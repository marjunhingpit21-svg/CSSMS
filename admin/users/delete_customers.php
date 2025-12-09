<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$customerIds = $data['customer_ids'] ?? [];

if (empty($customerIds)) {
    echo json_encode(['success' => false, 'message' => 'No customers selected']);
    exit;
}

// Sanitize IDs
$customerIds = array_map('intval', $customerIds);
$placeholders = implode(',', array_fill(0, count($customerIds), '?'));

// Check for pending orders
$checkSql = "
    SELECT c.customer_id, 
           CONCAT(u.username, ' (', u.email, ')') as customer_name,
           COUNT(o.order_id) as pending_orders
    FROM customers c
    LEFT JOIN users u ON c.user_id = u.user_id
    LEFT JOIN orders o ON c.customer_id = o.customer_id 
        AND o.status IN ('pending', 'processing', 'shipped')
    WHERE c.customer_id IN ($placeholders)
    GROUP BY c.customer_id
";

$stmt = $conn->prepare($checkSql);
$stmt->bind_param(str_repeat('i', count($customerIds)), ...$customerIds);
$stmt->execute();
$result = $stmt->get_result();

$cannotDelete = [];
$canDelete = [];

while ($row = $result->fetch_assoc()) {
    if ($row['pending_orders'] > 0) {
        $cannotDelete[] = [
            'id' => $row['customer_id'],
            'name' => $row['customer_name'],
            'pending_orders' => $row['pending_orders']
        ];
    } else {
        $canDelete[] = $row['customer_id'];
    }
}

// If some customers cannot be deleted
if (!empty($cannotDelete)) {
    echo json_encode([
        'success' => false,
        'message' => 'Some customers have pending orders',
        'cannot_delete' => $cannotDelete,
        'can_delete' => $canDelete
    ]);
    exit;
}

// All customers can be deleted - proceed with deletion
$conn->begin_transaction();

try {
    // Delete customers (this will cascade to related tables due to foreign keys)
    $deleteSql = "DELETE FROM customers WHERE customer_id IN ($placeholders)";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param(str_repeat('i', count($canDelete)), ...$canDelete);
    $deleteStmt->execute();
    
    $deletedCount = $deleteStmt->affected_rows;
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully deleted $deletedCount customer(s)",
        'deleted_count' => $deletedCount
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting customers: ' . $e->getMessage()
    ]);
}

$conn->close();
?>