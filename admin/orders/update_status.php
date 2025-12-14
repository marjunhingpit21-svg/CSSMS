<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    $new_status = trim($_POST['new_status']);
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    // Validate status
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Get current order status
    $check_stmt = $conn->prepare("SELECT status, customer_id FROM orders WHERE order_id = ?");
    $check_stmt->bind_param('i', $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }
    
    $order = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check if status is already the same
    if ($order['status'] === $new_status) {
        echo json_encode(['success' => false, 'message' => 'Order already has this status']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update order status (removed updated_at)
        $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $update_stmt->bind_param('si', $new_status, $order_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update order status');
        }
        $update_stmt->close();
        
        // Log the activity
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (employee_id, action, description, created_at) VALUES (?, 'order_status_update', ?, NOW())");
        $employee_id = $_SESSION['user_id'];
        $description = "Updated order #{$order_id} from {$order['status']} to {$new_status}";
        if (!empty($admin_notes)) {
            $description .= ". Notes: {$admin_notes}";
        }
        $log_stmt->bind_param('is', $employee_id, $description);
        $log_stmt->execute();
        $log_stmt->close();
        
        // If order is cancelled, restock items
        if ($new_status === 'cancelled') {
            $restock_stmt = $conn->prepare("
                SELECT oi.inventory_id, oi.quantity, i.quantity as current_stock
                FROM order_items oi
                JOIN inventory i ON oi.inventory_id = i.inventory_id
                WHERE oi.order_id = ?
            ");
            $restock_stmt->bind_param('i', $order_id);
            $restock_stmt->execute();
            $items_result = $restock_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $new_quantity = $item['current_stock'] + $item['quantity'];
                $update_inv_stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
                $update_inv_stmt->bind_param('ii', $new_quantity, $item['inventory_id']);
                $update_inv_stmt->execute();
                $update_inv_stmt->close();
            }
            $restock_stmt->close();
        }
        
        // If order was cancelled and now being uncancelled, subtract stock
        if ($order['status'] === 'cancelled' && $new_status !== 'cancelled') {
            $unrestock_stmt = $conn->prepare("
                SELECT oi.inventory_id, oi.quantity, i.quantity as current_stock
                FROM order_items oi
                JOIN inventory i ON oi.inventory_id = i.inventory_id
                WHERE oi.order_id = ?
            ");
            $unrestock_stmt->bind_param('i', $order_id);
            $unrestock_stmt->execute();
            $items_result = $unrestock_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $new_quantity = max(0, $item['current_stock'] - $item['quantity']);
                $update_inv_stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?");
                $update_inv_stmt->bind_param('ii', $new_quantity, $item['inventory_id']);
                $update_inv_stmt->execute();
                $update_inv_stmt->close();
            }
            $unrestock_stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Order status updated successfully!',
            'new_status' => $new_status,
            'order_id' => $order_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
