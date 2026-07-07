-- ============================================
-- BestBuy Store - Step 12 Migration
-- Feature: VNPay / Payment Gateways
-- ============================================

USE `bestbuy_store_v2`; 

-- 1. Modify payment_method ENUM to allow 'vnpay' (and potentially 'momo' later)
ALTER TABLE `orders`
MODIFY COLUMN `payment_method` ENUM('cod', 'card', 'vnpay', 'momo') NOT NULL DEFAULT 'cod';

-- 2. Add payment_status column
ALTER TABLE `orders`
ADD COLUMN `payment_status` ENUM('unpaid', 'paid', 'refunded', 'failed') NOT NULL DEFAULT 'unpaid' AFTER `status`;

-- 3. Add transaction_id column to store Gateway transaction refs
ALTER TABLE `orders`
ADD COLUMN `transaction_id` VARCHAR(100) NULL AFTER `payment_status`;

SELECT '✅ Database Migration (Step 12 - VNPay) hoàn thành!' AS status;
