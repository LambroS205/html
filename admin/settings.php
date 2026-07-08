<?php
/**
 * Admin Settings — Cài đặt giao diện trang chủ
 */

$adminPage  = 'settings';
$adminTitle = 'Cài đặt giao diện';

require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$msgType = 'info';

// Xử lý cập nhật settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = :value WHERE setting_key = :key");
        
        $fields = [
            'show_home_hero',
            'home_hero_badge',
            'home_hero_title',
            'home_hero_desc',
            'home_hero_button_text',
            'home_hero_button_link',
            'show_home_promo',
            'home_promo_title',
            'home_promo_desc',
            'home_promo_button_text',
            'home_promo_button_link',
            'header_promo_text',
            'hotline_number',
            'footer_description',
            'footer_copyright',
            'show_home_categories',
            'show_home_trust'
        ];
        
        $pdo->beginTransaction();
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $stmt->execute([
                    ':value' => $_POST[$field],
                    ':key' => $field
                ]);
            }
        }
        
        $pdo->commit();
        $message = '✅ Đã cập nhật cài đặt giao diện thành công!';
        $msgType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '❌ Lỗi khi cập nhật cài đặt: ' . htmlspecialchars($e->getMessage());
        $msgType = 'error';
    }
}

// Lấy danh sách settings hiện tại
$settingsList = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// Hàm helper để lấy giá trị
function getSetting($key, $settingsList) {
    return $settingsList[$key] ?? '';
}
?>

<!-- Thông báo -->
<?php if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'success' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20' ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="max-w-4xl bg-admin-card rounded-2xl border border-admin-border p-6 md:p-8 shadow-xl">
    <div class="mb-8 border-b border-admin-border pb-4">
        <h2 class="text-xl font-bold text-white">Chỉnh sửa nội dung trang chủ</h2>
        <p class="text-sm text-gray-400 mt-2">Cho phép bạn thay đổi các đoạn văn bản, tiêu đề, nút bấm hiển thị ở trang chủ.</p>
    </div>

    <form action="/admin/settings.php" method="POST" class="space-y-8">
        <input type="hidden" name="action" value="update_settings">
        
        <!-- Khối 1: Hero Banner -->
        <div>
            <div class="flex items-center justify-between mb-4 border-b border-admin-border/50 pb-2">
                <h3 class="text-lg font-semibold text-bb-yellow">1. Hero Banner (Phần đầu trang)</h3>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="show_home_hero" value="0">
                    <input type="checkbox" name="show_home_hero" value="1" <?= getSetting('show_home_hero', $settingsList) == '1' ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bb-yellow"></div>
                    <span class="ml-3 text-sm font-medium text-gray-300">Hiển thị</span>
                </label>
            </div>
            <div class="grid grid-cols-1 gap-6">
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Badge (Chữ nổi bật nhỏ)</label>
                    <input type="text" name="home_hero_badge" value="<?= htmlspecialchars(getSetting('home_hero_badge', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Tiêu đề chính (Có thể dùng HTML như &lt;span&gt;, &lt;br&gt;)</label>
                    <textarea name="home_hero_title" rows="4" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors font-mono"><?= htmlspecialchars(getSetting('home_hero_title', $settingsList)) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Đoạn mô tả ngắn</label>
                    <textarea name="home_hero_desc" rows="3" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors"><?= htmlspecialchars(getSetting('home_hero_desc', $settingsList)) ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Text nút bấm</label>
                        <input type="text" name="home_hero_button_text" value="<?= htmlspecialchars(getSetting('home_hero_button_text', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Link nút bấm</label>
                        <input type="text" name="home_hero_button_link" value="<?= htmlspecialchars(getSetting('home_hero_button_link', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                    </div>
                </div>

            </div>
        </div>

        <!-- Khối 2: Promo Banner -->
        <div>
            <div class="flex items-center justify-between mb-4 border-b border-admin-border/50 pb-2">
                <h3 class="text-lg font-semibold text-bb-yellow">2. Khối Khuyến mãi (Promo Banner)</h3>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="show_home_promo" value="0">
                    <input type="checkbox" name="show_home_promo" value="1" <?= getSetting('show_home_promo', $settingsList) == '1' ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bb-yellow"></div>
                    <span class="ml-3 text-sm font-medium text-gray-300">Hiển thị</span>
                </label>
            </div>
            <div class="grid grid-cols-1 gap-6">
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Tiêu đề Promo (Có thể dùng HTML)</label>
                    <textarea name="home_promo_title" rows="2" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors font-mono"><?= htmlspecialchars(getSetting('home_promo_title', $settingsList)) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Đoạn mô tả ngắn Promo</label>
                    <textarea name="home_promo_desc" rows="2" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors"><?= htmlspecialchars(getSetting('home_promo_desc', $settingsList)) ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Text nút Promo</label>
                        <input type="text" name="home_promo_button_text" value="<?= htmlspecialchars(getSetting('home_promo_button_text', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Link nút Promo</label>
                        <input type="text" name="home_promo_button_link" value="<?= htmlspecialchars(getSetting('home_promo_button_link', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                    </div>
                </div>

            </div>
        </div>

        <!-- Khối 3: Cài đặt chung -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-bb-yellow mb-4 border-b border-admin-border/50 pb-2">3. Cài đặt chung (Header & Footer)</h3>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Thông báo Top Bar (Header)</label>
                    <input type="text" name="header_promo_text" value="<?= htmlspecialchars(getSetting('header_promo_text', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Số Hotline (Dùng chung cho cả trang)</label>
                    <input type="text" name="hotline_number" value="<?= htmlspecialchars(getSetting('hotline_number', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Mô tả ngắn ở Footer</label>
                    <textarea name="footer_description" rows="2" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors"><?= htmlspecialchars(getSetting('footer_description', $settingsList)) ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Dòng Copyright ở cuối trang (Có thể dùng HTML)</label>
                    <input type="text" name="footer_copyright" value="<?= htmlspecialchars(getSetting('footer_copyright', $settingsList)) ?>" class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow focus:ring-1 focus:ring-bb-yellow outline-none transition-colors">
                </div>
            </div>
        </div>

        <!-- Khối 4: Bật/Tắt các khối khác trên trang chủ -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-bb-yellow mb-4 border-b border-admin-border/50 pb-2">4. Các khối khác trên trang chủ</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Danh mục nổi bật -->
                <div class="flex items-center justify-between p-4 bg-admin-bg rounded-xl border border-admin-border">
                    <div>
                        <p class="text-white font-medium">Danh mục nổi bật (Category Showcase)</p>
                        <p class="text-sm text-gray-500">Hiển thị các icon danh mục ngay dưới banner</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="show_home_categories" value="0">
                        <input type="checkbox" name="show_home_categories" value="1" <?= getSetting('show_home_categories', $settingsList, '1') == '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bb-yellow"></div>
                    </label>
                </div>

                <!-- Tại sao chọn chúng tôi -->
                <div class="flex items-center justify-between p-4 bg-admin-bg rounded-xl border border-admin-border">
                    <div>
                        <p class="text-white font-medium">Cam kết (Why Choose Us)</p>
                        <p class="text-sm text-gray-500">Hiển thị khối cam kết vận chuyển, đổi trả...</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="show_home_trust" value="0">
                        <input type="checkbox" name="show_home_trust" value="1" <?= getSetting('show_home_trust', $settingsList, '1') == '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bb-yellow"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-3 pt-6 border-t border-admin-border mt-8">
            <button type="reset" class="px-6 py-2.5 rounded-xl text-sm font-medium text-gray-300 bg-admin-bg border border-admin-border hover:bg-gray-800 transition-colors">Đặt lại</button>
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-bb-dark bg-bb-yellow hover:bg-yellow-400 transition-colors shadow-lg">Lưu thay đổi</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
