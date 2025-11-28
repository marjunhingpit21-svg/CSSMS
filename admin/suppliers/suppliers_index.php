<?php include '../includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../products/products.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../header.php'; ?>

    <main>
        <!-- Header -->
        <div class="header-section">
            <h1 class="page-title">Supplier Management</h1>
            <button class="add-btn">+ Add Supplier</button>
        </div>

        <!-- Stats -->
        <div class="stats-grid grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Suppliers</p>
                <p class="stat-value">28</p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">Active Suppliers</p>
                <p class="stat-value green">24</p>
            </div>
            <div class="stat-card red-rose">
                <p class="stat-label">Pending Reviews</p>
                <p class="stat-value red">3</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section mb-12">
            <div class="filters-grid">
                <div class="search-wrapper">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" placeholder="Search suppliers..." class="search-input">
                </div>
                <select class="filter-select">
                    <option>All Categories</option>
                    <option>Fabric</option>
                    <option>Accessories</option>
                    <option>Printing</option>
                    <option>Packaging</option>
                </select>
                <select class="filter-select">
                    <option>All Performance</option>
                    <option>Excellent (95%+)</option>
                    <option>Good (90–94%)</option>
                    <option>Needs Review (<90%)</option>
                </select>
                <select class="filter-select">
                    <option>Sort by: Name A-Z</option>
                    <option>Rating High-Low</option>
                    <option>Lead Time</option>
                    <option>Products Supplied</option>
                </select>
            </div>
        </div>

        <!-- Suppliers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            <?php 
            $suppliers = [
                ['name' => 'Urban Threads Co.',      'items' => 142, 'rating' => 4.9, 'lead' => '7 days',  'on_time' => '98.2%', 'stock' => 'in-stock'],
                ['name' => 'Luxe Fabric Mills',      'items' => 98,  'rating' => 4.7, 'lead' => '12 days', 'on_time' => '98.2%', 'stock' => 'in-stock'],
                ['name' => 'Vintage Denim Supply',   'items' => 67,  'rating' => 4.5, 'lead' => '10 days', 'on_time' => '98.2%', 'stock' => 'low-stock'],
                ['name' => 'EcoCotton Partners',     'items' => 89,  'rating' => 4.8, 'lead' => '9 days',  'on_time' => '97.8%', 'stock' => 'in-stock'],
                ['name' => 'SilkRoad Textiles',      'items' => 54,  'rating' => 4.3, 'lead' => '15 days', 'on_time' => '89.1%', 'stock' => 'low-stock'],
                ['name' => 'Prime Stitch Factory',   'items' => 201, 'rating' => 5.0, 'lead' => '5 days',  'on_time' => '99.5%', 'stock' => 'in-stock'],
            ];
            foreach($suppliers as $s): ?>
            <div class="bg-gray-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 hover:scale-105 transition-all duration-300 shadow-2xl">
                <!-- Header: Name + Rating -->
                <div class="flex justify-between items-center mb-8">
                    <h3 class="text-2xl font-extrabold text-white"><?=$s['name']?></h3>
                    <div class="flex items-center gap-1">
                        <span class="text-yellow-400 text-2xl">★</span>
                        <span class="text-xl font-bold text-white"><?=$s['rating']?></span>
                    </div>
                </div>

                <!-- Stats -->
                <div class="space-y-5 text-left mb-10">
                    <div class="flex justify-between">
                        <span class="text-gray-400 text-sm">Products Supplied</span>
                        <span class="text-white font-semibold text-lg"><?=$s['items']?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400 text-sm">Avg Lead Time</span>
                        <span class="text-white font-semibold text-lg"><?=$s['lead']?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400 text-sm">On-Time Rate</span>
                        <span class="text-green-400 font-bold text-lg"><?=$s['on_time']?></span>
                    </div>
                </div>

                <!-- Footer: Stock Badge + Button -->
                <div class="flex items-center justify-between">
                    <span class="status-badge <?=$s['stock'] === 'in-stock' ? 'in-stock' : 'low-stock'?>">
                        <?= $s['stock'] === 'in-stock' ? 'In Stock' : 'Low Stock' ?>
                    </span>
                    
                    <a href="supplier_details.php">
                        <button class="bg-gradient-to-r from-violet-600 to-pink-600 px-6 py-3 rounded-xl font-bold hover:scale-110 transition text-sm">
                            View Details
                        </button>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="table-container !rounded-b-3xl">
            <div class="pagination-section">
                <p class="pagination-info">Showing <span>1-12</span> of <span>28</span> suppliers</p>
                <div>
                    <button class="pagination-btn">Previous</button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">Next</button>
                </div>
            </div>
        </div>
    </main>
</body>
</html>