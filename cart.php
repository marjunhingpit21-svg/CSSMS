<?php
include 'Database/db.php';
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}

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
                
                header('Location: cart.php');
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

// Handle Update Quantity
if (isset($_POST['update_cart'])) {
  foreach ($_POST['quantity'] as $cart_key => $quantity) {
    $quantity = (int) $quantity;

    if ($quantity <= 0) {
      unset($_SESSION['cart'][$cart_key]);
    } else {
      if (isset($_SESSION['cart'][$cart_key])) {
        $max_quantity = $_SESSION['cart'][$cart_key]['stock'];
        $_SESSION['cart'][$cart_key]['quantity'] = min($quantity, $max_quantity);
      }
    }
  }

  header('Location: cart.php');
  exit();
}

// Handle Remove Item
if (isset($_GET['remove'])) {
  $cart_key = $_GET['remove'];
  unset($_SESSION['cart'][$cart_key]);

  header('Location: cart.php');
  exit();
}

// Handle Clear Cart
if (isset($_GET['clear'])) {
  $_SESSION['cart'] = [];
  header('Location: cart.php');
  exit();
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['cart'] as $item) {
  $subtotal += $item['price'] * $item['quantity'];
  $total_items += $item['quantity'];
}

$shipping = $subtotal > 0 ? 5.99 : 0; // Free shipping over $50
if ($subtotal >= 50) {
  $shipping = 0;
}

$tax = $subtotal * 0.12; // 12% tax
$total = $subtotal + $shipping + $tax;

// Get cart count for header
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shopping Cart - TrendyWear</title>
  <link rel="stylesheet" href="css/Header.css">
  <link rel="stylesheet" href="css/cart.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
  <?php include 'header.php'; ?>

  <div class="page-wrapper">
    <div class="cart-container">
      <div class="cart-header">
        <h1>Shopping Cart</h1>
        <p class="cart-items-count"><?php echo $total_items; ?> <?php echo $total_items === 1 ? 'item' : 'items'; ?></p>
      </div>

      <?php if (empty($_SESSION['cart'])): ?>
        <div class="empty-cart">
          <svg width="120" height="120" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
          <h2>Your cart is empty</h2>
          <p>Add some products to get started!</p>
          <a href="index.php" class="btn-continue-shopping">Continue Shopping</a>
        </div>
      <?php else: ?>
        <div class="cart-content">
          <form method="POST" action="cart.php" class="cart-form">
            <div class="cart-items">
              <?php foreach ($_SESSION['cart'] as $cart_key => $item): ?>
                <div class="cart-item">
                  <div class="item-image">
                    <?php
                    // Get product image
                    $stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
                    $stmt->bind_param("i", $item['product_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $product = $result->fetch_assoc();
                    $image_url = $product['image_url'] ?? 'https://via.placeholder.com/100';
                    $stmt->close();
                    ?>
                    <img src="<?php echo htmlspecialchars($image_url); ?>"
                      alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                      onerror="this.src='https://via.placeholder.com/100'">
                  </div>

                  <div class="item-details">
                    <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                    <?php if (isset($item['size'])): ?>
                      <p class="item-size">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                          <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 11V7h2v4H8zm4-4h-2v4h2V7z"/>
                        </svg>
                        Size: <strong><?php echo htmlspecialchars($item['size']); ?></strong>
                      </p>
                    <?php endif; ?>
                    <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                    <p class="item-stock">
                      <?php if ($item['stock'] > 0): ?>
                        <span class="in-stock">In Stock (<?php echo $item['stock']; ?> available)</span>
                      <?php else: ?>
                        <span class="out-of-stock">Out of Stock</span>
                      <?php endif; ?>
                    </p>
                  </div>

                  <div class="item-quantity">
                    <label for="qty-<?php echo $cart_key; ?>">Qty:</label>
                    <input type="number" id="qty-<?php echo $cart_key; ?>" name="quantity[<?php echo $cart_key; ?>]"
                      value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>"
                      class="quantity-input">
                  </div>

                  <div class="item-subtotal">
                    <p class="subtotal-label">Subtotal</p>
                    <p class="subtotal-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                  </div>

                  <div class="item-remove">
                    <a href="cart.php?remove=<?php echo urlencode($cart_key); ?>" class="btn-remove"
                      onclick="return confirm('Remove this item from cart?')">
                      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="cart-actions">
              <a href="index.php" class="btn-continue">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Continue Shopping
              </a>
              <div class="action-buttons">
                <button type="submit" name="update_cart" class="btn-update">Update Cart</button>
                <a href="cart.php?clear=1" class="btn-clear" onclick="return confirm('Clear entire cart?')">Clear Cart</a>
              </div>
            </div>
          </form>

          <div class="cart-summary">
            <h2>Order Summary</h2>

            <div class="summary-row">
              <span>Subtotal (<?php echo $total_items; ?> items)</span>
              <span>₱<?php echo number_format($subtotal, 2); ?></span>
            </div>

            <div class="summary-row">
              <span>Shipping</span>
              <span><?php echo $shipping > 0 ? '₱' . number_format($shipping, 2) : 'FREE'; ?></span>
            </div>

            <?php if ($subtotal < 50 && $subtotal > 0): ?>
              <div class="shipping-notice">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd" />
                </svg>
                Add ₱<?php echo number_format(50 - $subtotal, 2); ?> more for FREE shipping!
              </div>
            <?php endif; ?>

            <div class="summary-row">
              <span>Tax (12%)</span>
              <span>₱<?php echo number_format($tax, 2); ?></span>
            </div>

            <div class="summary-divider"></div>

            <div class="summary-row summary-total">
              <span>Total</span>
              <span>₱<?php echo number_format($total, 2); ?></span>
            </div>

            <div class="promo-code">
              <input type="text" placeholder="Enter promo code" class="promo-input">
              <button type="button" class="btn-apply">Apply</button>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
              <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
            <?php else: ?>
              <button onclick="openModal('loginModal')" class="btn-checkout" type="button">Login to Checkout</button>
              <p class="checkout-notice">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd"
                    d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                    clip-rule="evenodd" />
                </svg>
                Secure checkout with SSL encryption
              </p>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>


  <!-- Login Modal -->
  <div id="loginModal" class="modal <?php echo $login_error ? 'active' : ''; ?>">
    <div class="modal-content">
      <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
      <h2>Welcome Back</h2>
      <p class="modal-subtitle">Login to your TrendyWear account to checkout</p>
      
      <?php if ($login_error): ?>
        <div class="alert alert-error">
          <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <?php echo htmlspecialchars($login_error); ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" action="cart.php" id="loginForm">
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
      
      <form method="POST" action="cart.php" id="signupForm">
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

    // Update cart count in header
    document.addEventListener('DOMContentLoaded', function () {
      const cartBadge = document.getElementById('cart-count');
      if (cartBadge) {
        const count = <?php echo $cart_count; ?>;
        cartBadge.textContent = count;
        cartBadge.style.display = count > 0 ? 'flex' : 'none';
      }
    });

    // Quantity input validation
    document.querySelectorAll('.quantity-input').forEach(input => {
      input.addEventListener('change', function () {
        const min = parseInt(this.min);
        const max = parseInt(this.max);
        let value = parseInt(this.value);

        if (value < min) this.value = min;
        if (value > max) {
          this.value = max;
          alert('Maximum available quantity: ' + max);
        }
      });
    });
  </script>
</body>

</html>
<?php $conn->close(); ?>