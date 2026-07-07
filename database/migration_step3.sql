-- ════════════════════════════════════════
-- MIGRATION BƯỚC 3: MÃ GIẢM GIÁ (COUPONS)
-- ════════════════════════════════════════

-- 1. Tạo bảng coupons
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
    `value` DECIMAL(12,2) NOT NULL,
    `min_order_value` DECIMAL(12,2) DEFAULT 0.00,
    `expiry_date` DATETIME NULL,
    `status` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm một số dữ liệu mã giảm giá mặc định
INSERT IGNORE INTO `coupons` (`code`, `type`, `value`, `min_order_value`, `expiry_date`, `status`) VALUES
('SUMMER20', 'percent', 20.00, 1250000, '2027-12-31 23:59:59', 1),
('MINUS10', 'fixed', 250000, 500000, '2027-12-31 23:59:59', 1),
('FREESHIP', 'fixed', 125000, 0, '2027-12-31 23:59:59', 1);

-- 2. Thêm cột coupon_id và discount_amount vào bảng orders
ALTER TABLE `orders` 
    ADD COLUMN `coupon_id` INT DEFAULT NULL AFTER `user_id`,
    ADD COLUMN `discount_amount` DECIMAL(12,2) DEFAULT 0.00 AFTER `subtotal`,
    ADD INDEX `idx_orders_coupon_id` (`coupon_id`),
    ADD CONSTRAINT `fk_orders_coupon` 
        FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE;
