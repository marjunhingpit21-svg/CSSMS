<?php
include 'Database/db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get order ID
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch order details with customer and shipping address info
$order_stmt = $conn->prepare("
    SELECT 
        o.*,
        c.first_name as customer_first_name,
        c.last_name as customer_last_name,
        u.email,
        sa.first_name as ship_first_name,
        sa.last_name as ship_last_name,
        sa.phone as ship_phone,
        sa.address_line1,
        sa.address_line2,
        sa.city,
        sa.province,
        sa.postal_code,
        sa.address_label
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN shipping_addresses sa ON o.address_id = sa.address_id
    WHERE o.order_id = ? AND c.user_id = ?
");
$order_stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$order = $order_result->fetch_assoc();

// Fetch order items
$items_stmt = $conn->prepare("
    SELECT oi.*, p.product_name, p.image_url
    FROM order_items oi
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    JOIN products p ON i.product_id = p.product_id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Get cart count for header
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;

// Get orders count for header
  $orders_count = 0;
  if (isset($_SESSION['user_id'])) {
      include 'orders_count.php';
      $orders_count = getOrdersCount($conn, $_SESSION['user_id']);
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/order_confirmation.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="confirmation-wrapper">
        <div class="confirmation-container">
            <!-- Success Message -->
            <div class="success-banner">
                <div class="success-icon">
                    <svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1>Order Confirmed!</h1>
                <p>Thank you for your purchase, <?php echo htmlspecialchars($order['customer_first_name']); ?>!</p>
                <p class="order-number">Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
            </div>

            <!-- Order Summary -->
            <div class="confirmation-content">
                <div class="confirmation-main">
                    <!-- Order Details -->
                    <div class="info-section">
                        <h2>
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Order Details
                        </h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Order Number:</span>
                                <span class="info-value">#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Order Date:</span>
                                <span class="info-value"><?php echo date('F d, Y', strtotime($order['order_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Payment Method:</span>
                                <span class="info-value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Information -->
                    <div class="info-section">
                        <h2>
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Shipping Information
                            <?php if (!empty($order['address_label'])): ?>
                                <span style="font-size: 0.9rem; font-weight: 500; color: #666; margin-left: 10px;">
                                    (<?php echo htmlspecialchars($order['address_label']); ?>)
                                </span>
                            <?php endif; ?>
                        </h2>
                        <div class="shipping-details">
                            <p><strong><?php echo htmlspecialchars($order['ship_first_name'] . ' ' . $order['ship_last_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($order['address_line1']); ?></p>
                            <?php if (!empty($order['address_line2'])): ?>
                                <p><?php echo htmlspecialchars($order['address_line2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($order['city'] . ', ' . $order['province'] . ' ' . $order['postal_code']); ?></p>
                            <p style="margin-top: 10px;"><strong>Phone:</strong> <?php echo htmlspecialchars($order['ship_phone']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="info-section">
                        <h2>
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            Order Items
                        </h2>
                        <div class="order-items">
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                                <div class="order-item">
                                    <div class="item-image">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                             onerror="this.src='https://via.placeholder.com/80'">
                                    </div>
                                    <div class="item-details">
                                        <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                        <p>Quantity: <?php echo $item['quantity']; ?></p>
                                        <p class="item-price">₱<?php echo number_format($item['unit_price'], 2); ?> each</p>
                                    </div>
                                    <div class="item-total">
                                        <span class="total-price">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Total Sidebar -->
                <div class="confirmation-sidebar">
                    <div class="total-summary">
                        <h3>Order Summary</h3>
                        
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>₱<?php echo number_format($order['subtotal'], 2); ?></span>
                        </div>
                        
                        <?php if ($order['discount'] > 0): ?>
                        <div class="summary-row discount">
                            <span>Discount:</span>
                            <span>-₱<?php echo number_format($order['discount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Tax (12%):</span>
                            <span>₱<?php echo number_format($order['tax'], 2); ?></span>
                        </div>
                        
                        <div class="summary-divider"></div>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>

                        <div class="action-buttons">
                            <a href="index.php" class="btn-continue">Continue Shopping</a>
                            <a href="orders.php" class="btn-view-orders">View My Orders</a>
                        </div>

                        <div class="help-section">
                            <h4>Need Help?</h4>
                            <p>Contact our customer service for any questions about your order.</p>
                            <a href="mailto:riminology@altiere.com" class="contact-link">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                riminology@altiere.com
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update cart count in header
        document.addEventListener('DOMContentLoaded', function() {
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) {
                const count = <?php echo $cart_count; ?>;
                cartBadge.textContent = count;
                cartBadge.style.display = count > 0 ? 'flex' : 'none';
            }
        });

        // Print order
        function printOrder() {
            window.print();
        }
    </script>
</body>
</html>
<?php 
$order_stmt->close();
$items_stmt->close();
$conn->close(); 
?>