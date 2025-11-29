<?php 
include '../includes/auth.php';
include '../db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT 
        p.product_id,
        p.product_name,
        p.category_id,
        p.gender_id,
        p.age_group_id,
        p.description,
        p.price,
        p.cost_price,
        p.image_url,
        p.created_at,
        p.updated_at,
        c.category_name,
        g.gender_name,
        a.age_group_name,
        COALESCE(SUM(ps.stock_quantity), 0) AS total_stock
    FROM products p
    LEFT JOIN categories c      ON p.category_id = c.category_id
    LEFT JOIN gender_sections g ON p.gender_id = g.gender_id
    LEFT JOIN age_groups a      ON p.age_group_id = a.age_group_id
    LEFT JOIN product_sizes ps  ON p.product_id = ps.product_id
    WHERE p.product_id = ?
    GROUP BY 
        p.product_id,
        p.product_name,
        p.category_id,
        p.gender_id,
        p.age_group_id,
        p.description,
        p.price,
        p.cost_price,
        p.image_url,
        p.created_at,
        p.updated_at,
        c.category_name,
        g.gender_name,
        a.age_group_name
");

if ($stmt === false) {
    die("Query preparation failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $product_id);

if (!$stmt->execute()) {
    die("Query execution failed: " . htmlspecialchars($stmt->error));
}

$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: index.php');
    exit;
}


$sizes_query = "
    SELECT 
        ps.product_size_id,
        ps.stock_quantity,
        
        COALESCE(
            cs.size_name,                             
            ss.size_us,                                
            'One Size'
        ) AS display_size,

        CASE 
            WHEN ps.stock_quantity > 20 THEN 'in-stock'
            WHEN ps.stock_quantity > 0  THEN 'low-stock'
            ELSE 'out-of-stock'
        END AS status_class,
        
        CASE 
            WHEN ps.stock_quantity > 20 THEN 'In Stock'
            WHEN ps.stock_quantity > 0  THEN 'Low Stock'
            ELSE 'Out of Stock'
        END AS status_text

    FROM product_sizes ps
    LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
    LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id

    WHERE ps.product_id = ? AND ps.is_available = 1

    ORDER BY 
        COALESCE(cs.size_order, 999) ASC,              -- clothing sizes in correct order
        COALESCE(ss.size_us, 999) ASC                  -- shoes sorted by number
";

$sizes_stmt = $conn->prepare($sizes_query);

if ($sizes_stmt === false) {
    die("Query preparation failed: " . htmlspecialchars($conn->error) . "<br><br>Query:<pre>" . htmlspecialchars($sizes_query) . "</pre>");
}

$sizes_stmt->bind_param("i", $product_id);

if (!$sizes_stmt->execute()) {
    die("Query execution failed: " . htmlspecialchars($sizes_stmt->error));
}

$sizes_result = $sizes_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['product_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="products.css">
    <link rel="stylesheet" href="view_product.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <div class="header-left">
                <h1 class="page-title"><?= htmlspecialchars($product['product_name']) ?></h1>
                <a href="index.php" class="back-button">
                    <span class="material-icons text-lg" style="font-size:1.25rem;">arrow_back</span> Back to Products
                </a>
            </div>
            <div class="header-right">
                <a href="edit_product.php?id=<?= $product_id ?>" class="back-button">
                    <span class="material-icons text-lg" style="font-size:1.25rem;">edit</span> Edit Product
                </a>
            </div>
        </div>

        <div class="view-grid">
            <div class="top-row">
                <div class="product-image-container">
                    <?php 
                    $img = $product['image_url'] 
                        ? htmlspecialchars($product['image_url']) 
                        : 'https://via.placeholder.com/600/7c3aed/ec4899?text=' . substr(htmlspecialchars($product['product_name']), 0, 3);
                    ?>
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-image">
                </div>

                <div class="details-section">
                    <div class="detail-card">
                        <p class="label">Product ID</p>
                        <p class="value"><?= htmlspecialchars($product['product_id'] ?? '—') ?></p>
                    </div>
                    <div class="detail-card">
                        <p class="label">Total Stock</p>
                        <p class="value <?= $product['total_stock'] > 20 ? 'text-green' : ($product['total_stock'] > 0 ? 'text-orange' : 'text-red') ?>">
                            <?= number_format($product['total_stock']) ?> units
                        </p>
                    </div>
                    <div class="detail-card">
                        <p class="label">Category</p>
                        <p class="value"><?= htmlspecialchars($product['category_name'] ?? '—') ?></p>
                    </div>
                    <div class="detail-card">
                        <p class="label">Gender</p>
                        <p class="value"><?= htmlspecialchars($product['gender_name'] ?? 'Unisex') ?></p>
                    </div>
                    <div class="detail-card">
                        <p class="label">Age Group</p>
                        <p class="value"><?= htmlspecialchars($product['age_group_name'] ?? 'All Ages') ?></p>
                    </div>
                    <div class="detail-card">
                        <p class="label">Overall Status</p>
                        <span class="status-badge <?= $product['total_stock'] > 20 ? 'in-stock' : ($product['total_stock'] > 0 ? 'low-stock' : 'out-of-stock') ?>">
                            <?= $product['total_stock'] > 20 ? 'In Stock' : ($product['total_stock'] > 0 ? 'Low Stock' : 'Out of Stock') ?>
                        </span>
                    </div>
                    <div class="detail-card">
                        <p class="label">Selling Price</p>
                        <p class="value">₱<?= number_format($product['price'], 2) ?></p>
                    </div>
                    <div class="detail-card">
                        <p class="label">Cost Price</p>
                        <p class="value">₱<?= number_format($product['cost_price'], 2) ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($product['description'])): ?>
            <div class="description-card">
                <h2>Description</h2>
                <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Size Variants Table -->
            <div class="size-table-container">
                <div class="size-table-header">
                    <h2>Size Variants & Stock</h2>
                </div>
                <?php if ($sizes_result->num_rows > 0): ?>
                <table class="size-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($size = $sizes_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="size-badge">
                                    <?= htmlspecialchars($size['display_size']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="stock-value">
                                    <?= $size['stock_quantity'] ?> units
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= $size['status_class'] ?>">
                                    <?= $size['status_text'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <span class="material-icons">inventory_2</span>
                    <p>No size variants recorded yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>