<?php 
include '../includes/auth.php';
include '../db.php';

// Total products count
$totalQ = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];

// Products with stock > 20
$inStockQ = $conn->query("
    SELECT COUNT(DISTINCT p.product_id) 
    FROM products p 
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id 
    WHERE ps.product_size_id IS NOT NULL 
    GROUP BY p.product_id 
    HAVING COALESCE(SUM(ps.stock_quantity), 0) > 20
")->fetch_row()[0] ?? 0;

// Products with stock between 1-20
$lowStockQ = $conn->query("
    SELECT COUNT(DISTINCT p.product_id) 
    FROM products p 
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id 
    WHERE ps.product_size_id IS NOT NULL 
    GROUP BY p.product_id 
    HAVING COALESCE(SUM(ps.stock_quantity), 0) BETWEEN 1 AND 20
")->fetch_row()[0] ?? 0;

// Products with stock = 0
$outStockQ = $conn->query("
    SELECT COUNT(DISTINCT p.product_id) 
    FROM products p 
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id 
    WHERE ps.product_size_id IS NOT NULL 
    GROUP BY p.product_id 
    HAVING COALESCE(SUM(ps.stock_quantity), 0) = 0
")->fetch_row()[0] ?? 0;

// For products that have no size entries at all (completely out of stock)
$noSizeQ = $conn->query("
    SELECT COUNT(*) 
    FROM products p 
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id 
    WHERE ps.product_size_id IS NULL
")->fetch_row()[0] ?? 0;

// Add products with no sizes to out of stock count
$outStockQ += $noSizeQ;

$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$products_per_page = 10;
$offset = ($current_page - 1) * $products_per_page;

$products = $conn->query("
    SELECT p.product_id, p.product_name, p.image_url, c.category_name, p.price,
           COALESCE(SUM(ps.stock_quantity), 0) AS total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
    GROUP BY p.product_id, p.product_name, p.image_url, c.category_name, p.price
    ORDER BY p.product_id ASC
    LIMIT $offset, $products_per_page
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products & Stock • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="products.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
        <div class="flash-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="flash-message flash-success show">
                    <span class="material-icons">check_circle</span>
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="flash-message flash-error show">
                    <span class="material-icons">error</span>
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="header-section">
            <h1 class="page-title">Products & Stock</h1>
            <a href="add_product.php" class="add-btn">+ Add New Product</a>
        </div>
        <div class="stats-grid">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Products</p>
                <p class="stat-value"><?= $totalQ ?></p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">In Stock</p>
                <p class="stat-value green"><?= $inStockQ ?></p>
            </div>
            <div class="stat-card amber-orange">
                <p class="stat-label">Low Stock</p>
                <p class="stat-value orange"><?= $lowStockQ ?></p>
            </div>
            <div class="stat-card red-rose">
                <p class="stat-label">Out of Stock</p>
                <p class="stat-value red"><?= $outStockQ ?></p>
            </div>
        </div>

        <div class="filters-section">
            <div class="filters-grid">
                <div class="search-wrapper">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" placeholder="Search products..." class="search-input">
                </div>
                <select class="filter-select" id="category-filter">
                    <option value="All Categories">All Categories</option>
                    <?php
                        $categories = $conn->query("SELECT category_name FROM categories ORDER BY category_name");
                        while ($cat = $categories->fetch_assoc()): 
                    ?>
                    <option value="<?= htmlspecialchars($cat['category_name']) ?>">
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                     <?php endwhile; ?>
                </select>
                <select class="filter-select" id="stock-filter">
                    <option value="All Stock Status">All Stock Status</option>
                    <option value="In Stock">In Stock</option>
                    <option value="Low Stock">Low Stock</option>
                    <option value="Out of Stock">Out of Stock</option>
                </select>
                <select class="filter-select" id="sort-filter">
                    <option value="Sort by: Name A-Z">Sort by: Name A-Z</option>
                    <option value="Sort by: Newest">Sort by: Date Added</option>
                    <option value="Sort by: Price Low-High">Sort by: Price Low-High</option>
                    <option value="Sort by: Stock Level">Sort by: Stock Level</option>
                </select>
            </div>
        </div>


        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th>
                        <th> 
                            <div class="actions-cell" id="header-actions">
                                <!-- View -->
                                <button class="action-btn view disabled" title="View" id="btn-view">
                                    <span class="material-icons">visibility</span>
                                </button>

                                <!-- Edit -->
                                <button class="action-btn edit disabled" title="Edit" id="btn-edit">
                                    <span class="material-icons">edit</span>
                                </button>

                                <!-- Delete -->
                                <button class="action-btn delete disabled" title="Delete" id="btn-delete">
                                    <span class="material-icons">delete</span>
                                </button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $products->fetch_assoc()): 
                        $status = $p['total_stock'] > 20 ? 'in-stock' : ($p['total_stock'] > 0 ? 'low-stock' : 'out-of-stock');
                        $statusText = $p['total_stock'] > 20 ? 'In Stock' : ($p['total_stock'] > 0 ? 'Low Stock' : 'Out of Stock');
                        $img = $p['image_url'] ?: 'https://via.placeholder.com/80/7c3aed/ec4899?text=' . urlencode(substr($p['product_name'],0,2));
                    ?>
                    <tr product-id="<?= $p['product_id'] ?>">
                        <td>
                            <div class="product-cell">
                                <div 
                                    class="product-icon" 
                                    style="background-image: url('<?= htmlspecialchars($img) ?>');">
                                </div>
                                <div><p class="product-name"><?= htmlspecialchars($p['product_name']) ?></p></div>
                            </div>
                        </td>
                        <td class="category-text"><?= htmlspecialchars($p['category_name']) ?></td>
                        <td class="price-text">₱<?= number_format($p['price'], 2) ?></td>
                        <td><span class="stock-text <?= $p['total_stock'] > 20 ? 'green' : 'orange' ?>"><?= $p['total_stock'] ?> units</span></td>
                        <td><span class="status-badge <?= $status ?>"><?= $statusText ?></span></td>
                        <td><input type="checkbox" class="select-product"></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="pagination-section">
                <?php
                // Pagination variables
                $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $products_per_page = 10;
                $total_pages = ceil($totalQ / $products_per_page);
                
                // Calculate showing range
                $start_item = (($current_page - 1) * $products_per_page) + 1;
                $end_item = min($current_page * $products_per_page, $totalQ);
                ?>
                
                <p class="pagination-info">
                    Showing <span><?= $start_item ?>-<?= $end_item ?></span> of <span><?= $totalQ ?></span> products
                </p>
                
                <?php if ($total_pages > 1): ?>
                <div>
                    <!-- Previous Button -->
                    <button class="pagination-btn <?= $current_page == 1 ? 'disabled' : '' ?>" 
                            <?= $current_page == 1 ? 'disabled' : '' ?>
                            onclick="changePage(<?= $current_page - 1 ?>)">
                        Previous
                    </button>
                    
                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <button class="pagination-btn active"><?= $i ?></button>
                        <?php else: ?>
                            <button class="pagination-btn" onclick="changePage(<?= $i ?>)"><?= $i ?></button>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <!-- Next Button -->
                    <button class="pagination-btn <?= $current_page == $total_pages ? 'disabled' : '' ?>" 
                            <?= $current_page == $total_pages ? 'disabled' : '' ?>
                            onclick="changePage(<?= $current_page + 1 ?>)">
                        Next
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- ====================== DELETE CONFIRMATION MODAL ====================== -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8 max-w-md w-full mx-4 backdrop-blur-xl">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-white">Confirm Delete</h2>
                <button onclick="closeModal('deleteModal')" class="text-gray-400 hover:text-white">
                    <span class="material-icons">close</span>
                </button>
            </div>

            <div class="mb-8">
                <div class="flex items-center justify-center w-16 h-16 bg-red-900 bg-opacity-20 rounded-full mx-auto mb-4">
                    <span class="material-icons text-red-500 text-3xl">delete_forever</span>
                </div>
                <p id="deleteMessage" class="text-gray-300 text-center text-lg"></p>
            </div>

            <div class="flex justify-end gap-4">
                <button onclick="closeModal('deleteModal')" class="px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition font-medium">
                    Cancel
                </button>
                <button id="confirmDelete" class="px-6 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-lg font-semibold hover:opacity-90 transition">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <script src="product.js"></script>
    <script>
        // Auto-hide flash messages after 3 seconds with fade-out
        document.querySelectorAll('.flash-message.show').forEach(msg => {
            // Remove "show" class after 3 seconds → triggers fade-out
            setTimeout(() => {
                msg.classList.remove('show');
                // Remove from DOM after transition ends
                msg.addEventListener('transitionend', () => msg.remove());
            }, 3000);
        });

        function changePage(page) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', page);
            window.location.href = '?' + urlParams.toString();
        }

        // Auto-hide flash messages after 3 seconds with fade-out
        document.querySelectorAll('.flash-message.show').forEach(msg => {
            setTimeout(() => {
                msg.classList.remove('show');
                msg.addEventListener('transitionend', () => msg.remove());
            }, 3000);
        });
    </script>
</body>
</html>