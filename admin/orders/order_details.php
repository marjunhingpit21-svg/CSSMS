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
        // Fetch order items - FIXED QUERY
        $items_query = "SELECT oi.*, 
                               p.product_name,
                               p.image_url,
                               p.product_id,
                               COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) AS size_display
                        FROM order_items oi
                        INNER JOIN inventory i ON oi.inventory_id = i.inventory_id
                        INNER JOIN products p ON i.product_id = p.product_id
                        LEFT JOIN clothing_sizes cs ON i.size_id = cs.clothing_size_id
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
        
        
        <div class="modal-body">
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
                            <?= $status_workflow[$order['status']]['label'] ?>
                        </span>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value"><?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Subtotal</div>
                    <div class="info-value">₱<?= number_format($order['subtotal'], 2) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Tax</div>
                    <div class="info-value">₱<?= number_format($order['tax'], 2) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Discount</div>
                    <div class="info-value">₱<?= number_format($order['discount'], 2) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Total Amount</div>
                    <div class="info-value price-text">₱<?= number_format($order['total_amount'], 2) ?></div>
                </div>
            </div>
            
            <div class="info-card mb-4">
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
                        <span class="text-muted">N/A</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <h5 class="mb-3">Order Items</h5>
            <?php if (!empty($items)): ?>
                <div class="table-responsive">
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
                                            <?php if (!empty($item['image_url'])): ?>
                                                <?php 
                                                // Check if the image URL is relative and add appropriate path
                                                $image_path = $item['image_url'];
                                                if (strpos($image_path, 'http') !== 0) {
                                                    $image_path = '../../' . ltrim($image_path, '/');
                                                }
                                                ?>
                                                <img src="<?= htmlspecialchars($image_path) ?>" 
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                     class="item-image"
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div class="no-image" style="width: 50px; height: 50px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                                                    <i class="fas fa-image text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($item['product_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['size_display'] ?? 'N/A') ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                                    <td class="price-text">₱<?= number_format($item['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align: right; font-weight: 600; border-top: 2px solid #e5e7eb; padding-top: 16px;">Subtotal:</td>
                                <td style="border-top: 2px solid #e5e7eb; padding-top: 16px;">₱<?= number_format($order['subtotal'], 2) ?></td>
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
                                <td colspan="4" style="text-align: right; font-weight: 600; font-size: 1.1rem; padding-top: 16px;">Total Amount:</td>
                                <td style="font-weight: 600; font-size: 1.1rem; padding-top: 16px;" class="price-text">₱<?= number_format($order['total_amount'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state py-4">
                    <p class="text-muted">No items found for this order.</p>
                </div>
            <?php endif; ?>
            
            <div class="status-actions mt-4">
                <?php if ($next_status): ?>
                    <button type="button" 
                            class="add-btn mark-as-next"
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
                            onclick="cancelOrder(this)"
                            style="background: #ef4444; color: white; padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                        <i class="fas fa-times"></i> Cancel Order
                    </button>
                <?php endif; ?>
            </div>
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
                            showFlash('Order status updated successfully!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showFlash('Error: ' + response.message, 'error');
                        }
                    },
                    error: function() {
                        showFlash('Network error. Please try again.', 'error');
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
                            showFlash('Order cancelled successfully!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showFlash('Error: ' + response.message, 'error');
                        }
                    },
                    error: function() {
                        showFlash('Network error. Please try again.', 'error');
                    }
                });
            }
        }
        
        function showFlash(message, type) {
            const flash = document.createElement('div');
            flash.className = `flash-message flash-${type}`;
            flash.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            document.body.appendChild(flash);
            
            setTimeout(() => flash.classList.add('show'), 10);
            
            setTimeout(() => {
                flash.classList.remove('show');
                setTimeout(() => flash.remove(), 400);
            }, 3000);
        }
        </script>
        <?php
    } else {
        echo '<div class="modal-header"><h3>Error</h3><button type="button" class="close-modal">&times;</button></div>';
        echo '<div class="modal-body"><div class="alert alert-danger">Order not found</div></div>';
    }
} else {
    echo '<div class="modal-header"><h3>Error</h3><button type="button" class="close-modal">&times;</button></div>';
    echo '<div class="modal-body"><div class="alert alert-danger">Order ID not specified</div></div>';
}
?>