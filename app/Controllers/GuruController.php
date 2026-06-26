<?php
namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

class GuruController extends ResourceController
{
    use ResponseTrait;

    public function guruStats()
    {
        header('Access-Control-Allow-Origin: *'); // Penting untuk CORS

        $guruId = $this->request->getVar('guru_id');

        if (empty($guruId)) {
            return $this->fail('ID Guru tidak ditemukan.');
        }

        $db = \Config\Database::connect();

        // 1. Hitung jumlah siswa
        $myStudentsCount = $db->table('users')
            ->where('role', 'child')
            ->where('guru_id', $guruId)
            ->countAllResults();

        // 2. Hitung modul
        $myModulesCount = $db->table('modules')
            ->where('guru_id', $guruId)
            ->countAllResults();

        // // 3. Aktivitas
        // $recentActivity = $db->table('activities')
        //     ->select('activities.*, users.name as student_name')
        //     ->join('users', 'users.id = activities.student_id')
        //     ->where('users.guru_id', $guruId)
        //     ->orderBy('activities.created_at', 'DESC')
        //     ->get()
        //     ->getRowArray();

        return $this->respond([
            'status' => 200,
            'data'   => [
                'my_students' => $myStudentsCount,
                'my_modules'  => $myModulesCount,
                //'recent_activity' => $recentActivity,
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

}
