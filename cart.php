  <?php
  include 'Database/db.php';
  session_start();

  // Initialize cart if not exists
  if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
  }

  // Get cart items with product details
  $cart_items = [];
  $total = 0;

  if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $types = str_repeat('i', count($product_ids));
    $stmt->bind_param($types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($product = $result->fetch_assoc()) {
      $product_id = $product['product_id'];
      $quantity = $_SESSION['cart'][$product_id];
      $subtotal = $product['price'] * $quantity;
      $total += $subtotal;

      $cart_items[] = [
        'product' => $product,
        'quantity' => $quantity,
        'subtotal' => $subtotal
      ];
    }
    $stmt->close();
  }
  ?>

  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/Cart.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  </head>

  <body>
    <?php include 'Header.php'; ?>

    <div class="page-wrapper">
      <div class="cart-container">
        <div class="container">
          <h1 class="cart-title">Shopping Cart</h1>

          <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
              <svg width="120" height="120" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
              <h2>Your cart is empty</h2>
              <p>Add some products to get started!</p>
              <a href="index.php" class="btn-continue">Continue Shopping</a>
            </div>
          <?php else: ?>
            <div class="cart-content">
              <div class="cart-items">
                <?php foreach ($cart_items as $item): ?>
                  <div class="cart-item" data-product-id="<?php echo $item['product']['product_id']; ?>">
                    <img src="<?php echo htmlspecialchars($item['product']['image_url']); ?>"
                      alt="<?php echo htmlspecialchars($item['product']['product_name']); ?>"
                      onerror="this.src='https://via.placeholder.com/100x130?text=No+Image'">

                    <div class="item-details">
                      <h3><?php echo htmlspecialchars($item['product']['product_name']); ?></h3>
                      <p class="item-price">$<?php echo number_format($item['product']['price'], 2); ?></p>
                    </div>

                    <div class="item-quantity">
                      <button class="qty-btn"
                        onclick="updateQuantity(<?php echo $item['product']['product_id']; ?>, -1)">-</button>
                      <input type="number" value="<?php echo $item['quantity']; ?>" min="1" max="99" class="qty-input"
                        onchange="updateQuantityDirect(<?php echo $item['product']['product_id']; ?>, this.value)">
                      <button class="qty-btn"
                        onclick="updateQuantity(<?php echo $item['product']['product_id']; ?>, 1)">+</button>
                    </div>

                    <div class="item-subtotal">
                      <p>$<?php echo number_format($item['subtotal'], 2); ?></p>
                    </div>

                    <button class="btn-remove" onclick="removeFromCart(<?php echo $item['product']['product_id']; ?>)">
                      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="cart-summary">
                <h2>Order Summary</h2>

                <div class="summary-row">
                  <span>Subtotal</span>
                  <span id="subtotal">$<?php echo number_format($total, 2); ?></span>
                </div>

                <div class="summary-row">
                  <span>Shipping</span>
                  <span>Calculated at checkout</span>
                </div>

                <div class="summary-row">
                  <span>Tax</span>
                  <span id="tax">$<?php echo number_format($total * 0.1, 2); ?></span>
                </div>

                <hr>

                <div class="summary-row total">
                  <span>Total</span>
                  <span id="total">$<?php echo number_format($total * 1.1, 2); ?></span>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                  <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
                <?php else: ?>
                  <a href="login.php" class="btn-checkout">Login to Checkout</a>
                <?php endif; ?>

                <a href="index.php" class="btn-continue-shopping">Continue Shopping</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <script src="js/cart.js"></script>
    </div>

  </body>

  </html>
  <?php $conn->close(); ?>