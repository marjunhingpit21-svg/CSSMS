<?php
// process_sale.php - WITH AUTHORIZATION DETAILS
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items in cart']);
    exit;
}

try {
    $conn->begin_transaction();

    // Prepare cash_received value
    $cash_received = null;
    if (($input['payment_method'] === 'cash' || $input['payment_method'] === 'ewallet') && isset($input['cash_received'])) {
        $cash_received = floatval($input['cash_received']);
        
        // Validate cash payment
        if ($input['payment_method'] === 'cash' && $cash_received < $input['total_amount']) {
            throw new Exception("Cash received is less than total amount");
        }
    }

    // Get transaction reference
    $transaction_reference = isset($input['transaction_reference']) && !empty($input['transaction_reference']) 
        ? trim($input['transaction_reference']) 
        : null;

    // Get subtotal and tax from input
    $subtotal = isset($input['subtotal']) ? floatval($input['subtotal']) : 0;
    $tax = isset($input['tax']) ? floatval($input['tax']) : 0;
    $discount = isset($input['discount']) ? floatval($input['discount']) : 0;
    $total_amount = floatval($input['total_amount']);
    
    // Prepare notes
    $sale_notes = '';
    
    // Add discount info to notes
    if (isset($input['discount_percentage']) && $input['discount_percentage'] > 0) {
        $discount_type = isset($input['discount_type']) ? $input['discount_type'] : '';
        $discount_id = isset($input['discount_id_number']) ? $input['discount_id_number'] : '';
        
        $sale_notes .= "Discount: " . $input['discount_percentage'] . "%";
        if ($discount_type) {
            $sale_notes .= " (" . strtoupper($discount_type) . ")";
        }
        if ($discount_id) {
            $sale_notes .= " ID: " . $discount_id;
        }
    }
    
    // Prepare authorization details for the sale
    $authorization_details = null;
    if (isset($input['authorization_data'])) {
        $auth_data = $input['authorization_data'];
        
        // Create structured authorization details
        $auth_details = [
            'log_id' => isset($auth_data['log_id']) ? $auth_data['log_id'] : null,
            'authorized_by' => isset($auth_data['employee_name']) ? $auth_data['employee_name'] : '',
            'position' => isset($auth_data['position']) ? $auth_data['position'] : '',
            'employee_number' => isset($auth_data['employee_number']) ? $auth_data['employee_number'] : '',
            'reason' => isset($auth_data['reason']) ? $auth_data['reason'] : '',
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => isset($input['authorization_action']) ? $input['authorization_action'] : 'SALE_AUTHORIZATION'
        ];
        
        $authorization_details = json_encode($auth_details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Also add to notes for visibility
        if (!empty($sale_notes)) {
            $sale_notes .= "\n\n";
        }
        $sale_notes .= "AUTHORIZED TRANSACTION\n";
        $sale_notes .= "Authorized by: " . $auth_data['employee_name'] . " (" . $auth_data['position'] . ")\n";
        if (isset($auth_data['reason']) && !empty($auth_data['reason'])) {
            $sale_notes .= "Reason: " . $auth_data['reason'];
        }
    }
    
    // Prepare discount authorization details
    $discount_authorized_by = null;
    $discount_authorized_position = null;
    
    // Check if authorization data exists (from pendingAuthorization)
    if (isset($input['authorization_data'])) {
        $auth_data = $input['authorization_data'];
        $discount_authorized_by = isset($auth_data['employee_name']) ? $auth_data['employee_name'] : null;
        $discount_authorized_position = isset($auth_data['position']) ? $auth_data['position'] : null;
    }
    
    // Also check the old format for backwards compatibility
    if (isset($input['discount_type']) && $input['discount_type'] === 'others' && isset($input['discount_authorization'])) {
        $discount_auth = $input['discount_authorization'];
        if (!$discount_authorized_by) {
            $discount_authorized_by = isset($discount_auth['authorized_by']) ? $discount_auth['authorized_by'] : null;
        }
        if (!$discount_authorized_position) {
            $discount_authorized_position = isset($discount_auth['authorized_position']) ? $discount_auth['authorized_position'] : null;
        }
    }
    
    // Add discount authorization to sale notes if available
    if ($discount_authorized_by && $discount_authorized_position && isset($input['discount_type']) && $input['discount_type'] === 'others') {
        if (!empty($sale_notes)) {
            $sale_notes .= "\n\n";
        }
        $sale_notes .= "CUSTOM DISCOUNT AUTHORIZATION\n";
        $sale_notes .= "Authorized by: " . $discount_authorized_by . " (" . $discount_authorized_position . ")";
    }
    
    // Insert sale with authorization details
    $stmt = $conn->prepare("
        INSERT INTO sales 
        (employee_id, subtotal, tax, total_amount, discount, payment_method, 
         payment_status, cash_received, transaction_reference, notes, 
         authorization_details, authorized_by, authorized_by_position, sale_date)
        VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param(
        "iddddsdsssss",
        $input['employee_id'],
        $subtotal,
        $tax,
        $total_amount,
        $discount,
        $input['payment_method'],
        $cash_received,
        $transaction_reference,
        $sale_notes,
        $authorization_details,
        $discount_authorized_by,
        $discount_authorized_position
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create sale: " . $stmt->error);
    }
    
    $sale_id = $conn->insert_id;
    
    // Get the generated sale_number
    $result = $conn->query("SELECT sale_number FROM sales WHERE sale_id = $sale_id");
    $row = $result->fetch_assoc();
    $sale_number = $row['sale_number'];

    // Insert sale items & reduce stock
    $itemStmt = $conn->prepare("
        INSERT INTO sale_items 
        (sale_id, product_size_id, product_name, size_display, quantity, unit_price, unit_cost, subtotal, total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stockStmt = $conn->prepare("
        UPDATE product_sizes 
        SET stock_quantity = stock_quantity - ? 
        WHERE product_size_id = ? AND stock_quantity >= ?
    ");

    // Get actual cost prices
    $productSizeIds = array_map(function($item) { 
        return isset($item['product_size_id']) ? intval($item['product_size_id']) : 0; 
    }, $input['items']);
    
    // Filter out invalid IDs
    $productSizeIds = array_filter($productSizeIds, function($id) { return $id > 0; });
    
    if (empty($productSizeIds)) {
        throw new Exception("No valid product size IDs found");
    }
    
    $placeholders = implode(',', array_fill(0, count($productSizeIds), '?'));
    
    $costQuery = $conn->prepare("
        SELECT ps.product_size_id, p.cost_price 
        FROM product_sizes ps
        JOIN products p ON ps.product_id = p.product_id
        WHERE ps.product_size_id IN ($placeholders)
    ");
    
    $types = str_repeat('i', count($productSizeIds));
    $costQuery->bind_param($types, ...$productSizeIds);
    $costQuery->execute();
    $costResult = $costQuery->get_result();
    
    $costPrices = [];
    while ($row = $costResult->fetch_assoc()) {
        $costPrices[$row['product_size_id']] = floatval($row['cost_price']);
    }
    
    $costQuery->close();

    foreach ($input['items'] as $item) {
        if (!isset($item['product_size_id']) || !isset($item['quantity'])) {
            continue; // Skip invalid items
        }
        
        $product_size_id = intval($item['product_size_id']);
        $unit_price = floatval($item['final_price']);
        $quantity = intval($item['quantity']);
        $unit_cost = isset($costPrices[$product_size_id]) ? $costPrices[$product_size_id] : 0;
        $subtotal_item = $unit_price * $quantity;
        $total = $subtotal_item;
        
        // Check if price was changed
        $price_change_authorized_by = null;
        $price_change_authorized_position = null;
        
        if (isset($item['price_changed_by']) && isset($item['price_change_position'])) {
            $price_change_authorized_by = $item['price_changed_by'];
            $price_change_authorized_position = $item['price_change_position'];
        }
        
        // Insert sale item
        $itemStmt->bind_param(
            "iissidddd", 
            $sale_id, 
            $product_size_id,
            $item['product_name'],
            $item['size_name'],
            $quantity, 
            $unit_price, 
            $unit_cost,
            $subtotal_item,
            $total
        );
        
        if (!$itemStmt->execute()) {
            throw new Exception("Failed to add sale item: " . $itemStmt->error);
        }
        
        // Get the inserted sale_item_id to update with price change authorization
        $sale_item_id = $conn->insert_id;
        
        // If price was changed with authorization, update the sale_items record
        if ($price_change_authorized_by && $price_change_authorized_position) {
            $updateItemStmt = $conn->prepare("
                UPDATE sale_items 
                SET price_change_authorized_by = ?, 
                    price_change_authorized_position = ?
                WHERE sale_item_id = ?
            ");
            
            $updateItemStmt->bind_param(
                "ssi",
                $price_change_authorized_by,
                $price_change_authorized_position,
                $sale_item_id
            );
            
            if (!$updateItemStmt->execute()) {
                throw new Exception("Failed to update price change authorization: " . $updateItemStmt->error);
            }
            
            $updateItemStmt->close();
        }

        // Reduce stock
        $stockStmt->bind_param(
            "iii", 
            $quantity, 
            $product_size_id, 
            $quantity
        );
        
        if (!$stockStmt->execute() || $stockStmt->affected_rows === 0) {
            throw new Exception("Insufficient stock for " . $item['product_name']);
        }
    }
    
    $itemStmt->close();
    $stockStmt->close();

    $conn->commit();
    
    // Return success
    $response = [
        'success' => true, 
        'sale_id' => $sale_id,
        'receipt_number' => $sale_number,
        'transaction_reference' => $transaction_reference,
        'message' => 'Payment processed successfully'
    ];
    
    // Include authorization info if available
    if ($authorization_details) {
        $response['authorization_details'] = json_decode($authorization_details, true);
    }
    
    // Include discount authorization info if available
    if ($discount_authorized_by) {
        $response['discount_authorization'] = [
            'authorized_by' => $discount_authorized_by,
            'authorized_position' => $discount_authorized_position
        ];
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Process sale error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>