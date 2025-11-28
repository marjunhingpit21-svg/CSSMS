<aside class="w-80 bg-gradient-to-b from-slate-900 via-slate-800 to-black text-gray-100 fixed inset-y-0 left-0 z-50 shadow-2xl border-r border-slate-700">
    <div class="h-20 flex items-center justify-center border-b border-slate-700 bg-black/40 backdrop-blur-xl">
        <h1 class="text-3xl font-black tracking-tighter">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-violet-400 to-pink-400">TRENDY</span>
            <span class="text-gray-300">ADMIN</span>
        </h1>
    </div>

    <nav class="p-6 space-y-1">
        <?php $current = $_SERVER['REQUEST_URI']; ?>
        <a href="index.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all <?= $current === '/trendywear/admin/index.php' ? 'bg-gradient-to-r from-violet-600 to-pink-600 shadow-2xl shadow-pink-500/30 text-white font-semibold' : 'hover:bg-white/5 text-gray-300' ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span class="text-lg">Dashboard</span>
        </a>

        <a href="products/index.php" class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all <?= strpos($current, '/products') ? 'bg-gradient-to-r from-violet-600 to-pink-600 shadow-2xl shadow-pink-500/30 text-white font-semibold' : 'hover:bg-white/5 text-gray-300' ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span class="text-lg">Products & Stock</span>
        </a>

        <a href="/trendywear/admin/inventory/" class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all <?= strpos($current, '/inventory') ? 'bg-gradient-to-r from-violet-600 to-pink-600 shadow-2xl shadow-pink-500/30 text-white font-semibold' : 'hover:bg-white/5 text-gray-300' ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
            <span class="text-lg">Inventory Alerts</span>
        </a>

        <a href="/trendywear/admin/suppliers/" class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all <?= strpos($current, '/suppliers') ? 'bg-gradient-to-r from-violet-600 to-pink-600 shadow-2xl shadow-pink-500/30 text-white font-semibold' : 'hover:bg-white/5 text-gray-300' ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span class="text-lg">Suppliers</span>
        </a>

        <a href="/trendywear/admin/analytics/" class="flex items-center gap-4 px-5 py-4 rounded-2xl transition-all <?= strpos($current, '/analytics') ? 'bg-gradient-to-r from-violet-600 to-pink-600 shadow-2xl shadow-pink-500/30 text-white font-semibold' : 'hover:bg-white/5 text-gray-300' ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span class="text-lg">Analytics & AI</span>
        </a>
    </nav>

    <div class="absolute bottom-0 left-0 right-0 p-6 border-t border-slate-700 bg-black/30 backdrop-blur-xl">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center font-bold text-xl">A</div>
            <div>
                <p class="font-semibold text-white">Super Admin</p>
                <p class="text-xs text-gray-400">admin@trendywear.com</p>
            </div>
        </div>
    </div>
</aside>