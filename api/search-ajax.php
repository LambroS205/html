<?php
/**
 * API Live Search — BestBuy Store
 * Endpoint riêng biệt cho tìm kiếm AJAX, tối ưu tốc độ
 * 
 * Request:  GET /api/search-ajax.php?q=keyword
 * Response: JSON { results: [...], query: "...", total: N }
 * 
 * Tối ưu hóa:
 * - FULLTEXT MATCH...AGAINST cho từ khóa >= 3 ký tự (dùng index sẵn có)
 * - LIKE fallback cho từ khóa ngắn < 3 ký tự
 * - Chỉ SELECT cột cần thiết cho dropdown → giảm data transfer
 * - LIMIT 8 kết quả → đủ cho dropdown, không quá tải
 * - Prepared Statement → chống SQL Injection
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';

// Chỉ chấp nhận GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['results' => [], 'query' => '', 'total' => 0, 'error' => 'Method not allowed']);
    exit;
}

$query = trim($_GET['q'] ?? '');

// Từ khóa quá ngắn → trả rỗng
if (mb_strlen($query) < 2) {
    echo json_encode(['results' => [], 'query' => $query, 'total' => 0]);
    exit;
}

$pdo = Database::getConnection();

try {
    /**
     * Chiến lược query:
     * - Từ khóa >= 3 ký tự: Dùng FULLTEXT MATCH...AGAINST (BOOLEAN MODE)
     *   → Tận dụng idx_search trên products(name, description) → O(log n) lookup
     *   → Thêm wildcard * để match prefix (ví dụ: "iPho" → "iPhone")
     * 
     * - Từ khóa < 3 ký tự: Fallback LIKE
     *   → FULLTEXT yêu cầu tối thiểu 3 ký tự (ft_min_word_len default)
     *   → LIKE vẫn đủ nhanh cho < 15 sản phẩm trên localhost
     */
    if (mb_strlen($query) >= 3) {
        // FULLTEXT search — tận dụng index, nhanh hơn LIKE cho dataset lớn
        // Thêm * wildcard cho prefix matching
        $fulltextQuery = '+' . preg_replace('/\s+/', '* +', $query) . '*';
        
        $sql = "
            SELECT 
                p.id, p.name, p.slug, p.price, p.sale_price, p.image, p.stock,
                c.name AS category_name, c.icon AS category_icon,
                MATCH(p.name, p.description) AGAINST(:ft_query IN BOOLEAN MODE) AS relevance
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE MATCH(p.name, p.description) AGAINST(:ft_query2 IN BOOLEAN MODE)
               OR p.name LIKE :like_query
            ORDER BY relevance DESC, p.rating DESC
            LIMIT 8
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ft_query'    => $fulltextQuery,
            ':ft_query2'   => $fulltextQuery,
            ':like_query'  => "%{$query}%",
        ]);
    } else {
        // LIKE fallback cho từ khóa ngắn (2 ký tự)
        $sql = "
            SELECT 
                p.id, p.name, p.slug, p.price, p.sale_price, p.image, p.stock,
                c.name AS category_name, c.icon AS category_icon
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.name LIKE :like_query
            ORDER BY p.rating DESC, p.review_count DESC
            LIMIT 8
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':like_query' => "%{$query}%",
        ]);
    }

    $results = $stmt->fetchAll();

    // Xử lý image path — đảm bảo có fallback
    foreach ($results as &$row) {
        $row['price']      = (float) $row['price'];
        $row['sale_price'] = $row['sale_price'] ? (float) $row['sale_price'] : null;
        $row['stock']      = (int) $row['stock'];
        
        // Kiểm tra file ảnh tồn tại
        if (!empty($row['image'])) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($row['image'], '/');
            $row['image_url'] = file_exists($fullPath) ? '/' . ltrim($row['image'], '/') : '';
        } else {
            $row['image_url'] = '';
        }
    }
    unset($row);

    echo json_encode([
        'results' => $results,
        'query'   => $query,
        'total'   => count($results),
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'results' => [],
        'query'   => $query,
        'total'   => 0,
        'error'   => 'Database error',
    ], JSON_UNESCAPED_UNICODE);
}
