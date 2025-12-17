<?php
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

if ($sale_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit();
}

try {
    // Get sale details
    $saleQuery = "
        SELECT 
            s.sale_id,
            s.sale_number,
            s.sale_date,
            s.subtotal,
            s.tax,
            s.discount,
            s.total_amount,
            s.payment_method,
            s.payment_status,
            s.cash_received,
            s.transaction_reference,
            s.notes,
            s.status,
            s.void_reason,
            s.voided_at,
            CONCAT(e.first_name, ' ', e.last_name) AS cashier_name,
            e.employee_number,
            CASE 
                WHEN c.customer_id IS NOT NULL 
                THEN CONCAT(c.first_name, ' ', c.last_name)
                ELSE 'Walk-in Customer'
            END AS customer_name,
            CONCAT(ve.first_name, ' ', ve.last_name) as voided_by_name,
            CASE 
                WHEN s.payment_method = 'cash' AND s.cash_received IS NOT NULL 
                THEN s.cash_received - s.total_amount 
                ELSE NULL 
            END AS change_amount
        FROM sales s
        JOIN employees e ON s.employee_id = e.employee_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        LEFT JOIN employees ve ON s.voided_by = ve.employee_id
        WHERE s.sale_id = ?
    ";
    
    $stmt = $conn->prepare($saleQuery);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    
    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit();
    }
    
    // Set default status if null
    if (empty($sale['status'])) {
        $sale['status'] = 'completed';
    }
    
    // Get sale items
    $itemsQuery = "
        SELECT 
            si.product_name,
            si.size_display,
            si.quantity,
            si.unit_price,
            si.subtotal,
            si.discount,
            si.total
        FROM sale_items si
        WHERE si.sale_id = ?
        ORDER BY si.sale_item_id
    ";
    
    $itemsStmt = $conn->prepare($itemsQuery);
    $itemsStmt->bind_param("i", $sale_id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $items = [];
    while ($row = $itemsResult->fetch_assoc()) {
        $items[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'sale' => $sale,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>