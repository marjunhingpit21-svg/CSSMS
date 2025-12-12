<?php
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items in cart']);
    exit;
}

try {
    $conn->begin_transaction();

    // Prepare cash_received value
    $cash_received = null;
    if ($input['payment_method'] === 'cash' || $input['payment_method'] === 'ewallet') {
        $cash_received = isset($input['cash_received']) ? floatval($input['cash_received']) : null;
        
        // Validate cash payment
        if ($input['payment_method'] === 'cash' && $cash_received < $input['total_amount']) {
            throw new Exception("Cash received is less than total amount");
        }
    }

    // Get transaction reference (for card/ewallet payments)
    $transaction_reference = isset($input['transaction_reference']) && !empty($input['transaction_reference']) 
        ? trim($input['transaction_reference']) 
        : null;

    // Get subtotal and tax from input
    $subtotal = isset($input['subtotal']) ? floatval($input['subtotal']) : 0;
    $tax = isset($input['tax']) ? floatval($input['tax']) : 0;
    $discount = isset($input['discount']) ? floatval($input['discount']) : 0;
    $discount_percentage = isset($input['discount_percentage']) ? floatval($input['discount_percentage']) : 0;
    $total_amount = floatval($input['total_amount']);
    
    // Prepare comprehensive notes combining all item notes and metadata
    $sale_notes_array = [];
    
    // Add discount info if applicable
    if ($discount_percentage > 0) {
        $sale_notes_array[] = "Transaction Discount: {$discount_percentage}%";
    }
    
    // Collect all item-specific notes
    foreach ($input['items'] as $item) {
        $item_note_parts = [];
        
        // Add product name for context
        $item_note_parts[] = $item['product_name'] . " (" . $item['size_name'] . ")";
        
        // Add user notes if any
        if (!empty($item['notes'])) {
            $item_note_parts[] = "Note: " . trim($item['notes']);
        }
        
        // Add clerk info if different
        if (!empty($item['clerk_name'])) {
            $item_note_parts[] = "Clerk: " . trim($item['clerk_name']);
        }
        
        // Add price change indicator
        if (isset($item['price_changed']) && $item['price_changed']) {
            $item_note_parts[] = "Price Changed from ₱" . number_format($item['original_price'], 2) . " to ₱" . number_format($item['final_price'], 2);
        }
        
        // Only add to notes if there's something to note
        if (count($item_note_parts) > 1) { // More than just the product name
            $sale_notes_array[] = implode(" - ", $item_note_parts);
        }
    }
    
    // Combine all notes
    $sale_notes = implode(" | ", $sale_notes_array);
    
    // Insert sale with transaction reference and notes
    $stmt = $conn->prepare("
        INSERT INTO sales 
        (employee_id, branch_id, subtotal, tax, total_amount, discount, payment_method, 
         payment_status, cash_received, transaction_reference, notes, sale_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param(
        "iiddddsdss",
        $input['employee_id'],
        $input['branch_id'],
        $subtotal,
        $tax,
        $total_amount,
        $discount,
        $input['payment_method'],
        $cash_received,
        $transaction_reference,
        $sale_notes
    );
    
    if (!$stmt->execute()) {
        $error_msg = "Failed to create sale: " . $stmt->error;
        error_log($error_msg);
        throw new Exception($error_msg);
    }
    
    $sale_id = $conn->insert_id;
    
    // Get the generated sale_number and sale_date
    $result = $conn->query("SELECT sale_number, DATE(sale_date) as sale_date FROM sales WHERE sale_id = $sale_id");
    $row = $result->fetch_assoc();
    $sale_number = $row['sale_number'];
    $sale_date = $row['sale_date'];

    // Insert sale items & reduce stock (NO NOTES in sale_items)
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

    // Get actual cost prices for all products in cart
    $productSizeIds = array_map(function($item) { return intval($item['product_size_id']); }, $input['items']);
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

    foreach ($input['items'] as $item) {
        $unit_price = floatval($item['final_price']);
        $quantity = intval($item['quantity']);
        
        // Get actual cost from database
        if (!isset($costPrices[$item['product_size_id']])) {
            throw new Exception("Cost price not found for product: " . $item['product_name']);
        }
        $unit_cost = $costPrices[$item['product_size_id']];
            
        $subtotal_item = $unit_price * $quantity;
        $total = $subtotal_item; // No item-level discount in this implementation
        
        $product_name = $item['product_name'];
        $size_display = $item['size_name'];
        
        // Insert sale item WITHOUT notes
        $itemStmt->bind_param(
            "iissidddd", 
            $sale_id, 
            $item['product_size_id'],
            $product_name,
            $size_display,
            $quantity, 
            $unit_price, 
            $unit_cost,
            $subtotal_item,
            $total
        );
        
        if (!$itemStmt->execute()) {
            throw new Exception("Failed to add sale item: " . $itemStmt->error);
        }

        // Update stock
        $stockStmt->bind_param(
            "iii", 
            $quantity, 
            $item['product_size_id'], 
            $quantity
        );
        
        if (!$stockStmt->execute() || $stockStmt->affected_rows === 0) {
            throw new Exception("Insufficient stock for " . $product_name);
        }
    }

    // ========================================
    // UPDATE AGGREGATED SALES TABLES USING STORED PROCEDURES
    // ========================================
    
    // Update employee daily sales
    $empProc = $conn->prepare("CALL update_employee_daily_sales(?)");
    $empProc->bind_param("s", $sale_date);
    if (!$empProc->execute()) {
        error_log("Failed to update employee_daily_sales: " . $empProc->error);
    }
    $empProc->close();
    
    // Update branch daily sales
    $branchProc = $conn->prepare("CALL update_branch_daily_sales(?)");
    $branchProc->bind_param("s", $sale_date);
    if (!$branchProc->execute()) {
        error_log("Failed to update branch_daily_sales: " . $branchProc->error);
    }
    $branchProc->close();
    
    // Update daily sales (company-wide)
    // Calculate totals for daily_sales table
    $total_cost = 0;
    $total_items_sold = 0;
    
    foreach ($input['items'] as $item) {
        $quantity = intval($item['quantity']);
        $unit_cost = $costPrices[$item['product_size_id']];
        $total_cost += ($unit_cost * $quantity);
        $total_items_sold += $quantity;
    }
    
    $net_profit = $total_amount - $total_cost;
    
    $dailyStmt = $conn->prepare("
        INSERT INTO daily_sales 
        (sales_date, total_orders, total_revenue, total_cost, 
         total_profit, total_items_sold)
        VALUES (?, 1, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_orders = total_orders + 1,
            total_revenue = total_revenue + VALUES(total_revenue),
            total_cost = total_cost + VALUES(total_cost),
            total_profit = total_profit + VALUES(total_profit),
            total_items_sold = total_items_sold + VALUES(total_items_sold),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $dailyStmt->bind_param(
        "sdddi",
        $sale_date,
        $total_amount,
        $total_cost,
        $net_profit,
        $total_items_sold
    );
    
    if (!$dailyStmt->execute()) {
        error_log("Failed to update daily_sales: " . $dailyStmt->error);
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'sale_id' => $sale_id,
        'receipt_number' => $sale_number,
        'transaction_reference' => $transaction_reference
    ]);

} catch (Exception $e) {
    $conn->rollback();
    $error_details = [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    error_log("Sale processing error: " . json_encode($error_details));
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => $error_details
    ]);
}
?>