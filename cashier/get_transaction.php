<?php
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$employee_id = $_SESSION['employee_id'];
$branch_id = $_SESSION['branch_id'];

// Get date filter (default to today)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Build query based on filters
    $query = "
        SELECT 
            s.sale_id,
            s.sale_number,
            s.sale_date,
            s.subtotal,
            s.tax,
            s.discount,
            s.total_amount,
            s.payment_method,
            s.payment_status,
            s.cash_received,
            s.transaction_reference,
            s.notes,
            s.status,
            s.void_reason,
            s.voided_at,
            CONCAT(e.first_name, ' ', e.last_name) AS cashier_name,
            e.employee_number,
            b.branch_name,
            CASE 
                WHEN c.customer_id IS NOT NULL 
                THEN CONCAT(c.first_name, ' ', c.last_name)
                ELSE 'Walk-in Customer'
            END AS customer_name,
            CONCAT(ve.first_name, ' ', ve.last_name) as voided_by_name,
            (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.sale_id) AS item_count,
            (SELECT SUM(quantity) FROM sale_items WHERE sale_id = s.sale_id) AS total_quantity
        FROM sales s
        JOIN employees e ON s.employee_id = e.employee_id
        JOIN branches b ON s.branch_id = b.branch_id
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        LEFT JOIN employees ve ON s.voided_by = ve.employee_id
        WHERE s.branch_id = ?
        AND DATE(s.sale_date) = ?
    ";
    
    $params = [$branch_id, $date];
    $types = "is";
    
    // Add search filter if provided
    if (!empty($search)) {
        $query .= " AND (
            s.sale_number LIKE ? OR 
            CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR
            s.transaction_reference LIKE ?
        )";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }
    
    $query .= " ORDER BY s.sale_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Set default status if null
        if (empty($row['status'])) {
            $row['status'] = 'completed';
        }
        $transactions[] = $row;
    }
    
    // Get daily summary (excluding voided transactions)
    $summaryQuery = "
        SELECT 
            COUNT(*) AS total_transactions,
            SUM(CASE WHEN COALESCE(status, 'completed') != 'voided' THEN total_amount ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN payment_method = 'cash' AND COALESCE(status, 'completed') != 'voided' THEN total_amount ELSE 0 END) AS cash_total,
            SUM(CASE WHEN payment_method = 'card' AND COALESCE(status, 'completed') != 'voided' THEN total_amount ELSE 0 END) AS card_total,
            SUM(CASE WHEN payment_method = 'ewallet' AND COALESCE(status, 'completed') != 'voided' THEN total_amount ELSE 0 END) AS ewallet_total,
            SUM(CASE WHEN COALESCE(status, 'completed') != 'voided' THEN discount ELSE 0 END) AS total_discounts
        FROM sales
        WHERE branch_id = ?
        AND DATE(sale_date) = ?
        AND payment_status = 'completed'
    ";
    
    $summaryStmt = $conn->prepare($summaryQuery);
    $summaryStmt->bind_param("is", $branch_id, $date);
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'summary' => $summary,
        'date' => $date
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>