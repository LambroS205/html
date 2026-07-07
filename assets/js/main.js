/**
 * BestBuy Store — Main JavaScript (Upgraded)
 * Vanilla JS (ES6+) cho tương tác người dùng
 * 
 * Nâng cấp Bước 1:
 * - Live Search: API riêng, ảnh thumbnail, skeleton, keyboard nav, highlight
 * - Toast: Icon SVG, progress bar, close button, stacking limit
 */

document.addEventListener('DOMContentLoaded', () => {
    initStickyHeader();
    initMobileMenu();
    initLiveSearch();
    initUserMenu();
});

/* ═══════════════════════════════════════
   USER DROPDOWN MENU — Toggle account dropdown
   ═══════════════════════════════════════ */
function initUserMenu() {
    const btn = document.getElementById('user-menu-btn');
    const dropdown = document.getElementById('user-dropdown');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#user-menu-wrapper')) {
            dropdown.classList.add('hidden');
        }
    });
}

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
   LIVE SEARCH — Tìm kiếm gợi ý realtime (NÂNG CẤP)
   
   Cải tiến so với phiên bản cũ:
   1. Gọi API riêng /api/search-ajax.php (thay vì search.php?ajax=1)
   2. Hiển thị ảnh sản phẩm thumbnail trong dropdown
   3. Skeleton loading khi đang fetch
   4. Keyboard navigation (↑↓ Enter Escape)
   5. Highlight từ khóa tìm kiếm trong kết quả
   ═══════════════════════════════════════ */
function initLiveSearch() {
    const input    = document.getElementById('search-input');
    const dropdown = document.getElementById('search-dropdown');
    if (!input || !dropdown) return;

    let debounceTimer = null;
    let activeIndex = -1;      // Index item đang được highlight bằng keyboard
    let currentResults = [];   // Cache kết quả hiện tại cho keyboard nav

    // ── Input event: debounce 300ms ──
    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = input.value.trim();
        activeIndex = -1;

        if (query.length < 2) {
            dropdown.classList.remove('active');
            dropdown.innerHTML = '';
            return;
        }

        // Hiển thị skeleton loading ngay lập tức
        showSearchSkeleton(dropdown);

        // Debounce 300ms để tránh spam request
        debounceTimer = setTimeout(() => {
            fetchSearchResults(query, dropdown);
        }, 300);
    });

    // ── Keyboard navigation ──
    input.addEventListener('keydown', (e) => {
        if (!dropdown.classList.contains('active')) return;

        const items = dropdown.querySelectorAll('.search-dropdown-item');
        if (items.length === 0) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                updateActiveItem(items, activeIndex);
                break;

            case 'ArrowUp':
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, -1);
                updateActiveItem(items, activeIndex);
                // Nếu activeIndex = -1, trả focus về input
                if (activeIndex === -1) {
                    items.forEach(i => i.classList.remove('search-item-active'));
                }
                break;

            case 'Enter':
                e.preventDefault();
                if (activeIndex >= 0 && items[activeIndex]) {
                    // Navigate đến sản phẩm đang highlight
                    const href = items[activeIndex].getAttribute('href');
                    if (href) window.location.href = href;
                } else {
                    // Submit form tìm kiếm bình thường
                    input.closest('form')?.submit();
                }
                break;

            case 'Escape':
                dropdown.classList.remove('active');
                activeIndex = -1;
                input.blur();
                break;
        }
    });

    // ── Đóng dropdown khi click bên ngoài ──
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#search-wrapper')) {
            dropdown.classList.remove('active');
            activeIndex = -1;
        }
    });

    // ── Mở lại khi focus vào input ──
    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2 && dropdown.innerHTML) {
            dropdown.classList.add('active');
        }
    });
}

/**
 * Hiển thị skeleton loading trong dropdown khi đang fetch
 */
function showSearchSkeleton(dropdown) {
    dropdown.innerHTML = `
        <div class="p-3 space-y-3">
            ${[1, 2, 3].map(() => `
                <div class="flex items-center gap-3 animate-pulse">
                    <div class="w-12 h-12 bg-gray-200 rounded-lg shrink-0"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3.5 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-100 rounded w-1/2"></div>
                    </div>
                    <div class="h-4 bg-gray-200 rounded w-16 shrink-0"></div>
                </div>
            `).join('')}
        </div>`;
    dropdown.classList.add('active');
}

/**
 * Highlight item đang được chọn bằng keyboard
 */
function updateActiveItem(items, index) {
    items.forEach((item, i) => {
        if (i === index) {
            item.classList.add('search-item-active');
            // Scroll item vào view nếu cần
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            item.classList.remove('search-item-active');
        }
    });
}

/**
 * Highlight từ khóa trong text kết quả tìm kiếm
 * Dùng regex escape để an toàn với ký tự đặc biệt
 */
function highlightQuery(text, query) {
    if (!query) return escapeHtml(text);
    const escaped = escapeHtml(text);
    const queryEscaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${queryEscaped})`, 'gi');
    return escaped.replace(regex, '<mark class="search-highlight">$1</mark>');
}

/**
 * Fetch kết quả từ API riêng /api/search-ajax.php
 */
async function fetchSearchResults(query, dropdown) {
    try {
        const resp = await fetch(`/api/search-ajax.php?q=${encodeURIComponent(query)}`);
        const data = await resp.json();

        if (!data.results || data.results.length === 0) {
            dropdown.innerHTML = `
                <div class="p-5 text-center">
                    <span class="text-3xl block mb-2">🔍</span>
                    <p class="text-sm text-gray-500">
                        Không tìm thấy sản phẩm nào cho "<strong class="text-gray-700">${escapeHtml(query)}</strong>"
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Hãy thử từ khóa khác</p>
                </div>`;
        } else {
            dropdown.innerHTML = data.results.map((p, idx) => {
                const displayPrice = p.sale_price || p.price;
                const hasDiscount = p.sale_price && p.sale_price < p.price;
                
                // Ảnh thumbnail hoặc fallback category icon
                const imgHtml = p.image_url
                    ? `<img src="${escapeHtml(p.image_url)}" alt="" class="w-full h-full object-contain p-1" loading="lazy">`
                    : `<span class="text-xl">${p.category_icon || '📦'}</span>`;

                return `
                <a href="/product.php?slug=${escapeHtml(p.slug)}" 
                   class="search-dropdown-item" 
                   data-index="${idx}">
                    <div class="search-item-thumb">
                        ${imgHtml}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate leading-tight">
                            ${highlightQuery(p.name, query)}
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">${escapeHtml(p.category_name)}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-sm font-bold ${hasDiscount ? 'text-red-500' : 'text-bb-blue'} whitespace-nowrap">
                            ${new Intl.NumberFormat('vi-VN').format(displayPrice)} VNĐ
                        </span>
                        ${hasDiscount ? `<span class="block text-[10px] text-gray-400 line-through">${new Intl.NumberFormat('vi-VN').format(p.price)} VNĐ</span>` : ''}
                    </div>
                </a>`;
            }).join('');

            // Link "Xem tất cả" ở cuối dropdown
            if (data.total > 0) {
                dropdown.innerHTML += `
                    <a href="/search.php?q=${encodeURIComponent(query)}" 
                       class="search-dropdown-viewall">
                        Xem tất cả ${data.total} kết quả
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>`;
            }
        }

        dropdown.classList.add('active');
    } catch (err) {
        console.error('Search error:', err);
        dropdown.innerHTML = `
            <div class="p-4 text-center text-sm text-red-400">
                Lỗi tải kết quả. Vui lòng thử lại.
            </div>`;
        dropdown.classList.add('active');
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

            if (typeof openCartDrawer === 'function') {
                openCartDrawer();
            } else {
                showToast('Đã thêm vào giỏ hàng thành công!', 'success');
            }

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
        showToast(err.message, 'error');
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
   TOAST NOTIFICATION — Hệ thống thông báo nâng cao
   
   Cải tiến so với phiên bản cũ:
   1. Icon SVG riêng cho từng type (success, error, info, warning)
   2. Nút đóng thủ công (X)
   3. Progress bar hiển thị thời gian tự đóng
   4. Stacking thông minh: tối đa 3 toast, cũ nhất tự đóng
   5. Animation mượt hơn: slide-in từ phải
   ═══════════════════════════════════════ */

const TOAST_ICONS = {
    success: `<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>`,
    error: `<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>`,
    warning: `<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
    </svg>`,
    info: `<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>`
};

const MAX_TOASTS = 3;

function showToast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    // Stacking limit: xóa toast cũ nhất nếu quá 3
    const existingToasts = container.querySelectorAll('.toast');
    if (existingToasts.length >= MAX_TOASTS) {
        const oldest = existingToasts[0];
        oldest.classList.add('toast-removing');
        setTimeout(() => oldest.remove(), 300);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-body">
            ${TOAST_ICONS[type] || TOAST_ICONS.info}
            <span class="toast-message">${escapeHtml(message)}</span>
            <button class="toast-close" onclick="closeToast(this)" aria-label="Đóng">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="toast-progress">
            <div class="toast-progress-bar toast-progress-${type}" style="animation-duration: ${duration}ms"></div>
        </div>
    `;

    container.appendChild(toast);

    // Force reflow để animation chạy đúng
    void toast.offsetWidth;
    toast.classList.add('toast-visible');

    // Auto remove sau duration
    const autoRemoveTimer = setTimeout(() => {
        dismissToast(toast);
    }, duration);

    // Lưu timer để cancel nếu user đóng thủ công
    toast._autoRemoveTimer = autoRemoveTimer;
}

/**
 * Đóng toast khi user click nút X
 */
function closeToast(btn) {
    const toast = btn.closest('.toast');
    if (!toast) return;
    clearTimeout(toast._autoRemoveTimer);
    dismissToast(toast);
}

/**
 * Animation đóng toast
 */
function dismissToast(toast) {
    if (!toast || toast.classList.contains('toast-removing')) return;
    toast.classList.add('toast-removing');
    setTimeout(() => toast.remove(), 350);
}

/* ═══════════════════════════════════════
   UTILITY — Escape HTML để chống XSS
   ═══════════════════════════════════════ */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
/* Admin Utilities */
function confirmDelete(msg) {
    return confirm(msg || 'B?n c� ch?c ch?n mu?n x�a?');
}

