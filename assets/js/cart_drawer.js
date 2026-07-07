/**
 * Cart Drawer Logic & API Interaction
 */

// Format tiền tệ
function formatPrice(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' VNĐ';
}

// ── UI Toggles ──
function openCartDrawer() {
    const overlay = document.getElementById('cart-drawer-overlay');
    const drawer = document.getElementById('cart-drawer');
    
    if (overlay && drawer) {
        overlay.classList.remove('hidden');
        // Force reflow
        void overlay.offsetWidth;
        overlay.classList.remove('opacity-0');
        drawer.classList.remove('translate-x-full');
        document.body.style.overflow = 'hidden'; // prevent scrolling
        
        // Fetch dữ liệu mới nhất
        fetchCartDrawer();
    }
}

function closeCartDrawer() {
    const overlay = document.getElementById('cart-drawer-overlay');
    const drawer = document.getElementById('cart-drawer');
    
    if (overlay && drawer) {
        overlay.classList.add('opacity-0');
        drawer.classList.add('translate-x-full');
        document.body.style.overflow = '';
        
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 300);
    }
}

// ── API Interactions ──
async function fetchCartDrawer() {
    renderLoading(true);
    try {
        const res = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get' })
        });
        const data = await res.json();
        if (data.success) {
            renderCartDrawer(data);
        }
    } catch (e) {
        console.error(e);
        showToast('Lỗi khi tải giỏ hàng', 'error');
    }
}

async function updateCartQty(variantId, delta) {
    renderLoading(true);
    // Find current qty in DOM
    const itemEl = document.querySelector(`.drawer-item[data-id="${variantId}"]`);
    if (!itemEl) return;
    
    let currentQty = parseInt(itemEl.dataset.qty) || 1;
    let newQty = currentQty + delta;
    
    if (newQty <= 0) {
        removeCartItem(variantId);
        return;
    }
    
    try {
        const res = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update', variant_id: variantId, quantity: newQty })
        });
        const data = await res.json();
        if (data.success) {
            renderCartDrawer(data);
        } else {
            showToast(data.message, 'error');
            renderLoading(false);
        }
    } catch (e) {
        console.error(e);
        showToast('Lỗi cập nhật số lượng', 'error');
        renderLoading(false);
    }
}

async function removeCartItem(variantId) {
    renderLoading(true);
    try {
        const res = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove', variant_id: variantId })
        });
        const data = await res.json();
        if (data.success) {
            renderCartDrawer(data);
        } else {
            showToast(data.message, 'error');
            renderLoading(false);
        }
    } catch (e) {
        console.error(e);
        showToast('Lỗi xóa sản phẩm', 'error');
        renderLoading(false);
    }
}

// ── Coupons ──
async function applyCoupon() {
    const input = document.getElementById('coupon-input');
    const code = input.value.trim();
    if (!code) {
        showToast('Vui lòng nhập mã', 'error');
        return;
    }
    
    renderLoading(true);
    try {
        const res = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'apply_coupon', code: code })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            input.value = '';
            renderCartDrawer(data);
        } else {
            showToast(data.message, 'error');
            renderLoading(false);
        }
    } catch (e) {
        console.error(e);
        showToast('Lỗi áp dụng mã', 'error');
        renderLoading(false);
    }
}

async function removeCoupon() {
    renderLoading(true);
    try {
        const res = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_coupon' })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            renderCartDrawer(data);
        }
    } catch (e) {
        console.error(e);
        renderLoading(false);
    }
}

// ── Render UI ──
function renderLoading(isLoading) {
    const container = document.getElementById('drawer-cart-items');
    if (isLoading && container) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
    } else if (container) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}

function renderCartDrawer(data) {
    const itemsContainer = document.getElementById('drawer-cart-items');
    
    // Update header counts
    document.getElementById('drawer-cart-count').textContent = `(${data.cartCount})`;
    const headerBadge = document.getElementById('cart-badge');
    const mobileBadge = document.getElementById('mobile-cart-badge');
    
    if (headerBadge) {
        headerBadge.textContent = data.cartCount;
        headerBadge.classList.toggle('hidden', data.cartCount === 0);
    }
    if (mobileBadge) {
        mobileBadge.textContent = data.cartCount;
        mobileBadge.classList.toggle('hidden', data.cartCount === 0);
    }

    renderLoading(false);

    // Empty state
    if (data.cart.length === 0) {
        itemsContainer.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-center text-gray-500 space-y-4">
                <span class="text-6xl">🛒</span>
                <p>Giỏ hàng của bạn đang trống</p>
                <button onclick="closeCartDrawer()" class="px-6 py-2 bg-bb-blue text-white rounded-full font-semibold hover:bg-blue-600 transition-colors">
                    Tiếp tục mua sắm
                </button>
            </div>
        `;
        document.getElementById('drawer-checkout-btn').style.display = 'none';
    } else {
        document.getElementById('drawer-checkout-btn').style.display = 'block';
        itemsContainer.innerHTML = data.cart.map(item => `
            <div class="drawer-item flex gap-4 bg-white p-3 rounded-xl border border-gray-100 shadow-sm relative" data-id="${item.variant_id}" data-qty="${item.quantity}">
                <button onclick="removeCartItem(${item.variant_id})" class="absolute -top-2 -right-2 bg-white rounded-full p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 shadow-sm transition-colors border border-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
                <div class="w-20 h-20 bg-gray-50 rounded-lg flex items-center justify-center shrink-0 border border-gray-100">
                    <img src="${item.image ? item.image : ''}" alt="" class="w-full h-full object-contain p-2" onerror="this.outerHTML='<span class=\\'text-2xl opacity-50\\'>📦</span>'">
                </div>
                <div class="flex-1 flex flex-col min-w-0">
                    <h4 class="text-sm font-semibold text-gray-800 line-clamp-2 leading-tight">${item.name}</h4>
                    <div class="mt-auto flex items-end justify-between">
                        <span class="font-bold text-bb-blue">${formatPrice(item.price)}</span>
                        <div class="flex items-center border border-gray-200 rounded-lg bg-gray-50 overflow-hidden h-7">
                            <button onclick="updateCartQty(${item.variant_id}, -1)" class="px-2 text-gray-500 hover:bg-gray-200 hover:text-gray-800 font-bold transition-colors">−</button>
                            <span class="w-8 text-center text-xs font-semibold bg-white border-x border-gray-200 leading-7">${item.quantity}</span>
                            <button onclick="updateCartQty(${item.variant_id}, 1)" class="px-2 text-gray-500 hover:bg-gray-200 hover:text-gray-800 font-bold transition-colors">+</button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Render Summary
    if (data.summary) {
        document.getElementById('drawer-subtotal').textContent = formatPrice(data.summary.subtotal);
        document.getElementById('drawer-shipping').textContent = data.summary.shipping === 0 ? 'Miễn phí' : formatPrice(data.summary.shipping);
        document.getElementById('drawer-vat').textContent = formatPrice(data.summary.vat);
        document.getElementById('drawer-total').textContent = formatPrice(data.summary.total);

        // Discount
        const discountRow = document.getElementById('drawer-discount-row');
        if (data.coupon) {
            discountRow.classList.remove('hidden');
            document.getElementById('drawer-discount').textContent = `-${formatPrice(data.coupon.discount_amount)}`;
            
            // Coupon UI
            document.getElementById('coupon-status').classList.remove('hidden');
            document.getElementById('coupon-status').classList.add('flex');
            document.getElementById('coupon-message').textContent = `Đã áp dụng: ${data.coupon.code}`;
            document.getElementById('coupon-input').parentElement.classList.add('hidden');
        } else {
            discountRow.classList.add('hidden');
            document.getElementById('coupon-status').classList.add('hidden');
            document.getElementById('coupon-status').classList.remove('flex');
            document.getElementById('coupon-input').parentElement.classList.remove('hidden');
        }
    }
}
