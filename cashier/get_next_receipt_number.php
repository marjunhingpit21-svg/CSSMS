<?php
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

try {
    // Set timezone to match your server/location
    date_default_timezone_set('Asia/Manila'); // Or your timezone
    
    // Get today's date in Manila timezone
    $today = date('Y-m-d'); // This should be 2025-12-18
    $today_prefix = date('Ymd'); // This should be 20251218
    
    error_log("Today's date: $today, Prefix: $today_prefix");
    
    // DEBUG: Check what's in the database
    $debug_query = "SELECT sale_number, DATE(sale_date) as sale_date FROM sales ORDER BY sale_id DESC LIMIT 5";
    $debug_result = $conn->query($debug_query);
    $debug_rows = [];
    while ($row = $debug_result->fetch_assoc()) {
        $debug_rows[] = $row;
    }
    error_log("Last 5 sales: " . json_encode($debug_rows));
    
    // Get the highest sale number for today
    $query = "SELECT sale_number 
              FROM sales 
              WHERE DATE(sale_date) = ? 
              ORDER BY sale_id DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        error_log("Found last sale for today: " . $row['sale_number']);
        
        // Extract the number from the sale_number
        $parts = explode('-', $row['sale_number']);
        if (count($parts) === 2) {
            $last_date_prefix = substr($parts[0], 0, 8);
            $last_number = intval($parts[1]);
            
            // Verify date prefix matches today
            if ($last_date_prefix === $today_prefix) {
                $next_number = $last_number + 1;
                error_log("Incrementing from $last_number to $next_number");
            } else {
                // This shouldn't happen, but just in case
                error_log("Date mismatch! Last prefix: $last_date_prefix, Today: $today_prefix");
                $next_number = 1;
            }
        } else {
            error_log("Invalid sale_number format: " . $row['sale_number']);
            $next_number = 1;
        }
    } else {
        // No sales today
        error_log("No sales found for today: $today");
        $next_number = 1;
    }
    
    // Format: YYYYMMDD-XXXX
    $receipt_number = $today_prefix . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
    
    error_log("Returning receipt number: $receipt_number");
    
    echo json_encode([
        'success' => true,
        'next_receipt_number' => $receipt_number,
        'debug' => [
            'today' => $today,
            'today_prefix' => $today_prefix,
            'last_sales' => $debug_rows
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_next_receipt_number.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'next_receipt_number' => date('Ymd') . '-0001',
        'debug_date' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>