<?php
/**
 * Application Configuration
 * Tập trung toàn bộ cấu hình hệ thống
 */

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'bestbuy_store_v2',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'base_url' => 'http://localhost', // Thay đổi base URL tùy vào cấu hình Nginx
        'env' => 'development', // 'development' or 'production'
    ]
];
