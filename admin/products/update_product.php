<?php
include '../includes/auth.php';
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$product_id = (int)$_POST['product_id'];
$product_name = trim($_POST['product_name']);
$category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$gender_id = !empty($_POST['gender_id']) ? (int)$_POST['gender_id'] : null;
$age_group_id = !empty($_POST['age_group_id']) ? (int)$_POST['age_group_id'] : null;
$description = trim($_POST['description']);
$price = (float)$_POST['price'];
$cost_price = !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : 0.00;

// Validate required fields
if (empty($product_name) || $price <= 0 || empty($category_id)) {
    die("Error: Missing required fields");
}

// Start transaction
$conn->begin_transaction();

try {
    // Handle image upload if provided
    $image_url = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/products/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'product_' . $product_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                $image_url = 'uploads/products/' . $new_filename;
            }
        }
    }
    
    // Update product
    if ($image_url) {
        $stmt = $conn->prepare("
            UPDATE products 
            SET product_name = ?,
                category_id = ?,
                gender_id = ?,
                age_group_id = ?,
                description = ?,
                price = ?,
                cost_price = ?,
                image_url = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE product_id = ?
        ");
        $stmt->bind_param("siissdisi", 
            $product_name, $category_id, $gender_id, $age_group_id, 
            $description, $price, $cost_price, $image_url, $product_id
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE products 
            SET product_name = ?,
                category_id = ?,
                gender_id = ?,
                age_group_id = ?,
                description = ?,
                price = ?,
                cost_price = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE product_id = ?
        ");
        $stmt->bind_param("siissddi", 
            $product_name, $category_id, $gender_id, $age_group_id, 
            $description, $price, $cost_price, $product_id
        );
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update product: " . $stmt->error);
    }
    
    // Handle size deletions
    if (!empty($_POST['delete_sizes'])) {
        $delete_stmt = $conn->prepare("DELETE FROM product_sizes WHERE product_size_id = ?");
        foreach ($_POST['delete_sizes'] as $size_id) {
            $delete_stmt->bind_param("i", $size_id);
            $delete_stmt->execute();
        }
    }
    
    // Update existing sizes
    if (!empty($_POST['size_ids'])) {
        $update_size_stmt = $conn->prepare("
            UPDATE product_sizes 
            SET barcode = ?,
                stock_quantity = ?,
                price_adjustment = ?,
                is_available = ?
            WHERE product_size_id = ?
        ");
        
        foreach ($_POST['size_ids'] as $index => $size_id) {
            $barcode = $_POST['barcodes'][$index];
            $quantity = (int)$_POST['quantities'][$index];
            $price_adj = (float)$_POST['price_adjustments'][$index];
            $is_available = in_array($size_id, $_POST['is_available'] ?? []) ? 1 : 0;
            
            $update_size_stmt->bind_param("sidii", 
                $barcode, $quantity, $price_adj, $is_available, $size_id
            );
            $update_size_stmt->execute();
        }
    }
    
    // Add new sizes
    if (!empty($_POST['new_sizes'])) {
        // Get clothing sizes mapping
        $size_map_stmt = $conn->query("SELECT size_name, clothing_size_id FROM clothing_sizes");
        $size_map = [];
        while ($row = $size_map_stmt->fetch_assoc()) {
            $size_map[$row['size_name']] = $row['clothing_size_id'];
        }
        
        $insert_size_stmt = $conn->prepare("
            INSERT INTO product_sizes 
            (product_id, clothing_size_id, barcode, stock_quantity, price_adjustment, is_available)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_POST['new_sizes'] as $index => $size_name) {
            if (empty($size_name)) continue;
            
            $clothing_size_id = $size_map[$size_name] ?? null;
            if (!$clothing_size_id) continue;
            
            $barcode = !empty($_POST['new_barcodes'][$index]) 
                ? $_POST['new_barcodes'][$index] 
                : 'ATL-' . strtoupper(substr($product_name, 0, 2)) . sprintf('%04d', $product_id) . '-' . $size_name . '-' . rand(10, 99);
            
            $quantity = (int)$_POST['new_quantities'][$index];
            $price_adj = (float)$_POST['new_price_adjustments'][$index];
            $is_available = isset($_POST['new_is_available'][$index]) ? 1 : 0;
            
            $insert_size_stmt->bind_param("iisidi", 
                $product_id, $clothing_size_id, $barcode, $quantity, $price_adj, $is_available
            );
            $insert_size_stmt->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Redirect to view page
    header("Location: view_product.php?id=$product_id&success=1");
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    die("Error updating product: " . $e->getMessage());
}