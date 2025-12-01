<?php 
include '../includes/auth.php';
include '../db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Supplier</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/add_supplier.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <div>
                <a href="suppliers_index.php" class="back-button">
                    <span class="material-icons">arrow_back</span> Back to Suppliers
                </a>
                <h1 class="page-title">Add New Supplier</h1>
            </div>
        </div>

        <form id="addForm" action="save_supplier.php" method="POST">
            <div class="form-grid">
                <div class="form-column">
                    <!-- Supplier Information -->
                    <div class="card">
                        <h2>Supplier Information</h2>
                        <div class="form-row">
                            <div class="form-group full">
                                <label>Supplier Name <span class="required">*</span></label>
                                <input type="text" name="supplier_name" required maxlength="100">
                            </div>

                            <div class="form-group">
                                <label>Contact Person</label>
                                <input type="text" name="contact_person" maxlength="100">
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" maxlength="100">
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" placeholder="+63 900 000 0000" maxlength="20">
                            </div>

                            <div class="form-group full">
                                <label>Address</label>
                                <textarea name="address" rows="3" placeholder="Street, Barangay, Municipality, Province"></textarea>
                            </div>
                            
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="actions">
                        <a href="suppliers_index.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save">Save Supplier</button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
        document.getElementById('addForm').addEventListener('submit', function(e) {
            const name = this.supplier_name.value.trim();
            if (!name) {
                e.preventDefault();
                alert('Please enter a supplier name');
                return;
            }

            const submitBtn = this.querySelector('.btn-save');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-icons animate-spin">refresh</span> Saving...';
        });
    </script>
</body>
</html>