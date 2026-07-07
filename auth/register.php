<?php
/**
 * Đăng ký tài khoản — BestBuy Store
 * 
 * Bảo mật:
 * - Email unique check (DB level + PHP level)
 * - Password hash bằng password_hash() BCRYPT
 * - htmlspecialchars() cho tất cả output → chống XSS
 * - Prepared Statements → chống SQL Injection
 * - Validate: email format, password >= 6 ký tự, tên >= 2 ký tự
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập → redirect về trang chủ
if (!empty($_SESSION['user'])) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$errors   = [];
$formData = [];
$success  = false;

// ═══ XỬ LÝ POST ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $formData = [
        'name'     => trim($_POST['name'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'phone'    => trim($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
    ];

    // ── Validation ──
    // Tên
    if (empty($formData['name'])) {
        $errors['name'] = 'Vui lòng nhập họ tên';
    } elseif (mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 100) {
        $errors['name'] = 'Họ tên phải từ 2 đến 100 ký tự';
    }

    // Email
    if (empty($formData['email'])) {
        $errors['email'] = 'Vui lòng nhập email';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ';
    } else {
        // Kiểm tra email đã tồn tại chưa
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $formData['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Email này đã được đăng ký. Vui lòng dùng email khác hoặc đăng nhập.';
        }
    }

    // Mật khẩu
    if (empty($formData['password'])) {
        $errors['password'] = 'Vui lòng nhập mật khẩu';
    } elseif (mb_strlen($formData['password']) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
    }

    // Xác nhận mật khẩu
    if ($formData['password'] !== $formData['password_confirm']) {
        $errors['password_confirm'] = 'Mật khẩu xác nhận không khớp';
    }

    // ── Tạo tài khoản nếu không có lỗi ──
    if (empty($errors)) {
        try {
            $pdo = Database::getConnection();

            // BCRYPT hash — cost 10 (default, đủ bảo mật cho ứng dụng web)
            $passwordHash = password_hash($formData['password'], PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password_hash, role) 
                VALUES (:name, :email, :phone, :hash, 'customer')
            ");
            $stmt->execute([
                ':name'  => $formData['name'],
                ':email' => $formData['email'],
                ':phone' => $formData['phone'] ?: null,
                ':hash'  => $passwordHash,
            ]);

            $userId = (int) $pdo->lastInsertId();

            // Auto-login sau khi đăng ký
            $_SESSION['user'] = [
                'id'    => $userId,
                'name'  => $formData['name'],
                'email' => $formData['email'],
                'role'  => 'customer',
            ];

            // Redirect đến trang chủ
            header('Location: /?registered=1');
            exit;

        } catch (PDOException $e) {
            // Duplicate email (race condition — 2 user đăng ký cùng lúc)
            if ($e->getCode() == 23000) {
                $errors['email'] = 'Email này đã được đăng ký.';
            } else {
                $errors['system'] = 'Lỗi hệ thống. Vui lòng thử lại.';
            }
        }
    }
}

$pageTitle = 'Đăng ký tài khoản — BestBuy Store';
$pageDescription = 'Tạo tài khoản BestBuy để theo dõi đơn hàng và nhận ưu đãi.';

require_once __DIR__ . '/../includes/header.php';
?>

    <div class="max-w-md mx-auto px-4 py-10">

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-bb-blue to-bb-dark p-8 text-center">
                <div class="w-16 h-16 bg-white/15 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white mb-1">Tạo tài khoản</h1>
                <p class="text-blue-200/70 text-sm">Đăng ký để theo dõi đơn hàng và nhận ưu đãi</p>
            </div>

            <!-- Form -->
            <div class="p-6 md:p-8">
                <?php if (isset($errors['system'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-5 text-sm flex items-center gap-2">
                        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                        <?= htmlspecialchars($errors['system']) ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="space-y-5">
                    <?= csrfField() ?>
                    
                    <!-- Họ tên -->
                    <div class="space-y-1.5">
                        <label for="name" class="block text-sm font-medium text-gray-700">Họ và tên</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-bb-blue transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <input type="text" name="name" id="name" 
                                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border <?= isset($errors['name']) ? 'border-red-400 focus:ring-red-500' : 'border-gray-200 focus:ring-bb-blue' ?> rounded-xl text-sm outline-none focus:ring-2 focus:border-transparent transition-all" 
                                   placeholder="Nguyễn Văn A"
                                   value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                                   required>
                        </div>
                        <?php if (isset($errors['name'])): ?>
                            <p class="text-xs text-red-500 font-medium ml-1 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= htmlspecialchars($errors['name']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div class="space-y-1.5">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-bb-blue transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <input type="email" name="email" id="email" 
                                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border <?= isset($errors['email']) ? 'border-red-400 focus:ring-red-500' : 'border-gray-200 focus:ring-bb-blue' ?> rounded-xl text-sm outline-none focus:ring-2 focus:border-transparent transition-all" 
                                   placeholder="you@example.com"
                                   value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                   required>
                        </div>
                        <?php if (isset($errors['email'])): ?>
                            <p class="text-xs text-red-500 font-medium ml-1 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <?= htmlspecialchars($errors['email']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Phone -->
                    <div class="space-y-1.5">
                        <label for="phone" class="block text-sm font-medium text-gray-700">Số điện thoại <span class="text-gray-400 font-normal">(Tùy chọn)</span></label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-bb-blue transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <input type="tel" name="phone" id="phone" 
                                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 focus:ring-bb-blue rounded-xl text-sm outline-none focus:ring-2 focus:border-transparent transition-all" 
                                   placeholder="0912 345 678"
                                   value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Mật khẩu -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Mật khẩu <span class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password" required minlength="6"
                               placeholder="Tối thiểu 6 ký tự"
                               class="w-full px-4 py-3 rounded-xl border-2 <?= isset($errors['password']) ? 'border-red-400 bg-red-50' : 'border-gray-200' ?> focus:border-bb-blue focus:ring-2 focus:ring-bb-blue/10 outline-none transition-all text-sm">
                        <?php if (isset($errors['password'])): ?>
                            <p class="text-red-500 text-xs mt-1.5"><?= htmlspecialchars($errors['password']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Xác nhận mật khẩu -->
                    <div>
                        <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1.5">Xác nhận mật khẩu <span class="text-red-500">*</span></label>
                        <input type="password" id="password_confirm" name="password_confirm" required
                               placeholder="Nhập lại mật khẩu"
                               class="w-full px-4 py-3 rounded-xl border-2 <?= isset($errors['password_confirm']) ? 'border-red-400 bg-red-50' : 'border-gray-200' ?> focus:border-bb-blue focus:ring-2 focus:ring-bb-blue/10 outline-none transition-all text-sm">
                        <?php if (isset($errors['password_confirm'])): ?>
                            <p class="text-red-500 text-xs mt-1.5"><?= htmlspecialchars($errors['password_confirm']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-bb-yellow text-bb-dark font-bold py-3.5 rounded-xl hover:bg-yellow-300 active:scale-[0.98] transition-all shadow-lg shadow-yellow-500/20 text-base mt-2">
                        Tạo tài khoản
                    </button>
                </form>

                <!-- Login link -->
                <p class="text-center text-sm text-gray-500 mt-6">
                    Đã có tài khoản? 
                    <a href="/auth/login.php" class="text-bb-blue font-semibold hover:text-bb-dark transition-colors">Đăng nhập →</a>
                </p>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
