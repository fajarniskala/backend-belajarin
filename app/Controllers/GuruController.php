<?php
namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

class GuruController extends ResourceController
{
    use ResponseTrait;

    public function guruStats()
    {
        $db = \Config\Database::connect();

        // Mengambil guru_id dari parameter query string di Flutter (?guru_id=...)
        $guruId = $this->request->getGet('guru_id');

        if (! $guruId) {
            return $this->fail('Parameter guru_id tidak ditemukan', 400);
        }

        // 1. Hitung total siswa milik guru ini
        $myStudents = $db->table('users')
            ->where('guru_id', $guruId)
            ->countAllResults();

        // 2. Hitung total modul pembelajaran milik guru ini
        $myModules = $db->table('modules')
            ->where('guru_id', $guruId)
            ->countAllResults();

        // 3. LOGIKA UTAMA: Hitung jumlah TUGAS MASUK (status = pending)
        // Lakukan JOIN ke tabel 'tasks' untuk menyaring tugas berdasarkan guru_id
        $pendingTasks = $db->table('task_submissions ts')
            ->join('tasks t', 'ts.task_id = t.id')
            ->where('t.guru_id', $guruId)
            ->where('ts.status', 'pending')
            ->countAllResults();

        // 4. Hitung total poin yang sudah diberikan (Sum nilai score siswa)
        $totalPointsRow = $db->table('task_submissions ts')
            ->join('tasks t', 'ts.task_id = t.id')
            ->where('t.guru_id', $guruId)
            ->selectSum('ts.score')
            ->get()->getRow();
        $totalPoints = $totalPointsRow ? ($totalPointsRow->score ?? 0) : 0;

        // 5. Ambil aktivitas siswa terbaru yang mengumpulkan tugas (untuk card aktivitas)
        $recentActivity = $db->table('task_submissions ts')
            ->select('ts.submitted_at, u.name as student_name, t.title as task_title')
            ->join('tasks t', 'ts.task_id = t.id')
            ->join('users u', 'ts.student_id = u.id')
            ->where('t.guru_id', $guruId)
            ->orderBy('ts.submitted_at', 'DESC')
            ->get()->getRowArray();

        $recentData = null;
        if ($recentActivity) {
            $recentData = [
                'title'        => 'Tugas Dikumpulkan',
                'student_name' => $recentActivity['student_name'],
                'action'       => 'mengumpulkan tugas "' . $recentActivity['task_title'] . '"',
                'created_at'   => date('d M, H:i', strtotime($recentActivity['submitted_at'])),
            ];
        }

        // Return data dalam format JSON yang dibaca oleh guru_dashboard_screen.dart
        return $this->respond([
            'status' => 200,
            'data'   => [
                'my_students'     => (int) $myStudents,
                'my_modules'      => (int) $myModules,
                'pending_tasks'   => (int) $pendingTasks, // Akan mengirim angka 1 berdasarkan gambar DB kamu
                'total_points'    => (int) $totalPoints,
                'recent_activity' => $recentData,
            ],
        ]);
    }

    public function getParents()
    {
        header('Access-Control-Allow-Origin: *'); // CORS

        $db = \Config\Database::connect();

        // Ambil data users yang role-nya 'parent'
        $parents = $db->table('users')
            ->select('id, name, email')
            ->where('role', 'parent')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $parents,
        ]);
    }

    public function addStudent()
    {
        // Izinkan akses CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

        // Tangani preflight request OPTIONS
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response->setStatusCode(200);
        }

        $db = \Config\Database::connect();

        // Ambil data JSON yang dikirim dari Flutter
        $json = $this->request->getJSON();

        if ($json) {
            $data = [
                'name'         => $json->name,
                'email'        => $json->email,
                'password'     => $json->password,
                'role'         => 'child',
                'guru_id'      => $json->guru_id,
                'parent_id'    => $json->parent_id,
                'is_verified'  => 0,
                'total_points' => 0,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];

            // Masukkan ke tabel users
            $db->table('users')->insert($data);

            return $this->respond([
                'status'  => 201, // 201 = Created
                'message' => 'Data siswa berhasil disimpan',
            ], 201);
        }

        return $this->fail('Data tidak lengkap atau tidak valid', 400);
    }

    public function addModule()
    {
        // Pengaturan CORS untuk Flutter
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Methods: POST, OPTIONS');

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $db = \Config\Database::connect();

        // Mengambil data teks dari Multipart Request menggunakan getPost()
        $guruId      = $this->request->getPost('guru_id');
        $categoryId  = $this->request->getPost('category_id');
        $title       = $this->request->getPost('title');
        $description = $this->request->getPost('description');
        $level       = $this->request->getPost('level');
        $totalPoints = $this->request->getPost('total_points');
        $orderSeq    = $this->request->getPost('order_seq');

        // Proses penanganan file PDF
        $pdfFile  = $this->request->getFile('file_pdf');
        $fileName = null;

        if ($pdfFile && $pdfFile->isValid() && ! $pdfFile->hasMoved()) {
            // Membuat nama acak baru agar nama file tidak bentrok di server
            $fileName = $pdfFile->getRandomName();

            // Memindahkan file ke folder: public/uploads/modules/
            $pdfFile->move(ROOTPATH . 'public/uploads/modules', $fileName);
        }

        // Susun data untuk di-insert ke database
        $data = [
            'guru_id'      => $guruId,
            'category_id'  => $categoryId,
            'title'        => $title,
            'description'  => $description,
            'level'        => $level,
            'total_points' => $totalPoints,
            'order_seq'    => $orderSeq,
            'file_pdf'     => $fileName, // Simpan nama file acak ke database
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        // Jalankan Query Insert
        $db->table('modules')->insert($data);

        return $this->respond([
            'status'    => 201,
            'message'   => 'Modul dan file PDF berhasil disimpan!',
            'file_name' => $fileName,
        ], 201);
    }

    public function getCategories()
    {
        // Pengaturan CORS
        header('Access-Control-Allow-Origin: *');

        $db = \Config\Database::connect();

        // Ambil id dan name dari tabel categories, urutkan berdasarkan ID
        $categories = $db->table('categories')
            ->select('id, name')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $categories,
        ]);
    }

    // 1. Ambil daftar modul untuk Dropdown di Flutter
    public function getGuruModules($guruId)
    {
        header('Access-Control-Allow-Origin: *');
        $db = \Config\Database::connect();

        $modules = $db->table('modules')
            ->select('id, title')
            ->where('guru_id', $guruId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $modules,
        ]);
    }

// 2. Simpan Tugas Baru ke Tabel tasks
    public function addTask()
    {

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $db   = \Config\Database::connect();
        $json = $this->request->getJSON();

        if ($json) {
            $data = [
                'module_id'   => $json->module_id,
                'guru_id'     => $json->guru_id,
                'title'       => $json->title,
                'description' => $json->description,
                'due_date'    => $json->due_date ? date('Y-m-d H:i:s', strtotime($json->due_date)) : null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ];

            $db->table('tasks')->insert($data);

            return $this->respond([
                'status'  => 201,
                'message' => 'Tugas baru berhasil ditambahkan!',
            ], 201);
        }

        return $this->fail('Data tidak valid', 400);
    }

    public function getTaskRecap($guruId = null)
    {
        $db = \Config\Database::connect();

        // 2. WAJIB HAPUS ATAU KOMENTARI BARIS INI (karena ini yang bikin error):
        // $guruId = $this->request->getGet('guru_id');

        // Baris di bawah ini biarkan tetap ada untuk validasi aman
        if (! $guruId) {
            return $this->fail('Parameter guru_id diperlukan', 400);
        }

        // 1. Ambil semua tugas yang dibuat oleh guru ini
        $tasks = $db->table('tasks')
            ->where('guru_id', $guruId)
            ->orderBy('created_at', 'DESC')
            ->get()->getResultArray();

        $result = [];

        foreach ($tasks as $task) {
            // 1. Hitung yang BELUM dinilai (Statusnya masih murni 'pending')
            $belumDinilai = $db->table('task_submissions')
                ->where('task_id', $task['id'])
                ->where('status', 'pending')
                ->countAllResults();

            // 2. Hitung yang SUDAH dinilai (Kuncinya: score sudah diisi / tidak NULL)
            // Ini jauh lebih aman daripada mencocokkan string status 'approved'/'graded'
            $sudahDinilai = $db->table('task_submissions')
                ->where('task_id', $task['id'])
                ->where('score IS NOT NULL')
                ->countAllResults();

            $result[] = [
                'id'            => (int) $task['id'],
                'title'         => $task['title'],
                'belum_dinilai' => (int) $belumDinilai,
                'sudah_dinilai' => (int) $sudahDinilai,
            ];
        }

        return $this->respond([
            'status' => 200,
            'data'   => $result,
        ]);
    }

    public function getTaskSubmissions($taskId = null)
    {
        $db = \Config\Database::connect();

        if (! $taskId) {
            return $this->fail('Parameter task_id diperlukan', 400);
        }

        // PERBAIKAN: Ubah t.deadline menjadi t.due_date agar sesuai dengan struktur tabel
        $submissions = $db->table('task_submissions ts')
            ->select('ts.*, u.name as student_name,
                  IF(ts.submitted_at > t.due_date, 1, 0) as is_late') // Ganti t.deadline jadi t.due_date
            ->join('users u', 'ts.student_id = u.id')
            ->join('tasks t', 'ts.task_id = t.id')
            ->where('ts.task_id', $taskId)
            ->orderBy('submitted_at', 'DESC')
            ->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $submissions,
        ]);
    }

    // ======================================================================
    // PROSES INPUT/UBAH NILAI TUGAS + TRIGGER OTOMATIS USER PROGRESS & POIN
    // ======================================================================
    public function gradeSubmission()
    {
        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $db   = \Config\Database::connect();
        $json = $this->request->getJSON();

        if ($json && isset($json->submission_id) && isset($json->score)) {
            $submissionId = $json->submission_id;
            $score        = $json->score;

            $data = [
                'score'      => (int) $score,
                'status'     => 'graded',
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $updated = $db->table('task_submissions')
                ->where('id', $submissionId)
                ->update($data);

            if ($updated) {
                // Tarik info dasar relasi tugas murid
                $submissionInfo = $db->table('task_submissions ts')
                    ->select('ts.student_id, t.module_id, m.total_points')
                    ->join('tasks t', 'ts.task_id = t.id')
                    ->join('modules m', 't.module_id = m.id', 'left')
                    ->where('ts.id', $submissionId)
                    ->get()->getRowArray();

                if ($submissionInfo) {
                    $studentId   = $submissionInfo['student_id'];
                    $moduleId    = $submissionInfo['module_id'];
                    $pointsByMod = (int)($submissionInfo['total_points'] ?? 50);

                    $existingProgress = $db->table('user_progress')
                        ->where('user_id', $studentId)
                        ->where('module_id', $moduleId)
                        ->get()->getRowArray();

                    $modulBaruSelesai = false;

                    if (!$existingProgress) {
                        $db->table('user_progress')->insert([
                            'user_id'       => $studentId,
                            'module_id'     => $moduleId,
                            'is_completed'  => 1,
                            'completed_at'  => date('Y-m-d H:i:s'),
                            'points_earned' => $pointsByMod,
                            'created_at'    => date('Y-m-d H:i:s'),
                            'updated_at'    => date('Y-m-d H:i:s')
                        ]);
                        $db->query("UPDATE users SET total_points = total_points + ? WHERE id = ?", [$pointsByMod, $studentId]);
                        $modulBaruSelesai = true;

                    } else if ((int)$existingProgress['is_completed'] === 0) {
                        $db->table('user_progress')
                            ->where('id', $existingProgress['id'])
                            ->update([
                                'is_completed'  => 1,
                                'completed_at'  => date('Y-m-d H:i:s'),
                                'points_earned' => $pointsByMod,
                                'updated_at'    => date('Y-m-d H:i:s')
                            ]);
                        $db->query("UPDATE users SET total_points = total_points + ? WHERE id = ?", [$pointsByMod, $studentId]);
                        $modulBaruSelesai = true;
                    }

                    // ======================================================================
                    // 🔥 EVALUASI BADGE MODUL SECARA OTOMATIS (DINAMIS DARI DATABASE)
                    // ======================================================================
                    if ($modulBaruSelesai) {
                        // Hitung berapa total modul yang sudah diselesaikan oleh anak ini
                        $totalSelesaiModul = $db->table('user_progress')
                            ->where(['user_id' => $studentId, 'is_completed' => 1])
                            ->countAllResults();

                        // 🎯 COCOKKAN LOGIKANYA DI SINI: Menyusun string "finish_module:X"
                        $modCondition = 'finish_module:' . $totalSelesaiModul;

                        // Cari di tabel achievements apakah ada badge dengan required_condition tersebut
                        $matchingModBadge = $db->table('achievements')
                            ->where('required_condition', $modCondition)
                            ->get()->getRowArray();

                        if ($matchingModBadge) {
                            $badgeId = (int)$matchingModBadge['id'];
                            
                            // Validasi keamanan ekstra agar badge tidak diklaim ganda
                            $alreadyEarnedBadge = $db->table('user_achievements')
                                ->where(['user_id' => $studentId, 'achievement_id' => $badgeId])
                                ->countAllResults();
                            
                            if ($alreadyEarnedBadge == 0) {
                                // Masukkan log perolehan badge anak
                                $db->table('user_achievements')->insert([
                                    'user_id'        => $studentId,
                                    'achievement_id' => $badgeId,
                                    'earned_at'      => date('Y-m-d H:i:s'),
                                ]);

                                // Tambahkan reward poin yang tertera di database (id 6 otomatis berbobot 50 poin!)
                                $bonusPoints = (int)($matchingModBadge['points_reward'] ?? 0);
                                if ($bonusPoints > 0) {
                                    $db->query("UPDATE users SET total_points = total_points + ? WHERE id = ?", [$bonusPoints, $studentId]);
                                }
                            }
                        }
                    }
                    // ======================================================================
                }

                return $this->respond([
                    'status'  => 200,
                    'message' => 'Nilai berhasil disimpan & progres belajar siswa diperbarui!',
                ], 200);
            }

            return $this->fail('Gagal memperbarui data di database', 500);
        }

        return $this->fail('Data input tidak lengkap atau tidak valid', 400);
    }

    public function streamSubmission($fileName)
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setHeader('Access-Control-Allow-Methods', 'GET');

        // Mengambil file dari folder writable/uploads/submissions/
        $filePath = WRITEPATH . 'uploads/submissions/' . $fileName;

        if (! file_exists($filePath)) {
            return $this->failNotFound('File tugas tidak ditemukan di server');
        }

        return $this->response
            ->setContentType('application/pdf')
            ->setBody(file_get_contents($filePath));
    }

    public function getGuruModulesDetailed($guruId = null)
    {
        $db = \Config\Database::connect();

        if (! $guruId) {
            return $this->fail('Parameter guru_id diperlukan', 400);
        }

        // Query untuk mengambil modul milik guru beserta nama kategorinya
        $modules = $db->table('modules m')
            ->select('m.id, m.title, m.description, m.file_pdf, m.level, m.total_points, m.order_seq, m.created_at, c.name as category_name')
            ->join('categories c', 'm.category_id = c.id')
            ->where('m.guru_id', $guruId)
            ->orderBy('m.order_seq', 'ASC')
            ->get()->getResultArray();

        // Memastikan casting tipe data integer aman untuk Flutter
        $result = [];
        foreach ($modules as $row) {
            $row['id']           = (int) $row['id'];
            $row['level']        = (int) $row['level'];
            $row['total_points'] = (int) $row['total_points'];
            $row['order_seq']    = (int) $row['order_seq'];
            $result[]            = $row;
        }

        return $this->respond([
            'status' => 200,
            'data'   => $result,
        ]);
    }

    public function streamModule($fileName)
    {
        // Berikan izin CORS agar bisa dibaca Flutter
        $this->response->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setHeader('Access-Control-Allow-Methods', 'GET');

        // ROOTPATH akan mengarah ke folder utama, lalu masuk ke public/uploads/modules/
        $filePath = ROOTPATH . 'public/uploads/modules/' . $fileName;

        if (! file_exists($filePath)) {
            return $this->failNotFound('File modul tidak ditemukan di server');
        }

        return $this->response
            ->setContentType('application/pdf')
            ->setBody(file_get_contents($filePath));
    }

    public function deleteModule($id = null)
    {
        // 1. TAMBAHKAN ATURAN HEADER CORS DI SINI
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

        // Tangani Preflight Request OPTIONS otomatis dari Flutter
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response->setStatusCode(200);
        }

        $db = \Config\Database::connect();

        if (! $id) {
            return $this->fail('Parameter ID modul diperlukan', 400);
        }

        // 1. Cari data modul untuk mengambil nama file PDF-nya
        $module = $db->table('modules')->where('id', $id)->get()->getRowArray();

        if (! $module) {
            return $this->failNotFound('Modul tidak ditemukan');
        }

        // 2. Jika ada file PDF fisik, hapus dari folder public/uploads/modules/
        if (! empty($module['file_pdf'])) {
            $filePath = FCPATH . 'uploads/modules/' . $module['file_pdf'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // 3. Hapus data dari tabel modules
        $deleted = $db->table('modules')->where('id', $id)->delete();

        if ($deleted) {
            return $this->respondDeleted([
                'status'  => 200,
                'message' => 'Modul berhasil dihapus dari sistem',
            ]);
        }

        return $this->fail('Gagal menghapus modul', 500);
    }

    public function getGuruStudents($guruId = null)
    {
        // Izinkan CORS agar bisa diakses Flutter
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET");

        $db = \Config\Database::connect();

        if (! $guruId) {
            return $this->fail('Parameter guru_id diperlukan', 400);
        }

        // Query mengambil data siswa beserta nama & email orang tuanya
        $students = $db->table('users s')
            ->select('s.id, s.name as student_name, s.email as student_email, p.name as parent_name, p.email as parent_email')
            ->join('users p', 's.parent_id = p.id', 'left') // Self-join ke data orang tua
            ->where('s.role', 'child')
            ->where('s.guru_id', $guruId)
            ->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $students,
        ]);
    }

    public function getGuruGradesRecap($guruId = null)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET");

        $db = \Config\Database::connect();

        if (! $guruId) {
            return $this->fail('Parameter guru_id diperlukan', 400);
        }

        // Query untuk mengambil nama siswa, total tugas dikerjakan, dan rata-rata nilainya
        $recap = $db->table('users u')
            ->select('u.id as student_id, u.name as student_name, u.email as student_email,
                  COUNT(ts.id) as total_tasks,
                  ROUND(AVG(ts.score), 1) as average_score')
            ->join('task_submissions ts', 'u.id = ts.student_id', 'left')
            ->where('u.role', 'child')
            ->where('u.guru_id', $guruId)
            ->groupBy('u.id')
            ->orderBy('average_score', 'DESC') // Urutkan dari nilai tertinggi
            ->get()->getResultArray();

        // Pastikan casting tipe data numerik aman untuk Dart
        $result = [];
        foreach ($recap as $row) {
            $row['student_id']    = (int) $row['student_id'];
            $row['total_tasks']   = (int) $row['total_tasks'];
            $row['average_score'] = $row['average_score'] !== null ? (float) $row['average_score'] : 0.0;
            $result[]             = $row;
        }

        return $this->respond([
            'status' => 200,
            'data'   => $result,
        ]);
    }

    public function uploadEbook()
    {
        // 1. Pengaturan CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Methods: POST, OPTIONS');

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $db = \Config\Database::connect();

        // 2. Tangkap Data Teks dari Flutter
        $uploadedBy  = $this->request->getPost('guru_id');
        $categoryId  = $this->request->getPost('category_id');
        $level       = $this->request->getPost('level');
        $title       = $this->request->getPost('title');
        $author      = $this->request->getPost('author');
        $totalPages  = $this->request->getPost('total_pages');
        $description = $this->request->getPost('description');

        // 3. Tangkap File PDF
        $pdfFile  = $this->request->getFile('file_pdf');
        $fileName = null;
        $fileSize = 0;

        if ($pdfFile && $pdfFile->isValid() && ! $pdfFile->hasMoved()) {
            // Beri nama acak agar tidak bentrok, dan ambil ukuran filenya
            $fileName = $pdfFile->getRandomName();
            $fileSize = $pdfFile->getSize();

            // Pindahkan file ke folder public/uploads/ebooks/
            $pdfFile->move(FCPATH . 'uploads/ebooks/', $fileName);
        } else {
            return $this->fail('File PDF wajib diunggah atau file tidak valid', 400);
        }

        // 4. Susun Data untuk Disimpan ke Tabel 'ebooks'
        $data = [
            'uploaded_by' => (int) $uploadedBy,
            'category_id' => $categoryId ? (int) $categoryId : null,
            'level'       => (int) $level,
            'title'       => $title,
            'author'      => $author,
            'file_url'    => $fileName,
            'total_pages' => (int) $totalPages,
            'file_size'   => $fileSize,
            'description' => $description,
            'is_active'   => 1, // Otomatis aktif
            'uploaded_at' => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        // 5. Simpan ke Database
        $db->table('ebooks')->insert($data);

        return $this->respond([
            'status'  => 201,
            'message' => 'Hore! E-Book berhasil diunggah dan disimpan!',
        ], 201);
    }

    // ======================================================================
    // UPDATE DATA PROFIL DARI SISI HALAMAN GURU
    // ======================================================================
    public function updateProfile()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id       = $this->request->getPost('id');
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if (!$id || !$email || !$password) {
            return $this->fail('Parameter pembaruan profil guru tidak lengkap!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->update([
            'email'      => $email,
            'password'   => $password, // Format plain text sesuai setup DB kamu
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Profil guru berhasil diperbarui',
        ], 200);
    }

}
