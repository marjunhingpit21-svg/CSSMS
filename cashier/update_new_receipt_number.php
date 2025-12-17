<?php
session_start();
header('Content-Type: application/json');

// Check if session variables exist
if (!isset($_SESSION['current_receipt_number'])) {
    $_SESSION['current_receipt_number'] = 1;
}

if (!isset($_SESSION['receipt_date'])) {
    $_SESSION['receipt_date'] = date('Y-m-d');
}

// Check if it's a new day
$today = date('Y-m-d');
if ($_SESSION['receipt_date'] != $today) {
    // New day, reset to 1
    $_SESSION['receipt_date'] = $today;
    $_SESSION['current_receipt_number'] = 1;
}

// Increment the receipt number
$_SESSION['current_receipt_number']++;

echo json_encode([
    'success' => true,
    'message' => 'Receipt number updated',
    'new_number' => $_SESSION['current_receipt_number']
]);
?>