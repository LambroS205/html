<?php
/**
 * Database Configuration - BestBuy Store
 * Kết nối MariaDB bằng PDO theo Singleton Pattern
 * 
 * Sử dụng: $pdo = Database::getConnection();
 */

class Database
{
    // ── Cấu hình kết nối ──
    private static ?array $config = null;

    // ── Singleton instance ──
    private static ?PDO $instance = null;

    /**
     * Không cho phép tạo instance bên ngoài (Singleton)
     */
    private function __construct() {}
    private function __clone() {}

    /**
     * Load config file
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            $appConfig = require __DIR__ . '/app.php';
            self::$config = $appConfig['db'];
        }
        return self::$config;
    }

    /**
     * Lấy kết nối PDO (tạo mới nếu chưa có)
     * 
     * Lý do dùng Singleton:
     * - Tránh mở nhiều kết nối DB thừa trên mỗi request
     * - Tiết kiệm tài nguyên trên localhost
     * - Đảm bảo tất cả queries dùng chung 1 connection
     *
     * @return PDO
     * @throws PDOException Khi kết nối thất bại
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = self::getConfig();
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                // Ném Exception khi có lỗi SQL → dễ debug
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                // Trả về kết quả dạng associative array → gọn gàng
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // QUAN TRỌNG: Tắt emulate → dùng Prepared Statements thật
                // Đây là tuyến phòng thủ chính chống SQL Injection
                PDO::ATTR_EMULATE_PREPARES   => false,

                // Bỏ qua Persistent connection để tránh rò rỉ kết nối trên môi trường web thông thường
            ]);
        }

        return self::$instance;
    }

    /**
     * Đóng kết nối (dùng khi cần cleanup)
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
