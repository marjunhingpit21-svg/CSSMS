<?php
include '../includes/auth.php';
include '../includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
            $upload_dir = '../../img/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                $image_url = 'img/products/' . $filename;
            }
        }

        // Insert product
        $stmt = $conn->prepare("
            INSERT INTO products (product_name, category_id, gender_id, age_group_id, description, price, cost_price, image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("siissdds", $product_name, $category_id, $gender_id, $age_group_id, $description, $price, $cost_price, $image_url);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $product_id = $conn->insert_id;
        $stmt->close();

        // Handle size variants
        if (isset($_POST['sizes']) && is_array($_POST['sizes']) && count($_POST['sizes']) > 0) {
            $sizes = $_POST['sizes'];
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

            for ($i = 0; $i < count($sizes); $i++) {
                $size_value = trim($sizes[$i]);
                if (empty($size_value)) continue;

                $quantity = (int)($quantities[$i] ?? 0);
                $price_adjustment = (float)($price_adjustments[$i] ?? 0);
                $available = isset($is_available[$i]) ? 1 : 0;

                if ($is_shoe_product) {
                    // Handle shoe sizes - simplified to just match size_us
                    $shoe_size_stmt = $conn->prepare("
                        SELECT shoe_size_id FROM shoe_sizes 
                        WHERE size_us = ?
                        LIMIT 1
                    ");
                    
                    if (!$shoe_size_stmt) {
                        throw new Exception("Prepare shoe_size failed: " . $conn->error);
                    }
                    
                    $size_us_float = (float)$size_value;
                    $shoe_size_stmt->bind_param("d", $size_us_float);
                    $shoe_size_stmt->execute();
                    $shoe_result = $shoe_size_stmt->get_result();
                    
                    if ($shoe_row = $shoe_result->fetch_assoc()) {
                        $shoe_size_id = $shoe_row['shoe_size_id'];
                        
                        $size_insert_stmt = $conn->prepare("
                            INSERT INTO product_sizes (product_id, shoe_size_id, stock_quantity, price_adjustment, is_available)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        if (!$size_insert_stmt) {
                            throw new Exception("Prepare size insert failed: " . $conn->error);
                        }
                        
                        $size_insert_stmt->bind_param("iiidi", $product_id, $shoe_size_id, $quantity, $price_adjustment, $available);
                        
                        if (!$size_insert_stmt->execute()) {
                            throw new Exception("Size insert failed: " . $size_insert_stmt->error);
                        }
                        
                        $size_insert_stmt->close();
                    } else {
                        error_log("Shoe size not found: $size_value for gender: $gender_id, age_group: $age_group_id");
                    }
                    
                    $shoe_size_stmt->close();
                } else {
                    // Handle clothing sizes - simplified query to just match size name
                    $clothing_size_stmt = $conn->prepare("
                        SELECT clothing_size_id FROM clothing_sizes 
                        WHERE size_name = ?
                        LIMIT 1
                    ");
                    
                    if (!$clothing_size_stmt) {
                        throw new Exception("Prepare clothing_size failed: " . $conn->error);
                    }
                    
                    $clothing_size_stmt->bind_param("s", $size_value);
                    $clothing_size_stmt->execute();
                    $clothing_result = $clothing_size_stmt->get_result();
                    
                    if ($clothing_row = $clothing_result->fetch_assoc()) {
                        $clothing_size_id = $clothing_row['clothing_size_id'];
                        
                        $size_insert_stmt = $conn->prepare("
                            INSERT INTO product_sizes (product_id, clothing_size_id, stock_quantity, price_adjustment, is_available)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        if (!$size_insert_stmt) {
                            throw new Exception("Prepare size insert failed: " . $conn->error);
                        }
                        
                        $size_insert_stmt->bind_param("iiidi", $product_id, $clothing_size_id, $quantity, $price_adjustment, $available);
                        
                        if (!$size_insert_stmt->execute()) {
                            throw new Exception("Size insert failed: " . $size_insert_stmt->error);
                        }
                        
                        $size_insert_stmt->close();
                    } else {
                        error_log("Clothing size not found: $size_value for gender: $gender_id, age_group: $age_group_id");
                    }
                    
                    $clothing_size_stmt->close();
                }
            }
        }

        $conn->commit();
        
        // Redirect to product page with success message
        $_SESSION['success'] = 'Product added successfully';
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error adding product: " . $e->getMessage());
        $_SESSION['error'] = 'Error adding product: ' . $e->getMessage();
        header('Location: add_product.php');
        exit;
    }
} else {
    header('Location: add_product.php');
    exit;
}
?>