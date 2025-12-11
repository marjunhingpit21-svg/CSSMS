<?php 
include '../includes/auth.php';
include '../includes/db.php';

if ($_POST) {
    $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("sssss", $_POST['name'], $_POST['contact'], $_POST['phone'], $_POST['email'], $_POST['address']);
    $stmt->execute();
    header("Location: index.php?added=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Supplier • Altiere Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="suppliers.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main>
        <div class="header-section">
            <div>
                <h1 class="page-title">Add New Supplier</h1>
                <p class="header-subtitle">Start building your supplier network</p>
            </div>
        </div>

        <div style="max-width:560px; margin:0 auto;">
            <div class="card">
                <div class="card-header" style="background:linear-gradient(135deg,#e91e63,#c2185b);">
                    <h3 style="margin:0; color:white;">Supplier Details</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div style="margin-bottom:20px;">
                            <label class="info-label">Supplier Name <span style="color:#e91e63;">*</span></label>
                            <input type="text" name="name" required 
                                   style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; font-size:1rem; margin-top:8px;">
                        </div>

                        <div style="margin-bottom:20px;">
                            <label class="info-label">Contact Person</label>
                            <input type="text" name="contact" 
                                   style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; font-size:1rem; margin-top:8px;">
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                            <div>
                                <label class="info-label">Phone</label>
                                <input type="text" name="phone" 
                                       style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; margin-top:8px;">
                            </div>
                            <div>
                                <label class="info-label">Email</label>
                                <input type="email" name="email" 
                                       style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; margin-top:8px;">
                            </div>
                        </div>

                        <div style="margin-bottom:24px;">
                            <label class="info-label">Address</label>
                            <textarea name="address" rows="4" 
                                      style="width:100%; padding:14px; border:1px solid #ddd; border-radius:12px; font-size:1rem; margin-top:8px;"></textarea>
                        </div>

                        <div class="card-actions" style="background:#f8f9fa; margin:0 -24px -24px; border-radius:0 0 16px 16px;">
                            <button type="submit" 
                                    style="flex:1; padding:14px; background:#e91e63; color:white; border:none; border-radius:10px; font-weight:600; cursor:pointer;">
                                Create Supplier
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
    <script>
document.querySelector('form').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons" style="animation:spin 1s linear infinite;font-size:20px;">sync</span> Saving...';
});
</script>

<style>
@keyframes spin { 100% { transform:rotate(360deg); } }
</style>
</body>
</html>