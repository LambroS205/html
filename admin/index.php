<?php
/**
 * Admin Dashboard — BestBuy Store
 * Tổng quan: thống kê, đơn hàng gần đây, sản phẩm bán chạy
 */

$adminPage  = 'dashboard';
$adminTitle = 'Dashboard';

require_once __DIR__ . '/includes/admin_header.php';

// ── Stats ──
$todayOrders  = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$lowStock     = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE stock <= 5")->fetchColumn();

// ── Đơn hàng gần đây ──
$recentOrders = $pdo->query("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM orders o
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetchAll();

// ── Sản phẩm bán chạy ──
$topProducts = $pdo->query("
    SELECT p.id, p.name, c.icon AS category_icon,
           MIN(pv.price) as price, MIN(pv.sale_price) as sale_price, SUM(pv.stock) as stock,
           (SELECT image_url FROM product_variants WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as image,
           COALESCE(SUM(oi.quantity), 0) AS total_sold
    FROM products p
    LEFT JOIN product_variants pv ON pv.product_id = p.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    GROUP BY p.id
    ORDER BY total_sold DESC, p.rating DESC
    LIMIT 5
")->fetchAll();

// ── Chart Data: Doanh thu 7 ngày ──
$revenueChartData = $pdo->query("
    SELECT DATE(created_at) as date, SUM(total) as revenue
    FROM orders
    WHERE created_at >= DATE(NOW() - INTERVAL 6 DAY) AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
")->fetchAll();

// ── Chart Data: Trạng thái đơn hàng ──
$statusChartData = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM orders
    GROUP BY status
")->fetchAll();
?>

    <!-- ═══ STAT CARDS ═══ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-admin-card rounded-2xl p-5 border border-admin-border">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm">Tổng sản phẩm</span>
                <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-white"><?= $statProducts ?></p>
            <?php if ($lowStock > 0): ?>
                <p class="text-xs text-orange-400 mt-1">⚠ <?= $lowStock ?> sản phẩm sắp hết hàng</p>
            <?php endif; ?>
        </div>

        <div class="bg-admin-card rounded-2xl p-5 border border-admin-border">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm">Tổng đơn hàng</span>
                <div class="w-10 h-10 bg-green-500/10 rounded-xl flex items-center justify-center text-green-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-white"><?= $statOrders ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= $todayOrders ?> đơn hôm nay · <?= $pendingOrders ?> chờ xử lý</p>
        </div>

        <div class="bg-admin-card rounded-2xl p-5 border border-admin-border">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm">Doanh thu</span>
                <div class="w-10 h-10 bg-yellow-500/10 rounded-xl flex items-center justify-center text-yellow-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-bb-yellow"><?= formatPrice((float)$statRevenue) ?></p>
        </div>

        <div class="bg-admin-card rounded-2xl p-5 border border-admin-border">
            <div class="flex items-center justify-between mb-3">
                <span class="text-gray-400 text-sm">Đơn chờ xử lý</span>
                <div class="w-10 h-10 bg-orange-500/10 rounded-xl flex items-center justify-center text-orange-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-white"><?= $pendingOrders ?></p>
        </div>
    </div>

    <!-- ═══ CHARTS ═══ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Revenue Chart -->
        <div class="bg-admin-card rounded-2xl border border-admin-border p-5 lg:col-span-2">
            <h2 class="font-bold text-white mb-4">Doanh thu 7 ngày qua</h2>
            <div class="relative h-72 w-full">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Order Status Chart -->
        <div class="bg-admin-card rounded-2xl border border-admin-border p-5 lg:col-span-1">
            <h2 class="font-bold text-white mb-4">Trạng thái đơn hàng</h2>
            <div class="relative h-72 w-full flex justify-center">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- ═══ RECENT ORDERS ═══ -->
        <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
            <div class="px-5 py-4 border-b border-admin-border flex items-center justify-between">
                <h2 class="font-bold text-white">Đơn hàng gần đây</h2>
                <a href="/admin/orders.php" class="text-xs text-bb-yellow hover:text-yellow-300 font-medium">Xem tất cả →</a>
            </div>
            <?php if (empty($recentOrders)): ?>
                <div class="p-8 text-center text-gray-500 text-sm">Chưa có đơn hàng nào</div>
            <?php else: ?>
                <div class="divide-y divide-admin-border">
                    <?php foreach ($recentOrders as $o):
                        $statusColors = ['pending' => 'text-orange-400 bg-orange-400/10', 'processing' => 'text-blue-400 bg-blue-400/10', 'shipped' => 'text-purple-400 bg-purple-400/10', 'delivered' => 'text-green-400 bg-green-400/10', 'cancelled' => 'text-red-400 bg-red-400/10'];
                        $statusLabels = ['pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'shipped' => 'Đang giao', 'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy'];
                        $sc = $statusColors[$o['status']] ?? 'text-gray-400 bg-gray-400/10';
                        $sl = $statusLabels[$o['status']] ?? $o['status'];
                    ?>
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-admin-bg/30 transition-colors">
                        <div>
                            <p class="text-sm font-semibold text-white"><?= htmlspecialchars($o['order_code']) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($o['customer_name']) ?> · <?= $o['item_count'] ?> SP</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-white"><?= formatPrice((float)$o['total']) ?></p>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full <?= $sc ?>"><?= $sl ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ TOP PRODUCTS ═══ -->
        <div class="bg-admin-card rounded-2xl border border-admin-border overflow-hidden">
            <div class="px-5 py-4 border-b border-admin-border flex items-center justify-between">
                <h2 class="font-bold text-white">Sản phẩm nổi bật</h2>
                <a href="/admin/products.php" class="text-xs text-bb-yellow hover:text-yellow-300 font-medium">Quản lý →</a>
            </div>
            <div class="divide-y divide-admin-border">
                <?php foreach ($topProducts as $tp):
                    $img = getProductImage($tp['image'] ?? '');
                ?>
                <div class="px-5 py-3 flex items-center gap-3 hover:bg-admin-bg/30 transition-colors">
                    <div class="w-10 h-10 bg-admin-bg rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
                        <?php if ($img): ?>
                            <img src="<?= htmlspecialchars($img) ?>" alt="" class="w-full h-full object-contain p-1">
                        <?php else: ?>
                            <span class="text-lg"><?= $tp['category_icon'] ?? '📦' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($tp['name']) ?></p>
                        <p class="text-xs text-gray-500">Tồn kho: <?= $tp['stock'] ?> · Đã bán: <?= $tp['total_sold'] ?></p>
                    </div>
                    <span class="text-sm font-bold text-bb-yellow whitespace-nowrap"><?= formatPrice((float)($tp['sale_price'] ?? $tp['price'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ═══ CHART SCRIPT ═══ -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Parse data from PHP
        const revenueDataRaw = <?= json_encode($revenueChartData) ?>;
        const statusDataRaw = <?= json_encode($statusChartData) ?>;

        // Process Revenue Data (fill missing days)
        const labels = [];
        const dataRevenue = [];
        // Generate last 7 days labels
        for (let i = 6; i >= 0; i--) {
            const d = new Date();
            d.setDate(d.getDate() - i);
            const dateStr = d.toISOString().split('T')[0];
            const shortDate = d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
            
            labels.push(shortDate);
            // Find if we have revenue for this date
            const record = revenueDataRaw.find(r => r.date === dateStr);
            dataRevenue.push(record ? parseFloat(record.revenue) : 0);
        }

        // Configure Chart.js global defaults for Dark Mode
        Chart.defaults.color = '#9ca3af'; // gray-400
        Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
        Chart.defaults.font.family = "'Inter', sans-serif";

        // Initialize Revenue Line Chart
        const ctxRev = document.getElementById('revenueChart').getContext('2d');
        
        // Gradient for line chart
        const gradient = ctxRev.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(253, 216, 53, 0.5)'); // bb-yellow
        gradient.addColorStop(1, 'rgba(253, 216, 53, 0.0)');

        new Chart(ctxRev, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Doanh thu ($)',
                    data: dataRevenue,
                    borderColor: '#fdd835', // bb-yellow
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#1f2937',
                    pointBorderColor: '#fdd835',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4 // smooth curve
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleColor: '#fff',
                        bodyColor: '#e5e7eb',
                        borderColor: 'rgba(255,255,255,0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });

        // Process Status Data
        const statusMap = {
            'pending': { label: 'Chờ xử lý', color: '#fb923c' },     // orange-400
            'processing': { label: 'Đang xử lý', color: '#60a5fa' },  // blue-400
            'shipped': { label: 'Đang giao', color: '#c084fc' },      // purple-400
            'delivered': { label: 'Đã giao', color: '#4ade80' },      // green-400
            'cancelled': { label: 'Đã hủy', color: '#f87171' }        // red-400
        };

        const statusLabels = [];
        const statusCounts = [];
        const statusColors = [];

        statusDataRaw.forEach(item => {
            const config = statusMap[item.status] || { label: item.status, color: '#9ca3af' };
            statusLabels.push(config.label);
            statusCounts.push(parseInt(item.count));
            statusColors.push(config.color);
        });

        // If no data, show a dummy empty slice
        if (statusCounts.length === 0) {
            statusLabels.push('Chưa có đơn hàng');
            statusCounts.push(1);
            statusColors.push('rgba(255,255,255,0.05)');
        }

        // Initialize Order Status Doughnut Chart
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: statusColors,
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                }
            }
        });
    });
    </script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
