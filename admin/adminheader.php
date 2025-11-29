<!-- adminheader.php -->
<?php
// Optional: You can calculate admin notifications here if you want
$admin_notifications = 3; // Example count (replace with real logic later)
?>

<link rel="stylesheet" href="../css/adminheader.css">
<header class="main-header admin-header">
    <div class="container">
        <!-- Logo -->
        <div class="logo">
            <a href="admin_dashboard.php">
                <img src="../../img/a.png" alt="TrendyWear Admin" class="logo-img">
            </a>
        </div>

        <!-- Page Title (centered on large screens) -->
        <div class="admin-title">
            <h2>Admin Panel</h2>
        </div>

        <!-- Right Actions -->
        <div class="header-actions">
            <!-- Notifications Bell -->
            <a href="#" class="orders-icon admin-notif-icon" title="Notifications">
                <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <?php if ($admin_notifications > 0): ?>
                    <span class="orders-badge" style="display: flex;">
                        <?= $admin_notifications ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Admin Profile
            <div class="user-profile">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username'] ?? 'Admin') ?>&background=e91e63&color=fff&bold=true"
                     alt="Admin" class="profile-pic">
                <span class="user-greeting">Hello, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
            </div> -->

            <!-- Back to Store Button -->
            <a href="../../index.php" class="btn-admin">Back to Store</a>

            <!-- Logout -->
            <a href="../logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
</header>