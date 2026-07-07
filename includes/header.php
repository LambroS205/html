<?php
/**
 * Header Component - BestBuy Store
 * Gồm: meta tags, Tailwind CDN, top bar, main nav, category nav, mobile menu
 * 
 * Biến cần set trước khi include:
 * - $pageTitle (optional): Tiêu đề trang
 * - $pageDescription (optional): Meta description
 * - $activeCategory (optional): Slug danh mục đang active
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

// ── Cart count từ session ──
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int) ($item['quantity'] ?? 0);
    }
}

// ── Wishlist count từ db ──
$wishlistCount = 0;
$pdo = Database::getConnection();
if (!empty($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = :u");
    $stmt->execute([':u' => $_SESSION['user']['id']]);
    $wishlistCount = (int)$stmt->fetchColumn();
}

// ── Lấy danh mục cho navigation ──
$pdo = Database::getConnection();
$navCategories = $pdo->query("SELECT id, name, slug, icon FROM categories ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'BestBuy — Mua sắm điện tử chính hãng giá tốt nhất') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Cửa hàng điện tử trực tuyến — Laptop, Điện thoại, Tivi, Tai nghe chính hãng giá tốt nhất. Miễn phí vận chuyển đơn từ 875.000 VNĐ.') ?>">
    <meta name="robots" content="index, follow">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%23FFE000'/><text x='50%25' y='55%25' dominant-baseline='middle' text-anchor='middle' font-size='18' font-weight='900' fill='%23001E73'>B</text></svg>">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'bb-blue':   '#0046BE',
                    'bb-dark':   '#001E73',
                    'bb-navy':   '#040C43',
                    'bb-yellow': '#FFE000',
                    'bb-light':  '#F0F2F5',
                    'bb-gray':   '#55555A',
                },
                fontFamily: {
                    'inter': ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                }
            }
        }
    }
    </script>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="font-inter bg-bb-light text-gray-800 min-h-screen flex flex-col">

    <!-- ═══ TOP UTILITY BAR ═══ -->
    <div class="bg-bb-navy text-gray-300 text-xs py-1.5 hidden md:block">
        <div class="max-w-7xl mx-auto px-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-bb-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Giao hàng toàn quốc
                </span>
                <span class="text-gray-600">|</span>
                <span class="text-bb-yellow font-medium">🔥 Miễn phí vận chuyển đơn từ 875.000 VNĐ</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="#" class="hover:text-white transition-colors">Theo dõi đơn hàng</a>
                <span class="text-gray-600">|</span>
                <a href="/blog" class="hover:text-white transition-colors">Blog công nghệ</a>
                <span class="text-gray-600">|</span>
                <a href="#" class="hover:text-white transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    1800-BESTBUY
                </a>
            </div>
        </div>
    </div>

    <!-- ═══ MAIN HEADER ═══ -->
    <header id="main-header" class="bg-bb-blue sticky top-0 z-50 transition-shadow duration-300">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center gap-3 md:gap-6">
                
                <!-- Mobile menu button -->
                <button id="mobile-menu-btn" class="lg:hidden text-white p-1" aria-label="Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <!-- Logo -->
                <a href="/" class="flex items-center gap-1 shrink-0" aria-label="BestBuy Trang chủ">
                    <span class="bg-bb-yellow text-bb-dark font-black text-lg px-2 py-0.5 rounded shadow-sm">Best</span>
                    <span class="text-white font-bold text-lg hidden sm:inline">Buy</span>
                </a>

                <!-- Search Bar -->
                <div class="flex-1 relative" id="search-wrapper">
                    <form action="/search" method="GET" class="relative" autocomplete="off">
                        <input 
                            type="text" 
                            name="q" 
                            id="search-input"
                            placeholder="Tìm kiếm sản phẩm, thương hiệu..." 
                            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                            class="search-input w-full py-2.5 pl-4 pr-12 rounded-full text-gray-800 text-sm bg-white border-2 border-transparent focus:border-bb-yellow transition-all duration-200"
                        >
                        <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 bg-bb-yellow hover:bg-yellow-300 text-bb-dark p-2 rounded-full transition-colors" aria-label="Tìm kiếm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </button>
                    </form>
                    <!-- Live search dropdown -->
                    <div id="search-dropdown" class="search-dropdown" role="listbox" aria-live="polite" aria-label="Kết quả tìm kiếm"></div>
                </div>

                    <!-- Account — Dynamic based on login state -->
                    <?php if (!empty($_SESSION['user'])): ?>
                        <!-- Logged in: Show user menu -->
                        <div class="hidden md:block relative" id="user-menu-wrapper">
                            <button id="user-menu-btn" class="flex items-center gap-2 text-white hover:text-bb-yellow transition-colors text-sm">
                                <div class="w-7 h-7 bg-bb-yellow/20 rounded-full flex items-center justify-center text-bb-yellow text-xs font-bold border border-bb-yellow/30">
                                    <?= strtoupper(mb_substr($_SESSION['user']['name'], 0, 1)) ?>
                                </div>
                                <span class="hidden lg:inline max-w-[100px] truncate"><?= htmlspecialchars($_SESSION['user']['name']) ?></span>
                                <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <!-- Dropdown menu -->
                            <div id="user-dropdown" class="hidden absolute right-0 top-full mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50">
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($_SESSION['user']['name']) ?></p>
                                    <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($_SESSION['user']['email']) ?></p>
                                </div>
                                <a href="/profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-bb-blue transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    Hồ sơ & Đơn hàng
                                </a>
                                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                <a href="/admin/" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-bb-blue transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Quản trị
                                </a>
                                <?php endif; ?>
                                <div class="border-t border-gray-100">
                                    <a href="/auth/logout.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                        Đăng xuất
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Not logged in: Login link -->
                        <a href="/auth/login.php" class="hidden md:flex items-center gap-2 text-white hover:text-bb-yellow transition-colors text-sm">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <span class="hidden lg:inline">Đăng nhập</span>
                        </a>
                    <?php endif; ?>

                    <!-- Wishlist -->
                    <a href="/wishlist" class="relative flex items-center gap-2 text-white hover:text-red-400 transition-colors text-sm group mr-2">
                        <div class="relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            <span id="wishlist-badge" class="absolute -top-2 -right-2.5 bg-red-500 text-white text-[10px] font-black min-w-[20px] h-5 rounded-full flex items-center justify-center shadow <?= ($wishlistCount ?? 0) > 0 ? '' : 'hidden' ?>"><?= $wishlistCount ?? 0 ?></span>
                        </div>
                    </a>

                    <!-- Cart -->
                    <button type="button" id="cart-link" onclick="openCartDrawer()" class="relative flex items-center gap-2 text-white hover:text-bb-yellow transition-colors text-sm group">
                        <div class="relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
                            <span id="cart-badge" class="cart-badge absolute -top-2 -right-2.5 bg-bb-yellow text-bb-dark text-[10px] font-black min-w-[20px] h-5 rounded-full flex items-center justify-center shadow <?= $cartCount > 0 ? '' : 'hidden' ?>"><?= $cartCount ?></span>
                        </div>
                        <span class="hidden lg:inline">Giỏ hàng</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ CATEGORY NAVIGATION BAR ═══ -->
        <nav class="bg-bb-dark/50 border-t border-white/10 hidden md:block">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex items-center gap-1 py-0 overflow-x-auto no-scrollbar">
                    <!-- Tất cả sản phẩm -->
                    <a href="/" class="category-pill whitespace-nowrap px-4 py-2.5 text-sm font-medium text-white/80 hover:text-white rounded-t-lg transition-all <?= empty($activeCategory ?? '') ? 'active !text-white' : '' ?>">
                        🏠 Tất cả
                    </a>
                    <?php foreach ($navCategories as $cat): ?>
                        <a href="/danh-muc/<?= htmlspecialchars($cat['slug']) ?>" 
                           class="category-pill whitespace-nowrap px-4 py-2.5 text-sm font-medium text-white/80 hover:text-white rounded-t-lg transition-all <?= ($activeCategory ?? '') === $cat['slug'] ? 'active !text-white' : '' ?>">
                            <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <!-- Deal tag -->
                    <a href="/search?deals=1" class="category-pill whitespace-nowrap px-4 py-2.5 text-sm font-medium text-bb-yellow hover:text-yellow-300 rounded-t-lg transition-all flex items-center gap-1">
                        🔥 Deal Hot
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- ═══ MOBILE MENU OVERLAY ═══ -->
    <div id="mobile-overlay" class="mobile-overlay fixed inset-0 bg-black/50 z-[60] hidden"></div>
    <div id="mobile-menu" class="mobile-menu fixed top-0 left-0 bottom-0 w-80 max-w-[85vw] bg-white z-[70] shadow-2xl overflow-y-auto">
        <div class="p-4 bg-bb-blue text-white flex items-center justify-between">
            <span class="font-bold text-lg">Menu</span>
            <button id="mobile-menu-close" class="p-1 hover:bg-white/20 rounded-lg transition-colors" aria-label="Đóng">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-3 font-semibold">Danh mục sản phẩm</p>
            <nav class="space-y-1">
                <a href="/" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                    <span>🏠</span> <span>Tất cả sản phẩm</span>
                </a>
                <?php foreach ($navCategories as $cat): ?>
                    <a href="/danh-muc/<?= htmlspecialchars($cat['slug']) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                        <span><?= $cat['icon'] ?></span> <span><?= htmlspecialchars($cat['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <hr class="my-4 border-gray-200">
            <nav class="space-y-1">
                <button type="button" onclick="openCartDrawer()" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors text-left">
                    <span>🛒</span> <span>Giỏ hàng</span>
                    <span id="mobile-cart-badge" class="<?= $cartCount > 0 ? '' : 'hidden' ?> ml-auto bg-bb-blue text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $cartCount ?></span>
                </button>
                <?php if (!empty($_SESSION['user'])): ?>
                    <a href="/profile" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                        <span>👤</span> <span>Hồ sơ & Đơn hàng</span>
                    </a>
                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="/admin/" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                        <span>⚙️</span> <span>Quản trị</span>
                    </a>
                    <?php endif; ?>
                    <a href="/auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-50 text-red-500 transition-colors">
                        <span>🚪</span> <span>Đăng xuất</span>
                    </a>
                <?php else: ?>
                    <a href="/auth/login.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-100 text-gray-700 transition-colors">
                        <span>🔑</span> <span>Đăng nhập</span>
                    </a>
                    <a href="/auth/register.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-blue-50 text-bb-blue transition-colors">
                        <span>✨</span> <span>Đăng ký</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- Main content wrapper -->
    <main class="flex-1">

