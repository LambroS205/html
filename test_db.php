<?php
/**
 * Test Database Connection - BestBuy Store
 * File này dùng để kiểm tra kết nối DB và dữ liệu seed.
 * Xóa file này sau khi xác nhận thành công.
 */

require_once __DIR__ . '/config/db.php';

// ── Styling đơn giản cho output ──
echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test DB Connection - BestBuy Store</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background: #0a0e27;
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            width: 100%;
            background: #12163a;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.05);
        }
        h1 {
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logo { color: #ffe000; font-weight: 900; }
        .check-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.8rem 1rem;
            margin: 0.4rem 0;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            font-size: 0.95rem;
        }
        .check-item.ok { border-left: 3px solid #00e676; }
        .check-item.fail { border-left: 3px solid #ff5252; }
        .icon { font-size: 1.2rem; }
        .label { flex: 1; }
        .value { color: #aaa; font-size: 0.85rem; }
        table {
            width: 100%;
            margin-top: 1.5rem;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        th {
            text-align: left;
            padding: 0.6rem 0.8rem;
            background: rgba(255,224,0,0.1);
            color: #ffe000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        td {
            padding: 0.5rem 0.8rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-sale { background: #ff5252; color: #fff; }
        .badge-stock { background: #00e676; color: #000; }
        .summary {
            margin-top: 1.5rem;
            padding: 1rem;
            background: rgba(0,230,118,0.08);
            border-radius: 8px;
            border: 1px solid rgba(0,230,118,0.2);
            text-align: center;
            color: #00e676;
            font-weight: 600;
        }
        .summary.fail-summary {
            background: rgba(255,82,82,0.08);
            border-color: rgba(255,82,82,0.2);
            color: #ff5252;
        }
    </style>
</head>
<body>
<div class="container">';

echo '<h1>🔌 <span class="logo">BestBuy</span> Store — Database Test</h1>';

$allPassed = true;

// ── Test 1: Kết nối MariaDB ──
try {
    $pdo = Database::getConnection();
    echo '<div class="check-item ok">
            <span class="icon">✅</span>
            <span class="label">Kết nối MariaDB</span>
            <span class="value">PDO OK</span>
          </div>';
} catch (PDOException $e) {
    $allPassed = false;
    echo '<div class="check-item fail">
            <span class="icon">❌</span>
            <span class="label">Kết nối MariaDB</span>
            <span class="value">' . htmlspecialchars($e->getMessage()) . '</span>
          </div>';
    echo '</div></body></html>';
    exit;
}

// ── Test 2: Kiểm tra MariaDB version ──
$version = $pdo->query("SELECT VERSION() AS ver")->fetch();
echo '<div class="check-item ok">
        <span class="icon">✅</span>
        <span class="label">MariaDB Version</span>
        <span class="value">' . htmlspecialchars($version['ver']) . '</span>
      </div>';

// ── Test 3: Kiểm tra bảng ──
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = ['categories', 'products', 'orders', 'order_items'];
$missingTables = array_diff($expectedTables, $tables);

if (empty($missingTables)) {
    echo '<div class="check-item ok">
            <span class="icon">✅</span>
            <span class="label">Cấu trúc bảng</span>
            <span class="value">' . count($tables) . ' bảng (categories, products, orders, order_items)</span>
          </div>';
} else {
    $allPassed = false;
    echo '<div class="check-item fail">
            <span class="icon">❌</span>
            <span class="label">Thiếu bảng</span>
            <span class="value">' . implode(', ', $missingTables) . '</span>
          </div>';
}

// ── Test 4: Đếm danh mục ──
$catCount = $pdo->query("SELECT COUNT(*) AS cnt FROM categories")->fetch();
echo '<div class="check-item ok">
        <span class="icon">✅</span>
        <span class="label">Danh mục sản phẩm</span>
        <span class="value">' . $catCount['cnt'] . ' danh mục</span>
      </div>';

// ── Test 5: Đếm sản phẩm ──
$prodCount = $pdo->query("SELECT COUNT(*) AS cnt FROM products")->fetch();
echo '<div class="check-item ok">
        <span class="icon">✅</span>
        <span class="label">Sản phẩm</span>
        <span class="value">' . $prodCount['cnt'] . ' sản phẩm</span>
      </div>';

// ── Hiển thị bảng sản phẩm mẫu ──
$products = $pdo->query("
    SELECT p.name, c.icon, c.name AS category, p.price, p.sale_price, p.stock, p.rating
    FROM products p
    JOIN categories c ON p.category_id = c.id
    ORDER BY p.id ASC
")->fetchAll();

echo '<table>
        <thead>
            <tr>
                <th>Sản phẩm</th>
                <th>Danh mục</th>
                <th>Giá gốc</th>
                <th>Giảm giá</th>
                <th>Kho</th>
                <th>Rating</th>
            </tr>
        </thead>
        <tbody>';

foreach ($products as $p) {
    $saleHtml = $p['sale_price']
        ? '<span class="badge badge-sale">' . number_format($p['sale_price'], 0, ',', '.') . ' VNĐ</span>'
        : '—';

    echo '<tr>
            <td>' . htmlspecialchars($p['name']) . '</td>
            <td>' . $p['icon'] . ' ' . htmlspecialchars($p['category']) . '</td>
            <td>' . number_format($p['price'], 0, ',', '.') . ' VNĐ</td>
            <td>' . $saleHtml . '</td>
            <td><span class="badge badge-stock">' . $p['stock'] . '</span></td>
            <td>⭐ ' . $p['rating'] . '</td>
          </tr>';
}

echo '</tbody></table>';

// ── Kết luận ──
if ($allPassed) {
    echo '<div class="summary">
            🎉 TẤT CẢ KIỂM TRA ĐỀU THÀNH CÔNG — Sẵn sàng cho Bước 2!
          </div>';
} else {
    echo '<div class="summary fail-summary">
            ⚠️ Một số kiểm tra thất bại. Vui lòng kiểm tra lại cấu hình.
          </div>';
}

echo '</div></body></html>';
