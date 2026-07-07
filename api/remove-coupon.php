<?php
/**
 * API Remove Coupon
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

unset($_SESSION['coupon']);

echo json_encode([
    'success' => true,
    'message' => 'Đã gỡ mã giảm giá'
]);
