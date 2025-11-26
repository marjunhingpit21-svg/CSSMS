<?php
include 'Database/db.php';
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart count for header
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch product details
$stmt = $conn->prepare("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.product_id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

// Fetch related products (same category, exclude current product)
$related_stmt = $conn->prepare("
    SELECT product_id, product_name, price, image_url, stock_quantity 
    FROM products 
    WHERE category_id = ? AND product_id != ? 
    ORDER BY RAND() 
    LIMIT 4
");
$related_stmt->bind_param("ii", $product['category_id'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();
$related_stmt->close();

// Handle add to cart
$message = '';
if (isset($_POST['add_to_cart'])) {
    $quantity = (int)$_POST['quantity'] ?? 1;
    $size = $_POST['size'] ?? 'M';
    
    if ($product['stock_quantity'] > 0) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'stock' => $product['stock_quantity'],
                'size' => $size
            ];
        }
        
        // Limit to stock quantity
        if ($_SESSION['cart'][$product_id]['quantity'] > $product['stock_quantity']) {
            $_SESSION['cart'][$product_id]['quantity'] = $product['stock_quantity'];
        }
        
        $message = 'Added to cart successfully!';
        $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
}

// Determine stock status
$stock = $product['stock_quantity'] ?? 0;
$stock_status = '';
$stock_class = '';

if ($stock > 10) {
    $stock_status = 'In Stock';
    $stock_class = 'in-stock';
} elseif ($stock > 0) {
    $stock_status = 'Only ' . $stock . ' left';
    $stock_class = 'low-stock';
} else {
    $stock_status = 'Out of Stock';
    $stock_class = 'out-of-stock';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
        }

        .page-wrapper {
            padding-top: 80px;
            min-height: 100vh;
        }

        .breadcrumb {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .breadcrumb .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #666;
        }

        .breadcrumb a {
            color: #e91e63;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .product-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            margin-bottom: 60px;
        }

        /* Image Gallery */
        .product-gallery {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .main-image {
            width: 100%;
            height: 600px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            position: relative;
            background: #f8f8f8;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: zoom-in;
            transition: transform 0.3s ease;
        }

        .main-image img:hover {
            transform: scale(1.05);
        }

        .zoom-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }

        .zoom-overlay.active {
            display: flex;
        }

        .zoom-overlay img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }

        .thumbnail-gallery {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .thumbnail {
            width: 100%;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: #e91e63;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Product Info */
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .product-title {
            font-size: 2rem;
            font-weight: 700;
            color: #222;
            line-height: 1.3;
        }

        .product-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .product-category {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            background: #f8f8f8;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #666;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stars {
            color: #ffc107;
            font-size: 1.1rem;
        }

        .rating-count {
            color: #666;
            font-size: 0.9rem;
        }

        .product-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #e91e63;
        }

        .stock-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            width: fit-content;
        }

        .stock-info.in-stock {
            background: #d4edda;
            color: #155724;
        }

        .stock-info.low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .stock-info.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .stock-icon {
            width: 18px;
            height: 18px;
        }

        .product-description {
            line-height: 1.8;
            color: #555;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }

        .product-features {
            list-style: none;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }

        .product-features li {
            padding: 8px 0;
            padding-left: 28px;
            position: relative;
            color: #555;
        }

        .product-features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #e91e63;
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* Size Selection */
        .size-selector {
            padding: 20px 0;
        }

        .size-label {
            display: block;
            font-weight: 600;
            margin-bottom: 12px;
            color: #222;
        }

        .size-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .size-option {
            position: relative;
        }

        .size-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .size-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 50px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .size-option input[type="radio"]:checked + label {
            border-color: #e91e63;
            background: #e91e63;
            color: white;
        }

        .size-option label:hover {
            border-color: #e91e63;
        }

        /* Quantity and Add to Cart */
        .purchase-section {
            display: flex;
            gap: 15px;
            align-items: center;
            padding: 30px 0;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity-selector button {
            width: 45px;
            height: 50px;
            border: none;
            background: #f8f8f8;
            cursor: pointer;
            font-size: 1.2rem;
            color: #333;
            transition: background 0.3s ease;
        }

        .quantity-selector button:hover {
            background: #e91e63;
            color: white;
        }

        .quantity-selector input {
            width: 60px;
            height: 50px;
            border: none;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .btn-add-to-cart {
            flex: 1;
            height: 56px;
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-add-to-cart:hover:not(:disabled) {
            background: #c2185b;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(233, 30, 99, 0.4);
        }

        .btn-add-to-cart:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Success Message */
        .success-message {
            display: none;
            padding: 15px 20px;
            background: #d4edda;
            color: #155724;
            border-radius: 8px;
            margin-top: 20px;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .success-message.show {
            display: flex;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Related Products */
        .related-products {
            margin-top: 60px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #222;
            text-align: center;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }

        .related-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .related-card img {
            width: 100%;
            height: 280px;
            object-fit: cover;
        }

        .related-info {
            padding: 20px;
        }

        .related-name {
            font-weight: 600;
            color: #222;
            margin-bottom: 8px;
        }

        .related-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #e91e63;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .product-detail {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .product-gallery {
                position: relative;
                top: 0;
            }

            .main-image {
                height: 400px;
            }

            .purchase-section {
                flex-direction: column;
            }

            .btn-add-to-cart {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrapper">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <div class="container">
                <a href="index.php">Home</a>
                <span>/</span>
                <a href="index.php?category=<?php echo $product['category_id']; ?>">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </a>
                <span>/</span>
                <span><?php echo htmlspecialchars($product['product_name']); ?></span>
            </div>
        </div>

        <div class="product-container">
            <div class="product-detail">
                <!-- Image Gallery -->
                <div class="product-gallery">
                    <div class="main-image" id="mainImage">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                             onerror="this.src='https://via.placeholder.com/600x800?text=No+Image'"
                             onclick="zoomImage(this.src)">
                    </div>
                    
                    <div class="thumbnail-gallery">
                        <div class="thumbnail active" onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="View 1">
                        </div>
                        <!-- Additional images can be added here -->
                        <div class="thumbnail" onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="View 2">
                        </div>
                        <div class="thumbnail" onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="View 3">
                        </div>
                        <div class="thumbnail" onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="View 4">
                        </div>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    
                    <div class="product-meta">
                        <span class="product-category">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/>
                            </svg>
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>
                        
                        <div class="product-rating">
                            <span class="stars">★★★★★</span>
                            <span class="rating-count">(4.8 / 127 reviews)</span>
                        </div>
                    </div>

                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>

                    <div class="stock-info <?php echo $stock_class; ?>">
                        <svg class="stock-icon" fill="currentColor" viewBox="0 0 20 20">
                            <?php if ($stock > 0): ?>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            <?php else: ?>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            <?php endif; ?>
                        </svg>
                        <?php echo $stock_status; ?>
                    </div>

                    <div class="product-description">
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>

                    <ul class="product-features">
                        <li>Premium quality materials</li>
                        <li>Comfortable and durable</li>
                        <li>Easy care and maintenance</li>
                        <li>Perfect for everyday wear</li>
                        <li>Available in multiple sizes</li>
                    </ul>

                    <form method="POST" action="" id="addToCartForm">
                        <!-- Size Selection -->
                        <div class="size-selector">
                            <label class="size-label">Select Size:</label>
                            <div class="size-options">
                                <div class="size-option">
                                    <input type="radio" name="size" id="size-xs" value="XS">
                                    <label for="size-xs">XS</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size-s" value="S">
                                    <label for="size-s">S</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size-m" value="M" checked>
                                    <label for="size-m">M</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size-l" value="L">
                                    <label for="size-l">L</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size-xl" value="XL">
                                    <label for="size-xl">XL</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size-xxl" value="XXL">
                                    <label for="size-xxl">XXL</label>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Section -->
                        <div class="purchase-section">
                            <div class="quantity-selector">
                                <button type="button" onclick="decrementQty()">−</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $stock; ?>" readonly>
                                <button type="button" onclick="incrementQty()">+</button>
                            </div>

                            <button type="submit" name="add_to_cart" class="btn-add-to-cart" <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <?php echo $stock <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                            </button>
                        </div>
                    </form>

                    <?php if ($message): ?>
                        <div class="success-message show" id="successMessage">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Related Products -->
            <?php if ($related_result->num_rows > 0): ?>
                <div class="related-products">
                    <h2 class="section-title">You May Also Like</h2>
                    <div class="related-grid">
                        <?php while($related = $related_result->fetch_assoc()): ?>
                            <a href="product_details.php?id=<?php echo $related['product_id']; ?>" class="related-card">
                                <img src="<?php echo htmlspecialchars($related['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($related['product_name']); ?>"
                                     onerror="this.src='https://via.placeholder.com/250x280?text=No+Image'">
                                <div class="related-info">
                                    <h3 class="related-name"><?php echo htmlspecialchars($related['product_name']); ?></h3>
                                    <p class="related-price">₱<?php echo number_format($related['price'], 2); ?></p>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Zoom Overlay -->
    <div class="zoom-overlay" id="zoomOverlay" onclick="closeZoom()">
        <img id="zoomedImage" src="" alt="Zoomed product">
    </div>

    <script>
        // Quantity controls
        const maxStock = <?php echo $stock; ?>;
        
        function incrementQty() {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            if (current < maxStock) {
                input.value = current + 1;
            }
        }

        function decrementQty() {
            const input = document.getElementById('quantity');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
            }
        }

        // Image gallery
        function changeImage(src, element) {
            const mainImg = document.querySelector('#mainImage img');
            mainImg.src = src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
        }

        // Zoom functionality
        function zoomImage(src) {
            const overlay = document.getElementById('zoomOverlay');
            const zoomedImg = document.getElementById('zoomedImage');
            zoomedImg.src = src;
            overlay.classList.add('active');
        }

        function closeZoom() {
            document.getElementById('zoomOverlay').classList.remove('active');
        }

        // Update cart badge
        function updateCartBadge() {
            const cartBadge = document.getElementById('cart-count');
            if (cartBadge) {
                const count = <?php echo $cart_count; ?>;
                cartBadge.textContent = count;
                cartBadge.style.display = count > 0 ? 'flex' : 'none';
            }
        }

        // Hide success message after 5 seconds
        <?php if ($message): ?>
            setTimeout(() => {
                const msg = document.getElementById('successMessage');
                if (msg) {
                    msg.classList.remove('show');
                }
            }, 5000);
        <?php endif; ?>

        updateCartBadge();
    </script>
</body>
</html>
<?php $conn->close(); ?>