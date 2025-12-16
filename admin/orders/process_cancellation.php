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
    $action = trim($_POST['action']); // 'approve' or 'reject'
    
    if (!in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }
    
    // Get order details
    $check_stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ?");
    $check_stmt->bind_param('i', $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }
    
    $order = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check if order is in cancellation_requested status
    if ($order['status'] !== 'cancellation_requested') {
        echo json_encode(['success' => false, 'message' => 'Order is not in cancellation requested status']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $admin_id = $_SESSION['user_id'];
        
        if ($action === 'approve') {
            // Update order status to cancelled
            $update_stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
            $update_stmt->bind_param('i', $order_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to approve cancellation');
            }
            $update_stmt->close();
            
            // Restock items
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
            
            $message = 'Cancellation approved successfully!';
            
        } else { // reject
            // Update order status back to processing
            $update_stmt = $conn->prepare("UPDATE orders SET status = 'processing' WHERE order_id = ?");
            $update_stmt->bind_param('i', $order_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to reject cancellation');
            }
            $update_stmt->close();
            
            $message = 'Cancellation rejected. Order returned to processing.';
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'order_id' => $order_id,
            'new_status' => ($action === 'approve') ? 'cancelled' : 'processing'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>