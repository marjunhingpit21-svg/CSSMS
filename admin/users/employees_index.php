<?php
require_once '../includes/db.php';

// Total employees
$total_result = $conn->query("SELECT COUNT(*) FROM employees");
$totalEmployees = $total_result->fetch_row()[0];

// Active employees count (you can add an `is_active` column or assume all are active)
$activeEmployees = $totalEmployees; // Change later if you add is_active

// Fetch all employees
$sql = "
    SELECT 
        e.employee_id,
        e.position,
        e.hire_date,
        e.salary,
        e.phone,
        e.email,
        CONCAT(e.first_name, ' ', e.last_name) AS full_name,
        b.branch_name
    FROM employees e
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    ORDER BY e.hire_date DESC
";
$result = $conn->query($sql);
$employees = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/customers.css">
    <style>
        .add-btn {
            font-family: 'Inter', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #e91e63;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .add-btn:hover {
            background: #c2185b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.3);
        }

        /* Perfect even 4-column filters on desktop */
.filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;  /* Search gets more space */
    gap: 16px;
    align-items: center;
}

@media (max-width: 1200px) {
    .filters-grid {
        grid-template-columns: 1.5fr 1fr 1fr 1fr;
    }
}

@media (max-width: 992px) {
    .filters-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
}

/* Make all inputs/selects same height and style */
.search-input,
.filter-select {
    height: 48px;
    padding: 0 16px;
    font-size: 15px;
    border: 1.5px solid #e91e63;
    border-radius: 12px;
    background: #fff;
    transition: all 0.3s ease;
}

.search-input {
    padding-left: 48px;
}

.search-input:focus,
.filter-select:focus {
    outline: none;
    border-color: #c2185b;
    box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.15);
}

.filter-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23e91e63' d='M1.175 0L6 4.825 10.825 0 12 1.175 6 8 0 1.175z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
}
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <h1>Employee Management</h1>
            <button class="add-btn" id="openAddModal">
                <span class="material-icons">add</span>
                Add New Employee
            </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card violet-pink">
                <p class="stat-label">Total Employees</p>
                <p class="stat-value"><?= number_format($totalEmployees) ?></p>
            </div>
            <div class="stat-card emerald-teal">
                <p class="stat-label">Working Today</p>
                <p class="stat-value"><?= number_format($totalEmployees) ?></p>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <!-- Search -->
                <div class="search-wrapper">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" placeholder="Search by name, email, phone, position, branch..." class="search-input" id="searchInput">
                </div>

                <!-- Branch Filter -->
                <select class="filter-select" id="filter-branch">
                    <option value="">All Branches</option>
                    <?php
                    $branches_q = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
                    while ($b = $branches_q->fetch_assoc()) {
                        echo '<option value="'.htmlspecialchars($b['branch_name']).'">'.htmlspecialchars($b['branch_name']).'</option>';
                    }
                    ?>
                </select>

                <!-- Position Filter -->
                <select class="filter-select" id="filter-position">
                    <option value="">All Positions</option>
                    <?php
                    $positions_q = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL ORDER BY position");
                    while ($p = $positions_q->fetch_assoc()) {
                        echo '<option value="'.htmlspecialchars($p['position']).'">'.htmlspecialchars($p['position']).'</option>';
                    }
                    ?>
                </select>

                <!-- Sort By -->
                <select class="filter-select" id="filter-sort">
                    <option value="recent">Recently Employed</option>
                    <option value="oldest">Oldest Employed</option>
                    <option value="name-asc">Name (A-Z)</option>
                    <option value="name-desc">Name (Z-A)</option>
                    <option value="salary-asc">Salary (Low → High)</option>
                    <option value="salary-desc">Salary (High → Low)</option>
                </select>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="table-container">
            <table id="employeesTable">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Position</th>
                        <th>Branch</th>
                        <th>Salary</th>
                        <th>
                            <div class="actions-cell" id="header-actions">
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
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr 
                                data-employee-id="<?= $emp['employee_id'] ?>" 
                                data-hire-date="<?= $emp['hire_date'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($emp['full_name'] ?? '—') ?></strong>
                                </td>
                                <td><?= htmlspecialchars($emp['position'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($emp['branch_name'] ?? 'Head Office') ?></td>
                                <td>₱<?= number_format($emp['salary'] ?? 0, 2) ?></td>
                                <td>
                                    <input type="checkbox" class="select-employee" data-employee-id="<?= $emp['employee_id'] ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:60px; color:#9ca3af;">
                                No employees found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination-section">
                Showing all <?= count($employees) ?> employee<?= count($employees) != 1 ? 's' : '' ?>
            </div>
        </div>
    </main>

    <!-- Edit Employee Modal -->
    <div id="editEmployeeModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Employee</h2>
                <button class="modal-close">×</button>
            </div>
            <form id="editForm">
                <input type="hidden" name="employee_id" id="edit_id">

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- First Name -->
                    <div class="form-group">
                        <label>First Name <span style="color:red">*</span></label>
                        <input type="text" name="first_name" id="edit_first_name" required placeholder="Juan">
                    </div>

                    <!-- Last Name -->
                    <div class="form-group">
                        <label>Last Name <span style="color:red;">*</span></label>
                        <input type="text" name="last_name" id="edit_last_name" required placeholder="Dela Cruz">
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" placeholder="juan@example.com">
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="edit_phone" placeholder="0917-123-4567">
                    </div>

                    <!-- Position Dropdown -->
                    <div class="form-group">
                        <label>Position <span style="color:red;">*</span></label>
                        <select name="position" id="edit_position" class="filter-select" required style="height:48px; width:100%;">
                            <option value="">Select Position</option>
                            <option value="cashier">Cashier</option>
                            <option value="sales_associate">Sales Associate</option>
                            <option value="stock_clerk">Stock Clerk</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>

                    <!-- Branch -->
                    <div class="form-group">
                        <label>Branch <span style="color:red;">*</span></label>
                        <select name="branch_id" id="edit_branch" class="filter-select" required style="height:48px; width:100%;">
                            <option value="">Select Branch</option>
                            <?php
                            $branches_q = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
                            while ($b = $branches_q->fetch_assoc()) {
                                echo '<option value="'.$b['branch_id'].'">'.htmlspecialchars($b['branch_name']).'</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Salary -->
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Monthly Salary (₱) <span style="color:red;">*</span></label>
                        <input type="number" step="0.01" min="0" name="salary" id="edit_salary" required placeholder="25000.00">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay delete-modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Employee(s)</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div style="padding: 28px; text-align:center;">
                <div class="delete-icon">
                    <span class="material-icons" style="font-size:48px;color:#dc2626;">delete_forever</span>
                </div>
                <h3 style="margin:20px 0 10px;">Are you sure?</h3>
                <p id="deleteText">You are about to delete <strong>1</strong> employee.</p>
                <div id="deleteNames" style="margin:20px 0; font-weight:500;"></div>
                <div class="modal-actions delete-actions">
                    <button class="btn-cancel">Cancel</button>
                    <button class="btn-delete-confirm" id="confirmDelete">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal-overlay" id="addEmployeeModal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #e91e63, #c2185b);">
                <h2>Add New Employee</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addEmployeeForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name <span style="color:#e91e63;">*</span></label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span style="color:#e91e63;">*</span></label>
                            <input type="text" name="last_name" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span style="color:#e91e63;">*</span></label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" placeholder="e.g. 09171234567">
                        </div>

                        <div class="form-group">
                            <label>Position <span style="color:#e91e63;">*</span></label>
                            <select name="position" required>
                                <option value="">Select Position</option>
                                <option value="cashier">Cashier</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="manager">Manager</option>
                                <option value="stock_clerk">Stock Clerk</option>
                                <option value="sales_associate">Sales Associate</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Branch <span style="color:#e91e63;">*</span></label>
                            <select name="branch_id" required>
                                <option value="">Select Branch</option>
                                <?php
                                $branches_q = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
                                while ($b = $branches_q->fetch_assoc()) {
                                    echo '<option value="'.$b['branch_id'].'">'.htmlspecialchars($b['branch_name']).'</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Monthly Salary (₱) <span style="color:#e91e63;">*</span></label>
                            <input type="number" name="salary" min="0" step="0.01" required placeholder="25000.00">
                        </div>

                        <div class="form-group">
                            <label>Hire Date <span style="color:#e91e63;">*</span></label>
                            <input type="date" name="hire_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-save">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('employeesTable');
            const rows = table.querySelectorAll('tbody tr');
            const checkboxes = table.querySelectorAll('.select-employee');
            const editBtn = document.getElementById('btn-edit');
            const deleteBtn = document.getElementById('btn-delete');

            function updateButtons() {
                const checked = table.querySelectorAll('.select-employee:checked').length;
                editBtn.disabled = checked !== 1;
                deleteBtn.disabled = checked === 0;
            }

            checkboxes.forEach(cb => cb.addEventListener('change', updateButtons));
            updateButtons();

            // Edit
            editBtn.onclick = () => {
                const checked = table.querySelector('.select-employee:checked');
                if (checked) {
                    const id = checked.dataset.employeeId;
                    fetch(`get_employee.php?id=${id}`)
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                const e = d.employee;

                                document.getElementById('edit_id').value = e.employee_id;
                                document.getElementById('edit_first_name').value = e.first_name || '';
                                document.getElementById('edit_last_name').value = e.last_name || '';
                                document.getElementById('edit_email').value = e.email || '';
                                document.getElementById('edit_phone').value = e.phone || '';
                                document.getElementById('edit_position').value = e.position || '';
                                document.getElementById('edit_branch').value = e.branch_id || '';
                                document.getElementById('edit_salary').value = e.salary || '';

                                const modal = document.getElementById('editEmployeeModal');
                                modal.style.display = 'flex';
                                setTimeout(() => modal.classList.add('show'), 10);
                            } else {
                                alert('Failed to load employee data.');
                            }
                        })
                        .catch(() => alert('Error loading employee data.'));
                }
            };
            
            // Delete
            deleteBtn.onclick = () => {
                const checked = Array.from(table.querySelectorAll('.select-employee:checked'));
                const ids = checked.map(c => c.dataset.employeeId);
                const names = checked.map(c => c.closest('tr').cells[0].textContent.trim());

                document.getElementById('deleteText').innerHTML = `You are about to delete <strong>${ids.length}</strong> employee${ids.length > 1 ? 's' : ''}.`;
                document.getElementById('deleteNames').innerHTML = names.map(n => `<div style="margin:8px 0;">• ${n}</div>`).join('');

                document.getElementById('deleteModal').style.display = 'flex';
                setTimeout(() => document.getElementById('deleteModal').classList.add('show'), 10);

                document.getElementById('confirmDelete').onclick = () => {
                    fetch('delete_employee.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: ids })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) location.reload();
                        else alert(res.message || 'Error deleting');
                    });
                };
            };

            // Search & Filter
            const search = document.getElementById('searchInput');
            search.addEventListener('input', filter);
            document.getElementById('filter-branch').addEventListener('change', filter);
            document.getElementById('filter-position').addEventListener('change', filter);
            document.getElementById('filter-sort').addEventListener('change', () => {
                const sortBy = document.getElementById('filter-sort').value;
                const tbody = table.querySelector('tbody');
                const rowsArray = Array.from(rows).filter(r => r.style.display !== 'none');

                rowsArray.sort((a, b) => {
                    switch (sortBy) {
                        case 'recent':
                        case 'oldest':
                            const getDate = (d) => d ? new Date(d) : new Date('1900-01-01');
                            const dateA = getDate(a.dataset.hireDate);
                            const dateB = getDate(b.dataset.hireDate);
                            const comparison = dateA - dateB;
                            return sortBy === 'recent' ? -comparison : comparison;
                        case 'name-asc':
                            return a.cells[0].textContent.localeCompare(b.cells[0].textContent);
                        case 'name-desc':
                            return b.cells[0].textContent.localeCompare(a.cells[0].textContent);
                        case 'salary-asc':
                            const salaryA = parseFloat(a.cells[3].textContent.replace(/[^0-9.-]+/g,""));
                            const salaryB = parseFloat(b.cells[3].textContent.replace(/[^0-9.-]+/g,""));
                            return salaryA - salaryB;
                        case 'salary-desc':
                            const salaryA2 = parseFloat(a.cells[3].textContent.replace(/[^0-9.-]+/g,""));
                            const salaryB2 = parseFloat(b.cells[3].textContent.replace(/[^0-9.-]+/g,""));
                            return salaryB2 - salaryA2;
                        default:
                            return 0;
                    }
                });

                // Re-append sorted rows
                rowsArray.forEach(r => tbody.appendChild(r));
            });

            function filter() {
                const term = search.value.toLowerCase();
                const branch = document.getElementById('filter-branch').value.toLowerCase();
                const pos = document.getElementById('filter-position').value.toLowerCase();
                const sortBy = document.getElementById('filter-sort').value;

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    
                    // Branch = cell 2, Position = cell 1 (as per your table structure)
                    const rowBranch = (row.cells[2]?.textContent || '').toLowerCase().trim();
                    const rowPos = (row.cells[1]?.textContent || '').toLowerCase().trim();
                    
                    const matchSearch = text.includes(term);
                    const matchBranch = !branch || rowBranch.includes(branch);
                    const matchPos = !pos || rowPos.includes(pos);

                    row.style.display = matchSearch && matchBranch && matchPos ? '' : 'none';
                });
                updateButtons();
            }
            // Close modals
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.onclick = () => {
                    btn.closest('.modal-overlay').classList.remove('show');
                    setTimeout(() => btn.closest('.modal-overlay').style.display = 'none', 300);
                };
            });
        });

        document.querySelectorAll('.modal-close, .btn-cancel').forEach(btn => {
            btn.onclick = (e) => {
                if (e.target.closest('.modal-overlay')) {
                    const overlay = e.target.closest('.modal-overlay');
                    overlay.classList.remove('show');
                    setTimeout(() => overlay.style.display = 'none', 300);
                }
            };
        })

            document.getElementById('openAddModal').addEventListener('click', function() {
            const modal = document.getElementById('addEmployeeModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        });

        // Close all modals when clicking X or Cancel
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const overlay = this.closest('.modal-overlay');
                overlay.classList.remove('show');
                setTimeout(() => overlay.style.display = 'none', 300);
            });
        });

        // Submit Add Employee Form via AJAX
        document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);

            const submitBtn = this.querySelector('.btn-save');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            fetch('save_employee.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert(
                        `Employee added successfully!\n\n` +
                        `Employee No: ${res.employee_number}\n` +
                        `Default Password: ${res.default_password}\n\n` +
                        `They can log in with their email and this password, then change it.`
                    );
                    location.reload();
                } else {
                    alert('Error: ' + (res.message || 'Could not add employee'));
                }
            })
            .catch(() => alert('Connection error. Please try again.'))
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Add Employee';
            });
        });
        
    </script>
    <script>
    // Handle form submission via AJAX
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData);

        fetch('update_employee.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Employee updated successfully!');
                location.reload(); // Refresh to show changes
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(() => alert('Connection error. Please try again.'));
    });
    </script>
</body>
</html>