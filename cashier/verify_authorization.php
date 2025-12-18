<?php
// verify_authorization.php - UPDATED VERSION
// Turn off error display for production, log errors instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');

// Handle preflight for CORS if needed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate JSON input
$input = file_get_contents('php://input');
if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'No input data']);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// Validate required fields
if (!isset($data['employee_number'], $data['password'], $data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$employee_number = trim($data['employee_number']);
$password = $data['password'];
$action = $data['action'];
$reason = isset($data['reason']) ? trim($data['reason']) : '';
$details = isset($data['details']) ? $data['details'] : null;
$requested_by_employee_id = isset($data['requested_by_employee_id']) ? intval($data['requested_by_employee_id']) : 0;
$requested_by_employee_name = isset($data['requested_by_employee_name']) ? trim($data['requested_by_employee_name']) : '';

// Try to include database connection with error handling
try {
   require_once '../database/db.php';
    
    // Check if connection was successful
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Query to check manager/supervisor
    $query = "SELECT e.* FROM employees e 
              WHERE e.employee_number = ? 
              AND e.is_active = 1 
              AND e.position IN ('supervisor', 'manager')";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $employee_number);
    if (!$stmt->execute()) {
        throw new Exception('Database execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Employee not found or insufficient permissions']);
        exit;
    }
    
    $employee = $result->fetch_assoc();
    
    // Verify password
    $passwordValid = password_verify($password, $employee['password_hash']);
    
    // For debugging only - remove in production
    if (!$passwordValid) {
        // Check if password matches directly (if not hashed)
        if ($password === $employee['password_hash']) {
            error_log("Password matches directly (not hashed)");
            $passwordValid = true;
        } else {
            error_log("Password verification failed for: " . $employee_number);
        }
    }
    
    if (!$passwordValid) {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        exit;
    }
    
    // Check permissions
    $hasPermission = false;
    
    switch ($action) {
        case 'VOID TRANSACTION':
        case 'CHANGE PRICE':
        case 'APPLY CUSTOM DISCOUNT':
        case 'AUTHORIZATION REQUIRED FOR SPECIAL CHANGES':
            $hasPermission = in_array($employee['position'], ['supervisor', 'manager']);
            break;
        
        default:
            $hasPermission = $employee['position'] === 'manager';
    }
    
    if (!$hasPermission) {
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions for this action']);
        exit;
    }
    
    // Log the authorization to authorization_logs table
    $log_query = "INSERT INTO authorization_logs 
                  (action, authorized_by, position, reason, details, 
                   employee_id, employee_name, timestamp) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_query);
    
    if ($log_stmt) {
        // Prepare details as JSON if it's an array/object
        $details_json = null;
        if ($details !== null) {
            $details_json = is_array($details) || is_object($details) ? 
                          json_encode($details) : $details;
        }
        
        $log_stmt->bind_param(
            "sssssis", 
            $action,
            $employee['employee_number'],
            $employee['position'],
            $reason,
            $details_json,
            $requested_by_employee_id,
            $requested_by_employee_name
        );
        
        if (!$log_stmt->execute()) {
            error_log("Failed to log authorization: " . $log_stmt->error);
        }
        
        $log_id = $log_stmt->insert_id;
        $log_stmt->close();
    }
    
    // Log to activity_logs as well (optional, for backward compatibility)
    $activity_query = "INSERT INTO activity_logs (employee_id, action, description) 
                      VALUES (?, 'authorization', ?)";
    $activity_stmt = $conn->prepare($activity_query);
    if ($activity_stmt) {
        $log_desc = "Authorization for: $action - Employee: " . $employee['first_name'] . ' ' . $employee['last_name'];
        if ($reason) {
            $log_desc .= " - Reason: " . $reason;
        }
        $activity_stmt->bind_param("is", $employee['employee_id'], $log_desc);
        $activity_stmt->execute();
        $activity_stmt->close();
    }
    
    // Return success with log ID
    echo json_encode([
        'success' => true,
        'employee_id' => $employee['employee_id'],
        'employee_name' => $employee['first_name'] . ' ' . $employee['last_name'],
        'employee_number' => $employee['employee_number'],
        'position' => $employee['position'],
        'log_id' => isset($log_id) ? $log_id : null,
        'message' => 'Authorization successful'
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    // Log error but don't show details to user
    error_log("Authorization error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>