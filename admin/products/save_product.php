<?php
include '../includes/auth.php';
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Basic product information
        $product_name = $conn->real_escape_string(trim($_POST['product_name']));
        $category_id = (int)$_POST['category_id'];
        $gender_id = !empty($_POST['gender_id']) ? (int)$_POST['gender_id'] : null;
        $age_group_id = !empty($_POST['age_group_id']) ? (int)$_POST['age_group_id'] : null;
        $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        $price = (float)$_POST['price'];
        $cost_price = !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : 0.00;

        // Handle image upload
        $image_url = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                $image_url = 'uploads/products/' . $filename;
            }
        }

        // Insert product - FIXED: Correct number of parameters and types
        $stmt = $conn->prepare("
            INSERT INTO products (product_name, category_id, gender_id, age_group_id, description, price, cost_price, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // FIX: Use correct number of type specifiers (8 for 8 parameters)
        // Types: s=string, i=integer, d=double, and note that NULL values need special handling
        if ($gender_id === null && $age_group_id === null) {
            $stmt->bind_param("siissdds", $product_name, $category_id, $gender_id, $age_group_id, $description, $price, $cost_price, $image_url);
        } else if ($gender_id === null) {
            $stmt->bind_param("siissdds", $product_name, $category_id, $gender_id, $age_group_id, $description, $price, $cost_price, $image_url);
        } else if ($age_group_id === null) {
            $stmt->bind_param("siissdds", $product_name, $category_id, $gender_id, $age_group_id, $description, $price, $cost_price, $image_url);
        } else {
            $stmt->bind_param("siissdds", $product_name, $category_id, $gender_id, $age_group_id, $description, $price, $cost_price, $image_url);
        }
        
        $stmt->execute();
        $product_id = $conn->insert_id;
        $stmt->close();

        // Handle size variants
        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            $sizes = $_POST['sizes'];
            $barcodes = $_POST['barcodes'] ?? [];
            $quantities = $_POST['quantities'] ?? [];
            $price_adjustments = $_POST['price_adjustments'] ?? [];
            $is_available = $_POST['is_available'] ?? [];
            
            // Check if this is a shoe product
            $is_shoe_product = false;
            $category_stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
            $category_stmt->bind_param("i", $category_id);
            $category_stmt->execute();
            $category_result = $category_stmt->get_result();
            if ($category_row = $category_result->fetch_assoc()) {
                $category_name = strtolower($category_row['category_name']);
                $is_shoe_product = (strpos($category_name, 'shoe') !== false || strpos($category_name, 'footwear') !== false);
            }
            $category_stmt->close();

            // Prepare statements based on product type
            if ($is_shoe_product) {
                // Handle shoe sizes
                $shoe_size_stmt = $conn->prepare("
                    SELECT shoe_size_id FROM shoe_sizes 
                    WHERE size_us = ? AND (gender_id = ? OR ? IS NULL) AND (age_group_id = ? OR ? IS NULL)
                    LIMIT 1
                ");
                
                $size_insert_stmt = $conn->prepare("
                    INSERT INTO product_sizes (barcode, product_id, shoe_size_id, stock_quantity, price_adjustment, is_available)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
            } else {
                // Handle clothing sizes
                $clothing_size_stmt = $conn->prepare("SELECT clothing_size_id FROM clothing_sizes WHERE size_name = ?");
                $size_insert_stmt = $conn->prepare("
                    INSERT INTO product_sizes (barcode, product_id, clothing_size_id, stock_quantity, price_adjustment, is_available)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
            }

            for ($i = 0; $i < count($sizes); $i++) {
                $size_value = $sizes[$i];
                if (empty($size_value)) continue;

                $barcode = !empty($barcodes[$i]) ? $barcodes[$i] : generateBarcode($product_id, $size_value, $is_shoe_product);
                $quantity = (int)($quantities[$i] ?? 0);
                $price_adjustment = (float)($price_adjustments[$i] ?? 0);
                $available = isset($is_available[$i]) ? 1 : 0;

                if ($is_shoe_product) {
                    // Handle shoe sizes
                    $shoe_size_stmt->bind_param("diiii", $size_value, $gender_id, $gender_id, $age_group_id, $age_group_id);
                    $shoe_size_stmt->execute();
                    $shoe_result = $shoe_size_stmt->get_result();
                    
                    if ($shoe_row = $shoe_result->fetch_assoc()) {
                        $shoe_size_id = $shoe_row['shoe_size_id'];
                        
                        $size_insert_stmt->bind_param("siiidi", $barcode, $product_id, $shoe_size_id, $quantity, $price_adjustment, $available);
                        $size_insert_stmt->execute();
                    }
                } else {
                    // Handle clothing sizes
                    $clothing_size_stmt->bind_param("s", $size_value);
                    $clothing_size_stmt->execute();
                    $clothing_result = $clothing_size_stmt->get_result();
                    
                    if ($clothing_row = $clothing_result->fetch_assoc()) {
                        $clothing_size_id = $clothing_row['clothing_size_id'];
                        
                        $size_insert_stmt->bind_param("siiidi", $barcode, $product_id, $clothing_size_id, $quantity, $price_adjustment, $available);
                        $size_insert_stmt->execute();
                    }
                }
            }

            // Close prepared statements
            if ($is_shoe_product) {
                $shoe_size_stmt->close();
            } else {
                $clothing_size_stmt->close();
            }
            $size_insert_stmt->close();

        }

        $conn->commit();
        
        // Redirect to product page with success message
        header('Location: index.php?success=Product added successfully');
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error adding product: " . $e->getMessage());
        header('Location: add_product.php?error=Error adding product: ' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: add_product.php');
    exit;
}

function generateBarcode($product_id, $size_value, $is_shoe_product) {
    $prefix = 'ATL';
    $product_code = str_pad($product_id, 4, '0', STR_PAD_LEFT);
    
    if ($is_shoe_product) {
        $size_code = str_replace('.', '', $size_value);
        $type_code = 'SH';
    } else {
        $size_code = substr(strtoupper($size_value), 0, 2);
        $type_code = 'CL';
    }
    
    $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
    
    return $prefix . '-' . $type_code . $product_code . '-' . $size_code . '-' . $random;
}
?>