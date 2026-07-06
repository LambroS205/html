<?php
/**
 * Cart API — AJAX Endpoint cho giỏ hàng
 * Xử lý: add, remove, update, get
 * 
 * Request: POST JSON { action, product_id, quantity }
 * Response: JSON { success, message, cartCount, cart }
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/db.php';

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action    = $input['action'] ?? '';
$productId = (int) ($input['product_id'] ?? 0);
$quantity  = max(1, (int) ($input['quantity'] ?? 1));

// Khởi tạo cart trong session nếu chưa có
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pdo = Database::getConnection();

try {
    switch ($action) {
        case 'add':
            if ($productId <= 0) {
                throw new Exception('Product ID không hợp lệ');
            }

            // Kiểm tra sản phẩm tồn tại & còn hàng
            $stmt = $pdo->prepare("SELECT id, name, price, sale_price, stock, image FROM products WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception('Sản phẩm không tồn tại');
            }

            if ($product['stock'] <= 0) {
                throw new Exception('Sản phẩm đã hết hàng');
            }

            // Thêm hoặc tăng số lượng trong cart
            $cartKey = (string) $productId;
            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $product['id'],
                    'name'       => $product['name'],
                    'price'      => (float) ($product['sale_price'] ?? $product['price']),
                    'image'      => $product['image'],
                    'quantity'   => $quantity,
                ];
            }

            // Không cho vượt quá stock
            if ($_SESSION['cart'][$cartKey]['quantity'] > $product['stock']) {
                $_SESSION['cart'][$cartKey]['quantity'] = $product['stock'];
            }

            $response = [
                'success'   => true,
                'message'   => 'Đã thêm vào giỏ hàng',
                'cartCount' => getCartCount(),
            ];
            break;

        case 'remove':
            $cartKey = (string) $productId;
            unset($_SESSION['cart'][$cartKey]);
            $response = [
                'success'   => true,
                'message'   => 'Đã xóa khỏi giỏ hàng',
                'cartCount' => getCartCount(),
            ];
            break;

        case 'update':
            $cartKey = (string) $productId;
            if (isset($_SESSION['cart'][$cartKey])) {
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$cartKey]);
                } else {
                    $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
                }
            }
            $response = [
                'success'   => true,
                'message'   => 'Đã cập nhật giỏ hàng',
                'cartCount' => getCartCount(),
            ];
            break;

        case 'get':
            $response = [
                'success'   => true,
                'cart'      => array_values($_SESSION['cart']),
                'cartCount' => getCartCount(),
            ];
            break;

        default:
            throw new Exception('Action không hợp lệ');
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Đếm tổng số items trong giỏ hàng
 */
function getCartCount(): int
{
    $count = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += (int) ($item['quantity'] ?? 0);
        }
    }
    return $count;
}
