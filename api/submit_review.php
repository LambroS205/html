<?php
/**
 * API - Submit Product Review
 * Xử lý đánh giá sản phẩm và upload ảnh.
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Kiểm tra xác thực (phải đăng nhập)
if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Bạn cần đăng nhập để đánh giá sản phẩm.']);
    exit;
}

$userId = (int) $_SESSION['user']['id'];

// 2. Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không hợp lệ.']);
    exit;
}

// Lấy dữ liệu từ POST
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($productId <= 0) {
    echo json_encode(['error' => 'Sản phẩm không hợp lệ.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['error' => 'Vui lòng chọn số sao từ 1 đến 5.']);
    exit;
}

try {
    $pdo = Database::getConnection();

    // 3. Kiểm tra xem người dùng đã mua sản phẩm này chưa
    // Đơn hàng phải ở trạng thái đã giao (delivered)
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(oi.id)
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.product_id = :product_id 
          AND o.user_id = :user_id 
          AND o.status = 'delivered'
    ");
    $stmtCheck->execute([
        ':product_id' => $productId,
        ':user_id' => $userId
    ]);
    $hasPurchased = $stmtCheck->fetchColumn() > 0;

    if (!$hasPurchased) {
        echo json_encode(['error' => 'Bạn phải mua và nhận thành công sản phẩm này mới được đánh giá.']);
        exit;
    }

    // (Tùy chọn) Có thể giới hạn mỗi user chỉ được đánh giá 1 lần mỗi sản phẩm
    $stmtReviewCheck = $pdo->prepare("SELECT id FROM reviews WHERE product_id = :product_id AND user_id = :user_id");
    $stmtReviewCheck->execute([':product_id' => $productId, ':user_id' => $userId]);
    if ($stmtReviewCheck->fetch()) {
        echo json_encode(['error' => 'Bạn đã đánh giá sản phẩm này rồi.']);
        exit;
    }

    // 4. Xử lý Upload Ảnh (nếu có)
    $images = [];
    if (!empty($_FILES['review_images']['name'][0])) {
        $uploadDir = __DIR__ . '/../assets/uploads/reviews/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $fileCount = count($_FILES['review_images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            // Giới hạn tối đa 3 ảnh
            if ($i >= 3) break;

            $tmpName = $_FILES['review_images']['tmp_name'][$i];
            $name = $_FILES['review_images']['name'][$i];
            $size = $_FILES['review_images']['size'][$i];
            $type = mime_content_type($tmpName);

            if ($size > $maxSize) {
                continue; // Bỏ qua ảnh quá lớn
            }
            if (!in_array($type, $allowedTypes)) {
                continue; // Bỏ qua file không phải ảnh
            }

            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $newName = uniqid('review_', true) . '.' . $ext;
            $destination = $uploadDir . $newName;

            if (move_uploaded_file($tmpName, $destination)) {
                $images[] = 'assets/uploads/reviews/' . $newName;
            }
        }
    }

    $imagesJson = empty($images) ? null : json_encode($images);

    // 5. Lưu đánh giá vào database
    $stmtInsert = $pdo->prepare("
        INSERT INTO reviews (product_id, user_id, rating, comment, images_json)
        VALUES (:product_id, :user_id, :rating, :comment, :images_json)
    ");
    $stmtInsert->execute([
        ':product_id' => $productId,
        ':user_id' => $userId,
        ':rating' => $rating,
        ':comment' => $comment,
        ':images_json' => $imagesJson
    ]);

    // 6. Cập nhật lại Rating của sản phẩm theo công thức Bayesian
    updateBayesianRating($pdo, $productId);

    echo json_encode([
        'success' => true, 
        'message' => 'Cảm ơn bạn đã đánh giá sản phẩm!'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}

