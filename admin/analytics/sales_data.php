<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/db.php';
header('Content-Type: application/json');

try {
    $period = $_GET['period'] ?? 'month';
    $type   = $_GET['type']   ?? 'instore';

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    $rows = [];

    // Date filter helper
    $dateFilter = "";
    if ($period === 'week') {
        $dateFilter = " AND date_field >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($period === 'month') {
        $dateFilter = " AND date_field >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($period === 'year') {
        $dateFilter = " AND YEAR(date_field) = YEAR(CURDATE())";
    }

    // Unified query to get all sales rows with common columns
    $unionParts = [];

    if ($type === 'all' || $type === 'instore') {
            $unionParts[] = "SELECT 
            s.sale_date AS date_field,
            si.quantity,
            si.total AS revenue,
            (si.total - (si.unit_cost * si.quantity)) AS profit,
            si.product_name,
            COALESCE(cat.category_name, 'Uncategorized') AS category_name,
            'instore' AS source
        FROM sales s
        JOIN sale_items si ON s.sale_id = si.sale_id
        JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
        JOIN products p ON ps.product_id = p.product_id
        LEFT JOIN categories cat ON p.category_id = cat.category_id
        WHERE s.payment_status = 'completed'";
    }

    if ($type === 'all' || $type === 'online') {
        $unionParts[] = "SELECT 
            o.order_date AS date_field,
            oi.quantity,
            oi.subtotal AS revenue,
            (oi.subtotal - (oi.unit_cost * oi.quantity)) AS profit,
            p.product_name,
            COALESCE(c.category_name, 'Uncategorized') AS category_name,
            'online' AS source
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN inventory i ON oi.inventory_id = i.inventory_id
        JOIN products p ON i.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE o.status = 'completed'";
    }

    if (empty($unionParts)) {
        throw new Exception("Invalid type");
    }

        // Build UNION safely
    $unionQuery = implode("\nUNION ALL\n", $unionParts);

    // Wrap it properly with alias
    $fullSql = "
        SELECT *
        FROM ($unionQuery) AS combined
        WHERE 1=1 $dateFilter
        ORDER BY date_field
    ";

    $result = $conn->query($fullSql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        // No data response
        echo json_encode([
            'summary' => ['revenue'=>0,'profit'=>0,'items'=>0,'avg_order'=>0],
            'trend'   => ['labels'=>[],'revenue'=>[],'profit'=>[]],
            'charts'  => ['category' => ['type'=>'doughnut','data'=>['labels'=>['No sales'],'datasets'=>[['data'=>[1],'backgroundColor'=>['#ddd']]]]]],
            'top_products' => []
        ]);
        exit;
    }

    // Aggregations
    $totalRevenue = $totalProfit = $totalItems = 0;
    $byDate = [];
    $byCategory = [];
    $byProduct = [];

    foreach ($rows as $row) {
        $date = $row['date_field'];
        $dateKey = date('Y-m-d', strtotime($date)); // Group by day

        $byDate[$dateKey]['revenue'] = ($byDate[$dateKey]['revenue'] ?? 0) + $row['revenue'];
        $byDate[$dateKey]['profit'] = ($byDate[$dateKey]['profit'] ?? 0) + $row['profit'];

        $cat = $row['category_name'] ?: 'Unknown';
        $byCategory[$cat]['items'] = ($byCategory[$cat]['items'] ?? 0) + $row['quantity'];
        $byCategory[$cat]['revenue'] = ($byCategory[$cat]['revenue'] ?? 0) + $row['revenue'];

        $prodKey = $row['product_name'] . '|' . $cat;
        $byProduct[$prodKey]['product_name'] = $row['product_name'];
        $byProduct[$prodKey]['category_name'] = $cat;
        $byProduct[$prodKey]['qty'] = ($byProduct[$prodKey]['qty'] ?? 0) + $row['quantity'];
        $byProduct[$prodKey]['revenue'] = ($byProduct[$prodKey]['revenue'] ?? 0) + $row['revenue'];

        $totalRevenue += $row['revenue'];
        $totalProfit += $row['profit'];
        $totalItems += $row['quantity'];
    }

    // Sort dates
    ksort($byDate);
    $trendLabels = array_keys($byDate);
    $trendRevenue = array_values(array_column($byDate, 'revenue'));
    $trendProfit = array_values(array_column($byDate, 'profit'));

    // Order count for avg (approx by unique dates)
    $orderCount = count($trendLabels);
    $avgOrder = $orderCount > 0 ? round($totalRevenue / $orderCount) : 0;

    // Category chart
    $catLabels = array_keys($byCategory);
    $catData = array_values(array_column($byCategory, 'items'));
    $colors = ['#e91e63','#ff9800','#4caf50','#2196f3','#9c27b0','#00bcd4','#ffc107','#f44336'];

    // Top products
    uasort($byProduct, fn($a,$b) => $b['qty'] <=> $a['qty']);
    $topProducts = array_slice(array_values($byProduct), 0, 10);

    $response = [
        'summary' => [
            'revenue'   => round($totalRevenue),
            'profit'    => round($totalProfit),
            'items'     => (int)$totalItems,
            'avg_order' => $avgOrder
        ],
        'trend' => [
            'labels'  => $trendLabels,
            'revenue' => $trendRevenue,
            'profit'  => $trendProfit
        ],
        'charts' => [
            'category' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $catLabels,
                    'datasets' => [[
                        'data' => $catData,
                        'backgroundColor' => array_slice($colors, 0, count($catLabels))
                    ]]
                ]
            ]
        ],
        'top_products' => $topProducts
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed', 'msg' => $e->getMessage()]);
}
?>