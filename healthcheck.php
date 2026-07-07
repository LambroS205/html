<?php
/**
 * System Health Check API
 * Trả về trạng thái hoạt động của ứng dụng (dùng cho Zabbix/Prometheus)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';

$status = [
    'status' => 'OK',
    'timestamp' => date('c'),
    'services' => [
        'php' => [
            'status' => 'OK',
            'version' => phpversion()
        ],
        'database' => [
            'status' => 'UNKNOWN'
        ],
        'disk' => [
            'status' => 'UNKNOWN',
            'free_space_mb' => 0
        ]
    ]
];

// Check Database Connection
try {
    $pdo = Database::getConnection();
    $pdo->query("SELECT 1");
    $status['services']['database']['status'] = 'OK';
} catch (Exception $e) {
    $status['status'] = 'ERROR';
    $status['services']['database']['status'] = 'ERROR';
    $status['services']['database']['message'] = 'Database connection failed';
}

// Check Disk Space (for the current drive)
$freeSpace = disk_free_space(__DIR__);
if ($freeSpace !== false) {
    $freeSpaceMB = round($freeSpace / 1024 / 1024, 2);
    $status['services']['disk']['free_space_mb'] = $freeSpaceMB;
    $status['services']['disk']['status'] = $freeSpaceMB > 500 ? 'OK' : 'WARNING'; // Warning if < 500MB
} else {
    $status['status'] = 'WARNING';
    $status['services']['disk']['status'] = 'ERROR';
    $status['services']['disk']['message'] = 'Could not read disk space';
}

// Set HTTP response code based on overall status
if ($status['status'] === 'ERROR') {
    http_response_code(503); // Service Unavailable
} else {
    http_response_code(200); // OK
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
