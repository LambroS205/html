-- ============================================
-- BestBuy Store - Step 11 Migration
-- Feature: Blog / CMS System
-- ============================================

USE `bestbuy_store_v2`; 

-- 1. Bảng danh mục bài viết
CREATE TABLE IF NOT EXISTS `post_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some default categories
INSERT IGNORE INTO `post_categories` (`id`, `name`, `slug`) VALUES 
(1, 'Tin công nghệ', 'tin-cong-nghe'),
(2, 'Đánh giá sản phẩm', 'danh-gia-san-pham'),
(3, 'Khuyến mãi', 'khuyen-mai'),
(4, 'Thủ thuật', 'thu-thuat');

-- 2. Bảng bài viết (Posts)
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `author_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `excerpt` TEXT NULL,
    `content` LONGTEXT NOT NULL,
    `cover_image` VARCHAR(255) NULL,
    `status` ENUM('published', 'draft') DEFAULT 'draft',
    `views` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_post_category`
        FOREIGN KEY (`category_id`) REFERENCES `post_categories`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_post_author`
        FOREIGN KEY (`author_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✅ Database Migration (Step 11 - Blog/CMS) hoàn thành!' AS status;
