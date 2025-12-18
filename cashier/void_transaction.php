<?php
// cashier/void_transaction.php
session_start();

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../database/db.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$sale_id = intval($input['sale_id'] ?? 0);
$void_reason = trim($input['void_reason'] ?? '');
$voided_by = intval($input['voided_by'] ?? $_SESSION['employee_id']);
$authorized_by_id = intval($input['authorized_by'] ?? 0);
$authorized_by_name = trim($input['authorized_by_name'] ?? '');
$authorized_by_position = trim($input['authorized_by_position'] ?? '');
$void_timestamp = trim($input['void_timestamp'] ?? date('Y-m-d H:i:s'));

// Validate input
if ($sale_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit();
}

if (empty($void_reason) || strlen($void_reason) < 10) {
    echo json_encode(['success' => false, 'message' => 'Void reason must be at least 10 characters']);
    exit();
}

if ($authorized_by_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Authorization information missing']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Check if transaction exists and is not already voided
    $check_query = "SELECT sale_id, status, notes FROM sales WHERE sale_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $sale_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $sale = mysqli_fetch_assoc($result);
    
    if (!$sale) {
        throw new Exception('Transaction not found');
    }
    
    if ($sale['status'] === 'voided') {
        throw new Exception('Transaction is already voided');
    }
    
    // Combine with existing notes
    $new_notes = $sale['notes'] ? $sale['notes'] . $void_note : $void_note;

    // Update sales table with void info, authorization details, and notes
    $update_query = "UPDATE sales 
                     SET status = 'voided',
                         void_reason = ?,
                         voided_by = ?,
                         voided_at = NOW(),
                         notes = ?
                     WHERE sale_id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "sisi", $void_reason, $authorized_by_id, $new_notes, $sale_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to void transaction: ' . mysqli_error($conn));
    }
    
    // Get sale items to restore inventory
    $items_query = "SELECT product_size_id, quantity FROM sale_items WHERE sale_id = ?";
    $items_stmt = mysqli_prepare($conn, $items_query);
    mysqli_stmt_bind_param($items_stmt, "i", $sale_id);
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);
    
    // Restore inventory for each item
    while ($item = mysqli_fetch_assoc($items_result)) {
        $restore_query = "UPDATE product_sizes 
                         SET stock_quantity = stock_quantity + ? 
                         WHERE product_size_id = ?";
        $restore_stmt = mysqli_prepare($conn, $restore_query);
        mysqli_stmt_bind_param($restore_stmt, "ii", $item['quantity'], $item['product_size_id']);
        
        if (!mysqli_stmt_execute($restore_stmt)) {
            throw new Exception('Failed to restore inventory');
        }
    }
    
    // Log the void action with authorization details
    $log_query = "INSERT INTO activity_logs (employee_id, action, description, created_at) 
                  VALUES (?, 'void_transaction', ?, NOW())";
    $log_stmt = mysqli_prepare($conn, $log_query);
    $description = "Voided transaction #$sale_id. Reason: $void_reason. Authorized by: $authorized_by_name ($authorized_by_position)";
    mysqli_stmt_bind_param($log_stmt, "is", $voided_by, $description);
    mysqli_stmt_execute($log_stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaction voided successfully',
        'sale_id' => $sale_id,
        'voided_by' => $authorized_by_name,
        'voided_position' => $authorized_by_position
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>