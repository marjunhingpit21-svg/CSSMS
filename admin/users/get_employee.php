<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        employee_id,
        first_name,
        last_name,
        email,
        phone,
        position,
        branch_id,
        salary
    FROM employees 
    WHERE employee_id = ?
    LIMIT 1
");

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if ($employee) {
    echo json_encode([
        'success' => true,
        'employee' => $employee
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Employee not found'
    ]);
}

$stmt->close();
?>