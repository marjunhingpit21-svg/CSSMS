<?php 
include '../includes/auth.php';
include '../includes/db.php';

$total_revenue = 0;
$q = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM sales");
if ($q) $total_revenue = (float)$q->fetch_row()[0];

$total_orders = 0;
$q = $conn->query("SELECT COUNT(*) FROM sales");
if ($q) $total_orders = (int)$q->fetch_row()[0];

$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

$gross_profit = 0;
$q = $conn->query("
    SELECT COALESCE(SUM(si.quantity * (si.price_at_sale - COALESCE(p.cost_price,0))),0)
    FROM sale_items si
    JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
    JOIN products p ON ps.product_id = p.product_id
");
if ($q) $gross_profit = (float)$q->fetch_row()[0];


$monthly_data = array_fill(0, 12, 0);
$q = $conn->query("
    SELECT MONTH(created_at) AS m, COALESCE(SUM(total_amount),0) AS v
    FROM sales 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY m ORDER BY m
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $monthly_data[$row['m'] - 1] = (float)$row['v'];
    }
}


$top_products = [];
$q = $conn->query("
    SELECT p.product_name, COALESCE(SUM(si.quantity),0) AS units
    FROM sale_items si
    JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
    JOIN products p ON ps.product_id = p.product_id
    GROUP BY p.product_id
    ORDER BY units DESC LIMIT 10
");
if ($q) {
    while ($row = $q->fetch_assoc()) $top_products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & AI • Altiere Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <h1 class="page-title">Analytics & AI</h1>
            <p class="header-subtitle">Real-time business insights</p>
        </div>

      
        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">₱<?= number_format($total_revenue, 0) ?></p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Total Orders</p>
                <p class="stat-value"><?= number_format($total_orders) ?></p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Avg Order Value</p>
                <p class="stat-value">₱<?= number_format($avg_order_value, 0) ?></p>
            </div>
            <div class="stat-card">
                <p class="stat-label">Gross Profit</p>
                <p class="stat-value">₱<?= number_format($gross_profit, 0) ?></p>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h2>Monthly Sales Trend</h2>
                <canvas id="monthlyChart" height="300"></canvas>
            </div>
            <div class="chart-card">
                <h2>Top Selling Products</h2>
                <canvas id="topProductsChart" height="300"></canvas>
            </div>
        </div>

    
        <div class="quick-actions">
            <a href="../products/index.php" class="action-btn">
                <span class="material-icons">inventory_2</span> Products
            </a>
            <a href="../suppliers/" class="action-btn">
                <span class="material-icons">local_shipping</span> Suppliers
            </a>
            <a href="../reports/" class="action-btn">
                <span class="material-icons">bar_chart</span> Reports
            </a>
        </div>
    </main>

    <script>
    
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($monthly_data) ?>,
                borderColor: '#e91e63',
                backgroundColor: 'rgba(233,30,99,0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    
    new Chart(document.getElementById('topProductsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($top_products, 'product_name') ?: []) ?>,
            datasets: [{
                label: 'Units Sold',
                data: <?= json_encode(array_column($top_products, 'units') ?: []) ?>,
                backgroundColor: '#e91e63',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
    </script>
</body>
</html>