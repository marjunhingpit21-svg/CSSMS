<?php
// ajax/get_product_details.php
require_once '../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    echo json_encode([]);
    exit;
}

$product_id = (int)$_GET['product_id'];

$stmt = $conn->prepare("SELECT age_group_id, gender_id, category_id FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode($row ?: [
    'age_group_id' => null, 
    'gender_id' => null, 
    'category_id' => null
]);
?>