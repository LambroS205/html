/**
 * BestBuy Store — Main JavaScript
 * Vanilla JS (ES6+) cho tương tác người dùng
 */

document.addEventListener('DOMContentLoaded', () => {
    initStickyHeader();
    initMobileMenu();
    initLiveSearch();
});

/* ═══════════════════════════════════════
   STICKY HEADER — Thêm shadow khi scroll
   ═══════════════════════════════════════ */
function initStickyHeader() {
    const header = document.getElementById('main-header');
    if (!header) return;

    let lastScroll = 0;
    window.addEventListener('scroll', () => {
        const currentScroll = window.scrollY;
        if (currentScroll > 10) {
            header.classList.add('header-scrolled');
        } else {
            header.classList.remove('header-scrolled');
        }
        lastScroll = currentScroll;
    }, { passive: true });
}

/* ═══════════════════════════════════════
   MOBILE MENU — Slide-in/out
   ═══════════════════════════════════════ */
function initMobileMenu() {
    const btn     = document.getElementById('mobile-menu-btn');
    const menu    = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-overlay');
    const close   = document.getElementById('mobile-menu-close');

    if (!btn || !menu) return;

    function openMenu() {
        menu.classList.add('open');
        overlay.classList.add('open');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        menu.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => overlay.classList.add('hidden'), 300);
    }

    btn.addEventListener('click', openMenu);
    close?.addEventListener('click', closeMenu);
    overlay?.addEventListener('click', closeMenu);
}

/* ═══════════════════════════════════════
   LIVE SEARCH — Tìm kiếm gợi ý realtime
   ═══════════════════════════════════════ */
function initLiveSearch() {
    const input    = document.getElementById('search-input');
    const dropdown = document.getElementById('search-dropdown');
    if (!input || !dropdown) return;

    let debounceTimer = null;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = input.value.trim();

        if (query.length < 2) {
            dropdown.classList.remove('active');
            return;
        }

        // Debounce 300ms để tránh spam request
        debounceTimer = setTimeout(() => {
            fetchSearchResults(query, dropdown);
        }, 300);
    });

    // Đóng dropdown khi click bên ngoài
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#search-wrapper')) {
            dropdown.classList.remove('active');
        }
    });

    // Mở lại khi focus vào input
    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2 && dropdown.innerHTML) {
            dropdown.classList.add('active');
        }
    });
}

async function fetchSearchResults(query, dropdown) {
    try {
        const resp = await fetch(`/search.php?q=${encodeURIComponent(query)}&ajax=1`);
        const data = await resp.json();

        if (data.length === 0) {
            dropdown.innerHTML = `
                <div class="p-4 text-center text-gray-400 text-sm">
                    Không tìm thấy sản phẩm nào cho "<strong>${escapeHtml(query)}</strong>"
                </div>`;
        } else {
            dropdown.innerHTML = data.slice(0, 6).map(p => `
                <a href="/product.php?slug=${p.slug}" class="search-dropdown-item">
                    <span class="text-2xl">${p.category_icon || '📦'}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">${escapeHtml(p.name)}</p>
                        <p class="text-xs text-gray-400">${escapeHtml(p.category_name)}</p>
                    </div>
                    <span class="text-sm font-bold text-bb-blue whitespace-nowrap">
                        $${parseFloat(p.sale_price || p.price).toFixed(2)}
                    </span>
                </a>
            `).join('');

            // Link "Xem tất cả"
            dropdown.innerHTML += `
                <a href="/search.php?q=${encodeURIComponent(query)}" class="block p-3 text-center text-sm font-semibold text-bb-blue hover:bg-blue-50 border-t transition-colors">
                    Xem tất cả kết quả →
                </a>`;
        }

        dropdown.classList.add('active');
    } catch (err) {
        console.error('Search error:', err);
    }
}

/* ═══════════════════════════════════════
   ADD TO CART (Quick) — AJAX
   Gửi request đến cart_api.php, cập nhật badge
   ═══════════════════════════════════════ */
async function addToCartQuick(productId, btnElement) {
    // Disable button & show loading
    const originalHtml = btnElement.innerHTML;
    btnElement.disabled = true;
    btnElement.innerHTML = `
        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>`;

    try {
        const resp = await fetch('/cart_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', product_id: productId, quantity: 1 })
        });

        const data = await resp.json();

        if (data.success) {
            // Cập nhật badge giỏ hàng
            updateCartBadge(data.cartCount);

            // Show success button state
            btnElement.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Đã thêm!`;
            btnElement.classList.remove('bg-bb-yellow', 'hover:bg-yellow-300');
            btnElement.classList.add('bg-green-500', 'text-white');

            showToast('✓ Đã thêm vào giỏ hàng!', 'success');

            // Reset button sau 1.5s
            setTimeout(() => {
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
                btnElement.classList.remove('bg-green-500', 'text-white');
                btnElement.classList.add('bg-bb-yellow', 'hover:bg-yellow-300');
            }, 1500);
        } else {
            throw new Error(data.message || 'Lỗi thêm sản phẩm');
        }
    } catch (err) {
        showToast('✕ ' + err.message, 'error');
        btnElement.innerHTML = originalHtml;
        btnElement.disabled = false;
    }
}

/* ═══════════════════════════════════════
   CART BADGE — Cập nhật số lượng hiển thị
   ═══════════════════════════════════════ */
function updateCartBadge(count) {
    const badge = document.getElementById('cart-badge');
    if (!badge) return;

    if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('hidden');
        // Bounce animation
        badge.classList.remove('bounce');
        void badge.offsetWidth; // force reflow
        badge.classList.add('bounce');
    } else {
        badge.classList.add('hidden');
    }
}

/* ═══════════════════════════════════════
   TOAST NOTIFICATION — Hiển thị thông báo
   ═══════════════════════════════════════ */
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = message;

    container.appendChild(toast);

    // Auto remove
    setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/* ═══════════════════════════════════════
   UTILITY — Escape HTML để chống XSS
   ═══════════════════════════════════════ */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
