<?php
/**
 * Tệp duy nhất nhận mọi Request của ứng dụng (Front-Controller)
 * Toàn bộ Logic được xử lý thông qua MVC
 */

// Bật hiển thị lỗi trong quá trình dev
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Autoload đơn giản (hoặc require thủ công)
require_once __DIR__ . '/app/Core/Router.php';
require_once __DIR__ . '/app/Core/Controller.php';

// Các Controller
require_once __DIR__ . '/app/Controllers/HomeController.php';
require_once __DIR__ . '/app/Controllers/BlogController.php';
require_once __DIR__ . '/app/Controllers/CheckoutController.php';
require_once __DIR__ . '/app/Controllers/ProductController.php';
require_once __DIR__ . '/app/Controllers/ProfileController.php';
require_once __DIR__ . '/app/Controllers/SearchController.php';
require_once __DIR__ . '/app/Controllers/WishlistController.php';

// Nạp file định tuyến
require_once __DIR__ . '/routes/web.php';

// Lấy URI hiện tại
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Kích hoạt Router
\App\Core\Router::dispatch($uri, $method);
