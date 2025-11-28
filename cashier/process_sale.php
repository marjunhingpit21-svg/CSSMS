<?php
session_start();
require_once '../database/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items']);
    exit;
}

try {
    $conn->begin_transaction();

    // Insert sale
    $stmt = $conn->prepare("
        INSERT INTO sales 
        (employee_id, branch_id, total_amount, discount, payment_method, payment_status, sale_date)
        VALUES (?, ?, ?, ?, ?, 'completed', NOW())
    ");
    $stmt->bind_param(
        "iidss",
        $input['employee_id'],
        $input['branch_id'],
        $input['total_amount'],
        $input['discount'],
        $input['payment_method']
    );
    $stmt->execute();
    $sale_id = $conn->insert_id;

    // Insert sale items & reduce stock
    $itemStmt = $conn->prepare("
        INSERT INTO sale_items (sale_id, product_size_id, quantity, unit_price, unit_cost)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stockStmt = $conn->prepare("
        UPDATE product_sizes SET stock_quantity = stock_quantity - ? 
        WHERE product_size_id = ? AND stock_quantity >= ?
    ");

    foreach ($input['items'] as $item) {
        $unit_price = $item['final_price'];
        $unit_cost = $unit_price * 0.6; // adjust if you can pull from products table

        $itemStmt->bind_param("iiidd", $sale_id, $item['product_size_id'], $item['quantity'], $unit_price, $unit_cost);
        $itemStmt->execute();

        $stockStmt->bind_param("iii", $item['quantity'], $item['product_size_id'], $item['quantity']);
        if ($stockStmt->execute() === false || $stockStmt->affected_rows === 0) {
            throw new Exception("Stock update failed for item");
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'sale_id' => $sale_id]);

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Transaction failed']);
}