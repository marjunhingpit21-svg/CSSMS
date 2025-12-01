<?php
// profile.php
session_start();
require_once 'Database/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user data
$user_query = $conn->prepare("SELECT u.*, c.customer_id, c.first_name, c.last_name, c.phone, c.address, c.city, c.postal_code 
                               FROM users u 
                               LEFT JOIN customers c ON u.user_id = c.user_id 
                               WHERE u.user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_data = $user_query->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Update users table
        $update_user = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $update_user->bind_param("si", $email, $user_id);
        $update_user->execute();
        
        // Update or insert customer data
        if ($user_data['customer_id']) {
            $update_customer = $conn->prepare("UPDATE customers SET first_name=?, last_name=?, email=?, phone=?, address=?, city=?, postal_code=? WHERE customer_id=?");
            $update_customer->bind_param("sssssssi", $first_name, $last_name, $email, $phone, $address, $city, $postal_code, $user_data['customer_id']);
            $update_customer->execute();
        } else {
            $insert_customer = $conn->prepare("INSERT INTO customers (user_id, first_name, last_name, email, phone, address, city, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_customer->bind_param("isssssss", $user_id, $first_name, $last_name, $email, $phone, $address, $city, $postal_code);
            $insert_customer->execute();
        }
        
        $success = 'Profile updated successfully!';
        // Refresh user data
        $user_query->execute();
        $user_data = $user_query->get_result()->fetch_assoc();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Verify current password
        if (password_verify($current_password, $user_data['password_hash'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update_pass->bind_param("si", $new_hash, $user_id);
            $update_pass->execute();
            $success = 'Password changed successfully!';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// Get saved addresses
$addresses_query = $conn->prepare("SELECT * FROM shipping_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC");
$addresses_query->bind_param("i", $user_data['customer_id']);
$addresses_query->execute();
$addresses = $addresses_query->get_result();

// Get order history
$orders_query = $conn->prepare("SELECT o.*, COUNT(oi.order_item_id) as item_count 
                                FROM orders o 
                                LEFT JOIN order_items oi ON o.order_id = oi.order_id 
                                WHERE o.customer_id = ? 
                                GROUP BY o.order_id 
                                ORDER BY o.order_date DESC");
$orders_query->bind_param("i", $user_data['customer_id']);
$orders_query->execute();
$orders = $orders_query->get_result();

// Get cart count for header
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

// Get orders count for header
$orders_count = 0;
if ($user_data['customer_id']) {
    $count_query = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?");
    $count_query->bind_param("i", $user_data['customer_id']);
    $count_query->execute();
    $orders_count = $count_query->get_result()->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/profile.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="profile-wrapper">
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['username']); ?>&background=e91e63&color=fff&bold=true&size=120" 
                         alt="Profile Picture" class="profile-avatar">
                    <h3><?php echo htmlspecialchars($user_data['username']); ?></h3>
                    <p><?php echo htmlspecialchars($user_data['email']); ?></p>
                </div>

                <nav class="profile-nav">
                    <a href="#account" class="profile-nav-item active" data-tab="account">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Account Details
                    </a>
                    <a href="#security" class="profile-nav-item" data-tab="security">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Security
                    </a>
                    <a href="#addresses" class="profile-nav-item" data-tab="addresses">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Addresses
                    </a>
                    <a href="#orders" class="profile-nav-item" data-tab="orders">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Order History
                    </a>
                </nav>
            </div>

            <div class="profile-content">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Account Details Tab -->
                <div id="account-tab" class="profile-tab active">
                    <h2>Account Details</h2>
                    <form method="POST" class="profile-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required 
                                       value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($user_data['email']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" 
                                   value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" 
                                       value="<?php echo htmlspecialchars($user_data['postal_code'] ?? ''); ?>">
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn-primary">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="security-tab" class="profile-tab">
                    <h2>Change Password</h2>
                    <form method="POST" class="profile-form">
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small>Must be at least 6 characters</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>

                        <button type="submit" name="change_password" class="btn-primary">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Change Password
                        </button>
                    </form>
                </div>

                <!-- Addresses Tab -->
                <div id="addresses-tab" class="profile-tab">
                    <div class="tab-header">
                        <h2>Saved Addresses</h2>
                        <button class="btn-secondary" onclick="openAddressModal()">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add New Address
                        </button>
                    </div>

                    <div class="addresses-grid">
                        <?php if ($addresses->num_rows > 0): ?>
                            <?php while ($addr = $addresses->fetch_assoc()): ?>
                                <div class="address-card <?php echo $addr['is_default'] ? 'default' : ''; ?>">
                                    <?php if ($addr['is_default']): ?>
                                        <span class="default-badge">Default</span>
                                    <?php endif; ?>
                                    <h4><?php echo htmlspecialchars($addr['address_label']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($addr['first_name'] . ' ' . $addr['last_name']); ?><br>
                                        <?php echo htmlspecialchars($addr['phone']); ?><br>
                                        <?php echo htmlspecialchars($addr['address_line1']); ?>
                                        <?php if ($addr['address_line2']): ?>
                                            <br><?php echo htmlspecialchars($addr['address_line2']); ?>
                                        <?php endif; ?><br>
                                        <?php echo htmlspecialchars($addr['city'] . ', ' . $addr['province'] . ' ' . $addr['postal_code']); ?>
                                    </p>
                                    <div class="address-actions">
                                        <button class="btn-icon" onclick="editAddress(<?php echo $addr['address_id']; ?>)">
                                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon btn-delete" onclick="deleteAddress(<?php echo $addr['address_id']; ?>)">
                                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <h3>No saved addresses</h3>
                                <p>Add an address to make checkout faster</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Orders Tab -->
                <div id="orders-tab" class="profile-tab">
                    <h2>Order History</h2>

                    <?php if ($orders->num_rows > 0): ?>
                        <div class="orders-list">
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <h4>Order #<?php echo $order['order_id']; ?></h4>
                                            <p><?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                                        </div>
                                        <span class="order-status status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    <div class="order-details">
                                        <div class="order-info">
                                            <span><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] > 1 ? 's' : ''; ?></span>
                                            <span>•</span>
                                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                            <span>•</span>
                                            <span><?php echo ucfirst($order['payment_method']); ?></span>
                                        </div>
                                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-link">
                                            View Details →
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <h3>No orders yet</h3>
                            <p>Start shopping to see your orders here</p>
                            <a href="index.php" class="btn-primary">Start Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/profile.js"></script>
</body>
</html>
<script>
  // profile.js - User Profile Functionality

document.addEventListener('DOMContentLoaded', function() {
    // Tab Navigation
    const navItems = document.querySelectorAll('.profile-nav-item');
    const tabs = document.querySelectorAll('.profile-tab');

    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const targetTab = this.getAttribute('data-tab');

            // Remove active class from all nav items and tabs
            navItems.forEach(nav => nav.classList.remove('active'));
            tabs.forEach(tab => tab.classList.remove('active'));

            // Add active class to clicked nav item
            this.classList.add('active');

            // Show corresponding tab
            const activeTab = document.getElementById(`${targetTab}-tab`);
            if (activeTab) {
                activeTab.classList.add('active');
            }

            // Update URL hash without scrolling
            history.pushState(null, null, `#${targetTab}`);
        });
    });

    // Handle direct URL hash navigation
    const hash = window.location.hash.substring(1);
    if (hash) {
        const targetNavItem = document.querySelector(`[data-tab="${hash}"]`);
        if (targetNavItem) {
            targetNavItem.click();
        }
    }

    // Password confirmation validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== newPassword.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// Address Management Functions
function openAddressModal() {
    alert('Add Address Modal - To be implemented with full address form');
    // In a real implementation, you would open a modal with address form
    // For now, you can redirect to checkout.php or create a separate address form
}

function editAddress(addressId) {
    alert(`Edit Address ID: ${addressId} - To be implemented`);
    // In a real implementation, you would:
    // 1. Fetch address data via AJAX
    // 2. Open modal pre-filled with address data
    // 3. Submit updates via AJAX
}

function deleteAddress(addressId) {
    if (confirm('Are you sure you want to delete this address?')) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_address.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'address_id';
        input.value = addressId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Phone number formatting
const phoneInputs = document.querySelectorAll('input[type="tel"]');
phoneInputs.forEach(input => {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        e.target.value = value;
    });
});

// Form validation before submit
const forms = document.querySelectorAll('form');
forms.forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredInputs = this.querySelectorAll('input[required]');
        let isValid = true;

        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.style.borderColor = '#f44336';
            } else {
                input.style.borderColor = '';
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});
</script>