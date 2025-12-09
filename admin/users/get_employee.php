<?php
require_once '../includes/db.php';
$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'employee' => $employee]);