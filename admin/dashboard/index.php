<?php 
include '../includes/auth.php';
include '../includes/db.php';

// Dashboard statistics queries
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$total_sales = $conn->query("SELECT COUNT(*) as count FROM sales WHERE payment_status = 'completed'")->fetch_assoc()['count'];
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM product_sizes WHERE stock_quantity BETWEEN 1 AND 20 AND is_available = 1")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE payment_status = 'completed'")->fetch_assoc()['revenue'];

// Monthly sales data for chart
$monthly_sales = $conn->query("
    SELECT 
        DATE_FORMAT(sale_date, '%Y-%m') as month,
        COUNT(*) as transactions,
        SUM(total_amount) as revenue
    FROM sales 
    WHERE payment_status = 'completed'
    GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");

// Top Selling Products in Stores Nationwide (from sales table)
$top_store_products = $conn->query("
    SELECT 
        p.product_name,
        c.category_name,
        SUM(si.quantity) AS units_sold,
        SUM(si.total) AS total_revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
    JOIN products p ON ps.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE s.payment_status = 'completed'
      AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.product_id, p.product_name, c.category_name
    ORDER BY units_sold DESC
    LIMIT 10
");

// Top Selling Products Online (from orders table - delivered orders only)
$top_online_products = $conn->query("
    SELECT 
        p.product_name,
        c.category_name,
        SUM(oi.quantity) AS units_sold,
        SUM(oi.subtotal) AS total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN inventory i ON oi.inventory_id = i.inventory_id
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE o.status IN ('delivered', 'completed', 'received')
      AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.product_id, p.product_name, c.category_name
    ORDER BY units_sold DESC
    LIMIT 10
");

// Prepare monthly sales data for chart
$months = [];
$revenues = [];
while ($row = $monthly_sales->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $revenues[] = (float)$row['revenue'];
}
$months = array_reverse($months);
$revenues = array_reverse($revenues);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • TrendyWear</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Top Selling Products Charts */
        .top-products-section {
            margin-top: 40px;
            margin-bottom: 40px;
        }
        
        .top-products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-top: 20px;
        }
        
        @media (max-width: 1024px) {
            .top-products-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .top-products-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }
        
        .top-products-card h3 {
            font-size: 1.25rem;
            color: #e91e63;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .top-products-card h3 .material-icons {
            font-size: 1.5rem;
        }
        
        .product-subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 20px;
            margin-top: -10px;
        }
        
        .product-list {
            max-height: 450px;
            overflow-y: auto;
            margin-top: 10px;
        }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-item:hover {
            background: #f9f9fc;
        }
        
        .product-info h4 {
            margin: 0 0 4px 0;
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }
        
        .product-info .category {
            color: #666;
            font-size: 0.85rem;
        }
        
        .product-stats {
            text-align: right;
        }
        
        .units-sold {
            font-weight: 700;
            color: #e91e63;
            font-size: 1.1rem;
        }
        
        .revenue {
            color: #666;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        
        .no-data {
            text-align: center;
            padding: 30px 20px;
            color: #999;
        }
        
        .no-data .material-icons {
            font-size: 48px;
            margin-bottom: 12px;
            color: #e0e0e0;
        }
        
        /* Product ranking number */
        .product-rank {
            background: #e91e63;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .online-rank {
            background: #1976d2;
        }
        
        .product-info-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Fixed Chart Layout */
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
            margin-bottom: 24px;
        }

        .chart-card h2 {
            font-size: 1.25rem;
            color: #e91e63;
            margin-bottom: 20px;
            font-weight: 600;
        }

        #salesChart {
            width: 100% !important;
            height: 350px !important;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <h1 class="page-title">Dashboard Overview</h1>
            <p class="header-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>! Here's what's happening.</p>
        </div>

        <!-- KPI Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Products</div>
                <div class="stat-value"><?= number_format($total_products) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Sales</div>
                <div class="stat-value"><?= number_format($total_sales) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Low Stock Items</div>
                <div class="stat-value"><?= number_format($low_stock_count) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">₱<?= number_format($total_revenue, 2) ?></div>
            </div>
        </div>

        <!-- Monthly Sales Chart (Full Width) -->
        <div class="chart-card">
            <h2>Monthly Sales Trend</h2>
            <canvas id="salesChart"></canvas>
        </div>

        <!-- Quick Actions (COMMENTED OUT) -->
        <!--
        <div class="chart-card">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <a href="../products/index.php" class="action-btn">
                    <span class="material-icons">inventory</span>
                    Manage Products
                </a>
                <a href="../inventory/index.php" class="action-btn">
                    <span class="material-icons">warning</span>
                    View Low Stock
                </a>
                <a href="../analytics/sales_index.php" class="action-btn">
                    <span class="material-icons">trending_up</span>
                    Sales Analytics
                </a>
                <a href="../suppliers/index.php" class="action-btn">
                    <span class="material-icons">local_shipping</span>
                    Manage Suppliers
                </a>
            </div>
        </div>
        -->

        <!-- Top Selling Products Section -->
        <div class="top-products-section">
            <div class="header-section">
                <h2 class="page-title">Top Selling Products</h2>
                <p class="header-subtitle">Best performing products in the last 30 days</p>
            </div>
            
            <div class="top-products-grid">
                <!-- Top Selling Products in Stores Nationwide -->
                <div class="top-products-card">
                    <h3>
                        <span class="material-icons">storefront</span>
                        Top Selling Products in Stores Nationwide
                    </h3>
                    <p class="product-subtitle">Based on completed in-store sales</p>
                    
                    <div class="product-list">
                        <?php if ($top_store_products && $top_store_products->num_rows > 0): ?>
                            <?php $rank = 1; ?>
                            <?php while ($product = $top_store_products->fetch_assoc()): ?>
                                <div class="product-item">
                                    <div class="product-info-container">
                                        <div class="product-rank"><?= $rank ?></div>
                                        <div class="product-info">
                                            <h4><?= htmlspecialchars($product['product_name']) ?></h4>
                                            <span class="category"><?= htmlspecialchars($product['category_name']) ?></span>
                                        </div>
                                    </div>
                                    <div class="product-stats">
                                        <div class="units-sold"><?= number_format($product['units_sold']) ?> units</div>
                                        <div class="revenue">₱<?= number_format($product['total_revenue'], 2) ?></div>
                                    </div>
                                </div>
                                <?php $rank++; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <span class="material-icons">storefront</span>
                                <h3>No store sales data</h3>
                                <p>No completed in-store sales in the last 30 days.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Selling Products Online -->
                <div class="top-products-card">
                    <h3>
                        <span class="material-icons">shopping_cart</span>
                        Top Selling Products Online
                    </h3>
                    <p class="product-subtitle">Based on delivered/completed online orders</p>
                    
                    <div class="product-list">
                        <?php if ($top_online_products && $top_online_products->num_rows > 0): ?>
                            <?php $rank = 1; ?>
                            <?php while ($product = $top_online_products->fetch_assoc()): ?>
                                <div class="product-item">
                                    <div class="product-info-container">
                                        <div class="product-rank online-rank"><?= $rank ?></div>
                                        <div class="product-info">
                                            <h4><?= htmlspecialchars($product['product_name']) ?></h4>
                                            <span class="category"><?= htmlspecialchars($product['category_name']) ?></span>
                                        </div>
                                    </div>
                                    <div class="product-stats">
                                        <div class="units-sold"><?= number_format($product['units_sold']) ?> units</div>
                                        <div class="revenue">₱<?= number_format($product['total_revenue'], 2) ?></div>
                                    </div>
                                </div>
                                <?php $rank++; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <span class="material-icons">shopping_cart</span>
                                <h3>No online sales data</h3>
                                <p>No delivered online orders in the last 30 days.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Monthly Sales Chart
        const salesChart = new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: <?= json_encode($revenues) ?>,
                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                    borderColor: '#e91e63',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#e91e63',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                weight: '600'
                            },
                            color: '#333',
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            },
                            font: {
                                size: 12
                            },
                            color: '#666',
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            color: '#666',
                            padding: 10
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>