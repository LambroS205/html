<?php
/**
 * Admin Orders — Quản lý Đơn hàng
 * 
 * Actions:
 *  - (default) : Danh sách đơn hàng
 *  - ?action=view&id=X : Chi tiết đơn hàng
 *  - POST action=update_status : Cập nhật trạng thái đơn hàng
 * 
 * Bảo mật: htmlspecialchars + Prepared Statements
 */

$adminPage  = 'orders';
$adminTitle = 'Quản lý đơn hàng';

require_once __DIR__ . '/includes/admin_header.php';

$action  = $_GET['action'] ?? $_POST['action'] ?? 'list';
$message = '';
$msgType = 'info';

$statusColors = [
    'pending'    => 'text-orange-400 bg-orange-400/10 border-orange-500/20',
    'processing' => 'text-blue-400 bg-blue-400/10 border-blue-500/20',
    'shipped'    => 'text-purple-400 bg-purple-400/10 border-purple-500/20',
    'delivered'  => 'text-green-400 bg-green-400/10 border-green-500/20',
    'cancelled'  => 'text-red-400 bg-red-400/10 border-red-500/20',
];
$statusLabels = [
    'pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý',
    'shipped' => 'Đang giao', 'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy',
];

// ═══ UPDATE STATUS ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'update_status' || isset($_POST['action']) && $_POST['action'] === 'update_status')) {
    $orderId   = (int) ($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    
    if ($orderId > 0 && array_key_exists($newStatus, $statusLabels)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
            $message = '✅ Đã cập nhật trạng thái đơn #' . $orderId . ' → ' . $statusLabels[$newStatus];
            $msgType = 'success';
        } catch (PDOException $e) {
            $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
            $msgType = 'error';
        }
    }
    
    // Redirect back
    if (isset($_POST['return_view']) && $_POST['return_view'] == '1') {
        $action = 'view';
        $_GET['id'] = $orderId;
    } else {
        $action = 'list';
    }
}

// Flash message
if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
        <?= $message ?>
    </div>
<?php endif;

// ═══ VIEW — Chi tiết đơn hàng ═══
if ($action === 'view'):
    $orderId = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo '<p class="text-red-400">Đơn hàng không tồn tại.</p>';
        require_once __DIR__ . '/includes/admin_footer.php';
        exit;
    }

    // Items
    $itemStmt = $pdo->prepare("SELECT oi.*, p.image, p.slug FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :oid ORDER BY oi.id");
    $itemStmt->execute([':oid' => $orderId]);
    $items = $itemStmt->fetchAll();

    $sc = $statusColors[$order['status']] ?? '';
    $sl = $statusLabels[$order['status']] ?? $order['status'];
?>
    <div class="max-w-4xl">
        <!-- Back + Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <a href="/admin/orders.php" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <h2 class="text-xl font-bold text-white">Đơn hàng <?= htmlspecialchars($order['order_code']) ?></h2>
                <span class="text-xs font-medium px-2.5 py-1 rounded-full border <?= $sc ?>"><?= $sl ?></span>
            </div>
            <span class="text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Customer Info -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Items -->
                <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
                    <div class="px-5 py-3 border-b border-admin-border">
                        <h3 class="font-bold text-white text-sm">Sản phẩm (<?= count($items) ?>)</h3>
                    </div>
                    <div class="divide-y divide-admin-border">
                        <?php foreach ($items as $item):
                            $iImg = getProductImage($item['image'] ?? '');
                        ?>
                        <div class="px-5 py-3 flex items-center gap-3">
                            <div class="w-12 h-12 bg-admin-bg rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
                                <?php if ($iImg): ?>
                                    <img src="<?= htmlspecialchars($iImg) ?>" alt="" class="w-full h-full object-contain p-1">
                                <?php else: ?>
                                    <span class="text-xl">📦</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <?php if ($item['slug']): ?>
                                    <a href="/product.php?slug=<?= htmlspecialchars($item['slug']) ?>" target="_blank" class="text-sm font-medium text-white hover:text-bb-yellow truncate block">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($item['product_name']) ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500"><?= formatPrice((float)$item['price']) ?> × <?= $item['quantity'] ?></p>
                            </div>
                            <span class="text-sm font-bold text-bb-yellow"><?= formatPrice((float)$item['price'] * $item['quantity']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Customer Details -->
                <div class="bg-admin-card rounded-2xl border border-admin-border p-5">
                    <h3 class="font-bold text-white text-sm mb-4">Thông tin khách hàng</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 text-xs mb-1">Họ tên</p>
                            <p class="text-white font-medium"><?= htmlspecialchars($order['customer_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs mb-1">Email</p>
                            <p class="text-white font-medium"><?= htmlspecialchars($order['customer_email']) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs mb-1">Điện thoại</p>
                            <p class="text-white font-medium"><?= htmlspecialchars($order['customer_phone']) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs mb-1">Thanh toán</p>
                            <p class="text-white font-medium">
                                <?= $order['payment_method'] === 'cod' ? '💵 COD' : '💳 Thẻ quốc tế' ?>
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-gray-500 text-xs mb-1">Địa chỉ giao hàng</p>
                            <p class="text-white font-medium"><?= htmlspecialchars($order['shipping_address']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Totals + Status -->
            <div class="space-y-4">
                <!-- Order Totals -->
                <div class="bg-admin-card rounded-2xl border border-admin-border p-5">
                    <h3 class="font-bold text-white text-sm mb-4">Tổng tiền</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-400">
                            <span>Tạm tính</span>
                            <span class="text-white"><?= formatPrice((float)$order['subtotal']) ?></span>
                        </div>
                        <div class="flex justify-between text-gray-400">
                            <span>Vận chuyển</span>
                            <span class="text-white <?= $order['shipping_fee'] == 0 ? 'text-green-400' : '' ?>">
                                <?= $order['shipping_fee'] == 0 ? 'Miễn phí' : formatPrice((float)$order['shipping_fee']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between text-gray-400">
                            <span>VAT</span>
                            <span class="text-white"><?= formatPrice((float)$order['tax']) ?></span>
                        </div>
                        <div class="border-t border-admin-border pt-2 mt-2">
                            <div class="flex justify-between">
                                <span class="font-bold text-white">Tổng cộng</span>
                                <span class="text-xl font-black text-bb-yellow"><?= formatPrice((float)$order['total']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Update Status -->
                <div class="bg-admin-card rounded-2xl border border-admin-border p-5">
                    <h3 class="font-bold text-white text-sm mb-4">Cập nhật trạng thái</h3>
                    <form method="POST" action="/admin/orders.php">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="return_view" value="1">
                        <select name="status" class="w-full px-3 py-2.5 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none mb-3">
                            <?php foreach ($statusLabels as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="w-full bg-bb-blue text-white font-semibold py-2.5 rounded-xl hover:bg-bb-dark transition-colors text-sm">
                            Cập nhật
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php else:
// ═══ LIST — Danh sách đơn hàng ═══
    $filterStatus = $_GET['status'] ?? '';
    $whereSQL = '';
    $params = [];
    if ($filterStatus && array_key_exists($filterStatus, $statusLabels)) {
        $whereSQL = 'WHERE o.status = :status';
        $params[':status'] = $filterStatus;
    }

    $stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        {$whereSQL}
        ORDER BY o.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
?>
    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <span class="text-sm text-gray-500 mr-2">Lọc:</span>
        <a href="/admin/orders.php" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors <?= !$filterStatus ? 'bg-bb-yellow text-bb-dark' : 'bg-admin-card text-gray-400 hover:text-white border border-admin-border' ?>">
            Tất cả (<?= $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?>)
        </a>
        <?php foreach ($statusLabels as $sKey => $sLabel):
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = :s");
            $cnt->execute([':s' => $sKey]);
            $count = $cnt->fetchColumn();
        ?>
        <a href="/admin/orders.php?status=<?= $sKey ?>" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors <?= $filterStatus === $sKey ? 'bg-bb-yellow text-bb-dark' : 'bg-admin-card text-gray-400 hover:text-white border border-admin-border' ?>">
            <?= $sLabel ?> (<?= $count ?>)
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Orders Table -->
    <?php if (empty($orders)): ?>
        <div class="bg-admin-card rounded-2xl border border-admin-border p-12 text-center">
            <span class="text-5xl mb-3 block">📋</span>
            <p class="text-gray-400">Không có đơn hàng nào<?= $filterStatus ? ' với trạng thái này' : '' ?></p>
        </div>
    <?php else: ?>
    <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-admin-border text-left text-xs text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3">Mã đơn</th>
                        <th class="px-5 py-3">Khách hàng</th>
                        <th class="px-5 py-3">SP</th>
                        <th class="px-5 py-3">Thanh toán</th>
                        <th class="px-5 py-3 text-right">Tổng tiền</th>
                        <th class="px-5 py-3 text-center">Trạng thái</th>
                        <th class="px-5 py-3">Ngày đặt</th>
                        <th class="px-5 py-3 text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-admin-border">
                    <?php foreach ($orders as $o):
                        $sc = $statusColors[$o['status']] ?? '';
                        $sl = $statusLabels[$o['status']] ?? $o['status'];
                    ?>
                    <tr class="hover:bg-admin-bg/40 transition-colors">
                        <td class="px-5 py-3">
                            <a href="/admin/orders.php?action=view&id=<?= $o['id'] ?>" class="font-bold text-bb-yellow hover:text-yellow-300">
                                <?= htmlspecialchars($o['order_code']) ?>
                            </a>
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-white font-medium text-sm"><?= htmlspecialchars($o['customer_name']) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($o['customer_email']) ?></p>
                        </td>
                        <td class="px-5 py-3 text-gray-400"><?= $o['item_count'] ?></td>
                        <td class="px-5 py-3 text-gray-400"><?= $o['payment_method'] === 'cod' ? '💵 COD' : '💳 Card' ?></td>
                        <td class="px-5 py-3 text-right font-bold text-white"><?= formatPrice((float)$o['total']) ?></td>
                        <td class="px-5 py-3 text-center">
                            <span class="inline-block text-xs font-medium px-2.5 py-1 rounded-full border <?= $sc ?>">
                                <?= $sl ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                        <td class="px-5 py-3 text-center">
                            <a href="/admin/orders.php?action=view&id=<?= $o['id'] ?>" 
                               class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-bb-yellow transition-colors font-medium">
                                Chi tiết →
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
