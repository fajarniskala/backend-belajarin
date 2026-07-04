<?php
namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class OrtuController extends BaseController
{
    use ResponseTrait;

    public function dashboard($parentId = null)
    {
        // 1. Izinkan akses CORS untuk Flutter
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $db = \Config\Database::connect();

        if (! $parentId) {
            return $this->fail('Parameter parent_id diperlukan', 400);
        }

        // 2. Cari data anak
        $child = $db->table('users')
            ->where('parent_id', $parentId)
            ->where('role', 'child')
            ->get()->getRowArray();

        if (! $child) {
            return $this->respond([
                'status'       => 200,
                'child_name'   => 'Anak',
                'stats'        => [
                    'buku_selesai'  => 0,
                    'sedang_dibaca' => 0,
                    'total_durasi'  => '0m',
                    'poin_anak'     => 0,
                ],
                'reading_logs' => [],
            ]);
        }

        $childId = $child['id'];

        // 3. STATISTIK BUKU
        $bukuSelesai = $db->table('ebook_reading_logs')
            ->where('user_id', $childId)
            ->where('is_finished', 1)
            ->countAllResults();

        $sedangDibaca = $db->table('ebook_reading_logs')
            ->where('user_id', $childId)
            ->where('is_finished', 0)
            ->countAllResults();

        // [PENTING] AMAN DARI ERROR NULL
        $durasiRow = $db->table('ebook_reading_logs')
            ->where('user_id', $childId)
            ->selectSum('reading_duration')
            ->get()->getRow();
        $totalMenit = $durasiRow ? ($durasiRow->reading_duration ?? 0) : 0;

        $jam          = floor($totalMenit / 60);
        $menit        = $totalMenit % 60;
        $durasiFormat = ($jam > 0) ? "{$jam}j {$menit}m" : "{$menit}m";

        // [PENTING] AMAN DARI ERROR NULL
        $progRow = $db->table('user_progress')
            ->where('user_id', $childId)
            ->where('is_completed', 1)
            ->selectSum('points_earned')
            ->get()->getRow();
        $progressPoints = $progRow ? ($progRow->points_earned ?? 0) : 0;

        $achRow = $db->table('user_achievements ua')
            ->join('achievements a', 'ua.achievement_id = a.id')
            ->where('ua.user_id', $childId)
            ->selectSum('a.points_reward')
            ->get()->getRow();
        $achievementPoints = $achRow ? ($achRow->points_reward ?? 0) : 0;

        $totalPoints = $progressPoints + $achievementPoints;

        // 4. RIWAYAT BACAAN
        $readingLogs = $db->table('ebook_reading_logs erl')
            ->select('erl.id, erl.ebook_id, erl.last_page, erl.reading_duration, erl.last_read_at, erl.is_finished, b.title, b.total_pages')
            ->join('ebooks b', 'erl.ebook_id = b.id')
            ->where('erl.user_id', $childId)
            ->orderBy('erl.last_read_at', 'DESC')
            ->get()->getResultArray();

        return $this->respond([
            'status'       => 200,
            'child_name'   => $child['name'],
            'stats'        => [
                'buku_selesai'  => (int) $bukuSelesai,
                'sedang_dibaca' => (int) $sedangDibaca,
                'total_durasi'  => $durasiFormat,
                'poin_anak'     => (int) $totalPoints,
            ],
            'reading_logs' => $readingLogs,
        ]);
    }

    public function getRiwayatBaca($parentId = null)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        $db = \Config\Database::connect();

        if (! $parentId) {
            return $this->fail('Parameter parent_id diperlukan', 400);
        }

        // 1. Cari data anak
        $child = $db->table('users')
            ->where('parent_id', $parentId)
            ->where('role', 'child')
            ->get()->getRowArray();

        if (! $child) {
            return $this->respond([
                'status'       => 200,
                'child_name'   => 'Anak',
                'total_durasi' => '0j 0m',
                'total_buku'   => 0,
                'riwayat'      => [],
            ]);
        }

        $childId = $child['id'];

        // 2. Ambil Semua Riwayat Bacaan Anak
        $riwayat = $db->table('ebook_reading_logs erl')
            ->select('erl.*, e.title, e.total_pages')
            ->join('ebooks e', 'erl.ebook_id = e.id')
            ->where('erl.user_id', $childId)
            ->orderBy('erl.last_read_at', 'DESC')
            ->get()->getResultArray();

        // 3. Kalkulasi Total Menit dari semua buku
        $totalMenit = 0;
        foreach ($riwayat as $row) {
            $totalMenit += (int) $row['reading_duration'];
        }
        $jam          = floor($totalMenit / 60);
        $menit        = $totalMenit % 60;
        $durasiFormat = ($jam > 0) ? "{$jam}j {$menit}m" : "{$menit}m";

        return $this->respond([
            'status'       => 200,
            'child_name'   => $child['name'],
            'total_durasi' => $durasiFormat,
            'total_buku'   => count($riwayat),
            'riwayat'      => $riwayat,
        ]);
    }

    // ======================================================================
// UPDATE DATA PROFIL DARI SISI HALAMAN ORANG TUA (PARENT)
// ======================================================================
    public function updateParentProfile()
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

        if (! $id || ! $email || ! $password) {
            return $this->fail('Data pembaruan data wali tidak lengkap!', 400);
        }

        $db = \Config\Database::connect();
        $db->table('users')->where('id', $id)->update([
            'email'      => $email,
            'password'   => $password, // Format string biasa sesuai konfigurasi DB kamu
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Profil wali murid berhasil diperbarui',
        ], 200);
    }
}
