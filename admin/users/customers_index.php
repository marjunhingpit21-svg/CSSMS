<?php
require_once '../includes/db.php';  // This gives us $conn

// Total customers
$total_result = $conn->query("SELECT COUNT(*) FROM customers");
$totalCustomers = $total_result->fetch_row()[0];

// Active customers (user account is active)
$active_result = $conn->query("
    SELECT COUNT(*) FROM customers c
    JOIN users u ON c.user_id = u.user_id
    WHERE u.is_active = 1
");
$activeCustomers = $active_result->num_rows > 0 ? $active_result->fetch_row()[0] : 0;

// Fetch all customers with user info
$sql = "
    SELECT c.customer_id, c.phone, c.created_at,
            u.username, u.email, u.is_active
    FROM customers c
    LEFT JOIN users u ON c.user_id = u.user_id
    ORDER BY c.created_at DESC
";
$result = $conn->query($sql);
$customers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/customers.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <h1>Customer Management</h1>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Customers</p>
                <p class="stat-value"><?= number_format($totalCustomers) ?></p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">Active Customers</p>
                <p class="stat-value"><?= number_format($activeCustomers) ?></p>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="search-wrapper">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" placeholder="Search by name, email or phone..." class="search-input" id="searchInput" onkeyup="filterTable()">
                </div>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="table-container">
            <table id="customersTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th> 
                            <div class="actions-cell" id="header-actions">
                                <button class="action-btn view" title="View" id="btn-view" disabled>
                                    <span class="material-icons">visibility</span>
                                </button>
                                <button class="action-btn edit" title="Edit" id="btn-edit" disabled>
                                    <span class="material-icons">edit</span>
                                </button>
                                <button class="action-btn delete" title="Delete" id="btn-delete" disabled>
                                    <span class="material-icons">delete</span>
                                </button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $c): ?>
                            <tr data-customer-id="<?= $c['customer_id'] ?>">
                                <td><?= htmlspecialchars($c['username'] ?? 'Guest') ?></td>
                                <td>
                                    <span class="<?= $c['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= $c['created_at'] ? date('M d, Y', strtotime($c['created_at'])) : '—' ?></td>
                                <td>
                                    <input type="checkbox" class="select-customer" data-customer-id="<?= $c['customer_id'] ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align:center; padding:50px; color:#9ca3af;">
                                No customers found yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination-section">
                Showing all <?= count($customers) ?> customer<?= count($customers) != 1 ? 's' : '' ?>
            </div>
        </div>
    </main>

    <script>
        function filterTable() {
            const input = document.getElementById("searchInput").value.toLowerCase();
            const rows = document.querySelectorAll("#customersTable tbody tr");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? "" : "none";
            });
            
            // Update buttons after filtering
            updateActionButtons();
        }

        function updateActionButtons() {
            const checkboxes = document.querySelectorAll('.select-customer');
            const viewBtn = document.getElementById('btn-view');
            const editBtn = document.getElementById('btn-edit');
            const deleteBtn = document.getElementById('btn-delete');

            // Count only visible and checked checkboxes
            let checkedCount = 0;
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (cb.checked && row.style.display !== 'none') {
                    checkedCount++;
                }
            });

            // Default: all disabled
            viewBtn.disabled = true;
            editBtn.disabled = true;
            deleteBtn.disabled = true;

            // Remove disabled class for visual feedback
            viewBtn.classList.remove('disabled');
            editBtn.classList.remove('disabled');
            deleteBtn.classList.remove('disabled');

            if (checkedCount === 1) {
                // Exactly one checkbox checked: enable all buttons
                viewBtn.disabled = false;
                editBtn.disabled = false;
                deleteBtn.disabled = false;
            } else if (checkedCount > 1) {
                // Multiple checkboxes checked: only enable delete
                deleteBtn.disabled = false;
                viewBtn.classList.add('disabled');
                editBtn.classList.add('disabled');
            } else {
                // No checkboxes checked: all disabled
                viewBtn.classList.add('disabled');
                editBtn.classList.add('disabled');
                deleteBtn.classList.add('disabled');
            }

            // Update row selection styling
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (cb.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.select-customer');
            const viewBtn = document.getElementById('btn-view');
            const editBtn = document.getElementById('btn-edit');
            const deleteBtn = document.getElementById('btn-delete');

            // Initialize: all buttons disabled and unchecked
            viewBtn.disabled = true;
            editBtn.disabled = true;
            deleteBtn.disabled = true;
            viewBtn.classList.add('disabled');
            editBtn.classList.add('disabled');
            deleteBtn.classList.add('disabled');

            checkboxes.forEach(cb => {
                cb.checked = false;
                cb.addEventListener('change', updateActionButtons);
            });

            // Button click handlers (you can expand these)
            viewBtn.addEventListener('click', function() {
                const selected = document.querySelector('.select-customer:checked');
                if (selected) {
                    const customerId = selected.dataset.customerId;
                    console.log('View customer:', customerId);
                    // Add your view logic here
                }
            });

            editBtn.addEventListener('click', function() {
                const selected = document.querySelector('.select-customer:checked');
                if (selected) {
                    const customerId = selected.dataset.customerId;
                    console.log('Edit customer:', customerId);
                    // Add your edit logic here
                }
            });

            deleteBtn.addEventListener('click', function() {
                const selected = document.querySelectorAll('.select-customer:checked');
                if (selected.length > 0) {
                    const customerIds = Array.from(selected).map(cb => cb.dataset.customerId);
                    console.log('Delete customers:', customerIds);
                    // Add your delete logic here
                    if (confirm(`Are you sure you want to delete ${customerIds.length} customer(s)?`)) {
                        // Perform delete action
                    }
                }
            });

            // Initial state
            updateActionButtons();
        });
    </script>
</body>
</html>