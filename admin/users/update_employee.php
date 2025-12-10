<?php
require_once '../includes/db.php';
$data = json_decode(file_get_contents('php://input'), true);

$stmt = $conn->prepare("UPDATE employees SET full_name=?, email=?, phone=?, position=?, branch_id=?, salary=? WHERE employee_id=?");
$stmt->bind_param('ssssidi', 
    $data['full_name'],
    $data['email'],
    $data['phone'],
    $data['position'],
    $data['branch_id'],
    $data['salary'],
    $data['employee_id']
);

echo json_encode(['success' => $stmt->execute()]);