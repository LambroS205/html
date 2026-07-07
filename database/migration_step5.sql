-- ════════════════════════════════════════
-- MIGRATION BƯỚC 5: DATABASE INDEXING
-- Tối ưu hóa hiệu năng truy vấn cho bảng lớn
-- ════════════════════════════════════════

-- 1. Bảng products
-- Đánh index cho category_id để tăng tốc độ truy vấn danh mục
ALTER TABLE `products` ADD INDEX IF NOT EXISTS `idx_products_category_id` (`category_id`);

-- 2. Bảng orders
-- Đã có idx_orders_user_id từ trước.
-- Đánh index cho status để tăng tốc độ đếm đơn hàng trên Dashboard
ALTER TABLE `orders` ADD INDEX IF NOT EXISTS `idx_orders_status` (`status`);
-- Đánh index cho created_at để truy vấn doanh thu theo ngày nhanh hơn
ALTER TABLE `orders` ADD INDEX IF NOT EXISTS `idx_orders_created_at` (`created_at`);

-- 3. Bảng order_items
-- Đánh index cho product_id để thống kê sản phẩm bán chạy nhanh hơn
ALTER TABLE `order_items` ADD INDEX IF NOT EXISTS `idx_order_items_product_id` (`product_id`);
