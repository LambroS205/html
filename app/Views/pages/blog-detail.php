<?php
/**
 * Blog Detail Page - Xem chi tiết bài viết
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/helpers.php';

$pdo = Database::getConnection();

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /blog');
    exit;
}

// Fetch post
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug, u.name as author_name, u.email as author_email
    FROM posts p 
    JOIN post_categories c ON p.category_id = c.id 
    JOIN users u ON p.author_id = u.id 
    WHERE p.slug = ? AND p.status = 'published'
");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy bài viết';
    require_once __DIR__ . '/../../../includes/header.php';
    echo '<div class="max-w-3xl mx-auto px-4 py-20 text-center">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">Bài viết không tồn tại</h1>
            <a href="/blog" class="text-bb-blue hover:underline">← Trở về trang Blog</a>
          </div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Increment view count
$pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);

// Fetch related posts (same category)
$stmtRelated = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM posts p 
    JOIN post_categories c ON p.category_id = c.id 
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'published' 
    ORDER BY p.created_at DESC LIMIT 3
");
$stmtRelated->execute([$post['category_id'], $post['id']]);
$relatedPosts = $stmtRelated->fetchAll();

$pageTitle = htmlspecialchars($post['title']) . ' — Blog Công Nghệ';
$pageDescription = htmlspecialchars($post['excerpt']);
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="bg-bb-light py-10 min-h-screen">
    
    <!-- Hero Section (Cover Image) -->
    <?php if ($post['cover_image']): ?>
        <div class="max-w-5xl mx-auto px-4 mb-8">
            <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-auto max-h-[500px] object-cover rounded-3xl shadow-lg border border-gray-100">
        </div>
    <?php endif; ?>

    <main class="max-w-3xl mx-auto px-4">
        
        <!-- Breadcrumb & Meta -->
        <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 flex-wrap">
            <a href="/" class="hover:text-bb-blue transition-colors">Trang chủ</a>
            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <a href="/blog" class="hover:text-bb-blue transition-colors">Blog</a>
            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <a href="/blog?category=<?= htmlspecialchars($post['category_slug']) ?>" class="text-bb-blue hover:underline"><?= htmlspecialchars($post['category_name']) ?></a>
        </nav>

        <h1 class="text-3xl md:text-5xl font-extrabold text-gray-900 leading-tight mb-6">
            <?= htmlspecialchars($post['title']) ?>
        </h1>

        <div class="flex items-center gap-4 py-4 border-y border-gray-200 mb-8">
            <div class="w-12 h-12 bg-bb-blue text-white rounded-full flex items-center justify-center font-bold text-xl shadow-md">
                <?= strtoupper(mb_substr($post['author_name'], 0, 1)) ?>
            </div>
            <div class="flex-1">
                <p class="font-bold text-gray-900"><?= htmlspecialchars($post['author_name']) ?></p>
                <div class="text-sm text-gray-500 flex items-center gap-4 mt-0.5">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <?= date('d/m/Y', strtotime($post['created_at'])) ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        <?= number_format($post['views'] + 1) ?> lượt xem
                    </span>
                </div>
            </div>
        </div>

        <?php if ($post['excerpt']): ?>
            <div class="text-lg text-gray-600 font-medium leading-relaxed italic border-l-4 border-bb-yellow pl-4 mb-10">
                <?= htmlspecialchars($post['excerpt']) ?>
            </div>
        <?php endif; ?>

        <!-- Content -->
        <article class="prose prose-lg max-w-none text-gray-800 mb-16">
            <?= $post['content'] ?>
        </article>
        
        <!-- CSS styles for Quill content in Frontend (Prose-like) -->
        <style>
            .prose img { border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); margin: 2rem auto; }
            .prose h1, .prose h2, .prose h3 { color: #111827; font-weight: 800; margin-top: 2rem; margin-bottom: 1rem; }
            .prose h1 { font-size: 2.25rem; }
            .prose h2 { font-size: 1.875rem; }
            .prose h3 { font-size: 1.5rem; }
            .prose p { margin-bottom: 1.25rem; line-height: 1.75; }
            .prose ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1.25rem; }
            .prose ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1.25rem; }
            .prose blockquote { border-left: 4px solid #E5E7EB; padding-left: 1rem; font-style: italic; color: #4B5563; }
            .prose a { color: #0046BE; text-decoration: underline; }
            .prose a:hover { color: #001E73; }
            /* Embed videos fix */
            .prose iframe.ql-video { width: 100%; aspect-ratio: 16/9; border-radius: 0.75rem; margin: 2rem 0; }
        </style>

        <hr class="border-gray-200 mb-12">

        <!-- Related Posts -->
        <?php if (!empty($relatedPosts)): ?>
            <div class="mb-12">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Bài viết cùng chủ đề
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <?php foreach ($relatedPosts as $rp): ?>
                        <a href="/blog-detail?slug=<?= htmlspecialchars($rp['slug']) ?>" class="group">
                            <div class="aspect-video bg-gray-100 rounded-xl overflow-hidden mb-3 relative">
                                <?php if ($rp['cover_image']): ?>
                                    <img src="<?= htmlspecialchars($rp['cover_image']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-3xl opacity-20">📰</div>
                                <?php endif; ?>
                            </div>
                            <h4 class="font-bold text-gray-800 line-clamp-2 group-hover:text-bb-blue transition-colors leading-tight mb-1 text-sm">
                                <?= htmlspecialchars($rp['title']) ?>
                            </h4>
                            <p class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($rp['created_at'])) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
