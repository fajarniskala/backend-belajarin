<?php
namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class SiswaController extends BaseController
{
    use ResponseTrait;

    public function submitTask()
    {
        // 1. ATUR HEADER CORS agar request multipart-form dari Flutter tidak diblokir
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        try {
            $db = \Config\Database::connect();

            // 2. TANGKAP INPUT DATA TEXT DARI FLUTTER
            $taskId    = $this->request->getPost('task_id');
            $studentId = $this->request->getPost('student_id');
            $textSub   = $this->request->getPost('text_submission');

            if (empty($taskId) || empty($studentId)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 400,
                    'message' => 'Parameter task_id dan student_id wajib disertakan.',
                ]);
            }

            // 3. CEK DEADLINE DARI TABEL TASKS
            $task = $db->table('tasks')->where('id', $taskId)->get()->getRowArray();

            // Antisipasi fleksibel: mengecek apakah nama kolom di tabelmu 'deadline' atau 'due_date'
            $deadline = $task['deadline'] ?? $task['due_date'] ?? null;

            // 4. TENTUKAN STATUS KUMPUL (Jika melewati batas waktu set 'late', jika tidak set 'pending')
            $now    = date('Y-m-d H:i:s');
            $status = 'pending';
            if ($deadline && $now > $deadline) {
                $status = 'late';
            }

            // 5. PROSES UPLOAD FILE (PDF / FOTO JAWABAN)
            $fileName = null;
            $file     = $this->request->getFile('file_submission');

            if ($file && $file->isValid() && ! $file->hasMoved()) {
                // Buat nama berkas acak yang unik agar tidak saling menimpa
                $fileName = $file->getRandomName();
                // Pindahkan file ke folder aman di dalam writable path sesuai setup awal kita
                $file->move(WRITEPATH . 'uploads/submissions/', $fileName);
            }

            // 6. CEK APAKAH SISWA SUDAH PERNAH MENGUMPULKAN (Logika Revisi)
            $existing = $db->table('task_submissions')
                ->where('task_id', $taskId)
                ->where('student_id', $studentId)
                ->get()->getRowArray();

            // Susun cetak data payload ke database
            $data = [
                'text_submission' => $textSub,
                'status'          => $status,
                'updated_at'      => $now,
            ];

            // Jika ada file baru yang diunggah, sisipkan nama filenya
            if ($fileName !== null) {
                // Hapus berkas lama dari disk server jika siswa melakukan pengiriman ulang (revisi)
                if ($existing && ! empty($existing['file_submission'])) {
                    $oldFilePath = WRITEPATH . 'uploads/submissions/' . $existing['file_submission'];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
                $data['file_submission'] = $fileName;
            }

            // 7. EKSEKUSI OPERASI QUERY KE DATABASE task_submissions
            if ($existing) {
                // Jika data sudah ada, lakukan UPDATE (Reset nilai jadi NULL agar guru menilai ulang revisinya)
                $data['score'] = null;
                $db->table('task_submissions')->where('id', $existing['id'])->update($data);
                $message = 'Tugas (Revisi) berhasil diperbarui! 🎉';
            } else {
                // Jika pengumpulan pertama kali, lakukan INSERT data baru
                $data['task_id']      = $taskId;
                $data['student_id']   = $studentId;
                $data['score']        = null;
                $data['submitted_at'] = $now;

                $db->table('task_submissions')->insert($data);
                $message = 'Hore! Jawaban berhasil dikirim! 🎉';
            }

            // Kembalikan response sukses berbentuk JSON bersih ke Flutter
            return $this->response->setStatusCode(200)->setJSON([
                'status'  => 200,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            // Tangkap crash crash internal dan kembalikan dalam format JSON agar app Flutter tidak patah
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 500,
                'message' => 'Terjadi kesalahan internal server: ' . $e->getMessage(),
            ]);
        }
    }

    public function getPendingTasks($studentId)
    {
        $db = \Config\Database::connect();

        // 1. Ambil data siswa untuk mengetahui guru_id-nya terlebih dahulu
        $student = $db->table('users')->where('id', $studentId)->get()->getRow();

        if (! $student) {
            return $this->failNotFound('Siswa tidak ditemukan');
        }

        $guruId = $student->guru_id;

        // 2. Query: Ambil tugas dari tabel 'tasks' HANYA MILIK guru_id siswa ini,
        // KECUALI tugas yang ID-nya sudah ada di 'task_submissions' untuk student_id ini.
        $sql = "SELECT t.* FROM tasks t
            WHERE t.guru_id = ? AND t.id NOT IN (
                SELECT task_id FROM task_submissions WHERE student_id = ?
            ) ORDER BY t.created_at DESC";

        // Masukkan $guruId dan $studentId secara berurutan menggantikan tanda "?"
        $tasks = $db->query($sql, [$guruId, $studentId])->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $tasks,
        ]);
    }

    public function getCategoriesWithCount($studentId)
    {
        $db = \Config\Database::connect();

        // 1. Amit guru_id dari siswa yang sedang login
        $student = $db->table('users')->where('id', $studentId)->get()->getRow();
        if (! $student) {
            return $this->failNotFound('Siswa tidak ditemukan');
        }
        $guruId = $student->guru_id;

        // 2. Query ambil semua kategori + COUNT jumlah modul milik guru tersebut
        $sql = "SELECT c.id, c.name, c.color_hex, c.icon, COUNT(m.id) as total_modul
            FROM categories c
            LEFT JOIN modules m ON m.category_id = c.id AND m.guru_id = ?
            GROUP BY c.id, c.name, c.color_hex, c.icon";

        $categories = $db->query($sql, [$guruId])->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $categories,
        ]);
    }

    public function getLatestReadingLog($studentId)
    {
        $db = \Config\Database::connect();

        // 1. Cari log bacaan terakhir milik siswa ini
        $log = $db->table('ebook_reading_logs erl')
            ->select('erl.*, e.title, e.total_pages, e.file_url')
            ->join('ebooks e', 'e.id = erl.ebook_id')
            ->where('erl.user_id', $studentId)
            ->orderBy('erl.last_read_at', 'DESC')
            ->get()->getRowArray();

        // 2. Jika belum pernah membaca apapun, berikan e-book pertama HANYA DARI GURUNYA sebagai default awal
        if (! $log) {
            $student = $db->table('users')->where('id', $studentId)->get()->getRow();

            if ($student) {
                // Filter cari buku yang diupload oleh guru anak ini
                $ebook = $db->table('ebooks')
                    ->where('is_active', 1)
                    ->where('uploaded_by', $student->guru_id)
                    ->get()->getRowArray();

                if ($ebook) {
                    $log = [
                        'id'               => null,
                        'user_id'          => $studentId,
                        'ebook_id'         => $ebook['id'],
                        'last_page'        => 1,
                        'total_pages_read' => 0,
                        'is_finished'      => 0,
                        'title'            => $ebook['title'],
                        'total_pages'      => $ebook['total_pages'],
                        'file_url'         => $ebook['file_url'],
                    ];
                } else {
                    return $this->respond(['status' => 200, 'data' => null]);
                }
            } else {
                return $this->respond(['status' => 200, 'data' => null]);
            }
        }

        return $this->respond(['status' => 200, 'data' => $log]);
    }

    public function saveReadingLog()
    {
        $db = \Config\Database::connect();

        $studentId  = $this->request->getPost('user_id');
        $ebookId    = $this->request->getPost('ebook_id');
        $lastPage   = $this->request->getPost('last_page');
        $totalPages = $this->request->getPost('total_pages');

        // --- TAMBAHAN BARU: Menangkap durasi (dalam menit) ---
        $duration = (int) $this->request->getPost('reading_duration');

        $isFinished = ($lastPage >= $totalPages) ? 1 : 0;

        // Cek apakah anak sudah pernah membaca buku ini
        $existing = $db->table('ebook_reading_logs')
            ->where(['user_id' => $studentId, 'ebook_id' => $ebookId])
            ->get()->getRow();

        if ($existing) {
            // Jika sudah pernah, tambahkan (akumulasikan) waktu baca lama + sesi baru
            $newDuration = $existing->reading_duration + $duration;

            $data = [
                'last_page'        => $lastPage,
                'is_finished'      => $isFinished,
                'reading_duration' => $newDuration, // Simpan total waktu ke DB
                'last_read_at'     => date('Y-m-d H:i:s'),
            ];
            $db->table('ebook_reading_logs')->where('id', $existing->id)->update($data);
        } else {
            // Jika ini pertama kali anak membaca buku ini
            $data = [
                'user_id'          => $studentId,
                'ebook_id'         => $ebookId,
                'last_page'        => $lastPage,
                'is_finished'      => $isFinished,
                'reading_duration' => $duration, // Simpan waktu perdana
                'last_read_at'     => date('Y-m-d H:i:s'),
                'started_at'       => date('Y-m-d H:i:s'),
            ];
            $db->table('ebook_reading_logs')->insert($data);
        }

        // ================= LOGIKA UTAMA PEMBAGIAN POIN MEMBACA =================
        if ($isFinished == 1) {
            $totalFinishedEbooks = $db->table('ebook_reading_logs')
                ->where(['user_id' => $studentId, 'is_finished' => 1])
                ->countAllResults();

            if ($totalFinishedEbooks == 1) {
                $this->triggerBadgeReward($studentId, 1);
            } else if ($totalFinishedEbooks == 5) {
                $this->triggerBadgeReward($studentId, 2);
            }
        }

        return $this->respond(['status' => 200, 'message' => 'Progress baca berhasil disimpan']);
    }

    private function triggerBadgeReward($studentId, $achievementId)
    {
        $db = \Config\Database::connect();

        // Pastikan anak belum pernah mengklaim badge ini sebelumnya
        $alreadyEarned = $db->table('user_achievements')
            ->where(['user_id' => $studentId, 'achievement_id' => $achievementId])
            ->countAllResults();

        if ($alreadyEarned == 0) {
            $db->table('user_achievements')->insert([
                'user_id'        => $studentId,
                'achievement_id' => $achievementId,
                'earned_at'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function streamEbook($id)
    {
        $db = \Config\Database::connect();

        // Cari data ebook berdasarkan ID
        $ebook = $db->table('ebooks')->where('id', $id)->get()->getRowArray();

        if (! $ebook) {
            return $this->failNotFound('Ebook tidak ditemukan');
        }

        // FCPATH akan otomatis mengarah ke folder 'public/' backend Anda
        $filePath = FCPATH . 'uploads/ebooks/' . $ebook['file_url'];

        if (! file_exists($filePath)) {
            return $this->failNotFound('File PDF tidak ditemukan di server');
        }

        // Mengirimkan file dengan paksaan Header CORS agar diizinkan oleh Browser Chrome
        return $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setHeader('Access-Control-Allow-Methods', 'GET')
            ->setContentType('application/pdf')
            ->setBody(file_get_contents($filePath));
    }

    public function getGamificationStats($studentId)
    {
        $db = \Config\Database::connect();

        // 1. Hitung total poin dasar dari riwayat pengerjaan modul (user_progress)
        $progressPoints = $db->table('user_progress')
            ->where('user_id', $studentId)
            ->where('is_completed', 1)
            ->selectSum('points_earned')
            ->get()->getRow()->points_earned ?? 0;

        // 2. Hitung total poin bonus dari badge yang berhasil dibuka (user_achievements)
        $achievementPoints = $db->table('user_achievements ua')
            ->join('achievements a', 'ua.achievement_id = a.id')
            ->where('ua.user_id', $studentId)
            ->selectSum('a.points_reward')
            ->get()->getRow()->points_reward ?? 0;

        // 3. Hitung berapa jumlah badge yang dimiliki anak
        $totalBadges = $db->table('user_achievements')
            ->where('user_id', $studentId)
            ->countAllResults();

        // Total poin adalah gabungan poin aktivitas + poin bonus badge
        $totalPoints = $progressPoints + $achievementPoints;

        return $this->respond([
            'status' => 200,
            'data'   => [
                'total_poin'  => (int) $totalPoints,
                'total_badge' => (int) $totalBadges,
                'hari_streak' => 5, // Bisa dibuat dinamis nanti jika tabel log login sudah ada
            ],
        ]);
    }

    public function getSubmitedTasks($studentId)
    {
        $db = \Config\Database::connect();

        // Mengambil semua jawaban siswa dikombinasikan dengan judul tugasnya
        $sql = "SELECT ts.id, ts.score, ts.status, ts.submitted_at, t.title
            FROM task_submissions ts
            JOIN tasks t ON ts.task_id = t.id
            WHERE ts.student_id = ?
            ORDER BY ts.submitted_at DESC";

        $submissions = $db->query($sql, [$studentId])->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $submissions,
        ]);
    }

    public function getAllEbooks($studentId = null)
    {
        $db = \Config\Database::connect();

        if (! $studentId) {
            return $this->fail('Parameter student_id diperlukan', 400);
        }

        // Cari siapa guru_id dari anak ini
        $student = $db->table('users')->where('id', $studentId)->get()->getRow();
        if (! $student) {
            return $this->failNotFound('Siswa tidak ditemukan');
        }

        // Ambil semua buku yang aktif DAN diupload oleh guru anak tersebut
        $ebooks = $db->table('ebooks')
            ->where('is_active', 1)
            ->where('uploaded_by', $student->guru_id)
            ->orderBy('id', 'DESC')
            ->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $ebooks,
        ]);
    }

    public function getModulesByCategory($studentId, $categoryId)
    {
        $db = \Config\Database::connect();

        // 1. Ambil data siswa untuk mengetahui guru_id-nya
        $student = $db->table('users')->where('id', $studentId)->get()->getRow();
        if (! $student) {
            return $this->failNotFound('Siswa tidak ditemukan');
        }
        $guruId = $student->guru_id;

        // 2. Query modul berdasarkan category_id milik guru tersebut
        $modules = $db->table('modules')
            ->where(['category_id' => $categoryId, 'guru_id' => $guruId])
            ->orderBy('order_seq', 'ASC')
            ->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $modules,
        ]);
    }

    public function getLibraryBooks($studentId)
    {
        $db = \Config\Database::connect();

        $student = $db->table('users')->where('id', $studentId)->get()->getRow();
        if (! $student) {
            return $this->failNotFound('Siswa tidak ditemukan');
        }

        // Tambahkan filter "e.uploaded_by = ?" untuk memastikan e-book milik gurunya
        $sql = "SELECT e.*, erl.last_page, erl.is_finished
                FROM ebooks e
                LEFT JOIN ebook_reading_logs erl ON e.id = erl.ebook_id AND erl.user_id = ?
                WHERE e.is_active = 1 AND e.uploaded_by = ?
                ORDER BY e.id DESC";

        // Masukkan parameter $studentId dan $guru_id
        $books = $db->query($sql, [$studentId, $student->guru_id])->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $books,
        ]);
    }

    public function streamModul($fileName = null)
    {
        // 1. Izinkan CORS agar Flutter bisa mengakses file tanpa diblokir
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        // Tangani request OPTIONS dari Flutter
        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        if (! $fileName) {
            return $this->fail('Nama file tidak diberikan', 400);
        }

        // 2. AMBIL DARI FOLDER PUBLIC SESUAI LOKASIMU
        // FCPATH otomatis mengarah ke E:\Dev\Flutter PMO\Final Project\backend\belajarin-api\public\
        $path = FCPATH . 'uploads/modules/' . $fileName;

        // Cek apakah file fisiknya benar-benar ada
        if (! file_exists($path)) {
            return $this->failNotFound('File PDF tidak ditemukan di path: ' . $path);
        }

        // 3. Baca isi file fisik PDF
        $file = file_get_contents($path);

        // 4. Atur header Content-Type agar Flutter tahu bahwa ini adalah file PDF murni
        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $fileName . '"')
            ->setBody($file);
    }
    public function getAchievements($studentId = null)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        if (! $studentId) {
            return $this->fail('Parameter student_id diperlukan', 400);
        }

        $db = \Config\Database::connect();

        // Query gabungan (LEFT JOIN) untuk mengambil SEMUA badge
        // dan mengecek apakah ID anak ada di tabel user_achievements
        $sql = "
        SELECT a.*,
               IF(ua.id IS NOT NULL, 1, 0) as unlocked,
               ua.earned_at
        FROM achievements a
        LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
        ORDER BY a.points_reward ASC
    ";

        $achievements = $db->query($sql, [$studentId])->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $achievements,
        ]);
    }
}
