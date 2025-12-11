<?php 
include '../includes/auth.php';
include '../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: index.php"); exit; }

// Fetch supplier safely
$result = $conn->query("SELECT * FROM suppliers WHERE supplier_id = $id");
if (!$result || $result->num_rows === 0) {
    header("Location: index.php");
    exit;
}
$supplier = $result->fetch_assoc();

if ($_POST) {
    $name    = trim($_POST['name']);
    $contact = trim($_POST['contact'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status  = in_array($_POST['status'], ['active','inactive']) ? $_POST['status'] : 'active';

    // SAFE prepared statement
    $sql = "UPDATE suppliers SET 
            supplier_name = ?, 
            contact_person = ?, 
            phone = ?, 
            email = ?, 
            address = ?, 
            status = ? 
            WHERE supplier_id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // If prepare fails, show error (only in dev)
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssssi", $name, $contact, $phone, $email, $address, $status, $id);
    
    if ($stmt->execute()) {
        header("Location: index.php?updated=1");
        exit;
    } else {
        echo "Error updating: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supplier • Altiere Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="suppliers.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <div>
                <h1 class="page-title">Edit Supplier</h1>
                <p class="header-subtitle">Update supplier information</p>
            </div>
        </div>

        <div style="max-width:560px; margin:0 auto;">
            <div class="card">
                <div class="card-header" style="background:linear-gradient(135deg,#e91e63,#c2185b);">
                    <h3 style="margin:0; color:white;"><?= htmlspecialchars($supplier['supplier_name']) ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div style="margin-bottom:20px;">
                            <label class="info-label">Supplier Name <span style="color:#e91e63;">*</span></label>
                            <input type="text" name="name" value="<?= htmlspecialchars($supplier['supplier_name']) ?>" required 
                                   style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; font-size:1rem; margin-top:8px;">
                        </div>

                        <div style="margin-bottom:20px;">
                            <label class="info-label">Contact Person</label>
                            <input type="text" name="contact" value="<?= htmlspecialchars($supplier['contact_person'] ?? '') ?>" 
                                   style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; font-size:1rem; margin-top:8px;">
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                            <div>
                                <label class="info-label">Phone</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>" 
                                       style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; margin-top:8px;">
                            </div>
                            <div>
                                <label class="info-label">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>" 
                                       style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; margin-top:8px;">
                            </div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label class="info-label">Address</label>
                            <textarea name="address" rows="4" 
                                      style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; font-size:1rem; margin-top:8px;"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                        </div>

                        <div style="margin-bottom:24px;">
                            <label class="info-label">Status</label>
                            <select name="status" style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; margin-top:8px; font-size:1rem;">
                                <option value="active"   <?= ($supplier['status'] ?? '')=='active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($supplier['status'] ?? '')=='inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="card-actions" style="background:#f8f9fa; margin:0 -24px -24px; border-radius:0 0 16px 16px;">
                            <button type="submit" 
                                    style="flex:1; padding:14px; background:#e91e63; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer;">
                                Update Supplier
                            </button>
                            <a href="index.php" 
                               style="flex:1; text-align:center; padding:14px; color:#666; text-decoration:none; font-weight:500;">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading spinner on submit -->
    <script>
    document.querySelector('form').addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-icons" style="animation:spin 1s linear infinite; vertical-align:middle; font-size:20px;">sync</span> Saving...';
    });
    </script>
    <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>
</body>
</html>