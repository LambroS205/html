<?php
namespace App\Controllers;
use App\Core\Controller;

class CheckoutController extends Controller
{
    public function index()
    {
        $this->view('pages/checkout');
    }

    public function store()
    {
        // POST to checkout goes to the same file in classic PHP
        $this->view('pages/checkout');
    }

    public function vnpayReturn()
    {
        $this->view('pages/vnpay_return');
    }
}
