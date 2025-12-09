<?php
// order_details.php
session_start();
require_once 'Database/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id === 0) {
    header('Location: profile.php#orders');
    exit();
}

// Get customer_id
$customer_query = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$customer_query->bind_param("i", $_SESSION['user_id']);
$customer_query->execute();
$customer_result = $customer_query->get_result();

if ($customer_result->num_rows === 0) {
    header('Location: profile.php#orders');
    exit();
}

$customer_id = $customer_result->fetch_assoc()['customer_id'];

// Get order details
$order_query = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
$order_query->bind_param("ii", $order_id, $customer_id);
$order_query->execute();
$order_result = $order_query->get_result();

if ($order_result->num_rows === 0) {
    $_SESSION['error'] = 'Order not found.';
    header('Location: profile.php#orders');
    exit();
}

$order = $order_result->fetch_assoc();

// Get order items
$items_query = $conn->prepare("
    SELECT oi.*, p.product_name, p.image_url 
    FROM order_items oi
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    JOIN products p ON i.product_id = p.product_id
    WHERE oi.order_id = ?
");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items = $items_query->get_result();

// Get cart count for header
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

// Get orders count for header
$orders_count_query = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?");
$orders_count_query->bind_param("i", $customer_id);
$orders_count_query->execute();
$orders_count = $orders_count_query->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/order_details.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="order-details-wrapper">
        <div class="order-details-container">
            <div class="breadcrumb">
                <a href="profile.php#orders">← Back to Orders</a>
            </div>

            <div class="order-details-header">
                <div>
                    <h1>Order #<?php echo $order['order_id']; ?></h1>
                    <p>Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['order_date'])); ?></p>
                </div>
                <span class="order-status status-<?php echo $order['status']; ?>">
                    <?php echo ucfirst($order['status']); ?>
                </span>
            </div>

            <div class="order-details-content">
                <!-- Order Items -->
                <div class="order-section">
                    <h2>Order Items</h2>
                    <div class="order-items-list">
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <div class="order-item">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                    <p>Quantity: <?php echo $item['quantity']; ?></p>
                                    <p class="item-price">₱<?php echo number_format($item['unit_price'], 2); ?> each</p>
                                </div>
                                <div class="item-total">
                                    ₱<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="order-sidebar">
                    <div class="summary-card">
                        <h3>Order Summary</h3>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>₱<?php echo number_format($order['subtotal'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Tax</span>
                            <span>₱<?php echo number_format($order['tax'], 2); ?></span>
                        </div>
                        <?php if ($order['discount'] > 0): ?>
                            <div class="summary-row discount">
                                <span>Discount</span>
                                <span>-₱<?php echo number_format($order['discount'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="summary-divider"></div>
                        <div class="summary-row summary-total">
                            <span>Total</span>
                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="summary-card">
                        <h3>Shipping Address</h3>
                        <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                    </div>

                    <!-- Payment Method -->
                    <div class="summary-card">
                        <h3>Payment Method</h3>
                        <p>
                            <strong><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></strong>
                        </p>
                    </div>

                    <!-- Actions -->
                    <?php if ($order['status'] === 'delivered'): ?>
                        <button class="btn-primary btn-full">Leave a Review</button>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                        <button class="btn-secondary btn-full" onclick="if(confirm('Are you sure you want to cancel this order?')) window.location.href='cancel_order.php?id=<?php echo $order_id; ?>'">
                            Cancel Order
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>