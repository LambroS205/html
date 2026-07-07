-- ============================================
-- Migration Step 9: Suppliers and Inventory Management
-- ============================================

USE `bestbuy_store_v2`;

-- 1. Create suppliers table
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `contact_name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add supplier_id to products table
-- We check if column exists before adding it to avoid errors if script is run multiple times
SET @dbname = DATABASE();
SET @tablename = "products";
SET @columnname = "supplier_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " INT NULL AFTER category_id;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key for supplier_id in products table
SET @preparedStatementFK = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
      AND (constraint_name = 'fk_products_supplier')
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD CONSTRAINT fk_products_supplier FOREIGN KEY (", @columnname, ") REFERENCES suppliers(id) ON DELETE SET NULL ON UPDATE CASCADE;")
));
PREPARE alterIfNotExistsFK FROM @preparedStatementFK;
EXECUTE alterIfNotExistsFK;
DEALLOCATE PREPARE alterIfNotExistsFK;

-- 3. Create inventory_logs table
CREATE TABLE IF NOT EXISTS `inventory_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `supplier_id` INT DEFAULT NULL,
    `type` ENUM('in', 'out', 'adjustment') NOT NULL DEFAULT 'in',
    `quantity` INT NOT NULL,
    `note` TEXT DEFAULT NULL,
    `user_id` INT DEFAULT NULL COMMENT 'Admin who made the change',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `fk_inventory_logs_product`
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_inventory_logs_supplier`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_supplier_id` (`supplier_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some dummy data for suppliers
INSERT INTO `suppliers` (`name`, `contact_name`, `email`, `phone`, `address`)
SELECT * FROM (SELECT 'Công ty TNHH Apple Việt Nam', 'Nguyễn Văn A', 'contact@apple.vn', '0901234567', 'Quận 1, TP. HCM') AS tmp
WHERE NOT EXISTS (
    SELECT name FROM `suppliers` WHERE name = 'Công ty TNHH Apple Việt Nam'
) LIMIT 1;

INSERT INTO `suppliers` (`name`, `contact_name`, `email`, `phone`, `address`)
SELECT * FROM (SELECT 'Samsung Electronics VN', 'Trần Thị B', 'info@samsung.com', '0912345678', 'Quận 9, TP. HCM') AS tmp
WHERE NOT EXISTS (
    SELECT name FROM `suppliers` WHERE name = 'Samsung Electronics VN'
) LIMIT 1;

INSERT INTO `suppliers` (`name`, `contact_name`, `email`, `phone`, `address`)
SELECT * FROM (SELECT 'Nhà phân phối An Phát', 'Lê Văn C', 'sales@anphat.vn', '0987654321', 'Đống Đa, Hà Nội') AS tmp
WHERE NOT EXISTS (
    SELECT name FROM `suppliers` WHERE name = 'Nhà phân phối An Phát'
) LIMIT 1;
