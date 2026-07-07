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

            $response = array_merge([
                'success'   => true,
                'message'   => 'Đã thêm vào giỏ hàng',
            ], getCartSummary());
            break;

        case 'remove':
            $cartKey = (string) $productId;
            unset($_SESSION['cart'][$cartKey]);
            $response = array_merge([
                'success'   => true,
                'message'   => 'Đã xóa khỏi giỏ hàng',
            ], getCartSummary());
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
            $response = array_merge([
                'success'   => true,
                'message'   => 'Đã cập nhật giỏ hàng',
            ], getCartSummary());
            break;

        case 'apply_coupon':
            $code = strtoupper(trim($input['code'] ?? ''));
            if (empty($code)) {
                throw new Exception('Vui lòng nhập mã giảm giá');
            }

            $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = :code AND status = 1 AND (expiry_date IS NULL OR expiry_date >= NOW()) LIMIT 1");
            $stmt->execute([':code' => $code]);
            $coupon = $stmt->fetch();

            if (!$coupon) {
                throw new Exception('Mã giảm giá không hợp lệ hoặc đã hết hạn');
            }

            // Tính tạm subtotal
            $subtotal = 0;
            if (!empty($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $item) {
                    $subtotal += (float)$item['price'] * (int)$item['quantity'];
                }
            }

            if ($subtotal < (float)$coupon['min_order_value']) {
                throw new Exception('Đơn hàng chưa đạt giá trị tối thiểu ' . number_format($coupon['min_order_value'], 0, ',', '.') . ' VNĐ để áp dụng mã này');
            }

            $_SESSION['coupon'] = [
                'id' => $coupon['id'],
                'code' => $coupon['code'],
                'type' => $coupon['type'],
                'value' => (float)$coupon['value']
            ];

            $response = array_merge([
                'success' => true,
                'message' => 'Áp dụng mã giảm giá thành công'
            ], getCartSummary());
            break;

        case 'remove_coupon':
            unset($_SESSION['coupon']);
            $response = array_merge([
                'success' => true,
                'message' => 'Đã gỡ mã giảm giá'
            ], getCartSummary());
            break;

        case 'get':
            $response = array_merge([
                'success' => true
            ], getCartSummary());
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
 * Lấy tóm tắt giỏ hàng (sản phẩm, số lượng, subtotal, discount, shipping, vat, total)
 */
function getCartSummary(): array
{
    $cartItems = $_SESSION['cart'] ?? [];
    $subtotal = 0;
    $totalItems = 0;

    foreach ($cartItems as $item) {
        $subtotal += (float) $item['price'] * (int) $item['quantity'];
        $totalItems += (int) $item['quantity'];
    }

    $discount = 0;
    $couponData = null;
    if (isset($_SESSION['coupon'])) {
        $coupon = $_SESSION['coupon'];
        if ($coupon['type'] === 'percent') {
            $discount = $subtotal * ($coupon['value'] / 100);
        } else {
            $discount = $coupon['value'];
        }
        // Giới hạn giảm giá không vượt quá subtotal
        if ($discount > $subtotal) {
            $discount = $subtotal;
        }
        $couponData = [
            'code' => $coupon['code'],
            'discount_amount' => $discount
        ];
    }

    $freeShippingThreshold = 875000;
    $shippingFee = ($subtotal >= $freeShippingThreshold || $subtotal == 0) ? 0 : 125000;
    
    // Tạm tính trước VAT
    $amountBeforeVat = max(0, $subtotal - $discount);
    $vatRate = 0.10; // 10%
    $vat = $amountBeforeVat * $vatRate;
    
    $total = $amountBeforeVat + $shippingFee + $vat;

    return [
        'cart' => array_values($cartItems),
        'cartCount' => $totalItems,
        'summary' => [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'shipping' => round($shippingFee, 2),
            'vat'      => round($vat, 2),
            'total'    => round($total, 2)
        ],
        'coupon' => $couponData,
        'freeShippingThreshold' => $freeShippingThreshold
    ];
}
