<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class Auth extends ResourceController
{
    use ResponseTrait;

    public function register()
    {
        // Header CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

        // Tangani preflight request OPTIONS
        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response->setStatusCode(200);
        }

        $db = \Config\Database::connect();

        // Mengambil data yang dikirim dari Flutter
        $name     = $this->request->getVar('name');
        $email    = $this->request->getVar('email');
        $password = $this->request->getVar('password'); 
        $role     = $this->request->getVar('role');
        
        // Data khusus Guru
        $nip      = $this->request->getVar('nip');
        $subject  = $this->request->getVar('subject_specialization'); // <-- Field Baru
        $bio      = $this->request->getVar('bio');                    // <-- Field Baru

        // Validasi input wajib
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            return $this->failValidationErrors('Harap isi semua data yang wajib.');
        }

        if ($role === 'guru' && empty($nip)) {
            return $this->failValidationErrors('NIP wajib diisi untuk pendaftaran Guru.');
        }

        // Mulai Transaksi Database
        $db->transStart();

        try {
            // 1. Menyimpan data ke tabel 'users'
            $userData = [
                'name'     => $name,
                'email'    => $email,
                'password' => $password, 
                'role'     => $role,
            ];
            
            $db->table('users')->insert($userData);
            
            // Mendapatkan ID user baru
            $userId = $db->insertID(); 

            // 2. Simpan ke tabel 'teacher_profiles' jika role = guru
            if ($role === 'guru') {
                $teacherData = [
                    'user_id'                => $userId,
                    'nip'                    => $nip,
                    'subject_specialization' => $subject, // <-- Simpan Mata Pelajaran
                    'bio'                    => $bio,     // <-- Simpan Bio
                ];
                
                $db->table('teacher_profiles')->insert($teacherData);
            }

            // Selesaikan transaksi
            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->failServerError('Gagal mendaftarkan akun. Transaksi dibatalkan.');
            }

            return $this->respondCreated([
                'status'  => 201,
                'message' => 'Registrasi berhasil'
            ]);

        } catch (\Exception $e) {
            return $this->failServerError('DB Error: ' . $e->getMessage());
        }
    }

    public function login()
    {
        // Header CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

        if ($this->request->getMethod() === 'OPTIONS') {
            return $this->response->setStatusCode(200);
        }

        $email    = $this->request->getVar('email');
        $password = $this->request->getVar('password');

        if (empty($email) || empty($password)) {
            return $this->failValidationErrors('Email dan Password wajib diisi.');
        }

        $db = \Config\Database::connect();
        $user = $db->table('users')->where('email', $email)->get()->getRowArray();

        // Cek apakah user ada dan password cocok (sesuai skema plain text database saat ini)
        if ($user && $user['password'] === $password) {
            return $this->respond([
                'status'  => 200,
                'message' => 'Login berhasil',
                'data'    => [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role'], // <-- Ini kunci agar Flutter tahu rutenya
                ]
            ]);
        }

        return $this->failUnauthorized('Email atau password salah');
    }
}