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

    public function getTaskRecap($guruId)
    {
        header('Access-Control-Allow-Origin: *');
        $db = \Config\Database::connect();

        // Query untuk mengambil tugas dan menghitung submisi secara otomatis
        $builder = $db->table('tasks t');
        $builder->select('t.id, t.title, t.due_date,
                      COUNT(ts.id) as total_submissions,
                      SUM(CASE WHEN ts.status = "pending" THEN 1 ELSE 0 END) as pending_count');
        $builder->join('task_submissions ts', 'ts.task_id = t.id', 'left');
        $builder->where('t.guru_id', $guruId);
        $builder->groupBy('t.id');
        $builder->orderBy('t.created_at', 'DESC');

        $recap = $builder->get()->getResultArray();

        return $this->respond([
            'status' => 200,
            'data'   => $recap,
        ]);
    }

}
