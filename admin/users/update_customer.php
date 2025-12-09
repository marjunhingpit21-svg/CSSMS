<?php
require_once '../includes/db.php';
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: ../login.php'); exit; }

if ($_POST) {
    $id = (int)$_POST['customer_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $is_active = (int)$_POST['is_active'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    // Get user_id first
    $stmt = $conn->prepare("SELECT user_id FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];

    if ($user_id) {
        if ($password) {
            $sql = "UPDATE users SET username=?, email=?, password=?, is_active=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $username, $email, $password, $is_active, $user_id);
        } else {
            $sql = "UPDATE users SET username=?, email=?, is_active=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $username, $email, $is_active, $user_id);
        }
        $stmt->execute();
    }

    // Update phone in customers table
    $stmt = $conn->prepare("UPDATE customers SET phone=? WHERE customer_id=?");
    $stmt->bind_param("si", $phone, $id);
    $stmt->execute();

    header("Location: customers_index.php?updated=1");
}
?>