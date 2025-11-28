<?php

include 'Database/db.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: cart.php');
    exit();
}

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

// Check database connection
if (!$conn) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get user email from database
$user_email = '';
$user_stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $user_email = $user_data['email'];
}

// Calculate totals from cart
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}


$shipping = $subtotal >= 50 ? 0 : 5.99;
$tax = $subtotal * 0.12;
$total = $subtotal + $shipping + $tax;

// Get cart count for header
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Handle form submission
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Get selected address ID first
    $selected_address_id = isset($_POST['use_address_id']) ? intval($_POST['use_address_id']) : 0;
    
    $payment_method = $_POST['payment_method'] ?? 'cash';
    
    // Initialize variables
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    
    // ONLY validate if NOT using saved address
    if ($selected_address_id == 0) {
        // Validate required fields only for new address
        if (empty($first_name) || empty($last_name) || empty($phone) || 
            empty($address_line1) || empty($city) || empty($province) || empty($postal_code)) {
            $error = 'Please fill in all required fields.';
        }
    }
    
    // Only proceed if no validation errors
    if (empty($error)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Check/Create customer record
            $customer_id = null;
            $check_customer = $conn->prepare("SELECT customer_id, first_name, last_name, phone FROM customers WHERE user_id = ?");
            $check_customer->bind_param("i", $_SESSION['user_id']);
            $check_customer->execute();
            $result = $check_customer->get_result();
            
            if ($result->num_rows > 0) {
                $customer_data = $result->fetch_assoc();
                $customer_id = $customer_data['customer_id'];
                
                // Only update if we have new address data
                if ($selected_address_id == 0 && !empty($first_name)) {
                    $update_customer = $conn->prepare("UPDATE customers SET first_name=?, last_name=?, phone=? WHERE customer_id=?");
                    $update_customer->bind_param("sssi", $first_name, $last_name, $phone, $customer_id);
                    $update_customer->execute();
                } else {
                    // Use existing customer data for address lookup
                    $first_name = $customer_data['first_name'];
                    $last_name = $customer_data['last_name'];
                    $phone = $customer_data['phone'];
                }
            } else {
                // Create new customer - need data from form
                if (empty($first_name) || empty($last_name) || empty($phone)) {
                    throw new Exception("Customer information is required for first order.");
                }
                
                // FIX: Changed from 5 placeholders to 4
                $insert_customer = $conn->prepare("INSERT INTO customers (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)");
                $insert_customer->bind_param("isss", $_SESSION['user_id'], $first_name, $last_name, $phone);
                $insert_customer->execute();
                $customer_id = $conn->insert_id;
            }
            
            // === ADDRESS HANDLING ===
            $shipping_address = '';

            if ($selected_address_id > 0) {
                // Use saved address - NO insertion needed
                $addr_stmt = $conn->prepare("SELECT * FROM shipping_addresses WHERE address_id = ? AND customer_id = ?");
                $addr_stmt->bind_param("ii", $selected_address_id, $customer_id);
                $addr_stmt->execute();
                $addr_result = $addr_stmt->get_result();
                
                if ($addr_result->num_rows > 0) {
                    $addr = $addr_result->fetch_assoc();
                    $shipping_address = "{$addr['address_line1']}" . ($addr['address_line2'] ? ", {$addr['address_line2']}" : "") . 
                                      ", {$addr['city']}, {$addr['province']} {$addr['postal_code']}";
                } else {
                    throw new Exception("Selected address not found.");
                }
            } else {
                // ONLY insert new address if NOT using saved address
                if (empty($address_line1)) {
                    throw new Exception("Please provide a shipping address.");
                }
                
                $address_label = trim($_POST['address_label'] ?? 'Home');
                $set_default = isset($_POST['set_default']) ? 1 : 0;

                // If set as default, unset others
                if ($set_default) {
                    $update_default = $conn->prepare("UPDATE shipping_addresses SET is_default = 0 WHERE customer_id = ?");
                    $update_default->bind_param("i", $customer_id);
                    $update_default->execute();
                }

                // Insert new address
                $ins = $conn->prepare("INSERT INTO shipping_addresses 
                    (customer_id, address_label, first_name, last_name, phone, address_line1, address_line2, city, province, postal_code, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("isssssssssi", $customer_id, $address_label, $first_name, $last_name, $phone,
                                $address_line1, $address_line2, $city, $province, $postal_code, $set_default);
                $ins->execute();

                $shipping_address = "$address_line1" . ($address_line2 ? ", $address_line2" : "") . 
                                  ", $city, $province $postal_code";
            }
                        
            // Create order
            $discount = 0.00;
            $insert_order = $conn->prepare("INSERT INTO orders (customer_id, subtotal, tax, discount, total_amount, payment_method, status, shipping_address) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
            $insert_order->bind_param("idddsss", $customer_id, $subtotal, $tax, $discount, $total, $payment_method, $shipping_address);
            $insert_order->execute();
            $order_id = $conn->insert_id;
            
            // Insert order items (rest of your code remains the same)
            foreach ($_SESSION['cart'] as $item) {
                $cost_stmt = $conn->prepare("SELECT cost_price FROM products WHERE product_id = ?");
                $cost_stmt->bind_param("i", $item['product_id']);
                $cost_stmt->execute();
                $cost_result = $cost_stmt->get_result();
                $cost_row = $cost_result->fetch_assoc();
                $cost_price = $cost_row ? $cost_row['cost_price'] : 0;
                
                $size_stmt = $conn->prepare("
                    SELECT ps.product_size_id, ps.stock_quantity 
                    FROM product_sizes ps
                    LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
                    LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
                    WHERE ps.product_id = ? 
                    AND (cs.size_name = ? OR ss.size_us = ? OR ss.size_eu = ?)
                    LIMIT 1
                ");
                $size_value = $item['size'];
                $size_stmt->bind_param("isss", $item['product_id'], $size_value, $size_value, $size_value);
                $size_stmt->execute();
                $size_result = $size_stmt->get_result();
                
                if ($size_result->num_rows > 0) {
                    $size_data = $size_result->fetch_assoc();
                    $product_size_id = $size_data['product_size_id'];
                    $available_stock = $size_data['stock_quantity'];
                    
                    if ($available_stock < $item['quantity']) {
                        throw new Exception("Insufficient stock for {$item['product_name']} (Size: {$item['size']})");
                    }
                    
                    $update_stock = $conn->prepare("UPDATE product_sizes SET stock_quantity = stock_quantity - ? WHERE product_size_id = ?");
                    $update_stock->bind_param("ii", $item['quantity'], $product_size_id);
                    $update_stock->execute();
                    
                    $inv_check = $conn->prepare("SELECT inventory_id FROM inventory WHERE product_id = ? LIMIT 1");
                    $inv_check->bind_param("i", $item['product_id']);
                    $inv_check->execute();
                    $inv_result = $inv_check->get_result();
                    
                    if ($inv_result->num_rows > 0) {
                        $inventory_id = $inv_result->fetch_assoc()['inventory_id'];
                    } else {
                        $create_inv = $conn->prepare("INSERT INTO inventory (product_id, quantity) VALUES (?, 0)");
                        $create_inv->bind_param("i", $item['product_id']);
                        $create_inv->execute();
                        $inventory_id = $conn->insert_id;
                    }
                    
                    $item_subtotal = $item['price'] * $item['quantity'];
                    $insert_item = $conn->prepare("INSERT INTO order_items (order_id, inventory_id, quantity, unit_price, unit_cost, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert_item->bind_param("iiiddd", $order_id, $inventory_id, $item['quantity'], $item['price'], $cost_price, $item_subtotal);
                    $insert_item->execute();
                } else {
                    throw new Exception("Product size not found for {$item['product_name']} (Size: {$item['size']})");
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Set success and redirect
            $success = true;
            $_SESSION['order_success'] = $order_id;
            header('Location: order_confirmation.php?order_id=' . $order_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Order failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/checkout.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="checkout-wrapper">
        <div class="checkout-container">
            <div class="checkout-header">
                <h1>Checkout</h1>
                <div class="checkout-steps">
                    <div class="step active">
                        <span class="step-number">1</span>
                        <span class="step-label">Shipping</span>
                    </div>
                    <div class="step-divider"></div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span class="step-label">Payment</span>
                    </div>
                    <div class="step-divider"></div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span class="step-label">Confirmation</span>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="checkout.php" class="checkout-form">
                <div class="checkout-content">
                    <div class="checkout-main">
                        <!-- Shipping Information -->
                        <div class="checkout-section">
                            <h2>
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Shipping Address
                            </h2>

                            <?php
                            // Get customer_id
                            $customer_id = null;
                            $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
                            $stmt->bind_param("i", $_SESSION['user_id']);
                            $stmt->execute();
                            $res = $stmt->get_result();

                            if ($res->num_rows > 0) {
                                $customer_id = $res->fetch_assoc()['customer_id'];
                            }

                            // Fetch addresses
                            $addresses = [];
                            if ($customer_id) {
                                $addr_stmt = $conn->prepare("SELECT * FROM shipping_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC");
                                $addr_stmt->bind_param("i", $customer_id);
                                $addr_stmt->execute();
                                $res = $addr_stmt->get_result();
                                while ($row = $res->fetch_assoc()) {
                                    $addresses[] = $row;
                                }
                            }
                            ?>

                            <?php if (!empty($addresses)): ?>
                                <div class="saved-addresses">
                                    <p>Select a saved address or <a href="#" id="add-new-address">add a new one</a></p>
                                    <?php foreach ($addresses as $addr): ?>
                                        <label class="address-option">
                                            <input type="radio" name="use_address_id" value="<?php echo $addr['address_id']; ?>" 
                                                <?php echo $addr['is_default'] ? 'checked' : ''; ?>>
                                            <div class="address-card <?php echo $addr['is_default'] ? 'default' : ''; ?>">
                                                <strong><?php echo htmlspecialchars($addr['address_label']); ?>
                                                    <?php echo $addr['is_default'] ? ' <small>(Default)</small>' : ''; ?>
                                                </strong>
                                                <p><?php echo htmlspecialchars("{$addr['first_name']} {$addr['last_name']}"); ?><br>
                                                  <?php echo htmlspecialchars($addr['phone']); ?><br>
                                                  <?php echo htmlspecialchars("{$addr['address_line1']}" . ($addr['address_line2'] ? ', '.$addr['address_line2'] : '')); ?><br>
                                                  <?php echo htmlspecialchars("{$addr['city']}, {$addr['province']} {$addr['postal_code']}"); ?>
                                                </p>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- New / Edit Address Form -->
                            <div id="address-form-container" style="<?php echo !empty($addresses) ? 'display:none;' : ''; ?>">
                                <p><?php echo empty($addresses) ? 'Enter your shipping address' : 'Add new address'; ?></p>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">First Name *</label>
                                        <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">Last Name *</label>
                                        <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                                    <small style="color: #666;">Email is taken from your account and cannot be changed here</small>
                                </div>

                                <div class="form-group">
                                    <label for="phone">Phone Number *</label>
                                    <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="address_line1">Address Line 1 *</label>
                                    <input type="text" id="address_line1" name="address_line1" required value="<?php echo htmlspecialchars($_POST['address_line1'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="address_line2">Address Line 2 (Optional)</label>
                                    <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($_POST['address_line2'] ?? ''); ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="city">City *</label>
                                        <input type="text" id="city" name="city" required value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="province">Province *</label>
                                        <input type="text" id="province" name="province" required value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="postal_code">Postal Code *</label>
                                    <input type="text" id="postal_code" name="postal_code" required value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="address_label">Label (e.g. Home, Office)</label>
                                        <input type="text" id="address_label" name="address_label" value="<?php echo htmlspecialchars($_POST['address_label'] ?? 'Home'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="set_default" <?php echo empty($addresses) ? 'checked' : ''; ?>> Set as default address
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="checkout-section">
                            <h2>
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                Payment Method
                            </h2>

                            <div class="payment-methods">
                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="cash" checked>
                                    <div class="payment-card">
                                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <div>
                                            <strong>Cash on Delivery</strong>
                                            <p>Pay when you receive your order</p>
                                        </div>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="card">
                                    <div class="payment-card">
                                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        </svg>
                                        <div>
                                            <strong>Credit/Debit Card</strong>
                                            <p>Pay securely with your card</p>
                                        </div>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="bank_transfer">
                                    <div class="payment-card">
                                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
                                        </svg>
                                        <div>
                                            <strong>Bank Transfer</strong>
                                            <p>Direct bank transfer</p>
                                        </div>
                                    </div>
                                </label>

                                <label class="payment-option">
                                    <input type="radio" name="payment_method" value="online">
                                    <div class="payment-card">
                                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <div>
                                            <strong>Online Payment</strong>
                                            <p>GCash, PayMaya, etc.</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary Sidebar -->
                    <div class="checkout-sidebar">
                        <div class="order-summary">
                            <h3>Order Summary</h3>

                            <div class="summary-items">
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <div class="summary-item">
                                        <div class="item-info">
                                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p>Size: <?php echo htmlspecialchars($item['size']); ?> × <?php echo $item['quantity']; ?></p>
                                        </div>
                                        <div class="item-price">
                                            ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="summary-divider"></div>

                            <div class="summary-totals">
                                <div class="summary-row">
                                    <span>Subtotal (<?php echo $total_items; ?> items)</span>
                                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Shipping</span>
                                    <span><?php echo $shipping > 0 ? '₱' . number_format($shipping, 2) : 'FREE'; ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax (12%)</span>
                                    <span>₱<?php echo number_format($tax, 2); ?></span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="summary-row summary-total">
                                    <span>Total</span>
                                    <span>₱<?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>

                            <button type="submit" name="place_order" class="btn-place-order">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Place Order
                            </button>

                            <div class="secure-notice">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                                Secure checkout with SSL encryption
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
      // Replace the entire script section in your checkout.php with this:

document.addEventListener('DOMContentLoaded', function() {
    const addNewAddressLink = document.getElementById('add-new-address');
    const addressFormContainer = document.getElementById('address-form-container');
    const savedAddresses = document.querySelector('.saved-addresses');
    
    // Function to remove required attributes from address form
    function disableAddressFormValidation() {
        if (addressFormContainer) {
            addressFormContainer.querySelectorAll('input').forEach(field => {
                field.removeAttribute('required');
            });
        }
    }
    
    // Function to enable required attributes for address form
    function enableAddressFormValidation() {
        const fieldsToRequire = ['first_name', 'last_name', 'phone', 'address_line1', 'city', 'province', 'postal_code'];
        fieldsToRequire.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.setAttribute('required', 'required');
            }
        });
    }
    
    // Check if a saved address is already selected on page load
    const savedAddressSelected = document.querySelector('input[name="use_address_id"]:checked');
    if (savedAddressSelected) {
        // Disable validation since we're using a saved address
        disableAddressFormValidation();
    }
    
    // Handle "add new address" link click
    if (addNewAddressLink && addressFormContainer) {
        addNewAddressLink.addEventListener('click', function(e) {
            e.preventDefault();
            // Uncheck all saved address radios
            document.querySelectorAll('input[name="use_address_id"]').forEach(radio => {
                radio.checked = false;
            });
            // Show address form
            addressFormContainer.style.display = 'block';
            if (savedAddresses) {
                savedAddresses.style.opacity = '0.5';
            }
            // Enable validation for new address
            enableAddressFormValidation();
        });
    }

    // When a saved address is selected, disable form validation
    document.querySelectorAll('input[name="use_address_id"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                if (addressFormContainer) {
                    addressFormContainer.style.display = 'none';
                }
                if (savedAddresses) {
                    savedAddresses.style.opacity = '1';
                }
                // Remove required attributes since we're using saved address
                disableAddressFormValidation();
            }
        });
    });

    // Update cart count in header
    const cartBadge = document.getElementById('cart-count');
    if (cartBadge) {
        const count = <?php echo $cart_count; ?>;
        cartBadge.textContent = count;
        cartBadge.style.display = count > 0 ? 'flex' : 'none';
    }

    // Form validation - simplified
    const checkoutForm = document.querySelector('.checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Check if a saved address is selected
            const savedAddressSelected = document.querySelector('input[name="use_address_id"]:checked');
            
            // If saved address is selected, allow submission immediately
            if (savedAddressSelected) {
                return true;
            }
            
            // If no saved address selected, check if new address form is visible and filled
            const addressFormVisible = addressFormContainer && 
                                      window.getComputedStyle(addressFormContainer).display !== 'none';
            
            if (!addressFormVisible) {
                e.preventDefault();
                alert('Please select a saved address or add a new one.');
                return false;
            }
            
            // Validate new address form fields
            const phone = document.getElementById('phone');
            if (phone && phone.value.trim().length < 10) {
                e.preventDefault();
                alert('Please enter a valid phone number (at least 10 characters)');
                phone.focus();
                return false;
            }
            
            const requiredFields = [
                document.getElementById('first_name'),
                document.getElementById('last_name'),
                document.getElementById('phone'),
                document.getElementById('address_line1'),
                document.getElementById('city'),
                document.getElementById('province'),
                document.getElementById('postal_code')
            ];
            
            let allFilled = true;
            requiredFields.forEach(field => {
                if (field && !field.value.trim()) {
                    allFilled = false;
                    field.style.borderColor = '#ff0000';
                } else if (field) {
                    field.style.borderColor = '';
                }
            });
            
            if (!allFilled) {
                e.preventDefault();
                alert('Please fill in all required address fields.');
                return false;
            }
        });
    }
});
    </script>
</body>
</html>
<?php $conn->close(); ?>