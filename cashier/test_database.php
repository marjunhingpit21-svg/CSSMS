<?php
// test_database.php - Test your database setup
require_once '../database/db.php';

header('Content-Type: application/json');

try {
    // Test 1: Connection
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    $tests = [];
    $tests[] = ['test' => 'Database Connection', 'status' => 'PASS'];
    
    // Test 2: Check products table
    $result = $conn->query("SELECT COUNT(*) as count FROM products");
    if ($result) {
        $row = $result->fetch_assoc();
        $tests[] = [
            'test' => 'Products Table', 
            'status' => 'PASS', 
            'count' => $row['count']
        ];
    } else {
        $tests[] = [
            'test' => 'Products Table', 
            'status' => 'FAIL', 
            'error' => $conn->error
        ];
    }
    
    // Test 3: Check product_sizes table
    $result = $conn->query("SELECT COUNT(*) as count FROM product_sizes WHERE stock_quantity > 0");
    if ($result) {
        $row = $result->fetch_assoc();
        $tests[] = [
            'test' => 'Product Sizes (In Stock)', 
            'status' => 'PASS', 
            'count' => $row['count']
        ];
    } else {
        $tests[] = [
            'test' => 'Product Sizes', 
            'status' => 'FAIL', 
            'error' => $conn->error
        ];
    }
    
    // Test 4: Sample query (what your search does)
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
        LIMIT 5
    ");
    
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        $sample_products = [];
        while ($row = $result->fetch_assoc()) {
            $sample_products[] = $row;
        }
        $tests[] = [
            'test' => 'Sample Products Query', 
            'status' => 'PASS',
            'count' => count($sample_products),
            'sample' => $sample_products
        ];
    } else {
        $tests[] = [
            'test' => 'Sample Products Query', 
            'status' => 'FAIL',
            'error' => $conn->error
        ];
    }
    
    // Test 5: Check if there are any products with specific IDs
    $result = $conn->query("SELECT product_id, product_name FROM products LIMIT 10");
    if ($result) {
        $product_ids = [];
        while ($row = $result->fetch_assoc()) {
            $product_ids[] = $row;
        }
        $tests[] = [
            'test' => 'Available Product IDs', 
            'status' => 'PASS',
            'products' => $product_ids
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'All tests completed',
        'tests' => $tests
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}