<?php
namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

class Dashboard extends ResourceController
{
    use ResponseTrait;

    public function getUserStats()
    {
        // Header CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

        $db = \Config\Database::connect();

        // --- DATA PENGGUNA ---
        $builderUsers = $db->table('users');
        $guruCount    = $builderUsers->where('role', 'guru')->countAllResults();
        $parentCount  = $builderUsers->where('role', 'parent')->countAllResults();
        $childCount   = $builderUsers->where('role', 'child')->countAllResults();
        $totalUser    = $guruCount + $parentCount + $childCount;

        // --- DATA E-BOOK ---
        $builderEbooks = $db->table('ebooks');
        $totalEbooks   = $builderEbooks->countAllResults();

        // --- DATA AKTIVITAS E-BOOK TERBARU ---
        $builderRecent = $db->table('ebooks');
        $builderRecent->select('ebooks.title, users.name as uploader_name, users.role as uploader_role, ebooks.uploaded_at');
        // Join tabel users untuk mendapatkan nama uploader
        $builderRecent->join('users', 'users.id = ebooks.uploaded_by', 'left');
        // Urutkan dari yang terbaru (Descending)
        $builderRecent->orderBy('ebooks.uploaded_at', 'DESC');
        // Ambil 1 baris teratas
        $recentEbook = $builderRecent->get(1)->getRowArray();

        // Gabungkan data
        $data = [
            'guru'         => $guruCount,
            'parent'       => $parentCount,
            'child'        => $childCount,
            'total'        => $totalUser,
            'total_ebooks' => $totalEbooks,
            'recent_ebook' => $recentEbook, // <-- Data aktivitas dikirim ke Flutter
        ];

        return $this->respond([
            'status'  => 200,
            'message' => 'Berhasil mengambil data statistik',
            'data'    => $data,
        ], 200);
    }

    // ======================================================================
// 1. AMBIL SEMUA DATA GURU (READ)
// ======================================================================
    public function teachers()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        $db = \Config\Database::connect();
        // Ambil data dari tabel users yang rolenya murni 'guru'
        $teachers = $db->table('users')->where('role', 'guru')->where('is_verified', 1)->orderBy('id', 'DESC')->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $teachers,
        ], 200);
    }

// ======================================================================
// 2. TAMBAH AKUN GURU BARU (CREATE)
// ======================================================================
    public function addTeacher()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $name     = $this->request->getPost('name');
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if (! $name || ! $email || ! $password) {
            return $this->fail('Semua kolom data wajib diisi!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->insert([
            'name'       => $name,
            'email'      => $email,
            'password'   => $password, // Mengikuti password plain text bawaan DB kamu
            'role'       => 'guru',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Akun guru baru berhasil ditambahkan',
        ], 200);
    }

// ======================================================================
// 3. UBAH DATA GURU (UPDATE)
// ======================================================================
    public function updateTeacher()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id       = $this->request->getPost('id');
        $name     = $this->request->getPost('name');
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if (! $id || ! $name || ! $email || ! $password) {
            return $this->fail('Data pembaruan tidak lengkap!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->update([
            'name'       => $name,
            'email'      => $email,
            'password'   => $password,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Data guru berhasil diperbarui',
        ], 200);
    }

// ======================================================================
// 4. HAPUS AKUN GURU (DELETE)
// ======================================================================
    public function deleteTeacher()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id = $this->request->getPost('id');

        if (! $id) {
            return $this->fail('ID Guru tidak ditemukan!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->delete();

        return $this->respond([
            'status'  => 200,
            'message' => 'Akun guru sukses dihapus',
        ], 200);
    }

    // ======================================================================
// 1. AMBIL SEMUA DATA ORANG TUA (READ)
// ======================================================================
    public function parents()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        $db = \Config\Database::connect();
        // Mengambil user yang murni memiliki role 'parent'
        $parents = $db->table('users')->where('role', 'parent')->orderBy('id', 'DESC')->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $parents,
        ], 200);
    }

// ======================================================================
// 2. UBAH DATA ORANG TUA (UPDATE)
// ======================================================================
    public function updateParent()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id       = $this->request->getPost('id');
        $name     = $this->request->getPost('name');
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if (! $id || ! $name || ! $email || ! $password) {
            return $this->fail('Data pembaruan tidak lengkap!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->update([
            'name'       => $name,
            'email'      => $email,
            'password'   => $password,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Data orang tua berhasil diperbarui',
        ], 200);
    }

// ======================================================================
// 3. HAPUS AKUN ORANG TUA (DELETE)
// ======================================================================
    public function deleteParent()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id = $this->request->getPost('id');

        if (! $id) {
            return $this->fail('ID Orang Tua tidak ditemukan!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->delete();

        return $this->respond([
            'status'  => 200,
            'message' => 'Akun orang tua sukses dihapus',
        ], 200);
    }

    // ======================================================================
// 1. AMBIL SEMUA DATA ANAK / SISWA (READ)
// ======================================================================
    public function students()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        $db = \Config\Database::connect();

        // Melakukan Self-Join pada tabel users untuk menarik field nama orang tua
        $builder = $db->table('users as anak');
        $builder->select('anak.*, ortu.name as parent_name');
        $builder->join('users as ortu', 'ortu.id = anak.parent_id', 'left');
        $builder->where('anak.role', 'child');
        $builder->orderBy('anak.id', 'DESC');

        $students = $builder->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $students,
        ], 200);
    }

// ======================================================================
// 2. UBAH DATA ANAK / SISWA (UPDATE)
// ======================================================================
    public function updateStudent()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id       = $this->request->getPost('id');
        $name     = $this->request->getPost('name');
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if (! $id || ! $name || ! $email || ! $password) {
            return $this->fail('Data pembaruan tidak lengkap!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->update([
            'name'       => $name,
            'email'      => $email,
            'password'   => $password,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Data siswa berhasil diperbarui',
        ], 200);
    }

// ======================================================================
// 3. HAPUS AKUN ANAK / SISWA (DELETE)
// ======================================================================
    public function deleteStudent()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id = $this->request->getPost('id');

        if (! $id) {
            return $this->fail('ID Siswa tidak ditemukan!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->delete();

        return $this->respond([
            'status'  => 200,
            'message' => 'Akun siswa sukses dihapus',
        ], 200);
    }

    // ======================================================================
// AMBIL DATA LAPORAN STATISTIK SISTEM GLOBAL (ADMIN)
// ======================================================================
    public function getSystemReport()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        $db = \Config\Database::connect();

        // 1. Leaderboard Global Siswa (Top 5 Poin Tertinggi)
        $leaderboard = $db->table('users')
            ->select('id, name, email, total_points')
            ->where('role', 'child')
            ->orderBy('total_points', 'DESC')
            ->get(5)
            ->getResultArray();

        // 2. Akumulasi Total Poin Beredar di Sistem
        $pointsQuery = $db->table('users')
            ->where('role', 'child')
            ->selectSum('total_points', 'total')
            ->get()
            ->getRowArray();
        $totalPointsCirculating = (int) ($pointsQuery['total'] ?? 0);

        // 3. E-Book Terpopuler (Dihitung berdasarkan intensitas log di ebook_reading_logs)
        $popularEbooks = $db->table('ebook_reading_logs as log')
            ->select('buku.title, COUNT(log.id) as total_dibaca')
            ->join('ebooks as buku', 'buku.id = log.ebook_id', 'left')
            ->groupBy('log.ebook_id')
            ->orderBy('total_dibaca', 'DESC')
            ->get(5)
            ->getResultArray();

        $data = [
            'leaderboard'        => $leaderboard,
            'total_poin_beredar' => $totalPointsCirculating,
            'buku_populer'       => $popularEbooks,
        ];

        return $this->respond([
            'status'  => 200,
            'message' => 'Berhasil memuat laporan analitik sistem',
            'data'    => $data,
        ], 200);
    }

    // ======================================================================
    // 1. AMBIL SEMUA USER YANG BELUM DIVERIFIKASI (is_verified = 0)
    // ======================================================================
    public function getPendingUsers()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET");

        $db = \Config\Database::connect();

        // Ambil semua akun yang status verifikasinya masih 0
        $pendingUsers = $db->table('users')
            ->select('id, name, email, role, created_at')
            ->where('is_verified', 0)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $pendingUsers,
        ], 200);
    }

    // ======================================================================
    // 2. PROSES ACC ATAU TOLAK PENDAFTARAN AKUN MANDIRI
    // ======================================================================
    public function verifyUser()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $json = $this->request->getJSON();

        if ($json && isset($json->id) && isset($json->action)) {
            $userId = $json->id;
            $action = $json->action; // Menerima nilai 'approve' atau 'reject'

            $db = \Config\Database::connect();

            if ($action === 'approve') {
                // Jika disetujui, ubah nilai is_verified menjadi 1
                $db->table('users')->where('id', $userId)->update([
                    'is_verified' => 1,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                $message = 'Akun berhasil disetujui dan diaktifkan!';
            } else {
                // Jika ditolak, hapus data user tersebut dari database demi menghemat storage
                $db->table('users')->where('id', $userId)->delete();
                $message = 'Pendaftaran akun berhasil ditolak dan dihapus.';
            }

            return $this->respond([
                'status'  => 200,
                'message' => $message,
            ], 200);
        }

        return $this->fail('Data pengiriman tidak valid atau parameter kurang!', 400);
    }

    // ======================================================================
    // 1. AMBIL SEMUA DATA PENCAPAIAN / ACHIEVEMENT (READ)
    // ======================================================================
    public function getAchievements()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        $db = \Config\Database::connect();
        $achievements = $db->table('achievements')->orderBy('id', 'DESC')->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $achievements,
        ], 200);
    }

    // ======================================================================
    // 2. TAMBAH DATA ACHIEVEMENT BARU (CREATE)
    // ======================================================================
    public function addAchievement()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $title             = $this->request->getPost('title');
        $description       = $this->request->getPost('description');
        $icon              = $this->request->getPost('icon') ?? 'icon_star.png';
        $badgeImage        = $this->request->getPost('badge_image') ?? 'badge_diligent.png';
        $requiredCondition = $this->request->getPost('required_condition');
        $pointsReward      = $this->request->getPost('points_reward');

        if (!$title || !$requiredCondition) {
            return $this->fail('Judul pencapaian dan Syarat Kondisi wajib diisi!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('achievements')->insert([
            'title'              => $title,
            'description'        => $description,
            'icon'               => $icon,
            'badge_image'        => $badgeImage,
            'required_condition' => $requiredCondition,
            'points_reward'      => (int) $pointsReward,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Achievement baru berhasil ditambahkan!',
        ], 200);
    }

    // ======================================================================
    // 3. UBAH DATA ACHIEVEMENT (UPDATE)
    // ======================================================================
    public function updateAchievement()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id                = $this->request->getPost('id');
        $title             = $this->request->getPost('title');
        $description       = $this->request->getPost('description');
        $icon              = $this->request->getPost('icon');
        $badgeImage        = $this->request->getPost('badge_image');
        $requiredCondition = $this->request->getPost('required_condition');
        $pointsReward      = $this->request->getPost('points_reward');

        if (!$id || !$title || !$requiredCondition) {
            return $this->fail('Data pembaruan lencana tidak lengkap!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('achievements')->where('id', $id)->update([
            'title'              => $title,
            'description'        => $description,
            'icon'               => $icon,
            'badge_image'        => $badgeImage,
            'required_condition' => $requiredCondition,
            'points_reward'      => (int) $pointsReward,
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Data lencana achievement berhasil diperbarui',
        ], 200);
    }

    // ======================================================================
    // 4. HAPUS DATA ACHIEVEMENT (DELETE)
    // ======================================================================
    public function deleteAchievement()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $id = $this->request->getPost('id');

        if (!$id) {
            return $this->fail('ID lencana tidak ditemukan!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('achievements')->where('id', $id)->delete();

        return $this->respond([
            'status'  => 200,
            'message' => 'Lencana pencapaian sukses dihapus dari database',
        ], 200);
    }
}
