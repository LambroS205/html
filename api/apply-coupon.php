<?php
/**
 * API Apply Coupon - POST JSON
 * Nhận `code`, kiểm tra và lưu vào $_SESSION['coupon']
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($input['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá']);
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = :code LIMIT 1");
    $stmt->execute([':code' => $code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        throw new Exception("Mã giảm giá không tồn tại");
    }

    if ($coupon['status'] == 0) {
        throw new Exception("Mã giảm giá này đã bị vô hiệu hóa");
    }

    if ($coupon['expiry_date'] && strtotime($coupon['expiry_date']) < time()) {
        throw new Exception("Mã giảm giá đã hết hạn sử dụng");
    }

    // Tính subtotal hiện tại của giỏ hàng
    $cartItems = $_SESSION['cart'] ?? [];
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += (float)$item['price'] * (int)$item['quantity'];
    }

    if ($subtotal == 0) {
        throw new Exception("Giỏ hàng của bạn đang trống");
    }

    if ((float)$coupon['min_order_value'] > 0 && $subtotal < (float)$coupon['min_order_value']) {
        throw new Exception("Đơn hàng phải từ " . number_format($coupon['min_order_value'], 0, ',', '.') . "đ để áp dụng mã này");
    }

    // Lưu vào session
    $_SESSION['coupon'] = [
        'id' => $coupon['id'],
        'code' => $coupon['code'],
        'type' => $coupon['type'],
        'value' => (float)$coupon['value']
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Áp dụng mã giảm giá thành công!',
        'coupon' => $_SESSION['coupon']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
