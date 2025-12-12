<?php
session_start();

require_once '../includes/db.php';

$query = "
    SELECT 
        b.branch_id,
        b.branch_name,
        b.city,
        b.address,
        b.phone,
        
        COALESCE(SUM(s.total_amount), 0) AS total_revenue,
        COALESCE(SUM(s.total_amount) - COALESCE(SUM(si.quantity * si.unit_cost), 0), 0) AS total_profit,
        COALESCE(COUNT(DISTINCT s.sale_id), 0) AS total_transactions,
        COALESCE(SUM(si.quantity), 0) AS items_sold,
        
        COALESCE(emp.employee_count, 0) AS employee_count

    FROM branches b
    LEFT JOIN sales s ON b.branch_id = s.branch_id AND s.payment_status = 'completed'
    LEFT JOIN sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN (
        SELECT branch_id, COUNT(*) AS employee_count 
        FROM employees 
        WHERE is_active = 1
        GROUP BY branch_id
    ) emp ON b.branch_id = emp.branch_id
    WHERE b.is_active = 1
    GROUP BY b.branch_id, b.branch_name, b.city, b.address, b.phone, emp.employee_count
    ORDER BY total_revenue DESC
";

$result = $conn->query($query);
if (!$result) die("Query Error: " . $conn->error);

$branches = [];
while ($row = $result->fetch_assoc()) {
    $branches[] = $row;
}

$chartData = array_map(fn($b) => [
    'name' => $b['branch_name'],
    'revenue' => (float)$b['total_revenue']
], $branches);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Analytics</title>
    <link rel="stylesheet" href="../css/adminheader.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="branches.css">
</head>
<body>

<?php include '../adminheader.php'; ?>
<?php include '../sidebar.php'; ?>

<div class="admin-main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Branch Performance Analytics</h1>
            <p>Real-time comparison of revenue, profit, and activity across all branches</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <label for="branchFilter">Filter Branch:</label>
            <select id="branchFilter">
                <option value="all">All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['branch_id'] ?>">
                        <?= htmlspecialchars($b['branch_name']) ?> - <?= htmlspecialchars($b['city']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Summary Cards -->
        <div class="analytics-grid">
            <div class="stat-card">
                <div class="label">Total Revenue</div>
                <div class="value">₱<?= number_format(array_sum(array_column($branches, 'total_revenue')), 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Profit</div>
                <div class="value">₱<?= number_format(array_sum(array_column($branches, 'total_profit')), 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Transactions</div>
                <div class="value"><?= number_format(array_sum(array_column($branches, 'total_transactions'))) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Items Sold</div>
                <div class="value"><?= number_format(array_sum(array_column($branches, 'items_sold'))) ?></div>
            </div>
        </div>

        <!-- Ranking Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Branch Name</th>
                        <th>City</th>
                        <th>Address</th>
                        <th>Revenue</th>
                        <th>Profit</th>
                        <th>Transactions</th>
                        <th>Items Sold</th>
                        <th>Employees</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($branches as $i => $b): ?>
                    <tr data-id="<?= $b['branch_id'] ?>" class="<?= $i===0 ? 'top-rank' : '' ?>">
                        <td><span class="rank"><?= $i + 1 ?></span></td>
                        <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                        <td><?= htmlspecialchars($b['city']) ?></td>
                        <td><?= htmlspecialchars(strlen($b['address']) > 40 ? substr($b['address'],0,40).'...' : $b['address']) ?></td>
                        <td>₱<?= number_format($b['total_revenue'], 2) ?></td>
                        <td>₱<?= number_format($b['total_profit'], 2) ?></td>
                        <td><?= number_format($b['total_transactions']) ?></td>
                        <td><?= number_format($b['items_sold']) ?></td>
                        <td><?= $b['employee_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Revenue Bar Chart -->
        <div class="chart-container">
            <h3>Revenue Comparison by Branch</h3>
            <canvas id="revenueChart" height="120"></canvas>
        </div>
    </div>
</div>

<script>
// Instant client-side filter
document.getElementById('branchFilter').addEventListener('change', function() {
    const val = this.value;
    document.querySelectorAll('#tableBody tr').forEach(row => {
        row.style.display = (val === 'all' || row.dataset.id === val) ? '' : 'none';
    });
});

// Chart.js
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($chartData, 'name')) ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?= json_encode(array_column($chartData, 'revenue')) ?>,
            backgroundColor: '#e91e63',
            borderColor: '#c2185b',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { 
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                borderRadius: 8,
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 }
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                ticks: { 
                    callback: v => '₱' + v.toLocaleString(),
                    font: { size: 12 }
                },
                grid: {
                    color: '#f0f0f0'
                }
            },
            x: {
                ticks: {
                    font: { size: 12 }
                },
                grid: {
                    display: false
                }
            }
        }
    }
});
</script>

</body>
</html>