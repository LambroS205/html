<?php
/**
 * Trang Tìm kiếm & Lọc sản phẩm - BestBuy Store
 * 
 * URL params:
 *  - q        : Từ khóa tìm kiếm
 *  - category : Slug danh mục
 *  - deals    : 1 = chỉ hiện sản phẩm đang giảm giá
 *  - sort     : price_asc, price_desc, rating, newest
 *  - ajax     : 1 = trả về JSON (cho live search)
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = Database::getConnection();

// ── Lấy params ──
$searchQuery    = trim($_GET['q'] ?? '');
$categorySlug   = trim($_GET['category'] ?? '');
$dealsOnly      = (int) ($_GET['deals'] ?? 0);
$sortBy         = $_GET['sort'] ?? 'newest';

// ── Build query với Prepared Statements ──
$whereClauses = [];
$params       = [];

// Tìm kiếm theo tên
if ($searchQuery !== '') {
    // Dùng LIKE thay vì FULLTEXT cho linh hoạt hơn với từ khóa ngắn
    $whereClauses[] = "(p.name LIKE :search OR p.description LIKE :search2)";
    $params[':search']  = "%{$searchQuery}%";
    $params[':search2'] = "%{$searchQuery}%";
}

// Lọc theo danh mục
if ($categorySlug !== '') {
    $whereClauses[] = "c.slug = :category";
    $params[':category'] = $categorySlug;
}

// Chỉ deal giảm giá
if ($dealsOnly === 1) {
    $whereClauses[] = "pv.sale_price IS NOT NULL AND pv.sale_price < pv.price";
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Sắp xếp
$orderMap = [
    'price_asc'  => 'MIN(COALESCE(pv.sale_price, pv.price)) ASC',
    'price_desc' => 'MIN(COALESCE(pv.sale_price, pv.price)) DESC',
    'rating'     => 'real_rating DESC, real_review_count DESC',
    'newest'     => 'p.created_at DESC',
    'name'       => 'p.name ASC',
];
$orderSQL = $orderMap[$sortBy] ?? $orderMap['newest'];

$sql = "
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           MIN(pv.price) as price, MIN(pv.sale_price) as sale_price, SUM(pv.stock) as stock,
           (SELECT image_url FROM product_variants WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as image,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as real_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as real_review_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    {$whereSQL}
    GROUP BY p.id
    ORDER BY {$orderSQL}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ═══ AJAX MODE — Trả JSON cho live search ═══
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($products, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Page metadata ──
$activeCategory  = $categorySlug;
$categoryName    = '';
if ($categorySlug) {
    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE slug = :slug LIMIT 1");
    $catStmt->execute([':slug' => $categorySlug]);
    $categoryName = $catStmt->fetchColumn() ?: '';
}

if ($searchQuery) {
    $pageTitle = 'Tìm kiếm "' . $searchQuery . '" — BestBuy Store';
} elseif ($categoryName) {
    $pageTitle = $categoryName . ' — BestBuy Store';
} elseif ($dealsOnly) {
    $pageTitle = 'Deal Hot — Giảm giá sốc — BestBuy Store';
} else {
    $pageTitle = 'Tất cả sản phẩm — BestBuy Store';
}
$pageDescription = 'Tìm kiếm sản phẩm điện tử chính hãng tại BestBuy Store.';

// ── Lấy tất cả categories cho sidebar ──
$allCategories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.id
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

    <!-- ═══ SEARCH RESULTS PAGE ═══ -->
    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
            <a href="/" class="hover:text-bb-blue transition-colors">Trang chủ</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <?php if ($categoryName): ?>
                <span class="text-gray-600 font-medium"><?= htmlspecialchars($categoryName) ?></span>
            <?php elseif ($searchQuery): ?>
                <span class="text-gray-600 font-medium">Tìm kiếm</span>
            <?php elseif ($dealsOnly): ?>
                <span class="text-gray-600 font-medium">Deal Hot</span>
            <?php else: ?>
                <span class="text-gray-600 font-medium">Tất cả sản phẩm</span>
            <?php endif; ?>
        </nav>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- ═══ SIDEBAR — Filters ═══ -->
            <aside class="w-full lg:w-64 shrink-0">
                <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 sticky top-24">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        Bộ lọc
                    </h3>

                    <!-- Category filter -->
                    <div class="mb-5">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-3">Danh mục</p>
                        <div class="space-y-1">
                            <a href="/search.php<?= $searchQuery ? '?q=' . urlencode($searchQuery) : '' ?>" 
                               class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all <?= $categorySlug === '' ? 'bg-bb-blue/10 text-bb-blue font-semibold' : 'text-gray-600 hover:bg-gray-50' ?>">
                                <span>Tất cả</span>
                            </a>
                            <?php foreach ($allCategories as $cat): ?>
                                <?php
                                    $catUrl = '/danh-muc/' . urlencode($cat['slug']);
                                    if ($searchQuery) $catUrl .= '?q=' . urlencode($searchQuery);
                                    $isActive = $categorySlug === $cat['slug'];
                                ?>
                                <a href="<?= $catUrl ?>" 
                                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all <?= $isActive ? 'bg-bb-blue/10 text-bb-blue font-semibold' : 'text-gray-600 hover:bg-gray-50' ?>">
                                    <span class="flex items-center gap-2">
                                        <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                                    </span>
                                    <span class="text-xs <?= $isActive ? 'text-bb-blue' : 'text-gray-300' ?>"><?= $cat['product_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Deal filter -->
                    <div class="mb-5">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-3">Khuyến mãi</p>
                        <a href="/search.php?deals=1<?= $categorySlug ? '&category=' . urlencode($categorySlug) : '' ?>" 
                           class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all <?= $dealsOnly ? 'bg-red-50 text-red-600 font-semibold' : 'text-gray-600 hover:bg-gray-50' ?>">
                            🔥 Chỉ hiện deal giảm giá
                        </a>
                    </div>

                    <!-- Sort (mobile-visible in sidebar too) -->
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-3">Sắp xếp</p>
                        <select id="sort-select" onchange="applySortFilter(this.value)" 
                                class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 focus:border-bb-blue focus:ring-1 focus:ring-bb-blue/20 outline-none transition-all">
                            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                            <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Giá: Thấp → Cao</option>
                            <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Giá: Cao → Thấp</option>
                            <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Đánh giá cao nhất</option>
                            <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Tên A-Z</option>
                        </select>
                    </div>
                </div>
            </aside>

            <!-- ═══ MAIN CONTENT — Results ═══ -->
            <div class="flex-1">
                <!-- Search header -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <?php if ($searchQuery): ?>
                            <h1 class="text-xl md:text-2xl font-bold text-gray-900">
                                Kết quả cho "<span class="text-bb-blue"><?= htmlspecialchars($searchQuery) ?></span>"
                            </h1>
                        <?php elseif ($categoryName): ?>
                            <h1 class="text-xl md:text-2xl font-bold text-gray-900">
                                <?= htmlspecialchars($categoryName) ?>
                            </h1>
                        <?php elseif ($dealsOnly): ?>
                            <h1 class="text-xl md:text-2xl font-bold text-gray-900">
                                🔥 Deal Hot — Giảm giá sốc
                            </h1>
                        <?php else: ?>
                            <h1 class="text-xl md:text-2xl font-bold text-gray-900">
                                Tất cả sản phẩm
                            </h1>
                        <?php endif; ?>
                        <p class="text-sm text-gray-400 mt-1"><?= count($products) ?> sản phẩm được tìm thấy</p>
                    </div>
                </div>

                <!-- Products grid -->
                <?php if (empty($products)): ?>
                    <div class="bg-white rounded-2xl p-16 text-center shadow-sm border border-gray-100">
                        <span class="text-6xl mb-4 block">🔍</span>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">Không tìm thấy sản phẩm nào</h3>
                        <p class="text-gray-400 mb-6">Hãy thử tìm kiếm với từ khóa khác hoặc duyệt qua danh mục bên trái.</p>
                        <a href="/" class="inline-flex items-center gap-2 bg-bb-blue text-white font-semibold px-6 py-3 rounded-full hover:bg-bb-dark transition-colors">
                            ← Về trang chủ
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                        <?php foreach ($products as $product): ?>
                            <?= renderProductCard($product) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    /**
     * Apply sort filter — giữ nguyên params hiện tại, chỉ thay sort
     */
    function applySortFilter(sortValue) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

