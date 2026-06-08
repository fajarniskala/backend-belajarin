<?php 
namespace App\Models;
use CodeIgniter\Model;

class EbookModel extends Model {
    protected $table = 'ebooks';
    protected $primaryKey = 'id';
    protected $allowedFields = ['uploaded_by', 'child_id', 'title', 'file_url', 'is_active'];
}