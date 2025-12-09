<?php
include '../includes/db.php';
include '../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$data = $_POST;
$supplier_id = (int)$data['supplier_id'];
$expected_delivery = $data['expected_delivery'] ?? null;
$actual_delivery = $data['actual_delivery'] ?? null;
$rating = (int)($data['supplier_rating'] ?? 0);
$notes = $data['notes'] ?? '';
$items = $data['items'] ?? [];

if (empty($items)) {
    die(json_encode(['success' => false, 'message' => 'No items received']));
}

$conn->autocommit(FALSE);

try {
    // 1. Create purchase record
    $stmt = $conn->prepare("INSERT INTO purchase_stock 
        (supplier_id, expected_delivery, actual_delivery, rating, notes, total_cost) 
        VALUES (?, ?, ?, ?, ?, 0.00)");
    $stmt->bind_param("issis", $supplier_id, $expected_delivery, $actual_delivery, $rating, $notes);
    $stmt->execute();
    $purchase_id = $conn->insert_id;
    $stmt->close();

    $total_cost = 0.0;

    foreach ($items as $item) {
        $quantity = (int)($item['quantity'] ?? 0);
        $defective = (int)($item['defective'] ?? 0);
        $unit_cost = (float)($item['unit_cost'] ?? 0);
        $good_qty = $quantity - $defective;

        if ($good_qty <= 0) continue;

        $product_id = null;
        $clothing_size_id = !empty($item['size_id']) ? (int)$item['size_id'] : null;

        // === CASE 1: Existing Product ===
        if (!empty($item['product_id']) && $item['product_id'] !== 'new') {
            $product_id = (int)$item['product_id'];
        }
        // === CASE 2: New Product ===
        else if ($item['product_id'] === 'new') {
            $name = trim($item['new_product_name']);
            $category_id = (int)$item['new_category_id'];
            $gender_id = !empty($item['new_gender_id']) ? (int)$item['new_gender_id'] : null;
            $age_group_id = !empty($item['new_age_group_id']) ? (int)$item['new_age_group_id'] : null;
            $selling_price = (float)$item['new_selling_price'];
            $cost_price = (float)$item['new_cost_price'];
            $description = $item['new_description'] ?? '';

            // Handle image upload
            $image_url = null;
            if (!empty($_FILES['items']['name'][$index]['new_product_image'] ?? null)) {
                $file = $_FILES['items']['tmp_name'][$index]['new_product_image'];
                $filename = 'product_' . time() . '_' . basename($_FILES['items']['name'][$index]['new_product_image']);
                $target = "../img/products/" . $filename;
                if (move_uploaded_file($file, $target)) {
                    $image_url = "img/products/" . $filename;
                }
            }

            $stmt = $conn->prepare("INSERT INTO products 
                (product_name, category_id, gender_id, age_group_id, description, price, cost_price, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siiisdss", $name, $category_id, $gender_id, $age_group_id, $description, $selling_price, $cost_price, $image_url);
            $stmt->execute();
            $product_id = $conn->insert_id;
            $stmt->close();
        }

        if (!$product_id) continue;

        // === Ensure product_size entry exists (with barcode if needed) ===
        $barcode = 'ATL-' . strtoupper(substr($item['new_product_name'] ?? $conn->query("SELECT product_name FROM products WHERE product_id=$product_id")->fetch_row()[0], 0, 5)) . '-' . str_pad($product_id, 5, '0', STR_PAD_LEFT) . '-' . str_pad($clothing_size_id, 3, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO product_sizes 
            (barcode, product_id, clothing_size_id, stock_quantity, price_adjustment, last_purchase_cost) 
            VALUES (?, ?, ?, ?, 0.00, ?) 
            ON DUPLICATE KEY UPDATE 
                last_purchase_cost = VALUES(last_purchase_cost)");
        $stmt->bind_param("siiid", $barcode, $product_id, $clothing_size_id, $unit_cost);
        $stmt->execute();
        $product_size_id = $conn->insert_id ?: $conn->query("SELECT product_size_id FROM product_sizes WHERE product_id = $product_id AND clothing_size_id = $clothing_size_id")->fetch_row()[0];
        $stmt->close();

        // === Update inventory (add good quantity) ===
        $stmt = $conn->prepare("INSERT INTO inventory 
            (product_id, size_id, quantity, last_restocked) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
                quantity = quantity + VALUES(quantity), 
                last_restocked = NOW()");
        $stmt->bind_param("iii", $product_id, $clothing_size_id, $good_qty);
        $stmt->execute();
        $inventory_id = $conn->insert_id ?: $conn->query("SELECT inventory_id FROM inventory WHERE product_id = $product_id AND size_id = $clothing_size_id")->fetch_row()[0];
        $stmt->close();

        // === Record purchase item ===
        $subtotal = $quantity * $unit_cost;
        $total_cost += $subtotal;

        $stmt = $conn->prepare("INSERT INTO purchase_items 
            (purchase_id, inventory_id, quantity, unit_cost) 
            VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $purchase_id, $inventory_id, $quantity, $unit_cost);
        $stmt->execute();
        $stmt->close();

        // === Update product_sizes stock ===
        $stmt = $conn->prepare("UPDATE product_sizes 
            SET stock_quantity = stock_quantity + ?,
                last_purchase_cost = ?
            WHERE product_size_id = ?");
        $stmt->bind_param("idi", $good_qty, $unit_cost, $product_size_id);
        $stmt->execute();
        $stmt->close();
    }

    // Update total cost in purchase_stock
    $stmt = $conn->prepare("UPDATE purchase_stock SET total_cost = ? WHERE purchase_id = ?");
    $stmt->bind_param("di", $total_cost, $purchase_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Purchase recorded successfully!', 'purchase_id' => $purchase_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->autocommit(TRUE);
?>