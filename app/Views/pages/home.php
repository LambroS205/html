<?php
/**
 * Trang chủ - BestBuy Store (Best Buy Style)
 * Gồm: Hero Banner, Category Showcase, Deal of the Day, Featured Products, All Products Grid
 */

$pageTitle = 'BestBuy — Mua sắm điện tử chính hãng giá tốt nhất';
$pageDescription = 'Khám phá laptop, điện thoại, TV, tai nghe chính hãng. Giảm giá đến 30%. Miễn phí vận chuyển đơn từ 875.000 VNĐ.';
$activeCategory = '';

require_once __DIR__ . '/../../../includes/header.php';

// ── Lấy dữ liệu sản phẩm ──
$pdo = Database::getConnection();

// Lấy cài đặt giao diện
$settingsList = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
function getHomeSetting($key, $settingsList, $default = '') {
    return $settingsList[$key] ?? $default;
}

// Featured products — hiển thị ở phần "Sản phẩm nổi bật" (Tính real-time dựa trên doanh số và rating thực)
$featuredProducts = $pdo->query("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           MIN(pv.price) as price, MIN(pv.sale_price) as sale_price, SUM(pv.stock) as stock,
           (SELECT image_url FROM product_variants WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as image,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as real_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as real_review_count,
           (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = p.id) as total_sold
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    GROUP BY p.id
    ORDER BY total_sold DESC, real_rating DESC
    LIMIT 8
")->fetchAll();

// Deal products (có sale_price) — hiển thị ở "Deal of the Day"
$dealProducts = $pdo->query("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           MIN(pv.price) as price, MIN(pv.sale_price) as sale_price, SUM(pv.stock) as stock,
           (SELECT image_url FROM product_variants WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as image,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as real_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as real_review_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN product_variants pv ON p.id = pv.product_id
    WHERE pv.sale_price IS NOT NULL AND pv.sale_price < pv.price
    GROUP BY p.id
    ORDER BY (MIN(pv.price) - MIN(pv.sale_price)) DESC
    LIMIT 4
")->fetchAll();

// All products — hiển thị ở cuối trang
$allProducts = $pdo->query("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           MIN(pv.price) as price, MIN(pv.sale_price) as sale_price, SUM(pv.stock) as stock,
           (SELECT image_url FROM product_variants WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as image,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as real_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as real_review_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
")->fetchAll();

// Categories with product count
$categoriesWithCount = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.id
")->fetchAll();
?>

    <!-- ═══════════════════════════════════════
         HERO BANNER — Gradient background + animated shapes
         ═══════════════════════════════════════ -->
    <?php if (getHomeSetting('show_home_hero', $settingsList, '1') == '1'): ?>
    <section class="hero-gradient relative overflow-hidden">
        <!-- Animated background shapes -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute w-[500px] h-[500px] rounded-full bg-blue-500/10 -top-1/4 -right-1/4 animate-float"></div>
            <div class="absolute w-[350px] h-[350px] rounded-full bg-bb-yellow/5 bottom-[-10%] left-[15%] animate-float-delayed"></div>
            <div class="absolute w-[200px] h-[200px] rounded-full bg-blue-400/10 top-[60%] right-[30%] animate-float-slow"></div>
            <div class="absolute w-[150px] h-[150px] rounded-full border border-white/5 top-[20%] left-[60%] animate-spin-slow"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-16 md:py-24 relative z-10">
            <div class="grid md:grid-cols-2 gap-8 md:gap-12 items-center">
                <!-- Text content -->
                <div>
                    <span class="inline-flex items-center gap-2 bg-bb-yellow/15 text-bb-yellow text-sm font-semibold px-4 py-2 rounded-full mb-6 backdrop-blur-sm border border-bb-yellow/20">
                        <span class="w-2 h-2 bg-bb-yellow rounded-full animate-pulse"></span>
                        <?= htmlspecialchars(getHomeSetting('home_hero_badge', $settingsList, 'Flash Sale — Giảm đến 30%')) ?>
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white mb-6 leading-tight">
                        <?= getHomeSetting('home_hero_title', $settingsList, 'Công nghệ<br><span class="text-bb-yellow">đỉnh cao</span><br><span class="text-blue-200">Giá không tưởng</span>') ?>
                    </h1>
                    <p class="text-base md:text-lg text-blue-200/70 mb-8 max-w-lg leading-relaxed">
                        <?= nl2br(htmlspecialchars(getHomeSetting('home_hero_desc', $settingsList, 'Khám phá bộ sưu tập laptop, điện thoại, TV và phụ kiện chính hãng mới nhất với ưu đãi hấp dẫn chưa từng có.'))) ?>
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars(getHomeSetting('home_hero_button_link', $settingsList, '#products')) ?>" class="inline-flex items-center gap-2 bg-bb-yellow text-bb-dark font-bold px-7 py-3.5 rounded-full hover:bg-yellow-300 transition-all duration-300 transform hover:scale-105 shadow-lg shadow-yellow-500/20">
                            <?= htmlspecialchars(getHomeSetting('home_hero_button_text', $settingsList, 'Mua sắm ngay')) ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                        </a>
                        <a href="#deals" class="inline-flex items-center gap-2 border-2 border-white/25 text-white font-semibold px-7 py-3.5 rounded-full hover:bg-white/10 hover:border-white/40 transition-all duration-300">
                            🔥 Xem Deal Hot
                        </a>
                    </div>

                    <!-- Trust badges -->
                    <div class="flex flex-wrap gap-4 mt-10 text-xs text-blue-200/50">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            100% Chính hãng
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            Bảo hành 24 tháng
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            Ship miễn phí từ 875.000 VNĐ
                        </span>
                    </div>
                </div>

                <!-- Hero visual — decorative rings + featured product -->
                <div class="hidden md:flex justify-center items-center relative">
                    <div class="absolute w-80 h-80 rounded-full border-2 border-bb-yellow/15 animate-spin-slow"></div>
                    <div class="absolute w-72 h-72 rounded-full border border-blue-400/10 animate-spin-reverse"></div>
                    <div class="absolute w-64 h-64 rounded-full bg-gradient-to-br from-bb-yellow/5 to-blue-500/5"></div>
                    
                    <!-- Featured product showcase (Dynamic Slideshow) -->
                    <div class="relative z-10 bg-white/10 backdrop-blur-md rounded-3xl p-8 border border-white/10 shadow-2xl transform rotate-2 hover:rotate-0 transition-transform duration-500 overflow-hidden w-full max-w-sm h-[320px] flex items-center justify-center">
                        <?php if(!empty($featuredProducts)): ?>
                            <div class="relative w-full h-full flex items-center justify-center" id="featured-slideshow">
                                <?php foreach($featuredProducts as $index => $fp): 
                                    $fpImage = !empty($fp['image']) ? '/' . ltrim($fp['image'], '/') : null;
                                    $fpDisplayPrice = $fp['sale_price'] ?? $fp['price'];
                                ?>
                                    <div class="absolute inset-0 transition-opacity duration-1000 ease-in-out flex flex-col items-center justify-center text-center slide <?= $index === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0' ?>">
                                        <a href="/product?slug=<?= htmlspecialchars($fp['slug']) ?>" class="block group">
                                            <?php if($fpImage): ?>
                                                <img src="<?= htmlspecialchars($fpImage) ?>" alt="<?= htmlspecialchars($fp['name']) ?>" class="h-32 w-auto object-contain mb-4 mx-auto drop-shadow-2xl group-hover:scale-110 transition-transform duration-300">
                                            <?php else: ?>
                                                <span class="text-6xl mb-4 block drop-shadow-2xl group-hover:scale-110 transition-transform duration-300"><?= htmlspecialchars($fp['category_icon'] ?? '💻') ?></span>
                                            <?php endif; ?>
                                            <p class="text-bb-yellow font-bold text-xs uppercase tracking-wider mb-2">Sản phẩm nổi bật</p>
                                            <h3 class="text-white font-bold text-lg leading-tight mb-2 px-2 line-clamp-2"><?= htmlspecialchars($fp['name']) ?></h3>
                                        </a>
                                        <div class="flex items-center gap-2 mb-4">
                                            <span class="text-yellow-400 text-sm">★ <?= number_format((float)$fp['real_rating'], 1) ?></span>
                                            <span class="text-blue-200/50 text-xs">(<?= (int)$fp['real_review_count'] ?>)</span>
                                        </div>
                                        <a href="/product?slug=<?= htmlspecialchars($fp['slug']) ?>" class="inline-block bg-bb-yellow text-bb-dark text-sm font-bold px-5 py-2 rounded-full hover:bg-yellow-300 transition-colors shadow-lg">
                                            Từ <?= formatPrice($fpDisplayPrice) ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const slides = document.querySelectorAll('#featured-slideshow .slide');
                                    if(slides.length <= 1) return;
                                    let currentSlide = 0;
                                    setInterval(() => {
                                        slides[currentSlide].classList.remove('opacity-100', 'z-10');
                                        slides[currentSlide].classList.add('opacity-0', 'z-0');
                                        currentSlide = (currentSlide + 1) % slides.length;
                                        slides[currentSlide].classList.remove('opacity-0', 'z-0');
                                        slides[currentSlide].classList.add('opacity-100', 'z-10');
                                    }, 3500); // Đổi slide mỗi 3.5 giây
                                });
                            </script>
                        <?php else: ?>
                            <div class="text-center">
                                <span class="text-6xl mb-4 block">💻</span>
                                <p class="text-bb-yellow font-bold text-sm mb-1">Sản phẩm nổi bật</p>
                                <p class="text-white font-semibold text-lg">MacBook Pro M4</p>
                                <p class="text-blue-200/60 text-sm mt-1 mb-3">Hiệu năng vượt trội</p>
                                <span class="inline-block bg-bb-yellow text-bb-dark text-sm font-bold px-4 py-1.5 rounded-full">
                                    Từ 2.299 VNĐ
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════
         CATEGORY SHOWCASE — Grid icons
         ═══════════════════════════════════════ -->
    <?php if (getHomeSetting('show_home_categories', $settingsList, '1') == '1'): ?>
    <section class="max-w-7xl mx-auto px-4 <?= getHomeSetting('show_home_hero', $settingsList, '1') == '1' ? '-mt-8' : 'pt-8' ?> relative z-20 mb-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($categoriesWithCount as $cat): ?>
                <a href="/danh-muc/<?= htmlspecialchars($cat['slug']) ?>" 
                   class="bg-white rounded-2xl p-5 flex items-center gap-4 shadow-sm hover:shadow-lg transition-all duration-300 group border border-gray-100 hover:border-bb-blue/20">
                    <span class="text-3xl md:text-4xl group-hover:scale-110 transition-transform duration-300"><?= $cat['icon'] ?></span>
                    <div>
                        <h3 class="font-semibold text-gray-800 group-hover:text-bb-blue transition-colors"><?= htmlspecialchars($cat['name']) ?></h3>
                        <p class="text-xs text-gray-400"><?= $cat['product_count'] ?> sản phẩm</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════
         DEAL OF THE DAY — Highlighted deals
         ═══════════════════════════════════════ -->
    <?php if (!empty($dealProducts)): ?>
    <section id="deals" class="max-w-7xl mx-auto px-4 mb-14">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 flex items-center gap-2">
                    🔥 Deal of the Day
                </h2>
                <p class="text-sm text-gray-400 mt-1">Ưu đãi có hạn — Nhanh tay kẻo lỡ!</p>
            </div>
            <a href="/search?deals=1" class="hidden md:inline-flex items-center gap-1 text-bb-blue font-semibold text-sm hover:gap-2 transition-all">
                Xem tất cả
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php foreach ($dealProducts as $product): ?>
                <?= renderProductCard($product) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════
         PROMO BANNER — Mid-page CTA
         ═══════════════════════════════════════ -->
    <?php if (getHomeSetting('show_home_promo', $settingsList, '1') == '1'): ?>
    <section class="max-w-7xl mx-auto px-4 mb-14">
        <div class="bg-gradient-to-r from-bb-dark to-bb-blue rounded-2xl overflow-hidden relative">
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute w-64 h-64 rounded-full bg-bb-yellow/10 -top-20 -right-20 animate-float"></div>
                <div class="absolute w-32 h-32 rounded-full bg-white/5 bottom-4 left-1/3 animate-float-delayed"></div>
            </div>
            <div class="relative z-10 p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <h3 class="text-2xl md:text-3xl font-bold text-white mb-2">
                        <?= getHomeSetting('home_promo_title', $settingsList, '🎧 Mua tai nghe — Giảm thêm <span class="text-bb-yellow">20%</span>') ?>
                    </h3>
                    <p class="text-blue-200/60 text-sm"><?= nl2br(htmlspecialchars(getHomeSetting('home_promo_desc', $settingsList, 'Áp dụng cho Sony WH-1000XM5 & AirPods Pro 3. Số lượng có hạn.'))) ?></p>
                </div>
                <a href="<?= htmlspecialchars(getHomeSetting('home_promo_button_link', $settingsList, '/danh-muc/tai-nghe')) ?>" class="shrink-0 bg-bb-yellow text-bb-dark font-bold px-8 py-3 rounded-full hover:bg-yellow-300 transition-all transform hover:scale-105 shadow-lg">
                    <?= htmlspecialchars(getHomeSetting('home_promo_button_text', $settingsList, 'Xem ngay →')) ?>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════
         ALL PRODUCTS — Full grid
         ═══════════════════════════════════════ -->
    <section id="products" class="max-w-7xl mx-auto px-4 mb-16">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
                    ⚡ Tất cả sản phẩm
                </h2>
                <p class="text-sm text-gray-400 mt-1"><?= count($allProducts) ?> sản phẩm chính hãng</p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
            <?php foreach ($allProducts as $product): ?>
                <?= renderProductCard($product) ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ═══════════════════════════════════════
         WHY CHOOSE US — Trust section
         ═══════════════════════════════════════ -->
    <?php if (getHomeSetting('show_home_trust', $settingsList, '1') == '1'): ?>
    <section class="bg-white border-t border-gray-100 py-12 mb-0">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">🚚</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Miễn phí vận chuyển</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Đơn hàng từ 875.000 VNĐ</p>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center text-2xl">🔄</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Đổi trả 30 ngày</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Hoàn tiền 100%</p>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-yellow-50 rounded-2xl flex items-center justify-center text-2xl">🛡️</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Bảo hành chính hãng</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Lên đến 24 tháng</p>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center text-2xl">💬</div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-sm">Hỗ trợ 24/7</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Chat & Hotline</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
