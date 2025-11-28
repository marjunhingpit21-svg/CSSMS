<?php
include 'Database/db.php';
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: cart.php');
    exit();
}

// Handle login (for modal)
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
                
                header('Location: orders.php');
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

// Get cart count for header
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

// Get orders count for header
$orders_count = 0;

// Fetch orders from database for the logged-in user
$orders = [];
$user_id = $_SESSION['user_id'];

try {
    // First, get or create customer record for the user
    $customer_stmt = $conn->prepare("
        SELECT customer_id FROM customers WHERE user_id = ?
    ");
    $customer_stmt->bind_param("i", $user_id);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer = $customer_result->fetch_assoc();
        $customer_id = $customer['customer_id'];
        
        // Count orders for header (excluding cancelled orders)
        $count_stmt = $conn->prepare("
            SELECT COUNT(*) as order_count 
            FROM orders 
            WHERE customer_id = ? AND status NOT IN ('cancelled')
        ");
        $count_stmt->bind_param("i", $customer_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        $orders_count = $count_data['order_count'];
        $count_stmt->close();
        
        // Fetch orders for this customer
        $orders_stmt = $conn->prepare("
            SELECT 
                o.order_id,
                o.order_date,
                o.total_amount,
                o.status,
                o.subtotal,
                o.tax,
                o.discount,
                o.payment_method,
                o.shipping_address,
                COUNT(oi.order_item_id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.customer_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
        ");
        $orders_stmt->bind_param("i", $customer_id);
        $orders_stmt->execute();
        $orders_result = $orders_stmt->get_result();
        
        while ($order = $orders_result->fetch_assoc()) {
            // Get order items
            $items_stmt = $conn->prepare("
                SELECT 
                    oi.order_item_id,
                    oi.quantity,
                    oi.unit_price,
                    oi.subtotal,
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    c.category_name,
                    COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) as size_display,
                    cs.size_name as clothing_size,
                    ss.size_us as shoe_size
                FROM order_items oi
                INNER JOIN inventory i ON oi.inventory_id = i.inventory_id
                INNER JOIN products p ON i.product_id = p.product_id
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN sizes s ON i.size_id = s.size_id
                LEFT JOIN product_sizes ps ON (p.product_id = ps.product_id AND 
                    (ps.clothing_size_id = s.size_id OR ps.shoe_size_id = i.shoe_size_id))
                LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
                LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
                WHERE oi.order_id = ?
            ");
            $items_stmt->bind_param("i", $order['order_id']);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $order_items = [];
            while ($item = $items_result->fetch_assoc()) {
                $order_items[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'image_url' => $item['image_url'] ?: 'https://via.placeholder.com/100',
                    'category_name' => $item['category_name'],
                    'size' => $item['size_display'] ?: ($item['clothing_size'] ?: ($item['shoe_size'] ? $item['shoe_size'] . ' US' : 'One Size')),
                    'price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal']
                ];
            }
            $items_stmt->close();
            
            // Map database status to frontend status
            $status_map = [
                'pending' => 'to_ship',
                'processing' => 'to_ship',
                'shipped' => 'to_receive',
                'delivered' => 'to_rate',
                'cancelled' => 'cancelled'
            ];
            
            $frontend_status = $status_map[$order['status']] ?? 'to_ship';
            
            $orders[] = [
                'order_id' => 'TRW-' . $order['order_id'],
                'db_order_id' => $order['order_id'],
                'date' => $order['order_date'],
                'status' => $frontend_status,
                'db_status' => $order['status'],
                'total' => $order['total_amount'],
                'subtotal' => $order['subtotal'],
                'tax' => $order['tax'],
                'discount' => $order['discount'],
                'payment_method' => $order['payment_method'],
                'shipping_address' => $order['shipping_address'],
                'item_count' => $order['item_count'],
                'items' => $order_items
            ];
        }
        $orders_stmt->close();
    }
    $customer_stmt->close();
    
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
}

// Status display names
$status_display = [
    'to_ship' => 'To Ship',
    'to_receive' => 'To Receive',
    'to_rate' => 'To Rate',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

// Status descriptions
$status_description = [
    'to_ship' => 'Your order is being processed and will be shipped soon',
    'to_receive' => 'Your order has been shipped and is on its way',
    'to_rate' => 'Your order has been delivered. Please rate your items',
    'completed' => 'Your order has been completed',
    'cancelled' => 'Your order has been cancelled'
];

// Count orders by status for filter badges
$status_counts = [
    'all' => count($orders),
    'to_ship' => 0,
    'to_receive' => 0,
    'to_rate' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($orders as $order) {
    $status_counts[$order['status']]++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/orders.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="page-wrapper-orders">
        <div class="orders-container">
            <div class="orders-header">
                <h1>My Orders</h1>
                <p class="orders-subtitle">Track and manage your purchases</p>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <svg width="120" height="120" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h2>No orders yet</h2>
                    <p>Start shopping to see your orders here!</p>
                    <a href="index.php" class="btn-start-shopping">Start Shopping</a>
                </div>
            <?php else: ?>
                <!-- Order Filters -->
                <div class="orders-filters">
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-filter="all">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            All Orders
                            <span class="filter-badge"><?php echo $status_counts['all']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="to_ship">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                            To Ship
                            <span class="filter-badge"><?php echo $status_counts['to_ship']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="to_receive">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            To Receive
                            <span class="filter-badge"><?php echo $status_counts['to_receive']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="to_rate">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            To Rate
                            <span class="filter-badge"><?php echo $status_counts['to_rate']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="completed">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Completed
                            <span class="filter-badge"><?php echo $status_counts['completed']; ?></span>
                        </button>
                    </div>
                    
                    <!-- Continue Shopping Button -->
                    <div class="orders-actions">
                        <a href="index.php" class="btn-continue-shopping">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Continue Shopping
                        </a>
                    </div>
                </div>

                <div class="orders-content">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card" data-status="<?php echo $order['status']; ?>">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3>Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                                    <div class="order-meta">
                                        <div class="order-meta-item">
                                            <span class="meta-label">Order Date</span>
                                            <span class="meta-value"><?php echo date('M j, Y', strtotime($order['date'])); ?></span>
                                        </div>
                                        <div class="order-meta-item">
                                            <span class="meta-label">Items</span>
                                            <span class="meta-value"><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] !== 1 ? 's' : ''; ?></span>
                                        </div>
                                        <div class="order-meta-item">
                                            <span class="meta-label">Total Amount</span>
                                            <span class="meta-value">₱<?php echo number_format($order['total'], 2); ?></span>
                                        </div>
                                        <div class="order-meta-item">
                                            <span class="meta-label">Payment Method</span>
                                            <span class="meta-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo $status_display[$order['status']]; ?>
                                </div>
                            </div>

                            <!-- Status Timeline (without connecting line) -->
                            <div class="status-timeline">
                                <div class="timeline-step <?php echo in_array($order['status'], ['to_ship', 'to_receive', 'to_rate', 'completed']) ? 'completed' : ''; ?> <?php echo $order['status'] === 'to_ship' ? 'active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Order Placed</span>
                                </div>
                                <div class="timeline-step <?php echo in_array($order['status'], ['to_receive', 'to_rate', 'completed']) ? 'completed' : ''; ?> <?php echo $order['status'] === 'to_receive' ? 'active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                            <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1v-1a1 1 0 011-1h2a1 1 0 011 1v1a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H19a1 1 0 001-1V5a1 1 0 00-1-1H3z"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Shipped</span>
                                </div>
                                <div class="timeline-step <?php echo in_array($order['status'], ['to_rate', 'completed']) ? 'completed' : ''; ?> <?php echo $order['status'] === 'to_rate' ? 'active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Delivered</span>
                                </div>
                                <div class="timeline-step <?php echo $order['status'] === 'completed' ? 'completed active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Completed</span>
                                </div>
                            </div>

                            <div class="order-items">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 onerror="this.src='https://via.placeholder.com/100'">
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p class="item-variants">
                                                <?php if (isset($item['size']) && $item['size'] !== 'One Size'): ?>
                                                    Size: <?php echo htmlspecialchars($item['size']); ?> 
                                                <?php endif; ?>
                                                <?php if (isset($item['category_name'])): ?>
                                                    • Category: <?php echo htmlspecialchars($item['category_name']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                        </div>
                                        <div class="item-quantity">
                                            Qty: <?php echo $item['quantity']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="order-actions">
                                <div class="order-total">
                                    Total: <span>₱<?php echo number_format($order['total'], 2); ?></span>
                                </div>
                                <div class="action-buttons">
                                    <?php if ($order['status'] === 'to_ship'): ?>
                                        <button class="btn-track" onclick="trackOrder(<?php echo $order['db_order_id']; ?>)">Track Order</button>
                                        <button class="btn-cancel" onclick="cancelOrder(<?php echo $order['db_order_id']; ?>)">Cancel Order</button>
                                    <?php elseif ($order['status'] === 'to_receive'): ?>
                                        <button class="btn-track" onclick="trackOrder(<?php echo $order['db_order_id']; ?>)">Track Package</button>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">View Details</button>
                                    <?php elseif ($order['status'] === 'to_rate'): ?>
                                        <button class="btn-rate" onclick="rateProducts(<?php echo $order['db_order_id']; ?>)">Rate Products</button>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">Order Details</button>
                                    <?php elseif ($order['status'] === 'cancelled'): ?>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">View Details</button>
                                        <button class="btn-rate" onclick="reorder(<?php echo $order['db_order_id']; ?>)">Reorder</button>
                                    <?php else: ?>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">View Details</button>
                                        <button class="btn-rate" onclick="reorder(<?php echo $order['db_order_id']; ?>)">Buy Again</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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
            
            <form method="POST" action="orders.php" id="loginForm">
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

        // Update cart count and orders count in header
        document.addEventListener('DOMContentLoaded', function () {
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) {
                const count = <?php echo $cart_count; ?>;
                cartBadge.textContent = count;
                cartBadge.style.display = count > 0 ? 'flex' : 'none';
            }

            // Update orders count in header
            const ordersBadge = document.getElementById('orders-count');
            if (ordersBadge) {
                const ordersCount = <?php echo $orders_count; ?>;
                ordersBadge.textContent = ordersCount;
                ordersBadge.style.display = ordersCount > 0 ? 'flex' : 'none';
            }

            // Initialize filter functionality
            initializeFilters();
            initializeStickyFilter();
        });

        // Order action functions
        function trackOrder(orderId) {
            alert('Tracking order #' + orderId + '\nThis feature will be implemented soon!');
        }

        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel order #' + orderId + '?')) {
                alert('Order cancellation request sent for order #' + orderId);
                // In a real application, you would make an AJAX call here
            }
        }

        function viewOrderDetails(orderId) {
            alert('Viewing details for order #' + orderId + '\nThis feature will be implemented soon!');
        }

        function rateProducts(orderId) {
            alert('Rating products for order #' + orderId + '\nThis feature will be implemented soon!');
        }

        function reorder(orderId) {
            alert('Reordering items from order #' + orderId + '\nThis feature will be implemented soon!');
        }

        // Filter functionality
        function initializeFilters() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const orderCards = document.querySelectorAll('.order-card');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');

                    // Show/hide orders based on filter
                    orderCards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-status') === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        }

        // Add interactivity to order cards
        document.querySelectorAll('.order-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Prevent navigation if clicking on buttons
                if (!e.target.closest('.action-buttons')) {
                    // You can add functionality to expand order details here
                    console.log('Order card clicked');
                }
            });
        });

        // Sticky filter functionality
        function initializeStickyFilter() {
            const filters = document.querySelector('.orders-filters');
            if (!filters) return;

            const observer = new IntersectionObserver(
                ([e]) => {
                    if (e.intersectionRatio < 1) {
                        filters.classList.add('sticky');
                    } else {
                        filters.classList.remove('sticky');
                    }
                },
                { threshold: [1], rootMargin: '-90px 0px 0px 0px' }
            );

            observer.observe(filters);
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>