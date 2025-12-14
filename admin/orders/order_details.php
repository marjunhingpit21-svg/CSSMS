<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo '<p class="text-danger">Unauthorized access</p>';
    exit();
}

if (isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    
    // Fetch order details
    $order_query = "SELECT o.*, 
                           c.first_name, 
                           c.last_name, 
                           c.email, 
                           c.phone,
                           sa.address_line1,
                           sa.address_line2,
                           sa.city,
                           sa.province,
                           sa.postal_code
                    FROM orders o
                    LEFT JOIN customers c ON o.customer_id = c.customer_id
                    LEFT JOIN shipping_addresses sa ON o.address_id = sa.address_id
                    WHERE o.order_id = ?";
    
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param('i', $order_id);
    $order_stmt->execute();
    $order = $order_stmt->get_result()->fetch_assoc();
    
    if ($order) {
        // Fetch order items
        $items_query = "SELECT oi.*, 
                               p.product_name,
                               p.image_url,
                               s.size_name,
                               ss.size_us as shoe_size
                        FROM order_items oi
                        LEFT JOIN inventory i ON oi.inventory_id = i.inventory_id
                        LEFT JOIN products p ON i.product_id = p.product_id
                        LEFT JOIN sizes s ON i.size_id = s.size_id
                        LEFT JOIN shoe_sizes ss ON i.shoe_size_id = ss.shoe_size_id
                        WHERE oi.order_id = ?";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param('i', $order_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Status workflow
        $status_workflow = [
            'pending' => ['label' => 'Pending', 'color' => 'bg-yellow-100 text-yellow-800', 'next' => 'processing'],
            'processing' => ['label' => 'Processing', 'color' => 'bg-blue-100 text-blue-800', 'next' => 'shipped'],
            'shipped' => ['label' => 'Shipped', 'color' => 'bg-indigo-100 text-indigo-800', 'next' => 'delivered'],
            'delivered' => ['label' => 'Delivered', 'color' => 'bg-green-100 text-green-800', 'next' => 'completed'],
            'completed' => ['label' => 'Completed', 'color' => 'bg-emerald-100 text-emerald-800', 'next' => null],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800', 'next' => null],
        ];
        
        $next_status = $status_workflow[$order['status']]['next'] ?? null;
        ?>
        
        <style>
            .order-info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .info-card {
                background: #f9fafb;
                padding: 1rem;
                border-radius: 8px;
                border-left: 4px solid #3b82f6;
            }
            
            .info-label {
                font-size: 0.75rem;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 0.25rem;
            }
            
            .info-value {
                font-weight: 500;
                color: #1f2937;
            }
            
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 1rem;
            }
            
            .items-table th {
                background: #f3f4f6;
                padding: 0.75rem;
                text-align: left;
                font-weight: 600;
                color: #374151;
                font-size: 0.75rem;
                text-transform: uppercase;
            }
            
            .items-table td {
                padding: 0.75rem;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .item-image {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 6px;
            }
            
            .status-actions {
                display: flex;
                gap: 0.5rem;
                margin-top: 1.5rem;
                padding-top: 1.5rem;
                border-top: 1px solid #e5e7eb;
            }
        </style>
        
        <div class="order-info-grid">
            <div class="info-card">
                <div class="info-label">Customer</div>
                <div class="info-value"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Email</div>
                <div class="info-value"><?= htmlspecialchars($order['email']) ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Phone</div>
                <div class="info-value"><?= htmlspecialchars($order['phone']) ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Order Date</div>
                <div class="info-value"><?= date('M d, Y h:i A', strtotime($order['order_date'])) ?></div>
            </div>
            <div class="info-card">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="status-badge <?= $status_workflow[$order['status']]['color'] ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>
            </div>
            <div class="info-card">
                <div class="info-label">Payment Method</div>
                <div class="info-value"><?= ucfirst($order['payment_method']) ?></div>
            </div>
        </div>
        
        <div class="info-card mb-3">
            <div class="info-label">Shipping Address</div>
            <div class="info-value">
                <?php if ($order['address_line1']): ?>
                    <?= htmlspecialchars($order['address_line1']) ?><br>
                    <?php if ($order['address_line2']): ?>
                        <?= htmlspecialchars($order['address_line2']) ?><br>
                    <?php endif; ?>
                    <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['province']) ?><br>
                    <?= htmlspecialchars($order['postal_code']) ?>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </div>
        </div>
        
        <h5>Order Items</h5>
        <?php if (!empty($items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Size</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if ($item['image_url']): ?>
                                        <img src="../<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="item-image">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($item['product_name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($item['size_name'] ?? $item['shoe_size'] ?? 'N/A') ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                            <td>₱<?= number_format($item['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: 600;">Subtotal:</td>
                        <td>₱<?= number_format($order['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: 600;">Tax:</td>
                        <td>₱<?= number_format($order['tax'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: 600;">Discount:</td>
                        <td>₱<?= number_format($order['discount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right; font-weight: 600; border-top: 2px solid #e5e7eb;">Total Amount:</td>
                        <td style="border-top: 2px solid #e5e7eb;">₱<?= number_format($order['total_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <p class="text-muted">No items found for this order.</p>
        <?php endif; ?>
        
        <div class="status-actions">
            <?php if ($next_status): ?>
                <button type="button" 
                        class="btn-success mark-as-next"
                        data-order-id="<?= $order_id ?>"
                        data-current-status="<?= $order['status'] ?>"
                        data-next-status="<?= $next_status ?>"
                        onclick="markAsNext(this)">
                    <i class="fas fa-arrow-right"></i> Mark as <?= ucfirst($next_status) ?>
                </button>
            <?php endif; ?>
            
            <?php if ($order['status'] !== 'cancelled'): ?>
                <button type="button" 
                        class="btn-danger cancel-order"
                        data-order-id="<?= $order_id ?>"
                        onclick="cancelOrder(this)">
                    <i class="fas fa-times"></i> Cancel Order
                </button>
            <?php endif; ?>
        </div>
        
        <script>
        function markAsNext(button) {
            const orderId = button.dataset.orderId;
            const nextStatus = button.dataset.nextStatus;
            
            if (confirm(`Mark order #${orderId} as ${nextStatus}?`)) {
                $.ajax({
                    url: 'update_status.php',
                    type: 'POST',
                    data: {
                        order_id: orderId,
                        new_status: nextStatus,
                        admin_notes: 'Status updated from order details modal'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Order status updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                });
            }
        }
        
        function cancelOrder(button) {
            const orderId = button.dataset.orderId;
            
            if (confirm(`Cancel order #${orderId}? This action cannot be undone.`)) {
                $.ajax({
                    url: 'update_status.php',
                    type: 'POST',
                    data: {
                        order_id: orderId,
                        new_status: 'cancelled',
                        admin_notes: 'Order cancelled from order details modal'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Order cancelled successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                });
            }
        }
        </script>
        <?php
    } else {
        echo '<p class="text-danger">Order not found</p>';
    }
} else {
    echo '<p class="text-danger">Order ID not specified</p>';
}
