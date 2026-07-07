<?php
require_once __DIR__ . '/../config/db.php';
$pdo = Database::getConnection();
print_r($pdo->query('SHOW COLUMNS FROM order_items')->fetchAll());
