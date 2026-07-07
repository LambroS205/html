-- ============================================
-- BestBuy Store - Step 13 Migration
-- Feature: Database Cart (Per-User Cart)
-- ============================================

USE `bestbuy_store_v2`; 

CREATE TABLE IF NOT EXISTS `cart_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `variant_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- A user can only have one row per variant in their cart
    UNIQUE KEY `unique_user_variant` (`user_id`, `variant_id`),

    CONSTRAINT `fk_cart_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_cart_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_cart_variant`
        FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✅ Database Migration (Step 13 - Cart) hoàn thành!' AS status;
