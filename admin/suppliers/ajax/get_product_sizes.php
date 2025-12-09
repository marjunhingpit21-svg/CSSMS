<?php
// ajax/get_product_sizes.php
require_once '../../includes/db.php';
header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    echo json_encode([]);
    exit;
}

$product_id = (int)$_GET['product_id'];

// First, get product details
$stmt = $conn->prepare("
    SELECT p.age_group_id, p.gender_id, c.category_id 
    FROM products p 
    JOIN categories c ON p.category_id = c.category_id 
    WHERE p.product_id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    echo json_encode([]);
    exit;
}

$age_group_id = $product['age_group_id'];
$gender_id = $product['gender_id'];
$is_shoe = ($product['category_id'] == 5); // Shoes category

// Now get existing assigned sizes (if any)
$existing = [];
$stmt2 = $conn->prepare("
    SELECT product_size_id, clothing_size_id, shoe_size_id 
    FROM product_sizes 
    WHERE product_id = ?
");
$stmt2->bind_param("i", $product_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    $existing[] = $row;
}

// Now fetch ALL possible sizes for this age/gender combo
$table = $is_shoe ? 'shoe_sizes' : 'clothing_sizes';
$size_id_field = $is_shoe ? 'shoe_size_id' : 'clothing_size_id';
$size_name_field = $is_shoe ? "CONCAT(size_us, ' US')" : 'size_name';
$order_by = $is_shoe ? 'size_us' : 'size_order';

$sql = "SELECT $size_id_field as id, $size_name_field as name
        FROM $table 
        WHERE (age_group_id = ? OR age_group_id IS NULL)
          AND (gender_id = ? OR gender_id IS NULL OR gender_id = 3)
        ORDER BY $order_by";

$stmt3 = $conn->prepare($sql);
$stmt3->bind_param("ii", $age_group_id, $gender_id);
$stmt3->execute();
$res3 = $stmt3->get_result();

$sizes = [];
while ($row = $res3->fetch_assoc()) {
    $sizes[] = [
        'product_size_id' => null, // will be created later
        'size_name' => $row['name'],
        'new_size_id' => $row['id'], // temporary flag
        'is_new' => true
    ];
}

// Merge with existing ones (mark them properly)
$final = [];
foreach ($sizes as $possible) {
    $found = false;
    foreach ($existing as $ex) {
        if (($is_shoe && $ex['shoe_size_id'] == $possible['new_size_id']) ||
            (!$is_shoe && $ex['clothing_size_id'] == $possible['new_size_id'])) {
            $final[] = [
                'product_size_id' => $ex['product_size_id'],
                'size_name' => $possible['size_name']
            ];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $final[] = [
            'product_size_id' => 'new_' . $possible['new_size_id'],
            'size_name' => $possible['size_name']
        ];
    }
}

echo json_encode($final);
?>