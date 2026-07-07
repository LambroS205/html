<?php
$appConfig = require dirname(__DIR__) . '/config/app.php';
$dbConfig = $appConfig['db'];
$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['charset']);
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "Creating DB bestbuy_store_v2...\n";
$pdo->exec("DROP DATABASE IF EXISTS `bestbuy_store_v2`");
$pdo->exec("CREATE DATABASE `bestbuy_store_v2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `bestbuy_store_v2`");

// Lấy tất cả scripts
$scripts = [
    __DIR__ . '/schema.sql',
    __DIR__ . '/migration_step2.sql',
    __DIR__ . '/migration_step3.sql',
    __DIR__ . '/migration_step5.sql',
    __DIR__ . '/migration_step6_reviews.sql',
    __DIR__ . '/migration_step7_variants.sql',
    __DIR__ . '/migration_step8_audit.sql'
];

foreach ($scripts as $script) {
    if (file_exists($script)) {
        echo "Running $script...\n";
        $sql = file_get_contents($script);
        $sql = str_replace('bestbuy_store', 'bestbuy_store_v2', $sql);
        try {
            $pdo->exec($sql);
            echo "Success.\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Not found: $script\n";
    }
}
