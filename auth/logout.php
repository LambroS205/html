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

// Xóa thông tin user và dọn dẹp giỏ hàng (vì giỏ hàng đã lưu trong DB)
unset($_SESSION['user']);
unset($_SESSION['cart']);
unset($_SESSION['coupon']);

// Regenerate session ID để an toàn
session_regenerate_id(true);

header('Location: /');
exit;

