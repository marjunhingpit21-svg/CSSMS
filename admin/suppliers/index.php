<?php include '../includes/auth.php'; 
include '../includes/db.php'; 
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers • Altiere Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="suppliers.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <div>
                <h1 class="page-title">Suppliers</h1>
                <p class="header-subtitle">All your supplier partners in one beautiful place</p>
            </div>
        </div>

        <!-- Live Search -->
        <input type="text" id="searchInput" placeholder="Search suppliers..." 
               style="width:100%; max-width:460px; padding:16px 20px; border-radius:16px; 
                      border:2px solid #f0e6f8; font-size:1.1rem; margin-bottom:32px; outline:none;">

        <?php if ($suppliers && $suppliers->num_rows > 0): ?>
            <div class="suppliers-grid" id="suppliersGrid">
                <?php while ($s = $suppliers->fetch_assoc()): ?>
                <div class="supplier-card" data-name="<?= strtolower($s['supplier_name']) ?>">
                    <div class="card-header">
                        <h3><?= htmlspecialchars($s['supplier_name']) ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Contact</span>
                            <span class="info-value"><?= htmlspecialchars($s['contact_person'] ?: '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone</span>
                            <span class="info-value"><?= htmlspecialchars($s['phone'] ?: '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?= htmlspecialchars($s['email'] ?: '—') ?></span>
                        </div>
                        <span class="status-badge status-<?= $s['status'] ?? 'inactive' ?>">
                            <?= ucfirst($s['status'] ?? 'inactive') ?>
                        </span>
                    </div>
                    <div class="card-actions">
                        <a href="edit.php?id=<?= $s['supplier_id'] ?>" class="edit-btn">Edit</a>
                        <a href="#" class="delete-btn" data-id="<?= $s['supplier_id'] ?>" data-name="<?= htmlspecialchars($s['supplier_name']) ?>">
                            Delete
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <span class="material-icons">local_shipping</span>
                <h3>No suppliers yet</h3>
                <p>Add your first supplier to get started</p>
            </div>
        <?php endif; ?>

        <a href="add.php" class="fab">
            <span class="material-icons">add</span>
        </a>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:16px; padding:32px; max-width:420px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <span class="material-icons" style="font-size:64px; color:#e91e63; margin-bottom:16px;">warning</span>
            <h3 style="margin:0 0 12px; color:#333;">Delete Supplier?</h3>
            <p style="color:#666; margin-bottom:24px;">This action cannot be undone. Delete <strong id="deleteName"></strong>?</p>
            <div style="display:flex; gap:12px; justify-content:center;">
                <button id="cancelDelete" style="padding:12px 28px; border:2px solid #ddd; border-radius:12px; background:white; cursor:pointer;">Cancel</button>
                <button id="confirmDelete" style="padding:12px 28px; background:#e91e63; color:white; border:none; border-radius:12px; cursor:pointer;">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
    // Live Search
    const searchInput = document.getElementById('searchInput');
    const cards = document.querySelectorAll('.supplier-card');

    searchInput?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        cards.forEach(card => {
            const name = card.dataset.name;
            card.style.display = name.includes(term) ? '' : 'none';
        });
    });

    // Toast Notification
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('added')) showToast('Supplier added successfully!', 'success');
    if (urlParams.get('updated')) showToast('Supplier updated successfully!', 'success');
    if (urlParams.get('deleted')) showToast('Supplier deleted', 'error');

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = `
            position:fixed; top:100px; right:30px; z-index:10000;
            background:${type==='success'?'#e91e63':'#c62828'};
            color:white; padding:18px 36px; border-radius:16px;
            font-weight:600; font-size:1rem;
            box-shadow:0 10px 30px rgba(0,0,0,0.2);
            transform:translateX(400px); opacity:0; transition:all 0.5s ease;
        `;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.transform='translateX(0)'; toast.style.opacity='1'; }, 100);
        setTimeout(() => { toast.style.transform='translateX(400px)'; toast.style.opacity='0'; 
            setTimeout(() => toast.remove(), 500); }, 3000);
    }

    // Delete Modal
    let deleteId = null;
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            deleteId = btn.dataset.id;
            document.getElementById('deleteName').textContent = btn.dataset.name;
            document.getElementById('deleteModal').style.display = 'flex';
        });
    });

    document.getElementById('cancelDelete').addEventListener('click', () => {
        document.getElementById('deleteModal').style.display = 'none';
    });

    document.getElementById('confirmDelete').addEventListener('click', () => {
        window.location.href = `delete.php?id=${deleteId}&from=index`;
    });
    </script>
</body>
</html>