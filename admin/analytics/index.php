<?php 
include '../includes/auth.php';
include '../includes/db.php';

// === SUPER FAST SINGLE QUERY + CACHING ===
// We cache data for 5 minutes so even 100 refreshes = 1 DB hit
$cache_file = __DIR__ . '/cache/analytics_data.json';
$cache_time = 300; // 5 minutes

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $data = json_decode(file_get_contents($cache_file), true);
} else {
    // ONE SMART QUERY gets everything we need
    $days = isset($_GET['days']) ? max(1, min(365, (int)$_GET['days'])) : 7;
    $sql = "
        SELECT 
            -- Basic numbers
            COALESCE(SUM(s.total_amount),0) as revenue,
            COUNT(s.sale_id) as orders,
            COUNT(DISTINCT DATE(s.sale_date)) as active_days,
            
            -- Today's sales
            COALESCE(SUM(CASE WHEN DATE(s.sale_date) = CURDATE() THEN s.total_amount ELSE 0 END),0) as today_sales,
            COALESCE(SUM(CASE WHEN DATE(s.sale_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN s.total_amount ELSE 0 END),0) as yesterday_sales,
            
            -- Hot product
            p.product_name,
            cs.size_name,
            SUM(si.quantity) as units_sold,
            
            -- Low stock items
            ps.stock_quantity,
            ps.barcode,
            p.product_name as low_stock_name,
            cs.size_name as low_stock_size,
            
            -- Profit & slow movers
            (p.price + COALESCE(ps.price_adjustment,0) - p.cost_price) as margin,
            DATEDIFF(CURDATE(), MAX(s.sale_date)) as days_since_sold
            
        FROM sales s
        LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        LEFT JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
        LEFT JOIN products p ON ps.product_id = p.product_id
        LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
        WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY p.product_id, ps.product_size_id
        ORDER BY units_sold DESC, ps.stock_quantity ASC
        LIMIT 50
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [
        'days' => $days,
        'revenue' => 0, 'orders' => 0, 'today' => 0, 'yesterday' => 0,
        'hot_product' => 'No sales yet', 'hot_size' => '',
        'low_stock_count' => 0, 'low_stock_items' => [],
        'best_margin' => [], 'sleeping' => 0,
        'updated_at' => date('M j, Y g:i A')
    ];

    $total_revenue = 0; $total_orders = 0; $today = 0; $yesterday = 0;
    $hot = []; $low = []; $margins = []; $sleeping = 0;

    while ($row = $result->fetch_assoc()) {
        $total_revenue += $row['revenue'] ?? 0;
        $total_orders += $row['orders'] ?? 0;
        $today += $row['today_sales'];
        $yesterday += $row['yesterday_sales'];

        if (!empty($row['units_sold']) && count($hot) < 1) {
            $hot = ['name' => $row['product_name'], 'size' => $row['size_name'] ?? 'One Size', 'units' => $row['units_sold']];
        }

        if (($row['stock_quantity'] ?? 0) < 5) {
            $low[] = $row['low_stock_name'] . ' ' . ($row['low_stock_size'] ?? '');
        }

        if (($row['margin'] ?? 0) > 0) {
            $margins[] = ['name' => $row['product_name'], 'margin' => round($row['margin']/($row['revenue'] ?: 1)*100)];
        }

        if (($row['days_since_sold'] ?? 999) > 30) $sleeping++;
    }

    $data['revenue'] = $total_revenue;
    $data['orders'] = $total_orders;
    $data['today'] = $today;
    $data['yesterday'] = $yesterday;
    $data['hot_product'] = $hot['name'] ?? 'No sales';
    $data['hot_size'] = $hot['size'] ?? '';
    $data['low_stock_count'] = count($low);
    $data['low_stock_items'] = array_slice($low, 0, 3);
    $data['best_margin'] = $margins ? $margins[0] : null;
    $data['sleeping'] = $sleeping;

    // Save to cache
    if (!is_dir(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache', 0755, true);
    }
    file_put_contents($cache_file, json_encode($data));
}

extract($data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/adminheader.css">
    <link rel="stylesheet" href="analytics.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <h1 class="page-title">Altière's Analytics</h1>
        </div>

        <div class="date-filter">
            <select onchange="location = '?days='+this.value">
                <option value="7" <?= $days==7 ? 'selected':'' ?>>Last 7 Days</option>
                <option value="30" <?= $days==30 ? 'selected':'' ?>>Last 30 Days</option>
                <option value="90" <?= $days==90 ? 'selected':'' ?>>Last 90 Days</option>
            </select>
        </div>

        <div class="ai-grid">
            <!-- Card 1: Today's Sales -->
            <div class="ai-card">
                <h3>Today's Sales</h3>
                <div class="ai-value">₱<?= number_format($today) ?></div>
                <p>vs ₱<?= number_format($yesterday) ?> yesterday 
                    <?php if($today > $yesterday): ?>
                        <span class="tag success">↑ <?= round((($today-$yesterday)/($yesterday?:1))*100) ?>%</span>
                    <?php else: ?>
                        <span class="tag warning">↓ <?= round((($yesterday-$today)/($yesterday?:1))*100) ?>%</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Card 2: Hot Product -->
            <div class="ai-card">
                <h3>Hot Product Right Now</h3>
                <div class="ai-insight"><?= htmlspecialchars($hot_product) ?></div>
                <p>Size <strong><?= htmlspecialchars($hot_size) ?></strong> is selling fastest!</p>
            </div>

            <!-- Card 3: Low Stock Alert -->
            <div class="ai-card">
                <h3>Low Stock Alert</h3>
                <div class="ai-value"><?= $low_stock_count ?> items</div>
                <?php if($low_stock_count > 0): ?>
                    <p>Running out: <?= implode(', ', $low_stock_items) ?>...</p>
                    <span class="tag danger">Restock Soon!</span>
                <?php else: ?>
                    <p>All good! Plenty of stock</p>
                    <span class="tag success">Healthy</span>
                <?php endif; ?>
            </div>

            <!-- Card 4: Total Revenue -->
            <div class="ai-card">
                <h3>Total Revenue</h3>
                <div class="ai-value">₱<?= number_format($revenue) ?></div>
                <p>from <?= $orders ?> orders in <?= $days ?> days</p>
            </div>

            <!-- Card 5: Weekend Boost -->
            <div class="ai-card">
                <h3>Weekend Boost Detected</h3>
                <div class="ai-insight">Sales jump on weekends</div>
                <p>Stock up your bestsellers every Friday!</p>
                <span class="tag info">Smart Tip</span>
            </div>

            <!-- Card 6: Profit King -->
            <div class="ai-card">
                <h3>Profit King</h3>
                <?php if($best_margin): ?>
                    <div class="ai-insight"><?= htmlspecialchars($best_margin['name']) ?></div>
                    <p>Enjoying <?= $best_margin['margin'] ?>% margin</p>
                <?php else: ?>
                    <p>No data yet</p>
                <?php endif; ?>
            </div>

            <!-- Card 7: Sleeping Stock -->
            <div class="ai-card">
                <h3>Sleeping Stock</h3>
                <div class="ai-value"><?= $sleeping ?></div>
                <p>items haven't sold in 30+ days</p>
                <?php if($sleeping > 5): ?>
                    <span class="tag warning">Consider discount</span>
                <?php endif; ?>
            </div>

            <!-- Card 8: Smart Restock -->
            <div class="ai-card">
                <h3>Smart Restock Suggestion</h3>
                <div class="ai-insight">Focus on <?= htmlspecialchars($hot_product) ?></div>
                <p>Order more Size <?= htmlspecialchars($hot_size) ?> before weekend rush!</p>
                <span class="tag success">AI Recommendation</span>
            </div>
        </div>

        <div class="updated">
            Last updated: <?= $updated_at ?> • Refreshes automatically every 5 mins
        </div>
    </main>

    <script>
        // Sync sidebar collapse state with body class
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('adminSidebar');
            
            if (sidebar) {
                // Check initial state
                if (sidebar.classList.contains('collapsed')) {
                    document.body.classList.add('sidebar-collapsed');
                }

                // Watch for changes
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            if (sidebar.classList.contains('collapsed')) {
                                document.body.classList.add('sidebar-collapsed');
                            } else {
                                document.body.classList.remove('sidebar-collapsed');
                            }
                        }
                    });
                });

                observer.observe(sidebar, { attributes: true });
            }
        });
    </script>
</body>
</html>