<?php
namespace App\Controllers;
use App\Core\Controller;

class WishlistController extends Controller
{
    public function index()
    {
        $this->view('pages/wishlist');
    }
}
