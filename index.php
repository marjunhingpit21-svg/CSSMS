<?php 
include 'Database/db.php';
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart count for header
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Get orders count for header
$orders_count = 0;
if (isset($_SESSION['user_id'])) 
{
    include 'orders_count.php';
    $orders_count = getOrdersCount($conn, $_SESSION['user_id']);
}

// Handle ad banner close
if (isset($_POST['close_ad'])) {
    $_SESSION['ad_closed'] = true;
}

// Get trending products (most bought this month with completed/received status)
$trending_products = [];
$current_month = date('Y-m');
$trending_query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.description,
        p.price,
        p.cost_price,
        p.image_url,
        p.category_id,
        p.gender_id,
        p.age_group_id,
        c.category_name,
        COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN order_items oi ON p.product_id = (
        SELECT i.product_id 
        FROM inventory i 
        WHERE i.inventory_id = oi.inventory_id
        LIMIT 1
    )
    LEFT JOIN orders o ON oi.order_id = o.order_id 
        AND DATE_FORMAT(o.order_date, '%Y-%m') = ?
        AND o.status IN ('received', 'completed', 'delivered')
    GROUP BY p.product_id
    HAVING total_sold > 0
    ORDER BY total_sold DESC
    LIMIT 8
";

$trending_stmt = $conn->prepare($trending_query);
$trending_stmt->bind_param("s", $current_month);
$trending_stmt->execute();
$trending_result = $trending_stmt->get_result();

while($trending_row = $trending_result->fetch_assoc()) {
    // Get stock for trending products
    $stock_sql = "SELECT COALESCE(SUM(stock_quantity), 0) as total_stock 
                  FROM product_sizes WHERE product_id = ?";
    $stock_stmt = $conn->prepare($stock_sql);
    $stock_stmt->bind_param("i", $trending_row['product_id']);
    $stock_stmt->execute();
    $stock_result = $stock_stmt->get_result();
    $stock_row = $stock_result->fetch_assoc();
    
    $trending_row['total_stock'] = $stock_row['total_stock'];
    $trending_row['total_sold'] = intval($trending_row['total_sold']);
    $trending_products[] = $trending_row;
    $stock_stmt->close();
}
$trending_stmt->close();

// Get new arrival products (added this month, excluding existing items with new stock)
$new_arrival_products = [];
$new_arrival_query = "
    SELECT 
        p.*,
        c.category_name,
        COALESCE(SUM(ps.stock_quantity), 0) as total_stock
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
    WHERE DATE_FORMAT(p.created_at, '%Y-%m') = ?
        AND NOT EXISTS (
            SELECT 1 FROM order_items oi
            JOIN inventory i ON oi.inventory_id = i.inventory_id
            JOIN orders o ON oi.order_id = o.order_id
            WHERE i.product_id = p.product_id
                AND o.status IN ('received', 'completed', 'delivered')
        )
    GROUP BY p.product_id
    ORDER BY p.created_at DESC
    LIMIT 8
";

$new_arrival_stmt = $conn->prepare($new_arrival_query);
$new_arrival_stmt->bind_param("s", $current_month);
$new_arrival_stmt->execute();
$new_arrival_result = $new_arrival_stmt->get_result();

while($new_arrival_row = $new_arrival_result->fetch_assoc()) {
    $new_arrival_row['total_stock'] = intval($new_arrival_row['total_stock']);
    $new_arrival_products[] = $new_arrival_row;
}
$new_arrival_stmt->close();

// Handle login
$login_error = '';
if (isset($_POST['login_submit'])) {
    $email = trim($_POST['login_email'] ?? '');
    $password = $_POST['login_password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $login_error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, email, password_hash, role, is_active FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (!$user['is_active']) {
                $login_error = 'Your account has been deactivated. Please contact support.';
            } elseif (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                
                header('Location: index.php');
                exit();
            } else {
                $login_error = 'Invalid email or password.';
            }
        } else {
            $login_error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}

// Handle signup
$signup_error = '';
$signup_success = '';
if (isset($_POST['signup_submit'])) {
    $username = trim($_POST['signup_username'] ?? '');
    $email = trim($_POST['signup_email'] ?? '');
    $password = $_POST['signup_password'] ?? '';
    $confirm_password = $_POST['signup_confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $signup_error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $signup_error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $signup_error = 'Passwords do not match.';
    } else {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
        $check_stmt->bind_param("ss", $email, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $signup_error = 'Email or username already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'customer')");
            $insert_stmt->bind_param("sss", $username, $email, $password_hash);
            
            if ($insert_stmt->execute()) {
                $signup_success = 'Account created successfully! You can now login.';
            } else {
                $signup_error = 'An error occurred during registration. Please try again.';
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
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
    <?php include 'header.php'; ?>

<!-- Brand Logos Marquee Section -->
<section class="brand-marquee-section">
    <div class="marquee-container">
        <!-- First set - Add your own logo images here -->
        <div class="brand-item"><img src="img/nike.png" alt="Brand 1" class="brand-logo"></div>
        <div class="brand-item"><img src="img/channel.png" alt="Brand 2" class="brand-logo"></div>
        <div class="brand-item"><img src="img/lacoste.png" alt="Brand 3" class="brand-logo"></div>
        <div class="brand-item"><img src="img/lululemon.png" alt="Brand 4" class="brand-logo"></div>
        <div class="brand-item"><img src="img/gucci.png" alt="Brand 5" class="brand-logo"></div>
        <div class="brand-item"><img src="img/polo.png" alt="Brand 6" class="brand-logo"></div>
        
        <!-- Duplicate set - Copy the same logos for seamless loop -->
        <div class="brand-item"><img src="img/nike.png" alt="Brand 1" class="brand-logo"></div>
        <div class="brand-item"><img src="img/channel.png" alt="Brand 2" class="brand-logo"></div>
        <div class="brand-item"><img src="img/lacoste.png" alt="Brand 3" class="brand-logo"></div>
        <div class="brand-item"><img src="img/lululemon.png" alt="Brand 4" class="brand-logo"></div>
        <div class="brand-item"><img src="img/gucci.png" alt="Brand 5" class="brand-logo"></div>
        <div class="brand-item"><img src="img/polo.png" alt="Brand 6" class="brand-logo"></div>

        <!-- Duplicate set - Copy the same logos for seamless loop -->
        <div class="brand-item"><img src="img/nike.png" alt="Brand 1" class="brand-logo"></div>
        <div class="brand-item"><img src="img/channel.png" alt="Brand 2" class="brand-logo"></div>
        <div class="brand-item"><img src="img/lacoste.png" alt="Brand 3" class="brand-logo"></div>
        <div class="brand-item"><img src="img/lululemon.png" alt="Brand 4" class="brand-logo"></div>
        <div class="brand-item"><img src="img/gucci.png" alt="Brand 5" class="brand-logo"></div>
        <div class="brand-item"><img src="img/polo.png" alt="Brand 6" class="brand-logo"></div>
    </div>
</section>

<div class="page-wrapper">

    <!-- Section Navigation Links -->
    

    <section class="filters-section">
        <div class="container">
            <div class="filters-bar">
                <!-- Age Group Links on the left -->
                <div class="age-group-links">
                    <a href="#" class="age-group-link active" data-age-group="all">All Ages</a>
                    <?php
                    // Fetch age groups from database
                    $age_sql = "SELECT * FROM age_groups ORDER BY age_group_id";
                    $age_result = $conn->query($age_sql);
                    
                    if ($age_result->num_rows > 0) {
                        while($age_group = $age_result->fetch_assoc()) {
                            echo '<a href="#" class="age-group-link" data-age-group="'.$age_group['age_group_id'].'">'.$age_group['age_group_name'].'</a>';
                        }
                    }
                    ?>
                </div>

                <!-- Search and Filters on the right -->
                <div class="search-filters-container">
                    <input type="text" id="searchBar" placeholder="Search clothes..." class="search-input">

                    <select id="categoryFilter" class="filter-select">
                        <option value="all">All Categories</option>
                        <?php
                        // Fetch categories from database
                        $cat_sql = "SELECT * FROM categories ORDER BY category_id";
                        $cat_result = $conn->query($cat_sql);
                        
                        if ($cat_result->num_rows > 0) {
                            while($category = $cat_result->fetch_assoc()) {
                                echo '<option value="'.$category['category_id'].'">'.$category['category_name'].'</option>';
                            }
                        }
                        ?>
                    </select>

                    <select id="priceSort" class="filter-select">
                        <option value="default">Sort by</option>
                        <option value="low">Price: Low to High</option>
                        <option value="high">Price: High to Low</option>
                    </select>
                </div>
            </div>
        </div>
        <section class="section-nav">
        <div class="container">
            <nav class="section-nav-links">
                <a href="#trendingSection" class="section-nav-link <?php echo !empty($trending_products) ? 'active' : ''; ?>" id="navTrending">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                    </svg>
                    Trending
                </a>
                <a href="#newArrivalSection" class="section-nav-link <?php echo empty($trending_products) && !empty($new_arrival_products) ? 'active' : ''; ?>" id="navNewArrivals">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                    </svg>
                    New Arrivals
                </a>
                <a href="#allProductsSection" class="section-nav-link <?php echo empty($trending_products) && empty($new_arrival_products) ? 'active' : ''; ?>" id="navAllItems">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                    </svg>
                    All Items
                </a>
            </nav>
        </div>
    </section>
    </section>

    <!-- Trending Section -->
    <section class="featured-section" id="trendingSection">
        <div class="container">
            <div class="section-header">
                <h2>Trending This Month</h2>
                <p class="section-subtitle">Most popular items bought this month</p>
            </div>
            <?php if (!empty($trending_products)): ?>
            <div class="products-grid trending-grid" id="trendingGrid">
                <?php foreach($trending_products as $trending): 
                    $stock = intval($trending['total_stock']);
                    $stock_status = '';
                    $stock_class = '';
                    
                    if ($stock > 10) {
                        $stock_status = 'In Stock';
                        $stock_class = 'in-stock';
                    } elseif ($stock > 0) {
                        $stock_status = 'Only ' . $stock . ' left';
                        $stock_class = 'low-stock';
                    } else {
                        $stock_status = 'Out of Stock';
                        $stock_class = 'out-of-stock';
                    }
                ?>
                <div class="product-card" data-category="<?php echo $trending['category_id']; ?>" 
                     data-price="<?php echo $trending['price']; ?>" 
                     data-age-group="<?php echo $trending['age_group_id']; ?>">
                    <span class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_status; ?></span>
                    <?php if ($trending['total_sold'] > 0): ?>
                    <span class="trending-badge"> <?php echo $trending['total_sold']; ?> sold</span>
                    <?php endif; ?>
                    <img src="<?php echo $trending['image_url']; ?>" 
                         alt="<?php echo htmlspecialchars($trending['product_name']); ?>" 
                         onerror="this.src='https://via.placeholder.com/300x400?text=No+Image'">
                    <h3><?php echo htmlspecialchars($trending['product_name']); ?></h3>
                    <p class="price">₱<?php echo number_format($trending['price'], 2); ?></p>
                    <div class="product-actions">
                        <a href="product_details.php?id=<?php echo $trending['product_id']; ?>" 
                           class="btn-add-cart" 
                           style="flex: 1; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;"
                           <?php echo ($stock <= 0 ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''); ?>>
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <?php echo ($stock <= 0 ? 'View' : 'Add to cart'); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <svg width="64" height="64" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm7-1a1 1 0 11-2 0 1 1 0 012 0zm-7.536 5.879a1 1 0 001.415 0 3 3 0 014.242 0 1 1 0 001.415-1.415 5 5 0 00-7.072 0 1 1 0 000 1.415z" clip-rule="evenodd"/>
                </svg>
                <h3>No Trending Items Yet</h3>
                <p>Be the first to make a purchase this month!</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- New Arrival Section -->
    <section class="featured-section" id="newArrivalSection">
        <div class="container">
            <div class="section-header">
                <h2>New Arrivals</h2>
                <p class="section-subtitle">Freshly added items this month</p>
            </div>
            <?php if (!empty($new_arrival_products)): ?>
            <div class="products-grid new-arrival-grid" id="newArrivalGrid">
                <?php foreach($new_arrival_products as $new_arrival): 
                    $stock = intval($new_arrival['total_stock']);
                    $stock_status = '';
                    $stock_class = '';
                    
                    if ($stock > 10) {
                        $stock_status = 'In Stock';
                        $stock_class = 'in-stock';
                    } elseif ($stock > 0) {
                        $stock_status = 'Only ' . $stock . ' left';
                        $stock_class = 'low-stock';
                    } else {
                        $stock_status = 'Out of Stock';
                        $stock_class = 'out-of-stock';
                    }
                    
                    // Calculate how many days since added
                    $created_date = new DateTime($new_arrival['created_at']);
                    $current_date = new DateTime();
                    $interval = $current_date->diff($created_date);
                    $days_ago = $interval->days;
                ?>
                <div class="product-card" data-category="<?php echo $new_arrival['category_id']; ?>" 
                     data-price="<?php echo $new_arrival['price']; ?>" 
                     data-age-group="<?php echo $new_arrival['age_group_id']; ?>">
                    <span class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_status; ?></span>
                    <span class="new-badge">NEW</span>
                    <img src="<?php echo $new_arrival['image_url']; ?>" 
                         alt="<?php echo htmlspecialchars($new_arrival['product_name']); ?>" 
                         onerror="this.src='https://via.placeholder.com/300x400?text=No+Image'">
                    <h3><?php echo htmlspecialchars($new_arrival['product_name']); ?></h3>
                    <p class="price">₱<?php echo number_format($new_arrival['price'], 2); ?></p>
                    <?php if ($days_ago <= 7): ?>
                    <p class="new-arrival-tag">Just Added!</p>
                    <?php endif; ?>
                    <div class="product-actions">
                        <a href="product_details.php?id=<?php echo $new_arrival['product_id']; ?>" 
                           class="btn-add-cart" 
                           style="flex: 1; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;"
                           <?php echo ($stock <= 0 ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''); ?>>
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <?php echo ($stock <= 0 ? 'View' : 'Add to cart'); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <svg width="64" height="64" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm7-1a1 1 0 11-2 0 1 1 0 012 0zm-7.536 5.879a1 1 0 001.415 0 3 3 0 014.242 0 1 1 0 001.415-1.415 5 5 0 00-7.072 0 1 1 0 000 1.415z" clip-rule="evenodd"/>
                </svg>
                <h3>No New Arrivals</h3>
                <p>Check back soon for new items!</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- All Products Section -->
    <section class="products-section" id="allProductsSection">
        <div class="container">
            <div class="section-header">
                <h2>All Products</h2>
                <p class="section-subtitle">Browse our complete collection</p>
            </div>
            <div class="products-grid" id="productsGrid">
                <?php
                // Updated query to get total stock from product_sizes table and include age_group_id
                $sql = "SELECT p.*, c.category_name, 
                        COALESCE(SUM(ps.stock_quantity), 0) as total_stock
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.category_id 
                        LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
                        GROUP BY p.product_id
                        ORDER BY p.created_at DESC";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $stock = intval($row['total_stock']);
                        $stock_status = '';
                        $stock_class = '';
                        
                        if ($stock > 10) {
                            $stock_status = 'In Stock';
                            $stock_class = 'in-stock';
                        } elseif ($stock > 0) {
                            $stock_status = 'Only ' . $stock . ' left';
                            $stock_class = 'low-stock';
                        } else {
                            $stock_status = 'Out of Stock';
                            $stock_class = 'out-of-stock';
                        }
                        
                        echo '
                        <div class="product-card" data-category="'.$row['category_id'].'" data-price="'.$row['price'].'" data-age-group="'.$row['age_group_id'].'">
                            <span class="stock-badge '.$stock_class.'">'.$stock_status.'</span>
                            <img src="'.$row['image_url'].'" alt="'.$row['product_name'].'" onerror="this.src=\'https://via.placeholder.com/300x400?text=No+Image\'">
                            <h3>'.htmlspecialchars($row['product_name']).'</h3>
                            <p class="price">₱'.number_format($row['price'], 2).'</p>
                            <div class="product-actions">
                                <a href="product_details.php?id='.$row['product_id'].'" class="btn-add-cart" style="flex: 1; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;" '.($stock <= 0 ? 'style="opacity: 0.5; cursor: not-allowed;"' : '').'>
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    '.($stock <= 0 ? 'View' : 'Add to cart').'
                                </a>
                            </div>
                        </div>';
                    }
                } else {
                    echo '<div class="empty-state">
                            <svg width="64" height="64" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm7-1a1 1 0 11-2 0 1 1 0 012 0zm-7.536 5.879a1 1 0 001.415 0 3 3 0 014.242 0 1 1 0 001.415-1.415 5 5 0 00-7.072 0 1 1 0 000 1.415z" clip-rule="evenodd"/>
                            </svg>
                            <h3>No Products Found</h3>
                            <p>No products are available at the moment.</p>
                          </div>';
                }
                ?>
            </div>
        </div>
    </section>
</div>

    <!-- Fixed Bottom Ad Banner -->
    <?php if (!isset($_SESSION['ad_closed'])): ?>
    <div class="fixed-ad-banner" id="fixedAdBanner">
        <button class="ad-close-btn" onclick="closeAdBanner()">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
        <div class="ad-banner-content">
            <div class="ad-text">
                <h3>Summer Sale! 🎉</h3>
                <p>Up to 50% off on selected items</p>
            </div>
            <a href="#productsGrid" class="btn-ad-shop">Shop Now</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Success Toast -->
    <div id="successToast" class="toast">
        <svg class="toast-icon" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span>Added to cart!</span>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal <?php echo $login_error ? 'active' : ''; ?>">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
            <h2>Welcome Back</h2>
            <p class="modal-subtitle">Login to your TrendyWear account</p>
            
            <?php if ($login_error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php">
                <div class="form-group">
                    <label for="login_email">Email Address</label>
                    <input type="email" id="login_email" name="login_email" placeholder="your.email@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="login_password">Password</label>
                    <input type="password" id="login_password" name="login_password" placeholder="Enter your password" required>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" name="login_submit" class="btn-submit">Login</button>
            </form>
            
            <div class="modal-footer">
                Don't have an account? <span class="switch-modal" onclick="switchModal('loginModal', 'signupModal')">Sign Up</span>
            </div>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal <?php echo ($signup_error || $signup_success) ? 'active' : ''; ?>">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('signupModal')">&times;</button>
            <h2>Create Account</h2>
            <p class="modal-subtitle">Join TrendyWear today</p>
            
            <?php if ($signup_error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <?php echo htmlspecialchars($signup_error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($signup_success): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <?php echo htmlspecialchars($signup_success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php">
                <div class="form-group">
                    <label for="signup_username">Username</label>
                    <input type="text" id="signup_username" name="signup_username" placeholder="Choose a username" required>
                </div>
                
                <div class="form-group">
                    <label for="signup_email">Email Address</label>
                    <input type="email" id="signup_email" name="signup_email" placeholder="your.email@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="signup_password">Password</label>
                    <input type="password" id="signup_password" name="signup_password" placeholder="At least 6 characters" required>
                    <small>Must be at least 6 characters long</small>
                </div>
                
                <div class="form-group">
                    <label for="signup_confirm_password">Confirm Password</label>
                    <input type="password" id="signup_confirm_password" name="signup_confirm_password" placeholder="Confirm your password" required>
                </div>
                
                <button type="submit" name="signup_submit" class="btn-submit">Create Account</button>
            </form>
            
            <div class="modal-footer">
                Already have an account? <span class="switch-modal" onclick="switchModal('signupModal', 'loginModal')">Login</span>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function switchModal(closeId, openId) {
            closeModal(closeId);
            setTimeout(() => openModal(openId), 150);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // Ad Banner functions
        function closeAdBanner() {
            const adBanner = document.getElementById('fixedAdBanner');
            if (adBanner) {
                adBanner.style.display = 'none';
                
                // Send AJAX request to remember the close state
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'close_ad=1'
                });
            }
        }

        // Show success toast if redirected from cart
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('added') === '1') {
            showToast();
            // Remove the parameter from URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        function showToast() {
            const toast = document.getElementById('successToast');
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Update cart badge
        function updateCartBadge() {
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) {
                const count = <?php echo $cart_count; ?>;
                cartBadge.textContent = count;
                cartBadge.style.display = count > 0 ? 'flex' : 'none';
            }
        }

        updateCartBadge();

        // Section Navigation
        const sectionNavLinks = document.querySelectorAll('.section-nav-link');
        const sections = {
            trending: document.getElementById('trendingSection'),
            newArrivals: document.getElementById('newArrivalSection'),
            allItems: document.getElementById('allProductsSection')
        };

        // Set active nav link based on URL hash on page load
        function setActiveNavFromHash() {
            const hash = window.location.hash;
            
            // Remove active class from all links
            sectionNavLinks.forEach(link => link.classList.remove('active'));
            
            if (hash === '#newArrivalSection') {
                document.getElementById('navNewArrivals').classList.add('active');
            } else if (hash === '#allProductsSection') {
                document.getElementById('navAllItems').classList.add('active');
            } else {
                // Default to trending or first available
                if (sections.trending && sections.trending.children.length > 1) {
                    document.getElementById('navTrending').classList.add('active');
                } else if (sections.newArrivals && sections.newArrivals.children.length > 1) {
                    document.getElementById('navNewArrivals').classList.add('active');
                } else {
                    document.getElementById('navAllItems').classList.add('active');
                }
            }
        }

        // Smooth scroll to section
        function scrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (section) {
                const headerHeight = document.querySelector('header').offsetHeight || 80;
                const sectionPosition = section.offsetTop - headerHeight - 20;
                
                window.scrollTo({
                    top: sectionPosition,
                    behavior: 'smooth'
                });
                
                // Update URL hash without jumping
                history.pushState(null, null, `#${sectionId}`);
                
                // Update active nav link
                sectionNavLinks.forEach(link => link.classList.remove('active'));
                document.querySelector(`[href="#${sectionId}"]`).classList.add('active');
            }
        }

        // Add click events to nav links
        sectionNavLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                scrollToSection(targetId);
            });
        });

        // Initialize on page load
        window.addEventListener('load', function() {
            setActiveNavFromHash();
            
            // If there's a hash in URL, scroll to that section
            if (window.location.hash) {
                setTimeout(() => {
                    scrollToSection(window.location.hash.substring(1));
                }, 100);
            }
        });

        // Product filtering & sorting
        const searchBar = document.getElementById('searchBar');
        const categoryFilter = document.getElementById('categoryFilter');
        const priceSort = document.getElementById('priceSort');
        const productsGrid = document.getElementById('productsGrid');
        const trendingGrid = document.getElementById('trendingGrid');
        const newArrivalGrid = document.getElementById('newArrivalGrid');
        const allProducts = document.querySelectorAll('.product-card');
        const regularProducts = document.querySelectorAll('#productsGrid .product-card');
        const trendingProducts = trendingGrid ? trendingGrid.querySelectorAll('.product-card') : [];
        const newArrivalProducts = newArrivalGrid ? newArrivalGrid.querySelectorAll('.product-card') : [];

        // Age Group Filtering
        const ageGroupLinks = document.querySelectorAll('.age-group-link');
        let currentAgeGroup = 'all';

        ageGroupLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all links
                ageGroupLinks.forEach(l => l.classList.remove('active'));
                
                // Add active class to clicked link
                this.classList.add('active');
                
                // Update current age group
                currentAgeGroup = this.dataset.ageGroup;
                
                // Filter products
                filterProducts();
                
                // Scroll to All Items section when filtering
                scrollToSection('allProductsSection');
            });
        });

        function filterProducts() {
            const searchText = searchBar.value.toLowerCase();
            const selectedCategory = categoryFilter.value;
            const sortOrder = priceSort.value;

            let productArray = Array.from(regularProducts);

            productArray = productArray.filter(product => {
                const title = product.querySelector('h3').textContent.toLowerCase();
                const category = product.getAttribute('data-category');
                const ageGroup = product.getAttribute('data-age-group');
                
                const matchesSearch = title.includes(searchText);
                const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
                const matchesAgeGroup = currentAgeGroup === 'all' || ageGroup === currentAgeGroup;
                
                return matchesSearch && matchesCategory && matchesAgeGroup;
            });

            if (sortOrder === 'low') {
                productArray.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
            } else if (sortOrder === 'high') {
                productArray.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
            }

            productsGrid.innerHTML = '';
            productArray.forEach(p => productsGrid.appendChild(p));
            
            // If no products after filtering
            if (productArray.length === 0) {
                productsGrid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                        <svg width="64" height="64" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 100-2 1 1 0 000 2zm7-1a1 1 0 11-2 0 1 1 0 012 0zm-7.536 5.879a1 1 0 001.415 0 3 3 0 014.242 0 1 1 0 001.415-1.415 5 5 0 00-7.072 0 1 1 0 000 1.415z" clip-rule="evenodd"/>
                        </svg>
                        <h3>No Products Match Your Filters</h3>
                        <p>Try adjusting your search or filters</p>
                    </div>
                `;
            }
        }

        // Function to handle search/filter actions and scroll to All Items
        function handleFilterAction() {
            filterProducts();
            // Scroll to All Items section
            scrollToSection('allProductsSection');
        }

        // Event listeners for filter actions
        searchBar.addEventListener('input', function() {
            if (this.value.length > 0) {
                handleFilterAction();
            }
        });
        
        categoryFilter.addEventListener('change', handleFilterAction);
        priceSort.addEventListener('change', handleFilterAction);

        // Also call filterProducts on page load
        filterProducts();
    </script>
</body>
</html>
<?php $conn->close(); ?>