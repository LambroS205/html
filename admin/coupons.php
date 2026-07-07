<?php
/**
 * Quản lý Mã giảm giá (Coupons)
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Kiểm tra quyền admin
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$pdo = Database::getConnection();
$message = '';
$error = '';

// --- XỬ LÝ FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = in_array($_POST['type'] ?? '', ['percent', 'fixed']) ? $_POST['type'] : 'percent';
    $value = (float)($_POST['value'] ?? 0);
    $min_order_value = (float)($_POST['min_order_value'] ?? 0);
    $expiry_date = empty($_POST['expiry_date']) ? null : $_POST['expiry_date'];
    $status = isset($_POST['status']) ? 1 : 0;

    try {
        if ($action === 'add' || $action === 'edit') {
            if (empty($code)) throw new Exception('Mã giảm giá không được để trống.');
            if ($value <= 0) throw new Exception('Giá trị giảm phải lớn hơn 0.');
            if ($type === 'percent' && $value > 100) throw new Exception('Phần trăm giảm không được vượt quá 100%.');

            // Check trùng mã
            $checkStmt = $pdo->prepare("SELECT id FROM coupons WHERE code = :code AND id != :id");
            $checkStmt->execute([':code' => $code, ':id' => $id]);
            if ($checkStmt->fetch()) {
                throw new Exception("Mã giảm giá '$code' đã tồn tại.");
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO coupons (code, type, value, min_order_value, expiry_date, status) 
                    VALUES (:code, :type, :value, :min_order, :expiry, :status)
                ");
                $stmt->execute([
                    ':code' => $code,
                    ':type' => $type,
                    ':value' => $value,
                    ':min_order' => $min_order_value,
                    ':expiry' => $expiry_date,
                    ':status' => $status
                ]);
                $message = 'Thêm mã giảm giá thành công!';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE coupons 
                    SET code = :code, type = :type, value = :value, 
                        min_order_value = :min_order, expiry_date = :expiry, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':code' => $code,
                    ':type' => $type,
                    ':value' => $value,
                    ':min_order' => $min_order_value,
                    ':expiry' => $expiry_date,
                    ':status' => $status,
                    ':id' => $id
                ]);
                $message = 'Cập nhật mã giảm giá thành công!';
            }
        } elseif ($action === 'delete') {
            // Kiểm tra xem có đơn hàng nào dùng mã này không
            $checkOrder = $pdo->prepare("SELECT id FROM orders WHERE coupon_id = :id LIMIT 1");
            $checkOrder->execute([':id' => $id]);
            if ($checkOrder->fetch()) {
                // Không xóa mà chuyển status = 0
                $pdo->prepare("UPDATE coupons SET status = 0 WHERE id = :id")->execute([':id' => $id]);
                $message = 'Mã này đã được sử dụng nên chỉ được vô hiệu hóa.';
            } else {
                $pdo->prepare("DELETE FROM coupons WHERE id = :id")->execute([':id' => $id]);
                $message = 'Xóa mã giảm giá thành công!';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Lấy danh sách coupons
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

$adminPage = 'coupons';
$adminTitle = 'Quản lý Mã giảm giá';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="flex flex-col lg:flex-row gap-6">
    <!-- CỘT TRÁI: FORM THÊM/SỬA -->
    <div class="w-full lg:w-1/3">
        <div class="bg-admin-card border border-admin-border rounded-xl p-5 shadow-lg">
            <h2 class="text-lg font-bold text-white mb-4" id="form-title">Thêm Mã giảm giá mới</h2>
            
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-lg mb-4 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-3 rounded-lg mb-4 text-sm">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="coupon-form" class="space-y-4">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="id" id="coupon-id" value="">
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Mã giảm giá (Code) *</label>
                    <input type="text" name="code" id="coupon-code" required class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:outline-none focus:border-bb-blue uppercase" placeholder="VD: SUMMER20">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Loại giảm *</label>
                        <select name="type" id="coupon-type" class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:outline-none focus:border-bb-blue">
                            <option value="percent">Phần trăm (%)</option>
                            <option value="fixed">Số tiền cố định (đ)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Giá trị giảm *</label>
                        <input type="number" step="0.01" name="value" id="coupon-value" required class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:outline-none focus:border-bb-blue" placeholder="VD: 20 hoặc 50000">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Giá trị đơn hàng tối thiểu (đ)</label>
                    <input type="number" step="0.01" name="min_order_value" id="coupon-min-order" class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:outline-none focus:border-bb-blue" placeholder="0 = Áp dụng mọi đơn hàng">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Hạn sử dụng</label>
                    <input type="datetime-local" name="expiry_date" id="coupon-expiry" class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:outline-none focus:border-bb-blue">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="status" id="coupon-status" value="1" checked class="w-4 h-4 rounded bg-admin-bg border-admin-border text-bb-blue focus:ring-bb-blue focus:ring-offset-admin-bg">
                    <label class="text-sm font-medium text-gray-300">Hoạt động</label>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="submit" class="flex-1 bg-bb-blue hover:bg-blue-600 text-white font-medium py-2 rounded-lg transition-colors">Lưu lại</button>
                    <button type="button" onclick="resetForm()" class="px-4 bg-admin-bg hover:bg-gray-700 text-white font-medium py-2 rounded-lg border border-admin-border transition-colors">Hủy</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CỘT PHẢI: DANH SÁCH -->
    <div class="w-full lg:w-2/3">
        <div class="bg-admin-card border border-admin-border rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-admin-bg/50 text-gray-400 font-medium">
                        <tr>
                            <th class="px-6 py-3">Mã giảm giá</th>
                            <th class="px-6 py-3">Giá trị</th>
                            <th class="px-6 py-3">Điều kiện</th>
                            <th class="px-6 py-3">Hạn sử dụng</th>
                            <th class="px-6 py-3">Trạng thái</th>
                            <th class="px-6 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-admin-border">
                        <?php if (empty($coupons)): ?>
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Chưa có mã giảm giá nào</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach ($coupons as $c): ?>
                            <tr class="hover:bg-admin-bg/50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-bb-yellow"><?= htmlspecialchars($c['code']) ?></span>
                                </td>
                                <td class="px-6 py-4 font-medium text-white">
                                    <?php if ($c['type'] === 'percent'): ?>
                                        Giảm <?= (float)$c['value'] ?>%
                                    <?php else: ?>
                                        Giảm <?= formatPrice((float)$c['value']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-400">
                                    <?= (float)$c['min_order_value'] > 0 ? 'Đơn tối thiểu<br>'.formatPrice((float)$c['min_order_value']) : 'Mọi đơn hàng' ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    if ($c['expiry_date']) {
                                        $isExpired = strtotime($c['expiry_date']) < time();
                                        echo '<span class="'.($isExpired ? 'text-red-400' : 'text-green-400').'">' . date('d/m/Y H:i', strtotime($c['expiry_date'])) . '</span>';
                                        if ($isExpired) echo '<br><span class="text-xs text-red-500">(Đã hết hạn)</span>';
                                    } else {
                                        echo '<span class="text-gray-500">Không thời hạn</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($c['status']): ?>
                                        <span class="bg-green-500/10 text-green-400 px-2 py-1 rounded-full text-xs">Đang hoạt động</span>
                                    <?php else: ?>
                                        <span class="bg-gray-500/10 text-gray-400 px-2 py-1 rounded-full text-xs">Đã tắt</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick="editCoupon(<?= htmlspecialchars(json_encode($c)) ?>)" class="p-1.5 bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 rounded transition-colors" title="Sửa">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa mã này?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="p-1.5 bg-red-500/10 text-red-400 hover:bg-red-500/20 rounded transition-colors" title="Xóa">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editCoupon(c) {
    document.getElementById('form-title').innerText = 'Sửa Mã: ' + c.code;
    document.getElementById('form-action').value = 'edit';
    document.getElementById('coupon-id').value = c.id;
    document.getElementById('coupon-code').value = c.code;
    document.getElementById('coupon-type').value = c.type;
    document.getElementById('coupon-value').value = parseFloat(c.value);
    document.getElementById('coupon-min-order').value = parseFloat(c.min_order_value);
    
    if (c.expiry_date) {
        // Convert to local datetime format YYYY-MM-DDThh:mm
        const date = new Date(c.expiry_date);
        const pad = n => n.toString().padStart(2, '0');
        const dtStr = `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        document.getElementById('coupon-expiry').value = dtStr;
    } else {
        document.getElementById('coupon-expiry').value = '';
    }
    
    document.getElementById('coupon-status').checked = c.status == 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form-title').innerText = 'Thêm Mã giảm giá mới';
    document.getElementById('form-action').value = 'add';
    document.getElementById('coupon-form').reset();
    document.getElementById('coupon-id').value = '';
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
