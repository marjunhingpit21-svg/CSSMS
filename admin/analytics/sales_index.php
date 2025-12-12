<?php
session_start();
require_once '../includes/db.php';

$page_title = "Sales Analytics";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= $page_title ?> - TrendyWear Admin</title>
    <link rel="stylesheet" href="../css/adminheader.css"/>
    <link rel="stylesheet" href="../css/sidebar.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Main container - adjusted for sidebar and header */
        .analytics-container {
            margin-left: 280px;
            margin-top: 70px;
            padding: 40px;
            background: #f9fafb;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.3s ease;
            font-family: 'Inter', 'Poppins', -apple-system, sans-serif;
        }

        /* Adjust for collapsed sidebar */
        .admin-sidebar.collapsed ~ .analytics-container {
            margin-left: 70px;
        }

        /* Page header - matching sidebar styling */
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            color: #e91e63;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        /* Filter group - matching header button styles */
        .filter-group {
            display: flex;
            gap: 10px;
        }

        .filter-group select {
            padding: 10px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            background: white;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', 'Poppins', -apple-system, sans-serif;
        }

        .filter-group select:hover {
            border-color: #e91e63;
            background: #fff5f8;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #e91e63;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        /* Grid layout */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Card styling - matching sidebar card aesthetic */
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        .card h3 {
            margin: 0 0 15px;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Metric values - matching header accent color */
        .metric-value {
            font-size: 36px;
            font-weight: 700;
            color: #e91e63;
            margin-bottom: 8px;
        }

        .card small {
            color: #666;
            font-size: 13px;
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 15px;
        }

        /* Leaderboard table */
        .leaderboard {
            max-height: 400px;
            overflow-y: auto;
        }

        .leaderboard::-webkit-scrollbar {
            width: 6px;
        }

        .leaderboard::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .leaderboard::-webkit-scrollbar-thumb {
            background: #e91e63;
            border-radius: 3px;
        }

        .leaderboard::-webkit-scrollbar-thumb:hover {
            background: #c2185b;
        }

        /* Table styling - consistent with admin theme */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f5f5f5;
        }

        td strong {
            color: #e91e63;
        }

        /* Badges - matching alert styles */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success { 
            background: #d4edda; 
            color: #155724; 
        }

        .badge-warning { 
            background: #fff3cd; 
            color: #856404; 
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .analytics-container {
                margin-left: 240px;
            }

            .admin-sidebar.collapsed ~ .analytics-container {
                margin-left: 60px;
            }
        }

        @media (max-width: 768px) {
            .analytics-container {
                margin-left: 0;
                padding: 20px;
            }

            .admin-sidebar ~ .analytics-container,
            .admin-sidebar.collapsed ~ .analytics-container {
                margin-left: 0;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .metric-value {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .analytics-container {
                padding: 15px;
            }

            .card {
                padding: 20px;
            }

            .chart-container {
                height: 250px;
            }

            th, td {
                padding: 10px 8px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<?php include '../adminheader.php'; ?>
<?php include '../sidebar.php'; ?>

<div class="analytics-container">

    <div class="page-header">
        <h1>Sales Analytics Dashboard</h1>
        <div class="filter-group">
            <select id="timeFilter">
                <option value="week">Last 7 Days</option>
                <option value="month" selected>Last 30 Days</option>
                <option value="year">This Year</option>
            </select>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid">
        <div class="card">
            <h3>Total Revenue</h3>
            <div class="metric-value" id="totalRevenue">₱0</div>
            <small>Last 30 days</small>
        </div>
        <div class="card">
            <h3>Total Profit</h3>
            <div class="metric-value" id="totalProfit">₱0</div>
            <small>Net after cost</small>
        </div>
        <div class="card">
            <h3>Items Sold</h3>
            <div class="metric-value" id="totalItems">0</div>
            <small>Across all categories</small>
        </div>
        <div class="card">
            <h3>Avg Order Value</h3>
            <div class="metric-value" id="avgOrder">₱0</div>
            <small>Per transaction</small>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid" style="grid-template-columns: 1fr 1fr;">
        <div class="card">
            <h3>Sales Trend</h3>
            <div class="chart-container">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
        <div class="card">
            <h3>Profit Trend</h3>
            <div class="chart-container">
                <canvas id="profitTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Performance -->
    <div class="grid" style="grid-template-columns: 1fr 1fr;">
        <div class="card">
            <h3>Category Sales Breakdown</h3>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        <div class="card">
            <h3>Top Selling Products</h3>
            <div class="leaderboard">
                <table id="topProductsTable">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
// Optimized Queries via AJAX
async function loadAnalytics(period = 'month') {
    const res = await fetch(`sales_data.php?period=${period}`);
    const data = await res.json();

    // Update Metrics
    document.getElementById('totalRevenue').textContent = '₱' + Number(data.summary.revenue).toLocaleString();
    document.getElementById('totalProfit').textContent = '₱' + Number(data.summary.profit).toLocaleString();
    document.getElementById('totalItems').textContent = data.summary.items.toLocaleString();
    document.getElementById('avgOrder').textContent = '₱' + Number(data.summary.avg_order).toFixed(0);

    // Sales Trend Chart
    new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: {
            labels: data.trend.labels,
            datasets: [{
                label: 'Revenue (₱)',
                data: data.trend.revenue,
                borderColor: '#e91e63',
                backgroundColor: 'rgba(233, 30, 99, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Profit Trend
    new Chart(document.getElementById('profitTrendChart'), {
        type: 'line',
        data: {
            labels: data.trend.labels,
            datasets: [{
                label: 'Profit (₱)',
                data: data.trend.profit,
                borderColor: '#4caf50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Category Pie Chart
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: data.categories.map(c => c.category_name),
            datasets: [{
                data: data.categories.map(c => c.total_sold),
                backgroundColor: ['#e91e63','#667eea','#4caf50','#ff9800','#9c27b0','#00bcd4']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Top Products Table
    const tbody = document.querySelector('#topProductsTable tbody');
    tbody.innerHTML = '';
    data.top_products.forEach((p, i) => {
        const row = `<tr>
            <td><strong>#${i+1}</strong></td>
            <td>${p.product_name}</td>
            <td>${p.category_name}</td>
            <td><strong>${p.qty}</strong></td>
            <td>₱${Number(p.revenue).toLocaleString()}</td>
        </tr>`;
        tbody.innerHTML += row;
    });
}

// Load on start
loadAnalytics('month');

// Filter change
document.getElementById('timeFilter').addEventListener('change', (e) => {
    loadAnalytics(e.target.value);
});
</script>

</body>
</html>