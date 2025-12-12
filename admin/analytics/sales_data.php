<?php
// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/db.php';
header('Content-Type: application/json');

try {
    $period = $_GET['period'] ?? 'month';

    $where = "WHERE s.payment_status = 'completed'";

    if ($period === 'week') {
        $where .= " AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($period === 'month') {
        $where .= " AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($period === 'year') {
        $where .= " AND YEAR(s.sale_date) = YEAR(CURDATE())";
    }

    // 1. Summary
    $summary_query = "
        SELECT 
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COALESCE(SUM(si.quantity * (si.unit_price - si.unit_cost)), 0) as profit,
            COALESCE(SUM(si.quantity), 0) as items,
            COALESCE(AVG(s.total_amount), 0) as avg_order
        FROM sales s
        LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        $where
    ";
    
    $result = $conn->query($summary_query);
    $summary = $result->fetch_assoc();

    // 2. Trend Data (Grouped by Day/Month)
    $groupFormat = $period === 'year' ? '%Y-%m' : '%Y-%m-%d';
    $trend_query = "
        SELECT 
            DATE_FORMAT(s.sale_date, '$groupFormat') as label,
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COALESCE(SUM(si.quantity * (si.unit_price - si.unit_cost)), 0) as profit
        FROM sales s
        LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        $where
        GROUP BY DATE_FORMAT(s.sale_date, '$groupFormat')
        ORDER BY label
    ";
    
    $result = $conn->query($trend_query);
    $trend = [];
    while ($row = $result->fetch_assoc()) {
        $trend[] = $row;
    }

    // Fill missing dates
    $labels = [];
    $revenueData = [];
    $profitData = [];

    if ($period === 'year') {
        $start = new DateTime('first day of this year');
        $end = new DateTime('next month');
        $interval = new DateInterval('P1M');
    } else {
        $days = $period === 'week' ? 6 : 29;
        $start = new DateTime("-$days days");
        $end = new DateTime('tomorrow');
        $interval = new DateInterval('P1D');
    }

    $dateRange = new DatePeriod($start, $interval, $end);

    foreach ($dateRange as $date) {
        $key = $date->format($period === 'year' ? 'Y-m' : 'Y-m-d');
        $labels[] = $period === 'year' ? $date->format('M Y') : $date->format('M j');
        
        $found = false;
        foreach ($trend as $row) {
            if ($row['label'] === $key) {
                $revenueData[] = (float)$row['revenue'];
                $profitData[] = (float)$row['profit'];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $revenueData[] = 0;
            $profitData[] = 0;
        }
    }

    // 3. Category Breakdown
    $category_query = "
        SELECT 
            c.category_name,
            COALESCE(SUM(si.quantity), 0) as total_sold
        FROM categories c
        LEFT JOIN products p ON c.category_id = p.category_id
        LEFT JOIN product_sizes ps ON p.product_id = ps.product_id
        LEFT JOIN sale_items si ON ps.product_size_id = si.product_size_id
        LEFT JOIN sales s ON si.sale_id = s.sale_id
        $where
        GROUP BY c.category_id, c.category_name
        HAVING total_sold > 0
        ORDER BY total_sold DESC
    ";
    
    $result = $conn->query($category_query);
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

    // 4. Top Products
    $products_query = "
        SELECT 
            p.product_name,
            c.category_name,
            SUM(si.quantity) as qty,
            SUM(si.quantity * si.unit_price) as revenue
        FROM sale_items si
        JOIN product_sizes ps ON si.product_size_id = ps.product_size_id
        JOIN products p ON ps.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        JOIN sales s ON si.sale_id = s.sale_id
        $where
        GROUP BY p.product_id, p.product_name, c.category_name
        ORDER BY qty DESC
        LIMIT 10
    ";
    
    $result = $conn->query($products_query);
    $top_products = [];
    while ($row = $result->fetch_assoc()) {
        $top_products[] = $row;
    }

    // Return JSON response
    echo json_encode([
        'summary' => $summary,
        'trend' => [
            'labels' => $labels,
            'revenue' => $revenueData,
            'profit' => $profitData
        ],
        'categories' => $categories,
        'top_products' => $top_products
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>