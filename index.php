<?php 
include 'Database/db.php';
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart count for header
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

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

    <div class="page-wrapper">
        <section class="filters-section">
            <div class="container">
                <div class="filters-bar">
                    <input type="text" id="searchBar" placeholder="Search clothes..." class="search-input">

                    <select id="categoryFilter" class="filter-select">
                        <option value="all">All Categories</option>
                        <option value="1">T-Shirts</option>
                        <option value="2">Hoodies</option>
                        <option value="3">Jackets</option>
                        <option value="4">Pants</option>
                        <option value="5">Shoes</option>
                    </select>

                    <select id="priceSort" class="filter-select">
                        <option value="default">Sort by</option>
                        <option value="low">Price: Low to High</option>
                        <option value="high">Price: High to Low</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="products-section">
            <div class="container">
                <div class="products-grid" id="productsGrid">
                    <?php
                    $sql = "SELECT p.*, c.category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.category_id 
                            ORDER BY p.created_at DESC";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $stock = $row['stock_quantity'] ?? 0;
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
                            <div class="product-card" data-category="'.$row['category_id'].'" data-price="'.$row['price'].'">
                                <span class="stock-badge '.$stock_class.'">'.$stock_status.'</span>
                                <img src="'.$row['image_url'].'" alt="'.$row['product_name'].'" onerror="this.src=\'https://via.placeholder.com/300x400?text=No+Image\'">
                                <h3>'.htmlspecialchars($row['product_name']).'</h3>
                                <p class="price">₱'.number_format($row['price'], 2).'</p>
                                <div class="product-actions">
                                    <a href="product_details.php?id='.$row['product_id'].'" class="btn-add-cart" style="flex: 1; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;" '.($stock <= 0 ? 'style="opacity: 0.5; cursor: not-allowed;"' : '').'>
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        '.($stock <= 0 ? 'Out of Stock' : 'Add to cart').'
                                    </a>
                                </div>
                            </div>';
                        }
                    } else {
                        echo '<p>No products found.</p>';
                    }
                    ?>
                </div>
            </div>
        </section>
    </div>

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

        // Product filtering & sorting
        const searchBar = document.getElementById('searchBar');
        const categoryFilter = document.getElementById('categoryFilter');
        const priceSort = document.getElementById('priceSort');
        const productsGrid = document.getElementById('productsGrid');
        const products = document.querySelectorAll('.product-card');

        function filterProducts() {
            const searchText = searchBar.value.toLowerCase();
            const selectedCategory = categoryFilter.value;
            const sortOrder = priceSort.value;

            let productArray = Array.from(products);

            productArray = productArray.filter(product => {
                const title = product.querySelector('h3').textContent.toLowerCase();
                const category = product.getAttribute('data-category');
                const matchesSearch = title.includes(searchText);
                const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
                return matchesSearch && matchesCategory;
            });

            if (sortOrder === 'low') {
                productArray.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
            } else if (sortOrder === 'high') {
                productArray.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
            }

            productsGrid.innerHTML = '';
            productArray.forEach(p => productsGrid.appendChild(p));
        }

        searchBar.addEventListener('input', filterProducts);
        categoryFilter.addEventListener('change', filterProducts);
        priceSort.addEventListener('change', filterProducts);

        filterProducts();
    </script>
</body>
</html>
<?php $conn->close(); ?>