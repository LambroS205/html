-- ============================================
-- BestBuy Store - Step 6 Migration
-- Thêm bảng reviews
-- ============================================

USE `bestbuy_store`;

-- ============================================
-- BẢNG REVIEWS (Đánh giá sản phẩm)
-- ============================================
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK(`rating` BETWEEN 1 AND 5),
    `comment` TEXT,
    `images_json` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_reviews_product` (`product_id`),
    INDEX `idx_reviews_user` (`user_id`),
    
    CONSTRAINT `fk_reviews_product` 
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
        
    CONSTRAINT `fk_reviews_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✅ Migration Step 6 hoàn thành!' AS status;
SELECT CONCAT('⭐ Bảng reviews đã được tạo.') AS info;
