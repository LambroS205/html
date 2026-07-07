<?php
/**
 * Cart API — AJAX Endpoint cho giỏ hàng
 * Xử lý: add, remove, update, get
 * 
 * Request: POST JSON { action, variant_id, quantity }
 * Response: JSON { success, message, cartCount, cart }
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

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
$variantId = (int) ($input['variant_id'] ?? 0);
$quantity  = max(1, (int) ($input['quantity'] ?? 1));

// Khởi tạo cart trong session nếu chưa có
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pdo = Database::getConnection();

try {
    switch ($action) {
        case 'add':
            if ($variantId <= 0) {
                throw new Exception('Variant ID không hợp lệ');
            }

            // Kiểm tra sản phẩm tồn tại & còn hàng
            $stmt = $pdo->prepare("
                SELECT pv.id as variant_id, p.id as product_id, p.name, pv.price, pv.sale_price, pv.stock, pv.image_url as image 
                FROM product_variants pv 
                JOIN products p ON pv.product_id = p.id 
                WHERE pv.id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $variantId]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception('Sản phẩm không tồn tại');
            }

            if ($product['stock'] <= 0) {
                throw new Exception('Sản phẩm đã hết hàng');
            }

            // Thêm hoặc tăng số lượng trong cart
            $cartKey = (string) $variantId;
            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'variant_id' => $product['variant_id'],
                    'product_id' => $product['product_id'],
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

            // Sync with DB if logged in
            if (isset($_SESSION['user'])) {
                $userId = (int) $_SESSION['user']['id'];
                $pdo->prepare("
                    INSERT INTO cart_items (user_id, product_id, variant_id, quantity) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = ?
                ")->execute([
                    $userId,
                    $product['product_id'],
                    $product['variant_id'],
                    $_SESSION['cart'][$cartKey]['quantity'],
                    $_SESSION['cart'][$cartKey]['quantity']
                ]);
            }

            $response = array_merge([
                'success'   => true,
                'message'   => 'Đã thêm vào giỏ hàng',
            ], getCartSummary());
            break;

        case 'remove':
            $cartKey = (string) $variantId;
            unset($_SESSION['cart'][$cartKey]);
            
            // Sync with DB if logged in
            if (isset($_SESSION['user'])) {
                $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND variant_id = ?")
                    ->execute([(int)$_SESSION['user']['id'], $variantId]);
            }

            $response = array_merge([
                'success'   => true,
                'message'   => 'Đã xóa khỏi giỏ hàng',
            ], getCartSummary());
            break;

        case 'update':
            $cartKey = (string) $variantId;
            if (isset($_SESSION['cart'][$cartKey])) {
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$cartKey]);
                    if (isset($_SESSION['user'])) {
                        $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND variant_id = ?")
                            ->execute([(int)$_SESSION['user']['id'], $variantId]);
                    }
                } else {
                    $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
                    if (isset($_SESSION['user'])) {
                        $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND variant_id = ?")
                            ->execute([$quantity, (int)$_SESSION['user']['id'], $variantId]);
                    }
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

    foreach ($cartItems as $key => $item) {
        // Tự động dọn dẹp các item bị lỗi (do session cũ trước khi migrate variant)
        if (empty($item['variant_id'])) {
            unset($_SESSION['cart'][$key]);
            continue;
        }
        $subtotal += (float) $item['price'] * (int) $item['quantity'];
        $totalItems += (int) $item['quantity'];
    }
    
    // Refresh lại cart items sau khi đã dọn dẹp
    $cartItems = $_SESSION['cart'] ?? [];

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

