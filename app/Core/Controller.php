<?php
namespace App\Core;

class Controller
{
    /**
     * Tải View và truyền dữ liệu
     */
    protected function view($view, $data = [])
    {
        // Extract mảng thành các biến riêng lẻ
        extract($data);
        
        // Tạo đường dẫn tới file view
        // VD: view('pages/home') => app/Views/pages/home.php
        $file = __DIR__ . '/../Views/' . $view . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        } else {
            die("View does not exist: " . $view);
        }
    }
}
