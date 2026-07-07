<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo = Database::getConnection();
    $sql = file_get_contents(__DIR__ . '/database/migration_step12_payments.sql');
    $pdo->exec($sql);
    echo "Success step 12";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
