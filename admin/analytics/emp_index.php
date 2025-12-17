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
    LEFT JOIN sales s ON e.employee_id = s.employee_id 
        AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND s.payment_status = 'completed'
    WHERE s.sale_id IS NOT NULL
    GROUP BY DATE(s.sale_date), e.employee_id
    ORDER BY sale_date ASC, employee_name ASC
";

$trend_result = $conn->query($trend_query);
// Prepare trend data
$trends = [];
$all_dates = [];

// Get unique dates first
$dates_query = "
    SELECT DISTINCT DATE(sale_date) as sale_date 
    FROM sales 
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND payment_status = 'completed'
    ORDER BY sale_date ASC
";
$dates_result = $conn->query($dates_query);
while ($date_row = $dates_result->fetch_assoc()) {
    $all_dates[] = $date_row['sale_date'];
}

// Get employee trend data
$trend_result = $conn->query($trend_query);
while ($row = $trend_result->fetch_assoc()) {
    $emp_id = $row['employee_id'];
    if (!isset($trends[$emp_id])) {
        $trends[$emp_id] = [
            'name' => $row['employee_name'],
            'data' => array_fill_keys($all_dates, 0) // Initialize with 0 for all dates
        ];
    }
    $trends[$emp_id]['data'][$row['sale_date']] = (float)$row['daily_revenue'];
}

sort($all_dates);

// Debug: Check data counts
echo "<!-- Debug Info: -->";
echo "<!-- Employees found: " . $employees_result->num_rows . " -->";
echo "<!-- Trend records: " . $trend_result->num_rows . " -->";
echo "<!-- All dates count: " . count($all_dates) . " -->";
echo "<!-- Trends array count: " . count($trends) . " -->";
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
    <link rel="stylesheet" href="emp.css">
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
                <h3>Total Sales</h3>
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
                        <th>Sales</th>
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
                <h3>Top 10 Employees by Sales</h3>
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
const rankingLabels = [];
const rankingData = [];

<?php 
$employees_result->data_seek(0);
$count = 0;
while ($emp = $employees_result->fetch_assoc()): 
    if ($count >= 10) break;
    // Include even if revenue is 0 to show all employees
?>
    rankingLabels.push('<?= addslashes($emp['full_name']) ?>');
    rankingData.push(<?= $emp['total_revenue'] ?>);
<?php 
    $count++;
endwhile; 
?>

if (rankingLabels.length > 0) {
    new Chart(rankingCtx, {
        type: 'bar',
        data: {
            labels: rankingLabels,
            datasets: [{
                label: 'Total Revenue (₱)',
                data: rankingData,
                backgroundColor: rankingData.map(val => val > 0 ? 'rgba(102, 126, 234, 0.8)' : 'rgba(200, 200, 200, 0.5)'),
                borderColor: rankingData.map(val => val > 0 ? '#667eea' : '#cccccc'),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { 
                legend: { display: false }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    ticks: { 
                        callback: value => '₱' + value.toLocaleString() 
                    } 
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0
                    }
                }
            }
        }
    });
} else {
    document.getElementById('rankingChart').parentElement.innerHTML = 
        '<div style="text-align: center; padding: 40px; color: #666;">' +
        '<i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 15px;"></i><br>' +
        '<h4>No Sales Data Available</h4>' +
        '<p>Start making sales to see employee performance data</p>' +
        '</div>';
}

// Daily Trend Line Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendLabels = <?= json_encode($all_dates) ?>;
const trendDatasets = [];

<?php 
$colors = ['#e91e63', '#667eea', '#4caf50', '#ff9800', '#9c27b0', '#00bcd4'];
$colorIndex = 0;

foreach ($trends as $emp_id => $data):
    if ($colorIndex >= 6) break;
    
    // Check if this employee has any non-zero data
    $hasData = false;
    foreach ($data['data'] as $dateVal) {
        if ($dateVal > 0) {
            $hasData = true;
            break;
        }
    }
    
    if ($hasData):
?>
    trendDatasets.push({
        label: "<?= addslashes($data['name']) ?>",
        data: <?= json_encode(array_values($data['data'])) ?>,
        borderColor: "<?= $colors[$colorIndex] ?>",
        backgroundColor: "<?= $colors[$colorIndex] ?>33",
        tension: 0.4,
        fill: false
    });
<?php 
        $colorIndex++;
    endif;
endforeach; 
?>

if (trendDatasets.length > 0 && trendLabels.length > 0) {
    new Chart(trendCtx, {
        type: 'line',
        data: { 
            labels: trendLabels,
            datasets: trendDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { 
                    display: true, 
                    title: { display: true, text: 'Date' },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: { 
                    beginAtZero: true, 
                    ticks: { 
                        callback: value => '₱' + value.toLocaleString() 
                    } 
                }
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
} else {
    document.getElementById('trendChart').parentElement.innerHTML = 
        '<div style="text-align: center; padding: 40px; color: #666;">' +
        '<i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 15px;"></i><br>' +
        '<h4>No Trend Data Available</h4>' +
        '<p>No sales recorded in the last 30 days</p>' +
        '</div>';
}
        });
    </script>
</body>
</html>