-- ============================================
-- Migration Step 14: Bảng Settings
-- ============================================

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm dữ liệu mẫu (Seed Data)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('home_hero_badge', 'Flash Sale — Giảm đến 30%', 'Badge nhỏ ở đầu phần Hero'),
('home_hero_title', 'Công nghệ<br>\r\n<span class=\"text-bb-yellow\">đỉnh cao</span><br>\r\n<span class=\"text-blue-200\">Giá không tưởng</span>', 'Tiêu đề lớn Hero Banner (Cho phép HTML)'),
('home_hero_desc', 'Khám phá bộ sưu tập laptop, điện thoại, TV và phụ kiện chính hãng mới nhất với ưu đãi hấp dẫn chưa từng có.', 'Đoạn mô tả ngắn Hero Banner'),
('home_hero_button_text', 'Mua sắm ngay', 'Text của nút chính ở Hero Banner'),
('home_hero_button_link', '#products', 'Đường dẫn của nút chính (VD: /danh-muc/dien-thoai)'),

('home_promo_title', '🎧 Mua tai nghe — Giảm thêm <span class=\"text-bb-yellow\">20%</span>', 'Tiêu đề khối Promo giữa trang (Cho phép HTML)'),
('home_promo_desc', 'Áp dụng cho Sony WH-1000XM5 & AirPods Pro 3. Số lượng có hạn.', 'Mô tả ngắn của khối Promo'),
('home_promo_button_text', 'Xem ngay →', 'Text nút của khối Promo'),
('home_promo_button_link', '/danh-muc/tai-nghe', 'Đường dẫn nút khối Promo');
