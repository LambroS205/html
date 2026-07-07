            </main>
        </div>
    </div>

    <!-- Admin Toast Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"></div>

    <script>
    function showToast(msg, type = 'info', dur = 3000) {
        const c = document.getElementById('toast-container');
        if (!c) return;
        const colors = { success: 'from-emerald-600 to-green-600', error: 'from-red-600 to-rose-600', info: 'from-blue-600 to-indigo-600' };
        const t = document.createElement('div');
        t.className = `pointer-events-auto px-5 py-3 rounded-xl text-white text-sm font-medium shadow-xl bg-gradient-to-r ${colors[type] || colors.info}`;
        t.style.animation = 'slideInUp 0.3s ease-out';
        t.textContent = msg;
        c.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateY(10px)'; t.style.transition = 'all 0.3s'; setTimeout(() => t.remove(), 300); }, dur);
    }

    function confirmDelete(msg) {
        return confirm(msg || 'Bạn có chắc chắn muốn xóa?');
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    @keyframes slideInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
    </style>
</body>
</html>
