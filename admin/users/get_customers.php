<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$id = (int)$_GET['id'];

$sql = "SELECT c.customer_id, c.phone, u.username, u.email, u.is_active 
        FROM customers c 
        LEFT JOIN users u ON c.user_id = u.user_id 
        WHERE c.customer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

echo json_encode(['success' => true, 'customer' => $customer]);
?>