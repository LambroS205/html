<?php
namespace App\Controllers;
use App\Core\Controller;

class ProductController extends Controller
{
    public function detailLegacy()
    {
        $this->view('pages/product');
    }

    public function detail()
    {
        $this->view('pages/product');
    }
}
