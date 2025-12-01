<?php
include '../../db.php';
$product_id = (int)($_GET['product_id'] ?? 0);
$size_id = (int)($_GET['size_id'] ?? 0);

if ($product_id && $size_id) {
    $stmt = $conn->prepare("
        SELECT unit_cost FROM product_sizes 
        WHERE product_id = ? AND clothing_size_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param("ii", $product_id, $size_id);
    $stmt->execute();
    $stmt->bind_result($cost);
    if ($stmt->fetch()) {
        echo json_encode(['unit_cost' => number_format($cost, 2)]);
    } else {
        echo json_encode(['unit_cost' => '']);
    }
} else {
    echo json_encode(['unit_cost' => '']);
}
?>