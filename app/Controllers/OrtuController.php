public function getChildActiveReading($parentId = null)
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: GET");

    $db = \Config\Database::connect();

    if (!$parentId) {
        return $this->fail('Parameter parent_id diperlukan', 400);
    }

    // 1. Cari data anak (role child) yang memiliki parent_id sesuai akun orang tua ini
    $child = $db->table('users')
                ->where('parent_id', $parentId)
                ->where('role', 'child')
                ->get()->getRowArray();

    if (!$child) {
        return $this->respond([
            'status' => 200,
            'message' => 'Anak belum terdaftar',
            'data' => []
        ]);
    }

    // 2. Ambil data buku yang SEDANG DIBACA oleh anak tersebut (is_finished = 0)
    // Kita JOIN ke tabel ebooks untuk mendapatkan judul & total halaman asli buku
    $activeBooks = $db->table('ebook_reading_logs erl')
        ->select('erl.id, erl.ebook_id, erl.last_page, erl.reading_duration, erl.last_read_at, b.title, b.total_pages')
        ->join('ebooks b', 'erl.ebook_id = b.id')
        ->where('erl.user_id', $child['id'])
        ->where('erl.is_finished', 0) // 0 berarti masih proses dibaca
        ->orderBy('erl.last_read_at', 'DESC')
        ->get()->getResultArray();

    return $this->respond([
        'status' => 200,
        'child_name' => $child['name'],
        'data' => $activeBooks
    ]);
}