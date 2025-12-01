<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <!-- Header -->
        <div class="header-section">
            <h1 class="page-title">Customer Management</h1>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Customers</p>
                <p class="stat-value"><?= htmlspecialchars($totalSuppliers) ?></p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">Active Customers</p>
                <p class="stat-value green"></p>
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
                </select>
            </div>
        </div>

        <!-- Suppliers Grid -->
        <div class="customers-grid" id="customersGrid">

        <!-- Pagination -->
        <div class="table-container">
            <div class="pagination-section">
                <p class="pagination-info">Showing all <?= count($suppliers) ?> suppliers</p>
            </div>
        </div>
    </main>

</body>
</html>