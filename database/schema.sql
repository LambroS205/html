-- ============================================
-- BestBuy Store - Database Schema & Seed Data
-- WEMP Stack (MariaDB)
-- ============================================

-- Tạo database
CREATE DATABASE IF NOT EXISTS `bestbuy_store` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `bestbuy_store`;

-- ============================================
-- 1. BẢNG CATEGORIES (Danh mục sản phẩm)
-- ============================================
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Emoji hoặc icon class',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. BẢNG PRODUCTS (Sản phẩm)
-- ============================================
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `specs` TEXT DEFAULT NULL COMMENT 'Thông số kỹ thuật dạng JSON',
    `price` DECIMAL(12,2) NOT NULL COMMENT 'Giá gốc (USD)',
    `sale_price` DECIMAL(12,2) DEFAULT NULL COMMENT 'Giá khuyến mãi',
    `image` VARCHAR(255) DEFAULT NULL COMMENT 'Đường dẫn hình ảnh',
    `stock` INT NOT NULL DEFAULT 50,
    `rating` DECIMAL(2,1) NOT NULL DEFAULT 0.0 COMMENT 'Đánh giá 0-5 sao',
    `review_count` INT NOT NULL DEFAULT 0,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Hiển thị trang chủ',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Key
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    -- Indexes tối ưu tốc độ truy vấn
    INDEX `idx_category_id` (`category_id`),
    INDEX `idx_is_featured` (`is_featured`),
    INDEX `idx_sale_price` (`sale_price`),
    FULLTEXT INDEX `idx_search` (`name`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. BẢNG ORDERS (Đơn hàng)
-- ============================================
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Mã đơn hàng BB-XXXXXX',
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_email` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `payment_method` ENUM('cod', 'card') NOT NULL DEFAULT 'cod',
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `shipping_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'VAT 10%',
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') 
        NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_order_code` (`order_code`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. BẢNG ORDER_ITEMS (Chi tiết đơn hàng)
-- ============================================
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(255) NOT NULL COMMENT 'Snapshot tên SP tại thời điểm mua',
    `price` DECIMAL(12,2) NOT NULL COMMENT 'Giá tại thời điểm mua',
    `quantity` INT NOT NULL DEFAULT 1,

    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_order_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- SEED DATA - Danh mục
-- ============================================
INSERT INTO `categories` (`name`, `slug`, `icon`) VALUES
    ('Điện thoại', 'dien-thoai', '📱'),
    ('Laptop', 'laptop', '💻'),
    ('Tivi', 'tivi', '📺'),
    ('Tai nghe', 'tai-nghe', '🎧');


-- ============================================
-- SEED DATA - Sản phẩm (10+ sản phẩm điện tử)
-- ============================================

-- ── ĐIỆN THOẠI (category_id = 1) ──
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `specs`, `price`, `sale_price`, `image`, `stock`, `rating`, `review_count`, `is_featured`) VALUES
(1, 'iPhone 16 Pro Max 256GB', 'iphone-16-pro-max-256gb',
    'Siêu phẩm flagship mới nhất từ Apple với chip A18 Pro, camera 48MP Tetraprism 5x Zoom, màn hình Super Retina XDR 6.9 inch và thời lượng pin kỷ lục.',
    '{"screen": "6.9 inch Super Retina XDR OLED", "chip": "Apple A18 Pro", "ram": "8GB", "storage": "256GB", "camera": "48MP + 48MP + 12MP", "battery": "4685 mAh", "os": "iOS 18", "weight": "227g"}',
    1199.00, 1099.00, 'assets/images/iphone-16-pro-max.png', 45, 4.8, 2340, 1),

(1, 'Samsung Galaxy S25 Ultra 512GB', 'samsung-galaxy-s25-ultra-512gb',
    'Điện thoại Galaxy AI đầu tiên với chip Snapdragon 8 Elite, bút S-Pen tích hợp, camera 200MP và khung viền Titanium siêu bền.',
    '{"screen": "6.8 inch Dynamic AMOLED 2X", "chip": "Snapdragon 8 Elite", "ram": "12GB", "storage": "512GB", "camera": "200MP + 50MP + 10MP + 12MP", "battery": "5000 mAh", "os": "Android 15 / One UI 7", "weight": "218g"}',
    1419.99, 1299.99, 'assets/images/samsung-galaxy-s25-ultra.png', 38, 4.7, 1856, 1),

(1, 'Google Pixel 9 Pro 256GB', 'google-pixel-9-pro-256gb',
    'Trải nghiệm AI thuần túy từ Google với Tensor G4, camera Magic Eraser thế hệ mới, 7 năm cập nhật phần mềm và thiết kế ceramic cao cấp.',
    '{"screen": "6.3 inch Super Actua LTPO OLED", "chip": "Google Tensor G4", "ram": "16GB", "storage": "256GB", "camera": "50MP + 48MP + 48MP", "battery": "4700 mAh", "os": "Android 15", "weight": "199g"}',
    999.00, NULL, 'assets/images/google-pixel-9-pro.png', 30, 4.6, 987, 0);

-- ── LAPTOP (category_id = 2) ──
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `specs`, `price`, `sale_price`, `image`, `stock`, `rating`, `review_count`, `is_featured`) VALUES
(2, 'MacBook Pro 16" M4 Pro 512GB', 'macbook-pro-16-m4-pro-512gb',
    'Hiệu năng đỉnh cao cho dân chuyên nghiệp với chip M4 Pro 14-core, màn hình Liquid Retina XDR, pin 22 giờ và hệ thống loa 6 loa Spatial Audio.',
    '{"screen": "16.2 inch Liquid Retina XDR", "chip": "Apple M4 Pro 14-core", "ram": "24GB Unified", "storage": "512GB SSD", "gpu": "M4 Pro 20-core GPU", "battery": "22 giờ", "os": "macOS Sequoia", "weight": "2.14kg"}',
    2499.00, 2299.00, 'assets/images/macbook-pro-16-m4.png', 20, 4.9, 1543, 1),

(2, 'Dell XPS 15 9540', 'dell-xps-15-9540',
    'Laptop Windows mỏng nhẹ cao cấp với Intel Core Ultra 9, màn hình OLED 3.5K sắc nét, thiết kế InfinityEdge không viền và vỏ nhôm CNC.',
    '{"screen": "15.6 inch 3.5K OLED, 400 nits", "chip": "Intel Core Ultra 9 185H", "ram": "32GB DDR5", "storage": "1TB SSD NVMe", "gpu": "NVIDIA RTX 4070 6GB", "battery": "86 Whr", "os": "Windows 11 Pro", "weight": "1.86kg"}',
    2199.00, 1899.00, 'assets/images/dell-xps-15.png', 15, 4.5, 876, 1),

(2, 'ASUS ROG Zephyrus G16 (2025)', 'asus-rog-zephyrus-g16-2025',
    'Laptop gaming siêu mỏng với RTX 5080, màn hình OLED 240Hz, bàn phím cơ tích hợp và hệ thống tản nhiệt AAS Ultra.',
    '{"screen": "16 inch 2.5K OLED, 240Hz", "chip": "Intel Core Ultra 9 275HX", "ram": "32GB DDR5", "storage": "2TB SSD NVMe", "gpu": "NVIDIA RTX 5080 12GB", "battery": "90 Whr", "os": "Windows 11 Home", "weight": "1.85kg"}',
    2799.00, 2599.00, 'assets/images/asus-rog-zephyrus-g16.png', 12, 4.7, 654, 0);

-- ── TIVI (category_id = 3) ──
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `specs`, `price`, `sale_price`, `image`, `stock`, `rating`, `review_count`, `is_featured`) VALUES
(3, 'Sony Bravia XR A95L 65" QD-OLED', 'sony-bravia-xr-a95l-65-qd-oled',
    'TV OLED đỉnh cao từ Sony với công nghệ QD-OLED, bộ xử lý Cognitive Processor XR, Acoustic Surface Audio+ và Google TV tích hợp.',
    '{"screen": "65 inch QD-OLED 4K (3840x2160)", "processor": "Cognitive Processor XR", "hdr": "Dolby Vision, HDR10, HLG", "audio": "Acoustic Surface Audio+ 60W", "smart_tv": "Google TV", "hdmi": "4x HDMI 2.1", "refresh_rate": "120Hz VRR"}',
    2799.99, 2499.99, 'assets/images/sony-bravia-xr-65.png', 10, 4.8, 432, 1),

(3, 'Samsung Neo QLED QN90D 55"', 'samsung-neo-qled-qn90d-55',
    'Trải nghiệm hình ảnh tuyệt đỉnh với công nghệ Neo Quantum HDR+, Neural Quantum Processor 4K, chống chói mạnh mẽ và Tizen OS mới nhất.',
    '{"screen": "55 inch Neo QLED 4K (3840x2160)", "processor": "Neural Quantum Processor 4K", "hdr": "Neo Quantum HDR+, HDR10+, HLG", "audio": "Dolby Atmos 60W 4.2.2ch", "smart_tv": "Tizen OS", "hdmi": "4x HDMI 2.1", "refresh_rate": "144Hz VRR"}',
    1599.99, 1299.99, 'assets/images/samsung-neo-qled-55.png', 18, 4.6, 567, 0);

-- ── TAI NGHE (category_id = 4) ──
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `specs`, `price`, `sale_price`, `image`, `stock`, `rating`, `review_count`, `is_featured`) VALUES
(4, 'Sony WH-1000XM5', 'sony-wh-1000xm5',
    'Tai nghe chống ồn số 1 thế giới với 8 microphone, chip V1 xử lý tiếng ồn, âm thanh Hi-Res LDAC, pin 30 giờ và thiết kế siêu nhẹ chỉ 250g.',
    '{"type": "Over-ear Bluetooth", "driver": "30mm Carbon Fiber", "anc": "8 microphones, Auto NC Optimizer", "codec": "LDAC, AAC, SBC", "battery": "30 giờ (ANC on)", "weight": "250g", "connectivity": "Bluetooth 5.3, 3.5mm jack", "features": "Multipoint, Speak-to-Chat, DSEE Extreme"}',
    349.99, 279.99, 'assets/images/sony-wh-1000xm5.png', 60, 4.7, 3210, 1),

(4, 'Apple AirPods Pro 3', 'apple-airpods-pro-3',
    'Tai nghe true wireless cao cấp nhất từ Apple với chip H3, chống ồn thích ứng 2x, Spatial Audio cá nhân hóa, chống nước IP54 và sạc USB-C.',
    '{"type": "True Wireless In-ear", "driver": "Custom Apple Driver", "anc": "Active Noise Cancellation 2x, Adaptive Transparency", "codec": "AAC, LC3", "battery": "6 giờ (ANC) / 30 giờ (với case)", "weight": "5.3g mỗi bên", "connectivity": "Bluetooth 5.4, Apple U2 chip", "features": "Spatial Audio, Conversation Awareness, IP54"}',
    249.00, 229.00, 'assets/images/apple-airpods-pro-3.png', 80, 4.8, 4567, 1);

-- ============================================
-- Xác nhận seed data
-- ============================================
SELECT '✅ Database bestbuy_store đã được tạo thành công!' AS status;
SELECT CONCAT('📦 Tổng sản phẩm: ', COUNT(*)) AS info FROM products;
SELECT CONCAT('📂 Tổng danh mục: ', COUNT(*)) AS info FROM categories;
