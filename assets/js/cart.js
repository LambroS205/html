/**
 * BestBuy Store — Cart Page JavaScript
 * AJAX cập nhật số lượng, xóa item, tính tổng real-time
 * Không reload trang → UX mượt mà
 */

const FREE_SHIPPING_THRESHOLD = 35.00;
const VAT_RATE = 0.10;
const SHIPPING_FEE = 5.00;

/* ═══════════════════════════════════════
   UPDATE QUANTITY — Tăng/giảm số lượng
   ═══════════════════════════════════════ */
async function updateCartQty(productId, delta, btnElement) {
    const row = btnElement.closest('.cart-item');
    if (!row) return;

    const qtyEl = row.querySelector('.cart-item-qty');
    let currentQty = parseInt(qtyEl.textContent) || 1;
    let newQty = currentQty + delta;

    // Nếu giảm về 0 → xóa luôn
    if (newQty <= 0) {
        removeCartItem(productId, btnElement);
        return;
    }

    // Disable buttons tạm thời
    const buttons = row.querySelectorAll('.qty-btn');
    buttons.forEach(b => b.disabled = true);

    try {
        const resp = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', product_id: productId, quantity: newQty })
        });
        const data = await resp.json();

        if (data.success) {
            // Cập nhật UI
            qtyEl.textContent = newQty;

            // Cập nhật item total
            const price = parseFloat(row.dataset.price);
            const itemTotalEl = row.querySelector('.cart-item-total');
            itemTotalEl.textContent = formatMoney(price * newQty);

            // Cập nhật cart badge & summary
            updateCartBadge(data.cartCount);
            recalcSummary();
        } else {
            showToast('✕ ' + (data.message || 'Lỗi cập nhật'), 'error');
        }
    } catch (err) {
        showToast('✕ Lỗi kết nối', 'error');
    } finally {
        buttons.forEach(b => b.disabled = false);
    }
}

/* ═══════════════════════════════════════
   REMOVE ITEM — Xóa sản phẩm khỏi giỏ
   ═══════════════════════════════════════ */
async function removeCartItem(productId, btnElement) {
    const row = btnElement.closest('.cart-item');
    if (!row) return;

    // Fade-out animation
    row.style.transition = 'all 0.3s ease';
    row.style.opacity = '0.5';
    row.style.pointerEvents = 'none';

    try {
        const resp = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove', product_id: productId })
        });
        const data = await resp.json();

        if (data.success) {
            // Animate removal
            row.style.maxHeight = row.offsetHeight + 'px';
            row.style.overflow = 'hidden';
            
            requestAnimationFrame(() => {
                row.style.maxHeight = '0';
                row.style.padding = '0 24px';
                row.style.opacity = '0';
                row.style.transform = 'translateX(50px)';
            });

            setTimeout(() => {
                row.remove();
                updateCartBadge(data.cartCount);
                recalcSummary();

                // Kiểm tra giỏ trống
                const remaining = document.querySelectorAll('.cart-item');
                if (remaining.length === 0) {
                    showEmptyCart();
                }
            }, 300);

            showToast('✓ Đã xóa khỏi giỏ hàng', 'success');
        } else {
            row.style.opacity = '1';
            row.style.pointerEvents = '';
            showToast('✕ ' + (data.message || 'Lỗi xóa'), 'error');
        }
    } catch (err) {
        row.style.opacity = '1';
        row.style.pointerEvents = '';
        showToast('✕ Lỗi kết nối', 'error');
    }
}

/* ═══════════════════════════════════════
   RECALC SUMMARY — Tính lại tổng tiền
   Chạy client-side → cập nhật tức thì
   ═══════════════════════════════════════ */
function recalcSummary() {
    const items = document.querySelectorAll('.cart-item');
    let subtotal = 0;
    let totalItems = 0;

    items.forEach(item => {
        const price = parseFloat(item.dataset.price) || 0;
        const qty = parseInt(item.querySelector('.cart-item-qty')?.textContent) || 0;
        subtotal += price * qty;
        totalItems += qty;
    });

    const shipping = (subtotal >= FREE_SHIPPING_THRESHOLD || subtotal === 0) ? 0 : SHIPPING_FEE;
    const vat = subtotal * VAT_RATE;
    const total = subtotal + shipping + vat;

    // Cập nhật DOM
    const subtotalEl = document.getElementById('summary-subtotal');
    const shippingEl = document.getElementById('summary-shipping');
    const vatEl = document.getElementById('summary-vat');
    const totalEl = document.getElementById('summary-total');
    const itemCountEl = document.getElementById('summary-item-count');
    const headerCountEl = document.getElementById('cart-total-items-header');

    if (subtotalEl) subtotalEl.textContent = formatMoney(subtotal);
    if (vatEl) vatEl.textContent = formatMoney(vat);
    if (totalEl) totalEl.textContent = formatMoney(total);
    if (itemCountEl) itemCountEl.textContent = totalItems;
    if (headerCountEl) headerCountEl.textContent = `(${totalItems} sản phẩm)`;

    if (shippingEl) {
        if (shipping === 0) {
            shippingEl.textContent = 'Miễn phí';
            shippingEl.className = 'font-medium text-green-600';
        } else {
            shippingEl.textContent = formatMoney(shipping);
            shippingEl.className = 'font-medium';
        }
    }

    // Cập nhật shipping progress bar
    const progressEl = document.getElementById('shipping-progress');
    if (progressEl) {
        if (subtotal >= FREE_SHIPPING_THRESHOLD) {
            progressEl.style.display = 'none';
        } else {
            progressEl.style.display = 'block';
            const remaining = FREE_SHIPPING_THRESHOLD - subtotal;
            const remainEl = document.getElementById('shipping-remaining');
            if (remainEl) remainEl.textContent = formatMoney(remaining);
            const bar = progressEl.querySelector('.bg-bb-blue');
            if (bar) bar.style.width = Math.min(100, (subtotal / FREE_SHIPPING_THRESHOLD) * 100) + '%';
        }
    }
}

/* ═══════════════════════════════════════
   SHOW EMPTY CART — Hiện trạng thái trống
   ═══════════════════════════════════════ */
function showEmptyCart() {
    const container = document.getElementById('cart-container');
    if (!container) return;

    container.innerHTML = `
        <div class="w-full bg-white rounded-2xl p-16 text-center shadow-sm border border-gray-100">
            <span class="text-7xl mb-4 block">🛒</span>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Giỏ hàng trống</h2>
            <p class="text-gray-400 mb-6 max-w-md mx-auto">Bạn chưa thêm sản phẩm nào vào giỏ hàng. Hãy khám phá các sản phẩm tuyệt vời của chúng tôi!</p>
            <a href="/" class="inline-flex items-center gap-2 bg-bb-yellow text-bb-dark font-bold px-8 py-3 rounded-full hover:bg-yellow-300 transition-all transform hover:scale-105">
                ← Tiếp tục mua sắm
            </a>
        </div>`;
}

/* ═══════════════════════════════════════
   FORMAT MONEY — Utility
   ═══════════════════════════════════════ */
function formatMoney(amount) {
    return '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
