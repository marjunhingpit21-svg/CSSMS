<?php
session_start();
require_once '../includes/db.php';

// AUTO-RUN FORECAST
$forecast_file = __DIR__ . '/includes/forecast.json';
$script_path   = 'C:/forecast/smart_forecast.py';

if (!file_exists($forecast_file) || (time() - filemtime($forecast_file)) > 7200) {
    if (file_exists($script_path)) {
        shell_exec('"C:/xampp/python/python.exe" "' . $script_path . '" > nul 2>&1');
        sleep(3);
    }
}
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
    </div>

    <!-- AI Forecast Card -->
    <div class="card forecast-card full-width">
        <h3>AI Demand Forecasting</h3>
        
        <div class="ai-header">
            <div>
                <div class="metric-value" id="forecastTotal">—</div>
                <small id="forecastChange">Loading forecast...</small>
            </div>
            <div class="ai-confidence">
                <span>Confidence: <strong id="confidenceLevel">—</strong></span>
                <i class="fas fa-circle" id="confidenceDot"></i>
            </div>
        </div>

        <div class="chart-container" style="margin-top: 20px;">
            <canvas id="forecastChart"></canvas>
        </div>

        <!-- Will be hidden when no forecast data -->
        <div class="ai-insights" id="aiInsightsSection" style="display: none;">
            <h4>AI Insights & Recommendations</h4>
            <ul id="aiRecommendations">
                <li>Loading insights...</li>
            </ul>
        </div>

        <small style="display:block; text-align:right; margin-top:10px; color:#888;">
            Updated <span id="lastUpdated">—</span> • Powered by Prophet AI
        </small>
    </div>

    <!-- Other Charts -->
    <div class="grid" style="grid-template-columns: 1fr 1fr;">
        <div class="card">
            <h3>Sales Trend</h3>
            <div class="chart-container"><canvas id="salesTrendChart"></canvas></div>
        </div>
        <div class="card">
            <h3>Profit Trend</h3>
            <div class="chart-container"><canvas id="profitTrendChart"></canvas></div>
        </div>
    </div>

    <div class="grid" style="grid-template-columns: 1fr 1fr;">
        <div class="card">
            <h3>Category Sales Breakdown</h3>
            <div class="chart-container"><canvas id="categoryChart"></canvas></div>
        </div>
        <div class="card">
            <h3>Top Selling Products</h3>
            <div class="leaderboard">
                <table id="topProductsTable">
                    <thead>
                        <tr><th>Rank</th><th>Product</th><th>Category</th><th>Sold</th><th>Revenue</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // ── Chart instances ──
    let forecastChart = null;
    let salesTrendChart = null;
    let profitTrendChart = null;
    let categoryChart = null;

    const timeFilter = document.getElementById('timeFilter');
    const aiInsightsSection = document.getElementById('aiInsightsSection');

    // ── Map the main filter to forecast periods ──
    function getForecastPeriodFromFilter() {
        const val = timeFilter.value;
        return val === 'week' ? '7' : val === 'month' ? '30' : '365';
    }

    // ── LOAD HISTORICAL ANALYTICS (fixed & safe) ──
    async function loadAnalytics(period = 'month') {
        let data = { summary: {}, charts: {}, top_products: [] };

        try {
            const res = await fetch(`sales_data.php?period=${period}`);
            if (res.ok) data = await res.json();
        } catch (err) {
            console.error('Failed to fetch analytics:', err);
        }

        // Metrics (safe fallbacks)
        document.getElementById('totalRevenue').textContent = 
            '₱' + Number(data.summary?.revenue ?? 0).toLocaleString();
        document.getElementById('totalProfit').textContent = 
            '₱' + Number(data.summary?.profit ?? 0).toLocaleString();
        document.getElementById('totalItems').textContent = 
            (data.summary?.items ?? 0).toLocaleString();
        document.getElementById('avgOrder').textContent = 
            '₱' + Number(data.summary?.avg_order ?? 0).toFixed(0);

        // Destroy old charts safely
        [salesTrendChart, profitTrendChart, categoryChart].forEach(c => c?.destroy());

        // Empty chart fallback
        const emptyLine = {
            type: 'line',
            data: { labels: [], datasets: [{ label: 'No data', data: [] }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        };
        const emptyDoughnut = {
            type: 'doughnut',
            data: { labels: ['No data'], datasets: [{ data: [1], backgroundColor: ['#e0e0e0'] }] },
            options: { plugins: { legend: { display: false } } }
        };

        salesTrendChart = new Chart(document.getElementById('salesTrendChart'), data.charts?.sales_trend ?? emptyLine);
        profitTrendChart = new Chart(document.getElementById('profitTrendChart'), data.charts?.profit_trend ?? emptyLine);
        categoryChart = new Chart(document.getElementById('categoryChart'), data.charts?.category ?? emptyDoughnut);

        // Top products table
        const tbody = document.querySelector('#topProductsTable tbody');
        tbody.innerHTML = '';
        if (Array.isArray(data.top_products) && data.top_products.length) {
            data.top_products.forEach((p, i) => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>#${i+1}</strong></td>
                        <td>${p.product_name || '—'}</td>
                        <td>${p.category_name || '—'}</td>
                        <td><strong>${p.qty || 0}</strong></td>
                        <td>₱${Number(p.revenue || 0).toLocaleString()}</td>
                    </tr>`;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#999;padding:30px;">No sales in this period</td></tr>`;
        }
    }

    // ── LOAD AI FORECAST (fully intact + hide insights when no data) ──
    function loadForecast() {
        const periodKey = getForecastPeriodFromFilter();

        fetch('../includes/forecast.json?v=' + Date.now())
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(f => {
                if (forecastChart) forecastChart.destroy();

                // No forecast data available
                if (!f?.forecasts || f.status === "no_data") {
                    document.querySelector('.forecast-card .chart-container').innerHTML = `
                        <div style="text-align:center;padding:80px;color:#999;font-size:1.2em;">
                            ${f?.message || "Not enough sales data yet for AI forecasting"}
                        </div>`;
                    document.getElementById('forecastTotal').textContent = '—';
                    document.getElementById('forecastChange').textContent = 'Awaiting sales data';
                    document.getElementById('confidenceLevel').textContent = '—';
                    aiInsightsSection.style.display = 'none';
                    return;
                }

                const data = f.forecasts[periodKey];
                const isYear = periodKey === '365';

                // Header numbers
                document.getElementById('forecastTotal').textContent = Number(data.total).toLocaleString() + ' items';
                document.getElementById('forecastChange').textContent = 
                    (data.growth > 0 ? '+' : '') + data.growth + '% vs last ' + (isYear ? 'year' : periodKey + ' days');
                document.getElementById('forecastChange').style.color = data.growth >= 0 ? '#4caf50' : '#f44336';

                document.getElementById('confidenceLevel').textContent = data.confidence + '%';
                document.getElementById('confidenceDot').style.color = 
                    data.confidence >= 90 ? '#4caf50' : data.confidence >= 75 ? '#ff9800' : '#f44336';

                // Forecast chart
                forecastChart = new Chart(document.getElementById('forecastChart'), {
                    type: 'line',
                    data: {
                        labels: data.daily.map(d => 
                            isYear 
                                ? d.date.slice(5,10).replace('-','/') + '/' + d.date.slice(2,4)
                                : d.date.slice(8) + '/' + d.date.slice(5,7)
                        ),
                        datasets: [{
                            label: 'Demand',
                            data: data.daily.map(d => d.predicted),
                            borderColor: '#e91e63',
                            backgroundColor: 'rgba(233,30,99,0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: isYear ? 0 : 4,
                            pointBackgroundColor: '#e91e63'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // Show AI insights only when forecast exists
                const ul = document.getElementById('aiRecommendations');
                ul.innerHTML = '';
                f.insights.forEach(text => {
                    const li = document.createElement('li');
                    li.textContent = text;
                    ul.appendChild(li);
                });
                aiInsightsSection.style.display = 'block';

                // Last updated time
                document.getElementById('lastUpdated').textContent = 
                    new Date(f.generated_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            })
            .catch(err => {
                console.warn('Forecast load failed:', err);
                document.querySelector('.forecast-card .chart-container').innerHTML = 
                    '<div style="text-align:center;padding:60px;color:#aaa;">Forecast temporarily unavailable</div>';
                aiInsightsSection.style.display = 'none';
            });
    }

    // ── INITIAL LOAD ──
    loadAnalytics('month');
    loadForecast();

    // ── SINGLE FILTER CONTROLS EVERYTHING ──
    timeFilter.addEventListener('change', () => {
        const period = timeFilter.value;
        loadAnalytics(period);
        loadForecast();
    });
</script>

</body>
</html>