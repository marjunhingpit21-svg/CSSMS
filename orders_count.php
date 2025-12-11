<?php
// orders_count.php
function getOrdersCount($conn, $user_id) {
    $orders_count = 0;
    
    if (isset($user_id) && $conn) {
        try {
            // Get customer ID first
            $customer_stmt = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
            $customer_stmt->bind_param("i", $user_id);
            $customer_stmt->execute();
            $customer_result = $customer_stmt->get_result();
            
            if ($customer_result->num_rows > 0) {
                $customer = $customer_result->fetch_assoc();
                $customer_id = $customer['customer_id'];
                
                // Count orders that should show in badge:
                // - to_ship (pending, processing)
                // - to_receive (shipped) 
                // - to_rate (delivered)
                // Exclude: completed, cancelled
                $count_stmt = $conn->prepare("
                    SELECT COUNT(*) as order_count 
                    FROM orders 
                    WHERE customer_id = ? AND status IN ('pending', 'processing', 'shipped', 'delivered','received')
                ");
                $count_stmt->bind_param("i", $customer_id);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $count_data = $count_result->fetch_assoc();
                $orders_count = $count_data['order_count'];
                
                $count_stmt->close();
            }
            $customer_stmt->close();
            
        } catch (Exception $e) {
            error_log("Error in getOrdersCount: " . $e->getMessage());
        }
    }
    
    return $orders_count;
}
?>