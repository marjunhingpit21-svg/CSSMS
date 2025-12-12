<?php
session_start();
require_once '../includes/db.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sales Analytics</title>
    <link rel="stylesheet" href="../css/adminheader.css"/>
    <link rel="stylesheet" href="../css/sidebar.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="sales.css"/>
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

        <div class="card forecast-card">
            <h3><i class="fas fa-chart-line"></i> AI Demand Forecast</h3>
            <div class="metric-value" id="forecastTotal">487</div>
            <small id="forecastChange">+18% from last 30 days</small>
            <div class="chart-container" style="margin-top: 20px;">
                <canvas id="forecastChart"></canvas>
            </div>
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

// Load Forecast
fetch('../includes/forecast.json?' + Date.now())  // bypass cache
    .then(r => r.json())
    .then(f => {
        document.getElementById('forecastTotal').textContent = f.next_30_days_total.toLocaleString() + " items";
        const changeEl = document.getElementById('forecastChange');
        const change = f.vs_last_30_days;
        changeEl.textContent = (change > 0 ? "+" : "") + change + " from last 30 days";
        changeEl.style.color = change > 0 ? "#4caf50" : "#f44336";

        new Chart(document.getElementById('forecastChart'), {
            type: 'line',
            data: {
                labels: f.daily.map(d => d.date.split('-')[2] + '/' + d.date.split('-')[1]), // show day/month
                datasets: [{
                    label: 'Predicted Daily Sales',
                    data: f.daily.map(d => d.predicted),
                    borderColor: '#e91e63',
                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#e91e63',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false }
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        ticks: { color: '#666' },
                        grid: { color: '#e5e7eb' }
                    }, 
                    x: { 
                        ticks: { color: '#666' },
                        grid: { color: '#e5e7eb' }
                    } 
                }
            }
        });
    });
</script>

</body>
</html>