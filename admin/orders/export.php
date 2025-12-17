<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /CSSMS/login.php");
    exit();
}

// Get filters
$status = $_GET['status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query
$query = "SELECT o.*, 
                 c.first_name, 
                 c.last_name, 
                 c.email, 
                 c.phone,
                 sa.address_line1,
                 sa.address_line2,
                 sa.city,
                 sa.province,
                 sa.postal_code
          FROM orders o
          LEFT JOIN customers c ON o.customer_id = c.customer_id
          LEFT JOIN shipping_addresses sa ON o.address_id = sa.address_id
          WHERE 1=1";

$params = [];
$types = '';

if ($status !== 'all') {
    $query .= " AND o.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($start_date)) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=orders_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Order ID', 'Order Date', 'Customer Name', 'Email', 'Phone',
    'Address', 'City', 'Province', 'Postal Code',
    'Status', 'Payment Method', 'Subtotal', 'Tax', 'Discount', 'Total Amount'
]);

// Add data rows
foreach ($orders as $order) {
    $address = $order['address_line1'];
    if ($order['address_line2']) {
        $address .= ' ' . $order['address_line2'];
    }
    
    fputcsv($output, [
        $order['order_id'],
        $order['order_date'],
        $order['first_name'] . ' ' . $order['last_name'],
        $order['email'],
        $order['phone'],
        $address,
        $order['city'],
        $order['province'],
        $order['postal_code'],
        $order['status'],
        $order['payment_method'],
        $order['subtotal'],
        $order['tax'],
        $order['discount'],
        $order['total_amount']
    ]);
}

fclose($output);
exit();
