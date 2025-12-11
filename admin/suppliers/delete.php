<?php
include '../includes/auth.php';
include '../includes/db.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM suppliers WHERE supplier_id = $id");
}
header("Location: index.php?deleted=1");
exit;