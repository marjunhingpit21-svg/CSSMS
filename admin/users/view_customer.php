<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); // Suppress notices/warnings, keep errors
require_once '../includes/db.php';
session_start();

// Optional: Redirect if not admin (add your auth check)
// if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
//     header("Location: ../login.php");
//     exit();
// }

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid customer ID");
}

$customer_id = (int)$_GET['id'];

// Fetch Customer Details
$sql = "
    SELECT 
        c.customer_id, 
        c.phone, 
        c.created_at,
        u.username, 
        u.email, 
        u.is_active,
        sa.address_line1, 
        sa.address_line2, 
        sa.city, 
        sa.province AS state,
        sa.postal_code AS zip_code
    FROM customers c
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN shipping_addresses sa ON c.customer_id = sa.customer_id AND sa.is_default = 1
    WHERE c.customer_id = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database prepare error: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    die("Customer not found");
}

// Fetch Order History
$orders_sql = "
    SELECT 
        o.order_id,
        o.order_date,
        o.status,
        o.total_amount,
        o.payment_method
    FROM orders o
    WHERE o.customer_id = ?
    ORDER BY o.order_date DESC
";

$stmt2 = $conn->prepare($orders_sql);
if (!$stmt2) {
    die("Database prepare error (orders): " . htmlspecialchars($conn->error));
}
$stmt2->bind_param("i", $customer_id);
$stmt2->execute();
$orders_result = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['username'] ?? 'Guest') ?> - Customer Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="css/customers.css">
    <style>
        main { margin-left: 280px; padding: 100px 40px 60px; transition: margin-left 0.3s ease; }
        .admin-sidebar.collapsed ~ main { margin-left: 70px; }
        @media (max-width: 768px) { main { margin-left: 0; padding-top: 140px; } }

        .back-btn {
            display: inline-block;
            padding: 12px 28px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 24px;
            transition: all 0.3s;
        }
        .back-btn:hover { background: #5a67d8; transform: translateY(-2px); }

        .profile-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 30px;
        }
        .profile-header h1 { margin: 0 0 8px; font-size: 28px; }
        .profile-header p { margin: 0; opacity: 0.9; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        .info-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .info-card h3 {
            margin: 0 0 16px;
            color: #1f2937;
            font-size: 18px;
            border-bottom: 2px solid #e91e63;
            padding-bottom: 8px;
            display: inline-block;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-item:last-child { border-bottom: none; }
        .label { color: #6b7280; font-weight: 500; }
        .value { color: #1f2937; font-weight: 600; text-align: right; }

        .orders-section h2 {
            font-size: 24px;
            margin: 40px 0 20px;
            color: #1f2937;
        }
        .order-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 24px;
        }
        .order-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-id { font-weight: 700; color: #e91e63; font-size: 18px; }
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-shipped,
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .order-items { padding: 20px; }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .item-row:last-child { border-bottom: none; }
        .item-name { flex: 1; font-weight: 500; }
        .item-details { color: #6b7280; font-size: 14px; margin-top: 4px; }
        .total-row {
            font-weight: 700;
            font-size: 18px;
            color: #e91e63;
            margin-top: 16px;
            text-align: right;
        }

        .no-orders {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        .no-orders .material-icons {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <a href="customers_index.php" class="back-btn">← Back to Customers</a>

        <div class="profile-header">
            <h1><?= htmlspecialchars($customer['username'] ?? 'Guest Customer') ?></h1>
            <p>
                Customer ID: #<?= str_pad($customer['customer_id'], 6, '0', STR_PAD_LEFT) ?> 
                • Joined <?= date('F j, Y', strtotime($customer['created_at'])) ?>
            </p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>Account Details</h3>
                <div class="info-item">
                    <span class="label">Email</span>
                    <span class="value"><?= htmlspecialchars($customer['email'] ?? '—') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($customer['phone'] ?: '—') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="status-badge <?= ($customer['is_active'] ?? 0) ? 'status-delivered' : 'status-cancelled' ?>">
                            <?= ($customer['is_active'] ?? 0) ? 'Active' : 'Inactive' ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="info-card">
                <h3>Default Shipping Address</h3>
                <?php if ($customer['address_line1']): ?>
                    <div class="info-item">
                        <span class="label">Address</span>
                        <span class="value">
                            <?= htmlspecialchars($customer['address_line1']) ?><br>
                            <?= $customer['address_line2'] ? htmlspecialchars($customer['address_line2']) . '<br>' : '' ?>
                            <?= htmlspecialchars($customer['city'] . ', ' . $customer['state']) ?><br>
                            <?= htmlspecialchars($customer['zip_code'] ?? '') ?>
                        </span>
                    </div>
                <?php else: ?>
                    <p style="color:#9ca3af; font-style:italic; margin-top:16px;">No address saved</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="orders-section">
            <h2>Order History (<?= $orders_result->num_rows ?>)</h2>

            <?php if ($orders_result->num_rows > 0): ?>
                <?php 
                $orders_result_data = $orders_result->fetch_all(MYSQLI_ASSOC); // Fetch all orders first to loop safely
                $orders_result->data_seek(0); // Reset pointer if needed
                foreach ($orders_result_data as $order): 
                ?>
                    <?php
                    $items_sql = "
                        SELECT 
                            p.product_name,
                            COALESCE(ps.barcode, '—') AS barcode,
                            COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) AS size_display,
                            oi.quantity,
                            oi.unit_price,
                            (oi.quantity * oi.unit_price) AS line_total
                        FROM order_items oi
                        JOIN inventory i ON oi.inventory_id = i.inventory_id
                        JOIN products p ON i.product_id = p.product_id
                        LEFT JOIN product_sizes ps ON (
                            ps.product_id = p.product_id 
                            AND (
                                (i.size_id IS NOT NULL AND ps.clothing_size_id = i.size_id)
                                OR 
                                (i.shoe_size_id IS NOT NULL AND ps.shoe_size_id = i.shoe_size_id)
                            )
                        )
                        LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
                        LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
                        WHERE oi.order_id = ?
                    ";
                    $stmt3 = $conn->prepare($items_sql);
                    if (!$stmt3) {
                        echo "<p style='color:red;'>Error loading items for order #{$order['order_id']}: " . htmlspecialchars($conn->error) . "</p>";
                        continue;
                    }
                    $stmt3->bind_param("i", $order['order_id']);
                    $stmt3->execute();
                    $items_result = $stmt3->get_result();
                    ?>

                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?></div>
                                <small><?= date('M d, Y \a\t g:i A', strtotime($order['order_date'])) ?></small>
                            </div>
                            <div style="text-align:right;">
                                <div class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                    <?= ucfirst($order['status']) ?>
                                </div>
                                <div style="margin-top:8px; font-weight:700; color:#e91e63; font-size:18px;">
                                    $<?= number_format($order['total_amount'], 2) ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-items">
                            <?php if ($items_result->num_rows > 0): ?>
                                <?php while ($item = $items_result->fetch_assoc()): ?>
                                    <div class="item-row">
                                        <div>
                                            <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <div class="item-details">
                                                Barcode: <?= htmlspecialchars($item['barcode']) ?> • Qty: <?= $item['quantity'] ?>
                                            </div>
                                        </div>
                                        <div style="font-weight:600;">
                                            $<?= number_format($item['line_total'], 2) ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="item-row" style="text-align:center; color:#9ca3af; padding:20px;">
                                    No items found for this order.
                                </div>
                            <?php endif; ?>

                            <div class="total-row">
                                Total: $<?= number_format($order['total_amount'], 2) ?>
                                • Paid via <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="info-card no-orders">
                    <span class="material-icons">shopping_bag</span>
                    <p style="font-size:18px; margin:0;">No orders placed yet</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>