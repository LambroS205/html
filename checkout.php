<?php
/**
 * Trang Thanh toán - BestBuy Store
 * 
 * Flow:
 *  1. GET  → Hiển thị form nhập thông tin + tóm tắt đơn hàng
 *  2. POST → Server-side validation → DB Transaction → Trang cảm ơn
 * 
 * Logic:
 *  - Validate cả client-side (JS) và server-side (PHP)
 *  - Sử dụng PDO Transaction: INSERT orders → INSERT order_items → COMMIT
 *  - Clear cart session sau khi đặt hàng thành công
 *  - Generate mã đơn hàng BB-XXXXXX
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = Database::getConnection();

// ── Kiểm tra giỏ hàng ──
$cartItems = $_SESSION['cart'] ?? [];
if (empty($cartItems) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// ── Biến lưu trạng thái ──
$errors       = [];
$formData     = [
    'customer_name'  => '',
    'customer_email' => '',
    'customer_phone' => '',
    'shipping_address' => '',
    'payment_method' => ''
];
$orderSuccess = false;
$orderCode    = '';
$orderTotal   = 0;

// GET request: auto-fill từ session user nếu đã đăng nhập
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_SESSION['user'])) {
    $userInfo = $pdo->prepare("SELECT name, email, phone, address FROM users WHERE id = :id LIMIT 1");
    $userInfo->execute([':id' => (int)$_SESSION['user']['id']]);
    $userData = $userInfo->fetch();
    if ($userData) {
        $formData['customer_name'] = $userData['name'] ?? '';
        $formData['customer_email'] = $userData['email'] ?? '';
        $formData['customer_phone'] = $userData['phone'] ?? '';
        $formData['shipping_address'] = $userData['address'] ?? '';
    }
}

// ═══════════════════════════════════════
// XỬ LÝ POST — Đặt hàng
// ═══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    // ── Thu thập dữ liệu form ──
    $formData = [
        'customer_name'  => trim($_POST['customer_name'] ?? ''),
        'customer_email' => trim($_POST['customer_email'] ?? ''),
        'customer_phone' => trim($_POST['customer_phone'] ?? ''),
        'shipping_address' => trim($_POST['shipping_address'] ?? ''),
        'payment_method' => trim($_POST['payment_method'] ?? ''),
    ];

    // ══════════════════════════════════
    // SERVER-SIDE VALIDATION
    // ══════════════════════════════════

    // Họ tên
    if (empty($formData['customer_name'])) {
        $errors['customer_name'] = 'Vui lòng nhập họ tên';
    } elseif (mb_strlen($formData['customer_name']) < 2) {
        $errors['customer_name'] = 'Họ tên phải có ít nhất 2 ký tự';
    } elseif (mb_strlen($formData['customer_name']) > 100) {
        $errors['customer_name'] = 'Họ tên không được quá 100 ký tự';
    }

    // Email
    if (empty($formData['customer_email'])) {
        $errors['customer_email'] = 'Vui lòng nhập email';
    } elseif (!filter_var($formData['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['customer_email'] = 'Email không hợp lệ';
    }

    // Số điện thoại
    if (empty($formData['customer_phone'])) {
        $errors['customer_phone'] = 'Vui lòng nhập số điện thoại';
    } elseif (!preg_match('/^[0-9+\-\s()]{8,20}$/', $formData['customer_phone'])) {
        $errors['customer_phone'] = 'Số điện thoại không hợp lệ (8-20 ký tự số)';
    }

    // Địa chỉ
    if (empty($formData['shipping_address'])) {
        $errors['shipping_address'] = 'Vui lòng nhập địa chỉ giao hàng';
    } elseif (mb_strlen($formData['shipping_address']) < 10) {
        $errors['shipping_address'] = 'Địa chỉ phải có ít nhất 10 ký tự';
    }

    // Phương thức thanh toán
    if (!in_array($formData['payment_method'], ['cod', 'card', 'vnpay'])) {
        $errors['payment_method'] = 'Vui lòng chọn phương thức thanh toán hợp lệ';
    }

    // Kiểm tra giỏ hàng còn items không
    if (empty($cartItems)) {
        $errors['cart'] = 'Giỏ hàng trống. Vui lòng thêm sản phẩm trước khi thanh toán.';
    }

    // ══════════════════════════════════
    // NẾU KHÔNG CÓ LỖI → XỬ LÝ ĐƠN HÀNG
    // ══════════════════════════════════
    if (empty($errors)) {
        try {
            // ── Tính toán tổng tiền ──
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += (float) $item['price'] * (int) $item['quantity'];
            }

            $freeShippingThreshold = 875000;
            $shippingFee = ($subtotal >= $freeShippingThreshold || $subtotal == 0) ? 0 : 125000;
            
            // ── Tính giảm giá ──
            $discount = 0;
            $couponId = null;
            if (isset($_SESSION['coupon'])) {
                $coupon = $_SESSION['coupon'];
                $couponId = $coupon['id'];
                if ($coupon['type'] === 'percent') {
                    $discount = $subtotal * ($coupon['value'] / 100);
                } else {
                    $discount = $coupon['value'];
                }
                if ($discount > $subtotal) {
                    $discount = $subtotal;
                }
            }

            $amountBeforeVat = max(0, $subtotal - $discount);
            $vat = round($amountBeforeVat * 0.10, 2);    // VAT 10%
            $total = $amountBeforeVat + $shippingFee + $vat;

            // ── Generate mã đơn hàng ──
            $orderCode = 'BB-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            // ══════════════════════════════════
            // DATABASE TRANSACTION
            // ══════════════════════════════════
            $pdo->beginTransaction();

            // 1. Insert vào bảng orders (gắn user_id, coupon_id)
            $currentUserId = $_SESSION['user']['id'] ?? null;
            $orderStmt = $pdo->prepare("
                INSERT INTO orders (user_id, coupon_id, order_code, customer_name, customer_email, customer_phone, 
                                    shipping_address, payment_method, subtotal, discount_amount, shipping_fee, tax, total, status)
                VALUES (:user_id, :coupon_id, :order_code, :name, :email, :phone, :address, :payment, :subtotal, :discount_amount, :shipping, :tax, :total, 'pending')
            ");
            $orderStmt->execute([
                ':user_id'         => $currentUserId,
                ':coupon_id'       => $couponId,
                ':order_code'      => $orderCode,
                ':name'            => $formData['customer_name'],
                ':email'           => $formData['customer_email'],
                ':phone'           => $formData['customer_phone'],
                ':address'         => $formData['shipping_address'],
                ':payment'         => $formData['payment_method'],
                ':subtotal'        => $subtotal,
                ':discount_amount' => $discount,
                ':shipping'        => $shippingFee,
                ':tax'             => $vat,
                ':total'           => $total,
            ]);

            $orderId = (int) $pdo->lastInsertId();

            // 2. Insert từng item vào bảng order_items
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, variant_id, product_name, price, quantity)
                VALUES (:order_id, :product_id, :variant_id, :product_name, :price, :quantity)
            ");

            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    ':order_id'     => $orderId,
                    ':product_id'   => (int) $item['product_id'],
                    ':variant_id'   => (int) $item['variant_id'],
                    ':product_name' => $item['name'],
                    ':price'        => (float) $item['price'],
                    ':quantity'     => (int) $item['quantity'],
                ]);
            }

            // 3. COMMIT transaction
            $pdo->commit();

            // 4. Clear cart & coupon session
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon']);

            // 5. Nếu là VNPay -> Chuyển hướng sang VNPay
            if ($formData['payment_method'] === 'vnpay') {
                require_once __DIR__ . '/config/vnpay.php';
                
                $vnp_TxnRef = $orderCode;
                $vnp_OrderInfo = "Thanh toan don hang " . $orderCode;
                $vnp_OrderType = 'billpayment';
                $vnp_Amount = $total * 100;
                $vnp_Locale = 'vn';
                $vnp_IpAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                
                $inputData = array(
                    "vnp_Version" => "2.1.0",
                    "vnp_TmnCode" => VNP_TMN_CODE,
                    "vnp_Amount" => $vnp_Amount,
                    "vnp_Command" => "pay",
                    "vnp_CreateDate" => date('YmdHis'),
                    "vnp_CurrCode" => "VND",
                    "vnp_IpAddr" => $vnp_IpAddr,
                    "vnp_Locale" => $vnp_Locale,
                    "vnp_OrderInfo" => $vnp_OrderInfo,
                    "vnp_OrderType" => $vnp_OrderType,
                    "vnp_ReturnUrl" => VNP_RETURN_URL,
                    "vnp_TxnRef" => $vnp_TxnRef
                );
                
                ksort($inputData);
                $query = "";
                $i = 0;
                $hashdata = "";
                foreach ($inputData as $key => $value) {
                    if ($i == 1) {
                        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                    } else {
                        $hashdata .= urlencode($key) . "=" . urlencode($value);
                        $i = 1;
                    }
                    $query .= urlencode($key) . "=" . urlencode($value) . '&';
                }
                
                $vnp_Url = VNP_URL . "?" . $query;
                if (defined('VNP_HASH_SECRET') && VNP_HASH_SECRET != '') {
                    $vnpSecureHash = hash_hmac('sha512', $hashdata, VNP_HASH_SECRET);
                    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
                }
                
                header('Location: ' . $vnp_Url);
                exit;
            }

            $orderSuccess = true;
            $orderTotal   = $total;

        } catch (Exception $e) {
            // Rollback nếu có lỗi
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['system'] = 'Lỗi hệ thống khi xử lý đơn hàng. Vui lòng thử lại. (' . $e->getMessage() . ')';
        }
    }
}

// ── Tính tổng cho hiển thị form ──
$subtotal = 0;
$totalItems = 0;
foreach ($cartItems as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
    $totalItems += (int) $item['quantity'];
}
$freeShippingThreshold = 875000;
$shippingFee = ($subtotal >= $freeShippingThreshold || $subtotal == 0) ? 0 : 125000;

$discount = 0;
$couponInfo = null;
if (isset($_SESSION['coupon'])) {
    $coupon = $_SESSION['coupon'];
    if ($coupon['type'] === 'percent') {
        $discount = $subtotal * ($coupon['value'] / 100);
    } else {
        $discount = $coupon['value'];
    }
    if ($discount > $subtotal) {
        $discount = $subtotal;
    }
    $couponInfo = $coupon;
}

$amountBeforeVat = max(0, $subtotal - $discount);
$vat = round($amountBeforeVat * 0.10, 2);
$total = $amountBeforeVat + $shippingFee + $vat;

$pageTitle = $orderSuccess ? 'Đặt hàng thành công — BestBuy' : 'Thanh toán — BestBuy Store';
$pageDescription = 'Hoàn tất đơn hàng tại BestBuy Store.';

require_once __DIR__ . '/includes/header.php';
?>

    <div class="max-w-7xl mx-auto px-4 py-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
            <a href="/" class="hover:text-bb-blue transition-colors">Trang chủ</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <button type="button" onclick="openCartDrawer()" class="hover:text-bb-blue transition-colors cursor-pointer text-sm bg-transparent border-none p-0 outline-none">Giỏ hàng</button>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-600 font-medium"><?= $orderSuccess ? 'Đặt hàng thành công' : 'Thanh toán' ?></span>
        </nav>

        <?php if ($orderSuccess): ?>
        <!-- ═══════════════════════════════════════
             THANK YOU PAGE — Đặt hàng thành công
             ═══════════════════════════════════════ -->
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-8 text-center text-white">
                    <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Đặt hàng thành công!</h1>
                    <p class="text-green-100">Cảm ơn bạn đã mua sắm tại BestBuy Store</p>
                </div>

                <!-- Order details -->
                <div class="p-8">
                    <!-- Order code -->
                    <div class="bg-gray-50 rounded-2xl p-5 text-center mb-6">
                        <p class="text-sm text-gray-400 mb-1">Mã đơn hàng của bạn</p>
                        <p class="text-3xl font-black text-bb-blue tracking-wider"><?= htmlspecialchars($orderCode) ?></p>
                        <p class="text-xs text-gray-400 mt-2">Vui lòng lưu lại mã này để theo dõi đơn hàng</p>
                    </div>

                    <!-- Info summary -->
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Khách hàng</span>
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($formData['customer_name']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Email</span>
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($formData['customer_email']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Điện thoại</span>
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($formData['customer_phone']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Địa chỉ</span>
                            <span class="font-medium text-gray-700 text-right max-w-[60%]"><?= htmlspecialchars($formData['shipping_address']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Thanh toán</span>
                            <span class="font-medium text-gray-700">
                                <?php if ($formData['payment_method'] === 'cod') { echo '💵 Thanh toán khi nhận hàng (COD)'; } elseif ($formData['payment_method'] === 'vnpay') { echo '🏦 Thanh toán qua VNPAY'; } else { echo '💳 Thẻ quốc tế'; } ?>
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Trạng thái</span>
                            <span class="inline-flex items-center gap-1.5 text-orange-600 font-semibold">
                                <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                                Đang xử lý
                            </span>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="border-t border-gray-100 pt-4">
                        <div class="flex justify-between items-baseline">
                            <span class="font-bold text-gray-800">Tổng thanh toán</span>
                            <span class="text-2xl font-black text-bb-blue"><?= formatPrice($orderTotal) ?></span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-3 mt-8">
                        <a href="/" class="flex-1 text-center bg-bb-yellow text-bb-dark font-bold py-3.5 rounded-xl hover:bg-yellow-300 transition-all">
                            ← Tiếp tục mua sắm
                        </a>
                        <?php if (!empty($_SESSION['user'])): ?>
                        <a href="/profile.php" class="flex-1 text-center border-2 border-gray-200 text-gray-600 font-semibold py-3.5 rounded-xl hover:bg-gray-50 transition-all">
                            📋 Xem đơn hàng của tôi
                        </a>
                        <?php else: ?>
                        <a href="/auth/register.php" class="flex-1 text-center border-2 border-gray-200 text-gray-600 font-semibold py-3.5 rounded-xl hover:bg-gray-50 transition-all">
                            👤 Đăng ký để theo dõi đơn hàng
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ═══════════════════════════════════════
             CHECKOUT FORM
             ═══════════════════════════════════════ -->

        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 flex items-center gap-3">
            <svg class="w-8 h-8 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            Thanh toán
        </h1>

        <!-- Checkout steps indicator -->
        <div class="flex items-center gap-2 mb-8 text-sm">
            <span class="flex items-center gap-1.5 text-green-600 font-medium">
                <span class="w-6 h-6 bg-green-600 text-white rounded-full flex items-center justify-center text-xs font-bold">✓</span>
                Giỏ hàng
            </span>
            <div class="w-8 h-px bg-gray-300"></div>
            <span class="flex items-center gap-1.5 text-bb-blue font-semibold">
                <span class="w-6 h-6 bg-bb-blue text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                Thanh toán
            </span>
            <div class="w-8 h-px bg-gray-300"></div>
            <span class="flex items-center gap-1.5 text-gray-400">
                <span class="w-6 h-6 bg-gray-200 text-gray-400 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                Hoàn tất
            </span>
        </div>

        <!-- System error -->
        <?php if (isset($errors['system'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6 flex items-start gap-3">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                <span class="text-sm"><?= htmlspecialchars($errors['system']) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/checkout.php" id="checkout-form" novalidate>
            <?= csrfField() ?>
            <div class="flex flex-col lg:flex-row gap-8">

                <!-- ── LEFT: Customer Info Form ── -->
                <div class="flex-1 space-y-6">

                    <!-- Thông tin khách hàng -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                            <svg class="w-5 h-5 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Thông tin khách hàng
                        </h2>

                        <div class="space-y-4">
                            <!-- Họ tên -->
                            <div>
                                <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Họ và tên <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="customer_name" name="customer_name" required
                                       value="<?= htmlspecialchars($formData['customer_name'] ?? '') ?>"
                                       placeholder="Nguyễn Văn A"
                                       class="w-full px-4 py-3 rounded-xl border-2 <?= isset($errors['customer_name']) ? 'border-red-400 bg-red-50' : 'border-gray-200' ?> focus:border-bb-blue focus:ring-2 focus:ring-bb-blue/10 outline-none transition-all text-sm">
                                <?php if (isset($errors['customer_name'])): ?>
                                    <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                        <?= htmlspecialchars($errors['customer_name']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Email & Phone -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1.5">
                                        Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" id="customer_email" name="customer_email" required
                                           value="<?= htmlspecialchars($formData['customer_email'] ?? '') ?>"
                                           placeholder="email@example.com"
                                           class="w-full px-4 py-3 rounded-xl border-2 <?= isset($errors['customer_email']) ? 'border-red-400 bg-red-50' : 'border-gray-200' ?> focus:border-bb-blue focus:ring-2 focus:ring-bb-blue/10 outline-none transition-all text-sm">
                                    <?php if (isset($errors['customer_email'])): ?>
                                        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                            <?= htmlspecialchars($errors['customer_email']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1.5">
                                        Số điện thoại <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" id="customer_phone" name="customer_phone" required
                                           value="<?= htmlspecialchars($formData['customer_phone'] ?? '') ?>"
                                           placeholder="0901 234 567"
                                           class="w-full px-4 py-3 rounded-xl border-2 <?= isset($errors['customer_phone']) ? 'border-red-400 bg-red-50' : 'border-gray-200' ?> focus:border-bb-blue focus:ring-2 focus:ring-bb-blue/10 outline-none transition-all text-sm">
                                    <?php if (isset($errors['customer_phone'])): ?>
                                        <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                            <?= htmlspecialchars($errors['customer_phone']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Địa chỉ giao hàng -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                            <svg class="w-5 h-5 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Địa chỉ giao hàng
                        </h2>
                        <div>
                            <label for="shipping_address" class="block text-sm font-medium text-gray-700 mb-1.5">
                                Địa chỉ đầy đủ <span class="text-red-500">*</span>
                            </label>
                            <textarea id="shipping_address" name="shipping_address" rows="3" required
                                      placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố"
                                      class="w-full px-4 py-3 rounded-xl border-2 <?= isset($errors['shipping_address']) ? 'border-red-400 bg-red-50' : 'border-gray-200' ?> focus:border-bb-blue focus:ring-2 focus:ring-bb-blue/10 outline-none transition-all text-sm resize-none"><?= htmlspecialchars($formData['shipping_address'] ?? '') ?></textarea>
                            <?php if (isset($errors['shipping_address'])): ?>
                                <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                    <?= htmlspecialchars($errors['shipping_address']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Phương thức thanh toán -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                            <svg class="w-5 h-5 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            Phương thức thanh toán
                        </h2>

                        <?php if (isset($errors['payment_method'])): ?>
                            <p class="text-red-500 text-xs mb-3 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                <?= htmlspecialchars($errors['payment_method']) ?>
                            </p>
                        <?php endif; ?>

                        <div class="space-y-3">
                            <!-- COD -->
                            <label class="payment-option flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer transition-all hover:border-bb-blue/30 <?= ($formData['payment_method'] ?? 'cod') === 'cod' ? 'border-bb-blue bg-blue-50/50' : 'border-gray-200' ?>">
                                <input type="radio" name="payment_method" value="cod" 
                                       <?= ($formData['payment_method'] ?? 'cod') === 'cod' ? 'checked' : '' ?>
                                       class="w-5 h-5 text-bb-blue focus:ring-bb-blue"
                                       onchange="updatePaymentUI()">
                                <div class="flex-1">
                                    <span class="text-2xl">💵</span>
                                    <p class="font-semibold text-gray-800 text-sm">Thanh toán khi nhận hàng (COD)</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Trả tiền mặt cho nhân viên giao hàng</p>
                                </div>
                            </label>

                            <!-- Card -->
                            <label class="payment-option flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer transition-all hover:border-bb-blue/30 <?= ($formData['payment_method'] ?? '') === 'card' ? 'border-bb-blue bg-blue-50/50' : 'border-gray-200' ?>">
                                <input type="radio" name="payment_method" value="card"
                                       <?= ($formData['payment_method'] ?? '') === 'card' ? 'checked' : '' ?>
                                       class="w-5 h-5 text-bb-blue focus:ring-bb-blue"
                                       onchange="updatePaymentUI()">
                                <div class="flex-1">
                                    <span class="text-2xl">💳</span>
                                    <p class="font-semibold text-gray-800 text-sm">Thẻ quốc tế (Visa / Mastercard)</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Thanh toán an toàn qua cổng bảo mật</p>
                                </div>
                            </label>

                            <!-- VNPay -->
                            <label class="payment-option flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer transition-all hover:border-bb-blue/30 <?= ($formData['payment_method'] ?? '') === 'vnpay' ? 'border-bb-blue bg-blue-50/50' : 'border-gray-200' ?>">
                                <input type="radio" name="payment_method" value="vnpay"
                                       <?= ($formData['payment_method'] ?? '') === 'vnpay' ? 'checked' : '' ?>
                                       class="w-5 h-5 text-bb-blue focus:ring-bb-blue"
                                       onchange="updatePaymentUI()">
                                <div class="flex-1">
                                    <span class="text-2xl">🏦</span>
                                    <p class="font-semibold text-gray-800 text-sm">Thanh toán qua VNPAY</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Quét mã QR qua ứng dụng ngân hàng hoặc thẻ ATM</p>
                                </div>
                            </label>
                        </div>

                        <!-- Card form (hiện khi chọn card) -->
                        <div id="card-form" class="mt-4 p-4 bg-gray-50 rounded-xl space-y-3 <?= ($formData['payment_method'] ?? '') !== 'card' ? 'hidden' : '' ?>">
                            <p class="text-xs text-gray-500 mb-2 flex items-center gap-1">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                Giao dịch được mã hóa SSL an toàn (Demo — không xử lý thẻ thật)
                            </p>
                            <input type="text" placeholder="Số thẻ: 4242 4242 4242 4242" maxlength="19"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:border-bb-blue outline-none">
                            <div class="grid grid-cols-2 gap-3">
                                <input type="text" placeholder="MM/YY" maxlength="5"
                                       class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:border-bb-blue outline-none">
                                <input type="text" placeholder="CVV" maxlength="4"
                                       class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm focus:border-bb-blue outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── RIGHT: Order Summary ── -->
                <div class="w-full lg:w-96 shrink-0">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24">
                        <h3 class="font-bold text-gray-900 text-lg mb-5">Tóm tắt đơn hàng</h3>

                        <!-- Cart items mini list -->
                        <div class="space-y-3 mb-5 max-h-60 overflow-y-auto">
                            <?php foreach ($cartItems as $item):
                                $itemImage = getProductImage($item['image'] ?? '');
                            ?>
                            <div class="flex items-center gap-3">
                                <div class="w-14 h-14 bg-gray-50 rounded-lg flex items-center justify-center shrink-0 border border-gray-100 overflow-hidden">
                                    <?php if ($itemImage): ?>
                                        <img src="<?= htmlspecialchars($itemImage) ?>" alt="" class="w-full h-full object-contain p-1" loading="lazy">
                                    <?php else: ?>
                                        <span class="text-xl opacity-50">📦</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 line-clamp-1"><?= htmlspecialchars($item['name']) ?></p>
                                    <p class="text-xs text-gray-400">SL: <?= (int) $item['quantity'] ?></p>
                                </div>
                                <span class="text-sm font-semibold text-gray-700 whitespace-nowrap">
                                    <?= formatPrice((float) $item['price'] * (int) $item['quantity']) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <hr class="border-gray-100 mb-4">

                        <!-- Coupon Form -->
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mã giảm giá</label>
                            <?php if (isset($_SESSION['coupon'])): ?>
                                <div class="flex items-center justify-between bg-green-50 border border-green-200 p-3 rounded-xl">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span class="font-bold text-green-700"><?= htmlspecialchars($_SESSION['coupon']['code']) ?></span>
                                    </div>
                                    <button type="button" onclick="removeCoupon()" class="text-sm text-red-500 hover:text-red-600 font-medium hover:underline">Xóa</button>
                                </div>
                            <?php else: ?>
                                <div class="flex gap-2 relative">
                                    <input type="text" id="coupon_code" placeholder="Nhập mã giảm giá..." class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-bb-blue outline-none uppercase">
                                    <button type="button" onclick="applyCoupon()" class="bg-gray-900 text-white px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-bb-blue transition-colors whitespace-nowrap">Áp dụng</button>
                                </div>
                                <p id="coupon-msg" class="text-xs mt-2 hidden"></p>
                            <?php endif; ?>
                        </div>

                        <hr class="border-gray-100 mb-4">

                        <!-- Totals -->
                        <div class="space-y-2.5 text-sm">
                            <div class="flex justify-between text-gray-600">
                                <span>Tạm tính (<?= $totalItems ?> SP)</span>
                                <span class="font-medium"><?= formatPrice($subtotal) ?></span>
                            </div>
                            <?php if ($discount > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <span>Giảm giá (<?= htmlspecialchars($couponInfo['code']) ?>)</span>
                                <span class="font-medium">-<?= formatPrice($discount) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between text-gray-600">
                                <span>Phí vận chuyển</span>
                                <span class="font-medium <?= $shippingFee == 0 ? 'text-green-600' : '' ?>">
                                    <?= $shippingFee == 0 ? 'Miễn phí' : formatPrice($shippingFee) ?>
                                </span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>VAT (10%)</span>
                                <span class="font-medium"><?= formatPrice($vat) ?></span>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 mt-4 pt-4 mb-5">
                            <div class="flex justify-between items-baseline">
                                <span class="text-base font-bold text-gray-900">Tổng cộng</span>
                                <span class="text-2xl font-black text-bb-blue"><?= formatPrice($total) ?></span>
                            </div>
                        </div>

                        <!-- Submit button -->
                        <button type="submit" id="place-order-btn" 
                                class="w-full bg-bb-yellow text-bb-dark font-bold py-4 rounded-xl hover:bg-yellow-300 active:scale-[0.98] transition-all shadow-lg shadow-yellow-500/20 text-base flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Đặt hàng
                        </button>

                        <!-- Trust badges -->
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-3 text-xs text-gray-400">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                SSL Bảo mật
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                Hoàn tiền 100%
                            </span>
                        </div>

                        <!-- Edit cart link -->
                        <button type="button" onclick="openCartDrawer()" class="block w-full mt-3 text-center text-sm text-bb-blue hover:text-bb-dark font-medium transition-colors bg-transparent border-none p-0 cursor-pointer outline-none">
                            ← Quay lại giỏ hàng
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Checkout JavaScript — Client-side Validation -->
    <script>
    /**
     * Payment option UI toggle
     */
    function updatePaymentUI() {
        const options = document.querySelectorAll('.payment-option');
        options.forEach(opt => {
            const radio = opt.querySelector('input[type="radio"]');
            if (radio.checked) {
                opt.classList.add('border-bb-blue', 'bg-blue-50/50');
                opt.classList.remove('border-gray-200');
            } else {
                opt.classList.remove('border-bb-blue', 'bg-blue-50/50');
                opt.classList.add('border-gray-200');
            }
        });

        // Toggle card form
        const cardForm = document.getElementById('card-form');
        const cardRadio = document.querySelector('input[value="card"]');
        if (cardForm && cardRadio) {
            cardForm.classList.toggle('hidden', !cardRadio.checked);
        }
    }

    /**
     * Client-side form validation
     */
    document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
        const name    = document.getElementById('customer_name');
        const email   = document.getElementById('customer_email');
        const phone   = document.getElementById('customer_phone');
        const address = document.getElementById('shipping_address');
        const payment = document.querySelector('input[name="payment_method"]:checked');

        let hasError = false;

        // Clear previous client errors
        document.querySelectorAll('.client-error').forEach(el => el.remove());

        function showFieldError(field, message) {
            field.classList.add('border-red-400', 'bg-red-50');
            field.classList.remove('border-gray-200');
            const p = document.createElement('p');
            p.className = 'client-error text-red-500 text-xs mt-1.5';
            p.textContent = message;
            field.parentNode.appendChild(p);
            hasError = true;
        }

        function clearFieldError(field) {
            field.classList.remove('border-red-400', 'bg-red-50');
            field.classList.add('border-gray-200');
        }

        // Validate name
        clearFieldError(name);
        if (!name.value.trim()) {
            showFieldError(name, 'Vui lòng nhập họ tên');
        } else if (name.value.trim().length < 2) {
            showFieldError(name, 'Họ tên phải có ít nhất 2 ký tự');
        }

        // Validate email
        clearFieldError(email);
        if (!email.value.trim()) {
            showFieldError(email, 'Vui lòng nhập email');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            showFieldError(email, 'Email không hợp lệ');
        }

        // Validate phone
        clearFieldError(phone);
        if (!phone.value.trim()) {
            showFieldError(phone, 'Vui lòng nhập số điện thoại');
        } else if (!/^[0-9+\-\s()]{8,20}$/.test(phone.value.trim())) {
            showFieldError(phone, 'Số điện thoại không hợp lệ');
        }

        // Validate address
        clearFieldError(address);
        if (!address.value.trim()) {
            showFieldError(address, 'Vui lòng nhập địa chỉ giao hàng');
        } else if (address.value.trim().length < 10) {
            showFieldError(address, 'Địa chỉ phải có ít nhất 10 ký tự');
        }

        // Validate payment
        if (!payment) {
            hasError = true;
            if (typeof showToast === 'function') {
                showToast('Vui lòng chọn phương thức thanh toán', 'error');
            }
        }

        if (hasError) {
            e.preventDefault();
            // Scroll to first error
            const firstError = document.querySelector('.border-red-400');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            return false;
        }

        // Show loading state on button
        const btn = document.getElementById('place-order-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Đang xử lý đơn hàng...`;
        }
    });

    // Real-time field validation (clear error on input)
    document.querySelectorAll('#checkout-form input, #checkout-form textarea').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('border-red-400', 'bg-red-50');
            this.classList.add('border-gray-200');
            const error = this.parentNode.querySelector('.client-error');
            if (error) error.remove();
        });
    });

    /**
     * Coupon actions
     */
    async function applyCoupon() {
        const codeInput = document.getElementById('coupon_code');
        const msgEl = document.getElementById('coupon-msg');
        const code = codeInput.value.trim();

        if (!code) {
            msgEl.textContent = 'Vui lòng nhập mã giảm giá';
            msgEl.className = 'text-xs mt-2 text-red-500 block';
            return;
        }

        try {
            const res = await fetch('/api/apply-coupon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code })
            });
            const data = await res.json();
            
            if (data.success) {
                location.reload(); // Tải lại trang để áp dụng giảm giá
            } else {
                msgEl.textContent = data.message;
                msgEl.className = 'text-xs mt-2 text-red-500 block';
            }
        } catch (error) {
            console.error('Lỗi áp dụng mã:', error);
            msgEl.textContent = 'Lỗi kết nối. Vui lòng thử lại sau.';
            msgEl.className = 'text-xs mt-2 text-red-500 block';
        }
    }

    async function removeCoupon() {
        try {
            const res = await fetch('/api/remove-coupon.php', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                location.reload();
            }
        } catch (error) {
            console.error('Lỗi gỡ mã:', error);
        }
    }
    </script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>

