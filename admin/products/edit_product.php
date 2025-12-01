<?php 
include '../includes/auth.php';
include '../db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = (int)$_GET['id'];

// Fetch product details
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
        COALESCE(SUM(ps.stock_quantity), 0) AS total_stock
    FROM products p
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
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
        p.image_url
");

$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: index.php');
    exit;
}

// Fetch categories
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$current_category = $conn->query("SELECT category_name FROM categories WHERE category_id = " . $product['category_id'])->fetch_assoc();
$is_shoe_product = stripos($current_category['category_name'], 'shoe') !== false || stripos($current_category['category_name'], 'footwear') !== false;

// Fetch genders
$genders = $conn->query("SELECT gender_id, gender_name FROM gender_sections ORDER BY gender_name");

// Fetch age groups
$age_groups = $conn->query("SELECT age_group_id, age_group_name FROM age_groups ORDER BY age_group_name");

// Fetch size variants
$sizes_stmt = $conn->prepare("
    SELECT 
        ps.product_size_id,
        ps.barcode,
        ps.stock_quantity,
        ps.price_adjustment,
        ps.is_available,
        COALESCE(cs.size_name, CONCAT(ss.size_us, ' US')) AS display_size,
        COALESCE(cs.size_order, 999) AS sort_order
    FROM product_sizes ps
    LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
    LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
    WHERE ps.product_id = ?
    ORDER BY sort_order ASC, COALESCE(ss.size_us, 999) ASC
");

$sizes_stmt->bind_param("i", $product_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="edit_product.css" rel="stylesheet">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <!-- Updated header-section with back button on the right -->
        <div class="header-section">
            <div class="header-left">
                <h1 class="page-title">Edit Product</h1>
            </div>
            <div class="header-right">
                <a href="view_product.php?id=<?= $product_id ?>" class="back-button">
                    <span class="material-icons text-lg" style="font-size:1.25rem;">arrow_back</span> Back to Product
                </a>
            </div>
        </div>

        <form id="editForm" method="POST" action="update_product.php" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?= $product_id ?>">
            <input type="hidden" id="isShoeProduct" value="<?= $is_shoe_product ? 'true' : 'false' ?>">

            <div class="form-grid">
                <div>
                    <div class="card">
                        <h2>Basic Information</h2>
                        <div class="form-row">
                            <div class="form-group full">
                                <label>Product Name <span class="required">*</span></label>
                                <input type="text" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Category <span class="required">*</span></label>
                                <select name="category_id" required>
                                    <option value="">Select category</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $product['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender_id">
                                    <option value="">Select gender</option>
                                    <?php while ($gender = $genders->fetch_assoc()): ?>
                                    <option value="<?= $gender['gender_id'] ?>" <?= $gender['gender_id'] == $product['gender_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($gender['gender_name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Age Group</label>
                                <select name="age_group_id">
                                    <option value="">Select age group</option>
                                    <?php while ($age = $age_groups->fetch_assoc()): ?>
                                    <option value="<?= $age['age_group_id'] ?>" <?= $age['age_group_id'] == $product['age_group_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($age['age_group_name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group full">
                                <label>Description</label>
                                <textarea name="description" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
                            </div>

                            <div class="image-upload-header">
                                <h2>Product Image</h2>
                                <p>Click image to change. Supports JPG, PNG, WebP (Max 5MB)</p>
                            </div>
                            <div id="dropZone">
                                <input type="file" id="productImage" name="product_image" accept="image/*" style="display:none;">
                                <?php 
                                $current_img = $product['image_url'] ?: 'https://via.placeholder.com/600/7c3aed/ec4899?text=' . substr($product['product_name'], 0, 3);
                                ?>
                                <img id="imagePreview" src="<?= htmlspecialchars($current_img) ?>" alt="Product">
                                <div id="uploadPlaceholder" style="display:none;">
                                    <span class="material-icons">cloud_upload</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="card">
                        <h2>Pricing</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Selling Price <span class="required">*</span></label>
                                <div class="input-with-prefix">
                                    <span>₱</span><input type="number" name="price" value="<?= $product['price'] ?>" step="0.01" min="0" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Cost Price</label>
                                <div class="input-with-prefix">
                                    <span>₱</span>
                                    <input type="number" name="cost_price" value="<?= $product['cost_price'] ?>" step="0.01" min="0">
                                </div>
                            </div>

                            <div class="full">
                                <div class="profit-box">
                                    <span class="material-icons" style="font-size:0.875rem;vertical-align:middle;">info</span>
                                    Profit per unit: <strong>₱<?= number_format($product['price'] - $product['cost_price'], 2) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Size Variants & Stock -->
                    <div class="card sizes-card">
                        <div class="sizes-header">
                            <h2>Size Variants & Stock <span id="categoryIndicator" class="category-indicator <?= $is_shoe_product ? 'shoe-category' : 'clothing-category' ?>">
                                <?= $is_shoe_product ? 'Shoe Sizes' : 'Clothing Sizes' ?>
                            </span></h2>
                            <button type="button" onclick="addSizeRow()" class="btn-add">
                                <span class="material-icons" style="font-size:1rem;">add</span> Add Size
                            </button>
                        </div>

                        <!-- Always include the table structure, even when empty -->
                        <table id="sizesTable" style="<?= $sizes_result->num_rows == 0 ? 'display:none;' : '' ?>">
                            <thead>
                                <tr>
                                    <th id="sizeHeader"><?= $is_shoe_product ? 'Shoe Size (US)' : 'Size' ?></th>
                                    <th>Barcode</th>
                                    <th>Stock Quantity</th>
                                    <th>Price Adjustment</th>
                                    <th>Available</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sizesTableBody">
                                <?php if ($sizes_result->num_rows > 0): ?>
                                    <?php while ($size = $sizes_result->fetch_assoc()): ?>
                                    <tr data-size-id="<?= $size['product_size_id'] ?>">
                                        <td>
                                            <span class="size-tag"><?= htmlspecialchars($size['display_size']) ?></span>
                                            <input type="hidden" name="size_ids[]" value="<?= $size['product_size_id'] ?>">
                                        </td>
                                        <td><input type="text" name="barcodes[]" value="<?= htmlspecialchars($size['barcode']) ?>" style="width:100%;font-family:monospace;"></td>
                                        <td><input type="number" name="quantities[]" value="<?= $size['stock_quantity'] ?>" min="0" style="width:6rem;"></td>
                                        <td>
                                            <div class="input-with-prefix">
                                                <span style="font-size:0.875rem;">₱</span>
                                                <input type="number" name="price_adjustments[]" value="<?= $size['price_adjustment'] ?>" step="0.01" style="width:7.5rem;padding-left:2rem;">
                                            </div>
                                        </td>
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" name="is_available[]" value="<?= $size['product_size_id'] ?>" <?= $size['is_available'] ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </td>
                                        <td>
                                            <button type="button" onclick="deleteSizeRow(this)" class="delete-btn">
                                                <span class="material-icons">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php if ($sizes_result->num_rows == 0): ?>
                        <div id="emptySizeState" class="empty-state">
                            <span class="material-icons">inventory_2</span>
                            <p>No size variants yet. Click "Add Size" to create variants.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="actions">
                        <a href="view_product.php?id=<?= $product_id ?>" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script src="edit_product.js"></script>
</body>
</html>