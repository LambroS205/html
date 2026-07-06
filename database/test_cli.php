<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = Database::getConnection();
    echo "OK: Connected to MariaDB\n";
    
    $cnt = $pdo->query('SELECT COUNT(*) AS c FROM products')->fetch();
    echo "Products: " . $cnt['c'] . "\n";
    
    $cats = $pdo->query('SELECT COUNT(*) AS c FROM categories')->fetch();
    echo "Categories: " . $cats['c'] . "\n";
    
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
    
    echo "\nALL TESTS PASSED!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
