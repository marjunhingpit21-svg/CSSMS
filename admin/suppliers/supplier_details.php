<?php
include '../includes/auth.php';
include '../db.php';

$supplier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($supplier_id <= 0) die("Invalid supplier ID");

$supplier = null;
$stats = [
    'transaction_count' => 0,
    'total_spent'       => 0.00,
    'avg_rating'        => null,
    'last_transaction'  => null,
    'days_since_last'   => 'Never',
    'is_active'         => false
];
$supplied_products = [];

try {
    // 1. Get supplier info
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    $stmt->close();

    if (!$supplier) die("Supplier not found");

    // 2. Get performance stats (safe version)
    $perf_sql = "
        SELECT 
            COUNT(ps.purchase_id) as tx_count,
            COALESCE(SUM(ps.total_amount), 0) as total_spent,
            AVG(ps.supplier_rating) as avg_rating,
            MAX(st.transaction_date) as last_tx_date
        FROM stock_transactions st
        LEFT JOIN purchase_stock ps ON st.transaction_id = ps.transaction_id
        WHERE st.supplier_id = ?
        GROUP BY st.supplier_id
    ";

    $stmt = $conn->prepare($perf_sql);
    if (!$stmt) {
        // Fallback if tables/columns don't exist yet
        $stats = array_merge($stats, [
            'transaction_count' => 0,
            'total_spent' => 0.00,
            'avg_rating' => null,
            'last_transaction' => null,
            'is_active' => false
        ]);
    } else {
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            $stats['transaction_count'] = (int)$res['tx_count'];
            $stats['total_spent']       = (float)$res['total_spent'];
            $stats['avg_rating']        = $res['avg_rating'] ? round($res['avg_rating'], 1) : null;

            if ($res['last_tx_date']) {
                $lastDate = new DateTime($res['last_tx_date']);
                $now = new DateTime();
                $interval = $now->diff($lastDate);
                $days = $interval->days;
                $stats['days_since_last'] = $days == 0 ? 'Today' : "$days day" . ($days > 1 ? 's' : '') . " ago";
                $stats['last_transaction'] = $lastDate->format('M d, Y');
                $stats['is_active'] = $days <= 90;
            }
        }
    }

    // 3. Get products ever purchased from this supplier (safe fallback)
    $products_sql = "
        SELECT DISTINCT
            p.product_id,
            p.product_name,
            c.category_name,
            p.price,
            p.cost_price,
            COALESCE(SUM(i.quantity), 0) as current_stock
        FROM stock_transactions st
        JOIN inventory inv ON st.inventory_id = inv.inventory_id
        JOIN products p ON inv.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN inventory i ON p.product_id = i.product_id
        WHERE st.supplier_id = ?
        GROUP BY p.product_id
        ORDER BY p.product_name
    ";

    $stmt = $conn->prepare($products_sql);
    if (!$stmt) {
        // If query fails (likely no data or column issue), just show empty list
        $supplied_products = [];
    } else {
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $stock = (int)$row['current_stock'];
            $row['stock_status'] = $stock > 20 ? 'In Stock' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
            $supplied_products[] = $row;
        }
        $stmt->close();
    }

} catch (Exception $e) {
    error_log("Supplier details error: " . $e->getMessage());
    // Don't crash the page — just show basic info
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($supplier['supplier_name'] ?? 'Supplier') ?> • TrendyWear Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .performance-bar { height: 8px; border-radius: 4px; background: rgba(255,255,255,0.1); }
        .performance-fill { height: 100%; border-radius: 4px; }
        .excellent { background: linear-gradient(to right, #10b981, #34d399); }
        .good { background: linear-gradient(to right, #f59e0b, #fbbf24); }
        .poor { background: linear-gradient(to right, #ef4444, #f87171); }
    </style>
</head>
<body class="bg-gray-950 text-white">
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main class="p-6 max-w-7xl mx-auto">
        <?php if (!$supplier): ?>
            <div class="text-center py-16 text-red-400 text-xl">Supplier not found.</div>
        <?php else: ?>

        <div class="flex justify-between items-start mb-10">
            <div>
                <h1 class="text-4xl font-extrabold"><?= htmlspecialchars($supplier['supplier_name']) ?></h1>
                <p class="text-gray-400 mt-2">
                    #SUP-<?= str_pad($supplier['supplier_id'], 4, '0', STR_PAD_LEFT) ?>
                    • Added <?= date('M Y', strtotime($supplier['created_at'])) ?>
                </p>
            </div>
            <div class="flex gap-4">
                <button onclick="contactSupplier()" class="px-8 py-4 bg-violet-600 hover:bg-violet-700 rounded-xl font-semibold">
                    Contact
                </button>
                <button onclick="editSupplier()" class="px-8 py-4 bg-white/10 border border-white/20 hover:bg-white/20 rounded-xl font-semibold">
                    Edit
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-2xl p-6">
                <p class="text-gray-400 text-sm">Total Purchases</p>
                <p class="text-3xl font-bold"><?= $stats['transaction_count'] ?: '0' ?></p>
            </div>
            <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-2xl p-6">
                <p class="text-gray-400 text-sm">Total Spent</p>
                <p class="text-3xl font-bold text-green-400">$<?= number_format($stats['total_spent'], 2) ?></p>
            </div>
            <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-2xl p-6">
                <p class="text-gray-400 text-sm">Status</p>
                <p class="text-3xl font-bold <?= $stats['is_active'] ? 'text-green-400' : 'text-red-400' ?>">
                    <?= $stats['is_active'] ? 'Active' : 'Inactive' ?>
                </p>
            </div>
            <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-2xl p-6">
                <p class="text-gray-400 text-sm">Last Purchase</p>
                <p class="text-xl font-bold">
                    <?= $stats['last_transaction'] ?: 'Never' ?>
                    <?php if ($stats['last_transaction']): ?>
                        <br><small class="text-gray-500"><?= $stats['days_since_last'] ?></small>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Contact + Rating -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-8">
                <h3 class="text-xl font-bold mb-6">Contact Info</h3>
                <div class="space-y-4 text-sm">
                    <div><span class="text-gray-500">Contact:</span> <span class="font-medium"><?= htmlspecialchars($supplier['contact_person'] ?: '—') ?></span></div>
                    <div><span class="text-gray-500">Email:</span> <span class="font-medium"><?= htmlspecialchars($supplier['email'] ?: '—') ?></span></div>
                    <div><span class="text-gray-500">Phone:</span> <span class="font-medium"><?= htmlspecialchars($supplier['phone'] ?: '—') ?></span></div>
                    <div><span class="text-gray-500">Address:</span> <span class="font-medium"><?= htmlspecialchars($supplier['address'] ?: '—') ?></span></div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-8 text-center">
                <h3 class="text-xl font-bold mb-6">Supplier Rating</h3>
                <?php if ($stats['avg_rating']): ?>
                    <div class="text-8xl text-yellow-400">★</div>
                    <div class="text-6xl font-bold"><?= $stats['avg_rating'] ?></div>
                    <p class="text-xl text-gray-400">out of 5.0</p>
                    <p class="text-gray-500 mt-2">from <?= $stats['transaction_count'] ?> purchase<?= $stats['transaction_count'] == 1 ? '' : 's' ?></p>
                <?php else: ?>
                    <p class="text-6xl text-gray-600 mb-4">☆ ☆ ☆ ☆ ☆</p>
                    <p class="text-2xl text-gray-500">No ratings yet</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-12">
            <h2 class="text-2xl font-bold mb-6">
                Purchase History 
                <span class="text-lg font-normal text-gray-400">(<?= $stats['transaction_count'] ?> transaction<?= $stats['transaction_count'] == 1 ? '' : 's' ?>)</span>
            </h2>

            <?php if ($stats['transaction_count'] == 0): ?>
                <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-16 text-center text-gray-500">
                    <p class="text-xl">No purchase transactions recorded yet.</p>
                    <p class="text-sm mt-3">When you receive stock from this supplier, transactions will appear here.</p>
                </div>
            <?php else: ?>
                <?php
                // Fetch actual transaction records
                $tx_sql = "
                    SELECT 
                        st.transaction_id,
                        st.transaction_date,
                        st.quantity_received,
                        ps.total_amount,
                        ps.supplier_rating,
                        p.product_name,
                        c.category_name,
                        inv.size_id,
                        cs.size_name
                    FROM stock_transactions st
                    LEFT JOIN purchase_stock ps ON st.transaction_id = ps.transaction_id
                    LEFT JOIN inventory inv ON st.inventory_id = inv.inventory_id
                    LEFT JOIN products p ON inv.product_id = p.product_id
                    LEFT JOIN categories c ON p.category_id = c.category_id
                    LEFT JOIN clothing_sizes cs ON inv.size_id = cs.clothing_size_id
                    WHERE st.supplier_id = ?
                    ORDER BY st.transaction_date DESC
                ";

                $transactions = [];
                $stmt = $conn->prepare($tx_sql);
                if ($stmt) {
                    $stmt->bind_param("i", $supplier_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $transactions[] = $row;
                    }
                    $stmt->close();
                }
                ?>

                <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-white/5">
                            <tr>
                                <th class="text-left p-4">Date</th>
                                <th class="text-left p-4">Product</th>
                                <th class="text-left p-4">Size</th>
                                <th class="text-left p-4">Qty Received</th>
                                <th class="text-left p-4">Amount Paid</th>
                                <th class="text-left p-4">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr class="border-t border-white/10 hover:bg-white/5 transition">
                                    <td class="p-4 text-sm">
                                        <?= date('M d, Y', strtotime($tx['transaction_date'])) ?>
                                        <br><small class="text-gray-500"><?= date('g:i A', strtotime($tx['transaction_date'])) ?></small>
                                    </td>
                                    <td class="p-4">
                                        <div>
                                            <div class="font-medium"><?= htmlspecialchars($tx['product_name'] ?? '—') ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($tx['category_name'] ?? '') ?></div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-300">
                                        <?= htmlspecialchars($tx['size_name'] ?? '—') ?>
                                    </td>
                                    <td class="p-4 font-semibold text-green-400">
                                        +<?= number_format($tx['quantity_received']) ?>
                                    </td>
                                    <td class="p-4 font-medium">
                                        $<?= number_format($tx['total_amount'] ?? 0, 2) ?>
                                    </td>
                                    <td class="p-4">
                                        <?php if ($tx['supplier_rating']): ?>
                                            <div class="flex items-center gap-1">
                                                <span class="text-yellow-400 text-lg">★</span>
                                                <span class="font-bold"><?= number_format($tx['supplier_rating'], 1) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-sm">Not rated</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </main>

    <script>
        function contactSupplier() {
            const email = <?= json_encode($supplier['email'] ?? '') ?>;
            if (email && email !== '—') {
                location.href = `mailto:${email}`;
            } else {
                alert('No email available');
            }
        }
        function editSupplier() {
            alert('Coming soon!');
        }
    </script>
</body>
</html>