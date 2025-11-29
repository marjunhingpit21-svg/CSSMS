<!-- sidebar.php -->
<link rel="stylesheet" href="../css/sidebar.css">

<aside class="admin-sidebar">


    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php 
        $current = $_SERVER['REQUEST_URI'];
        $base_path = '/trendywear/admin/';
        ?>

        <a href="dashboard.php" class="<?= strpos($current, 'dashboard.php') !== false || $current === $base_path ? 'active' : '' ?>">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span>Dashboard</span>
        </a>

        <a href="products/index.php" class="<?= strpos($current, '/products') !== false ? 'active' : '' ?>">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <span>Products & Stock</span>
        </a>

        <a href="inventory/" class="<?= strpos($current, '/inventory') !== false ? 'active' : '' ?>">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <span>Inventory Alerts</span>
        </a>

        <a href="suppliers/" class="<?= strpos($current, '/suppliers') !== false ? 'active' : '' ?>">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span>Suppliers</span>
        </a>

        <a href="analytics/" class="<?= strpos($current, '/analytics') !== false ? 'active' : '' ?>">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <span>Analytics & AI</span>
        </a>
    </nav>

    <!-- Footer – Admin Info -->
    <div class="sidebar-footer">
        <div class="user-box">
            <div class="avatar">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="info">
                <h4><?= htmlspecialchars($_SESSION['username'] ?? 'Super Admin') ?></h4>
                <p><?= $_SESSION['email'] ?? 'admin@trendywear.com' ?></p>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile menu toggle button (add to your admin header if needed) -->
<style>
.mobile-menu-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #333;
    padding: 8px;
}

@media (max-width: 768px) {
    .mobile-menu-toggle {
        display: block;
    }
    
    /* Add padding to main content when sidebar is open on mobile */
    .admin-main-content {
        transition: margin-left 0.3s ease;
    }
    
    .admin-sidebar.mobile-open + .admin-main-content {
        margin-left: 220px;
    }
}
</style>