<?php 
include '../includes/auth.php';
include '../includes/db.php';


// ==================== KPI SECTION (100% SAFE) ====================

// Today's Sales
$today_sales = 0.00;
$q = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(created_at) = CURDATE()");
if ($q && $q->num_rows > 0) {
    $today_sales = (float)$q->fetch_assoc()['total'];
}

// Today's Orders
$today_orders = 0;
$q = $conn->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()");
if ($q) {
    $today_orders = (int)$q->fetch_row()[0];
}

// Total Inventory Value (cost price × stock)
$total_stock_value = 0.00;
$q = $conn->query("
    SELECT COALESCE(SUM(ps.stock_quantity * p.cost_price), 0)
    FROM product_sizes ps
    JOIN products p ON ps.product_id = p.product_id
    WHERE ps.is_available = 1
");
if ($q) {
    $total_stock_value = (float)$q->fetch_row()[0];
}

// Low Stock Items (1–20 units)
$low_stock_count = 0;
$q = $conn->query("
    SELECT COUNT(*) 
    FROM product_sizes 
    WHERE stock_quantity BETWEEN 1 AND 20 
      AND is_available = 1
");
if ($q) {
    $low_stock_count = (int)$q->fetch_row()[0];
}

// ==================== MONTHLY SALES CHART DATA ====================
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$sales_values = array_fill(0, 12, 0);

$q = $conn->query("
    SELECT 
        MONTH(created_at) AS m,
        COALESCE(SUM(total_amount), 0) AS value
    FROM sales
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY m
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $sales_values[$row['m'] - 1] = (float)$row['value'];
    }
}

// ==================== TOP 5 PRODUCTS (LAST 30 DAYS) ====================
$top_names = ['No sales data yet'];
$top_units  = [0];

$q = $conn->query("
    SELECT 
        p.product_name,
        COALESCE(SUM(si.quantity), 0) AS units
    FROM sale_items si
    JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
    JOIN products p ON ps.product_id = p.product_id
    WHERE si.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.product_id, p.product_name
    ORDER BY units DESC
    LIMIT 5
");
if ($q && $q->num_rows > 0) {
    $top_names = [];
    $top_units  = [];
    while ($row = $q->fetch_assoc()) {
        $top_names[] = $row['product_name'] ?: 'Unknown Product';
        $top_units[]  = (int)$row['units'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Dashboard • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <h1 class="page-title">Cloud Dashboard</h1>
            <p class="header-subtitle">Real-time business insights & performance</p>
        </div>

        <!-- KPI Cards -->
        <div class="stats-grid">
            <div class="stat-card violet-pink">
                <p class="stat-label">Today's Sales</p>
                <p class="stat-value">₱<?= number_format($today_sales, 2) ?></p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">Today's Orders</p>
                <p class="stat-value"><?= number_format($today_orders) ?></p>
            </div>
            <div class="stat-card amber-orange">
                <p class="stat-label">Inventory Value</p>
                <p class="stat-value">₱<?= number_format($total_stock_value, 2) ?></p>
            </div>
            <div class="stat-card red-rose">
                <p class="stat-label">Low Stock Items</p>
                <p class="stat-value"><?= $low_stock_count ?></p>
                <?php if ($low_stock_count > 0): ?>
                    <span class="text-xs text-red-300 mt-1 block">Requires attention</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h2>Monthly Sales Trend (Last 12 Months)</h2>
                <canvas id="salesChart"></canvas>
            </div>
            <div class="chart-card">
                <h2>Top Selling Products (Last 30 Days)</h2>
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="../products/index.php" class="action-btn">
                <span class="material-icons">inventory_2</span>
                Manage Products
            </a>
            <a href="../suppliers/index.php" class="action-btn">
                <span class="material-icons">local_shipping</span>
                Suppliers 
            </a>
            <a href="#" class="action-btn">
                <span class="material-icons">bar_chart</span>
                Detailed Reports
            </a>
        </div>
    </main>

    <script>
        // Monthly Sales Line Chart
        new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Sales (₱)',
                    data: <?= json_encode($sales_values) ?>,
                    borderColor: '#e91e63',
                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#e91e63'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { callback: value => '₱' + value.toLocaleString() }
                    }
                }
            }
        });

        // Top Products Bar Chart
        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($top_names) ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?= json_encode($top_units) ?>,
                    backgroundColor: '#e91e63',
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>