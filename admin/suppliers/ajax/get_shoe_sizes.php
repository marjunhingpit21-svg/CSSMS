<?php
include '../../db.php';
$result = $conn->query("SELECT shoe_size_id as id, size_value as name FROM shoe_sizes ORDER BY CAST(size_value AS DECIMAL)");
$sizes = [];
while ($row = $result->fetch_assoc()) {
    $sizes[] = $row;
}
header('Content-Type: application/json');
echo json_encode($sizes);
?>