<?php
/**
 * Trang Chi tiết Sản phẩm - BestBuy Store
 * URL: /product.php?slug=ten-san-pham
 * 
 * Hiển thị: Ảnh lớn, thông số kỹ thuật, giá, đánh giá, bảo hành, tồn kho,
 *           nút thêm giỏ hàng, sản phẩm liên quan
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = Database::getConnection();

// ── Lấy slug từ URL ──
$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: /');
    exit;
}

// ── Query sản phẩm ──
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.slug = :slug
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy sản phẩm — BestBuy';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="max-w-7xl mx-auto px-4 py-20 text-center">
            <span class="text-6xl mb-4 block">😕</span>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Không tìm thấy sản phẩm</h1>
            <p class="text-gray-400 mb-6">Sản phẩm bạn tìm kiếm không tồn tại hoặc đã bị xóa.</p>
            <a href="/" class="inline-flex items-center gap-2 bg-bb-blue text-white font-semibold px-6 py-3 rounded-full hover:bg-bb-dark transition-colors">← Về trang chủ</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ── Parse specs JSON ──
$specs = [];
if (!empty($product['specs'])) {
    $decoded = json_decode($product['specs'], true);
    if (is_array($decoded)) {
        $specs = $decoded;
    }
}

// ── Tính giá ──
$price     = (float) $product['price'];
$salePrice = $product['sale_price'] ? (float) $product['sale_price'] : null;
$displayPrice = $salePrice ?? $price;
$savings   = $salePrice ? ($price - $salePrice) : 0;
$discountPct = $salePrice ? calcDiscount($price, $salePrice) : 0;

// ── Sản phẩm liên quan (cùng danh mục, khác ID) ──
$relatedStmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = :cat_id AND p.id != :product_id
    ORDER BY p.rating DESC
    LIMIT 4
");
$relatedStmt->execute([':cat_id' => $product['category_id'], ':product_id' => $product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

// ── Page meta ──
$pageTitle = htmlspecialchars($product['name']) . ' — BestBuy Store';
$pageDescription = mb_substr(strip_tags($product['description'] ?? ''), 0, 160);
$activeCategory = $product['category_slug'];

// ── Spec labels tiếng Việt ──
$specLabels = [
    'screen' => 'Màn hình', 'chip' => 'Vi xử lý', 'ram' => 'RAM',
    'storage' => 'Bộ nhớ', 'camera' => 'Camera', 'battery' => 'Pin',
    'os' => 'Hệ điều hành', 'weight' => 'Trọng lượng', 'gpu' => 'Card đồ họa',
    'processor' => 'Bộ xử lý', 'hdr' => 'HDR', 'audio' => 'Âm thanh',
    'smart_tv' => 'Smart TV', 'hdmi' => 'HDMI', 'refresh_rate' => 'Tần số quét',
    'type' => 'Loại', 'driver' => 'Driver', 'anc' => 'Chống ồn',
    'codec' => 'Codec', 'connectivity' => 'Kết nối', 'features' => 'Tính năng',
];

require_once __DIR__ . '/includes/header.php';

$image = getProductImage($product['image'] ?? '');
?>

    <div class="max-w-7xl mx-auto px-4 py-6">

        <!-- ═══ BREADCRUMB ═══ -->
        <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6 flex-wrap">
            <a href="/" class="hover:text-bb-blue transition-colors">Trang chủ</a>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <a href="/search.php?category=<?= htmlspecialchars($product['category_slug']) ?>" class="hover:text-bb-blue transition-colors">
                <?= $product['category_icon'] ?> <?= htmlspecialchars($product['category_name']) ?>
            </a>
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-600 font-medium line-clamp-1"><?= htmlspecialchars($product['name']) ?></span>
        </nav>

        <!-- ═══ PRODUCT DETAIL — 2 Column Layout ═══ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 mb-12">

            <!-- ── LEFT: Product Image ── -->
            <div class="space-y-4">
                <div class="relative bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden group">
                    <?php if ($discountPct > 0): ?>
                        <span class="absolute top-4 left-4 bg-red-500 text-white text-sm font-bold px-3 py-1.5 rounded-lg shadow-lg z-10">
                            -<?= $discountPct ?>%
                        </span>
                    <?php endif; ?>

                    <div class="aspect-square flex items-center justify-center p-8 md:p-12 cursor-zoom-in overflow-hidden" id="product-image-container">
                        <?php if ($image): ?>
                            <img src="<?= htmlspecialchars($image) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="max-w-full max-h-full object-contain transition-transform duration-500 group-hover:scale-125"
                                 id="product-main-image">
                        <?php else: ?>
                            <span class="text-[120px] opacity-50"><?= $product['category_icon'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Trust badges below image -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-white rounded-xl p-3 text-center border border-gray-100">
                        <span class="text-xl mb-1 block">🛡️</span>
                        <p class="text-xs font-medium text-gray-600">Bảo hành<br>chính hãng</p>
                    </div>
                    <div class="bg-white rounded-xl p-3 text-center border border-gray-100">
                        <span class="text-xl mb-1 block">🔄</span>
                        <p class="text-xs font-medium text-gray-600">Đổi trả<br>30 ngày</p>
                    </div>
                    <div class="bg-white rounded-xl p-3 text-center border border-gray-100">
                        <span class="text-xl mb-1 block">🚚</span>
                        <p class="text-xs font-medium text-gray-600">Miễn phí ship<br>từ 35 VNĐ</p>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Product Details ── -->
            <div class="space-y-5">
                <!-- Category badge -->
                <a href="/search.php?category=<?= htmlspecialchars($product['category_slug']) ?>" 
                   class="inline-flex items-center gap-1.5 text-sm text-bb-blue bg-blue-50 px-3 py-1 rounded-full hover:bg-blue-100 transition-colors">
                    <?= $product['category_icon'] ?> <?= htmlspecialchars($product['category_name']) ?>
                </a>

                <!-- Product Name -->
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <!-- Rating -->
                <div class="flex items-center gap-3">
                    <?= renderStars((float) $product['rating'], (int) $product['review_count']) ?>
                    <span class="text-sm text-gray-400">|</span>
                    <span class="text-sm text-gray-400">Mã SP: <span class="text-gray-600 font-medium">BB-<?= str_pad($product['id'], 5, '0', STR_PAD_LEFT) ?></span></span>
                </div>

                <!-- Price Section -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-5 border border-blue-100">
                    <div class="flex items-baseline gap-3 mb-1">
                        <span class="text-3xl md:text-4xl font-black text-bb-blue"><?= formatPrice($displayPrice) ?></span>
                        <?php if ($salePrice): ?>
                            <span class="text-lg text-gray-400 line-through"><?= formatPrice($price) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($savings > 0): ?>
                        <p class="text-sm font-semibold text-green-600 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>
                            Tiết kiệm <?= formatPrice($savings) ?> (<?= $discountPct ?>%)
                        </p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-400 mt-2">Đã bao gồm thuế. Phí vận chuyển tính khi thanh toán.</p>
                </div>

                <!-- Stock Status -->
                <div class="flex items-center gap-2">
                    <?php if ($product['stock'] > 10): ?>
                        <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-sm font-medium text-green-600">Còn hàng</span>
                    <?php elseif ($product['stock'] > 0): ?>
                        <span class="w-2.5 h-2.5 bg-orange-500 rounded-full animate-pulse"></span>
                        <span class="text-sm font-medium text-orange-600">Chỉ còn <?= $product['stock'] ?> sản phẩm — Nhanh tay!</span>
                    <?php else: ?>
                        <span class="w-2.5 h-2.5 bg-red-500 rounded-full"></span>
                        <span class="text-sm font-medium text-red-600">Hết hàng</span>
                    <?php endif; ?>
                </div>

                <!-- Quantity + Add to Cart -->
                <?php if ($product['stock'] > 0): ?>
                <div class="flex flex-col sm:flex-row gap-3">
                    <!-- Quantity Selector -->
                    <div class="flex items-center border-2 border-gray-200 rounded-xl overflow-hidden bg-white">
                        <button type="button" onclick="changeQty(-1)" class="px-4 py-3 text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition-colors text-lg font-bold">−</button>
                        <input type="number" id="product-qty" value="1" min="1" max="<?= $product['stock'] ?>" 
                               class="w-16 text-center text-lg font-semibold border-x-2 border-gray-200 py-3 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                        <button type="button" onclick="changeQty(1)" class="px-4 py-3 text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition-colors text-lg font-bold">+</button>
                    </div>

                    <!-- Add to Cart Button -->
                    <button id="add-to-cart-btn" onclick="addToCartDetail(<?= $product['id'] ?>)" 
                            class="flex-1 bg-bb-yellow text-bb-dark font-bold py-3.5 px-8 rounded-xl hover:bg-yellow-300 active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-2 shadow-lg shadow-yellow-500/20 text-base">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
                        Thêm vào giỏ hàng
                    </button>
                </div>
                <?php else: ?>
                <div class="bg-gray-100 rounded-xl p-4 text-center">
                    <p class="text-gray-500 font-medium">Sản phẩm tạm hết hàng</p>
                    <p class="text-sm text-gray-400 mt-1">Vui lòng quay lại sau hoặc xem sản phẩm tương tự bên dưới.</p>
                </div>
                <?php endif; ?>

                <!-- Buy Now -->
                <?php if ($product['stock'] > 0): ?>
                <a href="/checkout.php" onclick="addToCartDetail(<?= $product['id'] ?>); return true;" 
                   class="block w-full text-center bg-bb-blue text-white font-bold py-3.5 px-8 rounded-xl hover:bg-bb-dark transition-colors">
                    Mua ngay
                </a>
                <?php endif; ?>

                <!-- Description -->
                <?php if (!empty($product['description'])): ?>
                <div class="border-t border-gray-100 pt-5">
                    <h3 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Mô tả sản phẩm
                    </h3>
                    <p class="text-gray-600 leading-relaxed text-sm"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ TECHNICAL SPECS TABLE ═══ -->
        <?php if (!empty($specs)): ?>
        <section class="mb-12">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-bb-blue to-bb-dark">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Thông số kỹ thuật
                    </h2>
                </div>
                <div class="divide-y divide-gray-50">
                    <?php $i = 0; foreach ($specs as $key => $value): $i++; ?>
                        <div class="flex flex-col sm:flex-row <?= $i % 2 === 0 ? 'bg-gray-50/50' : '' ?>">
                            <div class="sm:w-1/3 px-6 py-3.5 font-medium text-gray-500 text-sm">
                                <?= htmlspecialchars($specLabels[$key] ?? ucfirst(str_replace('_', ' ', $key))) ?>
                            </div>
                            <div class="sm:w-2/3 px-6 py-3.5 text-gray-800 text-sm font-medium">
                                <?= htmlspecialchars($value) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ═══ WARRANTY & POLICY ═══ -->
        <section class="mb-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-2xl">🛡️</div>
                        <h3 class="font-bold text-gray-800">Bảo hành chính hãng</h3>
                    </div>
                    <p class="text-sm text-gray-500 leading-relaxed">Sản phẩm được bảo hành chính hãng 12-24 tháng tại các trung tâm ủy quyền trên toàn quốc. Hỗ trợ 1 đổi 1 trong 30 ngày đầu nếu lỗi nhà sản xuất.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-2xl">📦</div>
                        <h3 class="font-bold text-gray-800">Giao hàng nhanh</h3>
                    </div>
                    <p class="text-sm text-gray-500 leading-relaxed">Giao hàng trong 1-3 ngày làm việc. Miễn phí vận chuyển cho đơn hàng từ 35 VNĐ. Đóng gói cẩn thận, bảo hiểm hàng hóa trong quá trình vận chuyển.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center text-2xl">💳</div>
                        <h3 class="font-bold text-gray-800">Thanh toán linh hoạt</h3>
                    </div>
                    <p class="text-sm text-gray-500 leading-relaxed">Hỗ trợ thanh toán COD (trả tiền khi nhận hàng) và thẻ quốc tế Visa/Mastercard. Giao dịch được mã hóa an toàn tuyệt đối.</p>
                </div>
            </div>
        </section>

        <!-- ═══ RELATED PRODUCTS ═══ -->
        <?php if (!empty($relatedProducts)): ?>
        <section class="mb-12">
            <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                Sản phẩm liên quan
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                <?php foreach ($relatedProducts as $rp): ?>
                    <?= renderProductCard($rp) ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <!-- Product Detail JS -->
    <script>
    /**
     * Thay đổi số lượng sản phẩm
     */
    function changeQty(delta) {
        const input = document.getElementById('product-qty');
        const min = parseInt(input.min) || 1;
        const max = parseInt(input.max) || 99;
        let val = parseInt(input.value) || 1;
        val = Math.max(min, Math.min(max, val + delta));
        input.value = val;
    }

    // Validate manual input
    document.getElementById('product-qty')?.addEventListener('change', function() {
        const min = parseInt(this.min) || 1;
        const max = parseInt(this.max) || 99;
        let val = parseInt(this.value) || 1;
        this.value = Math.max(min, Math.min(max, val));
    });

    /**
     * Thêm vào giỏ với số lượng tùy chọn (từ trang chi tiết)
     */
    async function addToCartDetail(productId) {
        const qty = parseInt(document.getElementById('product-qty')?.value) || 1;
        const btn = document.getElementById('add-to-cart-btn');
        if (!btn) return;

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Đang thêm...`;

        try {
            const resp = await fetch('/cart_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', product_id: productId, quantity: qty })
            });
            const data = await resp.json();

            if (data.success) {
                updateCartBadge(data.cartCount);
                btn.innerHTML = `
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Đã thêm thành công!`;
                btn.classList.remove('bg-bb-yellow', 'hover:bg-yellow-300', 'shadow-yellow-500/20');
                btn.classList.add('bg-green-500', 'text-white', 'shadow-green-500/20');
                if (typeof openCartDrawer === 'function') {
                    openCartDrawer();
                } else {
                    showToast('✓ Đã thêm ' + qty + ' sản phẩm vào giỏ hàng!', 'success');
                }

                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    btn.classList.remove('bg-green-500', 'text-white', 'shadow-green-500/20');
                    btn.classList.add('bg-bb-yellow', 'hover:bg-yellow-300', 'shadow-yellow-500/20');
                }, 2000);
            } else {
                throw new Error(data.message || 'Lỗi');
            }
        } catch (err) {
            showToast('✕ ' + err.message, 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
