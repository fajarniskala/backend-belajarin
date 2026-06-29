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
    // REGISTER — dipanggil dari register_screen.dart
    // ─────────────────────────────────────────────────
    public function register()
    {
        // Handle preflight CORS
        if ($this->request->getMethod() === 'options') {
            return $this->jsonResponse([], 200);
        }

        try {
            $name     = $this->getInput('name');
            $email    = $this->getInput('email');
            $password = $this->getInput('password');
            $role     = $this->getInput('role', 'child');
            $nip      = $this->getInput('nip');
            $subject  = $this->getInput('subject_specialization');
            $bio      = $this->getInput('bio');

            // Validasi wajib
            if (empty($name) || empty($email) || empty($password)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Nama, Email, dan Password wajib diisi.']
                ], 400);
            }

            // Validasi format email sederhana
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Format email tidak valid.']
                ], 400);
            }

            // Validasi NIP wajib untuk Guru
            if ($role === 'guru' && empty($nip)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'NIP wajib diisi untuk akun Guru.']
                ], 400);
            }

            $db = \Config\Database::connect();

            // Cek duplikat email
            $exists = $db->table('users')->where('email', $email)->get()->getRowArray();
            if ($exists) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Email sudah terdaftar! Gunakan email lain.']
                ], 400);
            }

            // Insert ke tabel users
            // Catatan: password masih plain-text sesuai skema DB saat ini
            // TODO: ganti dengan password_hash($password, PASSWORD_DEFAULT) 
            //       saat tabel siap untuk production
            $db->table('users')->insert([
                'name'     => $name,
                'email'    => $email,
                'password' => $password,
                'role'     => $role,
            ]);

            $newUserId = $db->insertID();

            if (!$newUserId) {
                return $this->jsonResponse([
                    'status'  => 500,
                    'message' => 'Gagal menyimpan data user ke database.'
                ], 500);
            }

            // Jika Guru → insert ke teacher_profiles
            if ($role === 'guru') {
                $db->table('teacher_profiles')->insert([
                    'user_id'                => $newUserId,
                    'nip'                    => $nip,
                    'subject_specialization' => $subject,
                    'bio'                    => $bio,
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
            // Tangkap semua error agar Flutter tidak menerima HTML error page
            return $this->jsonResponse([
                'status'  => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────
    // REGISTER VIA LOGIN PAGE (register_page.dart)
    // Role: child / parent — tidak ada field guru
    // ─────────────────────────────────────────────────
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

            $db = \Config\Database::connect();

            $exists = $db->table('users')->where('email', $email)->get()->getRowArray();
            if ($exists) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Email sudah terdaftar!']
                ], 400);
            }

            $db->table('users')->insert([
                'name'     => $name,
                'email'    => $email,
                'password' => $password,
                'role'     => $role,
            ]);

            $newUserId = $db->insertID();

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

    // ─────────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────────
    public function login()
    {
        if ($this->request->getMethod() === 'options') {
            return $this->jsonResponse([], 200);
        }

        try {
            $email    = $this->getInput('email');
            $password = $this->getInput('password');

            if (empty($email) || empty($password)) {
                return $this->jsonResponse([
                    'status'   => 400,
                    'messages' => ['error' => 'Email dan Password wajib diisi.']
                ], 400);
            }

            $db   = \Config\Database::connect();
            $user = $db->table('users')->where('email', $email)->get()->getRowArray();

            if ($user && $user['password'] === $password) {
                return $this->jsonResponse([
                    'status'  => 200,
                    'message' => 'Login berhasil',
                    'data'    => [
                        'id'    => $user['id'],
                        'name'  => $user['name'],
                        'email' => $user['email'],
                        'role'  => $user['role'],
                    ]
                ], 200);
            }

            return $this->jsonResponse([
                'status'   => 401,
                'messages' => ['error' => 'Email atau password salah.']
            ], 401);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status'  => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}