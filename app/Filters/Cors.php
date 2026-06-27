<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Izinkan semua domain, header, dan metode akses
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        
        // Jika browser mengirim Preflight 'OPTIONS', langsung beri status 200 OK dan berhentikan proses (agar tidak dicegat Router CI4)
        $method = $_SERVER['REQUEST_METHOD'] ?? $request->getMethod(true);
        if ($method === 'OPTIONS') {
            header('HTTP/1.1 200 OK');
            exit();
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada yang perlu dilakukan di sini
    }
}