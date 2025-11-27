<?php
session_start();

// Check if cashier is logged in
/*if (!isset($_SESSION['cashier_id'])) {
   header('Location: cashier_login.php');
    exit();
}*/

require_once '../database/db.php';

$cashier_name = $_SESSION['cashier_name'] ?? 'Cashier';
$branch = $_SESSION['branch'] ?? 'Branch';

// Handle AJAX request for processing sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'process_sale') {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['items']) || empty($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit();
    }

    $cashier_id = $_SESSION['cashier_id'];
    $branch_name = $data['branch'] ?? $_SESSION['branch'];
    $items = $data['items'];
    $subtotal = $data['subtotal'];
    $tax = $data['tax'];
    $total = $data['total'];

    try {
        // Start transaction
        $conn->begin_transaction();

        // Insert into sales table
        $stmt = $conn->prepare("INSERT INTO sales (cashier_id, branch, subtotal, tax, total, sale_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isddd", $cashier_id, $branch_name, $subtotal, $tax, $total);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create sale record");
        }
        
        $sale_id = $conn->insert_id;
        $stmt->close();

        // Insert sale items and update stock
        $stmt_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        $stmt_stock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");

        foreach ($items as $item) {
            $product_id = $item['id'];
            $quantity = $item['quantity'];
            $unit_price = $item['price'];
            $total_price = $unit_price * $quantity;

            // Insert sale item
            $stmt_item->bind_param("iiidd", $sale_id, $product_id, $quantity, $unit_price, $total_price);
            if (!$stmt_item->execute()) {
                throw new Exception("Failed to add sale item");
            }

            // Update stock
            $stmt_stock->bind_param("iii", $quantity, $product_id, $quantity);
            if (!$stmt_stock->execute() || $stmt_stock->affected_rows === 0) {
                throw new Exception("Failed to update stock for product ID: " . $product_id);
            }
        }

        $stmt_item->close();
        $stmt_stock->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Sale completed successfully',
            'sale_id' => $sale_id
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Sale processing error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    $conn->close();
    exit();
}

// Fetch products from database
$products = [];
try {
    $stmt = $conn->prepare("SELECT product_id, product_name, price, stock_quantity, category FROM products WHERE stock_quantity > 0 ORDER BY product_name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Add hardcoded products for demo if database is empty
if (empty($products)) {
    $products = [
        ['product_id' => 1, 'product_name' => 'Basic White T-Shirt', 'price' => 299.00, 'stock_quantity' => 50, 'category' => 'Tops'],
        ['product_id' => 2, 'product_name' => 'Black Polo Shirt', 'price' => 450.00, 'stock_quantity' => 35, 'category' => 'Tops'],
        ['product_id' => 3, 'product_name' => 'Denim Jeans', 'price' => 899.00, 'stock_quantity' => 25, 'category' => 'Bottoms'],
        ['product_id' => 4, 'product_name' => 'Casual Shorts', 'price' => 599.00, 'stock_quantity' => 40, 'category' => 'Bottoms'],
        ['product_id' => 5, 'product_name' => 'Summer Dress', 'price' => 1299.00, 'stock_quantity' => 20, 'category' => 'Dresses'],
        ['product_id' => 6, 'product_name' => 'Floral Maxi Dress', 'price' => 1599.00, 'stock_quantity' => 15, 'category' => 'Dresses'],
        ['product_id' => 7, 'product_name' => 'Leather Jacket', 'price' => 2499.00, 'stock_quantity' => 10, 'category' => 'Outerwear'],
        ['product_id' => 8, 'product_name' => 'Denim Jacket', 'price' => 1899.00, 'stock_quantity' => 18, 'category' => 'Outerwear'],
        ['product_id' => 9, 'product_name' => 'Baseball Cap', 'price' => 349.00, 'stock_quantity' => 60, 'category' => 'Accessories'],
        ['product_id' => 10, 'product_name' => 'Leather Belt', 'price' => 499.00, 'stock_quantity' => 30, 'category' => 'Accessories'],
        ['product_id' => 11, 'product_name' => 'Cotton Hoodie', 'price' => 799.00, 'stock_quantity' => 28, 'category' => 'Tops'],
        ['product_id' => 12, 'product_name' => 'Cargo Pants', 'price' => 999.00, 'stock_quantity' => 22, 'category' => 'Bottoms'],
        ['product_id' => 13, 'product_name' => 'Cocktail Dress', 'price' => 1899.00, 'stock_quantity' => 12, 'category' => 'Dresses'],
        ['product_id' => 14, 'product_name' => 'Windbreaker', 'price' => 1299.00, 'stock_quantity' => 16, 'category' => 'Outerwear'],
        ['product_id' => 15, 'product_name' => 'Sunglasses', 'price' => 599.00, 'stock_quantity' => 45, 'category' => 'Accessories'],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - TrendyWear</title>
    <link rel="stylesheet" href="css/pos_system.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <div class="pos-header">
        <div class="header-left">
            <div class="header-logo">
                <svg width="24" height="24" viewBox="0 0 40 40" fill="none">
                    <path d="M20 5L10 15H16V30H24V15H30L20 5Z" fill="white"/>
                </svg>
            </div>
            <div class="header-info">
                <h1>TrendyWear POS</h1>
                <p>Point of Sale System</p>
            </div>
        </div>
        <div class="header-right">
            <div class="cashier-info"><?php echo htmlspecialchars($cashier_name); ?></div>
            <div class="branch-info"><?php echo htmlspecialchars($branch); ?></div>
        </div>
    </div>

    <!-- Main POS Container -->
    <div class="pos-container">
        <!-- Left Panel - Scanner Input -->
        <div class="scanner-panel">
            <!-- Barcode Input Display -->
            <div class="barcode-display">
                <div class="display-label">Barcode Scanner</div>
                <input type="text" id="barcodeInput" class="barcode-input" placeholder="Scan or Enter Product ID" autofocus>
                
                <!-- Product Preview -->
                <div id="productPreview" class="product-preview" style="display: none;" onclick="addPreviewProduct()">
                    <div class="preview-icon">
                        <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3z"/>
                        </svg>
                    </div>
                    <div class="preview-info">
                        <div class="preview-name" id="previewName"></div>
                        <div class="preview-details">
                            <span class="preview-price" id="previewPrice"></span>
                            <span class="preview-stock" id="previewStock"></span>
                        </div>
                    </div>
                    <div class="preview-add-hint">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Tap to add
                    </div>
                </div>
                
                <div class="scanner-buttons">
                    <button class="btn-scan" onclick="scanProduct()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V4zM8 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1H9a1 1 0 01-1-1V4zM15 3a1 1 0 00-1 1v12a1 1 0 001 1h2a1 1 0 001-1V4a1 1 0 00-1-1h-2z"/>
                        </svg>
                        Scan
                    </button>
                    <button class="btn-camera" onclick="openCameraScanner()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                        </svg>
                        Camera
                    </button>
                </div>
            </div>

            <!-- Number Pad -->
            <div class="numpad-container">
                <div class="numpad-display" id="numpadDisplay">0</div>
                <div class="numpad-grid">
                    <button class="numpad-btn" onclick="appendNumber('7')">7</button>
                    <button class="numpad-btn" onclick="appendNumber('8')">8</button>
                    <button class="numpad-btn" onclick="appendNumber('9')">9</button>
                    
                    <button class="numpad-btn" onclick="appendNumber('4')">4</button>
                    <button class="numpad-btn" onclick="appendNumber('5')">5</button>
                    <button class="numpad-btn" onclick="appendNumber('6')">6</button>
                    
                    <button class="numpad-btn" onclick="appendNumber('1')">1</button>
                    <button class="numpad-btn" onclick="appendNumber('2')">2</button>
                    <button class="numpad-btn" onclick="appendNumber('3')">3</button>
                    
                    <button class="numpad-btn numpad-clear" onclick="clearNumber()">C</button>
                    <button class="numpad-btn" onclick="appendNumber('0')">0</button>
                    <button class="numpad-btn numpad-backspace" onclick="backspaceNumber()">⌫</button>
                </div>
                <button class="numpad-add" onclick="addProductById()">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                    </svg>
                    Add to Cart
                </button>
            </div>
        </div>

        <!-- Right Panel - Receipt -->
        <div class="receipt-panel">
            <div class="receipt-header">
                <h2>TrendyWear</h2>
                <p><?php echo htmlspecialchars($branch); ?></p>
                <p style="font-size: 11px; margin-top: 4px;">Receipt #<span id="receiptNumber">00001</span></p>
                <p style="font-size: 11px;" id="receiptDate"></p>
            </div>

            <div class="receipt-items" id="receiptItems">
                <div class="empty-cart">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
                    </svg>
                    <p>Cart is empty</p>
                </div>
            </div>

            <div class="receipt-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">₱0.00</span>
                </div>
                <div class="summary-row">
                    <span>Tax (12%):</span>
                    <span id="tax">₱0.00</span>
                </div>
                <div class="summary-row total">
                    <span>TOTAL:</span>
                    <span id="total">₱0.00</span>
                </div>
            </div>

            <div class="receipt-actions">
                <div class="payment-modes">
                    <button class="btn-payment btn-cash" onclick="openPaymentModal('cash')">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                        </svg>
                        Cash
                    </button>
                    <button class="btn-payment btn-card" onclick="openPaymentModal('card')">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                            <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                        </svg>
                        Card
                    </button>
                    <button class="btn-payment btn-ewallet" onclick="openPaymentModal('ewallet')">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                        </svg>
                        E-Wallet
                    </button>
                </div>
                <button class="btn btn-secondary" onclick="printReceipt()">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/>
                    </svg>
                    Print Receipt
                </button>
                <button class="btn btn-secondary" onclick="clearCart()">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Clear Cart
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Complete Payment</h2>
                <span class="modal-close" onclick="closePaymentModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be dynamically loaded -->
            </div>
        </div>
    </div>

    <script>
        let cart = [];
        let receiptCounter = 1;
        let numpadValue = '0';
        let currentPaymentMode = '';
        
        // Product database for quick lookup
        const productDatabase = <?php echo json_encode($products); ?>;

        // Initialize date
        document.getElementById('receiptDate').textContent = new Date().toLocaleString();

        // Barcode scanner - listen for Enter key
        document.getElementById('barcodeInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                scanProduct();
            }
        });

        // Product preview on input
        document.getElementById('barcodeInput').addEventListener('input', function(e) {
            const barcode = e.target.value.trim();
            const preview = document.getElementById('productPreview');
            
            if (!barcode) {
                preview.style.display = 'none';
                return;
            }
            
            const product = productDatabase.find(p => p.product_id == barcode);
            
            if (product) {
                document.getElementById('previewName').textContent = product.product_name;
                document.getElementById('previewPrice').textContent = '₱' + parseFloat(product.price).toFixed(2);
                document.getElementById('previewStock').textContent = 'Stock: ' + product.stock_quantity;
                preview.style.display = 'flex';
                preview.classList.remove('preview-error');
                preview.style.cursor = 'pointer';
            } else {
                document.getElementById('previewName').textContent = 'Product not found';
                document.getElementById('previewPrice').textContent = 'ID: ' + barcode;
                document.getElementById('previewStock').textContent = 'Not in database';
                preview.style.display = 'flex';
                preview.classList.add('preview-error');
                preview.style.cursor = 'default';
            }
        });

        function addPreviewProduct() {
            const barcode = document.getElementById('barcodeInput').value.trim();
            const product = productDatabase.find(p => p.product_id == barcode);
            
            if (product) {
                // Open quantity modal
                openQuantityModal(product);
            }
        }

        function openQuantityModal(product) {
            const modal = document.getElementById('paymentModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');

            modalTitle.textContent = 'Enter Quantity';
            modalBody.innerHTML = `
                <div class="quantity-product-info">
                    <div class="quantity-product-icon">
                        <svg width="32" height="32" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3z"/>
                        </svg>
                    </div>
                    <div class="quantity-product-details">
                        <div class="quantity-product-name">${product.product_name}</div>
                        <div class="quantity-product-meta">
                            <span class="quantity-price">₱${parseFloat(product.price).toFixed(2)}</span>
                            <span class="quantity-stock">Available: ${product.stock_quantity}</span>
                        </div>
                    </div>
                </div>
                <div class="payment-message">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <p>Please enter the quantity you want to add to the cart.</p>
                </div>
                <div class="payment-input-group">
                    <label for="productQuantity">Quantity:</label>
                    <input type="number" id="productQuantity" class="payment-input" placeholder="Enter quantity" min="1" max="${product.stock_quantity}" value="1" autofocus>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-primary" onclick="confirmAddToCart(${product.product_id}, '${product.product_name.replace(/'/g, "\\'")}', ${product.price}, ${product.stock_quantity})">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Add to Cart
                    </button>
                    <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                </div>
            `;

            modal.style.display = 'block';

            // Auto-select input
            setTimeout(() => {
                document.getElementById('productQuantity').select();
            }, 100);

            // Allow Enter key to add
            document.getElementById('productQuantity').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    confirmAddToCart(product.product_id, product.product_name, product.price, product.stock_quantity);
                }
            });
        }

        function confirmAddToCart(id, name, price, stock) {
            const quantity = parseInt(document.getElementById('productQuantity').value);
            
            if (isNaN(quantity) || quantity < 1) {
                alert('Please enter a valid quantity!');
                return;
            }

            if (quantity > stock) {
                alert(`Not enough stock! Available: ${stock}`);
                return;
            }

            addToCart(id, name, price, stock, quantity);
            closePaymentModal();
            
            // Clear barcode input and preview
            document.getElementById('barcodeInput').value = '';
            document.getElementById('productPreview').style.display = 'none';
            document.getElementById('barcodeInput').focus();
        }

        function scanProduct() {
            const barcode = document.getElementById('barcodeInput').value.trim();
            if (!barcode) {
                alert('Please enter a product ID or scan a barcode');
                return;
            }

            const product = productDatabase.find(p => p.product_id == barcode);
            
            if (product) {
                const quantity = parseInt(numpadValue) || 1;
                addToCart(product.product_id, product.product_name, product.price, product.stock_quantity, quantity);
                document.getElementById('barcodeInput').value = '';
                document.getElementById('productPreview').style.display = 'none';
                clearNumber();
                document.getElementById('barcodeInput').focus();
            } else {
                alert('Product not found! ID: ' + barcode);
                document.getElementById('barcodeInput').value = '';
                document.getElementById('productPreview').style.display = 'none';
                document.getElementById('barcodeInput').focus();
            }
        }

        function appendNumber(num) {
            if (numpadValue === '0') {
                numpadValue = num;
            } else {
                numpadValue += num;
            }
            updateNumpadDisplay();
        }

        function clearNumber() {
            numpadValue = '0';
            updateNumpadDisplay();
        }

        function backspaceNumber() {
            if (numpadValue.length > 1) {
                numpadValue = numpadValue.slice(0, -1);
            } else {
                numpadValue = '0';
            }
            updateNumpadDisplay();
        }

        function updateNumpadDisplay() {
            document.getElementById('numpadDisplay').textContent = numpadValue;
        }

        function addProductById() {
            const productId = parseInt(numpadValue);
            const product = productDatabase.find(p => p.product_id === productId);
            
            if (product) {
                const quantity = prompt(`Enter quantity for ${product.product_name}:\n(Available stock: ${product.stock_quantity})`, '1');
                
                if (quantity === null) return;
                
                const qty = parseInt(quantity);
                
                if (isNaN(qty) || qty < 1) {
                    alert('Please enter a valid quantity!');
                    return;
                }
                
                addToCart(product.product_id, product.product_name, product.price, product.stock_quantity, qty);
                clearNumber();
            } else {
                alert('Product not found! ID: ' + productId);
            }
        }

        function addToCart(id, name, price, stock, quantity) {
            const existingItem = cart.find(item => item.id == id);
            
            if (existingItem) {
                const newTotal = existingItem.quantity + quantity;
                if (newTotal <= stock) {
                    existingItem.quantity = newTotal;
                } else {
                    alert(`Not enough stock! Available: ${stock - existingItem.quantity}`);
                    return;
                }
            } else {
                if (quantity <= stock) {
                    cart.push({
                        id: id,
                        name: name,
                        price: parseFloat(price),
                        stock: parseInt(stock),
                        quantity: quantity
                    });
                } else {
                    alert('Not enough stock!');
                    return;
                }
            }

            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('receiptItems');
            
            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="empty-cart">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
                        </svg>
                        <p>Cart is empty</p>
                    </div>
                `;
            } else {
                container.innerHTML = cart.map((item, index) => `
                    <div class="receipt-item">
                        <div class="item-details">
                            <div class="item-name">${item.name}</div>
                            <div class="item-price">₱${item.price.toFixed(2)} each</div>
                            <div class="item-quantity no-print">
                                <button class="qty-btn" onclick="updateQuantity(${index}, -1)">-</button>
                                <input type="number" class="qty-input" value="${item.quantity}" onchange="setQuantity(${index}, this.value)" min="1" max="${item.stock}">
                                <button class="qty-btn" onclick="updateQuantity(${index}, 1)">+</button>
                            </div>
                            <div class="item-quantity-print">× ${item.quantity}</div>
                        </div>
                        <div>
                            <div class="item-total">₱${(item.price * item.quantity).toFixed(2)}</div>
                            <span class="remove-btn no-print" onclick="removeItem(${index})">×</span>
                        </div>
                    </div>
                `).join('');
            }

            updateTotals();
        }

        function updateQuantity(index, change) {
            const item = cart[index];
            const newQuantity = item.quantity + change;

            if (newQuantity > 0 && newQuantity <= item.stock) {
                item.quantity = newQuantity;
                renderCart();
            } else if (newQuantity > item.stock) {
                alert('Not enough stock!');
            } else if (newQuantity < 1) {
                if (confirm('Remove this item from cart?')) {
                    removeItem(index);
                }
            }
        }

        function setQuantity(index, value) {
            const quantity = parseInt(value);
            const item = cart[index];

            if (quantity > 0 && quantity <= item.stock) {
                item.quantity = quantity;
                renderCart();
            } else if (quantity > item.stock) {
                alert('Not enough stock!');
                renderCart();
            } else {
                renderCart();
            }
        }

        function removeItem(index) {
            if (confirm('Remove this item from cart?')) {
                cart.splice(index, 1);
                renderCart();
            }
        }

        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.12;
            const total = subtotal + tax;

            document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '₱' + tax.toFixed(2);
            document.getElementById('total').textContent = '₱' + total.toFixed(2);
        }

        function clearCart() {
            if (cart.length > 0) {
                if (confirm('Are you sure you want to clear the cart?')) {
                    cart = [];
                    renderCart();
                }
            }
        }

        function printReceipt() {
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }
            window.print();
        }

        // Payment Modal Functions
        function openPaymentModal(mode) {
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }

            currentPaymentMode = mode;
            const modal = document.getElementById('paymentModal');
            const modalBody = document.getElementById('modalBody');
            const modalTitle = document.getElementById('modalTitle');
            
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.12;

            if (mode === 'cash') {
                modalTitle.textContent = 'Cash Payment';
                modalBody.innerHTML = `
                    <div class="payment-summary">
                        <div class="payment-total">
                            <span>Total Amount:</span>
                            <span class="amount">₱${total.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="payment-message">
                        <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <p>Please enter the amount of cash received from the customer.</p>
                    </div>
                    <div class="payment-input-group">
                        <label for="cashReceived">Cash Received:</label>
                        <input type="number" id="cashReceived" class="payment-input" placeholder="Enter amount" step="0.01" min="${total}" autofocus>
                    </div>
                    <div class="payment-change" id="changeDisplay" style="display: none;">
                        <span>Change:</span>
                        <span class="change-amount" id="changeAmount">₱0.00</span>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-primary" onclick="processCashPayment()">Complete Payment</button>
                        <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                    </div>
                `;
                
                // Auto-calculate change
                document.getElementById('cashReceived').addEventListener('input', function() {
                    const received = parseFloat(this.value) || 0;
                    const change = received - total;
                    const changeDisplay = document.getElementById('changeDisplay');
                    const changeAmount = document.getElementById('changeAmount');
                    
                    if (received >= total) {
                        changeDisplay.style.display = 'flex';
                        changeAmount.textContent = '₱' + change.toFixed(2);
                        changeAmount.style.color = change > 0 ? '#00c851' : '#666';
                    } else {
                        changeDisplay.style.display = 'none';
                    }
                });

            } else if (mode === 'card') {
                modalTitle.textContent = 'Card Payment';
                modalBody.innerHTML = `
                    <div class="payment-summary">
                        <div class="payment-total">
                            <span>Total Amount:</span>
                            <span class="amount">₱${total.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="payment-message">
                        <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <p>Please enter the card details. Ensure the card has sufficient funds for the transaction.</p>
                    </div>
                    <div class="payment-input-group">
                        <label for="cardNumber">Card Number:</label>
                        <input type="text" id="cardNumber" class="payment-input" placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="19" autofocus>
                    </div>
                    <div class="payment-row">
                        <div class="payment-input-group">
                            <label for="cardExpiry">Expiry Date:</label>
                            <input type="text" id="cardExpiry" class="payment-input" placeholder="MM/YY" maxlength="5">
                        </div>
                        <div class="payment-input-group">
                            <label for="cardCVV">CVV:</label>
                            <input type="text" id="cardCVV" class="payment-input" placeholder="XXX" maxlength="3">
                        </div>
                    </div>
                    <div class="payment-input-group">
                        <label for="cardHolder">Cardholder Name:</label>
                        <input type="text" id="cardHolder" class="payment-input" placeholder="Name on card">
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-primary" onclick="processCardPayment()">Process Payment</button>
                        <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                    </div>
                `;
                
                // Format card number
                document.getElementById('cardNumber').addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                    let formattedValue = value.match(/.{1,4}/g)?.join('-') || value;
                    e.target.value = formattedValue;
                });
                
                // Format expiry
                document.getElementById('cardExpiry').addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length >= 2) {
                        value = value.slice(0, 2) + '/' + value.slice(2, 4);
                    }
                    e.target.value = value;
                });

            } else if (mode === 'ewallet') {
                modalTitle.textContent = 'E-Wallet Payment';
                modalBody.innerHTML = `
                    <div class="payment-summary">
                        <div class="payment-total">
                            <span>Total Amount:</span>
                            <span class="amount">₱${total.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="payment-message">
                        <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <p>Select your preferred e-wallet provider and enter the payment details. Ensure the account has sufficient balance.</p>
                    </div>
                    <div class="ewallet-options">
                        <button class="ewallet-btn" onclick="selectEwallet('GCash')">
                            <div class="ewallet-icon gcash">G</div>
                            <span>GCash</span>
                        </button>
                        <button class="ewallet-btn" onclick="selectEwallet('PayMaya')">
                            <div class="ewallet-icon paymaya">P</div>
                            <span>PayMaya</span>
                        </button>
                        <button class="ewallet-btn" onclick="selectEwallet('GrabPay')">
                            <div class="ewallet-icon grabpay">G</div>
                            <span>GrabPay</span>
                        </button>
                    </div>
                    <div id="ewalletDetails" style="display: none;">
                        <div class="payment-input-group">
                            <label for="ewalletPhone">Mobile Number:</label>
                            <input type="tel" id="ewalletPhone" class="payment-input" placeholder="09XX XXX XXXX" maxlength="13">
                        </div>
                        <div class="payment-input-group">
                            <label for="ewalletReference">Reference Number:</label>
                            <input type="text" id="ewalletReference" class="payment-input" placeholder="Enter reference number">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-primary" id="ewalletPayBtn" onclick="processEwalletPayment()" style="display: none;">Complete Payment</button>
                        <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                    </div>
                `;
                
                // Format phone number
                const phoneInput = document.getElementById('ewalletPhone');
                if (phoneInput) {
                    phoneInput.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        if (value.length > 4 && value.length <= 7) {
                            value = value.slice(0, 4) + ' ' + value.slice(4);
                        } else if (value.length > 7) {
                            value = value.slice(0, 4) + ' ' + value.slice(4, 7) + ' ' + value.slice(7, 11);
                        }
                        e.target.value = value;
                    });
                }
            }

            modal.style.display = 'block';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            currentPaymentMode = '';
        }

        function selectEwallet(provider) {
            document.getElementById('ewalletDetails').style.display = 'block';
            document.getElementById('ewalletPayBtn').style.display = 'block';
            
            // Highlight selected ewallet
            document.querySelectorAll('.ewallet-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.closest('.ewallet-btn').classList.add('selected');
            
            // Store selected provider
            currentPaymentMode = provider;
        }

        function processCashPayment() {
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.12;
            const received = parseFloat(document.getElementById('cashReceived').value) || 0;
            
            if (received < total) {
                alert('Insufficient cash received!');
                return;
            }
            
            const change = received - total;
            completeSale('Cash', { received: received.toFixed(2), change: change.toFixed(2) });
        }

        function processCardPayment() {
            const cardNumber = document.getElementById('cardNumber').value;
            const cardExpiry = document.getElementById('cardExpiry').value;
            const cardCVV = document.getElementById('cardCVV').value;
            const cardHolder = document.getElementById('cardHolder').value;
            
            if (!cardNumber || !cardExpiry || !cardCVV || !cardHolder) {
                alert('Please fill in all card details!');
                return;
            }
            
            // Simulate card processing
            const lastFour = cardNumber.replace(/-/g, '').slice(-4);
            completeSale('Card', { lastFour: lastFour, holder: cardHolder });
        }

        function processEwalletPayment() {
            const phone = document.getElementById('ewalletPhone').value;
            const reference = document.getElementById('ewalletReference').value;
            
            if (!phone || !reference) {
                alert('Please fill in all e-wallet details!');
                return;
            }
            
            completeSale('E-Wallet', { provider: currentPaymentMode, phone: phone, reference: reference });
        }

        function completeSale(paymentMethod = 'Cash', paymentDetails = {}) {
            if (cart.length === 0) {
                alert('Cart is empty!');
                return;
            }

            const saleData = {
                items: cart,
                subtotal: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
                tax: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 0.12,
                total: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.12,
                cashier_id: <?php echo $_SESSION['cashier_id'] ?? 1; ?>,
                branch: '<?php echo $branch; ?>',
                payment_method: paymentMethod,
                payment_details: paymentDetails
            };

            // Send to server via AJAX
            fetch('cashier_pos.php?action=process_sale', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(saleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePaymentModal();
                    
                    let successMessage = `Sale completed successfully!\n\nReceipt #${data.sale_id}\nPayment Method: ${paymentMethod}`;
                    
                    if (paymentMethod === 'Cash' && paymentDetails.change) {
                        successMessage += `\n\nCash Received: ₱${paymentDetails.received}\nChange: ₱${paymentDetails.change}`;
                    } else if (paymentMethod === 'Card' && paymentDetails.lastFour) {
                        successMessage += `\n\nCard ending in: ${paymentDetails.lastFour}`;
                    } else if (paymentMethod === 'E-Wallet' && paymentDetails.provider) {
                        successMessage += `\n\nProvider: ${paymentDetails.provider}\nReference: ${paymentDetails.reference}`;
                    }
                    
                    alert(successMessage);
                    
                    receiptCounter++;
                    document.getElementById('receiptNumber').textContent = String(receiptCounter).padStart(5, '0');
                    cart = [];
                    renderCart();
                    document.getElementById('barcodeInput').focus();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the sale.');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closePaymentModal();
            }
        }
    </script>
</body>
</html>