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
    private const HOST     = '127.0.0.1';
    private const PORT     = 3306;
    private const DBNAME   = 'bestbuy_store';
    private const USERNAME = 'root';
    private const PASSWORD = '';
    private const CHARSET  = 'utf8mb4';

    // ── Singleton instance ──
    private static ?PDO $instance = null;

    /**
     * Không cho phép tạo instance bên ngoài (Singleton)
     */
    private function __construct() {}
    private function __clone() {}

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
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                self::HOST,
                self::PORT,
                self::DBNAME,
                self::CHARSET
            );

            self::$instance = new PDO($dsn, self::USERNAME, self::PASSWORD, [
                // Ném Exception khi có lỗi SQL → dễ debug
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                // Trả về kết quả dạng associative array → gọn gàng
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // QUAN TRỌNG: Tắt emulate → dùng Prepared Statements thật
                // Đây là tuyến phòng thủ chính chống SQL Injection
                PDO::ATTR_EMULATE_PREPARES   => false,

                // Persistent connection → tái sử dụng kết nối giữa các request
                PDO::ATTR_PERSISTENT         => true,
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
