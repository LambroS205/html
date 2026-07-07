<?php
namespace App\Controllers;
use App\Core\Controller;

class SearchController extends Controller
{
    public function index()
    {
        $this->view('pages/search');
    }
}
