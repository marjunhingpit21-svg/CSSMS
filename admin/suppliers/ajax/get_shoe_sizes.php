<?php
// ajax/get_shoe_sizes.php - FINAL WORKING VERSION
require_once '../../includes/db.php';
header('Content-Type: application/json');

$age_group_id = $_GET['age_group_id'] ?? null;
$gender_id    = $_GET['gender_id']    ?? null;

$sql = "SELECT 
            shoe_size_id AS id,
            CONCAT(size_us, ' US') AS name,
            size_us
        FROM shoe_sizes
        WHERE (age_group_id = ? OR age_group_id IS NULL)
          AND (gender_id = ? OR gender_id IS NULL OR gender_id = 3)
        ORDER BY CAST(size_us AS DECIMAL(6,2)) ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $age_group_id, $gender_id);
$stmt->execute();
$result = $stmt->get_result();

$sizes = [];
while ($row = $result->fetch_assoc()) {
    $sizes[] = [
        'id'   => $row['id'],
        'name' => $row['name']  // e.g., "10.5 US"
    ];
}

echo json_encode($sizes);
?>