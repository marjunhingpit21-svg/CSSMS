<?php
// admin/users/save_employee.php
require_once '../includes/db.php';
header('Content-Type: application/json');

// Read raw POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received.']);
    exit;
}

// Sanitize inputs
$first_name = trim($input['first_name'] ?? '');
$last_name  = trim($input['last_name'] ?? '');
$email      = trim($input['email'] ?? '');
$phone      = trim($input['phone'] ?? '');
$position   = $input['position'] ?? '';
$branch_id  = (int)($input['branch_id'] ?? 0);
$salary     = (float)($input['salary'] ?? 0);
$hire_date  = $input['hire_date'] ?? date('Y-m-d');

// === Validation ===
if (empty($first_name) || empty($last_name) || empty($email) || empty($position) || $branch_id <= 0 || $salary <= 0) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// Validate position is allowed in enum
$allowed_positions = ['cashier', 'supervisor', 'manager', 'stock_clerk', 'sales_associate'];
if (!in_array($position, $allowed_positions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid position selected.']);
    exit;
}

// === Generate employee_number: EMP-009, EMP-010, etc. ===
$result = $conn->query("SELECT employee_number FROM employees ORDER BY employee_id DESC LIMIT 1");
$next_num = 1;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (preg_match('/EMP-(\d+)/', $row['employee_number'], $m)) {
        $next_num = (int)$m[1] + 1;
    }
}
$employee_number = sprintf("EMP-%03d", $next_num);  // EMP-009, EMP-010, etc.

// === Generate default password: lowercase full name, no space ===
$default_password = strtolower($first_name . $last_name);  // e.g. juliamercedes
$password_hash = password_hash($default_password, PASSWORD_DEFAULT);

try {
    // Check for duplicate email
    $check = $conn->prepare("SELECT employee_id FROM employees WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'An employee with this email already exists.']);
        $check->close();
        exit;
    }
    $check->close();

    // Insert employee
    $stmt = $conn->prepare("
        INSERT INTO employees (
            employee_number, first_name, last_name, email, phone,
            position, branch_id, hire_date, salary,
            password_hash, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->bind_param(
        "ssssssisds",
        $employee_number,
        $first_name,
        $last_name,
        $email,
        $phone,
        $position,
        $branch_id,
        $hire_date,
        $salary,
        $password_hash
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee added successfully!',
            'employee_number' => $employee_number,
            'default_password' => $default_password
        ]);
    } else {
        throw new Exception($stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Add Employee Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to add employee. Please try again.']);
}

$conn->close();
?>