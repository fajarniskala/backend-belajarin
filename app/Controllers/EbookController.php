<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class EbookController extends ResourceController 
{
    public function getEbooksByChild($child_id = null) 
{
    $model = new \App\Models\EbookModel();
    
    // Debug: Cek apakah ada data di database
    $ebooks = $model->where('child_id', $child_id)
                    ->where('is_active', 1)
                    ->findAll();
    
    // Jika $ebooks kosong, kita kirim pesan jelas
    if (empty($ebooks)) {
        return $this->respond(["message" => "Data tidak ditemukan untuk ID anak: " . $child_id, "data" => []]);
    }

    return $this->respond($ebooks);
}
}