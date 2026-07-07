<?php
/**
 * Trang Hồ sơ & Lịch sử Đơn hàng — BestBuy Store
 * 
 * Hiển thị:
 * - Thông tin tài khoản (tên, email, ngày đăng ký)
 * - Danh sách đơn hàng đã mua (mã đơn, ngày, tổng tiền, trạng thái)
 * - Chi tiết đơn hàng (expandable)
 * 
 * Yêu cầu: Đăng nhập
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

// Chưa đăng nhập → redirect sang login
if (empty($_SESSION['user'])) {
    header('Location: /auth/login.php?redirect=' . urlencode('/profile.php'));
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = Database::getConnection();
$userId = (int) $_SESSION['user']['id'];

// ── Lấy thông tin user ──
$userStmt = $pdo->prepare("SELECT id, name, email, phone, address, role, created_at FROM users WHERE id = :id LIMIT 1");
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    // User không tồn tại trong DB (có thể bị xóa)
    unset($_SESSION['user']);
    header('Location: /auth/login.php');
    exit;
}

// ── Lấy đơn hàng của user ──
$ordersStmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM orders o
    WHERE o.user_id = :user_id
    ORDER BY o.created_at DESC
");
$ordersStmt->execute([':user_id' => $userId]);
$orders = $ordersStmt->fetchAll();

// ── Lấy chi tiết items cho từng đơn hàng ──
$orderItems = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, p.slug, p.image 
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ({$placeholders})
        ORDER BY oi.id ASC
    ");
    $itemsStmt->execute($orderIds);
    $allItems = $itemsStmt->fetchAll();
    
    // Group theo order_id
    foreach ($allItems as $item) {
        $orderItems[$item['order_id']][] = $item;
    }
}

// ── Thống kê nhanh ──
$totalSpent = array_sum(array_column($orders, 'total'));
$totalOrders = count($orders);

// ── Status labels ──
$statusLabels = [
    'pending'    => ['label' => 'Đang xử lý', 'color' => 'text-orange-600 bg-orange-50 border-orange-200'],
    'processing' => ['label' => 'Đang chuẩn bị', 'color' => 'text-blue-600 bg-blue-50 border-blue-200'],
    'shipped'    => ['label' => 'Đang giao hàng', 'color' => 'text-purple-600 bg-purple-50 border-purple-200'],
    'delivered'  => ['label' => 'Đã giao', 'color' => 'text-green-600 bg-green-50 border-green-200'],
    'cancelled'  => ['label' => 'Đã hủy', 'color' => 'text-red-600 bg-red-50 border-red-200'],
];

$pageTitle = 'Hồ sơ của tôi — BestBuy Store';
$pageDescription = 'Xem thông tin tài khoản và lịch sử đơn hàng của bạn.';

require_once __DIR__ . '/includes/header.php';
?>

    <div class="max-w-5xl mx-auto px-4 py-8">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
            <a href="/" class="hover:text-bb-blue transition-colors">Trang chủ</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-600 font-medium">Hồ sơ của tôi</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- ═══ LEFT: Profile Card ═══ -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    
                    <!-- Avatar header -->
                    <div class="bg-gradient-to-r from-bb-blue to-bb-dark p-6 text-center">
                        <div class="w-20 h-20 bg-white/15 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-sm text-3xl font-bold text-white border-2 border-white/20">
                            <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
                        </div>
                        <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($user['name']) ?></h2>
                        <p class="text-blue-200/70 text-sm"><?= htmlspecialchars($user['email']) ?></p>
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="inline-block mt-2 bg-bb-yellow/20 text-bb-yellow text-xs font-semibold px-3 py-1 rounded-full">⚙ Admin</span>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="p-5 space-y-3">
                        <?php if ($user['phone']): ?>
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            <span class="text-gray-600"><?= htmlspecialchars($user['phone']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center gap-3 text-sm">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <span class="text-gray-600">Thành viên từ <?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                        </div>

                        <hr class="border-gray-100">

                        <!-- Quick stats -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-blue-50 rounded-xl p-3 text-center">
                                <p class="text-2xl font-bold text-bb-blue"><?= $totalOrders ?></p>
                                <p class="text-xs text-gray-500">Đơn hàng</p>
                            </div>
                            <div class="bg-green-50 rounded-xl p-3 text-center">
                                <p class="text-lg font-bold text-green-600"><?= formatPrice($totalSpent) ?></p>
                                <p class="text-xs text-gray-500">Tổng chi tiêu</p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="p-5 pt-0">
                        <a href="/auth/logout.php" class="block w-full text-center py-2.5 border-2 border-red-200 text-red-500 rounded-xl text-sm font-semibold hover:bg-red-50 transition-colors">
                            Đăng xuất
                        </a>
                    </div>
                </div>
            </div>

            <!-- ═══ RIGHT: Order History ═══ -->
            <div class="lg:col-span-2">
                <h1 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                    <svg class="w-7 h-7 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Lịch sử đơn hàng
                </h1>

                <?php if (empty($orders)): ?>
                    <!-- Empty state -->
                    <div class="bg-white rounded-2xl p-12 text-center shadow-sm border border-gray-100">
                        <span class="text-6xl mb-4 block">📦</span>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">Chưa có đơn hàng nào</h3>
                        <p class="text-gray-400 mb-6">Bạn chưa mua sắm gì. Hãy khám phá các sản phẩm tuyệt vời!</p>
                        <a href="/" class="inline-flex items-center gap-2 bg-bb-yellow text-bb-dark font-bold px-8 py-3 rounded-full hover:bg-yellow-300 transition-all">
                            ← Mua sắm ngay
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                    <?php foreach ($orders as $order):
                        $status = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'color' => 'text-gray-600 bg-gray-50 border-gray-200'];
                        $items = $orderItems[$order['id']] ?? [];
                    ?>
                        <!-- Order Card -->
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <!-- Order Header -->
                            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-50">
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <span class="font-bold text-gray-800 text-base"><?= htmlspecialchars($order['order_code']) ?></span>
                                        <span class="text-xs font-medium px-2.5 py-1 rounded-full border <?= $status['color'] ?>">
                                            <?= $status['label'] ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400">
                                        <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                        · <?= $order['item_count'] ?> sản phẩm
                                        · <?= $order['payment_method'] === 'cod' ? 'COD' : 'Thẻ' ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xl font-bold text-bb-blue"><?= formatPrice((float) $order['total']) ?></p>
                                </div>
                            </div>

                            <!-- Order Items (expandable) -->
                            <details class="group">
                                <summary class="px-6 py-3 text-sm font-medium text-bb-blue cursor-pointer hover:bg-blue-50/50 transition-colors flex items-center gap-2 select-none">
                                    <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    Xem chi tiết sản phẩm
                                </summary>
                                <div class="px-6 pb-4 space-y-3">
                                    <?php foreach ($items as $item):
                                        $itemImage = getProductImage($item['image'] ?? '');
                                    ?>
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center shrink-0 border border-gray-100 overflow-hidden">
                                            <?php if ($itemImage): ?>
                                                <img src="<?= htmlspecialchars($itemImage) ?>" alt="" class="w-full h-full object-contain p-1" loading="lazy">
                                            <?php else: ?>
                                                <span class="text-lg opacity-50">📦</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <?php if (!empty($item['slug'])): ?>
                                                <a href="/<?= htmlspecialchars($item['slug']) ?>.html" class="text-sm font-medium text-gray-800 hover:text-bb-blue transition-colors line-clamp-1"><?= htmlspecialchars($item['product_name']) ?></a>
                                            <?php else: ?>
                                                <p class="text-sm font-medium text-gray-800 line-clamp-1"><?= htmlspecialchars($item['product_name']) ?></p>
                                            <?php endif; ?>
                                            <p class="text-xs text-gray-400"><?= formatPrice((float) $item['price']) ?> × <?= (int) $item['quantity'] ?></p>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-700 whitespace-nowrap">
                                            <?= formatPrice((float) $item['price'] * (int) $item['quantity']) ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>

                                    <!-- Order Summary -->
                                    <div class="pt-3 mt-3 border-t border-gray-100 space-y-1 text-sm">
                                        <div class="flex justify-between text-gray-500">
                                            <span>Tạm tính</span>
                                            <span><?= formatPrice((float) $order['subtotal']) ?></span>
                                        </div>
                                        <div class="flex justify-between text-gray-500">
                                            <span>Phí ship</span>
                                            <span class="<?= (float)$order['shipping_fee'] == 0 ? 'text-green-600' : '' ?>">
                                                <?= (float)$order['shipping_fee'] == 0 ? 'Miễn phí' : formatPrice((float) $order['shipping_fee']) ?>
                                            </span>
                                        </div>
                                        <div class="flex justify-between text-gray-500">
                                            <span>VAT</span>
                                            <span><?= formatPrice((float) $order['tax']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

