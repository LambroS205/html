<?php
/**
 * Blog Page - Danh sách bài viết
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pdo = Database::getConnection();

// Lấy danh mục đang chọn (nếu có)
$catSlug = $_GET['category'] ?? '';
$categoryId = null;
$categoryName = 'Tất cả bài viết';

if ($catSlug) {
    $stmt = $pdo->prepare("SELECT id, name FROM post_categories WHERE slug = ?");
    $stmt->execute([$catSlug]);
    $cat = $stmt->fetch();
    if ($cat) {
        $categoryId = $cat['id'];
        $categoryName = $cat['name'];
    }
}

// Lấy tất cả danh mục
$categories = $pdo->query("SELECT * FROM post_categories ORDER BY name")->fetchAll();

// Phân trang
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;

// Đếm tổng số bài viết
$countSql = "SELECT COUNT(id) FROM posts WHERE status = 'published'";
$params = [];
if ($categoryId) {
    $countSql .= " AND category_id = ?";
    $params[] = $categoryId;
}
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalPosts = $stmtCount->fetchColumn();
$totalPages = ceil($totalPosts / $limit);

// Lấy danh sách bài viết
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, u.name as author_name 
        FROM posts p 
        JOIN post_categories c ON p.category_id = c.id 
        JOIN users u ON p.author_id = u.id 
        WHERE p.status = 'published'";
if ($categoryId) {
    $sql .= " AND p.category_id = ?";
}
$sql .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
$stmtPosts = $pdo->prepare($sql);
$stmtPosts->execute($params);
$posts = $stmtPosts->fetchAll();

$pageTitle = $categoryName . ' — Blog Công Nghệ';
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-bb-light py-10 min-h-screen">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Blog Công Nghệ</h1>
            <p class="text-gray-500 max-w-2xl mx-auto">Cập nhật những tin tức công nghệ mới nhất, đánh giá sản phẩm chuyên sâu và mẹo vặt hữu ích từ các chuyên gia của BestBuy.</p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Sidebar: Categories -->
            <aside class="w-full lg:w-1/4 shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h3 class="font-bold text-gray-900 mb-4 text-lg">Chủ đề</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="/blog.php" class="flex items-center justify-between py-2 text-sm <?= empty($catSlug) ? 'text-bb-blue font-semibold' : 'text-gray-600 hover:text-bb-blue transition-colors' ?>">
                                <span>Tất cả</span>
                            </a>
                        </li>
                        <?php foreach ($categories as $c): ?>
                            <li>
                                <a href="/blog.php?category=<?= htmlspecialchars($c['slug']) ?>" class="flex items-center justify-between py-2 text-sm <?= $catSlug === $c['slug'] ? 'text-bb-blue font-semibold' : 'text-gray-600 hover:text-bb-blue transition-colors' ?>">
                                    <span><?= htmlspecialchars($c['name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>

            <!-- Main Content: Posts Grid -->
            <main class="w-full lg:w-3/4">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($categoryName) ?></h2>
                    <span class="text-sm text-gray-500"><?= $totalPosts ?> bài viết</span>
                </div>

                <?php if (empty($posts)): ?>
                    <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
                        <span class="text-6xl mb-4 block opacity-50">📰</span>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Chưa có bài viết nào</h2>
                        <p class="text-gray-500 mb-6">Hiện tại chuyên mục này chưa có bài viết mới. Vui lòng quay lại sau.</p>
                        <a href="/blog.php" class="text-bb-blue font-medium hover:underline">Xem tất cả bài viết</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($posts as $post): ?>
                            <article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-xl transition-all duration-300 group flex flex-col">
                                <a href="/blog-detail.php?slug=<?= htmlspecialchars($post['slug']) ?>" class="relative block overflow-hidden aspect-[4/3] bg-gray-100">
                                    <?php if ($post['cover_image']): ?>
                                        <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-4xl opacity-20">📰</div>
                                    <?php endif; ?>
                                    <span class="absolute top-3 left-3 bg-white/90 backdrop-blur text-bb-dark text-xs font-bold px-3 py-1.5 rounded-full shadow-sm">
                                        <?= htmlspecialchars($post['category_name']) ?>
                                    </span>
                                </a>
                                <div class="p-5 flex flex-col flex-1">
                                    <div class="flex items-center gap-3 text-xs text-gray-400 mb-3">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <?= date('d/m/Y', strtotime($post['created_at'])) ?>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            <?= number_format($post['views']) ?>
                                        </span>
                                    </div>
                                    <h3 class="font-bold text-gray-900 mb-2 line-clamp-2 group-hover:text-bb-blue transition-colors leading-snug">
                                        <a href="/blog-detail.php?slug=<?= htmlspecialchars($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a>
                                    </h3>
                                    <p class="text-sm text-gray-500 line-clamp-3 mb-4 flex-1">
                                        <?= htmlspecialchars($post['excerpt']) ?>
                                    </p>
                                    <div class="mt-auto pt-4 border-t border-gray-100 flex items-center gap-2">
                                        <div class="w-6 h-6 bg-blue-100 text-bb-blue rounded-full flex items-center justify-center text-xs font-bold">
                                            <?= strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
                                        </div>
                                        <span class="text-xs font-medium text-gray-600"><?= htmlspecialchars($post['author_name']) ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-12 flex items-center justify-center gap-2">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="/blog.php?page=<?= $i ?><?= $catSlug ? '&category='.$catSlug : '' ?>" 
                                   class="w-10 h-10 flex items-center justify-center rounded-xl font-medium transition-colors <?= $i === $page ? 'bg-bb-blue text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </main>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
