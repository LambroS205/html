<?php
/**
 * Admin Categories — Quản lý danh mục sản phẩm
 */

$adminPage  = 'categories';
$adminTitle = 'Quản lý Danh mục';

require_once __DIR__ . '/includes/admin_header.php';
// admin_header.php đã include config/db.php và session/auth checks
// và helpers.php

$action  = $_GET['action'] ?? 'list';
$message = '';
$msgType = 'info';

// Xử lý các action (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? '');

        if (empty($name)) {
            $message = 'Tên danh mục không được để trống.';
            $msgType = 'error';
        } else {
            if (empty($slug)) {
                $slug = createSlug($name);
            } else {
                $slug = createSlug($slug);
            }

            try {
                if ($postAction === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon) VALUES (:name, :slug, :icon)");
                    $stmt->execute([
                        ':name' => $name,
                        ':slug' => $slug,
                        ':icon' => $icon
                    ]);
                    $message = 'Thêm danh mục thành công.';
                    $msgType = 'success';
                    $action  = 'list';
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET name = :name, slug = :slug, icon = :icon WHERE id = :id");
                    $stmt->execute([
                        ':name' => $name,
                        ':slug' => $slug,
                        ':icon' => $icon,
                        ':id'   => $id
                    ]);
                    $message = 'Cập nhật danh mục thành công.';
                    $msgType = 'success';
                    $action  = 'list';
                }
            } catch (PDOException $e) {
                // Lỗi trùng slug
                if ($e->getCode() == 23000) {
                    $message = 'Đường dẫn (slug) đã tồn tại, vui lòng chọn đường dẫn khác.';
                } else {
                    $message = 'Lỗi CSDL: ' . $e->getMessage();
                }
                $msgType = 'error';
                $action = $postAction === 'create' ? 'add' : 'edit';
                if ($action === 'edit') {
                    $_GET['id'] = $id; // Giữ lại id cho action edit
                }
            }
        }
    } elseif ($postAction === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        // Kiểm tra xem danh mục có sản phẩm không
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
        $checkStmt->execute([':id' => $id]);
        $productCount = $checkStmt->fetchColumn();
        
        if ($productCount > 0) {
            $message = 'Không thể xoá danh mục đang có sản phẩm. Vui lòng chuyển sản phẩm sang danh mục khác trước.';
            $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = 'Xoá danh mục thành công.';
                $msgType = 'success';
            } catch (PDOException $e) {
                $message = 'Lỗi CSDL: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
        $action = 'list';
    }
}

// -----------------------------------------------------------------------------
// VIEW: DANH SÁCH DANH MỤC
// -----------------------------------------------------------------------------
if ($action === 'list'):
    // Lấy danh sách danh mục và đếm số sản phẩm
    $categories = $pdo->query("
        SELECT c.*, COUNT(p.id) AS product_count 
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id
        ORDER BY c.id DESC
    ")->fetchAll();
?>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl font-bold text-white">Quản lý Danh mục</h2>
            <p class="text-sm text-gray-400 mt-1">Xem, thêm, sửa, xóa danh mục sản phẩm</p>
        </div>
        <a href="?action=add" class="inline-flex items-center gap-2 bg-bb-yellow text-bb-dark font-bold px-5 py-2.5 rounded-xl hover:bg-yellow-400 transition-colors shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Thêm danh mục
        </a>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-admin-bg/50 border-b border-admin-border text-gray-400 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Icon</th>
                        <th class="px-6 py-4">Tên danh mục</th>
                        <th class="px-6 py-4">Đường dẫn tĩnh (Slug)</th>
                        <th class="px-6 py-4 text-center">Số sản phẩm</th>
                        <th class="px-6 py-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-admin-border">
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">Không có danh mục nào.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-admin-bg/50 transition-colors">
                                <td class="px-6 py-4 text-gray-400">#<?= $cat['id'] ?></td>
                                <td class="px-6 py-4 text-2xl"><?= htmlspecialchars($cat['icon'] ?? '') ?></td>
                                <td class="px-6 py-4 font-medium text-white"><?= htmlspecialchars($cat['name']) ?></td>
                                <td class="px-6 py-4 text-gray-400"><?= htmlspecialchars($cat['slug']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center justify-center min-w-[2.5rem] bg-admin-bg px-2 py-1 rounded-lg text-bb-yellow font-medium border border-admin-border">
                                        <?= $cat['product_count'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="?action=edit&id=<?= $cat['id'] ?>" class="p-2 text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors" title="Sửa">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <?php if ($cat['product_count'] == 0): ?>
                                        <form action="categories.php" method="POST" onsubmit="return confirm('Bạn có chắc muốn xoá danh mục này?');" class="inline-block">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors" title="Xóa">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <button type="button" onclick="alert('Không thể xoá danh mục đang có sản phẩm!');" class="p-2 text-gray-500 cursor-not-allowed rounded-lg" title="Xóa">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
// -----------------------------------------------------------------------------
// VIEW: FORM THÊM / SỬA DANH MỤC
// -----------------------------------------------------------------------------
elseif ($action === 'add' || $action === 'edit'):
    $isEdit = ($action === 'edit');
    $category = [
        'id'   => 0,
        'name' => $_POST['name'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'icon' => $_POST['icon'] ?? ''
    ];

    if ($isEdit) {
        $id = (int)($_GET['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $category = $stmt->fetch();
            if (!$category) {
                echo "<div class='text-red-400'>Danh mục không tồn tại.</div>";
                require_once __DIR__ . '/includes/admin_footer.php';
                exit;
            }
        } else {
            $category['id'] = $id; // Giữ nguyên id nếu đang post lỗi
        }
    }
?>

    <div class="mb-8">
        <a href="categories.php" class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition-colors text-sm font-medium mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Quay lại danh sách
        </a>
        <h2 class="text-2xl font-bold text-white"><?= $isEdit ? 'Chỉnh sửa Danh mục' : 'Thêm Danh mục Mới' ?></h2>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="max-w-2xl bg-admin-card rounded-2xl border border-admin-border p-6 shadow-xl">
        <form action="categories.php?action=<?= $action ?><?= $isEdit ? '&id='.$category['id'] : '' ?>" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $category['id'] ?>">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Tên danh mục <span class="text-red-400">*</span></label>
                <input type="text" name="name" required value="<?= htmlspecialchars($category['name']) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors placeholder-gray-600" placeholder="VD: Điện thoại">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Đường dẫn tĩnh (Slug)</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($category['slug']) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors placeholder-gray-600" placeholder="Tự động tạo nếu để trống">
                <p class="text-xs text-gray-500 mt-2">Đường dẫn sẽ hiển thị trên URL (VD: `dien-thoai`). Không sử dụng dấu câu và khoảng trắng.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Icon / Emoji</label>
                <input type="text" name="icon" value="<?= htmlspecialchars($category['icon']) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors placeholder-gray-600" placeholder="VD: 📱">
            </div>

            <div class="pt-6 border-t border-admin-border flex justify-end gap-3">
                <a href="categories.php" class="px-6 py-2.5 rounded-xl text-sm font-medium text-gray-300 bg-admin-bg border border-admin-border hover:bg-gray-800 transition-colors">Hủy bỏ</a>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-bb-dark bg-bb-yellow hover:bg-yellow-400 transition-colors shadow-lg">
                    <?= $isEdit ? 'Lưu thay đổi' : 'Tạo danh mục' ?>
                </button>
            </div>
        </form>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
