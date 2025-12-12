<?php
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

// Get the branch_id from session
$branch_id = $_SESSION['branch_id'] ?? 1;

// Get today's date
$today = date('Y-m-d');

// Get the last sale number for today from this branch
$query = "SELECT sale_number 
          FROM sales 
          WHERE branch_id = ? 
          AND DATE(sale_date) = ? 
          ORDER BY sale_id DESC 
          LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $branch_id, $today);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Extract the number from the sale_number format (e.g., "SALE-00001" -> 1)
    preg_match('/\d+$/', $row['sale_number'], $matches);
    $last_number = isset($matches[0]) ? intval($matches[0]) : 0;
    $next_number = $last_number + 1;
} else {
    // No sales today, start from 1
    $next_number = 1;
}

echo json_encode([
    'success' => true,
    'next_receipt_number' => str_pad($next_number, 5, '0', STR_PAD_LEFT)
]);
?>