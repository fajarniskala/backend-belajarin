<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class Auth extends ResourceController
{
    use ResponseTrait;

    // ─────────────────────────────────────────────────
    // Helper: kirim JSON response tanpa konflik header
    // ─────────────────────────────────────────────────
    private function jsonResponse(array $data, int $statusCode = 200)
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setHeader('Content-Type', 'application/json')
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->setJSON($data);
    }

    // ─────────────────────────────────────────────────
    // Helper: ekstrak input dari JSON atau form-data
    // ─────────────────────────────────────────────────
    private function getInput(string $key, string $default = ''): string
    {
        $contentType = $this->request->getHeaderLine('Content-Type');
        $isJson      = str_contains($contentType, 'application/json');

        if ($isJson) {
            $json = $this->request->getJSON();
            return (string)($json->$key ?? $default);
        }

        return (string)($this->request->getVar($key) ?? $default);
    }

    // ─────────────────────────────────────────────────
    // REGISTER — dipanggil dari register_screen.dart (Lama)
    // ─────────────────────────────────────────────────
    public function register()
    {
        if ($this->request->getMethod() === 'options') {
            return $this->jsonResponse([], 200);
        }

        try {
            $name       = $this->getInput('name');
            $email      = $this->getInput('email');
            $password   = $this->getInput('password');
            $role       = $this->getInput('role', 'child');
            $nip        = $this->getInput('nip');
            $subject    = $this->getInput('subject_specialization');
            $bio        = $this->getInput('bio');
            $classGrade = $this->getInput('class_grade');

            if (empty($name) || empty($email) || empty($password)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Nama, Email, dan Password wajib diisi.']
                ], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Format email tidak valid.']
                ], 400);
            }

            if ($role === 'guru' && empty($nip)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'NIP wajib diisi untuk akun Guru.']
                ], 400);
            }

            $db = \Config\Database::connect();

            $exists = $db->table('users')->where('email', $email)->get()->getRowArray();
            if ($exists) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Email sudah terdaftar! Gunakan email lain.']
                ], 400);
            }

            $db->table('users')->insert([
                'name'        => $name,
                'email'       => $email,
                'password'    => $password,
                'role'        => $role,
                'is_verified' => 0, // 🔥 Default 0 sebelum di-ACC Admin
            ]);

            $newUserId = $db->insertID();

            if (!$newUserId) {
                return $this->jsonResponse([
                    'status'  => 500,
                    'message' => 'Gagal menyimpan data user ke database.'
                ], 500);
            }

            if ($role === 'guru') {
                $db->table('teacher_profiles')->insert([
                    'user_id'                => $newUserId,
                    'nip'                    => $nip,
                    'subject_specialization' => $subject,
                    'bio'                    => $bio,
                    'class_grade'            => $classGrade,
                ]);
            }

            return $this->jsonResponse([
                'status'  => 201,
                'message' => 'Registrasi berhasil!',
                'data'    => [
                    'id'    => $newUserId,
                    'name'  => $name,
                    'email' => $email,
                    'role'  => $role,
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status'  => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // 🔥 BAGIAN UTAMA YANG DIPERBAIKI: REGISTER VIA LOGIN PAGE (register_page.dart)
    // Kini mendukung pendaftaran role Guru lengkap dengan verifikasi Admin
    // ──────────────────────────────────────────────────────────────────────
    public function register_via_login()
    {
        if ($this->request->getMethod() === 'options') {
            return $this->jsonResponse([], 200);
        }

        try {
            $name     = $this->getInput('name');
            $email    = $this->getInput('email');
            $password = $this->getInput('password');
            $role     = $this->getInput('role', 'child');

            // 🌟 AMBIL PARAMETER LENGKAP: Orang Tua & Guru dari pendaftaran mandiri anak
            $parentId   = $this->getInput('parent_id');
            $guruId     = $this->getInput('guru_id'); // 🔥 Tambahan untuk menangkap ID Guru
            
            $nip        = $this->getInput('nip');
            $subject    = $this->getInput('subject_specialization');
            $classGrade = $this->getInput('class_grade');
            $bio        = $this->getInput('bio');

            if (empty($name) || empty($email) || empty($password)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Nama, Email, dan Password wajib diisi.']
                ], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Format email tidak valid.']
                ], 400);
            }

            if ($role === 'guru' && empty($nip)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'NIP wajib disertakan untuk registrasi akun Guru.']
                ], 400);
            }

            $db = \Config\Database::connect();

            $exists = $db->table('users')->where('email', $email)->get()->getRowArray();
            if ($exists) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Email sudah terdaftar!']
                ], 400);
            }

            // 🌟 SINKRONISASI COUPLING: Masukkan parent_id DAN guru_id murni jika yang daftar adalah Anak
            $db->table('users')->insert([
                'name'        => $name,
                'email'       => $email,
                'password'    => $password,
                'role'        => $role,
                'parent_id'   => $role === 'child' ? (!empty($parentId) ? (int)$parentId : null) : null,
                'guru_id'     => $role === 'child' ? (!empty($guruId) ? (int)$guruId : null) : null, // 🔥 Tersimpan aman di MySQL!
                'is_verified' => 0, 
            ]);

            $newUserId = $db->insertID();

            if ($role === 'guru' && $newUserId) {
                $db->table('teacher_profiles')->insert([
                    'user_id'                => $newUserId,
                    'nip'                    => $nip,
                    'subject_specialization' => $subject,
                    'bio'                    => $bio,
                    'class_grade'            => $classGrade,
                ]);
            }

            return $this->jsonResponse([
                'status'  => 201,
                'message' => 'Registrasi akun berhasil! Menunggu verifikasi dari pihak Admin.',
                'data'    => [
                    'id'    => $newUserId,
                    'name'  => $name,
                    'email' => $email,
                    'role'  => $role,
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status'  => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────
    // LOGIN — FORMAT PESAN DINAMIS & TERPROTEKSI AMAN
    // ─────────────────────────────────────────────────
    public function login()
    {
        // Mendukung input JSON maupun Form-Data secara fleksibel
        $email    = $this->getInput('email');
        $password = $this->getInput('password');

        if (empty($email) || empty($password)) {
            return $this->jsonResponse([
                'status' => 400,
                'error'  => 'Email dan Password wajib diisi!'
            ], 400);
        }

        $db   = \Config\Database::connect();
        $user = $db->table('users')->where('email', $email)->get()->getRowArray();

        if ($user) {
            // 1. Cek kecocokan password
            if ($user['password'] === $password) {
                
                // 2. 🔥 JIKA BELUM DIVALIDASI ADMIN: Kirim status 403 & pesan edukatif
                if ((int)$user['is_verified'] === 0) {
                    return $this->jsonResponse([
                        'status' => 403,
                        'error'  => 'Akun Anda belum aktif! Silakan tunggu verifikasi dan persetujuan dari Admin BelajarIn.'
                    ], 403);
                }

                // 3. Jika lolos verifikasi, kirim data sukses
                return $this->jsonResponse([
                    'status'  => 200,
                    'message' => 'Login Berhasil!',
                    'data'    => $user
                ], 200);
            }
        }

        // 4. JIKA EMAIL/PASSWORD SALAH: Kirim status 401 & pesan peringatan
        return $this->jsonResponse([
            'status' => 401,
            'error'  => 'Email atau Password salah!'
        ], 401);
    }
}