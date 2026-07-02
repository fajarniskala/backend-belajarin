<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class SiswaController extends BaseController
{
    use ResponseTrait;

    public function submitTask()
    {
        // Karena Anda sudah pakai Filter CORS global, tidak perlu header() di sini lagi!
        $db = \Config\Database::connect();

        $taskId         = $this->request->getPost('task_id');
        $studentId      = $this->request->getPost('student_id');
        $textSubmission = $this->request->getPost('text_submission');
        $file           = $this->request->getFile('file_submission');

        $fileName = null;

        // Proses upload file jika anak melampirkan file
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $fileName = $file->getRandomName();
            // Pastikan folder writable/uploads/submissions sudah dibuat!
            $file->move(WRITEPATH . 'uploads/submissions', $fileName);
        }

        $data = [
            'task_id'         => $taskId,
            'student_id'      => $studentId,
            'text_submission' => empty($textSubmission) ? null : $textSubmission,
            'status'          => 'pending',
            'submitted_at'    => date('Y-m-d H:i:s'),
        ];

        if ($fileName) {
            $data['file_submission'] = $fileName;
        }

        // Cek apakah siswa sudah pernah mengirim jawaban sebelumnya
        $existing = $db->table('task_submissions')
            ->where(['task_id' => $taskId, 'student_id' => $studentId])
            ->get()->getRow();

        if ($existing) {
            // Update (Revisi jawaban)
            $db->table('task_submissions')->where('id', $existing->id)->update($data);
        } else {
            // Insert baru
            $db->table('task_submissions')->insert($data);
        }

        return $this->respondCreated(['status' => 201, 'message' => 'Tugas berhasil dikumpulkan']);
    }

    public function getPendingTasks($studentId)
    {
        $db = \Config\Database::connect();

        // Query: Ambil semua tugas dari tabel 'tasks',
        // KECUALI tugas yang ID-nya sudah ada di 'task_submissions' untuk student_id ini.
        $sql = "SELECT t.* FROM tasks t
            WHERE t.id NOT IN (
                SELECT task_id FROM task_submissions WHERE student_id = ?
            ) ORDER BY t.created_at DESC";

        $tasks = $db->query($sql, [$studentId])->getResultArray();

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

        // 2. Jika belum pernah membaca apapun, berikan e-book pertama sebagai default awal
        if (! $log) {
            $ebook = $db->table('ebooks')->get()->getRowArray();
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

        $isFinished = ($lastPage >= $totalPages) ? 1 : 0;

        $data = [
            'user_id'      => $studentId,
            'ebook_id'     => $ebookId,
            'last_page'    => $lastPage,
            'is_finished'  => $isFinished,
            'last_read_at' => date('Y-m-d H:i:s'),
        ];

        $existing = $db->table('ebook_reading_logs')
            ->where(['user_id' => $studentId, 'ebook_id' => $ebookId])
            ->get()->getRow();

        if ($existing) {
            $db->table('ebook_reading_logs')->where('id', $existing->id)->update($data);
        } else {
            $data['started_at'] = date('Y-m-d H:i:s');
            $db->table('ebook_reading_logs')->insert($data);
        }

        // ================= LOGIKA UTAMA PEMBAGIAN POIN MEMBACA =================
        if ($isFinished == 1) {
            // 1. Hitung berapa total e-book yang SUDAH SELESAI dibaca oleh anak ini
            $totalFinishedEbooks = $db->table('ebook_reading_logs')
                ->where(['user_id' => $studentId, 'is_finished' => 1])
                ->countAllResults();

            // 2. Cek apakah jumlahnya memenuhi syarat untuk mendapatkan Badge "Pembaca Pertama" (1 buku)
            if ($totalFinishedEbooks == 1) {
                $this->triggerBadgeReward($studentId, 1); // 1 adalah ID Badge Pembaca Pertama
            }
            // 3. Cek apakah memenuhi syarat untuk Badge "Kutu Buku" (5 buku)
            else if ($totalFinishedEbooks == 5) {
                $this->triggerBadgeReward($studentId, 2); // 2 adalah ID Badge Kutu Buku
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

    public function getAllEbooks()
    {
        $db = \Config\Database::connect();

        // Ambil semua buku yang aktif di database
        $ebooks = $db->table('ebooks')
            ->where('is_active', 1)
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

        $sql = "SELECT e.*, erl.last_page, erl.is_finished 
                FROM ebooks e
                LEFT JOIN ebook_reading_logs erl ON e.id = erl.ebook_id AND erl.user_id = ?
                WHERE e.is_active = 1
                ORDER BY e.id DESC";

        $books = $db->query($sql, [$studentId])->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $books
        ]);
    }
}
