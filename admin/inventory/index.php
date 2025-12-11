<?php 
include '../includes/auth.php';
include '../includes/db.php';

// Fetch low stock items (1–20 units) with size display
$low_stock = $conn->query("
    SELECT 
        ps.product_size_id,
        ps.stock_quantity,
        p.product_name,
        p.product_id,
        c.category_name,
        COALESCE(cs.size_name, CONCAT(ss.size_us, ' US'), 'One Size') AS display_size
    FROM product_sizes ps
    JOIN products p ON ps.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN clothing_sizes cs ON ps.clothing_size_id = cs.clothing_size_id
    LEFT JOIN shoe_sizes ss ON ps.shoe_size_id = ss.shoe_size_id
    WHERE ps.stock_quantity BETWEEN 1 AND 20 
      AND ps.is_available = 1
    ORDER BY ps.stock_quantity ASC, p.product_name
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Alerts • Altiere Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="inventory.css">
    <style>
        .size-badge {
            background: #e91e63;
            color: white;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 8px;
            display: inline-block;
        }
        .stock-value { font-size: 1.8rem; font-weight: 700; color: #c62828; }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <h1 class="page-title">Inventory Alerts</h1>
            <p class="header-subtitle">Low stock items (1–20 units) that need restocking</p>
        </div>

        <input type="text" id="searchInput" placeholder="Search by product name or size..." class="search-input">

        <div class="alerts-grid" id="alertsGrid">
            <?php if ($low_stock && $low_stock->num_rows > 0): ?>
                <?php while ($item = $low_stock->fetch_assoc()): ?>
                    <div class="alert-card" 
                         data-name="<?= strtolower($item['product_name'] . ' ' . $item['display_size']) ?>">
                        <div class="card-header" style="background: linear-gradient(135deg,#ff9800,#f57c00);">
                            <h3><?= htmlspecialchars($item['product_name']) ?></h3>
                            <span class="size-badge"><?= htmlspecialchars($item['display_size']) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Stock Left</span>
                                <span class="stock-value"><?= $item['stock_quantity'] ?> units</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Category</span>
                                <span class="info-value"><?= htmlspecialchars($item['category_name'] ?: '—') ?></span>
                            </div>
                            <span class="status-badge status-low">Low Stock Alert</span>
                        </div>
                        <div class="card-actions">
                            <a href="#" class="edit-btn adjust-stock-btn" 
                               data-id="<?= $item['product_size_id'] ?>" 
                               data-current="<?= $item['stock_quantity'] ?>">
                               Adjust Stock
                            </a>
                            <a href="../products/view_product.php?id=<?= $item['product_id'] ?>" class="edit-btn" style="background:#1976d2;color:white;">
                                View Product
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <span class="material-icons">thumb_up</span>
                    <h3>All items in stock!</h3>
                    <p>No low stock alerts right now.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Adjust Stock Modal -->
        <div id="adjustModal" class="modal-overlay">
            <div class="modal-content">
                <h3>Adjust Stock Quantity</h3>
                <form id="adjustForm">
                    <input type="hidden" id="sizeId">
                    <label>New Stock Quantity</label>
                    <input type="number" id="newStock" min="0" required style="width:100%;padding:14px;margin:12px 0;border:1px solid #ddd;border-radius:12px;">
                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
                        <button type="button" id="cancelBtn" class="cancel-btn">Cancel</button>
                        <button type="submit" class="save-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Live Search (now includes size)
        const searchInput = document.getElementById('searchInput');
        searchInput?.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.alert-card').forEach(card => {
                card.style.display = card.dataset.name.includes(term) ? '' : 'none';
            });
        });

        // Modal Handling
        const modal = document.getElementById('adjustModal');
        document.querySelectorAll('.adjust-stock-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('sizeId').value = btn.dataset.id;
                document.getElementById('newStock').value = btn.dataset.current;
                modal.style.display = 'flex';
                document.getElementById('newStock').focus();
            });
        });

        document.getElementById('cancelBtn').onclick = () => modal.style.display = 'none';
        window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };

        // Submit Form via AJAX
        document.getElementById('adjustForm').onsubmit = async (e) => {
            e.preventDefault();
            const id = document.getElementById('sizeId').value;
            const stock = document.getElementById('newStock').value;

            const res = await fetch('update_stock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&stock=${stock}`
            });

            if (res.ok) {
                showToast('Stock updated successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error updating stock', 'error');
            }
        };

        function showToast(msg, type) {
            const toast = document.createElement('div');
            toast.textContent = msg;
            toast.style.cssText = `
                position:fixed;top:100px;right:30px;z-index:10000;
                background:${type==='success'?'#e91e63':'#c62828'};
                color:white;padding:18px 36px;border-radius:16px;
                font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,0.2);
                transform:translateX(400px);opacity:0;transition:all 0.4s;
            `;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.transform='translateX(0)'; toast.style.opacity='1'; }, 100);
            setTimeout(() => { toast.style.transform='translateX(400px)'; toast.style.opacity='0'; 
                setTimeout(() => toast.remove(), 500); }, 3000);
        }
    </script>

    <style>
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 32px;
            border-radius: 16px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .cancel-btn, .save-btn {
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .cancel-btn { background: #f0f0f0; border: 2px solid #ddd; }
        .save-btn { background: #e91e63; color: white; border: none; }
    </style>
</body>
</html>