<?php
include '../includes/db.php';
include '../includes/auth.php';

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

$ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));

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
        // 1. Get image path to delete file
        $img_stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
        $img_stmt->bind_param("i", $product_id);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        
        if ($img_row = $img_result->fetch_assoc()) {
            $image_path = '../../' . $img_row['image_url'];
            if ($img_row['image_url'] && file_exists($image_path)) {
                @unlink($image_path);
            }
        }
        $img_stmt->close();

        // 2. Get all inventory_ids associated with this product
        $inv_stmt = $conn->prepare("SELECT inventory_id FROM inventory WHERE product_id = ?");
        $inv_stmt->bind_param("i", $product_id);
        $inv_stmt->execute();
        $inv_result = $inv_stmt->get_result();
        
        $inventory_ids = [];
        while ($inv_row = $inv_result->fetch_assoc()) {
            $inventory_ids[] = $inv_row['inventory_id'];
        }
        $inv_stmt->close();

        // 3. Delete order_items that reference these inventory_ids
        if (!empty($inventory_ids)) {
            $placeholders = implode(',', array_fill(0, count($inventory_ids), '?'));
            $delete_order_items = $conn->prepare("DELETE FROM order_items WHERE inventory_id IN ($placeholders)");
            
            $types = str_repeat('i', count($inventory_ids));
            $delete_order_items->bind_param($types, ...$inventory_ids);
            $delete_order_items->execute();
            $delete_order_items->close();
        }

        // 4. Delete purchase_items that reference these inventory_ids
        if (!empty($inventory_ids)) {
            $placeholders = implode(',', array_fill(0, count($inventory_ids), '?'));
            $delete_purchase_items = $conn->prepare("DELETE FROM purchase_items WHERE inventory_id IN ($placeholders)");
            
            $types = str_repeat('i', count($inventory_ids));
            $delete_purchase_items->bind_param($types, ...$inventory_ids);
            $delete_purchase_items->execute();
            $delete_purchase_items->close();
        }

        // 5. Delete stock_transactions that reference these inventory_ids
        if (!empty($inventory_ids)) {
            $placeholders = implode(',', array_fill(0, count($inventory_ids), '?'));
            $delete_transactions = $conn->prepare("DELETE FROM stock_transactions WHERE inventory_id IN ($placeholders)");
            
            $types = str_repeat('i', count($inventory_ids));
            $delete_transactions->bind_param($types, ...$inventory_ids);
            $delete_transactions->execute();
            $delete_transactions->close();
        }

        // 6. Get all product_size_ids for this product
        $ps_stmt = $conn->prepare("SELECT product_size_id FROM product_sizes WHERE product_id = ?");
        $ps_stmt->bind_param("i", $product_id);
        $ps_stmt->execute();
        $ps_result = $ps_stmt->get_result();
        
        $product_size_ids = [];
        while ($ps_row = $ps_result->fetch_assoc()) {
            $product_size_ids[] = $ps_row['product_size_id'];
        }
        $ps_stmt->close();

        // 7. Delete sale_items that reference these product_size_ids
        if (!empty($product_size_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_size_ids), '?'));
            $delete_sale_items = $conn->prepare("DELETE FROM sale_items WHERE product_size_id IN ($placeholders)");
            
            $types = str_repeat('i', count($product_size_ids));
            $delete_sale_items->bind_param($types, ...$product_size_ids);
            $delete_sale_items->execute();
            $delete_sale_items->close();
        }

        // 8. Delete product_ratings if they exist for product_size_ids
if (!empty($product_size_ids)) {
    $placeholders = implode(',', array_fill(0, count($product_size_ids), '?'));
    $types = str_repeat('i', count($product_size_ids));
    
    // First get rating_ids
    $check_ratings = $conn->query("SHOW TABLES LIKE 'product_ratings'");
    if ($check_ratings->num_rows > 0) {
        $get_ratings = $conn->prepare("SELECT rating_id FROM product_ratings WHERE product_size_id IN ($placeholders)");
        $get_ratings->bind_param($types, ...$product_size_ids);
        $get_ratings->execute();
        $rating_result = $get_ratings->get_result();
        
        $rating_ids = [];
        while ($rating_row = $rating_result->fetch_assoc()) {
            $rating_ids[] = $rating_row['rating_id'];
        }
        $get_ratings->close();
        
        // Delete rating_images if rating_ids exist
        if (!empty($rating_ids)) {
            $check_rating_images = $conn->query("SHOW TABLES LIKE 'rating_images'");
            if ($check_rating_images->num_rows > 0) {
                $rating_placeholders = implode(',', array_fill(0, count($rating_ids), '?'));
                $rating_types = str_repeat('i', count($rating_ids));
                $delete_rating_images = $conn->prepare("DELETE FROM rating_images WHERE rating_id IN ($rating_placeholders)");
                $delete_rating_images->bind_param($rating_types, ...$rating_ids);
                $delete_rating_images->execute();
                $delete_rating_images->close();
            }
            
            // Delete rating_helpfulness
            $check_helpfulness = $conn->query("SHOW TABLES LIKE 'rating_helpfulness'");
            if ($check_helpfulness->num_rows > 0) {
                $rating_placeholders = implode(',', array_fill(0, count($rating_ids), '?'));
                $rating_types = str_repeat('i', count($rating_ids));
                $delete_helpfulness = $conn->prepare("DELETE FROM rating_helpfulness WHERE rating_id IN ($rating_placeholders)");
                $delete_helpfulness->bind_param($rating_types, ...$rating_ids);
                $delete_helpfulness->execute();
                $delete_helpfulness->close();
            }
        }
        
        // Delete product_ratings
        $delete_ratings = $conn->prepare("DELETE FROM product_ratings WHERE product_size_id IN ($placeholders)");
        $delete_ratings->bind_param($types, ...$product_size_ids);
        $delete_ratings->execute();
        $delete_ratings->close();
    }
}

        // 9. Delete from product_ratings by product_id (if table exists)
        $check_ratings = $conn->query("SHOW TABLES LIKE 'product_ratings'");
        if ($check_ratings->num_rows > 0) {
            $delete_product_ratings = $conn->prepare("DELETE FROM product_ratings WHERE product_id = ?");
            $delete_product_ratings->bind_param("i", $product_id);
            $delete_product_ratings->execute();
            $delete_product_ratings->close();
        }

        // 10. Delete from product_sizes (this will cascade to related tables if ON DELETE CASCADE is set)
        $delete_ps = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
        $delete_ps->bind_param("i", $product_id);
        $delete_ps->execute();
        $delete_ps->close();

        // 11. Delete from inventory (direct product reference)
        $delete_inv = $conn->prepare("DELETE FROM inventory WHERE product_id = ?");
        $delete_inv->bind_param("i", $product_id);
        $delete_inv->execute();
        $delete_inv->close();

        // 12. Finally delete the product itself
        $delete_product = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $delete_product->bind_param("i", $product_id);
        
        if ($delete_product->execute()) {
            $deleted_count++;
        } else {
            $errors[] = "Product ID $product_id could not be deleted: " . $delete_product->error;
        }
        $delete_product->close();
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
    error_log("Product deletion error: " . $e->getMessage());
}

// Restore autocommit
$conn->autocommit(TRUE);

header("Location: index.php");
exit();
?>