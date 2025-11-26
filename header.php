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
                <a href="index.php">
                    <img src="images/logo.png" alt="TrendyWear Logo" class="logo-img">
                    <h1>altière</h1>
                </a>
            </div>

            <nav class="main-nav">
                <!-- <a href="#">Men</a>
                <a href="#">Women</a>
                <a href="#">Kids</a>
                <a href="#">Accessories</a>
                <a href="#">Sale</a> -->
            </nav>

            <div class="header-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Cart Icon (for logged in users) -->
                    <a href="cart.php" class="cart-icon">
                        <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span id="cart-count" class="cart-badge"
                            style="display: <?php echo $cart_count > 0 ? 'flex' : 'none'; ?>;">
                            <?php echo $cart_count; ?>
                        </span>
                    </a>
                    
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=e91e63&color=fff&bold=true"
                            alt="Profile" class="profile-pic">
                        <span class="user-greeting">Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="admin_dashboard.php" class="btn-admin">Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-logout">Logout</a>
                <?php else: ?>
                    <!-- Cart Icon with Badge -->
                    <a href="cart.php" class="cart-icon">
                        <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span id="cart-count" class="cart-badge"
                            style="display: <?php echo $cart_count > 0 ? 'flex' : 'none'; ?>;">
                            <?php echo $cart_count; ?>
                        </span>
                    </a>
                    <a href="#" onclick="openModal('loginModal'); return false;" class="btn-login">Login</a>
                    <a href="#" onclick="openModal('signupModal'); return false;" class="btn-signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>