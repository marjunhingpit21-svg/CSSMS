<?php

include '../includes/db.php';
include '../includes/auth.php'; // Ensures only logged-in admins can access

// Start session if needed for flash messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if 'ids' parameter exists
if (!isset($_GET['ids']) || empty(trim($_GET['ids']))) {
    $_SESSION['error'] = "No products selected for deletion.";
    header("Location: index.php");
    exit();
}

$ids = array_filter(array_map('intval', explode(',', $_GET['ids']))); // Sanitize: only integers

if (empty($ids)) {
    $_SESSION['error'] = "Invalid product IDs.";
    header("Location: index.php");
    exit();
}

$deleted_count = 0;
$errors = [];

// Begin transaction for data integrity
$conn->autocommit(FALSE);

try {
    foreach ($ids as $product_id) {
        // 1. Get image path to delete file (optional but recommended)
        $img_stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
        $img_stmt->bind_param("i", $product_id);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        
        if ($img_row = $img_result->fetch_assoc()) {
            $image_path = '../../img/products/' . basename($img_row['image_url']);
            if ($img_row['image_url'] && file_exists($image_path)) {
                @unlink($image_path); // Delete image file
            }
        }
        $img_stmt->close();

        // 2. Delete related records in correct order (child tables first)

        // Get all product_size_id entries for this product
        $ps_stmt = $conn->prepare("SELECT product_size_id FROM product_sizes WHERE product_id = ?");
        $ps_stmt->bind_param("i", $product_id);
        $ps_stmt->execute();
        $ps_result = $ps_stmt->get_result();

        while ($ps = $ps_result->fetch_assoc()) {
            $ps_id = $ps['product_size_id'];

            // Delete from inventory (if linked via size)
            $conn->query("DELETE FROM inventory WHERE product_id = $product_id AND size_id IN (SELECT size_id FROM product_sizes WHERE product_size_id = $ps_id)");
        }
        $ps_stmt->close();

        // Delete from product_sizes
        $stmt1 = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
        $stmt1->bind_param("i", $product_id);
        if (!$stmt1->execute()) {
            throw new Exception("Failed to delete product sizes for ID $product_id");
        }
        $stmt1->close();

        // Delete from inventory (direct product reference)
        $stmt2 = $conn->prepare("DELETE FROM inventory WHERE product_id = ?");
        $stmt2->bind_param("i", $product_id);
        if (!$stmt2->execute()) {
            throw new Exception("Failed to delete inventory for ID $product_id");
        }
        $stmt2->close();

        // Finally delete the product itself
        $stmt3 = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt3->bind_param("i", $product_id);
        if ($stmt3->execute()) {
            $deleted_count++;
        } else {
            $errors[] = "Product ID $product_id could not be deleted.";
        }
        $stmt3->close();
    }

    // Commit transaction if all went well
    $conn->commit();

    if ($deleted_count > 0) {
        $_SESSION['success'] = "$deleted_count product(s) deleted successfully.";
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode(" | ", $errors);
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Deletion failed: " . $e->getMessage();
}

// Restore autocommit
$conn->autocommit(TRUE);

header("Location: index.php");
exit();
?>