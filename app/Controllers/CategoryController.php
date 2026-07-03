<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\CategoryModel;

class CategoryController extends ResourceController
{
    public function index()
    {
        $categoryModel = new CategoryModel();
        
        // Mengambil semua data kategori
        $categories = $categoryModel->findAll();
        
        // Mengembalikan respons dalam bentuk JSON
        return $this->respond($categories, 200);
    }
}