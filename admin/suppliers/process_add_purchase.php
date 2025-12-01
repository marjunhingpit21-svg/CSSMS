<?php
include '../includes/auth.php';
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$supplier_id        = (int)$_POST['supplier_id'];
$expected_delivery   = !empty($_POST['expected_delivery']) ? $_POST['expected_delivery'] : null;
$actual_delivery     = !empty($_POST['actual_delivery']) ? $_POST['actual_delivery'] : null;
$supplier_rating    = !empty($_POST['supplier_rating']) ? (int)$_POST['supplier_rating'] : null;
$notes               = trim($_POST['notes'] ?? '');

$items = $_POST['items'] ?? [];

if (empty($items)) {
    die("No items received.");
}

// Handle new products
foreach ($items as &$item) {
    if (!empty($item['new_product_name'])) {
        // Insert new product
        $stmt = $conn->prepare("INSERT INTO products (product_name, category_id) VALUES (?, ?)");
        $stmt->bind_param("si", $item['new_product_name'], $item['new_category_id']);
        $stmt->execute();
        $item['product_id'] = $conn->insert_id;
        $stmt->close();

        // We'll create product_size later when we have size
    }
}

$conn->begin_transaction();

try {
    // 1. Insert header into purchase_stock
    $stmt = $conn->prepare("
        INSERT INTO purchase_stock 
        (supplier_id, total_cost, expected_delivery, actual_delivery, supplier_rating, rating_notes)
        VALUES (?, 0, ?, ?, ?, ?)
    ");
    $temp_total = 0; // we'll update later
    $stmt->bind_param("isssi", $supplier_id, $expected_delivery, $actual_delivery, $supplier_rating, $notes);
    $stmt->execute();
    $purchase_id = $conn->insert_id;
    $stmt->close();

    $total_cost = 0;

    // 2. Insert each item + update stock
    $stmt_item = $conn->prepare("
        INSERT INTO purchase_items 
        (purchase_id, product_size_id, quantity, defective_quantity, unit_cost)
        VALUES (?, ?, ?, ?, ?)
    ");

    

    $stmt_stock = $conn->prepare("
        UPDATE product_sizes 
        SET stock_quantity = stock_quantity + ? 
        WHERE product_size_id = ?
    ");

    foreach ($items as $item) {
        $product_id   = (int)$item['product_id'];
        $size_id      = (int)$item['size_id'];
        $quantity     = (int)$item['quantity'];
        $defective    = (int)($item['defective'] ?? 0);
        $unit_cost    = (float)$item['unit_cost'];

        if ($quantity <= 0 || $unit_cost <= 0) continue;

        // Get or create product_size_id
        $product_size_id = null;

        // First, try to find existing product-size combination
        $ps_stmt = $conn->prepare("
            SELECT product_size_id FROM product_sizes 
            WHERE product_id = ? AND clothing_size_id = ? 
            LIMIT 1
        ");
        $ps_stmt->bind_param("ii", $product_id, $size_id);
        $ps_stmt->execute();
        $ps_stmt->bind_result($product_size_id);
        $ps_stmt->fetch();
        $ps_stmt->close();

        if (!$product_size_id) {
            // No existing record → create new product_size (especially for new products or new size variants)
            $ps_insert = $conn->prepare("
                INSERT INTO product_sizes 
                (product_id, clothing_size_id, unit_cost, stock_quantity, final_price, price_adjustment) 
                VALUES (?, ?, ?, 0, ?, 0)
            ");
            $final_price = $unit_cost * 1.5; // or use your actual pricing logic
            $ps_insert->bind_param("iiid", $product_id, $size_id, $unit_cost, $final_price);
            $ps_insert->execute();
            $product_size_id = $conn->insert_id;
            $ps_insert->close();
        }

        // Insert purchase item
        $good_qty = $quantity - $defective;
        $stmt_item->bind_param("iiiii", $purchase_id, $product_size_id, $quantity, $defective, $unit_cost);
        $stmt_item->execute();

        // Update actual stock (only good units count)
        if ($good_qty > 0) {
            $stmt_stock->bind_param("ii", $good_qty, $product_size_id);
            $stmt_stock->execute();
        }

        $total_cost += $quantity * $unit_cost;
    }

    // 3. Update total_cost in header
    $update_stmt = $conn->prepare("UPDATE purchase_stock SET total_cost = ? WHERE purchase_id = ?");
    $update_stmt->bind_param("di", $total_cost, $purchase_id);
    $update_stmt->execute();

    $conn->commit();

    // Optional: Log to stock_transactions if you want history
    // (you can add later)

    header("Location: supplier_details.php?id=$supplier_id&success=purchase_added");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Error: " . $e->getMessage());
}