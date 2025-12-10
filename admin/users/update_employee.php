<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

// Only allow POST + JSON (your edit form uses regular form submit, but we'll support both for flexibility)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Support both JSON (fetch) and regular form submission
    if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Required fields
$required = ['employee_id', 'first_name', 'last_name', 'position', 'branch_id', 'salary'];
foreach ($required as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

$employee_id = (int)$data['employee_id'];
$first_name  = trim($data['first_name']);
$last_name   = trim($data['last_name']);
$email       = trim($data['email'] ?? '');
$phone       = trim($data['phone'] ?? '');
$position    = $data['position'];
$branch_id   = (int)$data['branch_id'];
$salary      = (float)$data['salary'];

// Optional: Basic validation
if ($salary < 0) {
    echo json_encode(['success' => false, 'message' => 'Salary cannot be negative']);
    exit;
}

if ($branch_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch selected']);
    exit;
}

// Update query - now using first_name and last_name separately
$stmt = $conn->prepare("
    UPDATE employees 
    SET 
        first_name = ?,
        last_name = ?,
        email = ?,
        phone = ?,
        position = ?,
        branch_id = ?,
        salary = ?
    WHERE employee_id = ?
");

$stmt->bind_param(
    'sssssidi',
    $first_name,
    $last_name,
    $email,
    $phone,
    $position,
    $branch_id,
    $salary,
    $employee_id
);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made or employee not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>