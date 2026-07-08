<?php
/**
 * Helper Functions - BestBuy Store
 * Các hàm tiện ích dùng chung trên toàn bộ website
 */

/**
 * Render star rating dạng HTML
 * Dùng filled/empty stars thay vì icon library → không phụ thuộc bên ngoài
 *
 * @param float $rating   Điểm đánh giá 0-5
 * @param int   $reviewCount Số lượt đánh giá
 * @return string HTML
 */
function renderStars(float $rating, int $reviewCount = 0): string
{
    $html = '<div class="flex items-center gap-1">';
    $html .= '<div class="flex text-sm">';

    for ($i = 1; $i <= 5; $i++) {
        if ($i <= round($rating)) {
            $html .= '<span class="text-yellow-400">★</span>';
        } else {
            $html .= '<span class="text-gray-300">★</span>';
        }
    }

    $html .= '</div>';
    if ($reviewCount > 0) {
        $html .= '<span class="text-xs text-gray-400">(' . number_format($reviewCount) . ')</span>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * Format giá tiền VNĐ
 */
function formatPrice(float $price): string
{
    return number_format($price, 0, ',', '.') . ' VNĐ';
}

/**
 * Tính phần trăm giảm giá
 */
function calcDiscount(float $originalPrice, float $salePrice): int
{
    if ($originalPrice <= 0) return 0;
    return (int) round(($originalPrice - $salePrice) / $originalPrice * 100);
}

/**
 * Lấy đường dẫn ảnh sản phẩm có fallback.
 * Tối ưu hóa: Bỏ check file_exists để tránh thắt cổ chai I/O, frontend có thể handle lỗi load ảnh nếu cần.
 */
function getProductImage(?string $imagePath): string
{
    if (empty($imagePath)) return '';
    return '/' . ltrim($imagePath, '/');
}

/**
 * Render một product card HTML
 * Tách ra function riêng vì dùng lại ở index.php, search.php
 *
 * @param array $product Dữ liệu sản phẩm từ DB (JOIN categories)
 * @return string HTML
 */
function renderProductCard(array $product): string
{
    $name      = htmlspecialchars($product['name']);
    $slug      = htmlspecialchars($product['slug']);
    $price     = (float) $product['price'];
    $salePrice = $product['sale_price'] ? (float) $product['sale_price'] : null;
    $rating    = isset($product['real_rating']) ? (float) $product['real_rating'] : (float) ($product['rating'] ?? 0);
    $reviews   = isset($product['real_review_count']) ? (int) $product['real_review_count'] : (int) ($product['review_count'] ?? 0);
    $stock     = (int) $product['stock'];
    $catName   = htmlspecialchars($product['category_name'] ?? '');
    $catIcon   = $product['category_icon'] ?? '📦';
    $image     = getProductImage($product['image'] ?? '');
    $displayPrice = $salePrice ?? $price;

    $discountBadge = '';
    if ($salePrice && $salePrice < $price) {
        $pct = calcDiscount($price, $salePrice);
        $discountBadge = '<span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-2.5 py-1 rounded-md shadow-lg z-10">-' . $pct . '%</span>';
    }

    $stockBadge = '';
    if ($stock <= 0) {
        $stockBadge = '<span class="absolute top-3 right-3 bg-gray-700 text-white text-xs font-semibold px-2.5 py-1 rounded-md z-10">Hết hàng</span>';
    } elseif ($stock <= 10) {
        $stockBadge = '<span class="absolute top-3 right-3 bg-orange-500 text-white text-xs font-semibold px-2.5 py-1 rounded-md z-10">Còn ' . $stock . '</span>';
    }

    $imageHtml = $image
        ? '<img src="' . htmlspecialchars($image) . '" alt="' . $name . '" class="w-full h-full object-contain p-6 group-hover:scale-110 transition-transform duration-500" loading="lazy">'
        : '<span class="text-6xl opacity-60 group-hover:scale-110 transition-transform duration-500">' . $catIcon . '</span>';

    $priceHtml = $salePrice
        ? '<span class="text-xs text-gray-500 mr-1">Từ</span><span class="text-lg font-bold text-bb-blue">' . formatPrice($salePrice) . '</span>
           <span class="text-sm text-gray-400 line-through ml-1">' . formatPrice($price) . '</span>'
        : '<span class="text-xs text-gray-500 mr-1">Từ</span><span class="text-lg font-bold text-bb-blue">' . formatPrice($price) . '</span>';

    $stars = renderStars($rating, $reviews);

    $addToCartBtn = $stock > 0
        ? '<a href="/' . $slug . '.html" class="w-full bg-bb-yellow text-bb-dark font-semibold py-2.5 rounded-lg hover:bg-yellow-300 active:scale-95 transition-all duration-200 flex items-center justify-center gap-2">
               Tùy chọn
           </a>'
        : '<button disabled class="w-full bg-gray-200 text-gray-500 font-semibold py-2.5 rounded-lg cursor-not-allowed">Hết hàng</button>';

    return '
    <div class="product-card bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 group border border-gray-100 flex flex-col">
        <!-- Image -->
        <a href="/' . $slug . '.html" class="relative overflow-hidden aspect-square bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
            ' . $discountBadge . $stockBadge . '
            ' . $imageHtml . '
        </a>
        <!-- Info -->
        <div class="p-4 flex flex-col flex-1">
            <p class="text-xs text-gray-400 mb-1 uppercase tracking-wide">' . $catIcon . ' ' . $catName . '</p>
            <a href="/' . $slug . '.html" class="font-semibold text-sm text-gray-800 mb-2 line-clamp-2 min-h-[2.5rem] hover:text-bb-blue transition-colors">' . $name . '</a>
            ' . $stars . '
            <div class="flex items-baseline gap-1 mt-2 mb-3">
                ' . $priceHtml . '
            </div>
            <div class="mt-auto">
                ' . $addToCartBtn . '
            </div>
        </div>
    </div>';
}

// ═══════════════════════════════════════════
// SECURITY & ANTI-CSRF FUNCTIONS
// ═══════════════════════════════════════════

/**
 * Khởi tạo/Lấy CSRF Token từ session
 */
function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1); session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            // Fallback nếu random_bytes lỗi (rất hiếm)
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render input hidden chứa CSRF Token dùng cho form
 */
function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Xác thực CSRF Token từ biến POST
 * @throws Exception nếu token không hợp lệ
 */
function verifyCsrfToken()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1); session_start();
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postToken    = $_POST['csrf_token'] ?? '';

        if (empty($sessionToken) || empty($postToken) || !hash_equals($sessionToken, $postToken)) {
            // Xóa session liên quan nếu nghi ngờ tấn công
            die('CSRF Token validation failed. Refresh the page and try again.');
        }
    }
}

/**
 * Cập nhật điểm đánh giá theo công thức Bayesian cho một sản phẩm
 * Bayesian Rating = (v * R + m * C) / (v + m)
 * v: Số lượng đánh giá của sản phẩm hiện tại.
 * R: Điểm đánh giá trung bình thực tế của sản phẩm.
 * m: Số lượng đánh giá tối thiểu (m = 5).
 * C: Điểm đánh giá trung bình của toàn bộ các sản phẩm.
 *
 * @param PDO $pdo Kết nối database
 * @param int $productId ID sản phẩm cần cập nhật
 */
function updateBayesianRating(PDO $pdo, int $productId): void
{
    $m = 5;

    // 1. Tính C (Trung bình điểm đánh giá của toàn bộ hệ thống)
    // Lấy tất cả các đánh giá có trong hệ thống
    $stmtC = $pdo->query("SELECT AVG(rating) as avg_rating FROM reviews");
    $globalAvg = (float) $stmtC->fetchColumn();
    $C = $globalAvg > 0 ? $globalAvg : 0;

    // 2. Tính v (Số lượng đánh giá của sản phẩm) và R (Điểm trung bình của sản phẩm)
    $stmtProduct = $pdo->prepare("SELECT COUNT(id) as review_count, AVG(rating) as avg_rating FROM reviews WHERE product_id = :product_id");
    $stmtProduct->execute([':product_id' => $productId]);
    $productStats = $stmtProduct->fetch();

    $v = (int) $productStats['review_count'];
    $R = (float) $productStats['avg_rating'];

    // 3. Tính Bayesian Rating
    if ($v == 0) {
        $bayesianRating = 0;
    } else {
        $bayesianRating = ($v * $R + $m * $C) / ($v + $m);
    }

    // Làm tròn đến 1 chữ số thập phân
    $bayesianRating = round($bayesianRating, 1);

    // 4. Cập nhật vào bảng products
    $stmtUpdate = $pdo->prepare("UPDATE products SET rating = :rating, review_count = :review_count WHERE id = :id");
    $stmtUpdate->execute([
        ':rating' => $bayesianRating,
        ':review_count' => $v,
        ':id' => $productId
    ]);
}

/**
 * Helper: Tạo slug từ chuỗi
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

/**
 * Lấy cài đặt chung từ biến global
 */
function getGlobalSetting(string $key, string $default = ''): string {
    global $globalSettings;
    return $globalSettings[$key] ?? $default;
}
