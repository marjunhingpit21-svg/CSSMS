<?php
include '../includes/auth.php';
include '../includes/db.php';

$supplier_id = $_GET['id'] ?? 0;
$supplier_id = (int)$supplier_id;

if ($supplier_id <= 0) {
    header("Location: suppliers_index.php");
    exit;
}

// Fetch supplier data
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Supplier not found.");
}

$supplier = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supplier - <?= htmlspecialchars($supplier['supplier_name']) ?></title>
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
                <h1 class="page-title">Edit Supplier</h1>
            </div>
        </div>

        <form id="editForm" action="update_supplier.php" method="POST">
            <input type="hidden" name="supplier_id" value="<?= $supplier['supplier_id'] ?>">

            <div class="form-grid">
                <div class="form-column">
                    <!-- Supplier Information -->
                    <div class="card">
                        <h2>Supplier Information</h2>
                        <div class="form-row">
                            <div class="form-group full">
                                <label>Supplier Name <span class="required">*</span></label>
                                <input type="text" name="supplier_name" value="<?= htmlspecialchars($supplier['supplier_name']) ?>" required maxlength="100">
                            </div>

                            <div class="form-group">
                                <label>Contact Person</label>
                                <input type="text" name="contact_person" value="<?= htmlspecialchars($supplier['contact_person'] ?? '') ?>" maxlength="100">
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>" maxlength="100">
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>" placeholder="+63 900 000 0000" maxlength="20">
                            </div>

                            <div class="form-group full">
                                <label>Address</label>
                                <textarea name="address" rows="3" placeholder="Street, Barangay, Municipality, Province"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details (if you added these columns) -->
                    <?php if (!empty($supplier['payment_terms_days']) || !empty($supplier['minimum_order_value']) || !empty($supplier['notes']) || !empty($supplier['tags'])): ?>
                    <div class="card">
                        <h2>Additional Details</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Payment Terms (Days)</label>
                                <input type="number" name="payment_terms_days" value="<?= $supplier['payment_terms_days'] ?>" min="0" max="365">
                            </div>

                            <div class="form-group">
                                <label>Minimum Order Value (₱)</label>
                                <div class="input-with-prefix">
                                    <span>₱</span>
                                    <input type="number" name="minimum_order_value" step="0.01" value="<?= $supplier['minimum_order_value'] ?? '' ?>">
                                </div>
                            </div>

                            <div class="form-group full12">
                                <label>Internal Notes</label>
                                <textarea name="notes" rows="4"><?= htmlspecialchars($supplier['notes'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group full">
                                <label>Tags</label>
                                <input type="text" name="tags" value="<?= htmlspecialchars($supplier['tags'] ?? '') ?>" placeholder="e.g. fast delivery, bulk only">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="actions">
                        <a href="suppliers_index.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save">Update Supplier</button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const name = this.supplier_name.value.trim();
            if (!name) {
                e.preventDefault();
                alert('Please enter a supplier name');
                return;
            }

            const submitBtn = this.querySelector('.btn-save');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-icons animate-spin">refresh</span> Updating...';
        });
    </script>
</body>
</html>