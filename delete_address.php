<?php
// delete_address.php
session_start();
require_once 'Database/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address_id'])) {
    $address_id = intval($_POST['address_id']);
    $user_id = $_SESSION['user_id'];
    
    // Get customer_id
    $customer_query = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
    $customer_query->bind_param("i", $user_id);
    $customer_query->execute();
    $customer_result = $customer_query->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer_id = $customer_result->fetch_assoc()['customer_id'];
        
        // Verify the address belongs to this customer
        $verify_query = $conn->prepare("SELECT address_id FROM shipping_addresses WHERE address_id = ? AND customer_id = ?");
        $verify_query->bind_param("ii", $address_id, $customer_id);
        $verify_query->execute();
        $verify_result = $verify_query->get_result();
        
        if ($verify_result->num_rows > 0) {
            // Delete the address
            $delete_query = $conn->prepare("DELETE FROM shipping_addresses WHERE address_id = ?");
            $delete_query->bind_param("i", $address_id);
            
            if ($delete_query->execute()) {
                $_SESSION['success'] = 'Address deleted successfully.';
            } else {
                $_SESSION['error'] = 'Failed to delete address.';
            }
        } else {
            $_SESSION['error'] = 'Address not found or does not belong to you.';
        }
    }
}

header('Location: profile.php#addresses');
exit();
?>