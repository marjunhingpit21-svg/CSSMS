<?php
session_start();

if (!isset($_SESSION['employee_id'])) { 
    header('Location: index.php'); 
    exit(); 
}

require_once '../database/db.php';

$employee_id = $_SESSION['employee_id'] ?? 1;

// Fetch employee details
$query = "SELECT first_name, last_name, employee_number, position FROM employees WHERE employee_id = $employee_id";
$result = mysqli_query($conn, $query);
$employee = mysqli_fetch_assoc($result);

$employee_name = $employee ? $employee['first_name'] . ' ' . $employee['last_name'] : 'Cashier';
$employee_number = $employee['employee_number'] ?? '';
$employee_position = $employee['position'] ?? 'cashier';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Altiere POS</title>
    <link rel="stylesheet" href="css/pos_system.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- Header -->
<?php include 'includes/header.php'; ?>

<!-- Main Container -->
<div class="pos-container">
    <!-- Left Panel: Scanner + Search -->
    <?php include 'includes/scanner_panel.php'; ?>

    <!-- Right Panel: Receipt -->
    <?php include 'includes/receipt_panel.php'; ?>
</div>

<!-- Modals -->
<?php include 'includes/modals.php'; ?>

<!-- JavaScript Files -->
<script>
    // Global variables
    const EMPLOYEE_ID = <?php echo $employee_id; ?>;
    const EMPLOYEE_NAME = '<?php echo htmlspecialchars($employee_name); ?>';
</script>
<script src="js/globals.js"></script>
<script src="js/cart.js"></script>
<script src="js/search.js"></script>
<script src="js/payment.js"></script>
<script src="js/split_receipt.js"></script>
<script src="js/functions.js"></script>
<script src="js/transactions.js"></script>
<script src="js/keyboard.js"></script>
<script src="js/init.js"></script>

</body>
</html>