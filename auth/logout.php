<?php
/**
 * Đăng xuất — BestBuy Store
 * 
 * Xóa session user và redirect về trang chủ
 * Session giỏ hàng được giữ nguyên (guest cart)
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

// Xóa thông tin user, giữ giỏ hàng
unset($_SESSION['user']);

// Regenerate session ID để an toàn
session_regenerate_id(true);

header('Location: /');
exit;

