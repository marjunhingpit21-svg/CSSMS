<?php
// edit_address.php (with debugging)
session_start();
require_once 'Database/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer_id
$customer_query = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
$customer_query->bind_param("i", $user_id);
$customer_query->execute();
$customer_result = $customer_query->get_result();

if ($customer_result->num_rows === 0) {
    header('Location: profile.php#addresses');
    exit();
}

$customer_id = $customer_result->fetch_assoc()['customer_id'];

// Handle GET request (fetch address data for AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $address_id = intval($_GET['id']);
    
    $address_query = $conn->prepare("SELECT * FROM shipping_addresses WHERE address_id = ? AND customer_id = ?");
    $address_query->bind_param("ii", $address_id, $customer_id);
    $address_query->execute();
    $address_result = $address_query->get_result();
    
    if ($address_result->num_rows > 0) {
        $address = $address_result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($address);
    } else {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Address not found']);
    }
    exit();
}

// Handle POST request (update address)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address_id'])) {
    $address_id = intval($_POST['address_id']);
    $address_label = trim($_POST['address_label']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address_line1 = trim($_POST['address_line1']);
    $address_line2 = isset($_POST['address_line2']) ? trim($_POST['address_line2']) : '';
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $postal_code = trim($_POST['postal_code']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Debug logging
    error_log("Edit Address - ID: $address_id, Label: $address_label, Customer: $customer_id");
    
    // Validate required fields
    if (empty($address_label) || empty($first_name) || empty($last_name) || 
        empty($phone) || empty($address_line1) || empty($city) || 
        empty($province) || empty($postal_code)) {
        $_SESSION['error'] = 'Please fill in all required fields.';
        header('Location: profile.php#addresses');
        exit();
    }
    
    // Verify the address belongs to this customer
    $verify_query = $conn->prepare("SELECT address_id FROM shipping_addresses WHERE address_id = ? AND customer_id = ?");
    $verify_query->bind_param("ii", $address_id, $customer_id);
    $verify_query->execute();
    $verify_result = $verify_query->get_result();
    
    if ($verify_result->num_rows > 0) {
        // If this is set as default, unset other defaults
        if ($is_default) {
            $unset_default = $conn->prepare("UPDATE shipping_addresses SET is_default = 0 WHERE customer_id = ? AND address_id != ?");
            $unset_default->bind_param("ii", $customer_id, $address_id);
            $unset_default->execute();
        }
        
        // Update address - Fixed bind_param with correct number of 's' characters
        $update_query = $conn->prepare("UPDATE shipping_addresses SET 
            address_label = ?, first_name = ?, last_name = ?, phone = ?, 
            address_line1 = ?, address_line2 = ?, city = ?, province = ?, 
            postal_code = ?, is_default = ? 
            WHERE address_id = ? AND customer_id = ?");
        
        // 10 strings (sssssssss) + 2 integers (ii) = ssssssssssii
        $update_query->bind_param("ssssssssssii", 
            $address_label, $first_name, $last_name, $phone, 
            $address_line1, $address_line2, $city, $province, 
            $postal_code, $is_default, $address_id, $customer_id);
        
        if ($update_query->execute()) {
            $_SESSION['success'] = 'Address updated successfully.';
            error_log("Address updated successfully - ID: $address_id");
        } else {
            $_SESSION['error'] = 'Failed to update address: ' . $conn->error;
            error_log("Failed to update address - ID: $address_id, Error: " . $conn->error);
        }
    } else {
        $_SESSION['error'] = 'Address not found or does not belong to you.';
        error_log("Address verification failed - ID: $address_id, Customer: $customer_id");
    }
    
    header('Location: profile.php#addresses');
    exit();
}

// If neither GET nor POST with address_id, redirect
header('Location: profile.php#addresses');
exit();
?>