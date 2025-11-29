<?php include '../includes/auth.php'; ?>
<?php include '../db.php'; ?>

<?php
// Get supplier ID from URL
$supplier_id = $_GET['id'] ?? 0;

try {
    // Get supplier details
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    if (!$supplier) {
        die("Supplier not found");
    }
    
    // Get supplier products count
    $products_stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE product_id = ?");
    $products_stmt->bind_param("i", $supplier_id);
    $products_stmt->execute();
    $products_result = $products_stmt->get_result();
    $product_count = $products_result->fetch_assoc()['product_count'];
    
    // Mock data for demonstration (in real app, you'd calculate these from order history)
    $on_time_rate = 99.5;
    $avg_lead_time = 5;
    $total_spent = 248920;
    $quality_score = 98;
    $communication_score = 96;
    $defect_rate = 0.8;
    $overall_rating = 5.0;
    $review_count = 142;
    
} catch (Exception $e) {
    die("Error loading supplier details: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($supplier['supplier_name']) ?> • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../products/products.css">
    <style>
        .performance-bar { height: 8px; border-radius: 4px; background: rgba(255,255,255,0.1); }
        .performance-fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
        .excellent { background: linear-gradient(to right, #10b981, #34d399); }
        .good { background: linear-gradient(to right, #f59e0b, #fbbf24); }
        .needs-review { background: linear-gradient(to right, #ef4444, #f87171); }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1 class="page-title"><?= htmlspecialchars($supplier['supplier_name']) ?></h1>
                <p class="text-gray-400 mt-2 text-lg">Supplier ID: <span class="text-white font-semibold">#SUP-<?= str_pad($supplier['supplier_id'], 3, '0', STR_PAD_LEFT) ?></span></p>
            </div>
            <div class="flex gap-4">
                <button class="add-btn text-sm px-8" onclick="contactSupplier()">Contact Supplier</button>
                <button class="bg-white/10 backdrop-blur-xl border border-white/20 px-8 py-4 rounded-xl font-bold hover:bg-white/20 transition" onclick="editSupplier()">
                    Edit Supplier
                </button>
            </div>
        </div>

        <!-- Supplier Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Products Supplied</p>
                <p class="stat-value"><?= $product_count ?></p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">On-Time Delivery Rate</p>
                <p class="stat-value green"><?= $on_time_rate ?>%</p>
            </div>
            <div class="stat-card amber-orange">
                <p class="stat-label">Avg Lead Time</p>
                <p class="stat-value orange"><?= $avg_lead_time ?> days</p>
            </div>
            <div class="stat-card red-rose">
                <p class="stat-label">Total Spent (2025)</p>
                <p class="stat-value red">$<?= number_format($total_spent) ?></p>
            </div>
        </div>

        <!-- Supplier Info + Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            <!-- Info Card -->
            <div class="bg-gray-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 lg:col-span-1">
                <h3 class="text-2xl font-extrabold text-white mb-8">Supplier Details</h3>
                <div class="space-y-6 text-left">
                    <div>
                        <p class="text-gray-400 text-sm">Contact Person</p>
                        <p class="text-white font-bold text-lg"><?= htmlspecialchars($supplier['contact_person']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Email</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($supplier['email']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Phone</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($supplier['phone']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Location</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($supplier['address']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Categories</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="px-4 py-2 bg-violet-500/20 text-violet-300 rounded-full text-sm font-medium">General</span>
                            <span class="px-4 py-2 bg-pink-500/20 text-pink-300 rounded-full text-sm font-medium">Apparel</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Overall Rating</p>
                        <div class="flex items-center gap-3 mt-2">
                            <div class="flex items-center">
                                <span class="text-yellow-400 text-3xl">★</span>
                                <span class="text-3xl font-extrabold text-white ml-1"><?= number_format($overall_rating, 1) ?></span>
                            </div>
                            <span class="text-gray-400">(<?= $review_count ?> reviews)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-gray-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                    <h3 class="text-2xl font-extrabold text-white mb-8">Performance Overview</h3>
                    <div class="space-y-8">
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">Quality Score</span>
                                <span class="text-white font-bold"><?= $quality_score ?>%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: <?= $quality_score ?>%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">On-Time Delivery</span>
                                <span class="text-white font-bold"><?= $on_time_rate ?>%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: <?= $on_time_rate ?>%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">Communication</span>
                                <span class="text-white font-bold"><?= $communication_score ?>%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: <?= $communication_score ?>%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">Defect Rate</span>
                                <span class="text-white font-bold"><?= $defect_rate ?>%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: <?= 100 - $defect_rate ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Lower is better</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                    <h3 class="text-xl font-bold text-white mb-6">Supplier Notes</h3>
                    <p class="text-gray-300 italic">"Reliable supplier with consistent quality. Good communication and on-time delivery."</p>
                    <p class="text-right text-sm text-gray-500 mt-4">— Procurement Team • Last updated: <?= date('M j, Y', strtotime($supplier['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Supplier Products -->
        <div class="filters-section mb-6">
            <h2 class="text-2xl font-extrabold text-white mb-6">Supplier Products</h2>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Cost</th>
                        <th>Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $products_sql = "
                            SELECT 
                                p.product_id,
                                p.product_name,
                                c.category_name,
                                p.price,
                                p.cost_price,
                                COALESCE(SUM(ps.stock_quantity), 0) as total_stock,
                                CASE 
                                    WHEN COALESCE(SUM(ps.stock_quantity), 0) > 20 THEN 'In Stock'
                                    WHEN COALESCE(SUM(ps.stock_quantity), 0) > 0 THEN 'Low Stock'
                                    ELSE 'Out of Stock'
                                END as stock_status
                            FROM products p
                            LEFT JOIN categories c ON p.category_id = c.category_id
                            LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
                            WHERE p.product_id = ?
                            GROUP BY p.product_id
                            ORDER BY p.product_name
                        ";
                        
                        $products_stmt = $conn->prepare($products_sql);
                        $products_stmt->bind_param("i", $supplier_id);
                        $products_stmt->execute();
                        $products_result = $products_stmt->get_result();
                        
                        if ($products_result->num_rows > 0) {
                            while($product = $products_result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td class="font-mono text-violet-400 font-semibold">#<?= $product['product_id'] ?></td>
                        <td class="text-left"><?= htmlspecialchars($product['product_name']) ?></td>
                        <td><?= htmlspecialchars($product['category_name']) ?></td>
                        <td>$<?= number_format($product['price'], 2) ?></td>
                        <td>$<?= number_format($product['cost_price'], 2) ?></td>
                        <td><?= number_format($product['total_stock']) ?> units</td>
                        <td>
                            <span class="status-badge <?= strtolower(str_replace(' ', '-', $product['stock_status'])) ?>">
                                <?= $product['stock_status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php 
                            }
                        } else {
                    ?>
                    <tr>
                        <td colspan="7" class="text-center text-gray-400 py-8">No products found for this supplier</td>
                    </tr>
                    <?php 
                        }
                    } catch (Exception $e) {
                    ?>
                    <tr>
                        <td colspan="7" class="text-center text-red-400 py-8">Error loading products: <?= $e->getMessage() ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function contactSupplier() {
            const email = '<?= $supplier['email'] ?>';
            const subject = 'Inquiry from TrendyWear';
            window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}`;
        }
        
        function editSupplier() {
            alert('Edit supplier functionality would go here');
            // In a real application, this would open a modal or redirect to an edit form
        }
    </script>
</body>
</html>