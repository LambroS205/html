<?php
/**
 * Test checkout flow — CLI simulation
 * Kiểm tra DB Transaction hoạt động đúng
 */

require_once __DIR__ . '/../config/db.php';

$pdo = Database::getConnection();

// Simulate a cart
$_SESSION['cart'] = [
    '1' => ['product_id' => 1, 'name' => 'iPhone 16 Pro Max 256GB', 'price' => 1099.00, 'quantity' => 1, 'image' => ''],
    '9' => ['product_id' => 9, 'name' => 'Sony WH-1000XM5', 'price' => 279.99, 'quantity' => 2, 'image' => ''],
];

$cartItems = $_SESSION['cart'];
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
}

$shippingFee = ($subtotal >= 35) ? 0 : 5.00;
$vat = round($subtotal * 0.10, 2);
$total = $subtotal + $shippingFee + $vat;
$orderCode = 'BB-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

echo "=== TEST CHECKOUT TRANSACTION ===\n";
echo "Cart: " . count($cartItems) . " items\n";
echo "Subtotal: $" . number_format($subtotal, 2) . "\n";
echo "Shipping: $" . number_format($shippingFee, 2) . "\n";
echo "VAT (10%): $" . number_format($vat, 2) . "\n";
echo "Total: $" . number_format($total, 2) . "\n";
echo "Order Code: $orderCode\n\n";

try {
    $pdo->beginTransaction();

    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_code, customer_name, customer_email, customer_phone,
                            shipping_address, payment_method, subtotal, shipping_fee, tax, total, status)
        VALUES (:code, :name, :email, :phone, :addr, :pay, :sub, :ship, :tax, :total, 'pending')
    ");
    $stmt->execute([
        ':code' => $orderCode, ':name' => 'Test User', ':email' => 'test@bestbuy.com',
        ':phone' => '0901234567', ':addr' => '123 Test Street, Dist 1, HCMC',
        ':pay' => 'cod', ':sub' => $subtotal, ':ship' => $shippingFee,
        ':tax' => $vat, ':total' => $total,
    ]);

    $orderId = (int) $pdo->lastInsertId();
    echo "✅ Order inserted: ID=$orderId\n";

    // Insert items
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, price, quantity)
        VALUES (:oid, :pid, :pname, :price, :qty)
    ");
    foreach ($cartItems as $item) {
        $itemStmt->execute([
            ':oid' => $orderId, ':pid' => $item['product_id'],
            ':pname' => $item['name'], ':price' => $item['price'], ':qty' => $item['quantity'],
        ]);
    }
    echo "✅ " . count($cartItems) . " order items inserted\n";

    $pdo->commit();
    echo "✅ Transaction COMMITTED\n\n";

    // Verify
    $order = $pdo->query("SELECT * FROM orders WHERE id = $orderId")->fetch();
    echo "Verification:\n";
    echo "  Code: " . $order['order_code'] . "\n";
    echo "  Customer: " . $order['customer_name'] . "\n";
    echo "  Total: $" . number_format($order['total'], 2) . "\n";
    echo "  Status: " . $order['status'] . "\n";

    $items = $pdo->query("SELECT * FROM order_items WHERE order_id = $orderId")->fetchAll();
    echo "  Items: " . count($items) . "\n";
    foreach ($items as $i) {
        echo "    - " . $i['product_name'] . " x" . $i['quantity'] . " = $" . number_format($i['price'] * $i['quantity'], 2) . "\n";
    }

    echo "\n✅ ALL CHECKOUT TESTS PASSED!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
