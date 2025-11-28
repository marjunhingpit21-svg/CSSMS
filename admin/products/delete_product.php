<?php
// delete_products.php
include '../includes/auth.php';
include '../db.php';

if (isset($_GET['ids'])) {
    $ids = array_filter(explode(',', $_GET['ids']), 'is_numeric');
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
    }
}
header('Location: index.php');
exit;