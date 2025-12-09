<?php
ob_start();
include '../../includes/db.php';
ob_end_clean();

header('Content-Type: application/json');

$product_id = (int)($_GET['product_id'] ?? 0);

$unit_cost = '';

if ($product_id > 0) {
    $stmt = $conn->prepare("SELECT cost_price FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->bind_result($unit_cost);
    if ($stmt->fetch()) {
        $unit_cost = $unit_cost !== null ? number_format((float)$unit_cost, 2, '.', '') : '';
    }
    $stmt->close();
}

echo json_encode(['unit_cost' => $unit_cost]);
?>