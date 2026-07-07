<?php
/**
 * Trang Chi tiết Sản phẩm - BestBuy Store
 * URL: /product.php?slug=ten-san-pham
 * 
 * Hiển thị: Ảnh lớn, thông số kỹ thuật, giá, đánh giá, bảo hành, tồn kho,
 *           nút thêm giỏ hàng, sản phẩm liên quan
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
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
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as real_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as real_review_count
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

// ── Fetch Variants & Attributes ──
$variantsStmt = $pdo->prepare("
    SELECT pv.*, 
           GROUP_CONCAT(av.id ORDER BY a.id ASC) as attribute_value_ids
    FROM product_variants pv
    LEFT JOIN variant_attribute_values vav ON pv.id = vav.variant_id
    LEFT JOIN attribute_values av ON vav.attribute_value_id = av.id
    LEFT JOIN attributes a ON av.attribute_id = a.id
    WHERE pv.product_id = :product_id
    GROUP BY pv.id
    ORDER BY pv.price ASC
");
$variantsStmt->execute([':product_id' => $product['id']]);
$variants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);

$attrsStmt = $pdo->prepare("
    SELECT a.id as attribute_id, a.name as attribute_name, av.id as value_id, av.value 
    FROM attributes a
    JOIN attribute_values av ON a.id = av.attribute_id
    JOIN variant_attribute_values vav ON av.id = vav.attribute_value_id
    JOIN product_variants pv ON vav.variant_id = pv.id
    WHERE pv.product_id = :product_id
    GROUP BY a.id, av.id
    ORDER BY a.id ASC, av.id ASC
");
$attrsStmt->execute([':product_id' => $product['id']]);
$attributesRaw = $attrsStmt->fetchAll(PDO::FETCH_ASSOC);

$attributes = [];
foreach ($attributesRaw as $row) {
    if (!isset($attributes[$row['attribute_id']])) {
        $attributes[$row['attribute_id']] = [
            'name' => $row['attribute_name'],
            'values' => []
        ];
    }
    $attributes[$row['attribute_id']]['values'][$row['value_id']] = $row['value'];
}

// Lấy variant mặc định (variant đầu tiên)
$defaultVariant = $variants[0] ?? null;
$price = $defaultVariant ? (float) $defaultVariant['price'] : 0;
$salePrice = ($defaultVariant && $defaultVariant['sale_price']) ? (float) $defaultVariant['sale_price'] : null;
$displayPrice = $salePrice ?? $price;
$savings = $salePrice ? ($price - $salePrice) : 0;
$discountPct = $salePrice ? calcDiscount($price, $salePrice) : 0;
$stock = $defaultVariant ? (int) $defaultVariant['stock'] : 0;
$productImage = $defaultVariant && !empty($defaultVariant['image_url']) ? $defaultVariant['image_url'] : null;

// ── Sản phẩm liên quan (cùng danh mục, khác ID) ──
$relatedStmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           MIN(pv.price) as price, MIN(pv.sale_price) as sale_price, SUM(pv.stock) as stock,
           (SELECT image_url FROM product_variants WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as image,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as real_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as real_review_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE p.category_id = :cat_id AND p.id != :product_id
    GROUP BY p.id
    ORDER BY real_rating DESC
    LIMIT 4
");
$relatedStmt->execute([':cat_id' => $product['category_id'], ':product_id' => $product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

// ── Đánh giá sản phẩm (Reviews) ──
$reviewsStmt = $pdo->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = :product_id 
    ORDER BY r.created_at DESC
");
$reviewsStmt->execute([':product_id' => $product['id']]);
$reviews = $reviewsStmt->fetchAll();

// Kiểm tra quyền đánh giá (đã đăng nhập và chưa đánh giá)
$canReview = false;
$hasReviewed = false;
if (!empty($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id'];
    $checkReviewed = $pdo->prepare("SELECT id FROM reviews WHERE product_id = :product_id AND user_id = :user_id");
    $checkReviewed->execute([':product_id' => $product['id'], ':user_id' => $userId]);
    if ($checkReviewed->fetch()) {
        $hasReviewed = true;
    } else {
        $canReview = true;
    }
}

// ── Kiểm tra Wishlist ──
$isWished = false;
if (!empty($_SESSION['user']['id'])) {
    $wishCheck = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = :u AND product_id = :p");
    $wishCheck->execute([':u' => $_SESSION['user']['id'], ':p' => $product['id']]);
    if ($wishCheck->fetch()) {
        $isWished = true;
    }
}

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
            <a href="/danh-muc/<?= htmlspecialchars($product['category_slug']) ?>" class="hover:text-bb-blue transition-colors">
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
                        <?php if ($productImage): ?>
                            <img src="<?= htmlspecialchars('/' . ltrim($productImage, '/')) ?>" 
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
                        <p class="text-xs font-medium text-gray-600">Miễn phí ship<br>từ 875.000 VNĐ</p>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Product Details ── -->
            <div class="space-y-5">
                <!-- Category badge -->
                <a href="/danh-muc/<?= htmlspecialchars($product['category_slug']) ?>" 
                   class="inline-flex items-center gap-1.5 text-sm text-bb-blue bg-blue-50 px-3 py-1 rounded-full hover:bg-blue-100 transition-colors">
                    <?= $product['category_icon'] ?> <?= htmlspecialchars($product['category_name']) ?>
                </a>

                <!-- Product Name -->
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <!-- Rating -->
                <div class="flex items-center gap-3">
                    <?= renderStars(isset($product['real_rating']) ? (float) $product['real_rating'] : (float) $product['rating'], isset($product['real_review_count']) ? (int) $product['real_review_count'] : (int) $product['review_count']) ?>
                    <span class="text-sm text-gray-400">|</span>
                    <span class="text-sm text-gray-400">Mã SP: <span class="text-gray-600 font-medium">BB-<?= str_pad($product['id'], 5, '0', STR_PAD_LEFT) ?></span></span>
                </div>

                <!-- Price Section -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-5 border border-blue-100">
                    <div class="flex items-baseline gap-3 mb-1">
                        <span id="display-price" class="text-3xl md:text-4xl font-black text-bb-blue"><?= formatPrice($displayPrice) ?></span>
                        <span id="original-price" class="text-lg text-gray-400 line-through <?= $salePrice ? '' : 'hidden' ?>"><?= formatPrice($price) ?></span>
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
                <div id="stock-status" class="flex items-center gap-2">
                    <!-- Updated by JS -->
                </div>

                <!-- Attributes Selection -->
                <?php if (!empty($attributes)): ?>
                    <div class="space-y-4 py-2 border-t border-b border-gray-100 mt-4 mb-4">
                        <?php foreach ($attributes as $attrId => $attr): ?>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800 mb-2"><?= htmlspecialchars($attr['name']) ?></h3>
                                <div class="flex flex-wrap gap-2 attribute-group" data-attr-id="<?= $attrId ?>">
                                    <?php foreach ($attr['values'] as $valId => $valStr): ?>
                                        <button type="button" class="variant-btn border-2 border-gray-200 bg-white rounded-lg px-4 py-2 text-sm font-medium hover:border-bb-blue transition-colors focus:outline-none" data-val-id="<?= $valId ?>">
                                            <?= htmlspecialchars($valStr) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Quantity + Add to Cart -->
                <div id="add-to-cart-section" class="flex flex-col sm:flex-row gap-3 hidden">
                    <!-- Quantity Selector -->
                    <div class="flex items-center border-2 border-gray-200 rounded-xl overflow-hidden bg-white">
                        <button type="button" onclick="changeQty(-1)" class="px-4 py-3 text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition-colors text-lg font-bold">−</button>
                        <input type="number" id="product-qty" value="1" min="1" max="1" 
                               class="w-16 text-center text-lg font-semibold border-x-2 border-gray-200 py-3 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                        <button type="button" onclick="changeQty(1)" class="px-4 py-3 text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition-colors text-lg font-bold">+</button>
                    </div>

                    <!-- Add to Cart Button -->
                    <button id="add-to-cart-btn" onclick="addToCartVariant()" 
                            class="flex-1 bg-bb-yellow text-bb-dark font-bold py-3.5 px-6 rounded-xl hover:bg-yellow-300 active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-2 shadow-lg shadow-yellow-500/20 text-base">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
                        Thêm vào giỏ
                    </button>

                    <!-- Wishlist Button -->
                    <button type="button" onclick="toggleWishlist(<?= $product['id'] ?>)" id="wishlist-btn" class="w-14 shrink-0 flex items-center justify-center rounded-xl border-2 transition-all duration-200 active:scale-[0.98] <?= $isWished ? 'border-red-500 bg-red-50 text-red-500' : 'border-gray-200 bg-white text-gray-400 hover:border-red-200 hover:text-red-500 hover:bg-red-50' ?>" aria-label="Yêu thích">
                        <svg class="w-6 h-6" fill="<?= $isWished ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    </button>
                </div>

                <!-- Buy Now -->
                <button id="buy-now-btn" onclick="buyNowVariant()" 
                   class="block w-full text-center bg-bb-blue text-white font-bold py-3.5 px-8 rounded-xl hover:bg-bb-dark transition-colors hidden">
                    Mua ngay
                </button>

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
                    <p class="text-sm text-gray-500 leading-relaxed">Giao hàng trong 1-3 ngày làm việc. Miễn phí vận chuyển cho đơn hàng từ 875.000 VNĐ. Đóng gói cẩn thận, bảo hiểm hàng hóa trong quá trình vận chuyển.</p>
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

        <!-- ═══ PRODUCT REVIEWS ═══ -->
        <section class="mb-12">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden p-6 md:p-8">
                <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-bb-yellow" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    Đánh giá từ khách hàng (<?= count($reviews) ?>)
                </h2>

                <?php if ($canReview): ?>
                <div class="bg-gray-50 rounded-xl p-5 mb-8 border border-gray-100">
                    <h3 class="font-bold text-gray-800 mb-3">Viết đánh giá của bạn</h3>
                    <form id="review-form" onsubmit="submitReview(event, <?= $product['id'] ?>)">
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Đánh giá sao:</label>
                            <select name="rating" id="review-rating" class="border border-gray-300 rounded-lg px-3 py-2 w-full md:w-32 focus:ring-bb-blue focus:border-bb-blue outline-none" required>
                                <option value="5">5 Sao (Rất tốt)</option>
                                <option value="4">4 Sao (Tốt)</option>
                                <option value="3">3 Sao (Bình thường)</option>
                                <option value="2">2 Sao (Tệ)</option>
                                <option value="1">1 Sao (Rất tệ)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nhận xét (không bắt buộc):</label>
                            <textarea name="comment" id="review-comment" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-bb-blue focus:border-bb-blue outline-none" placeholder="Chia sẻ cảm nhận của bạn về sản phẩm..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hình ảnh đính kèm (Tối đa 3 ảnh):</label>
                            <input type="file" name="review_images[]" id="review-images" multiple accept="image/jpeg, image/png, image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-bb-blue hover:file:bg-blue-100">
                        </div>
                        <button type="submit" id="submit-review-btn" class="bg-bb-blue text-white font-semibold py-2 px-6 rounded-lg hover:bg-bb-dark transition-colors flex items-center gap-2">
                            Gửi đánh giá
                        </button>
                    </form>
                </div>
                <?php elseif (empty($_SESSION['user']['id'])): ?>
                <div class="bg-blue-50/50 rounded-xl p-5 mb-8 border border-blue-100 text-center">
                    <p class="text-gray-600 mb-3">Vui lòng đăng nhập để gửi đánh giá của bạn về sản phẩm này.</p>
                    <a href="/auth/login.php" class="inline-block bg-bb-blue text-white font-semibold py-2.5 px-6 rounded-lg hover:bg-bb-dark transition-colors">Đăng nhập ngay</a>
                </div>
                <?php endif; ?>

                <div class="space-y-6" id="reviews-list">
                    <?php if (empty($reviews)): ?>
                        <p class="text-gray-500 italic">Chưa có đánh giá nào cho sản phẩm này.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rv): ?>
                            <div class="border-b border-gray-100 pb-6 last:border-0 last:pb-0">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-bb-blue font-bold uppercase">
                                        <?= mb_substr(htmlspecialchars($rv['user_name']), 0, 1) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($rv['user_name']) ?></p>
                                        <div class="flex items-center gap-2">
                                            <?= renderStars((float) $rv['rating']) ?>
                                            <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($rv['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($rv['comment'])): ?>
                                    <p class="text-gray-600 text-sm mt-2"><?= nl2br(htmlspecialchars($rv['comment'])) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($rv['images_json'])): 
                                    $images = json_decode($rv['images_json'], true);
                                    if (is_array($images) && count($images) > 0):
                                ?>
                                    <div class="flex gap-2 mt-3 flex-wrap">
                                        <?php foreach ($images as $img): ?>
                                            <img src="/<?= htmlspecialchars($img) ?>" class="w-20 h-20 object-cover rounded-lg border border-gray-200 cursor-zoom-in" onclick="window.open(this.src, '_blank')" alt="Review image">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

    const variants = <?= json_encode($variants) ?>;
    let selectedVariant = variants.length > 0 ? variants[0] : null;
    let selectedAttributes = {};

    function updateVariantUI() {
        if (!selectedVariant) {
            document.getElementById('add-to-cart-section')?.classList.add('hidden');
            document.getElementById('buy-now-btn')?.classList.add('hidden');
            document.getElementById('stock-status').innerHTML = '<span class="text-red-500 font-medium">Không khả dụng</span>';
            return;
        }

        // Update price
        const price = parseFloat(selectedVariant.price);
        const salePrice = selectedVariant.sale_price ? parseFloat(selectedVariant.sale_price) : null;
        const displayPrice = salePrice || price;

        const displayPriceEl = document.getElementById('display-price');
        if(displayPriceEl) displayPriceEl.textContent = new Intl.NumberFormat('vi-VN').format(displayPrice) + ' VNĐ';
        
        const origPriceEl = document.getElementById('original-price');
        if(origPriceEl) {
            if (salePrice) {
                origPriceEl.textContent = new Intl.NumberFormat('vi-VN').format(price) + ' VNĐ';
                origPriceEl.classList.remove('hidden');
            } else {
                origPriceEl.classList.add('hidden');
            }
        }

        // Update stock
        const stock = parseInt(selectedVariant.stock);
        const stockStatus = document.getElementById('stock-status');
        const addToCartSection = document.getElementById('add-to-cart-section');
        const buyNowBtn = document.getElementById('buy-now-btn');
        const qtyInput = document.getElementById('product-qty');

        if (stock > 10) {
            if(stockStatus) stockStatus.innerHTML = '<span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></span><span class="text-sm font-medium text-green-600">Còn hàng</span>';
            addToCartSection?.classList.remove('hidden');
            buyNowBtn?.classList.remove('hidden');
            if(qtyInput) qtyInput.max = stock;
        } else if (stock > 0) {
            if(stockStatus) stockStatus.innerHTML = '<span class="w-2.5 h-2.5 bg-orange-500 rounded-full animate-pulse"></span><span class="text-sm font-medium text-orange-600">Chỉ còn ' + stock + ' sản phẩm</span>';
            addToCartSection?.classList.remove('hidden');
            buyNowBtn?.classList.remove('hidden');
            if(qtyInput) qtyInput.max = stock;
        } else {
            if(stockStatus) stockStatus.innerHTML = '<span class="w-2.5 h-2.5 bg-red-500 rounded-full"></span><span class="text-sm font-medium text-red-600">Hết hàng</span>';
            addToCartSection?.classList.add('hidden');
            buyNowBtn?.classList.add('hidden');
        }

        // Update image
        if (selectedVariant.image_url) {
            const img = document.getElementById('product-main-image');
            if(img) img.src = '/' + selectedVariant.image_url;
        }
    }

    // Bind click events on attribute buttons
    document.querySelectorAll('.attribute-group').forEach(group => {
        const attrId = group.getAttribute('data-attr-id');
        const buttons = group.querySelectorAll('.variant-btn');
        
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active state from all siblings
                buttons.forEach(b => {
                    b.classList.remove('border-bb-blue', 'text-bb-blue', 'bg-blue-50');
                    b.classList.add('border-gray-200');
                });
                // Add active state to clicked
                this.classList.remove('border-gray-200');
                this.classList.add('border-bb-blue', 'text-bb-blue', 'bg-blue-50');

                selectedAttributes[attrId] = this.getAttribute('data-val-id');
                findVariant();
            });
        });
    });

    function findVariant() {
        // Build a sorted array of selected value IDs
        const selectedValues = Object.values(selectedAttributes).map(v => parseInt(v)).sort((a,b) => a-b).join(',');
        const matched = variants.find(v => v.attribute_value_ids === selectedValues);
        if (matched) {
            selectedVariant = matched;
            updateVariantUI();
        } else {
            selectedVariant = null;
            updateVariantUI();
        }
    }

    // Auto-select first variant on load if attributes exist
    if (variants.length > 0 && variants[0].attribute_value_ids) {
        const defaultValues = variants[0].attribute_value_ids.split(',');
        document.querySelectorAll('.attribute-group').forEach(group => {
            const attrId = group.getAttribute('data-attr-id');
            const buttons = group.querySelectorAll('.variant-btn');
            buttons.forEach(btn => {
                if (defaultValues.includes(btn.getAttribute('data-val-id'))) {
                    btn.click(); // Triggers event listener
                }
            });
        });
    } else {
        updateVariantUI();
    }

    /**
     * Thêm vào giỏ
     */
    async function addToCartVariant(redirect = false) {
        if (!selectedVariant) {
            showToast('Vui lòng chọn phân loại hàng', 'error');
            return;
        }

        const qty = parseInt(document.getElementById('product-qty')?.value) || 1;
        const btn = redirect ? document.getElementById('buy-now-btn') : document.getElementById('add-to-cart-btn');
        
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Đang xử lý...';
        
        try {
            const resp = await fetch('/api/cart-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', variant_id: selectedVariant.id, quantity: qty })
            });
            const data = await resp.json();
            if (data.success) {
                if (redirect) {
                    window.location.href = '/checkout.php';
                    return;
                }
                updateCartBadge(data.cartCount);
                if (typeof openCartDrawer === 'function') openCartDrawer();
                else showToast('✓ Đã thêm vào giỏ hàng!', 'success');
            } else {
                throw new Error(data.message || 'Lỗi thêm sản phẩm');
            }
        } catch (err) {
            showToast('✕ ' + err.message, 'error');
        } finally {
            if (!redirect) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }
    }

    function buyNowVariant() {
        addToCartVariant(true);
    }

    /**
     * Gửi đánh giá sản phẩm
     */
    async function submitReview(e, productId) {
        e.preventDefault();
        const form = e.target;
        const btn = document.getElementById('submit-review-btn');
        const formData = new FormData(form);
        formData.append('product_id', productId);

        const originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Đang gửi...';

        try {
            const resp = await fetch('/api/submit_review.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (resp.ok && data.success) {
                alert(data.message);
                window.location.reload(); // Tải lại trang để thấy đánh giá mới và rating mới
            } else {
                throw new Error(data.error || 'Đã có lỗi xảy ra.');
            }
        } catch (err) {
            alert('Lỗi: ' + err.message);
            btn.innerHTML = originalBtnHtml;
            btn.disabled = false;
        }
    }
    /**
     * Wishlist toggle
     */
    async function toggleWishlist(productId) {
        const btn = document.getElementById('wishlist-btn');
        const icon = btn.querySelector('svg');
        
        // Optimistic UI update
        const isWished = btn.classList.contains('border-red-500');
        if (isWished) {
            btn.className = 'w-14 shrink-0 flex items-center justify-center rounded-xl border-2 transition-all duration-200 active:scale-[0.98] border-gray-200 bg-white text-gray-400 hover:border-red-200 hover:text-red-500 hover:bg-red-50';
            icon.setAttribute('fill', 'none');
        } else {
            btn.className = 'w-14 shrink-0 flex items-center justify-center rounded-xl border-2 transition-all duration-200 active:scale-[0.98] border-red-500 bg-red-50 text-red-500';
            icon.setAttribute('fill', 'currentColor');
        }

        try {
            const res = await fetch('/api/wishlist-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle', product_id: productId })
            });
            const data = await res.json();
            
            if (data.require_login) {
                // Revert optimistic UI
                if (isWished) {
                    btn.className = 'w-14 shrink-0 flex items-center justify-center rounded-xl border-2 transition-all duration-200 active:scale-[0.98] border-red-500 bg-red-50 text-red-500';
                    icon.setAttribute('fill', 'currentColor');
                } else {
                    btn.className = 'w-14 shrink-0 flex items-center justify-center rounded-xl border-2 transition-all duration-200 active:scale-[0.98] border-gray-200 bg-white text-gray-400 hover:border-red-200 hover:text-red-500 hover:bg-red-50';
                    icon.setAttribute('fill', 'none');
                }
                
                if (confirm('Vui lòng đăng nhập để sử dụng tính năng Yêu thích. Bạn có muốn chuyển đến trang Đăng nhập?')) {
                    window.location.href = '/auth/login.php';
                }
                return;
            }

            if (data.success) {
                // Update badge in header if exists
                const badge = document.getElementById('wishlist-badge');
                if (badge) {
                    badge.textContent = data.count;
                    if (data.count > 0) {
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
                showToast(data.message, 'success');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error(error);
            showToast('Lỗi xử lý yêu cầu', 'error');
            // Revert UI on error (refresh is safer here)
            setTimeout(() => window.location.reload(), 1500);
        }
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

