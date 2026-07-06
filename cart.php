<?php
/**
 * Trang Giỏ hàng - BestBuy Store
 * Xử lý: Hiển thị items, cập nhật số lượng (AJAX), xóa items, tính tổng tiền
 * 
 * Logic tính tiền:
 * - Subtotal: Tổng giá * số lượng
 * - Shipping: $5.00 (miễn phí nếu subtotal >= $35)
 * - VAT: 10% trên subtotal
 * - Total: subtotal + shipping + VAT
 */

$pageTitle = 'Giỏ hàng — BestBuy Store';
$pageDescription = 'Xem và quản lý giỏ hàng của bạn tại BestBuy Store.';

require_once __DIR__ . '/includes/header.php';

// ── Tính toán giỏ hàng ──
$cartItems = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += (float) $item['price'] * (int) $item['quantity'];
}

$freeShippingThreshold = 35.00;
$shippingFee = ($subtotal >= $freeShippingThreshold || $subtotal == 0) ? 0 : 5.00;
$vatRate = 0.10; // 10%
$vat = $subtotal * $vatRate;
$total = $subtotal + $shippingFee + $vat;
$totalItems = 0;
foreach ($cartItems as $item) {
    $totalItems += (int) $item['quantity'];
}
?>

    <div class="max-w-7xl mx-auto px-4 py-6">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
            <a href="/" class="hover:text-bb-blue transition-colors">Trang chủ</a>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-600 font-medium">Giỏ hàng</span>
        </nav>

        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 flex items-center gap-3">
            <svg class="w-8 h-8 text-bb-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
            Giỏ hàng
            <span id="cart-total-items-header" class="text-base font-normal text-gray-400">(<?= $totalItems ?> sản phẩm)</span>
        </h1>

        <?php if (empty($cartItems)): ?>
        <!-- ═══ EMPTY CART ═══ -->
        <div id="cart-empty" class="bg-white rounded-2xl p-16 text-center shadow-sm border border-gray-100">
            <span class="text-7xl mb-4 block">🛒</span>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Giỏ hàng trống</h2>
            <p class="text-gray-400 mb-6 max-w-md mx-auto">Bạn chưa thêm sản phẩm nào vào giỏ hàng. Hãy khám phá các sản phẩm tuyệt vời của chúng tôi!</p>
            <a href="/" class="inline-flex items-center gap-2 bg-bb-yellow text-bb-dark font-bold px-8 py-3 rounded-full hover:bg-yellow-300 transition-all transform hover:scale-105">
                ← Tiếp tục mua sắm
            </a>
        </div>
        <?php else: ?>
        <!-- ═══ CART WITH ITEMS ═══ -->
        <div class="flex flex-col lg:flex-row gap-8" id="cart-container">

            <!-- ── Cart Items List ── -->
            <div class="flex-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="cart-items-wrapper">
                    <!-- Header -->
                    <div class="hidden md:grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        <div class="col-span-6">Sản phẩm</div>
                        <div class="col-span-2 text-center">Đơn giá</div>
                        <div class="col-span-2 text-center">Số lượng</div>
                        <div class="col-span-2 text-right">Thành tiền</div>
                    </div>

                    <!-- Items -->
                    <div id="cart-items-list">
                        <?php foreach ($cartItems as $key => $item):
                            $itemPrice = (float) $item['price'];
                            $itemQty   = (int) $item['quantity'];
                            $itemTotal = $itemPrice * $itemQty;
                            $itemImage = getProductImage($item['image'] ?? '');
                        ?>
                        <div class="cart-item grid grid-cols-1 md:grid-cols-12 gap-4 items-center px-6 py-5 border-b border-gray-50 hover:bg-gray-50/50 transition-colors" 
                             data-product-id="<?= (int) $item['product_id'] ?>"
                             data-price="<?= $itemPrice ?>">
                            
                            <!-- Product Info (col-span-6) -->
                            <div class="md:col-span-6 flex items-center gap-4">
                                <div class="w-20 h-20 bg-gray-50 rounded-xl flex items-center justify-center shrink-0 overflow-hidden border border-gray-100">
                                    <?php if ($itemImage): ?>
                                        <img src="<?= htmlspecialchars($itemImage) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-contain p-2" loading="lazy">
                                    <?php else: ?>
                                        <span class="text-3xl opacity-50">📦</span>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-800 text-sm line-clamp-2"><?= htmlspecialchars($item['name']) ?></h3>
                                    <button onclick="removeCartItem(<?= (int) $item['product_id'] ?>, this)" 
                                            class="text-xs text-red-400 hover:text-red-600 mt-1.5 flex items-center gap-1 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Xóa
                                    </button>
                                </div>
                            </div>

                            <!-- Unit Price (col-span-2) -->
                            <div class="md:col-span-2 text-center">
                                <span class="md:hidden text-xs text-gray-400">Đơn giá: </span>
                                <span class="font-semibold text-gray-700 text-sm"><?= formatPrice($itemPrice) ?></span>
                            </div>

                            <!-- Quantity Controls (col-span-2) -->
                            <div class="md:col-span-2 flex justify-center">
                                <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-white">
                                    <button onclick="updateCartQty(<?= (int) $item['product_id'] ?>, -1, this)" 
                                            class="qty-btn px-3 py-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition-colors text-sm font-bold">−</button>
                                    <span class="cart-item-qty w-10 text-center text-sm font-semibold border-x border-gray-200 py-1.5"><?= $itemQty ?></span>
                                    <button onclick="updateCartQty(<?= (int) $item['product_id'] ?>, 1, this)" 
                                            class="qty-btn px-3 py-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition-colors text-sm font-bold">+</button>
                                </div>
                            </div>

                            <!-- Item Total (col-span-2) -->
                            <div class="md:col-span-2 text-right">
                                <span class="md:hidden text-xs text-gray-400">Thành tiền: </span>
                                <span class="cart-item-total font-bold text-bb-blue"><?= formatPrice($itemTotal) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Continue Shopping -->
                <a href="/" class="inline-flex items-center gap-2 mt-4 text-sm text-bb-blue hover:text-bb-dark font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path></svg>
                    Tiếp tục mua sắm
                </a>
            </div>

            <!-- ── Cart Summary Sidebar ── -->
            <div class="w-full lg:w-80 shrink-0">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24" id="cart-summary">
                    <h3 class="font-bold text-gray-900 text-lg mb-5">Tóm tắt đơn hàng</h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Tạm tính (<span id="summary-item-count"><?= $totalItems ?></span> sản phẩm)</span>
                            <span id="summary-subtotal" class="font-medium"><?= formatPrice($subtotal) ?></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Phí vận chuyển</span>
                            <span id="summary-shipping" class="font-medium <?= $shippingFee == 0 ? 'text-green-600' : '' ?>">
                                <?= $shippingFee == 0 ? 'Miễn phí' : formatPrice($shippingFee) ?>
                            </span>
                        </div>
                        <?php if ($shippingFee > 0): ?>
                        <div id="shipping-progress" class="bg-blue-50 rounded-lg p-2.5 text-xs text-bb-blue">
                            <div class="flex justify-between mb-1">
                                <span>Mua thêm <strong id="shipping-remaining"><?= formatPrice($freeShippingThreshold - $subtotal) ?></strong> để được miễn phí ship</span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-1.5">
                                <div class="bg-bb-blue h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, ($subtotal / $freeShippingThreshold) * 100) ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-gray-600">
                            <span>VAT (10%)</span>
                            <span id="summary-vat" class="font-medium"><?= formatPrice($vat) ?></span>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 mt-4 pt-4">
                        <div class="flex justify-between items-baseline">
                            <span class="text-base font-bold text-gray-900">Tổng cộng</span>
                            <span id="summary-total" class="text-2xl font-black text-bb-blue"><?= formatPrice($total) ?></span>
                        </div>
                    </div>

                    <a href="/checkout.php" id="checkout-btn" class="mt-5 block w-full text-center bg-bb-yellow text-bb-dark font-bold py-3.5 rounded-xl hover:bg-yellow-300 active:scale-[0.98] transition-all shadow-lg shadow-yellow-500/20 text-base">
                        Tiến hành thanh toán →
                    </a>

                    <!-- Trust -->
                    <div class="mt-4 flex items-center justify-center gap-2 text-xs text-gray-400">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                        Thanh toán bảo mật & an toàn
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Cart Page JavaScript -->
    <script src="/assets/js/cart.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
