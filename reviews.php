<?php
// reviews.php
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

// Fetch product basic info for header
$product_stmt = $conn->prepare("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.product_id = ?
");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$product = $product_result->fetch_assoc();
$product_stmt->close();

// Get sorting and filtering parameters
$sort_by = $_GET['sort'] ?? 'newest';
$min_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Show 10 reviews per page

// Handle review helpfulness if user is logged in
if (isset($_GET['helpful_action']) && isset($_GET['rating_id'])) {
    $rating_id = (int)$_GET['rating_id'];
    $action = $_GET['helpful_action'];
    
    if (isset($_SESSION['user_id'])) {
        // Get customer_id from users table
        $customer_query = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
        $customer_query->bind_param("i", $_SESSION['user_id']);
        $customer_query->execute();
        $customer_result = $customer_query->get_result();
        
        if ($customer_result->num_rows > 0) {
            $customer_data = $customer_result->fetch_assoc();
            $customer_id = $customer_data['customer_id'];
            
            // Check if user has already voted on this review
            $check_stmt = $conn->prepare("SELECT helpfulness_id, is_helpful FROM rating_helpfulness WHERE rating_id = ? AND customer_id = ?");
            $check_stmt->bind_param("ii", $rating_id, $customer_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_vote = $check_result->fetch_assoc();
                $existing_helpfulness_id = $existing_vote['helpfulness_id'];
                $existing_is_helpful = $existing_vote['is_helpful'];
                
                // Determine new vote value based on action
                $new_is_helpful = ($action == 'helpful') ? 1 : 0;
                
                if ($action == 'helpful_remove' || $action == 'not_helpful_remove') {
                    // User wants to remove their vote
                    $delete_stmt = $conn->prepare("DELETE FROM rating_helpfulness WHERE helpfulness_id = ?");
                    $delete_stmt->bind_param("i", $existing_helpfulness_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    // TRIGGER WILL HANDLE THE COUNT UPDATE
                } elseif ($existing_is_helpful != $new_is_helpful) {
                    // User is changing their vote
                    $update_vote_stmt = $conn->prepare("UPDATE rating_helpfulness SET is_helpful = ? WHERE helpfulness_id = ?");
                    $update_vote_stmt->bind_param("ii", $new_is_helpful, $existing_helpfulness_id);
                    $update_vote_stmt->execute();
                    $update_vote_stmt->close();
                    // TRIGGER WILL HANDLE THE COUNT UPDATE
                }
                // If same vote, do nothing
            } else {
                // New vote - only insert if not removing
                if ($action == 'helpful' || $action == 'not_helpful') {
                    $is_helpful_value = ($action == 'helpful') ? 1 : 0;
                    $insert_stmt = $conn->prepare("INSERT INTO rating_helpfulness (rating_id, customer_id, is_helpful) VALUES (?, ?, ?)");
                    $insert_stmt->bind_param("iii", $rating_id, $customer_id, $is_helpful_value);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    // TRIGGER WILL HANDLE THE COUNT UPDATE
                }
            }
            
            $check_stmt->close();
        }
        $customer_query->close();
        
        // Redirect back to the specific review with filters preserved
        $redirect_url = "reviews.php?id=$product_id";
        
        // Preserve filters
        if ($sort_by != 'newest') $redirect_url .= "&sort=$sort_by";
        if ($min_rating > 0) $redirect_url .= "&rating=$min_rating";
        if ($page > 1) $redirect_url .= "&page=$page";
        
        // Add anchor to scroll to the review
        $redirect_url .= "#review-$rating_id";
        
        header("Location: $redirect_url");
        exit();
    } else {
        // User not logged in - redirect to login
        $_SESSION['redirect_url'] = "reviews.php?id=$product_id";
        header("Location: login.php");
        exit();
    }
}

// Build review query based on sorting and filtering
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

// Get count of all reviews for pagination
$count_query = "SELECT COUNT(*) as total FROM (" . $base_review_query . ") as subquery";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $product_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_reviews = $count_row['total'] ?? 0;
$count_stmt->close();

// Calculate pagination
$total_pages = ceil($total_reviews / $per_page);
$offset = ($page - 1) * $per_page;

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

// Add pagination
$review_query .= " LIMIT ?, ?";

$reviews_stmt = $conn->prepare($review_query);
$reviews_stmt->bind_param("iii", $product_id, $offset, $per_page);
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

// Load user's helpfulness votes for all reviews on this product
$user_helpfulness = [];
if (isset($_SESSION['user_id'])) {
    // Get customer_id from users table
    $customer_stmt = $conn->prepare("SELECT customer_id FROM customers WHERE user_id = ?");
    $customer_stmt->bind_param("i", $_SESSION['user_id']);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    if ($customer_result->num_rows > 0) {
        $customer_data = $customer_result->fetch_assoc();
        $customer_id = $customer_data['customer_id'];
        
        $helpfulness_stmt = $conn->prepare("
            SELECT rating_id, is_helpful 
            FROM rating_helpfulness 
            WHERE rating_id IN (
                SELECT rating_id FROM product_ratings WHERE product_id = ?
            ) AND customer_id = ?
        ");
        $helpfulness_stmt->bind_param("ii", $product_id, $customer_id);
        $helpfulness_stmt->execute();
        $helpfulness_result = $helpfulness_stmt->get_result();
        
        while ($row = $helpfulness_result->fetch_assoc()) {
            $user_helpfulness[$row['rating_id']] = $row['is_helpful'];
        }
        $helpfulness_stmt->close();
    }
    $customer_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews for <?php echo htmlspecialchars($product['product_name']); ?> - TrendyWear</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/reviews.css">
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
                <a href="product_details.php?id=<?php echo $product_id; ?>">
                    <?php echo htmlspecialchars($product['product_name']); ?>
                </a>
                <span>/</span>
                <span>Reviews</span>
            </div>
        </div>

        <div class="reviews-container">
            <!-- Reviews Header -->
            <div class="reviews-header">
                <a href="product_details.php?id=<?php echo $product_id; ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Product
                </a>
                
                <div class="product-info-header">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                         onerror="this.src='https://via.placeholder.com/80x80?text=No+Image'">
                    <div class="product-details">
                        <h1><?php echo htmlspecialchars($product['product_name']); ?></h1>
                        <div class="product-meta">
                            <div class="product-rating">
                                <div class="stars">
                                    <?php
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
                                <span class="rating-text">
                                    <?php echo number_format($avg_rating, 1); ?> out of 5
                                </span>
                            </div>
                            <span class="review-count">
                                <?php echo $total_reviews; ?> customer reviews
                            </span>
                        </div>
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

            <!-- Review Filters -->
            <div class="review-filters">
                <div class="filter-group">
                    <label for="sort-select">Sort by:</label>
                    <select id="sort-select" class="sort-select" onchange="updateSort()">
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
                
                <div class="filter-group">
                    <label>Showing:</label>
                    <div class="showing-info">
                        <?php 
                        $start = ($total_reviews > 0) ? (($page - 1) * $per_page + 1) : 0;
                        $end = min($page * $per_page, $total_reviews);
                        echo "Reviews $start - $end of $total_reviews";
                        ?>
                    </div>
                </div>
            </div>

            <!-- Reviews List -->
            <?php if ($reviews_result->num_rows > 0): ?>
                <div class="reviews-list">
                    <?php while ($review = $reviews_result->fetch_assoc()): 
                        $user_vote = isset($user_helpfulness[$review['rating_id']]) ? $user_helpfulness[$review['rating_id']] : null;
                        $is_helpful_active = ($user_vote === 1);
                        $is_not_helpful_active = ($user_vote === 0);
                        $is_active_review = false;
                        if (isset($_SERVER['HTTP_REFERER'])) {
                            $referer = $_SERVER['HTTP_REFERER'];
                            if (strpos($referer, "#review-{$review['rating_id']}") !== false) {
                                $is_active_review = true;
                            }
                        }
                    ?>

                    <?php
                        // Fetch images for this review
                        $images_stmt = $conn->prepare("
                            SELECT image_id, image_url, thumbnail_url, file_name 
                            FROM rating_images 
                            WHERE rating_id = ? 
                            ORDER BY image_order
                        ");
                        $images_stmt->bind_param("i", $review['rating_id']);
                        $images_stmt->execute();
                        $images_result = $images_stmt->get_result();
                        $review_images = [];
                        while ($img = $images_result->fetch_assoc()) {
                            $review_images[] = $img;
                        }
                        $images_stmt->close();
                        ?>
                        <div class="review-card <?php echo $is_active_review ? 'highlight' : ''; ?>" id="review-<?php echo $review['rating_id']; ?>">
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

                            <!-- Review Images Display -->
                            <?php if (!empty($review_images)): ?>
                                <div class="review-images">
                                    <?php foreach ($review_images as $image): ?>
                                        <div class="review-image-item">
                                            <img src="<?php echo htmlspecialchars($image['thumbnail_url']); ?>" 
                                                alt="Review image" 
                                                onclick="openImageModal('<?php echo htmlspecialchars($image['image_url']); ?>')"
                                                onerror="this.src='<?php echo htmlspecialchars($image['image_url']); ?>'">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
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
                                    <button class="helpful-btn <?php echo $is_helpful_active ? 'active' : ''; ?>" 
                                            data-rating-id="<?php echo $review['rating_id']; ?>"
                                            onclick="markHelpful(<?php echo $review['rating_id']; ?>)"
                                            <?php echo $is_helpful_active ? 'disabled' : ''; ?>>
                                        <i class="fas fa-thumbs-up"></i> Yes (<?php echo $review['helpful_count']; ?>)
                                    </button>
                                    <button class="not-helpful-btn <?php echo $is_not_helpful_active ? 'active not-helpful' : ''; ?>" 
                                            data-rating-id="<?php echo $review['rating_id']; ?>"
                                            onclick="markNotHelpful(<?php echo $review['rating_id']; ?>)"
                                            <?php echo $is_not_helpful_active ? 'disabled' : ''; ?>>
                                        <i class="fas fa-thumbs-down"></i> No (<?php echo $review['not_helpful_count']; ?>)
                                    </button>
                                </div>
                            </div>
                            
                        </div>
                    <?php endwhile; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo buildPaginationUrl($product_id, $sort_by, $min_rating, $page - 1); ?>" class="page-link prev">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <div class="page-numbers">
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<a href="' . buildPaginationUrl($product_id, $sort_by, $min_rating, 1) . '" class="page-link">1</a>';
                                    if ($start_page > 2) echo '<span class="page-dots">...</span>';
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $page) {
                                        echo '<span class="page-link current">' . $i . '</span>';
                                    } else {
                                        echo '<a href="' . buildPaginationUrl($product_id, $sort_by, $min_rating, $i) . '" class="page-link">' . $i . '</a>';
                                    }
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) echo '<span class="page-dots">...</span>';
                                    echo '<a href="' . buildPaginationUrl($product_id, $sort_by, $min_rating, $total_pages) . '" class="page-link">' . $total_pages . '</a>';
                                }
                                ?>
                            </div>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo buildPaginationUrl($product_id, $sort_by, $min_rating, $page + 1); ?>" class="page-link next">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-reviews">
                    <div class="no-reviews-content">
                        <i class="fas fa-comments"></i>
                        <h3>No reviews found</h3>
                        <p>
                            <?php if ($min_rating > 0): ?>
                                No reviews found with <?php echo $min_rating; ?> star rating.
                                <a href="reviews.php?id=<?php echo $product_id; ?>" class="clear-filter-link">View all reviews</a>
                            <?php else: ?>
                                Be the first to share your thoughts about this product!
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Zoom Overlay -->
    <div class="zoom-overlay" id="zoomOverlay" onclick="closeZoom()">
        <img id="zoomedImage" src="" alt="Zoomed image">
    </div>
    
    <!-- Login Prompt Overlay -->
    <div class="login-prompt" id="loginPrompt">
        <div class="login-prompt-content">
            <h3>Sign In Required</h3>
            <p>You need to be logged in to vote on reviews.</p>
            <div class="login-prompt-buttons">
                <a href="login.php" class="login-btn">Sign In</a>
                <a href="javascript:void(0)" class="cancel-btn" onclick="hideLoginPrompt()">Cancel</a>
            </div>
        </div>
    </div>

    <script>
        // Check if user is logged in
        const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        
        // User helpfulness data
        const userHelpfulness = <?php echo json_encode($user_helpfulness); ?>;
        
        // Pagination helper function
        function buildPaginationUrl(page) {
            const productId = <?php echo $product_id; ?>;
            const sortBy = document.getElementById('sort-select').value;
            const rating = <?php echo $min_rating; ?>;
            
            let url = `reviews.php?id=${productId}`;
            if (sortBy !== 'newest') url += `&sort=${sortBy}`;
            if (rating > 0) url += `&rating=${rating}`;
            url += `&page=${page}`;
            
            return url;
        }

        // Image gallery
        function openImageModal(imageSrc) {
            const overlay = document.getElementById('zoomOverlay');
            const zoomedImg = document.getElementById('zoomedImage');
            zoomedImg.src = imageSrc;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeZoom() {
            document.getElementById('zoomOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Review filtering and sorting
        function updateSort() {
            const sortBy = document.getElementById('sort-select').value;
            const rating = <?php echo $min_rating; ?>;
            const page = <?php echo $page; ?>;
            
            let url = `reviews.php?id=<?php echo $product_id; ?>`;
            if (sortBy !== 'newest') url += `&sort=${sortBy}`;
            if (rating > 0) url += `&rating=${rating}`;
            if (page > 1) url += `&page=1`; // Reset to page 1 when sorting
            
            window.location.href = url;
        }

        function filterByRating(rating) {
            const sortBy = document.getElementById('sort-select').value;
            
            // Toggle rating filter - if same rating clicked, remove it
            const currentRating = <?php echo $min_rating; ?>;
            let newRating = rating;
            if (currentRating == rating) {
                newRating = 0;
            }
            
            let url = `reviews.php?id=<?php echo $product_id; ?>`;
            if (sortBy !== 'newest') url += `&sort=${sortBy}`;
            if (newRating > 0) url += `&rating=${newRating}`;
            url += `&page=1`; // Reset to page 1 when filtering
            
            window.location.href = url;
        }

        function clearRatingFilter(e) {
            if (e) e.stopPropagation();
            
            const sortBy = document.getElementById('sort-select').value;
            
            let url = `reviews.php?id=<?php echo $product_id; ?>`;
            if (sortBy !== 'newest') url += `&sort=${sortBy}`;
            url += `&page=1`; // Reset to page 1
            
            window.location.href = url;
        }

        // Login prompt functions
        function showLoginPrompt() {
            document.getElementById('loginPrompt').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function hideLoginPrompt() {
            document.getElementById('loginPrompt').classList.remove('show');
            document.body.style.overflow = '';
        }

        // Review helpfulness functions
        function markHelpful(ratingId) {
            if (!isLoggedIn) {
                showLoginPrompt();
                return;
            }
            
            const currentUserVote = userHelpfulness[ratingId];
            let action = 'helpful';
            
            // If user already voted helpful, remove the vote
            if (currentUserVote === 1) {
                action = 'helpful_remove';
            }
            
            // Build URL with all current filters
            let url = `reviews.php?id=<?php echo $product_id; ?>&rating_id=${ratingId}&helpful_action=${action}`;
            
            // Preserve filters
            const sortBy = document.getElementById('sort-select').value;
            if (sortBy !== 'newest') url += `&sort=${sortBy}`;
            
            const rating = <?php echo $min_rating; ?>;
            if (rating > 0) url += `&rating=${rating}`;
            
            const page = <?php echo $page; ?>;
            if (page > 1) url += `&page=${page}`;
            
            // Add anchor to scroll to the review
            url += `#review-${ratingId}`;
            
            window.location.href = url;
        }

        function markNotHelpful(ratingId) {
            if (!isLoggedIn) {
                showLoginPrompt();
                return;
            }
            
            const currentUserVote = userHelpfulness[ratingId];
            let action = 'not_helpful';
            
            // If user already voted not helpful, remove the vote
            if (currentUserVote === 0) {
                action = 'not_helpful_remove';
            }
            
            // Build URL with all current filters
            let url = `reviews.php?id=<?php echo $product_id; ?>&rating_id=${ratingId}&helpful_action=${action}`;
            
            // Preserve filters
            const sortBy = document.getElementById('sort-select').value;
            if (sortBy !== 'newest') url += `&sort=${sortBy}`;
            
            const rating = <?php echo $min_rating; ?>;
            if (rating > 0) url += `&rating=${rating}`;
            
            const page = <?php echo $page; ?>;
            if (page > 1) url += `&page=${page}`;
            
            // Add anchor to scroll to the review
            url += `#review-${ratingId}`;
            
            window.location.href = url;
        }

        // Auto-scroll to the review anchor on page load
        window.addEventListener('load', function() {
            const hash = window.location.hash;
            if (hash && hash.startsWith('#review-')) {
                const reviewId = hash.replace('#review-', '');
                const reviewElement = document.getElementById(`review-${reviewId}`);
                if (reviewElement) {
                    setTimeout(() => {
                        reviewElement.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        // Add highlight effect
                        reviewElement.classList.add('highlight');
                        
                        // Remove highlight after animation
                        setTimeout(() => {
                            reviewElement.classList.remove('highlight');
                        }, 2000);
                    }, 500);
                }
            }
        });

        // Close zoom overlay with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeZoom();
            }
        });
    </script>
</body>

</html>
<?php
// Helper function for building pagination URLs
function buildPaginationUrl($product_id, $sort_by, $min_rating, $page) {
    $url = "reviews.php?id=$product_id";
    if ($sort_by != 'newest') $url .= "&sort=$sort_by";
    if ($min_rating > 0) $url .= "&rating=$min_rating";
    if ($page > 1) $url .= "&page=$page";
    return $url;
}

$conn->close();
?>