<?php
include 'Database/db.php';
session_start();

// Simple password protection (remove after use or add proper admin check)
$admin_password = "admin123"; // Change this!

$message = '';
$error = '';

// Handle bulk update
if (isset($_POST['bulk_update'])) {
    $password = $_POST['password'] ?? '';
    $stock_amount = (int)($_POST['stock_amount'] ?? 50);
    
    if ($password !== $admin_password) {
        $error = 'Invalid password!';
    } else {
        $sql = "UPDATE products SET stock_quantity = ? WHERE stock_quantity IS NULL OR stock_quantity = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $stock_amount);
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $message = "Successfully updated {$affected} products with stock quantity of {$stock_amount}!";
        } else {
            $error = "Error updating stock: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle individual update
if (isset($_POST['update_product'])) {
    $password = $_POST['password'] ?? '';
    $product_id = (int)$_POST['product_id'];
    $stock = (int)$_POST['stock'];
    
    if ($password !== $admin_password) {
        $error = 'Invalid password!';
    } else {
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
        $stmt->bind_param("ii", $stock, $product_id);
        
        if ($stmt->execute()) {
            $message = "Product stock updated successfully!";
        } else {
            $error = "Error updating product: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get all products
$products_query = "SELECT product_id, product_name, stock_quantity, price FROM products ORDER BY product_name";
$products_result = $conn->query($products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stock Quantities</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #222;
            margin-bottom: 10px;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #222;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: #e91e63;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }

        .btn-primary {
            background: #e91e63;
            color: white;
        }

        .btn-primary:hover {
            background: #c2185b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            color: #555;
            font-weight: 600;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .stock-input {
            width: 80px;
            padding: 6px;
        }

        .out-of-stock {
            color: #f44336;
            font-weight: 600;
        }

        .low-stock {
            color: #ff9800;
            font-weight: 600;
        }

        .in-stock {
            color: #4caf50;
            font-weight: 600;
        }

        .inline-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #e91e63;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Store</a>
        
        <h1>📦 Update Stock Quantities</h1>
        
        <div class="warning">
            <strong>⚠️ Warning:</strong> This page allows you to update product stock. Use the password: <code>admin123</code>
            <br><small>Delete this file after updating stock or add proper authentication!</small>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Bulk Update Form -->
        <div class="card">
            <h2>🚀 Quick Bulk Update</h2>
            <p style="color: #666; margin-bottom: 20px;">Set the same stock quantity for all products that are currently out of stock.</p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">Admin Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter admin password" required>
                </div>
                
                <div class="form-group">
                    <label for="stock_amount">Stock Quantity to Set</label>
                    <input type="number" id="stock_amount" name="stock_amount" value="50" min="0" max="9999" required>
                </div>
                
                <button type="submit" name="bulk_update" class="btn btn-primary">
                    Update All Out-of-Stock Products
                </button>
            </form>
        </div>

        <!-- Individual Products -->
        <div class="card">
            <h2>📋 Individual Product Stock</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Current Stock</th>
                        <th>New Stock</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($product = $products_result->fetch_assoc()): 
                        $stock = $product['stock_quantity'] ?? 0;
                        $stock_class = '';
                        if ($stock == 0) {
                            $stock_class = 'out-of-stock';
                        } elseif ($stock <= 10) {
                            $stock_class = 'low-stock';
                        } else {
                            $stock_class = 'in-stock';
                        }
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td class="<?php echo $stock_class; ?>"><?php echo $stock; ?></td>
                            <td>
                                <form method="POST" action="" class="inline-form">
                                    <input type="hidden" name="password" value="" class="password-input">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="number" name="stock" value="<?php echo $stock; ?>" min="0" max="9999" class="stock-input" required>
                            </td>
                            <td>
                                    <button type="submit" name="update_product" class="btn btn-primary btn-small">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn btn-secondary">Done - Go to Store</a>
        </div>
    </div>

    <script>
        // Auto-fill password for individual updates
        document.querySelectorAll('.inline-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const password = prompt('Enter admin password:');
                if (password === null) {
                    e.preventDefault();
                    return false;
                }
                this.querySelector('.password-input').value = password;
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>