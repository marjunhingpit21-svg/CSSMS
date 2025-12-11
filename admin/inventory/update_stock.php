<?php
include '../includes/auth.php';
include '../db.php';

$id = (int)$_POST['id'];
$stock = (int)$_POST['stock'];

$stmt = $conn->prepare("UPDATE product_sizes SET stock_quantity = ? WHERE product_size_id = ?");
$stmt->bind_param("ii", $stock, $id);
$stmt->execute();

echo 'success';
?>
