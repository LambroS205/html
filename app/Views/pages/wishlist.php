<?php
/**
 * Wishlist Page - Danh sách Yêu thích
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

// Yêu cầu đăng nhập
if (empty($_SESSION['user'])) {
    header('Location: /auth/login.php?redirect=/wishlist.php');
    exit;
}

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/helpers.php';

$pdo = Database::getConnection();
$userId = (int) $_SESSION['user']['id'];

// Lấy danh sách sản phẩm trong wishlist
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, c.slug AS category_slug,
           MIN(pv.price) as price, MIN(pv.sale_price) as sale_price, SUM(pv.stock) as stock,
           (SELECT image_url FROM product_variants WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as image,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as real_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as real_review_count
    FROM products p
    JOIN wishlists w ON p.id = w.product_id
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE w.user_id = :u
    GROUP BY p.id
    ORDER BY w.created_at DESC
");
$stmt->execute([':u' => $userId]);
$wishlistProducts = $stmt->fetchAll();

$pageTitle = 'Danh sách Yêu thích — BestBuy Store';
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="bg-gray-50/50 py-12 min-h-screen">
    <div class="max-w-7xl mx-auto px-4">
        
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path></svg>
                    Danh sách Yêu thích
                </h1>
                <p class="text-gray-500 mt-2">Bạn có <span class="font-bold text-gray-900"><?= count($wishlistProducts) ?></span> sản phẩm trong danh sách</p>
            </div>
            <a href="/" class="hidden md:inline-flex items-center gap-2 text-bb-blue hover:text-bb-dark font-medium transition-colors">
                ← Tiếp tục mua sắm
            </a>
        </div>

        <?php if (empty($wishlistProducts)): ?>
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                </div>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Danh sách yêu thích trống</h2>
                <p class="text-gray-500 mb-6 max-w-md mx-auto">Bạn chưa lưu sản phẩm nào. Hãy khám phá các sản phẩm tuyệt vời của chúng tôi và thả tim nhé!</p>
                <a href="/" class="inline-flex items-center gap-2 bg-bb-blue text-white font-semibold px-8 py-3 rounded-xl hover:bg-bb-dark transition-colors shadow-lg shadow-blue-500/20">Khám phá ngay</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($wishlistProducts as $product): ?>
                    <div class="relative group" id="wishlist-item-<?= $product['id'] ?>">
                        <?= renderProductCard($product) ?>
                        
                        <!-- Remove from Wishlist Button -->
                        <button onclick="removeFromWishlist(<?= $product['id'] ?>)" class="absolute top-3 right-3 z-20 w-8 h-8 bg-white rounded-full flex items-center justify-center text-red-500 shadow-md hover:bg-red-50 hover:scale-110 transition-all border border-gray-100" title="Xóa khỏi danh sách">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
async function removeFromWishlist(productId) {
    if (!confirm('Bạn có chắc chắn muốn xóa sản phẩm này khỏi danh sách yêu thích?')) return;
    
    try {
        const res = await fetch('/api/wishlist-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle', product_id: productId })
        });
        const data = await res.json();
        
        if (data.success) {
            // Remove the card from UI smoothly
            const item = document.getElementById('wishlist-item-' + productId);
            if (item) {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '0';
                item.style.transform = 'scale(0.9)';
                setTimeout(() => item.remove(), 300);
            }
            
            // Update badge
            const badge = document.getElementById('wishlist-badge');
            if (badge) {
                badge.textContent = data.count;
                if (data.count > 0) {
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                    // Reload page if empty to show empty state
                    setTimeout(() => window.location.reload(), 500);
                }
            }
            if(typeof showToast === 'function') showToast(data.message, 'success');
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error('Lỗi khi xóa:', error);
        alert('Lỗi kết nối!');
    }
}
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
