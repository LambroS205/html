-- ============================================
-- BestBuy Store — Step 2 Migration
-- Thêm bảng users + cập nhật orders
-- ============================================

USE `bestbuy_store`;

-- ============================================
-- 1. BẢNG USERS (Tài khoản người dùng)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Bcrypt hash via password_hash()',
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. Thêm cột user_id vào bảng orders
-- ============================================
-- Cho phép NULL vì đơn hàng cũ (guest checkout) không có user_id
ALTER TABLE `orders` 
    ADD COLUMN `user_id` INT DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_orders_user_id` (`user_id`),
    ADD CONSTRAINT `fk_orders_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 3. Tạo tài khoản admin mặc định
-- Password: admin123 (bcrypt hash)
-- ============================================
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES
    ('Admin BestBuy', 'admin@bestbuy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
    ('Khách hàng Demo', 'demo@bestbuy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');

-- Mật khẩu mặc định cho cả 2 tài khoản: password
-- (hash trên là bcrypt hash của "password" — chuẩn Laravel/PHP)

SELECT '✅ Migration Step 2 hoàn thành!' AS status;
SELECT CONCAT('👤 Tổng users: ', COUNT(*)) AS info FROM users;
