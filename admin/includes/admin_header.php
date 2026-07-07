<?php
/**
 * Admin Header — BestBuy Store Admin Panel
 * Layout riêng cho khu vực quản trị: sidebar + top bar
 * 
 * Biến cần set trước khi include:
 *  - $adminPage (string): Tên trang active ('dashboard', 'products', 'orders')
 *  - $adminTitle (string): Tiêu đề trang
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); session_start();
}

// ── Bảo vệ khu vực Admin ──
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}


require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$pdo = Database::getConnection();

// Quick stats cho sidebar
$statProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$statOrders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$statRevenue  = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminTitle ?? 'Admin Panel') ?> — BestBuy Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%23001E73'/><text x='50%25' y='55%25' dominant-baseline='middle' text-anchor='middle' font-size='14' font-weight='900' fill='%23FFE000'>A</text></svg>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'bb-blue': '#0046BE', 'bb-dark': '#001E73', 'bb-navy': '#040C43',
                    'bb-yellow': '#FFE000', 'bb-light': '#F0F2F5', 'bb-gray': '#55555A',
                    'admin-bg': '#0f172a', 'admin-card': '#1e293b', 'admin-border': '#334155',
                },
                fontFamily: { 'inter': ['Inter', 'system-ui', 'sans-serif'] }
            }
        }
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="font-inter bg-admin-bg text-gray-200 min-h-screen">
    <div class="flex min-h-screen">

        <!-- ═══ SIDEBAR ═══ -->
        <aside class="w-64 bg-admin-card border-r border-admin-border flex flex-col shrink-0 fixed h-full z-30 hidden lg:flex">
            <!-- Logo -->
            <div class="p-5 border-b border-admin-border">
                <a href="/admin/" class="flex items-center gap-2">
                    <span class="bg-bb-yellow text-bb-dark font-black text-sm px-2 py-0.5 rounded">Best</span>
                    <span class="text-white font-bold">Buy</span>
                    <span class="text-xs bg-blue-500/20 text-blue-400 px-2 py-0.5 rounded-full font-medium ml-1">Admin</span>
                </a>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 py-4 px-3 space-y-1">
                <a href="/admin/" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm <?= ($adminPage ?? '') === 'dashboard' ? 'active' : 'text-gray-400' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Dashboard
                </a>
                <a href="/admin/products.php" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm <?= ($adminPage ?? '') === 'products' ? 'active' : 'text-gray-400' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    Sản phẩm
                    <span class="ml-auto bg-admin-bg text-xs px-2 py-0.5 rounded-full"><?= $statProducts ?></span>
                </a>
                <a href="/admin/attributes.php" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm <?= ($adminPage ?? '') === 'attributes' ? 'active' : 'text-gray-400' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    Thuộc tính
                </a>
                <a href="/admin/orders.php" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm <?= ($adminPage ?? '') === 'orders' ? 'active' : 'text-gray-400' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Đơn hàng
                    <span class="ml-auto bg-admin-bg text-xs px-2 py-0.5 rounded-full"><?= $statOrders ?></span>
                </a>

                <div class="pt-4 mt-4 border-t border-admin-border">
                    <a href="/" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-gray-400" target="_blank">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        Xem cửa hàng ↗
                    </a>
                </div>
            </nav>

            <!-- Stats footer -->
            <div class="p-4 border-t border-admin-border">
                <div class="bg-admin-bg rounded-xl p-3">
                    <p class="text-xs text-gray-500 mb-1">Tổng doanh thu</p>
                    <p class="text-lg font-bold text-bb-yellow"><?= formatPrice((float)$statRevenue) ?></p>
                </div>
            </div>
        </aside>

        <!-- ═══ MAIN CONTENT ═══ -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Bar -->
            <header class="bg-admin-card border-b border-admin-border px-6 py-4 flex items-center justify-between sticky top-0 z-20">
                <!-- Mobile menu -->
                <button id="admin-mobile-menu" class="lg:hidden text-gray-400 hover:text-white" onclick="document.querySelector('aside').classList.toggle('hidden')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>

                <div>
                    <h1 class="text-lg font-bold text-white"><?= htmlspecialchars($adminTitle ?? 'Dashboard') ?></h1>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500"><?= date('d/m/Y H:i') ?></span>
                    <div class="w-8 h-8 bg-bb-blue rounded-full flex items-center justify-center text-white text-xs font-bold">A</div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">


