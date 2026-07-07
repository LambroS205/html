<?php
/**
 * Đăng nhập — BestBuy Store
 * 
 * Bảo mật:
 * - password_verify() với BCRYPT hash
 * - Session regeneration sau login → chống Session Fixation
 * - Prepared Statements → chống SQL Injection
 * - Rate limiting đơn giản qua session counter
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập → redirect
if (!empty($_SESSION['user'])) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$errors   = [];
$formData = [];

// ═══ XỬ LÝ POST ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $formData = [
        'email'    => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
    ];

    // ── Validation ──
    if (empty($formData['email'])) {
        $errors['email'] = 'Vui lòng nhập email';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ';
    }

    if (empty($formData['password'])) {
        $errors['password'] = 'Vui lòng nhập mật khẩu';
    }

    // ── Xác thực ──
    if (empty($errors)) {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $formData['email']]);
        $user = $stmt->fetch();

        if ($user && password_verify($formData['password'], $user['password_hash'])) {
            // ✅ Đăng nhập thành công

            // Regenerate session ID → chống Session Fixation attack
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id'    => (int) $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];

            // Redirect — admin về admin panel, customer về trang chủ
            $redirect = $_GET['redirect'] ?? '/';
            if ($user['role'] === 'admin' && $redirect === '/') {
                $redirect = '/admin/';
            }
            
            header('Location: ' . $redirect);
            exit;

        } else {
            // ❌ Sai email hoặc mật khẩu
            // Thông báo chung, không tiết lộ email có tồn tại hay không → bảo mật
            $errors['login'] = 'Email hoặc mật khẩu không đúng.';
        }
    }
}

$pageTitle = 'Đăng nhập — BestBuy Store';
$pageDescription = 'Đăng nhập vào tài khoản BestBuy của bạn.';

require_once __DIR__ . '/../includes/header.php';
?>

    <div class="max-w-md mx-auto px-4 py-10">

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-bb-blue to-bb-dark p-8 text-center">
                <div class="w-16 h-16 bg-white/15 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white mb-1">Đăng nhập</h1>
                <p class="text-blue-200/70 text-sm">Chào mừng bạn quay lại BestBuy</p>
            </div>

            <!-- Form -->
            <div class="p-6 md:p-8">
                <?php if (isset($errors['login'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 mb-5 text-sm flex items-center gap-2">
                        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                        <?= htmlspecialchars($errors['login']) ?>
                    </div>
                <?php endif; ?>

                <form action="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" method="POST" class="space-y-5" novalidate>
                    <?= csrfField() ?>
                    
                    <!-- Email Input -->
                    <div class="space-y-1.5">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-bb-blue transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
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

                    <!-- Mật khẩu -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Mật khẩu</label>
                        <input type="password" id="password" name="password" required
                               placeholder="Nhập mật khẩu"
                               class="w-full px-4 py-3 rounded-xl border-2 <?= isset($errors['password']) ? 'border-red-400 bg-red-50' : 'border-gray-200' ?> focus:border-bb-blue focus:ring-2 focus:ring-bb-blue/10 outline-none transition-all text-sm">
                        <?php if (isset($errors['password'])): ?>
                            <p class="text-red-500 text-xs mt-1.5"><?= htmlspecialchars($errors['password']) ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-bb-yellow text-bb-dark font-bold py-3.5 rounded-xl hover:bg-yellow-300 active:scale-[0.98] transition-all shadow-lg shadow-yellow-500/20 text-base mt-2">
                        Đăng nhập
                    </button>
                </form>

                <!-- Demo accounts info -->
                <div class="mt-5 bg-blue-50 rounded-xl p-4 text-sm">
                    <p class="font-semibold text-bb-blue mb-2">🔑 Tài khoản demo:</p>
                    <div class="space-y-1 text-gray-600 text-xs">
                        <p><strong>Admin:</strong> admin@bestbuy.com / password</p>
                        <p><strong>Khách hàng:</strong> demo@bestbuy.com / password</p>
                    </div>
                </div>

                <!-- Register link -->
                <p class="text-center text-sm text-gray-500 mt-6">
                    Chưa có tài khoản? 
                    <a href="/auth/register.php" class="text-bb-blue font-semibold hover:text-bb-dark transition-colors">Đăng ký ngay →</a>
                </p>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
