<?php
/**
 * Admin Inventory — Quản lý Kho hàng & Nhập kho (theo Variant)
 */

$adminPage  = 'inventory';
$adminTitle = 'Kho Hàng';

require_once __DIR__ . '/includes/admin_header.php';

$action  = $_POST['action'] ?? 'list';
$message = '';
$msgType = 'info';

// ═══════════════════════════════════════
// XỬ LÝ POST ACTIONS
// ═══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_stock') {
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        $supplier_id = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $type = $_POST['type'] ?? 'in'; // in, out
        $quantity = (int)($_POST['quantity'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $user_id = $_SESSION['user']['id'] ?? null; // Nếu có lưu admin id trong session

        if ($variant_id > 0 && $quantity > 0) {
            try {
                $pdo->beginTransaction();

                // 1. Thêm log giao dịch kho
                $stmtLog = $pdo->prepare("INSERT INTO inventory_logs (variant_id, supplier_id, type, quantity, note, user_id) VALUES (:variant_id, :supplier_id, :type, :quantity, :note, :user_id)");
                $stmtLog->execute([
                    ':variant_id' => $variant_id,
                    ':supplier_id' => $supplier_id,
                    ':type' => $type,
                    ':quantity' => $quantity,
                    ':note' => $note,
                    ':user_id' => $user_id
                ]);

                // 2. Cập nhật tồn kho sản phẩm (ở bảng product_variants)
                if ($type === 'in') {
                    $stmtUpdate = $pdo->prepare("UPDATE product_variants SET stock = stock + :quantity WHERE id = :id");
                    $stmtUpdate->execute([':quantity' => $quantity, ':id' => $variant_id]);
                    $message = '✅ Đã nhập kho thành công!';
                } elseif ($type === 'out') {
                    $stmtUpdate = $pdo->prepare("UPDATE product_variants SET stock = GREATEST(0, stock - :quantity) WHERE id = :id");
                    $stmtUpdate->execute([':quantity' => $quantity, ':id' => $variant_id]);
                    $message = '✅ Đã xuất kho thành công!';
                }

                $pdo->commit();
                $msgType = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }
        } else {
            $message = '❌ Vui lòng chọn sản phẩm và số lượng hợp lệ (>0).';
            $msgType = 'error';
        }
    }
}

// ══════════════════════════════════════════
// FETCH DATA
// ══════════════════════════════════════════
// Lấy danh sách các variant
$variants = $pdo->query("
    SELECT pv.id as variant_id, p.name as product_name, pv.sku, pv.stock, 
           GROUP_CONCAT(av.value SEPARATOR ' - ') as attrs
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    LEFT JOIN variant_attribute_values vav ON pv.id = vav.variant_id
    LEFT JOIN attribute_values av ON vav.attribute_value_id = av.id
    GROUP BY pv.id
    ORDER BY p.name ASC, pv.sku ASC
")->fetchAll();

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

$logs = $pdo->query("
    SELECT il.*, p.name as product_name, pv.sku, pv.image_url, s.name as supplier_name,
           (SELECT GROUP_CONCAT(av.value SEPARATOR ' - ') 
            FROM variant_attribute_values vav 
            JOIN attribute_values av ON vav.attribute_value_id = av.id 
            WHERE vav.variant_id = pv.id) as attrs
    FROM inventory_logs il
    JOIN product_variants pv ON il.variant_id = pv.id
    JOIN products p ON pv.product_id = p.id
    LEFT JOIN suppliers s ON il.supplier_id = s.id
    ORDER BY il.created_at DESC
    LIMIT 100
")->fetchAll();

?>

<!-- Flash message -->
<?php if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Form Nhập/Xuất Kho -->
    <div class="xl:col-span-1">
        <div class="bg-admin-card rounded-2xl border border-admin-border p-5 sticky top-24">
            <h2 class="text-lg font-bold text-white mb-4">Giao Dịch Kho</h2>
            <form method="POST" action="/admin/inventory.php" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_stock">
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Loại giao dịch</label>
                    <select name="type" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                        <option value="in">Nhập kho (Tăng tồn kho)</option>
                        <option value="out">Xuất kho (Giảm tồn kho)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Sản phẩm (Biến thể) *</label>
                    <select name="variant_id" required class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php foreach ($variants as $v): ?>
                            <option value="<?= $v['variant_id'] ?>">
                                <?= htmlspecialchars($v['product_name']) ?> 
                                <?= $v['attrs'] ? ' ('.htmlspecialchars($v['attrs']).')' : '' ?>
                                - SKU: <?= htmlspecialchars($v['sku']) ?> 
                                [Tồn: <?= $v['stock'] ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Số lượng *</label>
                    <input type="number" name="quantity" required min="1"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                           placeholder="VD: 50">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Nhà cung cấp (Tùy chọn)</label>
                    <select name="supplier_id" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                        <option value="0">-- Chọn nhà cung cấp --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Ghi chú</label>
                    <textarea name="note" rows="2"
                              class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                              placeholder="Lý do nhập/xuất..."></textarea>
                </div>

                <button type="submit" class="w-full bg-bb-yellow text-bb-dark font-bold px-4 py-3 rounded-xl hover:bg-yellow-300 transition-all active:scale-[0.98]">
                    Xác Nhận
                </button>
            </form>
        </div>
    </div>

    <!-- Lịch sử kho hàng -->
    <div class="xl:col-span-2 space-y-6">
        <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
            <div class="px-5 py-4 border-b border-admin-border">
                <h3 class="text-lg font-bold text-white">Lịch sử Giao dịch (100 gần nhất)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-400">
                    <thead class="bg-admin-bg/50 text-gray-300">
                        <tr>
                            <th class="px-5 py-4 font-semibold">Thời gian</th>
                            <th class="px-5 py-4 font-semibold">Sản phẩm</th>
                            <th class="px-5 py-4 font-semibold">Loại</th>
                            <th class="px-5 py-4 font-semibold text-right">Số lượng</th>
                            <th class="px-5 py-4 font-semibold">Ghi chú / NCC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-admin-border">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-gray-500">
                                    Chưa có giao dịch kho nào.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-admin-bg/50 transition-colors">
                                    <td class="px-5 py-4 whitespace-nowrap text-gray-300">
                                        <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <?php if ($log['image_url']): ?>
                                                <img src="/<?= htmlspecialchars($log['image_url']) ?>" class="w-8 h-8 rounded object-cover bg-white">
                                            <?php else: ?>
                                                <div class="w-8 h-8 rounded bg-gray-800 flex items-center justify-center text-xs text-gray-500">No img</div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="font-medium text-white line-clamp-1" title="<?= htmlspecialchars($log['product_name']) ?>">
                                                    <?= htmlspecialchars($log['product_name']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    <?= htmlspecialchars($log['sku']) ?>
                                                    <?= $log['attrs'] ? ' (' . htmlspecialchars($log['attrs']) . ')' : '' ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <?php if ($log['type'] === 'in'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">Nhập kho</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Xuất kho</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-right font-medium <?= $log['type'] === 'in' ? 'text-green-400' : 'text-red-400' ?>">
                                        <?= $log['type'] === 'in' ? '+' : '-' ?><?= number_format($log['quantity']) ?>
                                    </td>
                                    <td class="px-5 py-4 text-xs text-gray-500 max-w-[200px] truncate">
                                        <?php if ($log['supplier_name']): ?>
                                            <div class="text-gray-300">NCC: <?= htmlspecialchars($log['supplier_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($log['note']): ?>
                                            <div><?= htmlspecialchars($log['note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
