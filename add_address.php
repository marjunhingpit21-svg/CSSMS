<?php
// add_address.php
session_start();
require_once 'Database/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Get customer_id
    $customer_query = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
    $customer_query->bind_param("i", $user_id);
    $customer_query->execute();
    $customer_result = $customer_query->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer_id = $customer_result->fetch_assoc()['customer_id'];
        
        // Get form data
        $address_label = trim($_POST['address_label']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $address_line1 = trim($_POST['address_line1']);
        $address_line2 = trim($_POST['address_line2']);
        $city = trim($_POST['city']);
        $province = trim($_POST['province']);
        $postal_code = trim($_POST['postal_code']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validate required fields
        if (empty($address_label) || empty($first_name) || empty($last_name) || 
            empty($phone) || empty($address_line1) || empty($city) || 
            empty($province) || empty($postal_code)) {
            $_SESSION['error'] = 'Please fill in all required fields.';
            header('Location: profile.php#addresses');
            exit();
        }
        
        // If this is set as default, unset other defaults
        if ($is_default) {
            $unset_default = $conn->prepare("UPDATE shipping_addresses SET is_default = 0 WHERE customer_id = ?");
            $unset_default->bind_param("i", $customer_id);
            $unset_default->execute();
        }
        
        // Insert new address
        $insert_query = $conn->prepare("INSERT INTO shipping_addresses 
            (customer_id, address_label, first_name, last_name, phone, address_line1, address_line2, city, province, postal_code, is_default) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_query->bind_param("isssssssssi", 
            $customer_id, $address_label, $first_name, $last_name, $phone, 
            $address_line1, $address_line2, $city, $province, $postal_code, $is_default);
        
        if ($insert_query->execute()) {
            $_SESSION['success'] = 'Address added successfully.';
        } else {
            $_SESSION['error'] = 'Failed to add address.';
        }
    } else {
        $_SESSION['error'] = 'Customer profile not found.';
    }
}

header('Location: profile.php#addresses');
exit();
?>