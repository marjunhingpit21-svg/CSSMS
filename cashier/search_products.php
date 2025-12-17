<?php
// search_products.php - Updated version with barcode search enhancement
require_once '../database/db.php';

// Set JSON header first
header('Content-Type: application/json');

// Enable error logging (not display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$q = trim($_GET['q'] ?? '');
$isBarcode = isset($_GET['barcode']) && $_GET['barcode'] == '1';

// Return empty array if no query
if ($q === '') {
    echo json_encode([]);
    exit();
}

try {
    // Check database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Extract only digits for numeric searches
    $q_digits = preg_replace('/[^0-9]/', '', $q);
    
    // Prepare search patterns
    $search_pattern = "%{$q}%";
    
    // Different query for barcode vs regular search
    if ($isBarcode) {
        // Barcode search - prioritize exact matches
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
                ps.barcode = ?
                OR ps.barcode LIKE ?
              )
            ORDER BY 
                (ps.barcode = ?) DESC,
                p.product_name ASC
            LIMIT 5
        ");

        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }

        // Bind parameters for barcode search
        $stmt->bind_param("sss", 
            $q,                // for exact barcode match
            $search_pattern,   // for barcode LIKE
            $q                 // for exact barcode match (ordering)
        );
    } else {
        // Regular search
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
                CAST(p.product_id AS CHAR) LIKE ?
                OR ps.barcode LIKE ?
                OR p.product_name LIKE ?
              )
            ORDER BY 
                (CAST(p.product_id AS CHAR) = ?) DESC,
                (ps.barcode = ?) DESC,
                p.product_name ASC
            LIMIT 15
        ");

        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }

        // Bind parameters for regular search
        $stmt->bind_param("sssss", 
            $search_pattern,  // for product_id LIKE
            $search_pattern,  // for barcode LIKE
            $search_pattern,  // for product_name LIKE
            $q_digits,        // for exact product_id match
            $q                // for exact barcode match
        );
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Fallback image if empty or add proper path
        if (empty($row['image_url'])) {
            $row['image_url'] = 'https://via.placeholder.com/80x80/e0e0e0/666666?text=' . urlencode(substr($row['product_name'], 0, 3));
        } else {
            // Add ../ prefix if the image path doesn't start with http
            if (!preg_match('/^https?:\/\//', $row['image_url'])) {
                $row['image_url'] = '../' . $row['image_url'];
            }
        }
        $products[] = $row;
    }
    
    $stmt->close();
    
    // Return products array
    echo json_encode(['products' => $products]);
    
} catch (Exception $e) {
    // Log error
    error_log("Search error: " . $e->getMessage());
    
    // Return empty array on error (so the frontend can handle it)
    echo json_encode(['products' => []]);
}