<?php
require_once '../includes/db.php';
$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No IDs']);
    exit;
}

$placeholders = str_repeat('?,', count($ids) - 1) . '?';
$stmt = $conn->prepare("DELETE FROM employees WHERE employee_id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

echo json_encode(['success' => $stmt->execute()]);