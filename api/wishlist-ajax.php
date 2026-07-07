<?php
/**
 * Wishlist API — AJAX Endpoint
 * Xử lý: toggle (thêm/xóa)
 * 
 * Request: POST JSON { action, product_id }
 * Response: JSON { success, message, is_wished, count }
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

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để sử dụng danh sách yêu thích', 'require_login' => true]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = $input['action'] ?? '';
$productId = (int) ($input['product_id'] ?? 0);
$userId = (int) $_SESSION['user']['id'];

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
    exit;
}

try {
    $pdo = Database::getConnection();

    if ($action === 'toggle') {
        // Kiểm tra xem sản phẩm có trong wishlist chưa
        $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = :u AND product_id = :p");
        $stmt->execute([':u' => $userId, ':p' => $productId]);
        $exists = $stmt->fetch();

        if ($exists) {
            // Đã có -> Xóa
            $pdo->prepare("DELETE FROM wishlists WHERE user_id = :u AND product_id = :p")
                ->execute([':u' => $userId, ':p' => $productId]);
            $isWished = false;
            $msg = "Đã bỏ khỏi danh sách yêu thích";
        } else {
            // Chưa có -> Thêm
            $pdo->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (:u, :p)")
                ->execute([':u' => $userId, ':p' => $productId]);
            $isWished = true;
            $msg = "Đã thêm vào danh sách yêu thích";
        }

        // Lấy lại tổng số wishlist
        $count = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = :u");
        $count->execute([':u' => $userId]);
        $totalWishlist = $count->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => $msg,
            'is_wished' => $isWished,
            'count' => $totalWishlist
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
