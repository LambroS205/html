<?php
/**
 * VNPay Return URL - Xử lý kết quả thanh toán từ VNPay
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/vnpay.php';
require_once __DIR__ . '/../../../includes/helpers.php';

$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, VNP_HASH_SECRET);

$isValidSignature = ($secureHash === $vnp_SecureHash);
$isSuccess = ($_GET['vnp_ResponseCode'] ?? '') === '00';
$orderCode = $_GET['vnp_TxnRef'] ?? '';
$transactionId = $_GET['vnp_TransactionNo'] ?? '';

$message = '';
$statusClass = '';

if ($isValidSignature) {
    $pdo = Database::getConnection();
    
    // Tìm đơn hàng theo order_code
    $stmt = $pdo->prepare("SELECT id, payment_status FROM orders WHERE order_code = ?");
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();
    
    if ($order) {
        if ($isSuccess) {
            if ($order['payment_status'] !== 'paid') {
                // Cập nhật trạng thái thanh toán thành công
                $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing', transaction_id = ? WHERE id = ?")
                    ->execute([$transactionId, $order['id']]);
            }
            $message = "Thanh toán thành công! Cảm ơn bạn đã mua sắm.";
            $statusClass = "text-green-500 bg-green-50 border-green-200";
        } else {
            // Cập nhật trạng thái thanh toán thất bại
            if ($order['payment_status'] !== 'paid') {
                $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$order['id']]);
            }
            $message = "Giao dịch không thành công hoặc đã bị hủy.";
            $statusClass = "text-red-500 bg-red-50 border-red-200";
        }
    } else {
        $message = "Không tìm thấy đơn hàng tương ứng.";
        $statusClass = "text-orange-500 bg-orange-50 border-orange-200";
    }
} else {
    $message = "Chữ ký không hợp lệ! Dữ liệu có thể đã bị giả mạo.";
    $statusClass = "text-red-500 bg-red-50 border-red-200";
    $isSuccess = false;
}

$pageTitle = 'Kết quả thanh toán — BestBuy Store';
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="bg-gray-50/50 py-20 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-gray-100 p-8 text-center relative overflow-hidden">
        
        <?php if ($isValidSignature && $isSuccess): ?>
            <!-- Lưới nền trang trí (Thành công) -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#80808012_1px,transparent_1px),linear-gradient(to_bottom,#80808012_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>
            
            <div class="w-24 h-24 bg-green-100 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10 shadow-[0_0_40px_rgba(34,197,94,0.3)] animate-[pulse_2s_infinite]">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
        <?php else: ?>
            <div class="w-24 h-24 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10 shadow-[0_0_40px_rgba(239,68,68,0.3)]">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </div>
        <?php endif; ?>

        <div class="relative z-10">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Kết quả giao dịch</h1>
            
            <div class="p-4 rounded-xl border <?= $statusClass ?> mb-6 text-sm font-medium">
                <?= htmlspecialchars($message) ?>
            </div>

            <?php if ($isValidSignature && $orderCode): ?>
                <div class="text-left bg-gray-50 rounded-xl p-4 mb-6 space-y-2 border border-gray-100 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Mã đơn hàng:</span>
                        <span class="font-bold text-gray-900"><?= htmlspecialchars($orderCode) ?></span>
                    </div>
                    <?php if ($transactionId): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Mã giao dịch VNPAY:</span>
                            <span class="font-bold text-gray-900"><?= htmlspecialchars($transactionId) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['vnp_Amount'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Số tiền:</span>
                            <span class="font-bold text-bb-blue"><?= formatPrice($_GET['vnp_Amount'] / 100) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col gap-3">
                <a href="/profile.php" class="w-full bg-bb-blue text-white font-bold py-3.5 rounded-xl hover:bg-blue-700 active:scale-[0.98] transition-all shadow-lg shadow-blue-500/25">
                    Quản lý đơn hàng
                </a>
                <a href="/" class="w-full bg-white text-gray-600 font-bold py-3.5 rounded-xl border-2 border-gray-200 hover:bg-gray-50 hover:border-gray-300 active:scale-[0.98] transition-all">
                    Về trang chủ
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
