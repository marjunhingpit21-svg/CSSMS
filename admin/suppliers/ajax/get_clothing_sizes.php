<?php
// ajax/get_clothing_sizes.php
require_once '../../includes/db.php';
header('Content-Type: application/json');

$age_group_id = $_GET['age_group_id'] ?? null;

$sql = "SELECT clothing_size_id AS id, size_name AS name 
        FROM clothing_sizes 
        WHERE 1=1";

$params = [];
$types  = "";

if ($age_group_id !== null && $age_group_id !== '') {
    $sql .= " AND age_group_id = ?";
    $params[] = (int)$age_group_id;
    $types .= "i";
}


$sql .= " ORDER BY clothing_size_id ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$sizes = [];
while ($row = $result->fetch_assoc()) {
    $sizes[] = $row;
}

echo json_encode($sizes);
?>