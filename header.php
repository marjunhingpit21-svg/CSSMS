<?php
include 'database/db.php';

// Calculate cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendyWear - Your Fashion Store</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/MainPage.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="logo">
                <h1><a href="index.php">TrendyWear</a></h1>
            </div>
            
            <nav class="main-nav">
                <a href="#">Men</a>
                <a href="#">Women</a>
                <a href="#">Kids</a>
                <a href="#">Accessories</a>
                <a href="#">Sale</a>
            </nav>

            <div class="header-actions">
                <!-- Cart Icon with Badge -->
                <a href="cart.php" class="cart-icon">
                    <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span id="cart-count" class="cart-badge" style="display: <?php echo $cart_count > 0 ? 'flex' : 'none'; ?>;">
                        <?php echo $cart_count; ?>
                    </span>
                </a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="user-greeting">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="admin_dashboard.php" class="btn-admin">Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-logout">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn-login">Login</a>
                    <a href="signup.php" class="btn-signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <script src="js/cart.js"></script>
</body>