<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'icon', 'color_hex', 'min_age', 'max_age'];
    
    // (Opsional) Jika ingin otomatis menggunakan created_at & updated_at
    protected $useTimestamps = true; 
}