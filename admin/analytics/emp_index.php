<?php
session_start();


require_once '../includes/db.php';

// Optimized queries using indexes & proper joins
$employees_query = "
    SELECT 
        e.employee_id,
        CONCAT(e.first_name, ' ', e.last_name) AS full_name,
        e.email,
        b.branch_name,
        COALESCE(SUM(s.total_amount), 0) AS total_revenue,
        COALESCE(SUM(si.quantity), 0) AS items_sold,
        COALESCE(SUM(si.quantity * p.cost_price), 0) AS total_cost,
        COALESCE(SUM(s.total_amount) - SUM(si.quantity * p.cost_price), 0) AS total_profit,
        COALESCE(COUNT(DISTINCT s.sale_id), 0) AS transactions,
        COALESCE(ROUND(SUM(s.total_amount) / NULLIF(COUNT(DISTINCT s.sale_id), 0), 2), 0) AS avg_transaction_value
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN sales s ON e.employee_id = s.employee_id
    LEFT JOIN sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
    LEFT JOIN products p ON ps.product_id = p.product_id
    GROUP BY e.employee_id
    ORDER BY total_revenue DESC
";

$employees_result = $conn->query($employees_query);

// Daily performance trend (last 30 days) - optimized with date range
$trend_query = "
    SELECT 
        DATE(s.sale_date) AS sale_date,
        e.employee_id,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        COALESCE(SUM(s.total_amount), 0) AS daily_revenue
    FROM employees e
    LEFT JOIN sales s ON e.employee_id = s.employee_id AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(s.sale_date), e.employee_id
    ORDER BY sale_date DESC
";

$trend_result = $conn->query($trend_query);
$trends = [];
while ($row = $trend_result->fetch_assoc()) {
    $trends[$row['employee_id']]['name'] = $row['employee_name'];
    $trends[$row['employee_id']]['data'][] = [
        'date' => $row['sale_date'],
        'revenue' => (float)$row['daily_revenue']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Analytics</title>
    <link rel="stylesheet" href="../css/adminheader.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        .analytics-container { margin-left: 280px; padding: 100px 30px 30px; transition: margin 0.3s; }
        .admin-sidebar.collapsed ~ .analytics-container { margin-left: 70px; }
        @media (max-width: 768px) { .analytics-container { margin-left: 0 !important; padding-top: 140px; } }

        .page-title { font-size: 28px; color: #e91e63; margin-bottom: 20px; font-weight: 700; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card {
            background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #667eea;
        }
        .stat-card h3 { margin: 0 0 10px; color: #333; font-size: 16px; }
        .stat-value { font-size: 28px; font-weight: 700; color: #e91e63; }

        .table-container { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e91e63; color: white; padding: 15px; text-align: left; }
        td { padding: 14px 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f8f9fa; }
        .rank-badge { background: #e91e63; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }

        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 40px; }
        .chart-box {
            background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        @media (max-width: 992px) { .charts-grid { grid-template-columns: 1fr; } }

        .top-performer { color: white; }
        .top-performer .stat-card {border: 2px solid;border-color: #e91e63; }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <div class="analytics-container">
        <h1 class="page-title"><i class="fas fa-users"></i> Employee Performance Analytics</h1>

        <!-- Top Performer Card -->
        <?php 
        $top_employee = $employees_result->fetch_assoc();
        if ($top_employee && $top_employee['total_revenue'] > 0):
        ?>
        <div class="stats-grid top-performer">
            <div class="stat-card">
                <h3>Top Performer</h3>
                <div class="stat-value"><?= htmlspecialchars($top_employee['full_name']) ?></div>
                <p>Branch: <?= htmlspecialchars($top_employee['branch_name'] ?? 'N/A') ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-value">₱<?= number_format($top_employee['total_revenue'], 2) ?></div>
            </div>
            <div class="stat-card">
                <h3>Items Sold</h3>
                <div class="stat-value"><?= number_format($top_employee['items_sold']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Profit Generated</h3>
                <div class="stat-value">₱<?= number_format($top_employee['total_profit'], 2) ?></div>
            </div>
        </div>
        <?php 
        $employees_result->data_seek(0); // reset pointer
        endif; ?>

        <!-- Employee Ranking Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Employee</th>
                        <th>Branch</th>
                        <th>Revenue</th>
                        <th>Items Sold</th>
                        <th>Transactions</th>
                        <th>Avg Transaction</th>
                        <th>Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    while ($emp = $employees_result->fetch_assoc()): 
                        $profit = $emp['total_revenue'] - $emp['total_cost'];
                    ?>
                    <tr>
                        <td><span class="rank-badge"><?= $rank++ ?></span></td>
                        <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong><br><small><?= htmlspecialchars($emp['email']) ?></small></td>
                        <td><?= htmlspecialchars($emp['branch_name'] ?? '—') ?></td>
                        <td>₱<?= number_format($emp['total_revenue'], 2) ?></td>
                        <td><?= number_format($emp['items_sold']) ?></td>
                        <td><?= $emp['transactions'] ?></td>
                        <td>₱<?= number_format($emp['avg_transaction_value'], 2) ?></td>
                        <td style="color: <?= $profit >= 0 ? 'green' : 'red' ?>; font-weight: bold;">
                            ₱<?= number_format($profit, 2) ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-box">
                <h3>Top 10 Employees by Revenue</h3>
                <canvas id="rankingChart"></canvas>
            </div>
            <div class="chart-box">
                <h3>Daily Sales Trend (Last 30 Days)</h3>
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ranking Bar Chart
            const rankingCtx = document.getElementById('rankingChart').getContext('2d');
            new Chart(rankingCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php 
                        $employees_result->data_seek(0);
                        $count = 0;
                        while ($emp = $employees_result->fetch_assoc() && $count < 10): 
                            echo "'".addslashes($emp['full_name'])."', ";
                            $count++;
                        endwhile; 
                        ?>
                    ],
                    datasets: [{
                        label: 'Total Revenue (₱)',
                        data: [
                            <?php 
                            $employees_result->data_seek(0);
                            $count = 0;
                            while ($emp = $employees_result->fetch_assoc() && $count < 10): 
                                echo $emp['total_revenue'].", ";
                                $count++;
                            endwhile; 
                            ?>
                        ],
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: '#667eea',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: value => '₱' + value.toLocaleString() } }
                    }
                }
            });

            // Daily Trend Line Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const datasets = [];
            const colors = ['#e91e63', '#667eea', '#4caf50', '#ff9800', '#9c27b0', '#00bcd4'];

            <?php 
            $colorIndex = 0;
            foreach ($trends as $emp_id => $data):
                if ($colorIndex >= count($colors)) break;
                if (empty($data['data'])) continue;
            ?>
            datasets.push({
                label: "<?= addslashes($data['name']) ?>",
                data: [
                    <?php 
                    $dates = [];
                    $revenues = [];
                    foreach ($data['data'] as $point) {
                        $dates[] = $point['date'];
                        $revenues[] = $point['revenue'];
                    }
                    echo implode(', ', $revenues);
                    ?>
                ],
                borderColor: "<?= $colors[$colorIndex] ?>",
                backgroundColor: "<?= $colors[$colorIndex] ?>33",
                tension: 0.4,
                fill: false
            });
            <?php $colorIndex++; endforeach; ?>

            new Chart(trendCtx, {
                type: 'line',
                data: { datasets },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { display: true, title: { display: true, text: 'Date' } },
                        y: { beginAtZero: true, ticks: { callback: value => '₱' + value } }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: ctx => `${ctx.dataset.label}: ₱${ctx.parsed.y.toLocaleString()}`
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>