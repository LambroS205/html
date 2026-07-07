<?php
use App\Core\Router;

// ==========================================
// ĐỊNH NGHĨA CÁC ĐƯỜNG DẪN (ROUTES)
// ==========================================

// Trang chủ
Router::get('/', 'HomeController@index');
Router::get('/index.php', 'HomeController@index');

// Blog
Router::get('/blog', 'BlogController@index');
Router::get('/blog.php', 'BlogController@index');
Router::get('/blog-detail', 'BlogController@detail'); 
Router::get('/blog-detail.php', 'BlogController@detailLegacy');

// Checkout
Router::get('/checkout', 'CheckoutController@index');
Router::get('/checkout.php', 'CheckoutController@index');
Router::post('/checkout', 'CheckoutController@store');
Router::post('/checkout.php', 'CheckoutController@store');

// Product
Router::get('/product', 'ProductController@detail');
Router::get('/product.php', 'ProductController@detailLegacy'); 
Router::get('/{slug}.html', 'ProductController@detail');

// Wishlist
Router::get('/wishlist', 'WishlistController@index');
Router::get('/wishlist.php', 'WishlistController@index');

// Profile
Router::get('/profile', 'ProfileController@index');
Router::get('/profile.php', 'ProfileController@index');

// Search (bao gồm cả chuẩn SEO)
Router::get('/search', 'SearchController@index');
Router::get('/search.php', 'SearchController@index');
Router::get('/danh-muc/{category}', 'SearchController@index');

// VNPay Callback
Router::get('/vnpay_return', 'CheckoutController@vnpayReturn');
Router::get('/vnpay_return.php', 'CheckoutController@vnpayReturn');

// (Lưu ý: Các API và Admin sẽ được xử lý độc lập hoặc migrate ở Phase 2)
