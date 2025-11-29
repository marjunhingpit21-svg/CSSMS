<?php include '../includes/auth.php'; ?>
<?php include '../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers • TrendyWear Admin</title>
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
            <button class="add-btn">+ Add Supplier</button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Suppliers</p>
                <p class="stat-value">
                    <?php
                    $result = $conn->query("SELECT COUNT(*) as total FROM suppliers");
                    $row = $result->fetch_assoc();
                    echo $row['total'];
                    ?>
                </p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">Active Suppliers</p>
                <p class="stat-value green">
                    <?php
                    // Assuming all suppliers are active for now
                    echo $row['total'];
                    ?>
                </p>
            </div>
            <div class="stat-card red-rose">
                <p class="stat-label">Pending Reviews</p>
                <p class="stat-value red">0</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="search-wrapper">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" placeholder="Search suppliers..." class="search-input" id="searchInput">
                </div>
                <select class="filter-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="Fabric">Fabric</option>
                    <option value="Accessories">Accessories</option>
                    <option value="Printing">Printing</option>
                    <option value="Packaging">Packaging</option>
                </select>
                <select class="filter-select" id="performanceFilter">
                    <option value="">All Performance</option>
                    <option value="excellent">Excellent (95%+)</option>
                    <option value="good">Good (90–94%)</option>
                    <option value="needs_review">Needs Review (<90%)</option>
                </select>
                <select class="filter-select" id="sortFilter">
                    <option value="name_asc">Sort by: Name A-Z</option>
                    <option value="name_desc">Name Z-A</option>
                    <option value="recent">Most Recent</option>
                </select>
            </div>
        </div>

        <!-- Suppliers Grid -->
        <div class="suppliers-grid" id="suppliersGrid">
            <?php 
            try {
                // Get suppliers from database
                $sql = "
                    SELECT 
                        s.supplier_id,
                        s.supplier_name,
                        s.contact_person,
                        s.email,
                        s.phone,
                        s.address,
                        s.created_at,
                        COUNT(DISTINCT p.product_id) as product_count
                    FROM suppliers s
                    LEFT JOIN products p ON s.supplier_id = p.product_id
                    GROUP BY s.supplier_id
                    ORDER BY s.supplier_name ASC
                ";
                
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
                    while($s = $result->fetch_assoc()) {
                        // Calculate some mock performance metrics for demonstration
                        $rating = rand(40, 50) / 10; // Random rating between 4.0 and 5.0
                        $lead_time = rand(5, 15); // Random lead time between 5-15 days
                        $on_time_rate = rand(85, 100); // Random on-time rate between 85-100%
                        $stock_status = $on_time_rate > 90 ? 'in-stock' : 'low-stock';
            ?>
            <div class="supplier-card" data-name="<?= htmlspecialchars($s['supplier_name']) ?>" data-rating="<?= $rating ?>" data-performance="<?= $on_time_rate >= 95 ? 'excellent' : ($on_time_rate >= 90 ? 'good' : 'needs_review') ?>">
                <!-- Header: Name + Rating -->
                <div class="card-header">
                    <h3 class="supplier-name"><?= htmlspecialchars($s['supplier_name']) ?></h3>
                    <div class="rating">
                        <span class="star">★</span>
                        <span class="rating-value"><?= number_format($rating, 1) ?></span>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="card-contact">
                    <p class="contact-person"><?= htmlspecialchars($s['contact_person']) ?></p>
                    <p class="contact-email"><?= htmlspecialchars($s['email']) ?></p>
                    <p class="contact-phone"><?= htmlspecialchars($s['phone']) ?></p>
                </div>

                <!-- Stats -->
                <div class="card-stats">
                    <div class="stat-row">
                        <span class="stat-label-text">Products Supplied</span>
                        <span class="stat-value-text"><?= $s['product_count'] ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label-text">Avg Lead Time</span>
                        <span class="stat-value-text"><?= $lead_time ?> days</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label-text">On-Time Rate</span>
                        <span class="stat-value-text on-time"><?= $on_time_rate ?>%</span>
                    </div>
                </div>

                <!-- Footer: Status Badge + Button -->
                <div class="card-footer">
                    <span class="status-badge <?= $stock_status ?>">
                        <?= $stock_status === 'in-stock' ? 'Active' : 'Needs Review' ?>
                    </span>
                    
                    <a href="supplier_details.php?id=<?= $s['supplier_id'] ?>">
                        <button class="view-details-btn">View Details</button>
                    </a>
                </div>
            </div>
            <?php 
                    }
                } else {
                    echo "<div class='no-suppliers'>No suppliers found</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error-message'>Error loading suppliers: " . $e->getMessage() . "</div>";
            }
            ?>
        </div>

        <!-- Pagination -->
        <div class="table-container">
            <div class="pagination-section">
                <p class="pagination-info">
                    <?php
                    $total_suppliers = $conn->query("SELECT COUNT(*) as total FROM suppliers")->fetch_assoc()['total'];
                    ?>
                    Showing <span>1-<?= $total_suppliers ?></span> of <span><?= $total_suppliers ?></span> suppliers
                </p>
                <div class="pagination-buttons">
                    <button class="pagination-btn" disabled>Previous</button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn" disabled>Next</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Simple filtering functionality
        document.getElementById('searchInput').addEventListener('input', filterSuppliers);
        document.getElementById('performanceFilter').addEventListener('change', filterSuppliers);
        
        function filterSuppliers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const performanceFilter = document.getElementById('performanceFilter').value;
            const suppliers = document.querySelectorAll('.supplier-card');
            
            suppliers.forEach(supplier => {
                const name = supplier.getAttribute('data-name').toLowerCase();
                const performance = supplier.getAttribute('data-performance');
                
                const matchesSearch = name.includes(searchTerm);
                const matchesPerformance = !performanceFilter || performance === performanceFilter;
                
                if (matchesSearch && matchesPerformance) {
                    supplier.style.display = 'block';
                } else {
                    supplier.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>