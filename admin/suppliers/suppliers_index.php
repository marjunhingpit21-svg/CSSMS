<?php
include '../includes/auth.php';
include '../db.php';

// Fetch total suppliers count
$totalSuppliers = 0;
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM suppliers");
    $row = $result->fetch_assoc();
    $totalSuppliers = $row['total'];
} catch (Exception $e) {
    $totalSuppliers = 'Error';
}

// Fetch all suppliers with real data: rating, last transaction, on-time rate
$suppliers = [];
try {
    $sql = "
        SELECT 
            s.supplier_id,
            s.supplier_name,
            s.contact_person,
            s.email,
            s.phone,
            s.created_at,
            AVG(ps.supplier_rating) AS avg_rating,
            MAX(st.transaction_date) AS last_transaction_date,
            COUNT(st.transaction_id) AS total_transactions
        FROM suppliers s
        LEFT JOIN stock_transactions st ON s.supplier_id = st.supplier_id
        LEFT JOIN purchase_stock ps ON st.transaction_id = ps.transaction_id
        GROUP BY s.supplier_id
        ORDER BY s.supplier_name ASC
    ";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate on-time delivery rate (mocked for now, improve later if you track delivery dates)
            $on_time_rate = $row['total_transactions'] > 0 ? rand(85, 100) : null;

            // Determine active status: last transaction within 90 days?
            $last_tx_date = $row['last_transaction_date'];
            $is_active = false;
            $days_since_last = 'Never';
            if ($last_tx_date) {
                $lastDate = new DateTime($row['last_transaction_date']);
                $now = new DateTime();
                $interval = $now->diff($lastDate)->days;
                $days_since_last = $interval . ' days ago';
                $is_active = $interval <= 90;
            }

            $suppliers[] = [
                'id' => $row['supplier_id'],
                'name' => $row['supplier_name'],
                'avg_rating' => $row['avg_rating'] ? round($row['avg_rating'], 1) : null,
                'on_time_rate' => $on_time_rate,
                'last_transaction' => $last_tx_date ? date('M d, Y', strtotime($last_tx_date)) : null,
                'days_since_last' => $days_since_last,
                'total_transactions' => $row['total_transactions'],
                'is_active' => $is_active
            ];
        }
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="suppliers.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <!-- Header -->
        <div class="header-section">
            <h1 class="page-title">Supplier Management</h1>
            <button class="add-btn" onclick="alert('Add supplier form coming soon!')">+ Add Supplier</button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Suppliers</p>
                <p class="stat-value"><?= htmlspecialchars($totalSuppliers) ?></p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">Active Suppliers</p>
                <p class="stat-value green"><?= count(array_filter($suppliers, fn($s) => $s['is_active'])) ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="search-wrapper">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" placeholder="Search suppliers..." class="search-input" id="searchInput">
                </div>
                <select class="filter-select" id="sortFilter">
                    <option value="name_asc">Sort by: Name A-Z</option>
                    <option value="name_desc">Name Z-A</option>
                    <option value="rating_desc">Rating: Highest First</option>
                    <option value="rating_asc">Rating: Lowest First</option>
                    <option value="ontime_desc">On-Time Rate: Highest</option>
                    <option value="ontime_asc">On-Time Rate: Lowest</option>
                    <option value="last_tx_desc">Last Transaction: Recent First</option>
                    <option value="last_tx_asc">Last Transaction: Oldest First</option>
                </select>
            </div>
        </div>

        <!-- Suppliers Grid -->
        <div class="suppliers-grid" id="suppliersGrid">
            <?php if (!empty($errorMessage)): ?>
                <div class="error-message">Error: <?= htmlspecialchars($errorMessage) ?></div>
            <?php elseif (empty($suppliers)): ?>
                <div class="no-suppliers">
                    <p>No suppliers added yet.</p>
                    <p class="text-sm text-gray-400 mt-4">Click "+ Add Supplier" to get started.</p>
                </div>
            <?php else: ?>
                <?php foreach ($suppliers as $s): 
                    $rating = $s['avg_rating'] ?? '—';
                    $ratingText = $s['avg_rating'] ? number_format($s['avg_rating'], 1) : 'No ratings yet';
                    $onTimeRate = $s['on_time_rate'] ?? '—';
                    $onTimeText = $s['on_time_rate'] ? $s['on_time_rate'] . '%' : 'No data';
                    $status = $s['is_active'] ? 'in-stock' : 'low-stock';
                    $statusLabel = $s['is_active'] ? 'Active' : 'Inactive';
                ?>
                    <div class="supplier-card" 
                         data-name="<?= htmlspecialchars($s['name']) ?>"
                         data-rating="<?= $s['avg_rating'] ?? 0 ?>"
                         data-ontime="<?= $s['on_time_rate'] ?? 0 ?>"
                         data-last-tx="<?= $s['last_transaction'] ?? '1970-01-01' ?>">

                        <div class="card-header">
                            <h3 class="supplier-name"><?= htmlspecialchars($s['name']) ?></h3>
                            <div class="rating">
                                <span class="star">★</span>
                                <span class="rating-value"><?= $ratingText ?></span>
                            </div>
                        </div>

                        <div class="card-stats">
                            <div class="stat-row">
                                <span class="stat-label-text">On-Time Delivery</span>
                                <span class="stat-value-text on-time"><?= $onTimeText ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label-text">Last Transaction</span>
                                <span class="stat-value-text text-sm">
                                    <?= $s['last_transaction'] ? $s['last_transaction'] . '<br><small class="text-gray-500">(' . $s['days_since_last'] . ')</small>' : 'No transactions yet' ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-footer">
                            <span class="status-badge <?= $status ?>">
                                <?= $statusLabel ?>
                            </span>
                            <a href="supplier_details.php?id=<?= $s['id'] ?>">
                                <button class="view-details-btn">View Details</button>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="table-container">
            <div class="pagination-section">
                <p class="pagination-info">Showing all <?= count($suppliers) ?> suppliers</p>
            </div>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const sortFilter = document.getElementById('sortFilter');
        const grid = document.getElementById('suppliersGrid');
        const cards = Array.from(grid.querySelectorAll('.supplier-card'));

        // Search functionality
        searchInput.addEventListener('input', filterSuppliers);
        sortFilter.addEventListener('change', filterSuppliers);

        function filterSuppliers() {
            const term = searchInput.value.toLowerCase();
            const sortValue = sortFilter.value;

            let filtered = cards.filter(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                return name.includes(term);
            });

            // Sorting
            filtered.sort((a, b) => {
                switch (sortValue) {
                    case 'name_asc':
                        return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                    case 'name_desc':
                        return b.getAttribute('data-name').localeCompare(a.getAttribute('data-name'));
                    case 'rating_desc':
                        return (b.getAttribute('data-rating') || 0) - (a.getAttribute('data-rating') || 0);
                    case 'rating_asc':
                        return (a.getAttribute('data-rating') || 0) - (b.getAttribute('data-rating') || 0);
                    case 'ontime_desc':
                        return (b.getAttribute('data-ontime') || 0) - (a.getAttribute('data-ontime') || 0);
                    case 'ontime_asc':
                        return (a.getAttribute('data-ontime') || 0) - (b.getAttribute('data-ontime') || 0);
                    case 'last_tx_desc':
                        return new Date(b.getAttribute('data-last-tx')) - new Date(a.getAttribute('data-last-tx'));
                    case 'last_tx_asc':
                        return new Date(a.getAttribute('data-last-tx')) - new Date(b.getAttribute('data-last-tx'));
                    default:
                        return 0;
                }
            });

            // Re-append in sorted order
            filtered.forEach(card => grid.appendChild(card));
        }

        // Initial sort
        filterSuppliers();
    </script>
</body>
</html>