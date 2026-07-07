<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = Database::getConnection();
    
    // Tạm thời tắt khóa ngoại để dễ insert/update
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    echo "Bắt đầu thêm dữ liệu mẫu...\n";

    // 1. Thêm Attributes (nếu chưa có)
    $attributes = ['Màu sắc', 'Dung lượng', 'RAM'];
    $attrIds = [];
    foreach ($attributes as $attr) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO attributes (name) VALUES (:name)");
        $stmt->execute([':name' => $attr]);
        
        $stmt = $pdo->prepare("SELECT id FROM attributes WHERE name = :name");
        $stmt->execute([':name' => $attr]);
        $attrIds[$attr] = $stmt->fetchColumn();
    }

    // Hàm helper thêm Attribute Value
    $getAttrValueId = function($attrId, $value) use ($pdo) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO attribute_values (attribute_id, value) VALUES (:attr_id, :value)");
        $stmt->execute([':attr_id' => $attrId, ':value' => $value]);
        
        $stmt = $pdo->prepare("SELECT id FROM attribute_values WHERE attribute_id = :attr_id AND value = :value");
        $stmt->execute([':attr_id' => $attrId, ':value' => $value]);
        return $stmt->fetchColumn();
    };

    // 2. Thêm Sản phẩm 1: MacBook Air M3
    $macbookSlug = 'macbook-air-m3-13-' . uniqid();
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, rating, review_count, is_featured) VALUES (2, 'MacBook Air M3 13-inch', :slug, 'MacBook Air M3 mỏng nhẹ, chip M3 mạnh mẽ.', 4.9, 120, 1)");
    $stmt->execute([':slug' => $macbookSlug]);
    $macbookId = $pdo->lastInsertId();

    // Các tùy chọn của MacBook
    $colorsMac = ['Midnight', 'Starlight'];
    $ramsMac = ['8GB', '16GB'];
    
    $skuCounter = 1;
    foreach ($colorsMac as $color) {
        foreach ($ramsMac as $ram) {
            $price = 25000000 + ($ram == '16GB' ? 5000000 : 0);
            $sku = "MBA-M3-13-" . strtoupper(substr($color, 0, 3)) . "-" . $ram . "-" . uniqid();
            
            $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, sku, price, sale_price, stock, image_url) VALUES (:pid, :sku, :price, :sale_price, 20, 'assets/images/macbook-air-m3.png')");
            $stmt->execute([':pid' => $macbookId, ':sku' => $sku, ':price' => $price, ':sale_price' => $price]);
            $variantId = $pdo->lastInsertId();
            
            $colorValId = $getAttrValueId($attrIds['Màu sắc'], $color);
            $ramValId = $getAttrValueId($attrIds['RAM'], $ram);
            
            $pdo->exec("INSERT INTO variant_attribute_values (variant_id, attribute_value_id) VALUES ($variantId, $colorValId), ($variantId, $ramValId)");
        }
    }

    // 3. Thêm Sản phẩm 2: Samsung Galaxy Z Fold 5
    $foldSlug = 'samsung-galaxy-z-fold-5-' . uniqid();
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, rating, review_count, is_featured) VALUES (1, 'Samsung Galaxy Z Fold 5', :slug, 'Điện thoại gập cao cấp nhất của Samsung.', 4.8, 85, 1)");
    $stmt->execute([':slug' => $foldSlug]);
    $foldId = $pdo->lastInsertId();

    $colorsFold = ['Phantom Black', 'Icy Blue'];
    $storagesFold = ['512GB', '1TB'];

    foreach ($colorsFold as $color) {
        foreach ($storagesFold as $storage) {
            $price = 40000000 + ($storage == '1TB' ? 4000000 : 0);
            $sale_price = $price - 2000000;
            $sku = "ZFOLD5-" . strtoupper(substr($color, 0, 3)) . "-" . $storage . "-" . uniqid();
            
            $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, sku, price, sale_price, stock, image_url) VALUES (:pid, :sku, :price, :sale_price, 15, 'assets/images/samsung-z-fold-5.png')");
            $stmt->execute([':pid' => $foldId, ':sku' => $sku, ':price' => $price, ':sale_price' => $sale_price]);
            $variantId = $pdo->lastInsertId();
            
            $colorValId = $getAttrValueId($attrIds['Màu sắc'], $color);
            $storageValId = $getAttrValueId($attrIds['Dung lượng'], $storage);
            
            $pdo->exec("INSERT INTO variant_attribute_values (variant_id, attribute_value_id) VALUES ($variantId, $colorValId), ($variantId, $storageValId)");
        }
    }

    // 4. Thêm Sản phẩm 3: Tai nghe Sony WH-1000XM5 (chỉ có màu)
    $sonySlug = 'sony-wh-1000xm5-variants-' . uniqid();
    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, rating, review_count, is_featured) VALUES (4, 'Tai nghe Sony WH-1000XM5', :slug, 'Tai nghe chống ồn đỉnh cao.', 4.7, 300, 0)");
    $stmt->execute([':slug' => $sonySlug]);
    $sonyId = $pdo->lastInsertId();

    $colorsSony = ['Black', 'Silver'];

    foreach ($colorsSony as $color) {
        $sku = "SONY-XM5-" . strtoupper($color) . "-" . uniqid();
        $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, sku, price, sale_price, stock, image_url) VALUES (:pid, :sku, 8000000, 7500000, 50, 'assets/images/sony-wh-1000xm5.png')");
        $stmt->execute([':pid' => $sonyId, ':sku' => $sku]);
        $variantId = $pdo->lastInsertId();
        
        $colorValId = $getAttrValueId($attrIds['Màu sắc'], $color);
        $pdo->exec("INSERT INTO variant_attribute_values (variant_id, attribute_value_id) VALUES ($variantId, $colorValId)");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "Thêm dữ liệu thành công! Đã thêm MacBook Air M3, Samsung Z Fold 5, Tai nghe Sony WH-1000XM5 với nhiều biến thể.\n";

} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}
