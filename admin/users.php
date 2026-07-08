<?php
/**
 * Admin Users — Quản lý tài khoản
 */

$adminPage  = 'users';
$adminTitle = 'Quản lý tài khoản';

require_once __DIR__ . '/includes/admin_header.php';

$action  = $_GET['action'] ?? $_POST['action'] ?? 'list';
$message = '';
$msgType = 'info';

// ═══════════════════════════════════════
// XỬ LÝ POST ACTIONS
// ═══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CREATE — Thêm người dùng mới ──
    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'customer';
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        // Validate
        $errors = [];
        if (empty($name)) $errors[] = 'Tên không được để trống';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ';
        if (empty($password)) $errors[] = 'Mật khẩu không được để trống';
        if (!in_array($role, ['admin', 'customer'])) $role = 'customer';

        if (empty($errors)) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, phone, address, role)
                    VALUES (:name, :email, :hash, :phone, :address, :role)
                ");
                $stmt->execute([
                    ':name'    => $name,
                    ':email'   => $email,
                    ':hash'    => $hash,
                    ':phone'   => $phone,
                    ':address' => $address,
                    ':role'    => $role
                ]);
                $message = '✅ Đã thêm người dùng "' . htmlspecialchars($name) . '" thành công!';
                $msgType = 'success';
                $action  = 'list';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $message = '❌ Email đã tồn tại trong hệ thống.';
                } else {
                    $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
                }
                $msgType = 'error';
                $action = 'add';
            }
        } else {
            $message = '❌ ' . implode('. ', $errors);
            $msgType = 'error';
            $action = 'add';
        }
    }

    // ── UPDATE — Cập nhật người dùng ──
    elseif ($action === 'update') {
        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'customer';
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        $errors = [];
        if ($id <= 0) $errors[] = 'ID không hợp lệ';
        if (empty($name)) $errors[] = 'Tên không được để trống';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ';
        if (!in_array($role, ['admin', 'customer'])) $role = 'customer';

        // Check if admin is trying to demote themselves
        if ($id === $_SESSION['user']['id'] && $role !== 'admin') {
            $errors[] = 'Không thể tự hạ quyền của chính mình';
        }

        if (empty($errors)) {
            try {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = :name, email = :email, password_hash = :hash, phone = :phone, address = :address, role = :role 
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':name'    => $name,
                        ':email'   => $email,
                        ':hash'    => $hash,
                        ':phone'   => $phone,
                        ':address' => $address,
                        ':role'    => $role,
                        ':id'      => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = :name, email = :email, phone = :phone, address = :address, role = :role 
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':name'    => $name,
                        ':email'   => $email,
                        ':phone'   => $phone,
                        ':address' => $address,
                        ':role'    => $role,
                        ':id'      => $id
                    ]);
                }
                
                // Update session if editing self
                if ($id === $_SESSION['user']['id']) {
                    $_SESSION['user']['name'] = $name;
                }

                $message = '✅ Đã cập nhật người dùng thành công!';
                $msgType = 'success';
                $action  = 'list';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $message = '❌ Email đã tồn tại trong hệ thống.';
                } else {
                    $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
                }
                $msgType = 'error';
                $action = 'edit';
                $_GET['id'] = $id;
            }
        } else {
            $message = '❌ ' . implode('. ', $errors);
            $msgType = 'error';
            $action = 'edit';
            $_GET['id'] = $id;
        }
    }

    // ── DELETE — Xóa người dùng ──
    elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === $_SESSION['user']['id']) {
            $message = '❌ Không thể tự xóa chính mình.';
            $msgType = 'error';
        } elseif ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = '✅ Đã xóa người dùng thành công!';
                $msgType = 'success';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'foreign key') || str_contains($e->getMessage(), 'RESTRICT')) {
                    $message = '❌ Không thể xóa: Người dùng này có đơn hàng hoặc dữ liệu liên quan.';
                } else {
                    $message = '❌ Lỗi xóa: ' . htmlspecialchars($e->getMessage());
                }
                $msgType = 'error';
            }
        }
        $action = 'list';
    }
}

// ══════════════════════════════════════════
// HIỂN THỊ THEO ACTION
// ══════════════════════════════════════════

// Flash message
if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
        <?= $message ?>
    </div>
<?php endif;

// ── ADD / EDIT FORM ──
if ($action === 'add' || $action === 'edit'):
    $user = null;
    if ($action === 'edit') {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            echo '<p class="text-red-400">Người dùng không tồn tại</p>';
            require_once __DIR__ . '/includes/admin_footer.php';
            exit;
        }
    }
?>
    <div class="max-w-4xl bg-admin-card rounded-2xl border border-admin-border p-6 md:p-8 shadow-xl">
        <div class="flex items-center justify-between mb-8 border-b border-admin-border pb-4">
            <h2 class="text-xl font-bold text-white"><?= $action === 'edit' ? 'Chỉnh sửa tài khoản: ' . htmlspecialchars($user['name']) : 'Thêm tài khoản mới' ?></h2>
            <a href="?action=list" class="text-sm font-medium text-gray-400 hover:text-white flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Quay lại
            </a>
        </div>

        <form action="/admin/users.php" method="POST" class="space-y-6">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Họ Tên <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $user['name'] ?? '') ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors" placeholder="Nguyễn Văn A">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Email <span class="text-red-400">*</span></label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors" placeholder="email@example.com">
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Mật khẩu <?= $action === 'add' ? '<span class="text-red-400">*</span>' : '<span class="text-xs text-gray-500 font-normal">(Để trống nếu không muốn đổi)</span>' ?></label>
                    <input type="password" name="password" <?= $action === 'add' ? 'required' : '' ?> class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors" placeholder="<?= $action === 'add' ? 'Mật khẩu' : 'Mật khẩu mới' ?>">
                </div>

                <!-- Role -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Phân quyền</label>
                    <select name="role" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors appearance-none">
                        <option value="customer" <?= ($user['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Khách hàng</option>
                        <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Quản trị viên (Admin)</option>
                    </select>
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Số điện thoại</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors" placeholder="09xxxxxxx">
                </div>

                <!-- Address -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Địa chỉ</label>
                    <textarea name="address" rows="3" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors" placeholder="Địa chỉ chi tiết..."><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-admin-border">
                <a href="?action=list" class="px-6 py-2.5 rounded-xl text-sm font-medium text-gray-300 bg-admin-bg border border-admin-border hover:bg-gray-800 transition-colors">Hủy</a>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-bb-dark bg-bb-yellow hover:bg-yellow-400 transition-colors">
                    <?= $action === 'edit' ? 'Lưu thay đổi' : 'Thêm tài khoản' ?>
                </button>
            </div>
        </form>
    </div>

<?php 
// ── LIST VIEW ──
else: 
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll();
?>
    <!-- Top actions -->
    <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-6">
        <h2 class="text-xl font-bold text-white">Danh sách tài khoản (<?= count($users) ?>)</h2>
        <a href="?action=add" class="bg-bb-blue hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-colors flex items-center gap-2 shadow-lg shadow-blue-500/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Thêm tài khoản
        </a>
    </div>

    <!-- Users Table -->
    <div class="bg-admin-card rounded-2xl border border-admin-border shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-admin-bg/50 border-b border-admin-border text-xs uppercase tracking-wider text-gray-400 font-semibold">
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Họ Tên</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Vai trò</th>
                        <th class="px-6 py-4">Ngày tạo</th>
                        <th class="px-6 py-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-admin-border text-sm">
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Chưa có người dùng nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-white/[0.02] transition-colors">
                                <td class="px-6 py-4 text-gray-400">#<?= $u['id'] ?></td>
                                <td class="px-6 py-4 font-medium text-white"><?= htmlspecialchars($u['name']) ?></td>
                                <td class="px-6 py-4 text-gray-300"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Admin</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">Khách hàng</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-400"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="?action=edit&id=<?= $u['id'] ?>" class="text-blue-400 hover:text-blue-300 transition-colors tooltip" title="Sửa">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <?php if ($u['id'] !== $_SESSION['user']['id']): ?>
                                            <form method="POST" action="/admin/users.php" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này? Thao tác không thể hoàn tác.');" class="inline-block">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="text-red-400 hover:text-red-300 transition-colors tooltip" title="Xóa">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-600 tooltip" title="Không thể tự xóa">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </span>
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
<?php endif;

require_once __DIR__ . '/includes/admin_footer.php';
