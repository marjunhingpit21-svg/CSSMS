<?php
include '../../db.php';
$result = $conn->query("SELECT clothing_size_id as id, size_name as name FROM clothing_sizes ORDER BY size_order");
$sizes = [];
while ($row = $result->fetch_assoc()) {
    $sizes[] = $row;
}
header('Content-Type: application/json');
echo json_encode($sizes);
?>