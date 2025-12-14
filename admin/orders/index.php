<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /CSSMS/login.php");
    exit();
}

// Get current status from URL or default to 'all'
$current_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date'] ?? '';
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Status definitions and workflow
$status_workflow = [
    'pending' => ['label' => 'Pending', 'color' => 'bg-yellow-100 text-yellow-800', 'next' => 'processing'],
    'processing' => ['label' => 'Processing', 'color' => 'bg-blue-100 text-blue-800', 'next' => 'shipped'],
    'shipped' => ['label' => 'Shipped', 'color' => 'bg-indigo-100 text-indigo-800', 'next' => 'delivered'],
    'delivered' => ['label' => 'Delivered', 'color' => 'bg-green-100 text-green-800', 'next' => null],
    'completed' => ['label' => 'Completed', 'color' => 'bg-emerald-100 text-emerald-800', 'next' => null],
    'cancelled' => ['label' => 'Cancelled', 'color' => 'bg-red-100 text-red-800', 'next' => null],
];

// Build query with filters
$query = "SELECT o.*, 
                 c.first_name, 
                 c.last_name, 
                 c.email, 
                 c.phone,
                 sa.address_line1,
                 sa.address_line2,
                 sa.city,
                 sa.province,
                 sa.postal_code,
                 COUNT(DISTINCT oi.order_item_id) as item_count,
                 SUM(oi.quantity) as total_quantity,
                 SUM(oi.subtotal) as order_total
          FROM orders o
          LEFT JOIN customers c ON o.customer_id = c.customer_id
          LEFT JOIN shipping_addresses sa ON o.address_id = sa.address_id
          LEFT JOIN order_items oi ON o.order_id = oi.order_id";

$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR o.order_id = ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search]);
    $types .= 'sssi';
}

if ($current_status !== 'all') {
    $conditions[] = "o.status = ?";
    $params[] = $current_status;
    $types .= 's';
}

if (!empty($date_filter)) {
    $conditions[] = "DATE(o.order_date) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " GROUP BY o.order_id ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

// Prepare and execute
$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT o.order_id) as total 
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.customer_id";
                
if (!empty($conditions)) {
    $count_query .= " WHERE " . implode(" AND ", $conditions);
}

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    if ($count_types) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $limit);

// Get stats for dashboard
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM orders";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get total revenue from delivered and completed orders only
$revenue_query = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE status IN ('delivered', 'completed')";
$revenue_result = $conn->query($revenue_query);
$revenue_data = $revenue_result->fetch_assoc();
$total_revenue = $revenue_data['total_revenue'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management | TrendyWear Admin</title>
    <link rel="stylesheet" href="../css/adminheader.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="manageorders.css">
    <style>
        /* Additional inline styles for compatibility */
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: #333;
            min-height: 100vh;
        }
        
        .admin-main-content {
            margin-left: 280px;
            padding: 100px 40px 40px;
            background: #f8f9fa;
            transition: margin-left 0.3s ease;
        }
        
        /* Status Colors to match orders.php */
        .status-badge.bg-yellow-100 {
            background: #fff3e0 !important;
            color: #f57c00 !important;
            border: 1px solid #ffb74d;
        }
        
        .status-badge.bg-blue-100 {
            background: #e3f2fd !important;
            color: #1976d2 !important;
            border: 1px solid #64b5f6;
        }
        
        .status-badge.bg-indigo-100 {
            background: #e8eaf6 !important;
            color: #3949ab !important;
            border: 1px solid #9fa8da;
        }
        
        .status-badge.bg-green-100 {
            background: #e8f5e8 !important;
            color: #388e3c !important;
            border: 1px solid #81c784;
        }
        
        .status-badge.bg-emerald-100 {
            background: #f5f5f5 !important;
            color: #666 !important;
            border: 1px solid #e0e0e0;
        }
        
        .status-badge.bg-red-100 {
            background: #ffebee !important;
            color: #f44336 !important;
            border: 1px solid #ef9a9a;
        }
        
        /* Button styles to match orders.php */
        .btn-primary, .btn-success {
            background: #e91e63 !important;
            color: white !important;
            border: none !important;
            padding: 10px 20px !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        
        .btn-primary:hover, .btn-success:hover {
            background: #c2185b !important;
            transform: translateY(-1px) !important;
        }
        
        .btn-danger {
            background: white !important;
            color: #f44336 !important;
            border: 1px solid #f44336 !important;
            padding: 10px 20px !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
        }
        
        .btn-danger:hover {
            background: #ffebee !important;
        }
        
        .action-btn {
            background: transparent !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 8px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            color: #666 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 36px !important;
            height: 36px !important;
        }
        
        .action-btn:hover {
            background: #f0f0f0 !important;
            color: #333 !important;
        }
        
        .action-btn.text-danger {
            color: #f44336 !important;
        }
        
        .action-btn.text-danger:hover {
            background: #ffebee !important;
        }
        
        /* Progress bar styles */
        .progress-fill {
            background: #e91e63 !important;
        }
        
        .step-circle.active {
            background: #e91e63 !important;
        }
        
        .step-circle.completed {
            background: #4caf50 !important;
        }
        
        /* Flash message styles */
        .flash-success {
            border-left: 4px solid #4caf50 !important;
        }
        
        .flash-error {
            border-left: 4px solid #f44336 !important;
        }
        
        /* Status Tabs styling */
        .orders-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 90px;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .orders-filters.sticky {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border-radius: 0 0 12px 12px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #f5f5f5;
            color: #666;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .filter-btn:hover {
            background: #e91e63;
            color: white;
            border-color: #e91e63;
            transform: translateY(-1px);
        }
        
        .filter-btn.active {
            background: #e91e63;
            color: white;
            border-color: #e91e63;
        }
        
        .filter-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }
        
        .filter-btn:not(.active) .filter-badge {
            background: #e91e63;
            color: white;
        }
        
        /* Status Timeline styling */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            position: relative;
        }
        
        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            flex: 1;
            position: relative;
            z-index: 2;
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid #e0e0e0;
            color: #999;
        }
        
        .timeline-step.active .timeline-icon {
            background: #e91e63;
            border-color: #e91e63;
            color: white;
        }
        
        .timeline-step.completed .timeline-icon {
            background: #4caf50;
            border-color: #4caf50;
            color: white;
        }
        
        .timeline-label {
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            font-weight: 500;
        }
        
        .timeline-step.active .timeline-label {
            color: #e91e63;
            font-weight: 600;
        }
        
        .timeline-step.completed .timeline-label {
            color: #4caf50;
        }
        
        /* Remove old progress bar styles */
        .progress-line, .progress-fill, .step-circle {
            display: none;
        }
        
        /* To be reviewed text styling */
        .review-text {
            color: #f57c00;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 12px;
            background: #fff3e0;
            border-radius: 6px;
            border: 1px solid #ffb74d;
            display: inline-block;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .admin-main-content {
                margin-left: 0;
                padding: 80px 20px 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr !important;
            }
            
            .orders-filters {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
                top: 80px;
            }
            
            .filter-buttons {
                justify-content: center;
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-btn {
                justify-content: center;
            }
            
            .status-timeline {
                flex-direction: column;
                gap: 20px;
                padding: 15px;
            }
            
            .timeline-step {
                flex-direction: row;
                width: 100%;
                text-align: left;
                gap: 15px;
            }
            
            .timeline-label {
                flex: 1;
                text-align: left;
            }
            
            .pagination-section {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .pagination-controls {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn-success, .btn-danger, .btn-primary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../adminheader.php'; ?>
    <?php include '../sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container-fluid">
            <!-- Header Section - Match orders.php style -->
            <div class="header-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h1 class="page-title">Order Management</h1>
                        <p class="header-subtitle">Track and manage customer orders through the fulfillment process</p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards - Match orders.php card style -->
            <div class="stats-grid">
                <div class="stat-card violet-pink">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                </div>
                <div class="stat-card amber-orange">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                </div>
                <div class="stat-card emerald-teal">
                    <div class="stat-label">Processing</div>
                    <div class="stat-value"><?php echo number_format($stats['processing'] ?? 0); ?></div>
                </div>
                <div class="stat-card red-rose">
                    <div class="stat-label">Shipped</div>
                    <div class="stat-value"><?php echo number_format($stats['shipped'] ?? 0); ?></div>
                </div>
                <div class="stat-card blue-sky">
                    <div class="stat-label">Delivered</div>
                    <div class="stat-value"><?php echo number_format($stats['delivered'] ?? 0); ?></div>
                </div>
                <div class="stat-card purple-deep">
                    <div class="stat-label">Total Revenue (Delivered & Completed)</div>
                    <div class="stat-value">₱<?php echo number_format($total_revenue, 2); ?></div>
                </div>
            </div>

            <!-- Status Tabs - Match orders.php filter style -->
            <div class="orders-filters">
                <div class="filter-buttons">
                    <a href="?status=all" class="filter-btn <?php echo $current_status === 'all' ? 'active' : ''; ?>" data-filter="all">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        All Orders
                        <span class="filter-badge"><?php echo $stats['total_orders'] ?? 0; ?></span>
                    </a>
                    <a href="?status=pending" class="filter-btn <?php echo $current_status === 'pending' ? 'active' : ''; ?>" data-filter="pending">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                        Pending
                        <span class="filter-badge"><?php echo $stats['pending'] ?? 0; ?></span>
                    </a>
                    <a href="?status=processing" class="filter-btn <?php echo $current_status === 'processing' ? 'active' : ''; ?>" data-filter="processing">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Processing
                        <span class="filter-badge"><?php echo $stats['processing'] ?? 0; ?></span>
                    </a>
                    <a href="?status=shipped" class="filter-btn <?php echo $current_status === 'shipped' ? 'active' : ''; ?>" data-filter="shipped">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        Shipped
                        <span class="filter-badge"><?php echo $stats['shipped'] ?? 0; ?></span>
                    </a>
                    <a href="?status=delivered" class="filter-btn <?php echo $current_status === 'delivered' ? 'active' : ''; ?>" data-filter="delivered">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Delivered
                        <span class="filter-badge"><?php echo $stats['delivered'] ?? 0; ?></span>
                    </a>
                    <a href="?status=completed" class="filter-btn <?php echo $current_status === 'completed' ? 'active' : ''; ?>" data-filter="completed">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Completed
                        <span class="filter-badge"><?php echo $stats['completed'] ?? 0; ?></span>
                    </a>
                    <a href="?status=cancelled" class="filter-btn <?php echo $current_status === 'cancelled' ? 'active' : ''; ?>" data-filter="cancelled">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancelled
                        <span class="filter-badge"><?php echo $stats['cancelled'] ?? 0; ?></span>
                    </a>
                </div>
            </div>

            <!-- Search and Filters - Auto-filtering without Apply button -->
            <div class="filters-container mb-4">
                <div class="filters-section">
                    <form method="GET" id="filterForm" class="d-flex gap-3 align-items-center flex-wrap">
                        <input type="hidden" name="status" value="<?php echo $current_status; ?>">
                        
                        <div class="search-wrapper">
                            <input type="text" 
                                   name="search" 
                                   placeholder="Search by customer name, email, or order ID..." 
                                   class="search-input"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   id="searchInput"
                                   onkeyup="this.form.submit()">
                            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        
                        <input type="date" 
                               name="date" 
                               class="filter-select"
                               value="<?php echo htmlspecialchars($date_filter); ?>"
                               id="dateSelect"
                               onchange="this.form.submit()">
                        
                        <a href="?" class="action-btn">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </form>
                </div>
            </div>

            <!-- Status Progress Indicator - Match orders.php timeline -->
            <?php if ($current_status !== 'all' && $current_status !== 'cancelled'): ?>
            <div class="table-container mb-4">
                <div class="p-3">
                    <h4 class="mb-3" style="color: #e91e63; font-weight: 600;">Order Workflow Progress</h4>
                    <div class="status-timeline">
                        <?php 
                        $steps = ['pending', 'processing', 'shipped', 'delivered', 'completed'];
                        $current_index = array_search($current_status, $steps);
                        
                        foreach ($steps as $index => $step): 
                            $step_class = '';
                            $icon = ($index + 1);
                            
                            if ($index < $current_index) {
                                $step_class = 'completed';
                                $icon = '<i class="fas fa-check"></i>';
                            } elseif ($index == $current_index) {
                                $step_class = 'active';
                            }
                        ?>
                        <div class="timeline-step <?php echo $step_class; ?>">
                            <div class="timeline-icon">
                                <?php echo $icon; ?>
                            </div>
                            <span class="timeline-label"><?php echo ucfirst($step); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Orders Table - Match orders.php table style -->
            <div class="table-container" style="margin-top: 20px;">
                <div class="table-header">
                    <h3>Order List</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Shipping</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)) { ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                            <h3>No orders found</h3>
                                            <p>Try adjusting your search filters</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php } else { 
                                foreach ($orders as $order) { 
                                    $next_status = $status_workflow[$order['status']]['next'] ?? null;
                                ?>
                                <tr data-order-id="<?php echo $order['order_id']; ?>">
                                    <td>
                                        <strong>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td>
                                        <div class="customer-cell">
                                            <div class="customer-avatar">
                                                <?php echo strtoupper(substr($order['first_name'] ?? 'C', 0, 1)); ?>
                                            </div>
                                            <div class="customer-info">
                                                <div class="customer-name">
                                                    <?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?>
                                                </div>
                                                <div class="customer-email">
                                                    <?php echo htmlspecialchars($order['email'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                        <div class="text-sm text-muted">
                                            <?php echo date('h:i A', strtotime($order['order_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td class="price-text">
                                        ₱<?php echo number_format($order['order_total'] ?? $order['total_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_workflow[$order['status']]['color']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="shipping-info">
                                            <?php if (!empty($order['city'])) { ?>
                                                <span class="text-sm"><?php echo htmlspecialchars($order['city']); ?>, <?php echo htmlspecialchars($order['province']); ?></span>
                                            <?php } else { ?>
                                                <span class="text-sm text-muted">N/A</span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <?php if ($next_status && $order['status'] !== 'delivered'): ?>
                                                <button type="button" 
                                                        class="btn-success mark-as-next"
                                                        data-order-id="<?php echo $order['order_id']; ?>"
                                                        data-current-status="<?php echo $order['status']; ?>"
                                                        data-next-status="<?php echo $next_status; ?>">
                                                    <i class="fas fa-arrow-right"></i> Mark as <?php echo ucfirst($next_status); ?>
                                                </button>
                                            <?php elseif ($order['status'] === 'delivered'): ?>
                                                <span class="review-text">
                                                    <i class="fas fa-clock"></i> To be reviewed by buyer
                                                </span>
                                            <?php endif; ?>
                                            
                                            <button type="button" 
                                                    class="action-btn view-order-details"
                                                    data-order-id="<?php echo $order['order_id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'completed' && $order['status'] !== 'delivered') { ?>
                                                <button type="button" 
                                                        class="action-btn text-danger cancel-order"
                                                        data-order-id="<?php echo $order['order_id']; ?>"
                                                        title="Cancel Order">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php } 
                            } ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination - Match orders.php pagination -->
                <div class="pagination-section">
                    <div class="pagination-info">
                        Showing <span><?php echo min($offset + 1, $total_orders); ?>-<?php echo min($offset + $limit, $total_orders); ?></span> of <span><?php echo number_format($total_orders); ?></span> orders
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1) { ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $current_status; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo $date_filter; ?>" 
                               class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php } ?>
                        
                        <?php for ($i = 1; $i <= min($total_pages, 5); $i++) { ?>
                            <?php if ($i == $page) { ?>
                                <span class="pagination-btn active"><?php echo $i; ?></span>
                            <?php } else { ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $current_status; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo $date_filter; ?>" 
                                   class="pagination-btn"><?php echo $i; ?></a>
                            <?php } ?>
                        <?php } ?>
                        
                        <?php if ($total_pages > 5) { ?>
                            <span class="pagination-btn disabled">...</span>
                            <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $current_status; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo $date_filter; ?>" 
                               class="pagination-btn"><?php echo $total_pages; ?></a>
                        <?php } ?>
                        
                        <?php if ($page < $total_pages) { ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $current_status; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo $date_filter; ?>" 
                               class="pagination-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Order Details #<span id="modalOrderId"></span></h3>
                <button type="button" class="close-modal" id="closeModal">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_message'])) { ?>
    <div class="flash-message show flash-<?php echo $_SESSION['flash_message']['type']; ?>">
        <?php if ($_SESSION['flash_message']['type'] === 'success') { ?>
            <i class="fas fa-check-circle text-success"></i>
        <?php } else { ?>
            <i class="fas fa-exclamation-circle text-danger"></i>
        <?php } ?>
        <span><?php echo $_SESSION['flash_message']['message']; ?></span>
    </div>
    <?php 
        unset($_SESSION['flash_message']);
    } 
    ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Mark as Next Status
        $('.mark-as-next').click(function() {
            const orderId = $(this).data('order-id');
            const currentStatus = $(this).data('current-status');
            const nextStatus = $(this).data('next-status');
            
            if (confirm('Mark order #' + orderId + ' as ' + nextStatus + '?')) {
                updateOrderStatus(orderId, nextStatus, 'Order moved to next status');
            }
        });
        
        // Cancel Order
        $('.cancel-order').click(function() {
            const orderId = $(this).data('order-id');
            
            if (confirm('Cancel order #' + orderId + '? This action cannot be undone.')) {
                updateOrderStatus(orderId, 'cancelled', 'Order cancelled');
            }
        });
        
        // Update Order Status Function
        function updateOrderStatus(orderId, newStatus, notes = '') {
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                data: {
                    order_id: orderId,
                    new_status: newStatus,
                    admin_notes: notes
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showFlashMessage(response.message, 'success');
                        // Reload page after 1 second
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showFlashMessage(response.message, 'error');
                    }
                },
                error: function() {
                    showFlashMessage('Error updating order status', 'error');
                }
            });
        }
        
        // View Order Details
        $('.view-order-details').click(function() {
            const orderId = $(this).data('order-id');
            
            $('#modalOrderId').text(orderId.toString().padStart(6, '0'));
            $('#orderDetailsModal').show();
            
            // Load order details
            $.ajax({
                url: 'order_details.php',
                type: 'GET',
                data: { order_id: orderId },
                success: function(response) {
                    $('#modalContent').html(response);
                },
                error: function() {
                    $('#modalContent').html('<p class="text-danger">Error loading order details.</p>');
                }
            });
        });
        
        // Close modal
        $('#closeModal').click(function() {
            $('#orderDetailsModal').hide();
        });
        
        // Close modal when clicking outside
        $(window).click(function(e) {
            if ($(e.target).is('#orderDetailsModal')) {
                $('#orderDetailsModal').hide();
            }
        });
        
        // Auto-hide flash message
        setTimeout(() => {
            $('.flash-message').removeClass('show');
            setTimeout(() => $('.flash-message').remove(), 300);
        }, 4000);
        
        // Show flash message function
        function showFlashMessage(message, type) {
            // Remove existing flash message
            $('.flash-message').remove();
            
            // Create new flash message
            const flash = $('<div class="flash-message flash-' + type + '">' +
                '<i class="fas fa-' + (type === 'success' ? 'check' : 'exclamation') + '-circle text-' + type + '"></i>' +
                '<span>' + message + '</span>' +
                '</div>');
            
            $('body').append(flash);
            
            // Show with animation
            setTimeout(() => flash.addClass('show'), 100);
            
            // Auto hide after 4 seconds
            setTimeout(() => {
                flash.removeClass('show');
                setTimeout(() => flash.remove(), 300);
            }, 4000);
        }
        
        // Initialize sticky filters
        initializeStickyFilter();
        
        function initializeStickyFilter() {
            const filters = document.querySelector('.orders-filters');
            if (!filters) return;
            
            const observer = new IntersectionObserver(
                ([e]) => {
                    if (e.intersectionRatio < 1) {
                        filters.classList.add('sticky');
                    } else {
                        filters.classList.remove('sticky');
                    }
                },
                { threshold: [1], rootMargin: '-90px 0px 0px 0px' }
            );
            
            observer.observe(filters);
        }
        
        // Debounce function for search input
        let searchTimeout;
        $('#searchInput').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                $('#filterForm').submit();
            }, 500);
        });
    });
    </script>
</body>
</html>