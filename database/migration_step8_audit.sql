-- ============================================
-- BestBuy Store - Step 8 Migration
-- Database Audit, Cleanup & Indexing
-- ============================================

USE `bestbuy_store_v2`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. KIỂM TRA & CẬP NHẬT RÀNG BUỘC (FOREIGN KEYS)
-- ============================================

-- products -> categories (Sửa từ RESTRICT sang SET NULL an toàn)
ALTER TABLE `products` MODIFY `category_id` INT NULL;
ALTER TABLE `products` DROP FOREIGN KEY `fk_products_category`;
ALTER TABLE `products` ADD CONSTRAINT `fk_products_category` 
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- order_items -> products (Sửa từ RESTRICT sang SET NULL để giữ bill cũ nếu xóa SP)
ALTER TABLE `order_items` MODIFY `product_id` INT NULL;
ALTER TABLE `order_items` DROP FOREIGN KEY `fk_order_items_product`;
ALTER TABLE `order_items` ADD CONSTRAINT `fk_order_items_product` 
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE;

-- (order_items -> orders đã là CASCADE; order_items -> product_variants đã là SET NULL)
-- (orders -> users đã là SET NULL)

-- ============================================
-- 2. TỐI ƯU HÓA INDEX
-- ============================================

-- Thêm các chỉ mục (INDEX) bổ sung cho các cột thường xuyên query
-- (products.slug đã có UNIQUE KEY nên không cần add)
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_price` (`price`);
ALTER TABLE `reviews` ADD INDEX IF NOT EXISTS `idx_reviews_rating` (`rating`);
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_rating` (`rating`);

-- ============================================
-- 3. DỌN DẸP DỮ LIỆU RÁC (Garbage Collection)
-- ============================================

-- Xóa đơn hàng rác (không có thông tin khách hàng)
DELETE FROM `orders` WHERE `customer_name` = '' OR `customer_email` = '' OR `customer_name` IS NULL;

-- Xóa review trống (không có nội dung chữ lẫn ảnh)
DELETE FROM `reviews` WHERE (`comment` = '' OR `comment` IS NULL) AND (`images_json` = '' OR `images_json` IS NULL OR `images_json` = '[]');

SET FOREIGN_KEY_CHECKS = 1;

SELECT '✅ Database Audit & Optimization (Step 8) hoàn thành!' AS status;
