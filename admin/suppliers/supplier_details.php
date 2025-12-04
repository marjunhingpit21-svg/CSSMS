<?php
include '../includes/auth.php';
include '../includes/db.php';

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

    // 2. FIXED: Get performance stats without GROUP BY
    $perf_sql = "
        SELECT 
            COUNT(DISTINCT st.transaction_id) as tx_count,
            COALESCE(SUM(ps.total_cost), 0) as total_spent,
            AVG(ps.supplier_rating) as avg_rating,
            MAX(st.transaction_date) as last_tx_date
        FROM stock_transactions st
        LEFT JOIN purchase_stock ps ON st.transaction_id = ps.transaction_id
        WHERE st.supplier_id = ? AND st.transaction_type = 'purchase'
    ";

    $stmt = $conn->prepare($perf_sql);
    if (!$stmt) {
        error_log("Stats query failed: " . $conn->error);
        throw new Exception("Failed to prepare stats query");
    }
    
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

} catch (Exception $e) {
    error_log("Supplier details error: " . $e->getMessage());
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
        main {
            margin-left: 300px !important;
            margin-top: 50px !important;
        }

        .add-transaction-btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white;
            font-weight: 600;
            font-size: 15px;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .add-transaction-btn:hover {
            background: linear-gradient(135deg, #7c3aed, #9333ea);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.5);
        }

        .add-transaction-btn:active {
            transform: translateY(0);
        }
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
                
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= urlencode($supplier['email'] ?? '') ?>&su=Inquiry%20from%20Altière%20Clothing&body=Hi%20<?= urlencode($supplier['contact_person'] ?? $supplier['supplier_name']) ?>%2C%0A%0AI%20hope%20this%20email%20finds%20you%20well.%0A%0AI'm%20reaching%20out%20regarding...%0A%0ABest%20regards%2C%0A[Your%20Name]%0AAltière%20Admin%20Team" 
                    target="_blank" 
                    class="inline-block">
                        <button class="px-8 py-4 bg-violet-600 hover:bg-violet-700 rounded-xl font-semibold transition">
                            Contact Through Email
                        </button>
                </a>

                <a href="edit_supplier.php?id=<?= $supplier_id ?>">
                    <button class="px-8 py-4 bg-white/10 border border-white/20 hover:bg-white/20 rounded-xl font-semibold">Edit</button>
                </a>
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
                <p class="text-3xl font-bold text-green-400">₱<?= number_format($stats['total_spent'], 2) ?></p>
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

        <!-- Purchase History -->
        <div class="mt-12">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">
                    Purchase History 
                    <span class="text-lg font-normal text-gray-400">
                        (<?= $stats['transaction_count'] ?> transaction<?= $stats['transaction_count'] == 1 ? '' : 's' ?>)
                    </span>
                </h2>

                <!-- Add Transaction Button -->
                <a href="add_transaction.php?supplier_id=<?= $supplier_id ?>" class="add-transaction-btn">
                    + Add Transaction
                </a>
            </div>

            <?php
            // FIXED QUERY
            $tx_sql = "
                SELECT 
                    st.transaction_id,
                    st.transaction_date,
                    st.quantity,
                    st.notes,
                    COALESCE(ps.total_cost, 0) as total_cost,
                    ps.supplier_rating,
                    ps.defective_products,
                    ps.expected_delivery,
                    ps.actual_delivery,
                    p.product_name,
                    c.category_name,
                    COALESCE(s.size_name, 'One Size') as size_name
                FROM stock_transactions st
                LEFT JOIN purchase_stock ps ON st.transaction_id = ps.transaction_id
                LEFT JOIN inventory inv ON st.inventory_id = inv.inventory_id
                LEFT JOIN products p ON inv.product_id = p.product_id
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN sizes s ON inv.size_id = s.size_id
                WHERE st.supplier_id = ? AND st.transaction_type = 'purchase'
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
            } else {
                error_log("Transaction query failed: " . $conn->error);
            }
            ?>

            <?php if (empty($transactions)): ?>
                <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-16 text-center text-gray-500">
                    <p class="text-xl">No purchase transactions found.</p>
                    <p class="text-sm mt-3">When you receive stock from this supplier, transactions will appear here.</p>
                </div>
            <?php else: ?>
                <div class="bg-gray-900/60 backdrop-blur-xl border border-white/10 rounded-3xl overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-white/5">
                            <tr>
                                <th class="text-left p-4">Date</th>
                                <th class="text-left p-4">Product</th>
                                <th class="text-left p-4">Size</th>
                                <th class="text-left p-4">Qty Ordered</th>
                                <th class="text-left p-4">Defective</th>
                                <th class="text-left p-4">Amount Paid</th>
                                <th class="text-left p-4">Rating</th>
                                <th class="text-left p-4">Notes</th>
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
                                            <div class="font-medium"><?= htmlspecialchars($tx['product_name'] ?? 'Unknown Product') ?></div>
                                            <?php if (!empty($tx['category_name'])): ?>
                                                <div class="text-xs text-gray-400"><?= htmlspecialchars($tx['category_name']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-300">
                                        <?= htmlspecialchars($tx['size_name']) ?>
                                    </td>
                                    <td class="p-4 font-semibold text-green-400">
                                        +<?= number_format($tx['quantity']) ?>
                                    </td>
                                    <td class="p-4 font-semibold <?= ($tx['defective_products'] ?? 0) > 0 ? 'text-red-400' : 'text-gray-500' ?>">
                                        <?= $tx['defective_products'] ? number_format($tx['defective_products']) : '0' ?>
                                    </td>
                                    <td class="p-4 font-medium text-lg">
                                        ₱<?= number_format($tx['total_cost'], 2) ?>
                                    </td>
                                    <td class="p-4">
                                        <?php if (!empty($tx['supplier_rating'])): ?>
                                            <div class="flex items-center gap-1">
                                                <span class="text-yellow-400 text-lg">★</span>
                                                <span class="font-bold"><?= number_format($tx['supplier_rating'], 1) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-sm">Not rated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-sm text-gray-400 max-w-xs">
                                        <?= htmlspecialchars($tx['notes'] ?? '—') ?>
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
            alert('Edit functionality coming soon!');
        }
    </script>
</body>
</html>