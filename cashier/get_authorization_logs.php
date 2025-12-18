<?php
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    $stmt = $conn->prepare("
        SELECT * FROM authorization_logs 
        WHERE DATE(timestamp) = ?
        ORDER BY timestamp DESC
    ");
    
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'count' => count($logs)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>