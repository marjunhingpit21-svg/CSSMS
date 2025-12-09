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
                    <input type="text" placeholder="Search by name, email or phone..." class="search-input" id="searchInput">
                </div>
                <select class="filter-select" id="sort-status">
                    <option value="All">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <select class="filter-select" id="sort-filter">
                    <option value="Sort by: Newest">Sort by: Newest First</option>
                    <option value="Sort by: Name A-Z">Sort by: Name A-Z</option>
                </select>
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

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Customer</h2>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>

            <form id="editCustomerForm" method="POST" action="update_customer.php">
                <input type="hidden" name="customer_id" id="edit_customer_id">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_username">Username</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="text" id="edit_phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="edit_status">Account Status</label>
                        <select id="edit_status" name="is_active" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="edit_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password" placeholder="••••••••">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="modalCancel">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Customer Modal -->
    <div id="deleteCustomerModal" class="modal-overlay delete-modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Customer(s)</h2>
                <button class="modal-close" id="deleteModalClose">&times;</button>
            </div>

            <div style="padding: 28px;">
                <div class="delete-icon">
                    <span class="material-icons">delete_forever</span>
                </div>

                <div class="delete-message">
                    <h3>Are you sure you want to delete?</h3>
                    <p id="deleteMessage">This action cannot be undone.</p>
                </div>

                <div id="deleteCustomerList" class="customer-list" style="display:none;"></div>
                <div id="deleteWarningBox" class="warning-box" style="display:none;"></div>

                <div class="modal-actions delete-actions">
                    <button type="button" class="btn-cancel" id="deleteModalCancel">Cancel</button>
                    <button type="button" class="btn-delete-confirm" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Filter & Action Scripts -->
    <script>
        // Enhanced Filter Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('sort-status');
            const sortFilter = document.getElementById('sort-filter');
            const table = document.getElementById('customersTable');
            const tbody = table.querySelector('tbody');

            // Combined filter function
            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedStatus = statusFilter.value;
                const rows = Array.from(tbody.querySelectorAll('tr'));

                rows.forEach(row => {
                    // Skip empty state row
                    if (row.cells.length < 4) {
                        row.style.display = 'none';
                        return;
                    }

                    const username = row.cells[0].textContent.toLowerCase();
                    const status = row.cells[1].textContent.trim();
                    const registered = row.cells[2].textContent.toLowerCase();

                    // Check search match
                    const matchesSearch = username.includes(searchTerm) || 
                                        status.toLowerCase().includes(searchTerm) ||
                                        registered.includes(searchTerm);

                    // Check status filter
                    const matchesStatus = selectedStatus === 'All' || status === selectedStatus;

                    // Show/hide row
                    row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
                });

                updateActionButtons();
                updatePaginationText();
            }

            // Sort functionality
            function sortTable() {
                const sortValue = sortFilter.value;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const dataRows = rows.filter(row => row.cells.length >= 4);

                dataRows.sort((a, b) => {
                    switch(sortValue) {
                        case 'Sort by: Newest':
                            const dateA = new Date(a.cells[2].textContent);
                            const dateB = new Date(b.cells[2].textContent);
                            return dateB - dateA;

                        case 'Sort by: Name A-Z':
                            const nameA = a.cells[0].textContent.toLowerCase();
                            const nameB = b.cells[0].textContent.toLowerCase();
                            return nameA.localeCompare(nameB);

                        default:
                            return 0;
                    }
                });

                dataRows.forEach(row => tbody.appendChild(row));
                updateActionButtons();
            }

            // Update pagination text
            function updatePaginationText() {
                const allRows = tbody.querySelectorAll('tr');
                const visibleRows = Array.from(allRows).filter(row => 
                    row.style.display !== 'none' && row.cells.length >= 4
                );
                
                const paginationSection = document.querySelector('.pagination-section');
                if (paginationSection) {
                    const count = visibleRows.length;
                    paginationSection.textContent = `Showing ${count} customer${count !== 1 ? 's' : ''}`;
                }
            }

            // Event listeners
            searchInput.addEventListener('keyup', filterTable);
            statusFilter.addEventListener('change', function() {
                filterTable();
                updateFilterStyles();
            });
            sortFilter.addEventListener('change', sortTable);

            // Visual feedback for active filters
            function updateFilterStyles() {
                if (statusFilter.value !== 'All') {
                    statusFilter.style.borderColor = '#e91e63';
                    statusFilter.style.background = '#fce7f3';
                    statusFilter.style.fontWeight = '600';
                } else {
                    statusFilter.style.borderColor = '#e91e63';
                    statusFilter.style.background = '#f8f9fa';
                    statusFilter.style.fontWeight = '400';
                }
            }

            updatePaginationText();
        });

        // Update action buttons based on selection
        function updateActionButtons() {
            const checked = document.querySelectorAll('.select-customer:checked');
            const visibleChecked = Array.from(checked).filter(cb => 
                cb.closest('tr').style.display !== 'none'
            );

            const viewBtn  = document.getElementById('btn-view');
            const editBtn  = document.getElementById('btn-edit');
            const deleteBtn = document.getElementById('btn-delete');

            viewBtn.disabled = editBtn.disabled = deleteBtn.disabled = true;
            viewBtn.classList.add('disabled');
            editBtn.classList.add('disabled');
            deleteBtn.classList.add('disabled');

            if (visibleChecked.length === 1) {
                viewBtn.disabled = editBtn.disabled = deleteBtn.disabled = false;
                viewBtn.classList.remove('disabled');
                editBtn.classList.remove('disabled');
                deleteBtn.classList.remove('disabled');
            } else if (visibleChecked.length > 1) {
                deleteBtn.disabled = false;
                deleteBtn.classList.remove('disabled');
            }
        }

        // Initialize action handlers
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.select-customer');
            checkboxes.forEach(cb => cb.addEventListener('change', updateActionButtons));

            // View button
            document.getElementById('btn-view').addEventListener('click', function () {
                const checked = document.querySelector('.select-customer:checked');
                if (checked) {
                    const customerId = checked.dataset.customerId;
                    window.location.href = `view_customer.php?id=${customerId}`;
                }
            });

            // Edit button
            document.getElementById('btn-edit').addEventListener('click', function () {
                const checked = document.querySelector('.select-customer:checked');
                if (checked) {
                    const customerId = checked.dataset.customerId;
                    openEditModal(customerId);
                }
            });

            // Delete button
            document.getElementById('btn-delete').addEventListener('click', function () {
                const checked = document.querySelectorAll('.select-customer:checked');
                if (checked.length > 0) {
                    const customerIds = Array.from(checked).map(cb => cb.dataset.customerId);
                    const customerNames = Array.from(checked).map(cb => {
                        const row = cb.closest('tr');
                        return row.cells[0].textContent.trim();
                    });
                    openDeleteModal(customerIds, customerNames);
                }
            });

            updateActionButtons();
        });
    </script>

    <!-- Edit Modal Script -->
    <script>
        const editModal = document.getElementById('editCustomerModal');
        const modalClose = document.getElementById('modalClose');
        const modalCancel = document.getElementById('modalCancel');

        function openEditModal(customerId) {
            fetch(`get_customers.php?id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_customer_id').value = data.customer.customer_id;
                        document.getElementById('edit_username').value = data.customer.username || '';
                        document.getElementById('edit_email').value = data.customer.email || '';
                        document.getElementById('edit_phone').value = data.customer.phone || '';
                        document.getElementById('edit_status').value = data.customer.is_active ? 1 : 0;
                        document.getElementById('edit_password').value = '';

                        editModal.style.display = 'flex';
                        setTimeout(() => editModal.classList.add('show'), 10);
                    }
                })
                .catch(err => console.error('Error fetching customer:', err));
        }

        function closeEditModal() {
            editModal.classList.remove('show');
            setTimeout(() => editModal.style.display = 'none', 300);
        }

        modalClose.onclick = closeEditModal;
        modalCancel.onclick = closeEditModal;
        editModal.onclick = (e) => {
            if (e.target === editModal) closeEditModal();
        };
    </script>

    <!-- Delete Modal Script -->
    <script>
        const deleteModal = document.getElementById('deleteCustomerModal');
        const deleteModalClose = document.getElementById('deleteModalClose');
        const deleteModalCancel = document.getElementById('deleteModalCancel');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let currentDeleteIds = [];

        function openDeleteModal(customerIds, customerNames) {
            currentDeleteIds = customerIds;
            const deleteMessage = document.getElementById('deleteMessage');
            const deleteCustomerList = document.getElementById('deleteCustomerList');
            
            if (customerIds.length === 1) {
                deleteMessage.textContent = `You are about to permanently delete 1 customer. This action cannot be undone.`;
            } else {
                deleteMessage.textContent = `You are about to permanently delete ${customerIds.length} customers. This action cannot be undone.`;
            }

            deleteCustomerList.innerHTML = customerNames.map(name => 
                `<div class="customer-item">${name}</div>`
            ).join('');
            deleteCustomerList.style.display = 'block';

            document.getElementById('deleteWarningBox').style.display = 'none';
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.style.display = 'block';

            deleteModal.style.display = 'flex';
            setTimeout(() => deleteModal.classList.add('show'), 10);
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            setTimeout(() => {
                deleteModal.style.display = 'none';
                currentDeleteIds = [];
            }, 300);
        }

        deleteModalClose.onclick = closeDeleteModal;
        deleteModalCancel.onclick = closeDeleteModal;
        deleteModal.onclick = (e) => {
            if (e.target === deleteModal) closeDeleteModal();
        };

        confirmDeleteBtn.addEventListener('click', function() {
            if (currentDeleteIds.length === 0) return;

            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';

            fetch('delete_customers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ customer_ids: currentDeleteIds })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    if (data.cannot_delete && data.cannot_delete.length > 0) {
                        showPendingOrdersWarning(data.cannot_delete);
                    } else {
                        alert(data.message || 'Error deleting customers');
                        closeDeleteModal();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting customers');
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete';
            });
        });

        function showPendingOrdersWarning(cannotDelete) {
            const deleteMessage = document.getElementById('deleteMessage');
            const warningBox = document.getElementById('deleteWarningBox');
            
            deleteMessage.textContent = 'The following customers cannot be deleted:';
            
            const warningContent = `
                <span class="material-icons">warning</span>
                <div class="warning-content">
                    <h4>Customers with Pending Orders</h4>
                    <p>These customers have pending, processing, or shipped orders and cannot be deleted until those orders are completed or cancelled.</p>
                    ${cannotDelete.map(customer => `
                        <div class="pending-order-item">
                            ${customer.name} - <strong>${customer.pending_orders}</strong> pending order(s)
                        </div>
                    `).join('')}
                </div>
            `;
            
            warningBox.innerHTML = warningContent;
            warningBox.style.display = 'flex';
            confirmDeleteBtn.style.display = 'none';
            
            deleteModalCancel.textContent = 'OK';
            deleteModalCancel.classList.remove('btn-cancel');
            deleteModalCancel.classList.add('btn-save');
        }
    </script>
</body>
</html>