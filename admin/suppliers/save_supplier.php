<?php
include '../includes/auth.php';
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($supplier_name)) {
        die("Supplier name is required.");
    }

    $stmt = $conn->prepare("INSERT INTO suppliers (
        supplier_name, contact_person, email, phone, 
        address
    ) VALUES (?, ?, ?, ?, ?)");

    $stmt->bind_param("sssss", $supplier_name, $contact_person, $email, $phone, 
       $address);

    if ($stmt->execute()) {
        header("Location: suppliers_index.php?success=1");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>