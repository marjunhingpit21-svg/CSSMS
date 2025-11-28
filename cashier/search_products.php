<?php
// search_products.php → with better partial matching + image support
require_once '../database/db.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') exit(json_encode([]));

// Extract only digits for numeric searches
$q_digits = preg_replace('/[^0-9]/', '', $q);

$stmt = $conn->prepare("
    SELECT 
        ps.product_size_id,
        p.product_id,
        p.product_name,
        ps.barcode,
        p.image_url,
        (p.price + COALESCE(ps.price_adjustment, 0)) AS final_price,
        ps.stock_quantity,
        COALESCE(cs.size_name, CONCAT(ss.size_us, ' US'), 'One Size') AS size_name
    FROM product_sizes ps
    JOIN products p ON ps.product_id = p.product_id
    LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
    LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
    WHERE ps.stock_quantity > 0
      AND (
        CAST(p.product_id AS CHAR) LIKE CONCAT('%', ?, '%')
        OR ps.barcode LIKE CONCAT('%', ?, '%')
        OR p.product_name LIKE CONCAT('%', ?, '%')
      )
    ORDER BY 
        (p.product_id = ?) DESC,
        (ps.barcode = ?) DESC,
        p.product_name ASC
    LIMIT 15
");

// Use the original query for all searches
$stmt->bind_param("sssss", $q, $q, $q, $q_digits, $q);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    // Fallback image if empty
    if (empty($row['image_url'])) {
        $row['image_url'] = 'https://via.placeholder.com/80x80/e0e0e0/666666?text=' . urlencode(substr($row['product_name'], 0, 3));
    }
    $products[] = $row;
}

header('Content-Type: application/json');
echo json_encode($products);