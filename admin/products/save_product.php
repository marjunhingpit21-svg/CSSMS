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

            // Get clothing size IDs based on size names
            $size_map = [];
            $size_stmt = $conn->prepare("SELECT clothing_size_id, size_name FROM clothing_sizes WHERE size_name = ?");
            
            for ($i = 0; $i < count($sizes); $i++) {
                $size_name = $sizes[$i];
                if (empty($size_name)) continue;

                // Get or create size mapping
                if (!isset($size_map[$size_name])) {
                    $size_stmt->bind_param("s", $size_name);
                    $size_stmt->execute();
                    $result = $size_stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $size_map[$size_name] = $row['clothing_size_id'];
                    } else {
                        // If size doesn't exist, skip this variant
                        continue;
                    }
                }

                if (isset($size_map[$size_name])) {
                    $barcode = !empty($barcodes[$i]) ? $barcodes[$i] : generateBarcode($product_id, $size_name);
                    $quantity = (int)($quantities[$i] ?? 0);
                    $price_adjustment = (float)($price_adjustments[$i] ?? 0);
                    $available = isset($is_available[$i]) ? 1 : 0;

                    $size_stmt2 = $conn->prepare("
                        INSERT INTO product_sizes (barcode, product_id, clothing_size_id, stock_quantity, price_adjustment, is_available)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $size_stmt2->bind_param("siiidi", $barcode, $product_id, $size_map[$size_name], $quantity, $price_adjustment, $available);
                    $size_stmt2->execute();
                    $size_stmt2->close();
                }
            }
            $size_stmt->close();
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

function generateBarcode($product_id, $size_name) {
    $prefix = 'ATL';
    $product_code = str_pad($product_id, 4, '0', STR_PAD_LEFT);
    $size_code = substr(strtoupper($size_name), 0, 2);
    $random = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
    
    return $prefix . '-' . $product_code . '-' . $size_code . '-' . $random;
}
?>