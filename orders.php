<?php
include 'Database/db.php';
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: cart.php');
    exit();
}

// Handle login (for modal)
$login_error = '';
if (isset($_POST['login_submit'])) {
    $email = trim($_POST['login_email'] ?? '');
    $password = $_POST['login_password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $login_error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, email, password_hash, role, is_active FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (!$user['is_active']) {
                $login_error = 'Your account has been deactivated. Please contact support.';
            } elseif (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                
                header('Location: orders.php');
                exit();
            } else {
                $login_error = 'Invalid email or password.';
            }
        } else {
            $login_error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}

// Handle mark as received
if (isset($_POST['mark_received'])) {
    $order_id = intval($_POST['order_id']);
    $user_id = $_SESSION['user_id'];
    
    // Verify the order belongs to the logged in user
    $check_stmt = $conn->prepare("
        SELECT o.order_id 
        FROM orders o 
        INNER JOIN customers c ON o.customer_id = c.customer_id 
        WHERE o.order_id = ? AND c.user_id = ? AND o.status = 'delivered'
    ");
    $check_stmt->bind_param("ii", $order_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update order status to 'received'
        $update_stmt = $conn->prepare("UPDATE orders SET status = 'received' WHERE order_id = ?");
        $update_stmt->bind_param("i", $order_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = 'Order marked as received successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update order status. Please try again.';
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = 'Order not found or not eligible for marking as received.';
    }
    $check_stmt->close();
    
    header('Location: orders.php');
    exit();
}

// Function to create thumbnail
function createThumbnail($source_path, $dest_path, $width, $height) {
    $source_info = getimagesize($source_path);
    $source_type = $source_info[2];
    
    switch ($source_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source_image = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    $source_width = imagesx($source_image);
    $source_height = imagesy($source_image);
    
    // Calculate aspect ratio
    $source_ratio = $source_width / $source_height;
    $thumb_ratio = $width / $height;
    
    if ($source_ratio > $thumb_ratio) {
        // Source is wider
        $new_height = $height;
        $new_width = (int) ($height * $source_ratio);
    } else {
        // Source is taller or equal
        $new_width = $width;
        $new_height = (int) ($width / $source_ratio);
    }
    
    // Create new image with transparent background for PNG/GIF
    $thumb_image = imagecreatetruecolor($width, $height);
    
    // For PNG and GIF, preserve transparency
    if ($source_type == IMAGETYPE_PNG || $source_type == IMAGETYPE_GIF) {
        imagealphablending($thumb_image, false);
        imagesavealpha($thumb_image, true);
        $transparent = imagecolorallocatealpha($thumb_image, 255, 255, 255, 127);
        imagefilledrectangle($thumb_image, 0, 0, $width, $height, $transparent);
    } else {
        // For JPEG, use white background
        $white = imagecolorallocate($thumb_image, 255, 255, 255);
        imagefilledrectangle($thumb_image, 0, 0, $width, $height, $white);
    }
    
    // Resize and center the image
    $x_offset = ($width - $new_width) / 2;
    $y_offset = ($height - $new_height) / 2;
    
    imagecopyresampled(
        $thumb_image, $source_image,
        $x_offset, $y_offset, 0, 0,
        $new_width, $new_height, $source_width, $source_height
    );
    
    // Save the thumbnail
    switch ($source_type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumb_image, $dest_path, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumb_image, $dest_path, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumb_image, $dest_path);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumb_image, $dest_path, 85);
            break;
    }
    
    imagedestroy($source_image);
    imagedestroy($thumb_image);
    
    return true;
}

// Handle submit rating with images
if (isset($_POST['submit_rating'])) {
    // Debug logging
    error_log("Rating submission received for order: " . $_POST['order_id']);
    error_log("POST data keys: " . implode(', ', array_keys($_POST)));
    
    $order_id = intval($_POST['order_id']);
    $user_id = $_SESSION['user_id'];
    
    // Get customer ID
    $customer_stmt = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
    $customer_stmt->bind_param("i", $user_id);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer = $customer_result->fetch_assoc();
        $customer_id = $customer['customer_id'];
        
        // Get order items for this order with proper size information
        $items_stmt = $conn->prepare("
            SELECT 
                oi.order_item_id, 
                oi.order_id, 
                i.product_id,
                ps.product_size_id
            FROM order_items oi
            INNER JOIN inventory i ON oi.inventory_id = i.inventory_id
            LEFT JOIN product_sizes ps ON (
                ps.product_id = i.product_id 
                AND (
                    (ps.clothing_size_id = i.size_id AND i.size_id IS NOT NULL)
                    OR 
                    (ps.shoe_size_id = i.shoe_size_id AND i.shoe_size_id IS NOT NULL)
                )
            )
            WHERE oi.order_id = ?
        ");
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $success_count = 0;
        $total_items = 0;
        
        // Create upload directory if it doesn't exist
        $upload_dir = 'ratingimages/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Create thumbnails directory
        $thumbnail_dir = $upload_dir . 'thumbnails/';
        if (!file_exists($thumbnail_dir)) {
            mkdir($thumbnail_dir, 0777, true);
        }
        
        while ($item = $items_result->fetch_assoc()) {
            $total_items++;
            $order_item_id = $item['order_item_id'];
            $product_id = $item['product_id'];
            $product_size_id = $item['product_size_id']; // May be NULL
            
            error_log("Processing item - order_item_id: {$order_item_id}, product_id: {$product_id}, product_size_id: " . ($product_size_id ?? 'NULL'));
            
            // Check if rating already exists for this order item
            $check_rating_stmt = $conn->prepare("
                SELECT rating_id FROM product_ratings WHERE order_item_id = ?
            ");
            $check_rating_stmt->bind_param("i", $order_item_id);
            $check_rating_stmt->execute();
            $check_rating_result = $check_rating_stmt->get_result();
            
            if ($check_rating_result->num_rows == 0) {
                // Get form field names for this order item
                $rating_key = "rating_{$order_item_id}";
                $review_title_key = "review_title_{$order_item_id}";
                $review_text_key = "review_text_{$order_item_id}";
                $quality_key = "quality_{$order_item_id}";
                $fit_key = "fit_{$order_item_id}";
                $value_key = "value_{$order_item_id}";
                $recommend_key = "recommend_{$order_item_id}";
                
                // Get values from POST
                $rating = isset($_POST[$rating_key]) ? intval($_POST[$rating_key]) : 0;
                $review_title = trim($_POST[$review_title_key] ?? '');
                $review_text = trim($_POST[$review_text_key] ?? '');
                $quality_rating = isset($_POST[$quality_key]) ? intval($_POST[$quality_key]) : 0;
                $fit_rating = isset($_POST[$fit_key]) ? intval($_POST[$fit_key]) : 0;
                $value_rating = isset($_POST[$value_key]) ? intval($_POST[$value_key]) : 0;
                $would_recommend = isset($_POST[$recommend_key]) ? 1 : 0;
                
                error_log("Rating data for order_item_id {$order_item_id}: rating={$rating}, quality={$quality_rating}, fit={$fit_rating}, value={$value_rating}");
                
                // Only process if rating is provided (minimum requirement)
                if ($rating > 0) {
                    // Prepare insert statement - use NULL for product_size_id if not available
                    $insert_stmt = $conn->prepare("
                        INSERT INTO product_ratings (
                            order_id, order_item_id, customer_id, product_id, product_size_id,
                            rating, review_title, review_text, quality_rating, fit_rating,
                            value_rating, would_recommend, verified_purchase, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved')
                    ");
                    
                    $insert_stmt->bind_param(
                        "iiiiisssiiii",
                        $order_id,
                        $order_item_id,
                        $customer_id,
                        $product_id,
                        $product_size_id,
                        $rating,
                        $review_title,
                        $review_text,
                        $quality_rating,
                        $fit_rating,
                        $value_rating,
                        $would_recommend
                    );
                    
                    if ($insert_stmt->execute()) {
                        $rating_id = $insert_stmt->insert_id;
                        $success_count++;
                        error_log("Successfully inserted rating ID: {$rating_id}");
                        
                        // Handle image uploads for this rating
                        $image_input_name = "rating_images_{$order_item_id}";
                        if (isset($_FILES[$image_input_name]) && is_array($_FILES[$image_input_name]['name'])) {
                            $image_count = count($_FILES[$image_input_name]['name']);
                            error_log("Found {$image_count} images for rating");
                            
                            for ($i = 0; $i < $image_count; $i++) {
                                if ($_FILES[$image_input_name]['error'][$i] === UPLOAD_ERR_OK) {
                                    $file_name = $_FILES[$image_input_name]['name'][$i];
                                    $file_tmp = $_FILES[$image_input_name]['tmp_name'][$i];
                                    $file_size = $_FILES[$image_input_name]['size'][$i];
                                    $file_type = $_FILES[$image_input_name]['type'][$i];
                                    
                                    // Validate file
                                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                                    $max_size = 5 * 1024 * 1024; // 5MB
                                    
                                    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                                        // Generate unique filename
                                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                        $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                                        $upload_path = $upload_dir . $unique_name;
                                        
                                        if (move_uploaded_file($file_tmp, $upload_path)) {
                                            // Create thumbnail
                                            $thumbnail_path = $thumbnail_dir . 'thumb_' . $unique_name;
                                            createThumbnail($upload_path, $thumbnail_path, 300, 300);
                                            
                                            // Insert image record into database
                                            $image_stmt = $conn->prepare("
                                                INSERT INTO rating_images 
                                                (rating_id, image_url, thumbnail_url, file_name, file_size, file_type, image_order) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                            ");
                                            $image_url = $upload_path;
                                            $thumbnail_url = $thumbnail_path;
                                            $image_order = $i + 1;
                                            $image_stmt->bind_param(
                                                "isssisi", 
                                                $rating_id, 
                                                $image_url, 
                                                $thumbnail_url,
                                                $file_name,
                                                $file_size,
                                                $file_type,
                                                $image_order
                                            );
                                            
                                            if ($image_stmt->execute()) {
                                                error_log("Successfully saved image: {$file_name}");
                                            } else {
                                                error_log("Failed to save image record: " . $image_stmt->error);
                                            }
                                            $image_stmt->close();
                                        } else {
                                            error_log("Failed to move uploaded file: " . $file_name);
                                        }
                                    } else {
                                        error_log("Invalid file type or size: " . $file_name);
                                    }
                                }
                            }
                        }
                    } else {
                        error_log("Failed to insert rating: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                } else {
                    error_log("Rating is 0 or not set for order_item_id {$order_item_id}");
                }
            } else {
                error_log("Rating already exists for order_item_id {$order_item_id}");
            }
            $check_rating_stmt->close();
        }
        $items_stmt->close();
        
        error_log("Total items: {$total_items}, Success count: {$success_count}");
        
        // UPDATE ORDER STATUS TO COMPLETED
        if ($success_count > 0) {
            // Update order status to 'completed'
            $update_order_stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'completed' 
                WHERE order_id = ? AND customer_id = ? AND status = 'received'
            ");
            $update_order_stmt->bind_param("ii", $order_id, $customer_id);
            
            if ($update_order_stmt->execute()) {
                $_SESSION['success_message'] = "Thank you! Your {$success_count} rating(s) have been submitted successfully. Order marked as completed!";
            } else {
                $_SESSION['success_message'] = "Thank you! Your {$success_count} rating(s) have been submitted.";
                error_log('Could not update order status: ' . $update_order_stmt->error);
            }
            $update_order_stmt->close();
        } else {
            $_SESSION['error_message'] = 'No ratings were submitted. Please make sure to rate at least one product.';
        }
    } else {
        $_SESSION['error_message'] = 'Customer account not found.';
    }
    $customer_stmt->close();
    
    header('Location: orders.php');
    exit();
}

// Get cart count for header
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

// Get orders count for header
$orders_count = 0;

// Fetch orders from database for the logged-in user
$orders = [];
$user_id = $_SESSION['user_id'];

try {
    // First, get or create customer record for the user
    $customer_stmt = $conn->prepare("
        SELECT customer_id FROM customers WHERE user_id = ?
    ");
    $customer_stmt->bind_param("i", $user_id);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer = $customer_result->fetch_assoc();
        $customer_id = $customer['customer_id'];
        
        // Count orders for header (excluding cancelled orders)
        $count_stmt = $conn->prepare("
            SELECT COUNT(*) as order_count 
            FROM orders 
            WHERE customer_id = ? AND status NOT IN ('cancelled')
        ");
        $count_stmt->bind_param("i", $customer_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        $orders_count = $count_data['order_count'];
        $count_stmt->close();
        
        // Fetch orders for this customer
        $orders_stmt = $conn->prepare("
            SELECT 
                o.order_id,
                o.order_date,
                o.total_amount,
                o.status,
                o.subtotal,
                o.tax,
                o.discount,
                o.payment_method,
                o.shipping_address,
                COUNT(oi.order_item_id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.customer_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
        ");
        $orders_stmt->bind_param("i", $customer_id);
        $orders_stmt->execute();
        $orders_result = $orders_stmt->get_result();
        
        while ($order = $orders_result->fetch_assoc()) {
            // Get order items
            $items_stmt = $conn->prepare("
                SELECT 
                    oi.order_item_id,
                    oi.quantity,
                    oi.unit_price,
                    oi.subtotal,
                    p.product_id,
                    p.product_name,
                    p.image_url,
                    c.category_name,
                    COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) as size_display,
                    cs.size_name as clothing_size,
                    ss.size_us as shoe_size
                FROM order_items oi
                INNER JOIN inventory i ON oi.inventory_id = i.inventory_id
                INNER JOIN products p ON i.product_id = p.product_id
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN sizes s ON i.size_id = s.size_id
                LEFT JOIN product_sizes ps ON (p.product_id = ps.product_id AND 
                    (ps.clothing_size_id = s.size_id OR ps.shoe_size_id = i.shoe_size_id))
                LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
                LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
                WHERE oi.order_id = ?
            ");
            $items_stmt->bind_param("i", $order['order_id']);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $order_items = [];
            while ($item = $items_result->fetch_assoc()) {
                $order_items[] = [
                    'order_item_id' => $item['order_item_id'],
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'image_url' => $item['image_url'] ?: 'https://via.placeholder.com/100',
                    'category_name' => $item['category_name'],
                    'size' => $item['size_display'] ?: ($item['clothing_size'] ?: ($item['shoe_size'] ? $item['shoe_size'] . ' US' : 'One Size')),
                    'price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal']
                ];
            }
            $items_stmt->close();
            
            // Map database status to frontend status
            $status_map = [
                'pending' => 'to_ship',
                'processing' => 'to_ship',
                'shipped' => 'to_receive',
                'delivered' => 'to_receive', // Now "delivered" shows in "to_receive" section
                'received' => 'to_rate', // New status that shows in "to_rate" section
                'completed' => 'completed',
                'cancelled' => 'cancelled'
            ];
            
            $frontend_status = $status_map[$order['status']] ?? 'to_ship';
            
            $orders[] = [
                'order_id' => 'TRW-' . $order['order_id'],
                'db_order_id' => $order['order_id'],
                'date' => $order['order_date'],
                'status' => $frontend_status,
                'db_status' => $order['status'],
                'total' => $order['total_amount'],
                'subtotal' => $order['subtotal'],
                'tax' => $order['tax'],
                'discount' => $order['discount'],
                'payment_method' => $order['payment_method'],
                'shipping_address' => $order['shipping_address'],
                'item_count' => $order['item_count'],
                'items' => $order_items
            ];
        }
        $orders_stmt->close();
    }
    $customer_stmt->close();
    
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
}

// Status display names
$status_display = [
    'to_ship' => 'To Ship',
    'to_receive' => 'To Receive',
    'to_rate' => 'To Rate',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

// Status descriptions
$status_description = [
    'to_ship' => 'Your order is being processed and will be shipped soon',
    'to_receive' => 'Your order has been shipped and is on its way',
    'to_rate' => 'Your order has been delivered. Please rate your items',
    'completed' => 'Your order has been completed',
    'cancelled' => 'Your order has been cancelled'
];

// Count orders by status for filter badges
$status_counts = [
    'all' => count($orders),
    'to_ship' => 0,
    'to_receive' => 0,
    'to_rate' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($orders as $order) {
    $status_counts[$order['status']]++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/orders.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Rating Modal Styles */
        .rating-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            overflow-y: auto;
        }

        .rating-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rating-modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rating-modal-header {
            padding: 25px 30px 20px;
            border-bottom: 1px solid #f0f0f0;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 12px 12px 0 0;
        }

        .rating-modal-header h2 {
            font-size: 1.8rem;
            color: #222;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .rating-modal-subtitle {
            color: #666;
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .rating-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #666;
            line-height: 1;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .rating-modal-close:hover {
            color: #e91e63;
        }

        .rating-modal-body {
            padding: 25px 30px;
        }

        .rating-products {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-bottom: 30px;
        }

        .rating-product-item {
            background: #fafafa;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #f0f0f0;
        }

        .product-rating-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .product-rating-image img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-rating-info h4 {
            font-size: 1.1rem;
            color: #222;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .product-rating-variants {
            font-size: 0.9rem;
            color: #666;
        }

        .rating-section {
            margin-bottom: 25px;
        }

        .rating-section-title {
            font-size: 1rem;
            color: #222;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rating-section-title svg {
            color: #e91e63;
        }

        .star-rating {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            cursor: pointer;
            font-size: 2rem;
            color: #ddd;
            transition: color 0.2s ease;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffd700;
        }

        .star-rating input:checked ~ label {
            color: #ffd700;
        }

        .star-rating input:checked + label ~ label {
            color: #ddd;
        }

        .rating-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }

        .rating-label {
            font-size: 0.8rem;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.95rem;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e91e63;
            box-shadow: 0 0 0 2px rgba(233, 30, 99, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Image Upload Styles */
        .image-upload-section {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .image-upload-title {
            font-size: 1rem;
            color: #222;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .image-upload-title svg {
            color: #e91e63;
        }

        .image-upload-container {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }

        .image-upload-container:hover {
            border-color: #e91e63;
            background: #fff9fb;
        }

        .image-upload-container.dragover {
            border-color: #e91e63;
            background: #fff0f5;
        }

        .upload-icon {
            font-size: 3rem;
            color: #e91e63;
            margin-bottom: 10px;
        }

        .upload-text {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .upload-hint {
            font-size: 0.85rem;
            color: #999;
            margin-top: 10px;
        }

        .btn-upload {
            display: inline-block;
            padding: 10px 20px;
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-upload:hover {
            background: #c2185b;
            transform: translateY(-1px);
        }

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .image-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #eee;
        }

        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            color: #f44336;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .image-remove-btn:hover {
            background: white;
            transform: scale(1.1);
        }

        .hidden-file-input {
            display: none;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-size: 0.95rem;
            color: #333;
        }

        .rating-modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            position: sticky;
            bottom: 0;
            background: white;
            border-radius: 0 0 12px 12px;
        }

        .btn-cancel-rating {
            padding: 12px 24px;
            background: #f5f5f5;
            color: #666;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-cancel-rating:hover {
            background: #e0e0e0;
        }

        .btn-submit-rating {
            padding: 12px 24px;
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-submit-rating:hover {
            background: #c2185b;
            transform: translateY(-1px);
        }

        .btn-submit-rating:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Detail Ratings */
        .detail-rating {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }

        .detail-rating-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-rating-label {
            min-width: 120px;
            font-size: 0.9rem;
            color: #666;
        }

        .detail-rating-stars {
            display: flex;
            gap: 3px;
        }

        .detail-rating-stars span {
            font-size: 1.2rem;
            color: #ddd;
        }

        .detail-rating-stars span.filled {
            color: #ffd700;
        }

        /* Required star */
        .required-star {
            color: #f44336;
            font-weight: bold;
            margin-left: 3px;
        }

        /* Rating error */
        .rating-error {
            color: #f44336;
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .rating-modal-content {
                width: 95%;
                margin: 10px;
            }

            .rating-modal-header,
            .rating-modal-body,
            .rating-modal-footer {
                padding: 20px;
            }

            .product-rating-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .rating-modal-footer {
                flex-direction: column;
            }

            .rating-modal-footer button {
                width: 100%;
            }

            .star-rating label {
                font-size: 1.8rem;
            }
            
            .image-preview-container {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
            
            .image-preview-item {
                width: 80px;
                height: 80px;
            }
        }

        @media (max-width: 480px) {
            .rating-modal-header h2 {
                font-size: 1.5rem;
            }

            .product-rating-image img {
                width: 60px;
                height: 60px;
            }

            .star-rating label {
                font-size: 1.5rem;
            }
            
            .image-preview-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" style="position: fixed; top: 20px; right: 20px; z-index: 1000; padding: 15px 20px; background: #4CAF50; color: white; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" style="vertical-align: middle; margin-right: 10px;">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error" style="position: fixed; top: 20px; right: 20px; z-index: 1000; padding: 15px 20px; background: #f44336; color: white; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" style="vertical-align: middle; margin-right: 10px;">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="page-wrapper-orders">
        <div class="orders-container">
            <div class="orders-header">
                <h1>My Orders</h1>
                <p class="orders-subtitle">Track and manage your purchases</p>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <svg width="120" height="120" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h2>No orders yet</h2>
                    <p>Start shopping to see your orders here!</p>
                    <a href="index.php" class="btn-start-shopping">Start Shopping</a>
                </div>
            <?php else: ?>
                <!-- Order Filters -->
                <div class="orders-filters">
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-filter="all">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            All Orders
                            <span class="filter-badge"><?php echo $status_counts['all']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="to_ship">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                            To Ship
                            <span class="filter-badge"><?php echo $status_counts['to_ship']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="to_receive">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            To Receive
                            <span class="filter-badge"><?php echo $status_counts['to_receive']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="to_rate">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            To Rate
                            <span class="filter-badge"><?php echo $status_counts['to_rate']; ?></span>
                        </button>
                        <button class="filter-btn" data-filter="completed">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Completed
                            <span class="filter-badge"><?php echo $status_counts['completed']; ?></span>
                        </button>
                    </div>
                    
                    <!-- Continue Shopping Button -->
                    <div class="orders-actions">
                        <a href="index.php" class="btn-continue-shopping">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Continue Shopping
                        </a>
                    </div>
                </div>

                <div class="orders-content">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card" data-status="<?php echo $order['status']; ?>">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3>Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                                    <div class="order-meta">
                                        <div class="order-meta-item">
                                            <span class="meta-label">Order Date</span>
                                            <span class="meta-value"><?php echo date('M j, Y', strtotime($order['date'])); ?></span>
                                        </div>
                                        <div class="order-meta-item">
                                            <span class="meta-label">Items</span>
                                            <span class="meta-value"><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] !== 1 ? 's' : ''; ?></span>
                                        </div>
                                        <div class="order-meta-item">
                                            <span class="meta-label">Total Amount</span>
                                            <span class="meta-value">₱<?php echo number_format($order['total'], 2); ?></span>
                                        </div>
                                        <div class="order-meta-item">
                                            <span class="meta-label">Payment Method</span>
                                            <span class="meta-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo $status_display[$order['status']]; ?>
                                </div>
                            </div>

                            <!-- Status Timeline (without connecting line) -->
                            <div class="status-timeline">
                                <div class="timeline-step <?php echo in_array($order['status'], ['to_ship', 'to_receive', 'to_rate', 'completed']) ? 'completed' : ''; ?> <?php echo $order['status'] === 'to_ship' ? 'active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Order Placed</span>
                                </div>
                                <div class="timeline-step <?php echo in_array($order['status'], ['to_receive', 'to_rate', 'completed']) ? 'completed' : ''; ?> <?php echo $order['status'] === 'to_receive' ? 'active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                            <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1v-1a1 1 0 011-1h2a1 1 0 011 1v1a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H19a1 1 0 001-1V5a1 1 0 00-1-1H3z"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Shipped</span>
                                </div>
                                <div class="timeline-step <?php echo in_array($order['status'], ['to_rate', 'completed']) ? 'completed' : ''; ?> <?php echo $order['status'] === 'to_rate' ? 'active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Delivered</span>
                                </div>
                                <div class="timeline-step <?php echo $order['status'] === 'completed' ? 'completed active' : ''; ?>">
                                    <div class="timeline-icon">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <span class="timeline-label">Completed</span>
                                </div>
                            </div>

                            <div class="order-items">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 onerror="this.src='https://via.placeholder.com/100'">
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            <p class="item-variants">
                                                <?php if (isset($item['size']) && $item['size'] !== 'One Size'): ?>
                                                    Size: <?php echo htmlspecialchars($item['size']); ?> 
                                                <?php endif; ?>
                                                <?php if (isset($item['category_name'])): ?>
                                                    • Category: <?php echo htmlspecialchars($item['category_name']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                        </div>
                                        <div class="item-quantity">
                                            Qty: <?php echo $item['quantity']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="order-actions">
                                <div class="order-total">
                                    Total: <span>₱<?php echo number_format($order['total'], 2); ?></span>
                                </div>
                                <div class="action-buttons">
                                    <?php if ($order['status'] === 'to_ship'): ?>
                                        <button class="btn-track" onclick="trackOrder(<?php echo $order['db_order_id']; ?>)">Track Order</button>
                                        <button class="btn-cancel" onclick="cancelOrder(<?php echo $order['db_order_id']; ?>)">Cancel Order</button>
                                    <?php elseif ($order['status'] === 'to_receive'): ?>
                                        <?php if ($order['db_status'] === 'delivered'): ?>
                                            <!-- Show "Mark as Received" button for delivered orders -->
                                            <form method="POST" action="orders.php" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['db_order_id']; ?>">
                                                <button type="submit" name="mark_received" class="btn-received">Mark as Received</button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Show "Track Package" for shipped orders -->
                                            <button class="btn-track" onclick="trackOrder(<?php echo $order['db_order_id']; ?>)">Track Package</button>
                                        <?php endif; ?>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">View Details</button>
                                    <?php elseif ($order['status'] === 'to_rate'): ?>
                                        <button class="btn-rate" onclick="openRatingModal(<?php echo $order['db_order_id']; ?>, <?php echo htmlspecialchars(json_encode($order['items'])); ?>)">Rate Products</button>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">Order Details</button>
                                    <?php elseif ($order['status'] === 'cancelled'): ?>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">View Details</button>
                                        <button class="btn-rate" onclick="reorder(<?php echo $order['db_order_id']; ?>)">Reorder</button>
                                    <?php else: ?>
                                        <button class="btn-view" onclick="viewOrderDetails(<?php echo $order['db_order_id']; ?>)">View Details</button>
                                        <button class="btn-rate" onclick="reorder(<?php echo $order['db_order_id']; ?>)">Buy Again</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rating Modal -->
    <div id="ratingModal" class="rating-modal">
        <div class="rating-modal-content">
            <button class="rating-modal-close" onclick="closeRatingModal()">&times;</button>
            <div class="rating-modal-header">
                <h2>Rate Your Products</h2>
                <p class="rating-modal-subtitle">Share your experience to help other shoppers</p>
            </div>
            <form method="POST" action="orders.php" id="ratingForm" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="ratingOrderId">
                <div class="rating-modal-body">
                    <div class="rating-products" id="ratingProductsContainer">
                        <!-- Products will be dynamically inserted here -->
                    </div>
                </div>
                <div class="rating-modal-footer">
                    <button type="button" class="btn-cancel-rating" onclick="closeRatingModal()">Cancel</button>
                    <button type="submit" name="submit_rating" class="btn-submit-rating" id="submitRatingBtn">Submit All Ratings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal <?php echo $login_error ? 'active' : ''; ?>">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
            <h2>Welcome Back</h2>
            <p class="modal-subtitle">Login to your TrendyWear account</p>
            
            <?php if ($login_error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="orders.php" id="loginForm">
                <div class="form-group">
                    <label for="login_email">Email Address</label>
                    <input type="email" id="login_email" name="login_email" placeholder="your.email@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="login_password">Password</label>
                    <input type="password" id="login_password" name="login_password" placeholder="Enter your password" required>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" name="login_submit" class="btn-submit">Login</button>
            </form>
            
            <div class="modal-footer">
                Don't have an account? <span class="switch-modal" onclick="switchModal('loginModal', 'signupModal')">Sign Up</span>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function switchModal(closeId, openId) {
            closeModal(closeId);
            setTimeout(() => openModal(openId), 150);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
            if (event.target.classList.contains('rating-modal')) {
                closeRatingModal();
            }
        }

        // Update cart count and orders count in header
        document.addEventListener('DOMContentLoaded', function () {
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) {
                const count = <?php echo $cart_count; ?>;
                cartBadge.textContent = count;
                cartBadge.style.display = count > 0 ? 'flex' : 'none';
            }

            // Update orders count in header
            const ordersBadge = document.getElementById('orders-count');
            if (ordersBadge) {
                const ordersCount = <?php echo $orders_count; ?>;
                ordersBadge.textContent = ordersCount;
                ordersBadge.style.display = ordersCount > 0 ? 'flex' : 'none';
            }

            // Initialize filter functionality
            initializeFilters();
            initializeStickyFilter();
            
            // Auto-hide success/error messages after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-success, .alert-error');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });

        // Rating Modal Functions
        function openRatingModal(orderId, items) {
            const modal = document.getElementById('ratingModal');
            const orderIdInput = document.getElementById('ratingOrderId');
            const productsContainer = document.getElementById('ratingProductsContainer');
            
            // Set order ID
            orderIdInput.value = orderId;
            
            // Clear previous content
            productsContainer.innerHTML = '';
            
            // Create product rating forms
            items.forEach((item, index) => {
                const productHtml = `
                    <div class="rating-product-item" data-order-item-id="${item.order_item_id}">
                        <div class="product-rating-header">
                            <div class="product-rating-image">
                                <img src="${item.image_url}" alt="${item.product_name}" onerror="this.src='https://via.placeholder.com/100'">
                            </div>
                            <div class="product-rating-info">
                                <h4>${item.product_name}</h4>
                                <p class="product-rating-variants">
                                    ${item.size ? 'Size: ' + item.size : ''}
                                    ${item.category_name ? '• Category: ' + item.category_name : ''}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Overall Rating -->
                        <div class="rating-section">
                            <div class="rating-labels">
                                <span class="rating-label">Poor</span>
                                <span class="rating-label">Fair</span>
                                <span class="rating-label">Good</span>
                                <span class="rating-label">Very Good</span>
                                <span class="rating-label">Excellent</span>
                            </div>
                            <div class="rating-section-title">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                Overall Rating <span class="required-star">*</span>
                            </div>
                            <div class="star-rating">
                                <input type="radio" id="rating_${item.order_item_id}_5" name="rating_${item.order_item_id}" value="5">
                                <label for="rating_${item.order_item_id}_5" data-value="5">★</label>
                                <input type="radio" id="rating_${item.order_item_id}_4" name="rating_${item.order_item_id}" value="4">
                                <label for="rating_${item.order_item_id}_4" data-value="4">★</label>
                                <input type="radio" id="rating_${item.order_item_id}_3" name="rating_${item.order_item_id}" value="3">
                                <label for="rating_${item.order_item_id}_3" data-value="3">★</label>
                                <input type="radio" id="rating_${item.order_item_id}_2" name="rating_${item.order_item_id}" value="2">
                                <label for="rating_${item.order_item_id}_2" data-value="2">★</label>
                                <input type="radio" id="rating_${item.order_item_id}_1" name="rating_${item.order_item_id}" value="1">
                                <label for="rating_${item.order_item_id}_1" data-value="1">★</label>
                            </div>
                            <div class="rating-error" id="rating_error_${item.order_item_id}" style="color: #f44336; font-size: 0.85rem; margin-top: 5px; display: none;">
                                Please select an overall rating
                            </div>
                        </div>
                        
                        <!-- Detailed Ratings -->
                        <div class="detail-ratings">
                            <div class="rating-section">
                                <div class="rating-section-title">Quality</div>
                                <div class="star-rating">
                                    <input type="radio" id="quality_${item.order_item_id}_5" name="quality_${item.order_item_id}" value="5">
                                    <label for="quality_${item.order_item_id}_5" data-value="5">★</label>
                                    <input type="radio" id="quality_${item.order_item_id}_4" name="quality_${item.order_item_id}" value="4">
                                    <label for="quality_${item.order_item_id}_4" data-value="4">★</label>
                                    <input type="radio" id="quality_${item.order_item_id}_3" name="quality_${item.order_item_id}" value="3">
                                    <label for="quality_${item.order_item_id}_3" data-value="3">★</label>
                                    <input type="radio" id="quality_${item.order_item_id}_2" name="quality_${item.order_item_id}" value="2">
                                    <label for="quality_${item.order_item_id}_2" data-value="2">★</label>
                                    <input type="radio" id="quality_${item.order_item_id}_1" name="quality_${item.order_item_id}" value="1">
                                    <label for="quality_${item.order_item_id}_1" data-value="1">★</label>
                                </div>
                            </div>
                            
                            <div class="rating-section">
                                <div class="rating-section-title">Fit (if applicable)</div>
                                <div class="star-rating">
                                    <input type="radio" id="fit_${item.order_item_id}_5" name="fit_${item.order_item_id}" value="5">
                                    <label for="fit_${item.order_item_id}_5" data-value="5">★</label>
                                    <input type="radio" id="fit_${item.order_item_id}_4" name="fit_${item.order_item_id}" value="4">
                                    <label for="fit_${item.order_item_id}_4" data-value="4">★</label>
                                    <input type="radio" id="fit_${item.order_item_id}_3" name="fit_${item.order_item_id}" value="3">
                                    <label for="fit_${item.order_item_id}_3" data-value="3">★</label>
                                    <input type="radio" id="fit_${item.order_item_id}_2" name="fit_${item.order_item_id}" value="2">
                                    <label for="fit_${item.order_item_id}_2" data-value="2">★</label>
                                    <input type="radio" id="fit_${item.order_item_id}_1" name="fit_${item.order_item_id}" value="1">
                                    <label for="fit_${item.order_item_id}_1" data-value="1">★</label>
                                </div>
                            </div>
                            
                            <div class="rating-section">
                                <div class="rating-section-title">Value for Money</div>
                                <div class="star-rating">
                                    <input type="radio" id="value_${item.order_item_id}_5" name="value_${item.order_item_id}" value="5">
                                    <label for="value_${item.order_item_id}_5" data-value="5">★</label>
                                    <input type="radio" id="value_${item.order_item_id}_4" name="value_${item.order_item_id}" value="4">
                                    <label for="value_${item.order_item_id}_4" data-value="4">★</label>
                                    <input type="radio" id="value_${item.order_item_id}_3" name="value_${item.order_item_id}" value="3">
                                    <label for="value_${item.order_item_id}_3" data-value="3">★</label>
                                    <input type="radio" id="value_${item.order_item_id}_2" name="value_${item.order_item_id}" value="2">
                                    <label for="value_${item.order_item_id}_2" data-value="2">★</label>
                                    <input type="radio" id="value_${item.order_item_id}_1" name="value_${item.order_item_id}" value="1">
                                    <label for="value_${item.order_item_id}_1" data-value="1">★</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Image Upload Section -->
                        <div class="image-upload-section">
                            <div class="image-upload-title">
                                <svg width="18" height="18" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                </svg>
                                Upload Photos (Optional)
                            </div>
                            <div class="image-upload-container" id="uploadContainer_${item.order_item_id}" 
                                 ondragover="handleDragOver(event)" 
                                 ondragleave="handleDragLeave(event)" 
                                 ondrop="handleDrop(event, ${item.order_item_id})">
                                <div class="upload-icon">
                                    <svg width="48" height="48" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <p class="upload-text">Drag & drop photos here or click to browse</p>
                                <button type="button" class="btn-upload" onclick="document.getElementById('imageInput_${item.order_item_id}').click()">
                                    Browse Files
                                </button>
                                <p class="upload-hint">Max 5 images • JPG, PNG, GIF, WebP • Max 5MB each</p>
                                <input type="file" 
                                       id="imageInput_${item.order_item_id}" 
                                       class="hidden-file-input" 
                                       name="rating_images_${item.order_item_id}[]" 
                                       multiple 
                                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                       onchange="handleImageSelect(event, ${item.order_item_id})">
                            </div>
                            <div class="image-preview-container" id="imagePreview_${item.order_item_id}">
                                <!-- Image previews will be added here -->
                            </div>
                        </div>
                        
                        <!-- Review Title & Text -->
                        <div class="form-group">
                            <label for="review_title_${item.order_item_id}">Review Title (Optional)</label>
                            <input type="text" id="review_title_${item.order_item_id}" name="review_title_${item.order_item_id}" placeholder="Summarize your experience">
                        </div>
                        
                        <div class="form-group">
                            <label for="review_text_${item.order_item_id}">Detailed Review (Optional)</label>
                            <textarea id="review_text_${item.order_item_id}" name="review_text_${item.order_item_id}" placeholder="Share details about your experience with this product..."></textarea>
                        </div>
                        
                        <!-- Would Recommend -->
                        <div class="checkbox-group">
                            <input type="checkbox" id="recommend_${item.order_item_id}" name="recommend_${item.order_item_id}" value="1" checked>
                            <label for="recommend_${item.order_item_id}">I would recommend this product</label>
                        </div>
                    </div>
                `;
                productsContainer.innerHTML += productHtml;
            });
            
            // Initialize star rating interactions
            initializeStarRatings();
            
            // Add form validation - REMOVED THE PREVENT DEFAULT THAT WAS BLOCKING SUBMISSION
            document.getElementById('ratingForm').addEventListener('submit', function(e) {
                const hasRating = checkRatingsCompletion();
                if (!hasRating) {
                    e.preventDefault();
                    alert('Please select an overall rating for at least one product.');
                    return false;
                }
                return true;
            });
            
            // Show modal
            modal.classList.add('active');
            
            // Enable submit button initially
            document.getElementById('submitRatingBtn').disabled = true;
        }

        function closeRatingModal() {
            const modal = document.getElementById('ratingModal');
            modal.classList.remove('active');
        }

        // Image Upload Functions
        function handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            e.target.closest('.image-upload-container').classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            e.target.closest('.image-upload-container').classList.remove('dragover');
        }

        function handleDrop(e, orderItemId) {
            e.preventDefault();
            e.stopPropagation();
            e.target.closest('.image-upload-container').classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            handleImageFiles(files, orderItemId);
        }

        function handleImageSelect(e, orderItemId) {
            const files = e.target.files;
            handleImageFiles(files, orderItemId);
        }

        function handleImageFiles(files, orderItemId) {
            const previewContainer = document.getElementById(`imagePreview_${orderItemId}`);
            const maxImages = 5;
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            
            // Count existing images
            const existingCount = previewContainer.querySelectorAll('.image-preview-item').length;
            
            // Process each file
            Array.from(files).forEach((file, index) => {
                if (existingCount + index >= maxImages) {
                    alert(`Maximum ${maxImages} images allowed per product.`);
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    alert(`File "${file.name}" is not a valid image type. Please upload JPG, PNG, GIF, or WebP.`);
                    return;
                }
                
                if (file.size > maxSize) {
                    alert(`File "${file.name}" is too large. Maximum size is 5MB.`);
                    return;
                }
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'image-preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="image-remove-btn" onclick="removeImage(this, ${orderItemId})">×</button>
                    `;
                    previewContainer.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
            
            // Reset file input to allow uploading same file again
            document.getElementById(`imageInput_${orderItemId}`).value = '';
        }

        function removeImage(button, orderItemId) {
            const previewItem = button.closest('.image-preview-item');
            previewItem.remove();
        }

        function initializeStarRatings() {
            // Add click events to all star ratings
            document.querySelectorAll('.star-rating').forEach(ratingContainer => {
                const inputs = ratingContainer.querySelectorAll('input[type="radio"]');
                const labels = ratingContainer.querySelectorAll('label[data-value]');
                
                // Add click event to labels
                labels.forEach(label => {
                    label.addEventListener('click', function() {
                        const inputId = this.getAttribute('for');
                        const input = document.getElementById(inputId);
                        const value = this.getAttribute('data-value');
                        
                        // Check the corresponding radio button
                        if (input) {
                            input.checked = true;
                            
                            // Update visual state for all labels in this group
                            const name = input.getAttribute('name');
                            const allInputs = document.querySelectorAll(`input[name="${name}"]`);
                            allInputs.forEach((inp, index) => {
                                const labelForInput = document.querySelector(`label[for="${inp.id}"]`);
                                if (labelForInput) {
                                    if (parseInt(inp.value) <= parseInt(value)) {
                                        labelForInput.style.color = '#ffd700';
                                    } else {
                                        labelForInput.style.color = '#ddd';
                                    }
                                }
                            });
                            
                            // Check if this is an overall rating and update submit button
                            if (name && name.startsWith('rating_')) {
                                checkRatingsCompletion();
                            }
                        }
                    });
                });
                
                // Also handle direct input changes
                inputs.forEach(input => {
                    input.addEventListener('change', function() {
                        const value = this.value;
                        const name = this.getAttribute('name');
                        
                        // Update visual state for all labels in this group
                        const allInputs = document.querySelectorAll(`input[name="${name}"]`);
                        allInputs.forEach((inp, index) => {
                            const labelForInput = document.querySelector(`label[for="${inp.id}"]`);
                            if (labelForInput) {
                                if (parseInt(inp.value) <= parseInt(value)) {
                                    labelForInput.style.color = '#ffd700';
                                } else {
                                    labelForInput.style.color = '#ddd';
                                }
                            }
                        });
                        
                        // Check if this is an overall rating and update submit button
                        if (name && name.startsWith('rating_')) {
                            checkRatingsCompletion();
                        }
                    });
                });
                
                // Initialize colors for any pre-checked ratings (if any)
                const checkedInput = ratingContainer.querySelector('input[type="radio"]:checked');
                if (checkedInput) {
                    const value = checkedInput.value;
                    const name = checkedInput.getAttribute('name');
                    const allInputs = document.querySelectorAll(`input[name="${name}"]`);
                    allInputs.forEach((inp, index) => {
                        const labelForInput = document.querySelector(`label[for="${inp.id}"]`);
                        if (labelForInput) {
                            if (parseInt(inp.value) <= parseInt(value)) {
                                labelForInput.style.color = '#ffd700';
                            } else {
                                labelForInput.style.color = '#ddd';
                            }
                        }
                    });
                }
            });
            
            // Check initial state
            checkRatingsCompletion();
        }

        function checkRatingsCompletion() {
            const submitBtn = document.getElementById('submitRatingBtn');
            let hasRating = false;
            
            // Check each product for overall rating
            document.querySelectorAll('.rating-product-item').forEach(product => {
                const orderItemId = product.getAttribute('data-order-item-id');
                const overallRating = product.querySelector(`input[name="rating_${orderItemId}"]:checked`);
                const errorElement = document.getElementById(`rating_error_${orderItemId}`);
                
                if (overallRating && parseInt(overallRating.value) > 0) {
                    hasRating = true;
                    if (errorElement) {
                        errorElement.style.display = 'none';
                    }
                } else {
                    if (errorElement) {
                        errorElement.style.display = 'block';
                    }
                }
            });
            
            submitBtn.disabled = !hasRating;
            return hasRating;
        }

        // Order action functions
        function trackOrder(orderId) {
            alert('Tracking order #' + orderId + '\nThis feature will be implemented soon!');
        }

        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel order #' + orderId + '?')) {
                alert('Order cancellation request sent for order #' + orderId);
                // In a real application, you would make an AJAX call here
            }
        }

        function viewOrderDetails(orderId) {
            alert('Viewing details for order #' + orderId + '\nThis feature will be implemented soon!');
        }

        function reorder(orderId) {
            alert('Reordering items from order #' + orderId + '\nThis feature will be implemented soon!');
        }

        // Filter functionality
        function initializeFilters() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const orderCards = document.querySelectorAll('.order-card');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');

                    // Show/hide orders based on filter
                    orderCards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-status') === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        }

        // Add interactivity to order cards
        document.querySelectorAll('.order-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Prevent navigation if clicking on buttons
                if (!e.target.closest('.action-buttons')) {
                    // You can add functionality to expand order details here
                    console.log('Order card clicked');
                }
            });
        });

        // Sticky filter functionality
        function initializeStickyFilter() {
            const filters = document.querySelector('.orders-filters');
            if (!filters) return;

            const observer = new IntersectionObserver(
                ([e]) => {
                    if (e.intersectionRatio < 1) {
                        filters.classList.add('sticky');
                    } else {
                        filters.classList.remove('sticky');
                    }
                },
                { threshold: [1], rootMargin: '-90px 0px 0px 0px' }
            );

            observer.observe(filters);
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>