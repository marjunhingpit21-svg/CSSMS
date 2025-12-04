<?php
include '../includes/auth.php';
include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: suppliers_index.php");
    exit;
}

$supplier_id = (int)$_POST['supplier_id'];
$supplier_name = trim($_POST['supplier_name']);
$contact_person = trim($_POST['contact_person'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$payment_terms_days = !empty($_POST['payment_terms_days']) ? (int)$_POST['payment_terms_days'] : null;
$minimum_order_value = !empty($_POST['minimum_order_value']) ? (float)$_POST['minimum_order_value'] : null;
$notes = trim($_POST['notes'] ?? '');
$tags = trim($_POST['tags'] ?? '');

if (empty($supplier_name)) {
    die("Supplier name is required.");
}

// Check if supplier exists
$check = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
$check->bind_param("i", $supplier_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    die("Supplier not found.");
}
$check->close();

// Update supplier
$stmt = $conn->prepare("UPDATE suppliers SET 
    supplier_name = ?, 
    contact_person = ?, 
    email = ?, 
    phone = ?, 
    address = ?
    WHERE supplier_id = ?");

$stmt->bind_param("sssssi", 
    $supplier_name, 
    $contact_person, 
    $email, 
    $phone, 
    $address, 
    $supplier_id
);

if ($stmt->execute()) {
    header("Location: suppliers_index.php?updated=1");
    exit;
} else {
    echo "Error updating supplier: " . $stmt->error;
}
?>