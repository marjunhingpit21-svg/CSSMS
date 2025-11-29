<?php 
include '../includes/auth.php';
include '../db.php';

// Fetch categories, genders, and age groups for dropdowns
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$genders = $conn->query("SELECT gender_id, gender_name FROM gender_sections ORDER BY gender_name");
$age_groups = $conn->query("SELECT age_group_id, age_group_name FROM age_groups ORDER BY age_group_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="edit_product.css" rel="stylesheet">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <div class="header-left">
                <h1 class="page-title">Add New Product</h1>
            </div>
            <div class="header-right">
                <a href="index.php" class="back-button">
                    <span class="material-icons text-lg" style="font-size:1.25rem;">arrow_back</span> Back to Product
                </a>
            </div>
        </div>

        <form id="addForm" method="POST" action="save_product.php" enctype="multipart/form-data">
            <div class="form-grid">
                <div>
                    <div class="card">
                        <h2>Basic Information</h2>
                        <div class="form-row">
                            <div class="form-group full">
                                <label>Product Name <span class="required">*</span></label>
                                <input type="text" name="product_name" required>
                            </div>

                            <div class="form-group">
                                <label>Category <span class="required">*</span></label>
                                <select name="category_id" required>
                                    <option value="">Select category</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?= $cat['category_id'] ?>">
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
                                    <option value="<?= $gender['gender_id'] ?>">
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
                                    <option value="<?= $age['age_group_id'] ?>">
                                        <?= htmlspecialchars($age['age_group_name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group full">
                                <label>Description</label>
                                <textarea name="description" rows="4"></textarea>
                            </div>

                            <div class="image-upload-header">
                                <h2>Product Image</h2>
                                <p>Click image to change. Supports JPG, PNG, WebP (Max 5MB)</p>
                            </div>
                            <div id="dropZone">
                                <input type="file" id="productImage" name="product_image" accept="image/*" style="display:none;">
                                <img id="imagePreview" class="hidden" src="" alt="Product Preview">
                                <div id="uploadPlaceholder">
                                    <span class="material-icons">cloud_upload</span>
                                    <p>Click to upload product image</p>
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
                                    <span>₱</span><input type="number" name="price" step="0.01" min="0" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Cost Price</label>
                                <div class="input-with-prefix">
                                    <span>₱</span>
                                    <input type="number" name="cost_price" step="0.01" min="0">
                                </div>
                            </div>

                            <div class="full">
                                <div class="profit-box">
                                    <span class="material-icons" style="font-size:0.875rem;vertical-align:middle;">info</span>
                                    Profit per unit: <strong>₱0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Size Variants & Stock -->
                    <div class="card sizes-card">
                        <div class="sizes-header">
                            <h2>Size Variants & Stock</h2>
                            <button type="button" onclick="addSizeRow()" class="btn-add">
                                <span class="material-icons" style="font-size:1rem;">add</span> Add Size
                            </button>
                        </div>

                        <div class="empty-state">
                            <span class="material-icons">inventory_2</span>
                            <p>No size variants yet. Click "Add Size" to create variants.</p>
                        </div>

                        <table class="hidden">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Barcode</th>
                                    <th>Stock Quantity</th>
                                    <th>Price Adjustment</th>
                                    <th>Available</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sizesTableBody">
                                <!-- Size rows will be added here dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Action Buttons -->
                    <div class="actions">
                        <a href="index.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save">Add Product</button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script src="add_product.js"></script>
</body>
</html>