<?php
include 'Database/db.php';
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}

// Handle Add to Cart
if (isset($_POST['add_to_cart'])) {
  $product_id = (int) $_POST['product_id'];
  $quantity = (int) $_POST['quantity'] ?? 1;

  // Get product details
  $stmt = $conn->prepare("SELECT product_id, product_name, price, stock_quantity FROM products WHERE product_id = ?");
  $stmt->bind_param("i", $product_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();

    // Check if product already in cart
    if (isset($_SESSION['cart'][$product_id])) {
      $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
      $_SESSION['cart'][$product_id] = [
        'product_id' => $product['product_id'],
        'product_name' => $product['product_name'],
        'price' => $product['price'],
        'quantity' => $quantity,
        'stock' => $product['stock_quantity']
      ];
    }

    // Limit to stock quantity
    if ($_SESSION['cart'][$product_id]['quantity'] > $product['stock_quantity']) {
      $_SESSION['cart'][$product_id]['quantity'] = $product['stock_quantity'];
    }
  }
  $stmt->close();

  header('Location: cart.php');
  exit();
}

// Handle Update Quantity
if (isset($_POST['update_cart'])) {
  foreach ($_POST['quantity'] as $product_id => $quantity) {
    $product_id = (int) $product_id;
    $quantity = (int) $quantity;

    if ($quantity <= 0) {
      unset($_SESSION['cart'][$product_id]);
    } else {
      if (isset($_SESSION['cart'][$product_id])) {
        $max_quantity = $_SESSION['cart'][$product_id]['stock'];
        $_SESSION['cart'][$product_id]['quantity'] = min($quantity, $max_quantity);
      }
    }
  }

  header('Location: cart.php');
  exit();
}

// Handle Remove Item
if (isset($_GET['remove'])) {
  $product_id = (int) $_GET['remove'];
  unset($_SESSION['cart'][$product_id]);

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

$tax = $subtotal * 0.12; // 10% tax
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
              <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                <div class="cart-item">
                  <div class="item-image">
                    <?php
                    // Get product image
                    $stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
                    $stmt->bind_param("i", $product_id);
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
                    <label for="qty-<?php echo $product_id; ?>">Qty:</label>
                    <input type="number" id="qty-<?php echo $product_id; ?>" name="quantity[<?php echo $product_id; ?>]"
                      value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>"
                      class="quantity-input">
                  </div>

                  <div class="item-subtotal">
                    <p class="subtotal-label">Subtotal</p>
                    <p class="subtotal-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                  </div>

                  <div class="item-remove">
                    <a href="cart.php?remove=<?php echo $product_id; ?>" class="btn-remove"
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
              <button onclick="openModal('loginModal')" class="btn-checkout">Login to Checkout</button>
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


  <script>
    // Modal functions (for login prompt)
    function openModal(modalId) {
      window.location.href = 'index.php#' + modalId;
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