<?php
/**
 * Admin Suppliers — Quản lý Nhà cung cấp
 */

$adminPage  = 'suppliers';
$adminTitle = 'Nhà Cung Cấp';

require_once __DIR__ . '/includes/admin_header.php';

$action  = $_POST['action'] ?? 'list';
$message = '';
$msgType = 'info';

// ═══════════════════════════════════════
// XỬ LÝ POST ACTIONS
// ═══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CREATE / UPDATE SUPPLIER ──
    if ($action === 'save_supplier') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (!empty($name)) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE suppliers SET name = :name, contact_name = :contact_name, email = :email, phone = :phone, address = :address WHERE id = :id");
                    $stmt->execute([':name' => $name, ':contact_name' => $contact_name, ':email' => $email, ':phone' => $phone, ':address' => $address, ':id' => $id]);
                    $message = '✅ Đã cập nhật nhà cung cấp thành công!';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_name, email, phone, address) VALUES (:name, :contact_name, :email, :phone, :address)");
                    $stmt->execute([':name' => $name, ':contact_name' => $contact_name, ':email' => $email, ':phone' => $phone, ':address' => $address]);
                    $message = '✅ Đã thêm nhà cung cấp mới thành công!';
                }
                $msgType = 'success';
            } catch (PDOException $e) {
                $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }
        } else {
            $message = '❌ Tên nhà cung cấp không được để trống.';
            $msgType = 'error';
        }
    }

    // ── DELETE SUPPLIER ──
    elseif ($action === 'delete_supplier') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = '✅ Đã xóa nhà cung cấp thành công!';
                $msgType = 'success';
            } catch (PDOException $e) {
                $message = '❌ Không thể xóa nhà cung cấp này vì có sản phẩm hoặc lịch sử kho liên kết.';
                $msgType = 'error';
            }
        }
    }
}

// ══════════════════════════════════════════
// FETCH DATA
// ══════════════════════════════════════════
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC")->fetchAll();

?>

<!-- Flash message -->
<?php if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Form Thêm / Sửa Nhà cung cấp -->
    <div class="xl:col-span-1">
        <div class="bg-admin-card rounded-2xl border border-admin-border p-5 sticky top-24" id="supplierFormContainer">
            <h2 class="text-lg font-bold text-white mb-4" id="formTitle">Thêm Nhà Cung Cấp Mới</h2>
            <form method="POST" action="/admin/suppliers.php" class="space-y-4" id="supplierForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_supplier">
                <input type="hidden" name="id" id="supplier_id" value="">
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Tên nhà cung cấp *</label>
                    <input type="text" name="name" id="supplier_name" required
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                           placeholder="VD: Công ty TNHH Apple Việt Nam">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Người liên hệ</label>
                    <input type="text" name="contact_name" id="supplier_contact_name"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                           placeholder="VD: Nguyễn Văn A">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Email</label>
                    <input type="email" name="email" id="supplier_email"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                           placeholder="contact@example.com">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Số điện thoại</label>
                    <input type="text" name="phone" id="supplier_phone"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                           placeholder="0901234567">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Địa chỉ</label>
                    <textarea name="address" id="supplier_address" rows="3"
                              class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                              placeholder="Nhập địa chỉ chi tiết"></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit" id="btnSubmit" class="flex-1 bg-bb-yellow text-bb-dark font-bold px-4 py-3 rounded-xl hover:bg-yellow-300 transition-all active:scale-[0.98]">
                        Lưu Thay Đổi
                    </button>
                    <button type="button" id="btnCancel" onclick="resetForm()" class="hidden px-4 py-3 rounded-xl bg-admin-border text-white hover:bg-gray-600 transition-all">
                        Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách Nhà cung cấp -->
    <div class="xl:col-span-2 space-y-6">
        <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-400">
                    <thead class="bg-admin-bg/50 text-gray-300">
                        <tr>
                            <th class="px-5 py-4 font-semibold">Nhà Cung Cấp</th>
                            <th class="px-5 py-4 font-semibold">Liên Hệ</th>
                            <th class="px-5 py-4 font-semibold text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-admin-border">
                        <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-gray-500">
                                    Chưa có nhà cung cấp nào.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $sup): ?>
                                <tr class="hover:bg-admin-bg/50 transition-colors group">
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-white"><?= htmlspecialchars($sup['name']) ?></div>
                                        <div class="text-xs text-gray-500 mt-1">ID: <?= $sup['id'] ?></div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="text-gray-300"><?= htmlspecialchars($sup['contact_name']) ?></div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?= htmlspecialchars($sup['phone']) ?> 
                                            <?= $sup['email'] ? ' • ' . htmlspecialchars($sup['email']) : '' ?>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <button onclick='editSupplier(<?= json_encode($sup) ?>)' class="text-blue-400 hover:text-blue-300 transition-colors">
                                                Sửa
                                            </button>
                                            <form method="POST" action="/admin/suppliers.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhà cung cấp này?');" class="inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete_supplier">
                                                <input type="hidden" name="id" value="<?= $sup['id'] ?>">
                                                <button type="submit" class="text-red-400 hover:text-red-300 transition-colors">
                                                    Xóa
                                                </button>
                                            </form>
                                        </div>
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

<script>
function editSupplier(sup) {
    document.getElementById('formTitle').innerText = 'Cập Nhật Nhà Cung Cấp';
    document.getElementById('supplier_id').value = sup.id;
    document.getElementById('supplier_name').value = sup.name;
    document.getElementById('supplier_contact_name').value = sup.contact_name || '';
    document.getElementById('supplier_email').value = sup.email || '';
    document.getElementById('supplier_phone').value = sup.phone || '';
    document.getElementById('supplier_address').value = sup.address || '';
    
    document.getElementById('btnCancel').classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('formTitle').innerText = 'Thêm Nhà Cung Cấp Mới';
    document.getElementById('supplier_id').value = '';
    document.getElementById('supplierForm').reset();
    document.getElementById('btnCancel').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
