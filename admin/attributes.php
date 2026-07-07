<?php
/**
 * Admin Attributes — Quản lý Thuộc tính & Giá trị (Màu sắc, Dung lượng...)
 */

$adminPage  = 'attributes';
$adminTitle = 'Quản lý Thuộc tính';

require_once __DIR__ . '/includes/admin_header.php';

$action  = $_POST['action'] ?? 'list';
$message = '';
$msgType = 'info';

// ═══════════════════════════════════════
// XỬ LÝ POST ACTIONS
// ═══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CREATE ATTRIBUTE ──
    if ($action === 'create_attr') {
        $name = trim($_POST['name'] ?? '');
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO attributes (name) VALUES (:name)");
                $stmt->execute([':name' => $name]);
                $message = '✅ Đã thêm thuộc tính "' . htmlspecialchars($name) . '" thành công!';
                $msgType = 'success';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    $message = '❌ Thuộc tính này đã tồn tại.';
                } else {
                    $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
                }
                $msgType = 'error';
            }
        } else {
            $message = '❌ Tên thuộc tính không được để trống.';
            $msgType = 'error';
        }
    }

    // ── DELETE ATTRIBUTE ──
    elseif ($action === 'delete_attr') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM attributes WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = '✅ Đã xóa thuộc tính thành công!';
                $msgType = 'success';
            } catch (PDOException $e) {
                $message = '❌ Không thể xóa thuộc tính này vì đang có biến thể sử dụng nó.';
                $msgType = 'error';
            }
        }
    }

    // ── CREATE ATTRIBUTE VALUE ──
    elseif ($action === 'create_val') {
        $attrId = (int) ($_POST['attribute_id'] ?? 0);
        $value = trim($_POST['value'] ?? '');
        
        if ($attrId > 0 && !empty($value)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO attribute_values (attribute_id, value) VALUES (:attr_id, :val)");
                $stmt->execute([':attr_id' => $attrId, ':val' => $value]);
                $message = '✅ Đã thêm giá trị "' . htmlspecialchars($value) . '" thành công!';
                $msgType = 'success';
            } catch (PDOException $e) {
                $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }
        } else {
            $message = '❌ Giá trị không được để trống.';
            $msgType = 'error';
        }
    }

    // ── DELETE ATTRIBUTE VALUE ──
    elseif ($action === 'delete_val') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM attribute_values WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = '✅ Đã xóa giá trị thành công!';
                $msgType = 'success';
            } catch (PDOException $e) {
                $message = '❌ Không thể xóa giá trị này vì đang có biến thể sử dụng nó.';
                $msgType = 'error';
            }
        }
    }
}

// ══════════════════════════════════════════
// FETCH DATA
// ══════════════════════════════════════════
$attributesRaw = $pdo->query("
    SELECT a.id as attr_id, a.name as attr_name, av.id as val_id, av.value as val_name
    FROM attributes a
    LEFT JOIN attribute_values av ON a.id = av.attribute_id
    ORDER BY a.id DESC, av.id ASC
")->fetchAll();

$attributes = [];
foreach ($attributesRaw as $row) {
    if (!isset($attributes[$row['attr_id']])) {
        $attributes[$row['attr_id']] = [
            'id' => $row['attr_id'],
            'name' => $row['attr_name'],
            'values' => []
        ];
    }
    if ($row['val_id']) {
        $attributes[$row['attr_id']]['values'][] = [
            'id' => $row['val_id'],
            'value' => $row['val_name']
        ];
    }
}

?>

<!-- Flash message -->
<?php if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Form Thêm Thuộc tính Mới -->
    <div class="lg:col-span-1">
        <div class="bg-admin-card rounded-2xl border border-admin-border p-5 sticky top-24">
            <h2 class="text-lg font-bold text-white mb-4">Thêm Thuộc Tính Mới</h2>
            <form method="POST" action="/admin/attributes.php" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_attr">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Tên thuộc tính (VD: Màu sắc, RAM)</label>
                    <input type="text" name="name" required
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                           placeholder="Nhập tên thuộc tính...">
                </div>
                <button type="submit" class="w-full bg-bb-yellow text-bb-dark font-bold px-4 py-3 rounded-xl hover:bg-yellow-300 transition-all active:scale-[0.98]">
                    ➕ Thêm Thuộc Tính
                </button>
            </form>
        </div>
    </div>

    <!-- Danh sách Thuộc tính & Giá trị -->
    <div class="lg:col-span-2 space-y-6">
        <?php if (empty($attributes)): ?>
            <div class="bg-admin-card rounded-2xl border border-admin-border p-8 text-center text-gray-500">
                Chưa có thuộc tính nào. Hãy thêm ở bên trái.
            </div>
        <?php else: ?>
            <?php foreach ($attributes as $attr): ?>
                <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
                    <div class="bg-admin-bg/50 px-5 py-4 border-b border-admin-border flex items-center justify-between">
                        <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($attr['name']) ?></h3>
                        
                        <form method="POST" action="/admin/attributes.php" onsubmit="return confirmDelete('Xóa thuộc tính <?= htmlspecialchars(addslashes($attr['name'])) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_attr">
                            <input type="hidden" name="id" value="<?= $attr['id'] ?>">
                            <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition-colors">
                                Xóa thuộc tính
                            </button>
                        </form>
                    </div>
                    
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2 mb-4">
                            <?php if (empty($attr['values'])): ?>
                                <span class="text-sm text-gray-500 italic">Chưa có giá trị nào.</span>
                            <?php else: ?>
                                <?php foreach ($attr['values'] as $val): ?>
                                    <div class="group inline-flex items-center gap-2 bg-admin-bg border border-admin-border px-3 py-1.5 rounded-lg text-sm text-gray-300">
                                        <?= htmlspecialchars($val['value']) ?>
                                        <form method="POST" action="/admin/attributes.php" class="inline" onsubmit="return confirmDelete('Xóa giá trị <?= htmlspecialchars(addslashes($val['value'])) ?>?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_val">
                                            <input type="hidden" name="id" value="<?= $val['id'] ?>">
                                            <button type="submit" class="text-red-400 hover:text-red-300 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Form thêm giá trị -->
                        <form method="POST" action="/admin/attributes.php" class="flex items-center gap-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="create_val">
                            <input type="hidden" name="attribute_id" value="<?= $attr['id'] ?>">
                            <input type="text" name="value" required
                                   class="flex-1 px-3 py-2 bg-admin-bg border border-admin-border rounded-lg text-white text-sm focus:border-bb-yellow outline-none"
                                   placeholder="Thêm giá trị mới (VD: Đen, 256GB)...">
                            <button type="submit" class="bg-admin-border text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors text-sm font-medium">
                                Thêm
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(msg) {
    return confirm('Bạn có chắc chắn muốn ' + msg);
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
