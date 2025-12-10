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
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 100px 20px 40px; }
        h1 { font-size: 2.4rem; color: #333; margin-bottom: 8px; }
        .analytics-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px; 
            margin: 30px 0; 
        }
        .card { 
            background: white; padding: 24px; border-radius: 16px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center;
        }
        .value { font-size: 2.4rem; font-weight: 700; color: #e91e63; margin: 12px 0; }
        .label { color: #666; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        th { background: #f8f9fa; padding: 18px; text-align: left; font-weight: 600; border-bottom: 3px solid #e91e63; }
        td { padding: 16px; border-bottom: 1px solid #eee; }
        tr:hover { background: #fdf6ff; }
        .rank { background: #e91e63; color: white; width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; }
        .top-rank { background: linear-gradient(135deg, #e91e63, #c2185b) !important; color: white; }
        .top-rank td { color: white; font-weight: 600; }
        .chart-box { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-top: 30px; }
        select { padding: 12px 16px; border: 2px solid #ddd; border-radius: 12px; font-size: 1rem; }
        @media (max-width: 768px) { .container { padding-top: 120px; } }
    </style>
</head>
<body>

<?php include '../adminheader.php'; ?>
<?php include '../sidebar.php'; ?>

<div class="admin-main-content">
    <div class="container">
        <h1>Branch Performance Analytics</h1>
        <p style="color:#777;">Real-time comparison of revenue, profit, and activity across all branches</p>

        <!-- Filter -->
        <div style="margin:25px 0;">
            <label for="branchFilter"><strong>Filter Branch:</strong></label>
            <select id="branchFilter" style="margin-left:10px; width:320px;">
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
            <div class="card">
                <div class="label">Total Revenue</div>
                <div class="value">₱<?= number_format(array_sum(array_column($branches, 'total_revenue')), 2) ?></div>
            </div>
            <div class="card">
                <div class="label">Total Profit</div>
                <div class="value">₱<?= number_format(array_sum(array_column($branches, 'total_profit')), 2) ?></div>
            </div>
            <div class="card">
                <div class="label">Total Transactions</div>
                <div class="value"><?= number_format(array_sum(array_column($branches, 'total_transactions'))) ?></div>
            </div>
            <div class="card">
                <div class="label">Total Items Sold</div>
                <div class="value"><?= number_format(array_sum(array_column($branches, 'items_sold'))) ?></div>
            </div>
        </div>

        <!-- Ranking Table -->
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

        <!-- Revenue Bar Chart -->
        <div class="chart-box">
            <h3 style="margin-bottom:20px; color:#333;">Revenue Comparison by Branch</h3>
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

// Chart.js - super lightweight
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
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } }
        }
    }
});
</script>

</body>
</html>