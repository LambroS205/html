<?php
/**
 * Export Invoice to PDF (Print View)
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = Database::getConnection();

$orderId = (int) ($_GET['id'] ?? 0);

// Nếu user không phải admin, check xem đơn hàng này có thuộc về user đang đăng nhập không (nếu xem từ profile)
$isAdmin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$userId = $_SESSION['user']['id'] ?? null;

if (!$isAdmin && !$userId) {
    die("Bạn không có quyền xem hoá đơn này.");
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Đơn hàng không tồn tại.");
}

if (!$isAdmin && $order['user_id'] != $userId) {
    die("Bạn không có quyền xem hoá đơn này.");
}

$itemStmt = $pdo->prepare("SELECT oi.* FROM order_items oi WHERE oi.order_id = :oid ORDER BY oi.id");
$itemStmt->execute([':oid' => $orderId]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hoá đơn <?= htmlspecialchars($order['order_code']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; }
        .invoice-container { max-width: 800px; margin: 2rem auto; background: white; padding: 3rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        @media print {
            body { background-color: white; }
            .invoice-container { margin: 0; padding: 0; box-shadow: none; border-radius: 0; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="text-gray-800">
    <div class="text-center py-4 no-print flex justify-center gap-3">
        <button onclick="exportPDF()" class="bg-[#0046BE] text-white px-6 py-2.5 rounded-xl font-bold shadow hover:bg-[#001E73] transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Tải xuống PDF
        </button>
        <button onclick="window.print()" class="bg-gray-200 text-gray-800 px-6 py-2.5 rounded-xl font-bold shadow hover:bg-gray-300 transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            In hoá đơn
        </button>
    </div>

    <div id="invoice-content" class="invoice-container">
        <!-- Header -->
        <div class="flex justify-between items-start border-b pb-6 mb-6">
            <div>
                <h1 class="text-3xl font-black text-[#001E73] mb-2 flex items-center gap-2">
                    <span class="bg-[#FFE000] text-[#001E73] px-2 py-0.5 rounded text-xl">Best</span>Buy
                </h1>
                <p class="text-sm text-gray-500">123 Đường Điện Biên Phủ, Quận 1, TP.HCM</p>
                <p class="text-sm text-gray-500">Hotline: 1900 1234</p>
                <p class="text-sm text-gray-500">Email: support@bestbuy.com</p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-gray-300 uppercase tracking-widest">Hoá đơn</h2>
                <p class="text-sm font-bold mt-2">Mã đơn: <span class="text-gray-800"><?= htmlspecialchars($order['order_code']) ?></span></p>
                <p class="text-sm">Ngày lập: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
            </div>
        </div>

        <!-- Info -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Thông tin khách hàng</h3>
                <p class="font-bold"><?= htmlspecialchars($order['customer_name']) ?></p>
                <p class="text-sm"><?= htmlspecialchars($order['customer_phone']) ?></p>
                <p class="text-sm"><?= htmlspecialchars($order['customer_email']) ?></p>
            </div>
            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Giao hàng đến</h3>
                <p class="text-sm font-medium"><?= htmlspecialchars($order['shipping_address']) ?></p>
                <div class="mt-3 bg-gray-50 inline-block px-3 py-1 rounded border">
                    <p class="text-xs font-medium text-gray-600">Phương thức thanh toán</p>
                    <p class="text-sm font-bold"><?= $order['payment_method'] === 'cod' ? 'Tiền mặt (COD)' : 'Thẻ quốc tế (Đã thanh toán)' ?></p>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="w-full text-left border-collapse mb-8">
            <thead>
                <tr class="border-b-2 border-gray-800">
                    <th class="py-2 text-xs uppercase tracking-wider font-bold text-gray-500">Sản phẩm</th>
                    <th class="py-2 text-xs uppercase tracking-wider font-bold text-center text-gray-500 w-16">SL</th>
                    <th class="py-2 text-xs uppercase tracking-wider font-bold text-right text-gray-500 w-32">Đơn giá</th>
                    <th class="py-2 text-xs uppercase tracking-wider font-bold text-right text-gray-500 w-32">Thành tiền</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="py-3 pr-4">
                        <p class="font-medium text-sm text-gray-900"><?= htmlspecialchars($item['product_name']) ?></p>
                    </td>
                    <td class="py-3 text-center text-sm"><?= $item['quantity'] ?></td>
                    <td class="py-3 text-right text-sm"><?= formatPrice((float)$item['price']) ?></td>
                    <td class="py-3 text-right font-bold text-sm"><?= formatPrice((float)$item['price'] * $item['quantity']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="flex justify-end">
            <div class="w-72 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Tạm tính:</span>
                    <span class="font-medium"><?= formatPrice((float)$order['subtotal']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Phí vận chuyển:</span>
                    <span class="font-medium"><?= $order['shipping_fee'] == 0 ? 'Miễn phí' : formatPrice((float)$order['shipping_fee']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">VAT (Đã bao gồm):</span>
                    <span class="font-medium"><?= formatPrice((float)$order['tax']) ?></span>
                </div>
                <div class="flex justify-between border-t-2 border-gray-800 pt-3 mt-3">
                    <span class="font-bold uppercase tracking-wider">Tổng cộng:</span>
                    <span class="font-black text-xl text-[#0046BE]"><?= formatPrice((float)$order['total']) ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-16 text-center text-sm text-gray-500 border-t pt-8">
            <p class="font-medium text-gray-800 mb-1">Cảm ơn quý khách đã mua sắm tại BestBuy Store!</p>
            <p>Nếu có thắc mắc về hoá đơn, vui lòng liên hệ hotline <span class="font-bold">1900 1234</span> hoặc email <span class="font-bold">support@bestbuy.com</span>.</p>
        </div>
    </div>

    <script>
        function exportPDF() {
            var element = document.getElementById('invoice-content');
            var opt = {
                margin:       10,
                filename:     'Hoa_Don_<?= htmlspecialchars($order['order_code']) ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
