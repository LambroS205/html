-- ============================================
-- BestBuy Store - Step 7 Migration
-- Cấu trúc Sản phẩm nhiều Biến thể (SKUs)
-- ============================================

USE `bestbuy_store`;

SET FOREIGN_KEY_CHECKS=0;

-- 1. BẢNG ATTRIBUTES (Thuộc tính)
CREATE TABLE IF NOT EXISTS `attributes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG ATTRIBUTE_VALUES (Giá trị thuộc tính)
CREATE TABLE IF NOT EXISTS `attribute_values` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attribute_id` INT NOT NULL,
    `value` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG PRODUCT_VARIANTS (Biến thể sản phẩm)
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `sku` VARCHAR(100) NOT NULL UNIQUE,
    `price` DECIMAL(12,2) NOT NULL,
    `sale_price` DECIMAL(12,2) DEFAULT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `image_url` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG VARIANT_ATTRIBUTE_VALUES (Liên kết biến thể và giá trị thuộc tính)
CREATE TABLE IF NOT EXISTS `variant_attribute_values` (
    `variant_id` INT NOT NULL,
    `attribute_value_id` INT NOT NULL,
    PRIMARY KEY (`variant_id`, `attribute_value_id`),
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATA MIGRATION: Chuyển dữ liệu cũ sang biến thể mặc định
-- ============================================
INSERT IGNORE INTO `product_variants` (`product_id`, `sku`, `price`, `sale_price`, `stock`, `image_url`)
SELECT `id`, CONCAT('SKU-DEFAULT-', `id`), `price`, `sale_price`, `stock`, `image` 
FROM `products`;

-- Cập nhật thông tin chi tiết đơn hàng (order_items)
ALTER TABLE `order_items`
    ADD COLUMN `variant_id` INT DEFAULT NULL AFTER `product_id`,
    ADD CONSTRAINT `fk_order_items_variant` 
        FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL;

-- Cập nhật các sản phẩm cũ vào order_items (tìm variant mặc định vừa tạo)
UPDATE `order_items` oi
JOIN `product_variants` pv ON oi.product_id = pv.product_id
SET oi.variant_id = pv.id;

-- ============================================
-- DROP CỘT CŨ Ở BẢNG PRODUCTS
-- ============================================
-- Tạm thời xóa khóa ngoại và fulltext index liên quan (nếu có lỗi) nhưng ở đây ko dính
ALTER TABLE `products` 
    DROP COLUMN `price`,
    DROP COLUMN `sale_price`,
    DROP COLUMN `stock`,
    DROP COLUMN `image`,
    DROP COLUMN `specs`;

-- ============================================
-- SEED DATA: Thêm 4 biến thể cho iPhone 16 Pro Max
-- ============================================
-- Xóa variant mặc định của sản phẩm ID = 1 (iPhone) để tạo các variant chi tiết
DELETE FROM `product_variants` WHERE `product_id` = 1;

-- Thêm thuộc tính
INSERT IGNORE INTO `attributes` (`id`, `name`) VALUES (1, 'Màu sắc'), (2, 'Dung lượng');

-- Thêm giá trị thuộc tính
INSERT IGNORE INTO `attribute_values` (`id`, `attribute_id`, `value`) VALUES 
(1, 1, 'Titan Đen'), (2, 1, 'Titan Trắng'),
(3, 2, '256GB'), (4, 2, '512GB');

-- Thêm các phiên bản cho iPhone 16 Pro Max (product_id = 1)
INSERT INTO `product_variants` (`id`, `product_id`, `sku`, `price`, `sale_price`, `stock`, `image_url`) VALUES
(101, 1, 'IP16PM-BLK-256', 29975000, 27475000, 20, 'assets/images/iphone-16-pro-max.png'),
(102, 1, 'IP16PM-BLK-512', 35000000, 33000000, 15, 'assets/images/iphone-16-pro-max.png'),
(103, 1, 'IP16PM-WHT-256', 29975000, 27475000, 10, 'assets/images/iphone-16-pro-max-white.png'),
(104, 1, 'IP16PM-WHT-512', 35000000, 33000000, 5, 'assets/images/iphone-16-pro-max-white.png');

-- Liên kết thuộc tính với phiên bản
INSERT INTO `variant_attribute_values` (`variant_id`, `attribute_value_id`) VALUES
(101, 1), (101, 3), -- Đen 256GB
(102, 1), (102, 4), -- Đen 512GB
(103, 2), (103, 3), -- Trắng 256GB
(104, 2), (104, 4); -- Trắng 512GB

SELECT '✅ Migration Step 7 hoàn thành!' AS status;
SET FOREIGN_KEY_CHECKS=1;
