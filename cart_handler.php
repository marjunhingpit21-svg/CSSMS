<?php
include 'Database/db.php';
session_start();

header('Content-Type: application/json');

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';
$product_id = intval($_POST['product_id'] ?? 0);

$response = ['success' => false, 'message' => '', 'cart_count' => 0];

switch ($action) {
    case 'add':
        if ($product_id > 0) {
            // Check if product exists
            $stmt = $conn->prepare("SELECT product_id, product_name, stock_quantity FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                $current_qty = $_SESSION['cart'][$product_id] ?? 0;
                
                // Check stock availability
                if ($current_qty < $product['stock_quantity']) {
                    $_SESSION['cart'][$product_id] = $current_qty + 1;
                    $response['success'] = true;
                    $response['message'] = $product['product_name'] . ' added to cart!';
                } else {
                    $response['message'] = 'Sorry, not enough stock available.';
                }
            } else {
                $response['message'] = 'Product not found.';
            }
            $stmt->close();
        }
        break;
        
    case 'update':
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($product_id > 0 && $quantity > 0) {
            // Check stock
            $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                
                if ($quantity <= $product['stock_quantity']) {
                    $_SESSION['cart'][$product_id] = $quantity;
                    $response['success'] = true;
                    $response['message'] = 'Cart updated successfully!';
                } else {
                    $response['message'] = 'Only ' . $product['stock_quantity'] . ' items available.';
                }
            }
            $stmt->close();
        } elseif ($quantity <= 0) {
            // Remove item if quantity is 0 or less
            unset($_SESSION['cart'][$product_id]);
            $response['success'] = true;
            $response['message'] = 'Item removed from cart.';
        }
        break;
        
    case 'remove':
        if ($product_id > 0 && isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            $response['success'] = true;
            $response['message'] = 'Item removed from cart.';
        } else {
            $response['message'] = 'Item not found in cart.';
        }
        break;
        
    case 'get_count':
        $response['success'] = true;
        break;
        
    case 'clear':
        $_SESSION['cart'] = [];
        $response['success'] = true;
        $response['message'] = 'Cart cleared successfully!';
        break;
        
    default:
        $response['message'] = 'Invalid action.';
}

// Calculate cart count
$response['cart_count'] = array_sum($_SESSION['cart']);

// Calculate totals if updating
if ($action === 'update' || $action === 'remove') {
    $total = 0;
    if (!empty($_SESSION['cart'])) {
        $product_ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        
        $stmt = $conn->prepare("SELECT product_id, price FROM products WHERE product_id IN ($placeholders)");
        $types = str_repeat('i', count($product_ids));
        $stmt->bind_param($types, ...$product_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($product = $result->fetch_assoc()) {
            $total += $product['price'] * $_SESSION['cart'][$product['product_id']];
        }
        $stmt->close();
    }
    
    $response['subtotal'] = number_format($total, 2);
    $response['tax'] = number_format($total * 0.1, 2);
    $response['total'] = number_format($total * 1.1, 2);
}

echo json_encode($response);
$conn->close();
?>