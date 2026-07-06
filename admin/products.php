<?php
/**
 * Admin Products — CRUD Sản phẩm
 * 
 * Actions:
 *  - (default) : Danh sách sản phẩm
 *  - ?action=add : Form thêm mới
 *  - ?action=edit&id=X : Form chỉnh sửa
 *  - POST action=create : Tạo sản phẩm mới
 *  - POST action=update : Cập nhật sản phẩm
 *  - POST action=delete : Xóa sản phẩm
 * 
 * Bảo mật:
 *  - htmlspecialchars() cho mọi output → chống XSS
 *  - PDO Prepared Statements cho mọi query → chống SQL Injection
 */

$adminPage  = 'products';
$adminTitle = 'Quản lý sản phẩm';

require_once __DIR__ . '/includes/admin_header.php';

$action  = $_GET['action'] ?? $_POST['action'] ?? 'list';
$message = '';
$msgType = 'info';

// ── Lấy danh mục cho dropdown ──
$categories = $pdo->query("SELECT id, name, icon FROM categories ORDER BY id")->fetchAll();

// ═══════════════════════════════════════
// XỬ LÝ POST ACTIONS
// ═══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CREATE — Thêm sản phẩm mới ──
    if ($action === 'create') {
        $name       = trim($_POST['name'] ?? '');
        $catId      = (int) ($_POST['category_id'] ?? 0);
        $slug       = trim($_POST['slug'] ?? '') ?: createSlug($name);
        $desc       = trim($_POST['description'] ?? '');
        $price      = (float) ($_POST['price'] ?? 0);
        $salePrice  = !empty($_POST['sale_price']) ? (float) $_POST['sale_price'] : null;
        $stock      = (int) ($_POST['stock'] ?? 0);
        $rating     = (float) ($_POST['rating'] ?? 0);
        $reviewCnt  = (int) ($_POST['review_count'] ?? 0);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $image      = trim($_POST['image'] ?? '');
        $specs      = trim($_POST['specs'] ?? '');

        // Validate
        $errors = [];
        if (empty($name)) $errors[] = 'Tên sản phẩm không được để trống';
        if ($catId <= 0) $errors[] = 'Vui lòng chọn danh mục';
        if ($price <= 0) $errors[] = 'Giá phải lớn hơn 0';

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO products (category_id, name, slug, description, specs, price, sale_price, image, stock, rating, review_count, is_featured)
                    VALUES (:cat, :name, :slug, :desc, :specs, :price, :sale, :img, :stock, :rating, :reviews, :featured)
                ");
                $stmt->execute([
                    ':cat' => $catId, ':name' => $name, ':slug' => $slug, ':desc' => $desc,
                    ':specs' => $specs, ':price' => $price, ':sale' => $salePrice,
                    ':img' => $image, ':stock' => $stock, ':rating' => $rating,
                    ':reviews' => $reviewCnt, ':featured' => $isFeatured,
                ]);
                $message = '✅ Đã thêm sản phẩm "' . htmlspecialchars($name) . '" thành công!';
                $msgType = 'success';
                $action = 'list'; // Quay lại danh sách
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    $message = '❌ Slug "' . htmlspecialchars($slug) . '" đã tồn tại. Vui lòng đổi tên.';
                } else {
                    $message = '❌ Lỗi: ' . htmlspecialchars($e->getMessage());
                }
                $msgType = 'error';
            }
        } else {
            $message = '❌ ' . implode('. ', $errors);
            $msgType = 'error';
        }
    }

    // ── UPDATE — Cập nhật sản phẩm ──
    elseif ($action === 'update') {
        $id         = (int) ($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $catId      = (int) ($_POST['category_id'] ?? 0);
        $slug       = trim($_POST['slug'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $price      = (float) ($_POST['price'] ?? 0);
        $salePrice  = !empty($_POST['sale_price']) ? (float) $_POST['sale_price'] : null;
        $stock      = (int) ($_POST['stock'] ?? 0);
        $rating     = (float) ($_POST['rating'] ?? 0);
        $reviewCnt  = (int) ($_POST['review_count'] ?? 0);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $image      = trim($_POST['image'] ?? '');
        $specs      = trim($_POST['specs'] ?? '');

        if ($id > 0 && !empty($name) && $price > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE products SET
                        category_id = :cat, name = :name, slug = :slug, description = :desc,
                        specs = :specs, price = :price, sale_price = :sale, image = :img,
                        stock = :stock, rating = :rating, review_count = :reviews, is_featured = :featured
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':cat' => $catId, ':name' => $name, ':slug' => $slug, ':desc' => $desc,
                    ':specs' => $specs, ':price' => $price, ':sale' => $salePrice,
                    ':img' => $image, ':stock' => $stock, ':rating' => $rating,
                    ':reviews' => $reviewCnt, ':featured' => $isFeatured, ':id' => $id,
                ]);
                $message = '✅ Đã cập nhật sản phẩm "' . htmlspecialchars($name) . '"!';
                $msgType = 'success';
                $action = 'list';
            } catch (PDOException $e) {
                $message = '❌ Lỗi cập nhật: ' . htmlspecialchars($e->getMessage());
                $msgType = 'error';
            }
        }
    }

    // ── DELETE — Xóa sản phẩm ──
    elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $message = '✅ Đã xóa sản phẩm thành công!';
                $msgType = 'success';
            } catch (PDOException $e) {
                // FK constraint — sản phẩm đã có trong đơn hàng
                if (str_contains($e->getMessage(), 'foreign key') || str_contains($e->getMessage(), 'RESTRICT')) {
                    $message = '❌ Không thể xóa: Sản phẩm đã có trong đơn hàng.';
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
    $product = null;
    if ($action === 'edit') {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();
        if (!$product) {
            echo '<p class="text-red-400">Sản phẩm không tồn tại.</p>';
            require_once __DIR__ . '/includes/admin_footer.php';
            exit;
        }
    }
    $isEdit = $product !== null;
    $formAction = $isEdit ? 'update' : 'create';
?>
    <div class="max-w-4xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="/admin/products.php" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <h2 class="text-xl font-bold text-white"><?= $isEdit ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm mới' ?></h2>
        </div>

        <form method="POST" action="/admin/products.php" class="space-y-6">
            <input type="hidden" name="action" value="<?= $formAction ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $product['id'] ?>">
            <?php endif; ?>

            <!-- Row 1: Name + Category -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Tên sản phẩm <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required
                           value="<?= htmlspecialchars($product['name'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none transition-colors"
                           placeholder="VD: iPhone 16 Pro Max 256GB">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Danh mục <span class="text-red-400">*</span></label>
                    <select name="category_id" required class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                        <option value="">Chọn danh mục</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Slug + Image -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Slug (URL)</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($product['slug'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none"
                           placeholder="Tự tạo từ tên nếu để trống">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Đường dẫn ảnh</label>
                    <input type="text" name="image" value="<?= htmlspecialchars($product['image'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none"
                           placeholder="assets/images/ten-file.png">
                </div>
            </div>

            <!-- Row 3: Prices + Stock -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Giá gốc ($) <span class="text-red-400">*</span></label>
                    <input type="number" name="price" step="0.01" min="0" required
                           value="<?= htmlspecialchars($product['price'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Giá KM ($)</label>
                    <input type="number" name="sale_price" step="0.01" min="0"
                           value="<?= htmlspecialchars($product['sale_price'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none"
                           placeholder="Để trống nếu không KM">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Tồn kho</label>
                    <input type="number" name="stock" min="0"
                           value="<?= htmlspecialchars($product['stock'] ?? '50') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Rating (0-5)</label>
                    <input type="number" name="rating" step="0.1" min="0" max="5"
                           value="<?= htmlspecialchars($product['rating'] ?? '0') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                </div>
            </div>

            <!-- Row 4: Description -->
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Mô tả sản phẩm</label>
                <textarea name="description" rows="4"
                          class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none resize-none"
                          placeholder="Mô tả chi tiết về sản phẩm..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>

            <!-- Row 5: Specs JSON -->
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Thông số kỹ thuật (JSON)</label>
                <textarea name="specs" rows="3"
                          class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none resize-none font-mono"
                          placeholder='{"screen": "6.9 inch", "chip": "A18 Pro", "ram": "8GB"}'><?= htmlspecialchars($product['specs'] ?? '') ?></textarea>
            </div>

            <!-- Row 6: Options -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">Số lượt đánh giá</label>
                    <input type="number" name="review_count" min="0"
                           value="<?= htmlspecialchars($product['review_count'] ?? '0') ?>"
                           class="w-full px-4 py-3 bg-admin-bg border border-admin-border rounded-xl text-white text-sm focus:border-bb-yellow outline-none">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-3 px-4 py-3 bg-admin-bg border border-admin-border rounded-xl cursor-pointer hover:border-bb-yellow transition-colors w-full">
                        <input type="checkbox" name="is_featured" value="1"
                               <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?>
                               class="w-5 h-5 text-bb-yellow rounded focus:ring-bb-yellow bg-admin-border border-admin-border">
                        <span class="text-sm text-gray-300">⭐ Sản phẩm nổi bật (hiện trang chủ)</span>
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-bb-yellow text-bb-dark font-bold px-8 py-3 rounded-xl hover:bg-yellow-300 transition-all active:scale-[0.98]">
                    <?= $isEdit ? '💾 Cập nhật sản phẩm' : '➕ Thêm sản phẩm' ?>
                </button>
                <a href="/admin/products.php" class="px-8 py-3 rounded-xl border border-admin-border text-gray-400 hover:text-white hover:border-gray-500 transition-all font-medium">
                    Hủy
                </a>
            </div>
        </form>
    </div>

<?php else:
// ── LIST — Danh sách sản phẩm ──
    $products = $pdo->query("
        SELECT p.*, c.name AS category_name, c.icon AS category_icon
        FROM products p
        JOIN categories c ON p.category_id = c.id
        ORDER BY p.id DESC
    ")->fetchAll();
?>
    <!-- Actions bar -->
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500"><?= count($products) ?> sản phẩm</p>
        <a href="/admin/products.php?action=add" class="inline-flex items-center gap-2 bg-bb-yellow text-bb-dark font-bold px-5 py-2.5 rounded-xl hover:bg-yellow-300 transition-all text-sm active:scale-[0.98]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Thêm sản phẩm
        </a>
    </div>

    <!-- Products table -->
    <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-admin-border text-left text-xs text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3">ID</th>
                        <th class="px-5 py-3">Sản phẩm</th>
                        <th class="px-5 py-3">Danh mục</th>
                        <th class="px-5 py-3 text-right">Giá</th>
                        <th class="px-5 py-3 text-center">Kho</th>
                        <th class="px-5 py-3 text-center">Nổi bật</th>
                        <th class="px-5 py-3 text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-admin-border">
                    <?php foreach ($products as $p):
                        $pImg = getProductImage($p['image'] ?? '');
                    ?>
                    <tr class="hover:bg-admin-bg/40 transition-colors">
                        <td class="px-5 py-3 text-gray-500 font-mono text-xs">#<?= $p['id'] ?></td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-admin-bg rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
                                    <?php if ($pImg): ?>
                                        <img src="<?= htmlspecialchars($pImg) ?>" alt="" class="w-full h-full object-contain p-1">
                                    <?php else: ?>
                                        <span><?= $p['category_icon'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-white font-medium truncate max-w-[200px]"><?= htmlspecialchars($p['name']) ?></p>
                                    <p class="text-xs text-gray-500">⭐ <?= $p['rating'] ?> (<?= $p['review_count'] ?>)</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-400"><?= $p['category_icon'] ?> <?= htmlspecialchars($p['category_name']) ?></td>
                        <td class="px-5 py-3 text-right">
                            <?php if ($p['sale_price']): ?>
                                <span class="text-bb-yellow font-bold"><?= formatPrice((float)$p['sale_price']) ?></span>
                                <br><span class="text-xs text-gray-500 line-through"><?= formatPrice((float)$p['price']) ?></span>
                            <?php else: ?>
                                <span class="text-white font-medium"><?= formatPrice((float)$p['price']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?= $p['stock'] > 10 ? 'bg-green-500/10 text-green-400' : ($p['stock'] > 0 ? 'bg-orange-500/10 text-orange-400' : 'bg-red-500/10 text-red-400') ?>">
                                <?= $p['stock'] ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center"><?= $p['is_featured'] ? '⭐' : '—' ?></td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="/product.php?slug=<?= htmlspecialchars($p['slug']) ?>" target="_blank"
                                   class="p-2 rounded-lg hover:bg-admin-bg text-gray-400 hover:text-blue-400 transition-colors" title="Xem">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                <a href="/admin/products.php?action=edit&id=<?= $p['id'] ?>"
                                   class="p-2 rounded-lg hover:bg-admin-bg text-gray-400 hover:text-yellow-400 transition-colors" title="Sửa">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form method="POST" action="/admin/products.php" class="inline"
                                      onsubmit="return confirmDelete('Xóa sản phẩm \'<?= htmlspecialchars(addslashes($p['name'])) ?>\'?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="p-2 rounded-lg hover:bg-admin-bg text-gray-400 hover:text-red-400 transition-colors" title="Xóa">
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
<?php endif; ?>

<?php
/**
 * Helper: Tạo slug từ tên sản phẩm
 * "iPhone 16 Pro Max" → "iphone-16-pro-max"
 */
function createSlug(string $str): string {
    $str = mb_strtolower($str);
    // Vietnamese character mapping
    $map = ['à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a','è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e','ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i','ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o','ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u','ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d'];
    $str = strtr($str, $map);
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

require_once __DIR__ . '/includes/admin_footer.php';
?>
