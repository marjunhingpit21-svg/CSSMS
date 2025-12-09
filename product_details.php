<?php
include 'Database/db.php';
session_start();
// Get orders count for header
  $orders_count = 0;
  if (isset($_SESSION['user_id'])) {
      include 'orders_count.php';
      $orders_count = getOrdersCount($conn, $_SESSION['user_id']);
  }
  
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get cart count for header
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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

// Fetch available sizes - ONLY sizes with stock > 0, GROUP by EU size to avoid duplicates
$sizes_stmt = $conn->prepare("
    SELECT 
        ps.product_size_id, 
        cs.size_name as clothing_size,
        ss.size_eu,
        ps.stock_quantity,
        COALESCE(cs.size_order, ss.size_eu) as sort_order,
        CASE 
            WHEN cs.size_name IS NOT NULL THEN 'clothing'
            ELSE 'shoe'
        END as size_type
    FROM product_sizes ps
    LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
    LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
    WHERE ps.product_id = ? AND ps.is_available = 1 AND ps.stock_quantity > 0
    GROUP BY COALESCE(cs.size_name, ss.size_eu)
    ORDER BY sort_order
");
$sizes_stmt->bind_param("i", $product_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();
$sizes_stmt->close();

// Get sorting parameters for reviews
$sort_by = $_GET['sort'] ?? 'newest';
$min_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// Build review query based on sorting
// Get all reviews for count and stats
// First, get the base query without ORDER BY and LIMIT
$base_review_query = "
    SELECT 
        pr.rating_id,
        pr.rating,
        pr.review_title,
        pr.review_text,
        pr.quality_rating,
        pr.fit_rating,
        pr.value_rating,
        pr.would_recommend,
        pr.verified_purchase,
        pr.helpful_count,
        pr.not_helpful_count,
        pr.created_at,
        CONCAT(LEFT(cu.first_name, 1), '. ', LEFT(cu.last_name, 1), '.') as customer_initials,
        COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) AS size_purchased,
        (SELECT COUNT(*) FROM rating_images WHERE rating_id = pr.rating_id) AS image_count
    FROM product_ratings pr
    JOIN customers cu ON pr.customer_id = cu.customer_id
    LEFT JOIN product_sizes ps ON pr.product_size_id = ps.product_size_id
    LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
    LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
    WHERE pr.product_id = ? 
    AND pr.status = 'approved'
";

// Add rating filter if specified
if ($min_rating > 0) {
    $base_review_query .= " AND pr.rating = $min_rating";
}

// Get count of all reviews
$count_query = "SELECT COUNT(*) as total FROM (" . $base_review_query . ") as subquery";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $product_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_reviews = $count_row['total'] ?? 0;
$count_stmt->close();

// Now build the main query with sorting
$review_query = $base_review_query;

// Add sorting
switch ($sort_by) {
    case 'highest':
        $review_query .= " ORDER BY pr.rating DESC, pr.created_at DESC";
        break;
    case 'lowest':
        $review_query .= " ORDER BY pr.rating ASC, pr.created_at DESC";
        break;
    case 'most_helpful':
        $review_query .= " ORDER BY (pr.helpful_count - pr.not_helpful_count) DESC, pr.created_at DESC";
        break;
    case 'oldest':
        $review_query .= " ORDER BY pr.created_at ASC";
        break;
    default: // 'newest'
        $review_query .= " ORDER BY pr.created_at DESC";
}

// Get limited reviews for display (just 1 initially)
$limit = 1;
$review_query .= " LIMIT ?";
$reviews_stmt = $conn->prepare($review_query);
$reviews_stmt->bind_param("ii", $product_id, $limit);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Get rating distribution for progress bars
$rating_dist_stmt = $conn->prepare("
    SELECT 
        rating,
        COUNT(*) as count
    FROM product_ratings
    WHERE product_id = ? AND status = 'approved'
    GROUP BY rating
    ORDER BY rating DESC
");
$rating_dist_stmt->bind_param("i", $product_id);
$rating_dist_stmt->execute();
$rating_dist_result = $rating_dist_stmt->get_result();

// Initialize rating counts
$rating_counts = [
    5 => 0,
    4 => 0,
    3 => 0,
    2 => 0,
    1 => 0
];

// Calculate total reviews and average
$total_reviews_for_avg = 0;
$total_rating_sum = 0;
$avg_rating = 0;

while ($row = $rating_dist_result->fetch_assoc()) {
    $rating = (int)$row['rating'];
    $count = (int)$row['count'];
    $rating_counts[$rating] = $count;
    $total_reviews_for_avg += $count;
    $total_rating_sum += ($rating * $count);
}

if ($total_reviews_for_avg > 0) {
    $avg_rating = $total_rating_sum / $total_reviews_for_avg;
}

$rating_dist_stmt->close();

// Calculate percentages for rating distribution
$rating_distribution = [];
if ($total_reviews_for_avg > 0) {
    foreach ($rating_counts as $rating => $count) {
        $rating_distribution[$rating] = [
            'count' => $count,
            'percentage' => ($count / $total_reviews_for_avg) * 100
        ];
    }
}

// Fetch related products (same category, exclude current product)
$related_stmt = $conn->prepare("
   SELECT p.product_id, p.product_name, p.price, p.image_url, 
       COALESCE(SUM(ps.stock_quantity), 0) as total_stock
FROM products p
LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
WHERE p.category_id = ? AND p.product_id != ?
GROUP BY p.product_id
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
    $quantity = (int) $_POST['quantity'] ?? 1;
    $size = $_POST['size'] ?? null;

    if ($size) {
        // Check if it's a shoe size (EU number) or clothing size
        if (is_numeric($size)) {
            // It's a shoe size (EU)
            $size_stmt = $conn->prepare("
                SELECT ps.stock_quantity, ps.product_size_id 
                FROM product_sizes ps
                LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
                WHERE ps.product_id = ? AND ss.size_eu = ?
            ");
            $size_stmt->bind_param("ii", $product_id, $size);
        } else {
            // It's a clothing size
            $size_stmt = $conn->prepare("
                SELECT ps.stock_quantity, ps.product_size_id 
                FROM product_sizes ps
                LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
                WHERE ps.product_id = ? AND cs.size_name = ?
            ");
            $size_stmt->bind_param("is", $product_id, $size);
        }
        
        $size_stmt->execute();
        $size_result = $size_stmt->get_result();
        $size_data = $size_result->fetch_assoc();
        $stock = $size_data ? $size_data['stock_quantity'] : 0;
        $size_stmt->close();

        if ($stock > 0) {
            $cart_key = $product_id . '_' . $size;
            if (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$cart_key] = [
                    'product_id' => $product['product_id'],
                    'product_name' => $product['product_name'],
                    'price' => $product['price'],
                    'quantity' => $quantity,
                    'stock' => $stock,
                    'size' => $size
                ];
            }

            // Limit to stock quantity
            if ($_SESSION['cart'][$cart_key]['quantity'] > $stock) {
                $_SESSION['cart'][$cart_key]['quantity'] = $stock;
            }

            $message = 'Added to cart successfully!';
            $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
        } else {
            $message = 'Out of stock for selected size!';
        }
    } else {
        $message = 'Please select a size!';
    }
}

// Determine if any stock available
$has_stock = ($sizes_result->num_rows > 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/product_details.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <div class="thumbnail active"
                            onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="View 1">
                        </div>
                        <div class="thumbnail"
                            onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="View 2">
                        </div>
                        <div class="thumbnail"
                            onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="View 3">
                        </div>
                        <div class="thumbnail"
                            onclick="changeImage('<?php echo htmlspecialchars($product['image_url']); ?>', this)">
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
                                <path
                                    d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                            </svg>
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>

                        <div class="product-rating">
                            <div class="stars">
                                <?php
                                $avg_rating = $avg_rating ?? 0;
                                $full_stars = floor($avg_rating);
                                $has_half_star = ($avg_rating - $full_stars) >= 0.5;
                                
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $full_stars) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($has_half_star && $i == $full_stars + 1) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="rating-count">
                                <?php 
                                if ($total_reviews_for_avg > 0) {
                                    echo number_format($avg_rating, 1) . ' / ' . $total_reviews_for_avg . ' reviews';
                                } else {
                                    echo 'No reviews yet';
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>

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
                            <div class="size-options <?php 
                                // Check if first size is a shoe to add shoe class
                                $sizes_result->data_seek(0);
                                $first_size = $sizes_result->fetch_assoc();
                                echo ($first_size && $first_size['size_type'] == 'shoe') ? 'shoe-sizes' : '';
                                $sizes_result->data_seek(0);
                            ?>">
                                <?php
                                $default_set = false;
                                while ($size = $sizes_result->fetch_assoc()):
                                    $checked = '';
                                    if (!$default_set) {
                                        $checked = 'checked';
                                        $default_set = true;
                                    }
                                    
                                    // Determine size display value - ONLY EU for shoes
                                    if ($size['size_type'] == 'shoe') {
                                        $size_value = $size['size_eu'];
                                        $size_id = 'size-eu-' . $size['size_eu'];
                                        $size_label = $size['size_eu'];
                                    } else {
                                        $size_value = $size['clothing_size'];
                                        $size_id = 'size-' . strtolower($size['clothing_size']);
                                        $size_label = $size['clothing_size'];
                                    }
                                ?>
                                
                                
                                    <div class="size-option">
                                        <input type="radio" name="size"
                                            id="<?php echo $size_id; ?>"
                                            value="<?php echo htmlspecialchars($size_value); ?>" 
                                            data-stock="<?php echo $size['stock_quantity']; ?>"
                                            <?php echo $checked; ?>>
                                        <label for="<?php echo $size_id; ?>">
                                            <?php echo htmlspecialchars($size_label); ?>
                                        </label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <!-- Stock Info (dynamic per size) -->
                        <div id="stock-info" class="stock-info"></div>

                        <!-- Purchase Section -->
                        <div class="purchase-section">
                            <div class="quantity-selector">
                                <button type="button" onclick="decrementQty()">−</button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" readonly>
                                <button type="button" onclick="incrementQty()">+</button>
                            </div>

                            <button type="submit" name="add_to_cart" class="btn-add-to-cart" <?php echo !$has_stock ? 'disabled' : ''; ?>>
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                <?php echo !$has_stock ? 'Out of Stock' : 'Add to Cart'; ?>
                            </button>
                        </div>
                    </form>

                    <?php if ($message): ?>
                        <div class="success-message show" id="successMessage">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="reviews-section">
                <h2 class="section-title">Customer Reviews</h2>
                
                <!-- Review Filters -->
                <div class="review-filters">
                    <div class="filter-group">
                        <label for="sort-select">Sort by:</label>
                        <select id="sort-select" class="sort-select" onchange="updateReviews()">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="highest" <?php echo $sort_by == 'highest' ? 'selected' : ''; ?>>Highest Rating</option>
                            <option value="lowest" <?php echo $sort_by == 'lowest' ? 'selected' : ''; ?>>Lowest Rating</option>
                            <option value="most_helpful" <?php echo $sort_by == 'most_helpful' ? 'selected' : ''; ?>>Most Helpful</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Filter by rating:</label>
                        <div class="rating-filter">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <button class="rating-filter-btn <?php echo $min_rating == $i ? 'active' : ''; ?>" 
                                        data-rating="<?php echo $i; ?>" 
                                        onclick="filterByRating(<?php echo $i; ?>)">
                                    <?php echo $i; ?> <i class="fas fa-star"></i>
                                    <?php if ($min_rating == $i): ?>
                                        <span class="clear-filter" onclick="clearRatingFilter(event)">×</span>
                                    <?php endif; ?>
                                </button>
                            <?php endfor; ?>
                            <?php if ($min_rating > 0): ?>
                                <button class="clear-all-btn" onclick="clearRatingFilter()">Clear All</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Overall Rating Summary -->
                <div class="rating-summary">
                    <div class="rating-overview">
                        <div class="average-rating">
                            <span class="rating-number"><?php echo number_format($avg_rating, 1); ?></span>
                            <div class="rating-stars">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $full_stars) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($has_half_star && $i == $full_stars + 1) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="rating-count"><?php echo $total_reviews; ?> reviews</span>
                        </div>
                        
                        <div class="rating-distribution">
                            <?php if ($total_reviews_for_avg > 0): ?>
                                <?php for ($i = 5; $i >= 1; $i--): 
                                    $count = $rating_counts[$i];
                                    $percentage = $rating_distribution[$i]['percentage'] ?? 0;
                                ?>
                                    <div class="rating-bar-item">
                                        <span class="star-label">
                                            <a href="javascript:void(0)" onclick="filterByRating(<?php echo $i; ?>)" class="star-link">
                                                <?php echo $i; ?> stars
                                            </a>
                                        </span>
                                        <div class="rating-bar">
                                            <div class="rating-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <span class="rating-count">
                                            <a href="javascript:void(0)" onclick="filterByRating(<?php echo $i; ?>)" class="count-link">
                                                <?php echo $count; ?>
                                            </a>
                                        </span>
                                    </div>
                                <?php endfor; ?>
                            <?php else: ?>
                                <p class="no-reviews-message">No reviews yet. Be the first to review this product!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Reviews List -->
                <?php if ($reviews_result->num_rows > 0): ?>
                    <div class="reviews-list">
                        <?php while ($review = $reviews_result->fetch_assoc()): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="reviewer-info">
                                        <div class="reviewer-avatar">
                                            <?php echo $review['customer_initials']; ?>
                                        </div>
                                        <div class="reviewer-details">
                                            <span class="reviewer-name"><?php echo $review['customer_initials']; ?></span>
                                            <div class="review-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <?php if (!empty($review['size_purchased'])): ?>
                                                <span class="review-size">Size: <?php echo htmlspecialchars($review['size_purchased']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="review-meta">
                                        <span class="review-date"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></span>
                                        <?php if ($review['verified_purchase']): ?>
                                            <span class="verified-badge">
                                                <i class="fas fa-check-circle"></i> Verified Purchase
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="review-content">
                                    <?php if (!empty($review['review_title'])): ?>
                                        <h3 class="review-title"><?php echo htmlspecialchars($review['review_title']); ?></h3>
                                    <?php endif; ?>
                                    
                                    <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    
                                    <!-- Sub-ratings -->
                                    <div class="sub-ratings">
                                        <?php if ($review['quality_rating']): ?>
                                            <div class="sub-rating">
                                                <span>Quality:</span>
                                                <div class="sub-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['quality_rating'] ? 'filled' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($review['fit_rating']): ?>
                                            <div class="sub-rating">
                                                <span>Fit:</span>
                                                <div class="sub-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['fit_rating'] ? 'filled' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($review['value_rating']): ?>
                                            <div class="sub-rating">
                                                <span>Value:</span>
                                                <div class="sub-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['value_rating'] ? 'filled' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($review['would_recommend']): ?>
                                            <div class="recommendation">
                                                <i class="fas fa-thumbs-up"></i> Would recommend
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Helpfulness -->
                                    <div class="helpfulness">
                                        <span>Was this review helpful?</span>
                                        <button class="helpful-btn" onclick="markHelpful(<?php echo $review['rating_id']; ?>)">
                                            <i class="fas fa-thumbs-up"></i> Yes (<?php echo $review['helpful_count']; ?>)
                                        </button>
                                        <button class="not-helpful-btn" onclick="markNotHelpful(<?php echo $review['rating_id']; ?>)">
                                            <i class="fas fa-thumbs-down"></i> No (<?php echo $review['not_helpful_count']; ?>)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        
                        <!-- Show More Button -->
                        <?php if ($total_reviews > 1): ?>
                            <div class="show-more-container">
                                <button class="btn-show-more" onclick="loadMoreReviews()">
                                    <i class="fas fa-chevron-down"></i>
                                    Show All Reviews (<?php echo $total_reviews - 1; ?> more)
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-reviews">
                        <div class="no-reviews-content">
                            <i class="fas fa-comments"></i>
                            <h3>No reviews yet</h3>
                            <p>Be the first to share your thoughts about this product!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Related Products -->
            <?php if ($related_result->num_rows > 0): ?>
                <div class="related-products">
                    <h2 class="section-title">You May Also Like</h2>
                    <div class="related-grid">
                        <?php while ($related = $related_result->fetch_assoc()): ?>
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
        // Size stocks data from PHP
        const sizeStocks = {
            <?php
            $sizes_result->data_seek(0);
            while ($size = $sizes_result->fetch_assoc()):
                if ($size['size_type'] == 'shoe') {
                    $key = $size['size_eu'];
                } else {
                    $key = $size['clothing_size'];
                }
                echo "'" . addslashes($key) . "': {$size['stock_quantity']},";
            endwhile;
            ?>
        };

        // Quantity controls
        function incrementQty() {
            const input = document.getElementById('quantity');
            const max = parseInt(input.max) || 1;
            const current = parseInt(input.value);
            if (current < max) {
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

        // Review filtering and sorting
        function updateReviews() {
            const sortBy = document.getElementById('sort-select').value;
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sort', sortBy);
            
            // Keep rating filter if present
            const minRating = urlParams.get('rating') || '';
            
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }

        function filterByRating(rating) {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Toggle rating filter - if same rating clicked, remove it
            const currentRating = urlParams.get('rating');
            if (currentRating == rating) {
                urlParams.delete('rating');
            } else {
                urlParams.set('rating', rating);
            }
            
            // Keep sort if present
            const sortBy = urlParams.get('sort') || 'newest';
            
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }

        function clearRatingFilter(e) {
            if (e) e.stopPropagation();
            
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('rating');
            
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }

        // Load more reviews
        function loadMoreReviews() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Remove any limit parameter to show all reviews
            if (urlParams.has('limit')) {
                urlParams.delete('limit');
            }
            
            // Add a parameter to indicate we want all reviews
            urlParams.set('show_all', 'true');
            
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }

        // Review helpfulness functions
        function markHelpful(ratingId) {
            // In a real application, you would send an AJAX request here
            alert('Thank you for your feedback! This will be implemented with proper backend functionality.');
        }

        function markNotHelpful(ratingId) {
            // In a real application, you would send an AJAX request here
            alert('Thank you for your feedback! This will be implemented with proper backend functionality.');
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

        // Dynamic stock display and button control on size change
        const addButton = document.querySelector('.btn-add-to-cart');
        const qtyInput = document.getElementById('quantity');
        document.querySelectorAll('input[name="size"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const selectedSize = this.value;
                const stock = sizeStocks[selectedSize] || 0;
                const stockElem = document.getElementById('stock-info');

                let stockStatus = '';
                let stockClass = '';
                if (stock > 10) {
                    stockStatus = `In stock: ${stock}`;
                    stockClass = 'in-stock';
                } else if (stock > 0) {
                    stockStatus = 'Only ' + stock + ' left';
                    stockClass = 'low-stock';
                } else {
                    stockStatus = 'Out of Stock';
                    stockClass = 'out-of-stock';
                }

                stockElem.className = 'stock-info ' + stockClass;
                stockElem.innerHTML = `
                    <svg class="stock-icon" fill="currentColor" viewBox="0 0 20 20">
                        ${stock > 0 ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>' : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>'}
                    </svg>
                    ${stockStatus}
                `;

                // Update quantity max and add button
                qtyInput.max = stock;
                qtyInput.value = 1;
                
                if (stock <= 0) {
                    addButton.disabled = true;
                    addButton.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg> Out of Stock';
                } else {
                    addButton.disabled = false;
                    addButton.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg> Add to Cart';
                }
            });
        });

        // Trigger initial change event for default selected size
        const defaultRadio = document.querySelector('input[name="size"]:checked');
        if (defaultRadio) {
            defaultRadio.dispatchEvent(new Event('change'));
        } else {
            // No available sizes, disable add button
            addButton.disabled = true;
            addButton.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg> Out of Stock';
        }

        updateCartBadge();
    </script>
</body>

</html>
<?php $conn->close(); ?>