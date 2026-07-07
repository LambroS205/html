-- ============================================
-- BestBuy Store - Step 10 Migration
-- Feature: Wishlist (Danh sách yêu thích)
-- ============================================

-- Chú ý: Đảm bảo sử dụng đúng database (nếu dùng versioning, hãy sửa lại cho đúng, ví dụ bestbuy_store_v2)
USE `bestbuy_store_v2`; 
-- Hiện tại hệ thống đang sử dụng chung kết nối PDO từ config/db.php nên script này được thiết kế để chạy trực tiếp trên CSDL đang dùng.

CREATE TABLE IF NOT EXISTS `wishlists` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Đảm bảo 1 user chỉ thêm 1 sản phẩm 1 lần
    UNIQUE KEY `uk_user_product` (`user_id`, `product_id`),

    -- Khóa ngoại
    CONSTRAINT `fk_wishlist_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_wishlist_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✅ Database Migration (Step 10 - Wishlist) hoàn thành!' AS status;
