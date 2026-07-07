<?php
namespace App\Controllers;
use App\Core\Controller;

class BlogController extends Controller
{
    public function index()
    {
        $this->view('pages/blog');
    }

    public function detailLegacy()
    {
        $this->view('pages/blog-detail');
    }

    public function detail()
    {
        $this->view('pages/blog-detail');
    }
}
