<?php
$adminPage = 'posts';
$adminTitle = 'Quản lý Blog / Tin tức';
require_once __DIR__ . '/includes/admin_header.php';

$action = $_GET['action'] ?? 'list';
$msg = '';
$msgType = '';

// Lấy danh mục
$cats = $pdo->query("SELECT * FROM post_categories ORDER BY name")->fetchAll();

// Xử lý POST (Thêm/Sửa/Xóa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
            $msg = "Đã xóa bài viết!";
            $msgType = "success";
        } catch (Exception $e) {
            $msg = "Lỗi khi xóa: " . $e->getMessage();
            $msgType = "error";
        }
    } elseif ($postAction === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $slug = trim($_POST['slug'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $authorId = $_SESSION['user']['id'];
        
        if (empty($slug)) $slug = createSlug($title);
        
        // Handle image upload
        $coverImage = $_POST['current_image'] ?? '';
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../assets/images/blog/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = time() . '_' . basename($_FILES['cover_image']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                $coverImage = '/assets/images/blog/' . $fileName;
            }
        }
        
        try {
            if ($id > 0) {
                // Cập nhật
                $stmt = $pdo->prepare("UPDATE posts SET title=?, slug=?, category_id=?, excerpt=?, content=?, cover_image=?, status=? WHERE id=?");
                $stmt->execute([$title, $slug, $categoryId, $excerpt, $content, $coverImage, $status, $id]);
                $msg = "Đã cập nhật bài viết!";
            } else {
                // Thêm mới
                $stmt = $pdo->prepare("INSERT INTO posts (title, slug, category_id, excerpt, content, cover_image, status, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $categoryId, $excerpt, $content, $coverImage, $status, $authorId]);
                $msg = "Đã thêm bài viết mới!";
            }
            $msgType = "success";
            $action = 'list'; // quay về list
        } catch (Exception $e) {
            $msg = "Lỗi lưu dữ liệu: " . $e->getMessage();
            $msgType = "error";
            $action = $id > 0 ? 'edit' : 'add';
        }
    }
}
?>

<div class="flex-1 flex flex-col min-h-0 overflow-hidden">
    <!-- Header -->
    <header class="bg-admin-card border-b border-admin-border h-16 flex items-center justify-between px-6 shrink-0">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-bold text-white"><?= $adminTitle ?></h1>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="bg-bb-blue text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Viết bài mới
                </a>
            <?php else: ?>
                <a href="?action=list" class="bg-admin-border text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
                    ← Quay lại
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 overflow-auto p-6">
        
        <?php if ($msg): ?>
            <div class="mb-6 px-4 py-3 rounded-lg text-sm <?= $msgType === 'success' ? 'bg-green-500/10 border border-green-500/20 text-green-400' : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <?php
            $posts = $pdo->query("SELECT p.*, c.name as category_name, u.name as author_name FROM posts p JOIN post_categories c ON p.category_id = c.id JOIN users u ON p.author_id = u.id ORDER BY p.created_at DESC")->fetchAll();
            ?>
            <div class="bg-admin-card border border-admin-border rounded-xl shadow-sm overflow-hidden">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-admin-bg/50 border-b border-admin-border text-gray-400">
                        <tr>
                            <th class="px-6 py-4 font-medium">Tiêu đề</th>
                            <th class="px-6 py-4 font-medium">Danh mục</th>
                            <th class="px-6 py-4 font-medium">Tác giả</th>
                            <th class="px-6 py-4 font-medium">Trạng thái</th>
                            <th class="px-6 py-4 font-medium">Lượt xem</th>
                            <th class="px-6 py-4 font-medium">Ngày viết</th>
                            <th class="px-6 py-4 font-medium text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-admin-border text-gray-300">
                        <?php if (empty($posts)): ?>
                            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Chưa có bài viết nào</td></tr>
                        <?php else: ?>
                            <?php foreach ($posts as $p): ?>
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <?php if ($p['cover_image']): ?>
                                                <img src="<?= htmlspecialchars($p['cover_image']) ?>" class="w-10 h-10 rounded object-cover">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded bg-admin-border flex items-center justify-center">📄</div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="font-medium text-white max-w-[200px] truncate" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></p>
                                                <p class="text-xs text-gray-500">/<?= htmlspecialchars($p['slug']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($p['category_name']) ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($p['author_name']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($p['status'] === 'published'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">Đã xuất bản</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-500/10 text-gray-400 border border-gray-500/20">Bản nháp</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4"><?= number_format($p['views']) ?></td>
                                    <td class="px-6 py-4"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="?action=edit&id=<?= $p['id'] ?>" class="p-1.5 text-blue-400 hover:bg-blue-400/10 rounded transition-colors" title="Sửa">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Xóa bài viết này?');" class="inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="p-1.5 text-red-400 hover:bg-red-400/10 rounded transition-colors" title="Xóa">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <?php
            $post = null;
            if ($action === 'edit' && isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $post = $stmt->fetch();
            }
            ?>
            <form method="POST" enctype="multipart/form-data" class="bg-admin-card border border-admin-border rounded-xl shadow-sm p-6 max-w-5xl" id="post-form">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $post['id'] ?? 0 ?>">
                <input type="hidden" name="content" id="hidden_content">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column: Main content -->
                    <div class="lg:col-span-2 space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Tiêu đề bài viết <span class="text-red-500">*</span></label>
                            <input type="text" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" required
                                   class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:border-bb-blue focus:ring-1 focus:ring-bb-blue outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Đường dẫn tĩnh (Slug)</label>
                            <input type="text" name="slug" value="<?= htmlspecialchars($post['slug'] ?? '') ?>" placeholder="Để trống để tự tạo từ tiêu đề"
                                   class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:border-bb-blue focus:ring-1 focus:ring-bb-blue outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Mô tả ngắn (Excerpt)</label>
                            <textarea name="excerpt" rows="3" class="w-full bg-admin-bg border border-admin-border rounded-lg px-4 py-2 text-white focus:border-bb-blue focus:ring-1 focus:ring-bb-blue outline-none transition-colors"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Nội dung chi tiết <span class="text-red-500">*</span></label>
                            <div class="bg-white text-black rounded-lg overflow-hidden">
                                <div id="editor-container" style="height: 400px;"><?= $post['content'] ?? '' ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Settings -->
                    <div class="space-y-5">
                        <div class="bg-admin-bg border border-admin-border p-4 rounded-lg">
                            <h3 class="font-medium text-white mb-3">Tùy chọn hiển thị</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Trạng thái</label>
                                    <select name="status" class="w-full bg-admin-card border border-admin-border rounded-lg px-3 py-2 text-white focus:border-bb-blue outline-none">
                                        <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Đã xuất bản</option>
                                        <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Bản nháp</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-400 mb-1">Danh mục <span class="text-red-500">*</span></label>
                                    <select name="category_id" required class="w-full bg-admin-card border border-admin-border rounded-lg px-3 py-2 text-white focus:border-bb-blue outline-none">
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php foreach ($cats as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($post['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-admin-bg border border-admin-border p-4 rounded-lg">
                            <h3 class="font-medium text-white mb-3">Ảnh bìa</h3>
                            <?php if (!empty($post['cover_image'])): ?>
                                <div class="mb-3">
                                    <img src="<?= htmlspecialchars($post['cover_image']) ?>" class="w-full h-32 object-cover rounded-lg">
                                    <input type="hidden" name="current_image" value="<?= htmlspecialchars($post['cover_image']) ?>">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="cover_image" accept="image/*" class="w-full text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-admin-border file:text-white hover:file:bg-gray-600">
                        </div>

                        <button type="submit" class="w-full bg-bb-blue text-white font-bold py-3 rounded-lg hover:bg-blue-600 transition-colors shadow-lg shadow-blue-500/20">
                            <?= $action === 'edit' ? 'Cập nhật bài viết' : 'Lưu bài viết' ?>
                        </button>
                    </div>
                </div>
            </form>

            <!-- QuillJS Setup -->
            <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
            <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
            <script>
                var quill = new Quill('#editor-container', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            ['blockquote', 'code-block'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'color': [] }, { 'background': [] }],
                            ['link', 'image', 'video'],
                            ['clean']
                        ]
                    }
                });

                document.getElementById('post-form').onsubmit = function() {
                    // Populate hidden input with HTML content
                    document.getElementById('hidden_content').value = quill.root.innerHTML;
                };
            </script>
        <?php endif; ?>

    </main>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
