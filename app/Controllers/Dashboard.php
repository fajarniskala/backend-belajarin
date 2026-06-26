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
}
