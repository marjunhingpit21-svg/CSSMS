<?php
session_start();
require_once '../database/db.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    $stmt = $conn->prepare("
        SELECT timestamp, action, authorized_by, position, employee_name, reason
        FROM authorization_logs 
        WHERE DATE(timestamp) = ?
        ORDER BY timestamp DESC
    ");
    
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="authorization_logs_' . $date . '.csv"');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Timestamp', 'Action', 'Authorized By', 'Position', 'Employee', 'Reason']);
    
    // Add data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['timestamp'],
            $row['action'],
            $row['authorized_by'],
            $row['position'],
            $row['employee_name'],
            $row['reason']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    echo "Error exporting logs: " . $e->getMessage();
}
?>