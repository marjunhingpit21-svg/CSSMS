<?php include '../includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Stitch Factory • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../products/products.css">
    <style>
        .performance-bar { height: 8px; border-radius: 4px; background: rgba(255,255,255,0.1); }
        .performance-fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
        .excellent { background: linear-gradient(to right, #10b981, #34d399); }
        .good { background: linear-gradient(to right, #f59e0b, #fbbf24); }
        .needs-review { background: linear-gradient(to right, #ef4444, #f87171); }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../header.php'; ?>

    <main>
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1 class="page-title">Prime Stitch Factory</h1>
                <p class="text-gray-400 mt-2 text-lg">Supplier ID: <span class="text-white font-semibold">#SUP-018</span></p>
            </div>
            <div class="flex gap-4">
                <button class="add-btn text-sm px-8">Contact Supplier</button>
                <button class="bg-white/10 backdrop-blur-xl border border-white/20 px-8 py-4 rounded-xl font-bold hover:bg-white/20 transition">
                    Edit Supplier
                </button>
            </div>
        </div>

        <!-- Supplier Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Products Supplied</p>
                <p class="stat-value">201</p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">On-Time Delivery Rate</p>
                <p class="stat-value green">99.5%</p>
            </div>
            <div class="stat-card amber-orange">
                <p class="stat-label">Avg Lead Time</p>
                <p class="stat-value orange">5 days</p>
            </div>
            <div class="stat-card red-rose">
                <p class="stat-label">Total Spent (2025)</p>
                <p class="stat-value red">$248,920</p>
            </div>
        </div>

        <!-- Supplier Info + Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            <!-- Info Card -->
            <div class="bg-gray-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl p-8 lg:col-span-1">
                <h3 class="text-2xl font-extrabold text-white mb-8">Supplier Details</h3>
                <div class="space-y-6 text-left">
                    <div>
                        <p class="text-gray-400 text-sm">Contact Person</p>
                        <p class="text-white font-bold text-lg">Maria Chen</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Email</p>
                        <p class="text-white font-bold">maria@primestitchfactory.com</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Phone</p>
                        <p class="text-white font-bold">+1 (555) 892-4471</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Location</p>
                        <p class="text-white font-bold">Guangzhou, China</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Categories</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="px-4 py-2 bg-violet-500/20 text-violet-300 rounded-full text-sm font-medium">Fabric</span>
                            <span class="px-4 py-2 bg-pink-500/20 text-pink-300 rounded-full text-sm font-medium">Stitching</span>
                            <span class="px-4 py-2 bg-emerald-500/20 text-emerald-300 rounded-full text-sm font-medium">Labels</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Overall Rating</p>
                        <div class="flex items-center gap-3 mt-2">
                            <div class="flex items-center">
                                <span class="text-yellow-400 text-3xl">★</span>
                                <span class="text-3xl font-extrabold text-white ml-1">5.0</span>
                            </div>
                            <span class="text-gray-400">(142 reviews)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-gray-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                    <h3 class="text-2xl font-extrabold text-white mb-8">Performance Overview</h3>
                    <div class="space-y-8">
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">Quality Score</span>
                                <span class="text-white font-bold">98%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: 98%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">On-Time Delivery</span>
                                <span class="text-white font-bold">99.5%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: 99.5%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">Communication</span>
                                <span class="text-white font-bold">96%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: 96%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-3">
                                <span class="text-gray-300 font-medium">Defect Rate</span>
                                <span class="text-white font-bold">0.8%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill excellent" style="width: 8%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Lower is better</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900/60 backdrop-blur-2xl border border-white/10 rounded-3xl p-8">
                    <h3 class="text-xl font-bold text-white mb-6">Recent Notes</h3>
                    <p class="text-gray-300 italic">"Excellent quality on Spring '25 cotton batch. Considering increasing order volume by 30% next quarter."</p>
                    <p class="text-right text-sm text-gray-500 mt-4">— Alex Rivera, Procurement • 2 days ago</p>
                </div>
            </div>
        </div>

        <!-- Transaction History -->
        <div class="filters-section mb-6">
            <h2 class="text-2xl font-extrabold text-white mb-6">Transaction History</h2>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>PO Number</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Delivery</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $transactions = [
                        ['date' => '2025-11-20', 'po' => 'PO-2025-1189', 'desc' => 'Organic Cotton Jersey (Black)', 'qty' => 2500, 'price' => 4.20, 'status' => 'Delivered', 'delivery' => 'On Time', 'class' => 'in-stock'],
                        ['date' => '2025-11-05', 'po' => 'PO-2025-1162', 'desc' => 'Ribbed Cuff Fabric (12 colors)', 'qty' => 1800, 'price' => 5.80, 'status' => 'Delivered', 'delivery' => 'On Time', 'class' => 'in-stock'],
                        ['date' => '2025-10-28', 'po' => 'PO-2025-1141', 'desc' => 'Custom Woven Labels', 'qty' => 10000, 'price' => 0.18, 'status' => 'Delivered', 'delivery' => '2 Days Early', 'class' => 'in-stock'],
                        ['date' => '2025-10-15', 'po' => 'PO-2025-1119', 'desc' => 'French Terry Fleece', 'qty' => 3200, 'price' => 7.10, 'status' => 'Delivered', 'delivery' => 'On Time', 'class' => 'in-stock'],
                        ['date' => '2025-09-22', 'po' => 'PO-2025-1097', 'desc' => 'Heavyweight Hoodie Blanks', 'qty' => 4000, 'price' => 12.50, 'status' => 'Delivered', 'delivery' => '1 Day Late', 'class' => 'low-stock'],
                    ];
                    foreach($transactions as $t):
                        $total = $t['qty'] * $t['price'];
                    ?>
                    <tr>
                        <td class="text-gray-300"><?=$t['date']?></td>
                        <td class="font-mono text-violet-400 font-semibold"><?=$t['po']?></td>
                        <td class="text-left"><?=$t['desc']?></td>
                        <td><?=number_format($t['qty'])?> pcs</td>
                        <td>$<?=number_format($t['price'], 2)?></td>
                        <td class="font-bold text-white">$<?=number_format($total, 2)?></td>
                        <td>
                            <span class="status-badge in-stock">Delivered</span>
                        </td>
                        <td>
                            <span class="stock-text <?= $t['delivery'] === '1 Day Late' ? 'orange' : 'green' ?>">
                                <?=$t['delivery']?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-section">
                <p class="pagination-info">Showing <span>1-5</span> of <span>42</span> transactions</p>
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