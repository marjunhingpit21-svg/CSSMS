<?php
session_start();

if (!isset($_SESSION['employee_id'])) { header('Location: index.php'); exit(); }

require_once '../database/db.php';

$employee_id   = $_SESSION['employee_id'] ?? 1;
$branch_id     = $_SESSION['branch_id'] ?? 1;

// Fetch employee details
$query = "SELECT first_name, last_name, employee_number, position FROM employees WHERE employee_id = $employee_id";
$result = mysqli_query($conn, $query);
$employee = mysqli_fetch_assoc($result);

$employee_name = $employee ? $employee['first_name'] . ' ' . $employee['last_name'] : 'Cashier';
$employee_number = $employee['employee_number'] ?? '';
$employee_position = $employee['position'] ?? 'cashier';

// Fetch branch name
$query = "SELECT branch_name FROM branches WHERE branch_id = $branch_id";
$result = mysqli_query($conn, $query);
$branch = mysqli_fetch_assoc($result);
$branch_name = $branch['branch_name'] ?? 'Main Branch';
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
<div class="pos-header">
    <div class="header-left">
        <img src="../img/a.png" alt="Altiere" style="height: 40px; width: auto;">
    </div>
    <div class="header-right">
        <div class="cashier-info"><?php echo htmlspecialchars($employee_name); ?></div>
        <div class="branch-info"><?php echo htmlspecialchars($branch_name); ?></div>
    </div>
</div>

<!-- Main Container -->
<div class="pos-container">

    <!-- Left: Scanner + Search -->
    <div class="scanner-panel">
        <div class="barcode-display">
            <div class="display-label">Search Product ID or Scan Barcode</div>
            
            <input type="text" id="searchInput" class="barcode-input" 
                   placeholder="Type ID (e.g. 1) or scan barcode..." autocomplete="off" autofocus>

            <!-- Search Results -->
            <div id="searchResults" class="search-results" style="display:none;"></div>

            <!-- Product Preview (click to add to cart) -->
            <div id="productPreview" class="product-preview" style="display:none; cursor: pointer;"></div>

        </div>

        <!-- Numpad -->
        <div class="numpad-container">
            <div class="numpad-grid">
                <!-- ROW 1 -->
                <button class="numpad-btn fn-btn" onclick="applyDiscount()" title="Apply Discount" id="fn-f1">
                    <span class="fn-key">F1</span>
                    <span class="fn-label">Discount</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="viewTransactionHistory()" title="View Transactions" id="fn-f2">
                    <span class="fn-key">F2</span>
                    <span class="fn-label">Transactions</span>
                </button>
                <button class="numpad-btn" onclick="appendNumber('7')">7</button>
                <button class="numpad-btn" onclick="appendNumber('8')">8</button>
                <button class="numpad-btn" onclick="appendNumber('9')">9</button>

                <!-- ROW 2 -->
                <button class="numpad-btn fn-btn" onclick="deleteAllItems()" title="Delete All Items" id="fn-f3">
                    <span class="fn-key">F3</span>
                    <span class="fn-label">Delete All Items</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="changePrice()" title="Change Price" id="fn-f4">
                    <span class="fn-key">F4</span>
                    <span class="fn-label">Change Price</span>
                </button>
                <button class="numpad-btn" onclick="appendNumber('4')">4</button>
                <button class="numpad-btn" onclick="appendNumber('5')">5</button>
                <button class="numpad-btn" onclick="appendNumber('6')">6</button>

                <!-- ROW 3 -->
                <button class="numpad-btn fn-btn" onclick="addNotes()" title="Add Notes" id="fn-f5">
                    <span class="fn-key">F5</span>
                    <span class="fn-label">Add Notes</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="changeQuantity()" title="Change Quantity" id="fn-f6">
                    <span class="fn-key">F6</span>
                    <span class="fn-label">Change Qty</span>
                </button>
                <button class="numpad-btn" onclick="appendNumber('1')">1</button>
                <button class="numpad-btn" onclick="appendNumber('2')">2</button>
                <button class="numpad-btn" onclick="appendNumber('3')">3</button>

                <!-- ROW 4 -->
                <button class="numpad-btn fn-btn" onclick="splitReceipt()" title="Split Receipt" id="fn-f7">
                    <span class="fn-key">F7</span>
                    <span class="fn-label">Split Receipt</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="deleteSelectedItem()" title="Delete Item" id="fn-f8">
                    <span class="fn-key">F8</span>
                    <span class="fn-label">Delete Item</span>
                </button>
                <button class="numpad-btn numpad-clear" onclick="clearNumber()">C</button>
                <button class="numpad-btn" onclick="appendNumber('0')">0</button>
                <button class="numpad-btn numpad-backspace" onclick="backspaceNumber()">×</button>
                <!-- ROW 5 - Logout Button (Bottom, Full Width) -->
                <button class="numpad-btn fn-btn fn-logout" onclick="logoutCashier()" title="Logout">
                    <span class="fn-key">ESC</span>
                    <span class="fn-label">Logout</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Right: Receipt -->
    <div class="receipt-panel">
        <!-- Main receipt (shown by default) -->
        <div id="mainReceipt">
            <div class="receipt-header">
                <h2>Altiere</h2>
                <p><?php echo htmlspecialchars($branch_name); ?></p>
                <p style="font-size:11px;margin-top:4px;">Receipt #<span id="receiptNumber">00001</span></p>
                <p style="font-size:11px;" id="receiptDate"></p>
            </div>

            <div class="receipt-items" id="receiptItems">
                <div class="empty-cart">Cart is empty</div>
            </div>

            <div class="receipt-summary">
                <div class="summary-row"><span>Subtotal:</span><span id="subtotal">₱0.00</span></div>
                <div class="summary-row"><span>Tax (12%):</span><span id="tax">₱0.00</span></div>
                <div class="summary-row"><span>Discount:</span><span id="discount">₱0.00</span></div>
                <div class="summary-row total"><span>TOTAL:</span><span id="total">₱0.00</span></div>
            </div>

            <div class="receipt-actions">
                <div class="payment-modes">
                    <button class="btn-payment btn-cash" onclick="openPaymentModal('cash')">Cash</button>
                    <button class="btn-payment btn-card" onclick="openPaymentModal('card')">Card</button>
                    <button class="btn-payment btn-ewallet" onclick="openPaymentModal('ewallet')">E-Wallet</button>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button class="btn btn-secondary" onclick="printReceipt()" style="flex: 1;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="vertical-align: middle; margin-right: 5px;">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                        </svg>
                        Print Receipt
                    </button>
                    <button class="btn btn-secondary" onclick="clearCart()">Clear Cart</button>
                </div>
            </div>
        </div>

        <!-- Split Receipt Container (hidden by default) -->
        <div id="splitReceiptContainer" style="display: none;">
            <!-- Receipt 1 -->
            <div class="split-receipt" id="splitReceipt1">
                <div class="split-receipt-header">
                    <h2>Altiere</h2>
                    <p><?php echo htmlspecialchars($branch_name); ?></p>
                    <p style="font-size:11px;margin-top:4px;">Receipt #<span id="splitReceipt1Number">00001-A</span></p>
                    <p style="font-size:11px;margin-bottom:10px;color:#e91e63;font-weight:600;">SPLIT RECEIPT 1</p>
                </div>

                <div class="split-receipt-items" id="splitReceipt1Items">
                    <div class="empty-cart">No items</div>
                </div>

                <div class="split-receipt-summary">
                    <div class="summary-row"><span>Subtotal:</span><span id="splitReceipt1Subtotal">₱0.00</span></div>
                    <div class="summary-row"><span>Tax (12%):</span><span id="splitReceipt1Tax">₱0.00</span></div>
                    <div class="summary-row"><span>Discount:</span><span id="splitReceipt1Discount">₱0.00</span></div>
                    <div class="summary-row total"><span>TOTAL:</span><span id="splitReceipt1Total">₱0.00</span></div>
                </div>

                <div class="split-receipt-actions">
                    <button class="btn btn-primary split-pay-btn" onclick="paySplitReceipt(1)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                            <path d="M12 1v22M5 12h14" stroke-width="2"/>
                        </svg>
                        Pay Receipt 1
                    </button>
                </div>
            </div>

            <!-- Divider between split receipts -->
            <div class="split-receipt-divider">
                <div class="divider-line"></div>
                <div class="divider-text">SPLIT TRANSACTION</div>
                <div class="divider-line"></div>
            </div>

            <!-- Receipt 2 -->
            <div class="split-receipt" id="splitReceipt2">
                <div class="split-receipt-header">
                    <h2>Altiere</h2>
                    <p><?php echo htmlspecialchars($branch_name); ?></p>
                    <p style="font-size:11px;margin-top:4px;">Receipt #<span id="splitReceipt2Number">00001-B</span></p>
                    <p style="font-size:11px;margin-bottom:10px;color:#c2185b;font-weight:600;">SPLIT RECEIPT 2</p>
                </div>

                <div class="split-receipt-items" id="splitReceipt2Items">
                    <div class="empty-cart">No items</div>
                </div>

                <div class="split-receipt-summary">
                    <div class="summary-row"><span>Subtotal:</span><span id="splitReceipt2Subtotal">₱0.00</span></div>
                    <div class="summary-row"><span>Tax (12%):</span><span id="splitReceipt2Tax">₱0.00</span></div>
                    <div class="summary-row"><span>Discount:</span><span id="splitReceipt2Discount">₱0.00</span></div>
                    <div class="summary-row total"><span>TOTAL:</span><span id="splitReceipt2Total">₱0.00</span></div>
                </div>

                <div class="split-receipt-actions">
                    <button class="btn btn-primary split-pay-btn" onclick="paySplitReceipt(2)" disabled>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                            <path d="M12 1v22M5 12h14" stroke-width="2"/>
                        </svg>
                        Pay Receipt 2
                    </button>
                </div>
            </div>

            <!-- Split receipt actions -->
            <div class="split-controls">
                <button class="btn btn-secondary" onclick="cancelSplitReceipt()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <line x1="15" y1="9" x2="9" y2="15" stroke-width="2"/>
                        <line x1="9" y1="9" x2="15" y2="15" stroke-width="2"/>
                    </svg>
                    Cancel Split
                </button>
                <button class="btn btn-secondary" onclick="printSplitReceipts()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                        <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                        <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                    </svg>
                    Print Both
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Complete Payment</h2>
            <span class="modal-close" onclick="closePaymentModal()">×</span>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- Transaction History Modal -->
<div id="transactionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="transactionModalTitle">Transaction History</h2>
            <span class="modal-close" onclick="closeTransactionModal()">×</span>
        </div>
        <div class="modal-body">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<script>
    let cart = [];
    let receiptCounter = 1;
    let selectedItemIndex = -1;
    let globalDiscount = 0; // Global discount percentage
    let splitReceiptActive = false;
    let splitReceipt1Items = [];
    let splitReceipt2Items = [];
    let splitReceipt1Paid = false;
    let originalReceiptNumber = '';
    let splitPaymentData = {
        receipt1: null,
        receipt2: null
    };

    // Init - Fetch next receipt number from database
    document.getElementById('receiptDate').textContent = new Date().toLocaleString();
    
    // Load receipt number from database
    fetch('get_next_receipt_number.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                receiptCounter = parseInt(data.next_receipt_number);
                document.getElementById('receiptNumber').textContent = data.next_receipt_number;
                originalReceiptNumber = data.next_receipt_number;
            }
        })
        .catch(err => {
            console.error('Error loading receipt number:', err);
        });

    // === KEYBOARD SHORTCUTS ===
    document.addEventListener('keydown', function(e) {
        const activeElement = document.activeElement;
        const isTyping = activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA';
        
        // Disable F keys if split receipt is active
        if (splitReceiptActive) {
            if (e.key.startsWith('F') && e.key.length === 2) {
                e.preventDefault();
                return;
            }
        }
        
        if (e.key === 'F1') { e.preventDefault(); applyDiscount(); }
        else if (e.key === 'F2') { e.preventDefault(); viewTransactionHistory(); }
        else if (e.key === 'F3') { e.preventDefault(); deleteAllItems(); }
        else if (e.key === 'F4') { e.preventDefault(); changePrice(); }
        else if (e.key === 'F5') { e.preventDefault(); addNotes(); }
        else if (e.key === 'F6') { e.preventDefault(); changeQuantity(); }
        else if (e.key === 'F7') { e.preventDefault(); splitReceipt(); }
        else if (e.key === 'F8') { e.preventDefault(); deleteSelectedItem(); }
        else if (e.key === 'Escape' && !isTyping) { e.preventDefault(); logoutCashier(); }
    });

    // Function to disable F keys when split receipt is active
    function updateFKeyState() {
        const fKeys = ['fn-f1', 'fn-f2', 'fn-f3', 'fn-f4', 'fn-f5', 'fn-f6', 'fn-f7', 'fn-f8'];
        fKeys.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.disabled = splitReceiptActive;
                if (splitReceiptActive) {
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                } else {
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                }
            }
        });
    }

    // === IMPROVED SEARCH LOGIC WITH DEBUGGING ===
    let searchTimeout;
    let allProducts = [];
    let barcodeBuffer = '';
    let barcodeTimeout;
    const searchInput = document.getElementById('searchInput');
    const resultsBox = document.getElementById('searchResults');

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();

        console.log('Search input:', q); // Debug log

        if (q.length < 1) {
            resultsBox.style.display = 'none';
            allProducts = [];
            return;
        }

        searchTimeout = setTimeout(() => {
            console.log('Fetching products for:', q); // Debug log
            
            fetch(`search_products.php?q=${encodeURIComponent(q)}`)
                .then(r => {
                    console.log('Response status:', r.status); // Debug log
                    if (!r.ok) {
                        throw new Error(`HTTP error! status: ${r.status}`);
                    }
                    return r.json();
                })
                .then(data => {
                    console.log('Search response:', data); // Debug log
                    
                    // Handle different response formats
                    const products = data.products || data || [];
                    allProducts = products;
                    
                    if (products.length === 0) {
                        resultsBox.innerHTML = `
                            <div class="search-item" style="padding: 20px; text-align: center; color: #666;">
                                <p>No products found for "${q}"</p>
                                <p style="font-size: 12px; margin-top: 10px;">
                                    Try searching by:<br>
                                    • Product ID (e.g., 1)<br>
                                    • Product name<br>
                                    • Barcode
                                </p>
                            </div>
                        `;
                        resultsBox.style.display = 'block';
                        return;
                    }

                    resultsBox.innerHTML = products.map((p, i) => `
                        <div class="search-item ${i===0?'selected':''}" 
                             data-index="${i}"
                             onclick="selectProduct(${i})"
                             onmouseover="highlightProduct(${i})"
                             style="cursor: pointer; transition: transform 0.1s ease;"
                             onmousedown="this.style.transform='scale(0.98)'"
                             onmouseup="this.style.transform='scale(1)'">
                            <div style="display:flex; align-items:center; gap:14px;">
                                <div style="width:60px;height:60px;border-radius:8px;overflow:hidden;background:#f8f8f8;border:1px solid #eee;flex-shrink:0;">
                                    <img src="${p.image_url}" 
                                         style="width:100%;height:100%;object-fit:cover;"
                                         onerror="this.src='https://via.placeholder.com/60x60/eee/999?text=IMG'">
                                </div>
                                <div style="flex:1;">
                                    <div style="font-weight:600;">
                                        ${p.product_name}
                                        <span style="color:#1976d2;font-size:11px;margin-left:8px;">ID: ${p.product_id}</span>
                                    </div>
                                    <div style="font-size:12px;color:#555;margin-top:4px;">
                                        <strong>${p.size_name}</strong>
                                        ${p.barcode ? ` • ${p.barcode}` : ''}  
                                        <br>Stock: <span style="color:${p.stock_quantity<5?'#d32f2f':'#2e7d32'}">${p.stock_quantity}</span>
                                    </div>
                                </div>
                                <div style="font-size:16px;font-weight:700;color:#1976d2;">
                                    ₱${parseFloat(p.final_price).toFixed(2)}
                                </div>
                            </div>
                        </div>
                    `).join('');

                    resultsBox.style.display = 'block';
                })
                .catch(err => {
                    console.error('Search error:', err); // Debug log
                    resultsBox.innerHTML = `
                        <div class="search-item" style="padding: 20px; text-align: center; color: #d32f2f;">
                            <p><strong>Error loading products</strong></p>
                            <p style="font-size: 12px; margin-top: 10px;">${err.message}</p>
                            <p style="font-size: 12px; margin-top: 10px;">
                                Please check:<br>
                                • Database connection<br>
                                • search_products.php file exists<br>
                                • Browser console for errors
                            </p>
                        </div>
                    `;
                    resultsBox.style.display = 'block';
                });
        }, 400);
    });

    function highlightProduct(index) {
        document.querySelectorAll('.search-item').forEach((item, i) => {
            item.classList.toggle('selected', i === index);
        });
    }

    function selectProduct(index) {
        if (allProducts[index]) {
            addProductFromSearch(allProducts[index]);
            searchInput.value = '';
            resultsBox.style.display = 'none';
            allProducts = [];
            searchInput.focus();
        }
    }

    function addProductFromSearch(product) {
        const qty = 1;
        
        if (qty > product.stock_quantity) {
            alert('Not enough stock! Only ' + product.stock_quantity + ' available.');
            return;
        }

        const existing = cart.find(i => i.product_size_id === product.product_size_id);
        if (existing) {
            if (existing.quantity + qty > product.stock_quantity) {
                alert('Not enough stock!');
                return;
            }
            existing.quantity += qty;
            selectedItemIndex = cart.indexOf(existing);
        } else {
            cart.push({
                product_size_id: product.product_size_id,
                product_name: product.product_name,
                size_name: product.size_name,
                final_price: parseFloat(product.final_price),
                original_price: parseFloat(product.final_price),
                stock_quantity: product.stock_quantity,
                quantity: qty,
                discount: 0,
                notes: '',
                clerk_name: '<?php echo htmlspecialchars($employee_name); ?>',
                price_changed: false
            });
            selectedItemIndex = cart.length - 1;
        }

        renderCart();
        showFunctionFeedback('Item added to cart');
        document.getElementById('productPreview').style.display = 'none';
    }

    searchInput.addEventListener('keydown', function(e) {
        const items = resultsBox.querySelectorAll('.search-item');
        let sel = resultsBox.querySelector('.selected');
        let idx = sel ? Array.from(items).indexOf(sel) : -1;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (idx < items.length-1) {
                sel.classList.remove('selected');
                items[idx+1].classList.add('selected');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (idx > 0) {
                sel.classList.remove('selected');
                items[idx-1].classList.add('selected');
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (sel) sel.click();
        } else if (e.key === 'Escape') {
            resultsBox.style.display = 'none';
        }
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.barcode-display')) {
            resultsBox.style.display = 'none';
        }
    });

    // === CART RENDERING ===
    function renderCart() {
        const container = document.getElementById('receiptItems');
        if (cart.length === 0) {
            container.innerHTML = '<div class="empty-cart"><p>Cart is empty</p></div>';
            updateTotals();
            selectedItemIndex = -1;
            return;
        }

        container.innerHTML = cart.map((item, i) => `
            <div class="receipt-item ${i === selectedItemIndex ? 'latest-item' : ''}">
                <div class="item-details">
                    <div class="item-name">
                        ${item.product_name} <span class="size-tag">${item.size_name}</span>
                        ${item.price_changed ? `<span class="price-change">Price Changed</span>` : ''}
                        ${item.clerk_name !== '<?php echo htmlspecialchars($employee_name); ?>' ? `<span class="clerk-indicator">${item.clerk_name}</span>` : ''}
                    </div>
                    <div class="item-price">₱${item.final_price.toFixed(2)} × ${item.quantity}</div>
                    ${item.notes ? `<div style="font-size:11px;color:#666;margin-top:3px;background:#f5f5f5;padding:4px 8px;border-radius:4px;">📝 ${item.notes}</div>` : ''}
                    <div class="item-quantity-print">Qty: ${item.quantity}</div>
                </div>
                <div>
                    <div class="item-total">₱${(item.final_price * item.quantity).toFixed(2)}</div>
                    <span class="remove-btn no-print" onclick="event.stopPropagation(); removeItem(${i})">×</span>
                </div>
            </div>
        `).join('');

        updateTotals();
    }

    function removeItem(index) {
        if (confirm(`Remove "${cart[index].product_name}" from cart?`)) {
            cart.splice(index, 1);
            if (cart.length > 0) {
                selectedItemIndex = cart.length - 1;
            } else {
                selectedItemIndex = -1;
            }
            renderCart();
        }
    }

    function updateTotals() {
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;

        document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
        document.getElementById('tax').textContent = '₱' + tax.toFixed(2);
        document.getElementById('discount').textContent = '₱' + discountAmount.toFixed(2);
        document.getElementById('total').textContent = '₱' + total.toFixed(2);
    }

    function clearCart() {
        if (cart.length && confirm('Clear cart?')) {
            cart = [];
            selectedItemIndex = -1;
            globalDiscount = 0;
            renderCart();
        }
    }

    // === F3 - DELETE ALL ITEMS ===
    function deleteAllItems() {
        if (cart.length === 0) {
            showFunctionFeedback('Cart is already empty');
            return;
        }
        
        if (confirm(`Are you sure you want to delete all ${cart.length} item(s) from the cart?`)) {
            cart = [];
            selectedItemIndex = -1;
            globalDiscount = 0;
            renderCart();
            showFunctionFeedback('All items deleted from cart');
        }
    }

    function appendNumber(n) {
        searchInput.value += n;
        searchInput.focus();
        searchInput.dispatchEvent(new Event('input'));
    }

    function clearNumber() {
        searchInput.value = '';
        searchInput.focus();
        resultsBox.style.display = 'none';
    }

    function backspaceNumber() {
        searchInput.value = searchInput.value.slice(0, -1);
        searchInput.focus();
        searchInput.dispatchEvent(new Event('input'));
    }

    function showFunctionFeedback(message) {
        const existing = document.querySelector('.function-feedback');
        if (existing) existing.remove();
        
        const feedback = document.createElement('div');
        feedback.className = 'function-feedback';
        feedback.textContent = message;
        document.body.appendChild(feedback);
        
        setTimeout(() => {
            if (feedback.parentNode) {
                feedback.remove();
            }
        }, 2000);
    }

    // === F2 - APPLY DISCOUNT ===
    function applyDiscount() {
        if (cart.length === 0) {
            alert('Please add items to the cart first.');
            return;
        }

        const modalHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 20px; color: #1a1a2e;">Apply Transaction Discount</h3>
                
                <div style="background: #f0f7ff; border-left: 4px solid #2196f3; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #1a1a2e; font-size: 14px;">
                        💡 This will apply a discount to the entire transaction subtotal.
                    </p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Current Discount:</label>
                    <div style="font-size: 24px; font-weight: 700; color: #e91e63;">${globalDiscount}%</div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Enter Discount Percentage (0-100):</label>
                    <input type="number" id="discountInput" 
                           style="width: 100%; padding: 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 18px;"
                           placeholder="0" min="0" max="100" step="0.01" value="${globalDiscount}" autofocus>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="saveDiscount()" style="flex: 1;">Apply Discount</button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Apply Discount', modalHTML);
        setTimeout(() => document.getElementById('discountInput').select(), 100);
    }

    function saveDiscount() {
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        
        if (discount < 0 || discount > 100) {
            alert('Invalid discount. Please enter a value between 0 and 100.');
            return;
        }
        
        globalDiscount = discount;
        updateTotals();
        showFunctionFeedback(`Transaction discount: ${discount}%`);
        closeCustomModal();
    }

    // === F3 - VIEW TRANSACTION HISTORY ===
    function viewTransactionHistory() {
        document.getElementById('transactionModal').style.display = 'block';
        loadTransactionHistory();
    }

    function loadTransactionHistory(date = null) {
        if (!date) {
            date = new Date().toISOString().split('T')[0];
        }
        
        const modalBody = document.querySelector('#transactionModal .modal-body');
        modalBody.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading transactions...</p>
            </div>
        `;
        
        fetch(`get_transaction.php?date=${date}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderTransactionHistory(data, date);
                } else {
                    modalBody.innerHTML = `
                        <div class="no-transactions">
                            <h3>No Transactions Found</h3>
                            <p>${data.message || 'Try selecting a different date'}</p>
                        </div>
                    `;
                }
            })
            .catch(err => {
                modalBody.innerHTML = `
                    <div class="no-transactions">
                        <h3>Error Loading Transactions</h3>
                        <p>${err.message}</p>
                    </div>
                `;
            });
    }

    function renderTransactionHistory(data, date) {
        const { transactions, summary } = data;
        const modalBody = document.querySelector('#transactionModal .modal-body');
        
        let html = `
            <div class="transaction-filters">
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" id="transactionDate" class="filter-input" value="${date}">
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="transactionSearch" class="filter-input" placeholder="Receipt # or Cashier">
                </div>
                <button class="btn-filter" onclick="filterTransactions()">Filter</button>
            </div>
            
            <div class="transaction-summary">
                <div class="summary-card">
                    <div class="summary-label">Total Sales</div>
                    <div class="summary-value">₱${summary.total_revenue ? parseFloat(summary.total_revenue).toFixed(2) : '0.00'}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Transactions</div>
                    <div class="summary-value">${summary.total_transactions || 0}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Cash</div>
                    <div class="summary-value">₱${summary.cash_total ? parseFloat(summary.cash_total).toFixed(2) : '0.00'}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Discounts</div>
                    <div class="summary-value">₱${summary.total_discounts ? parseFloat(summary.total_discounts).toFixed(2) : '0.00'}</div>
                </div>
            </div>
        `;
        
        if (transactions.length === 0) {
            html += `
                <div class="no-transactions">
                    <h3>No Transactions Found</h3>
                    <p>No transactions found for ${date}</p>
                </div>
            `;
        } else {
            html += `
                <div class="transactions-table-container">
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Time</th>
                                <th>Cashier</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${transactions.map(transaction => {
                                const isVoided = transaction.status === 'voided';
                                return `
                                    <tr data-sale-id="${transaction.sale_id}" ${isVoided ? 'style="opacity: 0.6;"' : ''}>
                                        <td>
                                            <div class="transaction-receipt">${transaction.sale_number}</div>
                                            <div class="transaction-time">${formatTime(transaction.sale_date)}</div>
                                        </td>
                                        <td>${formatDate(transaction.sale_date)}</td>
                                        <td>${transaction.cashier_name}</td>
                                        <td>
                                            <span class="payment-badge ${transaction.payment_method}">
                                                ${transaction.payment_method.toUpperCase()}
                                            </span>
                                            ${transaction.transaction_reference ? `<div style="font-size:10px;color:#666;">${transaction.transaction_reference.substring(0, 12)}...</div>` : ''}
                                        </td>
                                        <td class="transaction-amount ${isVoided ? 'voided' : ''}">₱${parseFloat(transaction.total_amount).toFixed(2)}</td>
                                        <td>
                                            <span class="status-badge ${transaction.status || 'completed'}">
                                                ${(transaction.status || 'completed').toUpperCase()}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="transaction-actions">
                                                <button class="btn-action" onclick="viewTransactionDetails(${transaction.sale_id})" title="View Details">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke-width="2"/>
                                                        <circle cx="12" cy="12" r="3" stroke-width="2"/>
                                                    </svg>
                                                    Details
                                                </button>
                                                <button class="btn-action btn-void" 
                                                        onclick="voidTransaction(${transaction.sale_id}, '${transaction.sale_number}')" 
                                                        title="Void Transaction"
                                                        ${isVoided ? 'disabled' : ''}>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                                                        <line x1="15" y1="9" x2="9" y2="15" stroke-width="2"/>
                                                        <line x1="9" y1="9" x2="15" y2="15" stroke-width="2"/>
                                                    </svg>
                                                    Void
                                                </button>
                                                <button class="btn-action btn-reprint" 
                                                        onclick="reprintTransaction(${transaction.sale_id})" 
                                                        title="Reprint Receipt">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                                                        <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                                                    </svg>
                                                    Print
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        modalBody.innerHTML = html;
    }

    // Add new function to handle voiding transactions
    function voidTransaction(saleId, saleNumber) {
        const modalHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 15px; color: #1a1a2e;">Void Transaction</h3>
                
                <div style="background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #856404; font-size: 14px;">
                        ⚠️ Are you sure you want to void transaction <strong>${saleNumber}</strong>?
                    </p>
                    <p style="margin: 8px 0 0 0; color: #856404; font-size: 12px;">
                        This action cannot be undone. The transaction will be marked as voided.
                    </p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                        Reason for Void: <span style="color: #d32f2f;">*</span>
                    </label>
                    <textarea id="voidReason" 
                            style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; min-height: 100px; font-size: 14px; font-family: 'Inter', sans-serif; resize: vertical;"
                            placeholder="Please provide a reason for voiding this transaction (e.g., customer request, wrong order, system error)..."
                            autofocus></textarea>
                    <div id="voidReasonError" style="color: #d32f2f; font-size: 12px; margin-top: 5px; display: none;">
                        Please provide a reason for voiding this transaction
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="confirmVoidTransaction(${saleId})" style="flex: 1; background: #ff9800;">
                        Void Transaction
                    </button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Void Transaction', modalHTML);
        setTimeout(() => document.getElementById('voidReason').focus(), 100);
    }

    // Add function to confirm void transaction
    function confirmVoidTransaction(saleId) {
        const reason = document.getElementById('voidReason').value.trim();
        const errorDiv = document.getElementById('voidReasonError');
        
        if (!reason || reason.length < 10) {
            errorDiv.textContent = 'Please provide a detailed reason (at least 10 characters)';
            errorDiv.style.display = 'block';
            document.getElementById('voidReason').focus();
            return;
        }
        
        // Show loading state
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span style="opacity: 0.7;">Processing...</span>';
        btn.disabled = true;
        
        // Send void request to server
        fetch('void_transaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sale_id: saleId,
                void_reason: reason,
                voided_by: <?php echo $employee_id; ?>
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showFunctionFeedback('Transaction voided successfully');
                closeCustomModal();
                // Reload transaction history
                const date = document.getElementById('transactionDate')?.value || new Date().toISOString().split('T')[0];
                loadTransactionHistory(date);
            } else {
                alert('Error voiding transaction: ' + data.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Void error:', err);
            alert('Error voiding transaction. Please try again.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    // Update showTransactionDetailsModal to show void information
    function showTransactionDetailsModal(sale, items) {
        const isVoided = sale.status === 'voided';
        
        const modalHTML = `
            <div class="transaction-detail-modal">
                <div class="receipt-preview">
                    <div class="receipt-preview-header">
                        <h3>Altiere</h3>
                        <p>${sale.branch_name}</p>
                        <p>${sale.branch_address}</p>
                        <p>Receipt #: ${sale.sale_number}</p>
                        <p>Date: ${new Date(sale.sale_date).toLocaleString()}</p>
                        ${isVoided ? '<p style="color: #ff9800; font-weight: 700; margin-top: 10px;">⚠️ VOIDED TRANSACTION</p>' : ''}
                    </div>
                    
                    <div class="receipt-info-grid">
                        <div class="receipt-info-item">
                            <span class="receipt-info-label">Cashier:</span>
                            <span class="receipt-info-value">${sale.cashier_name}</span>
                        </div>
                        <div class="receipt-info-item">
                            <span class="receipt-info-label">Employee #:</span>
                            <span class="receipt-info-value">${sale.employee_number}</span>
                        </div>
                        <div class="receipt-info-item">
                            <span class="receipt-info-label">Customer:</span>
                            <span class="receipt-info-value">${sale.customer_name}</span>
                        </div>
                        <div class="receipt-info-item">
                            <span class="receipt-info-label">Payment:</span>
                            <span class="receipt-info-value">
                                <span class="payment-badge ${sale.payment_method}">${sale.payment_method.toUpperCase()}</span>
                            </span>
                        </div>
                        ${isVoided ? `
                        <div class="receipt-info-item">
                            <span class="receipt-info-label">Status:</span>
                            <span class="receipt-info-value">
                                <span class="status-badge voided">VOIDED</span>
                            </span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div class="receipt-items-list">
                        ${items.map(item => `
                            <div class="receipt-item-row">
                                <div>
                                    <div class="receipt-item-name">${item.product_name} (${item.size_display})</div>
                                    <div class="receipt-item-details">${item.quantity} × ₱${parseFloat(item.unit_price).toFixed(2)}</div>
                                </div>
                                <div class="receipt-item-total">₱${parseFloat(item.total).toFixed(2)}</div>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="receipt-totals">
                        <div class="receipt-total-row">
                            <span>Subtotal:</span>
                            <span>₱${parseFloat(sale.subtotal).toFixed(2)}</span>
                        </div>
                        <div class="receipt-total-row">
                            <span>Tax (12%):</span>
                            <span>₱${parseFloat(sale.tax).toFixed(2)}</span>
                        </div>
                        <div class="receipt-total-row">
                            <span>Discount:</span>
                            <span>₱${parseFloat(sale.discount).toFixed(2)}</span>
                        </div>
                        <div class="receipt-total-row grand-total">
                            <span>TOTAL:</span>
                            <span ${isVoided ? 'style="text-decoration: line-through; opacity: 0.6;"' : ''}>₱${parseFloat(sale.total_amount).toFixed(2)}</span>
                        </div>
                        
                        ${sale.payment_method === 'cash' && sale.cash_received ? `
                            <div class="receipt-total-row">
                                <span>Cash Received:</span>
                                <span>₱${parseFloat(sale.cash_received).toFixed(2)}</span>
                            </div>
                            <div class="receipt-total-row">
                                <span>Change:</span>
                                <span>₱${parseFloat(sale.cash_received - sale.total_amount).toFixed(2)}</span>
                            </div>
                        ` : ''}
                        
                        ${sale.transaction_reference ? `
                            <div class="receipt-total-row">
                                <span>Reference:</span>
                                <span>${sale.transaction_reference}</span>
                            </div>
                        ` : ''}
                    </div>
                    
                    ${sale.notes ? `
                        <div style="margin-top: 15px; padding: 10px; background: #f5f5f5; border-radius: 6px; font-size: 12px;">
                            <strong>Notes:</strong> ${sale.notes}
                        </div>
                    ` : ''}
                    
                    ${isVoided && sale.void_reason ? `
                        <div class="void-note">
                            <strong>Void Reason:</strong>
                            <p>${sale.void_reason}</p>
                            ${sale.voided_by_name ? `<p style="margin-top: 5px; font-size: 11px;">Voided by: ${sale.voided_by_name} on ${new Date(sale.voided_at).toLocaleString()}</p>` : ''}
                        </div>
                    ` : ''}
                    
                    <div class="receipt-footer">
                        <p>Thank you for shopping with us!</p>
                        <p>Please keep this receipt for returns/exchanges</p>
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    ${!isVoided ? `
                        <button class="btn btn-primary" onclick="printTransactionReceipt(${sale.sale_id})" style="flex: 1;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                                <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                                <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                            </svg>
                            Print Receipt
                        </button>
                    ` : '<div style="flex: 1;"></div>'}
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Close</button>
                </div>
            </div>
        `;
        
        showCustomModal('Transaction Details', modalHTML);
    }

    function filterTransactions() {
        const date = document.getElementById('transactionDate').value;
        const search = document.getElementById('transactionSearch').value;
        
        if (search) {
            // Filter client-side for now
            const rows = document.querySelectorAll('.transactions-table tbody tr');
            rows.forEach(row => {
                const receiptNum = row.querySelector('.transaction-receipt').textContent;
                const cashier = row.cells[2].textContent;
                const visible = receiptNum.includes(search) || cashier.toLowerCase().includes(search.toLowerCase());
                row.style.display = visible ? '' : 'none';
            });
        } else {
            loadTransactionHistory(date);
        }
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    function viewTransactionDetails(saleId) {
        fetch(`get_transaction_details.php?sale_id=${saleId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showTransactionDetailsModal(data.sale, data.items);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                alert('Error loading transaction details');
            });
    }

    function reprintTransaction(saleId) {
        // This would trigger the print functionality
        alert(`Reprinting receipt for transaction #${saleId}`);
        // You would typically fetch and print the receipt here
    }

    function printTransactionReceipt(saleId) {
        // Implement print functionality
        window.print();
    }

    function closeTransactionModal() {
        document.getElementById('transactionModal').style.display = 'none';
    }

    // === F5 - ADD NOTES ===
    function addNotes() {
        if (selectedItemIndex === -1 || !cart[selectedItemIndex]) {
            alert('Please add an item to the cart first.');
            return;
        }

        const item = cart[selectedItemIndex];
        
        const modalHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 15px; color: #1a1a2e;">Add Notes</h3>
                
                <p style="margin-bottom: 15px; color: #666;">
                    Adding notes for: <strong>${item.product_name}</strong>
                </p>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Notes:</label>
                    <textarea id="itemNotes" class="notes-input" 
                              placeholder="Enter notes for this item (e.g., customer requests, special instructions)..."
                              style="width: 100%; padding: 15px; border: 2px solid #e5e7eb; border-radius: 8px; min-height: 120px; font-size: 16px; resize: vertical;">${item.notes || ''}</textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="saveItemNotes()" style="flex: 1;">Save Notes</button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Add Notes', modalHTML);
        setTimeout(() => document.getElementById('itemNotes').focus(), 100);
    }

    function saveItemNotes() {
        const itemNotes = document.getElementById('itemNotes').value.trim();
        
        if (selectedItemIndex !== -1) {
            cart[selectedItemIndex].notes = itemNotes;
            renderCart();
            showFunctionFeedback('Notes saved');
        }
        
        closeCustomModal();
    }

    // === F6 - CHANGE QUANTITY ===
    function changeQuantity() {
        if (selectedItemIndex === -1 || !cart[selectedItemIndex]) {
            alert('Please add an item to the cart first.');
            return;
        }

        const item = cart[selectedItemIndex];
        
        const modalHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 15px; color: #1a1a2e;">Change Quantity</h3>
                
                <p style="margin-bottom: 15px; color: #666;">
                    Changing quantity for: <strong>${item.product_name}</strong>
                </p>
                
                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <div style="margin-bottom: 10px;">
                        <span style="color: #666;">Current Quantity:</span>
                        <span style="float: right; font-weight: 600;">${item.quantity}</span>
                    </div>
                    <div>
                        <span style="color: #666;">Available Stock:</span>
                        <span style="float: right; font-weight: 600; color: ${item.stock_quantity < 5 ? '#d32f2f' : '#2e7d32'};">${item.stock_quantity}</span>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Enter New Quantity:</label>
                    <input type="number" id="quantityInput" 
                           style="width: 100%; padding: 15px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 18px;"
                           placeholder="1" min="1" max="${item.stock_quantity}" value="${item.quantity}" autofocus>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="saveQuantity()" style="flex: 1;">Change Quantity</button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Change Quantity', modalHTML);
        setTimeout(() => document.getElementById('quantityInput').select(), 100);
    }

    function saveQuantity() {
        const newQty = parseInt(document.getElementById('quantityInput').value);
        
        if (isNaN(newQty) || newQty < 1) {
            alert('Invalid quantity. Please enter a positive number.');
            return;
        }

        if (newQty > cart[selectedItemIndex].stock_quantity) {
            alert(`Not enough stock! Only ${cart[selectedItemIndex].stock_quantity} available.`);
            return;
        }

        cart[selectedItemIndex].quantity = newQty;
        
        renderCart();
        showFunctionFeedback(`Quantity changed to ${newQty}`);
        closeCustomModal();
    }

    // === F7 - SPLIT RECEIPT ===
    function splitReceipt() {
        if (cart.length === 0) {
            alert('Please add items to the cart first.');
            return;
        }

        if (cart.length < 2) {
            alert('Need at least 2 items to split receipt.');
            return;
        }

        const modalHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 15px; color: #1a1a2e;">Split Receipt</h3>

                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 12px; color: #666;">Current Total</div>
                    <div style="font-size: 28px; font-weight: 700; color: #e91e63;">₱${cart.reduce((s,i) => s + i.final_price * i.quantity, 0).toFixed(2)}</div>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">${cart.length} item(s)</div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 10px;">Select items for Receipt 1:</label>
                    <div id="splitItemsList" style="max-height: 200px; overflow-y: auto; border: 1px solid #ffeef2; border-radius: 8px; padding: 10px; background: #fff5f7;">
                        ${cart.map((item, index) => `
                            <div style="display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #ffeef2; background: white; margin-bottom: 5px; border-radius: 6px; border: 2px solid ${index === 0 ? '#e91e63' : '#ffeef2'};">
                                <input type="checkbox" id="item-${index}" class="split-item-checkbox" style="margin-right: 10px;" ${index === 0 ? 'checked' : ''}>
                                <label for="item-${index}" style="flex: 1; cursor: pointer;">
                                    <div style="font-weight: 600; color: #e91e63;">${item.product_name}</div>
                                    <div style="font-size: 12px; color: #666;">
                                        ${item.quantity} × ₱${item.final_price.toFixed(2)} = ₱${(item.quantity * item.final_price).toFixed(2)}
                                    </div>
                                </label>
                                <div style="font-weight: 700; color: #e91e63;">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <div style="flex: 1; background: #ffeef2; padding: 15px; border-radius: 8px; text-align: center; border: 2px solid #e91e63;">
                        <div style="font-size: 12px; color: #e91e63; font-weight: 600;">Receipt 1 Total</div>
                        <div id="receipt1Total" style="font-size: 20px; font-weight: 700; color: #e91e63;">₱0.00</div>
                        <div style="font-size: 11px; color: #c2185b;" id="receipt1ItemCount">0 items</div>
                    </div>
                    <div style="flex: 1; background: #ffeef2; padding: 15px; border-radius: 8px; text-align: center; border: 2px solid #c2185b;">
                        <div style="font-size: 12px; color: #c2185b; font-weight: 600;">Receipt 2 Total</div>
                        <div id="receipt2Total" style="font-size: 20px; font-weight: 700; color: #c2185b;">₱0.00</div>
                        <div style="font-size: 11px; color: #e91e63;" id="receipt2ItemCount">0 items</div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="processSplitReceipt()" style="flex: 1;">Create Split Receipts</button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Split Receipt', modalHTML);
        
        // Initialize split totals
        updateSplitTotals();
        
        // Add event listeners to checkboxes
        setTimeout(() => {
            document.querySelectorAll('.split-item-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSplitTotals);
            });
        }, 100);
    }

    function updateSplitTotals() {
        const checkboxes = document.querySelectorAll('.split-item-checkbox');
        let receipt1Total = 0;
        let receipt1Items = 0;
        let receipt2Total = 0;
        let receipt2Items = 0;
        
        checkboxes.forEach((checkbox, index) => {
            if (checkbox.checked && cart[index]) {
                receipt1Total += cart[index].quantity * cart[index].final_price;
                receipt1Items += cart[index].quantity;
            } else if (cart[index]) {
                receipt2Total += cart[index].quantity * cart[index].final_price;
                receipt2Items += cart[index].quantity;
            }
        });
        
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        
        // Apply tax proportionally
        const receipt1Tax = (receipt1Total / subtotal) * tax || 0;
        const receipt1Discount = (receipt1Total / subtotal) * discountAmount || 0;
        const receipt1Final = receipt1Total + receipt1Tax - receipt1Discount;
        
        const receipt2Tax = (receipt2Total / subtotal) * tax || 0;
        const receipt2Discount = (receipt2Total / subtotal) * discountAmount || 0;
        const receipt2Final = receipt2Total + receipt2Tax - receipt2Discount;
        
        document.getElementById('receipt1Total').textContent = '₱' + receipt1Final.toFixed(2);
        document.getElementById('receipt2Total').textContent = '₱' + receipt2Final.toFixed(2);
        document.getElementById('receipt1ItemCount').textContent = `${receipt1Items} item${receipt1Items !== 1 ? 's' : ''}`;
        document.getElementById('receipt2ItemCount').textContent = `${receipt2Items} item${receipt2Items !== 1 ? 's' : ''}`;
    }

    function selectAllForReceipt1() {
        document.querySelectorAll('.split-item-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSplitTotals();
    }

    function moveSelectedToReceipt2() {
        document.querySelectorAll('.split-item-checkbox').forEach(checkbox => {
            if (checkbox.checked) {
                checkbox.checked = false;
            }
        });
        updateSplitTotals();
    }

    function processSplitReceipt() {
        const checkboxes = document.querySelectorAll('.split-item-checkbox');
        splitReceipt1Items = [];
        splitReceipt2Items = [];
        
        checkboxes.forEach((checkbox, index) => {
            if (checkbox.checked && cart[index]) {
                splitReceipt1Items.push({...cart[index]});
            } else if (cart[index]) {
                splitReceipt2Items.push({...cart[index]});
            }
        });
        
        if (splitReceipt1Items.length === 0 || splitReceipt2Items.length === 0) {
            alert('Please split items between both receipts. Each receipt must have at least one item.');
            return;
        }
        
        // Show confirmation
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        
        const receipt1Subtotal = splitReceipt1Items.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const receipt1Tax = (receipt1Subtotal / subtotal) * tax;
        const receipt1Discount = (receipt1Subtotal / subtotal) * discountAmount;
        const receipt1Total = receipt1Subtotal + receipt1Tax - receipt1Discount;
        
        const receipt2Subtotal = splitReceipt2Items.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const receipt2Tax = (receipt2Subtotal / subtotal) * tax;
        const receipt2Discount = (receipt2Subtotal / subtotal) * discountAmount;
        const receipt2Total = receipt2Subtotal + receipt2Tax - receipt2Discount;
        
        const confirmHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 15px; color: #1a1a2e;">Confirm Split Receipts</h3>
                
                <div style="background: #fff5f7; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 2px solid #ffeef2;">
                    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                        <div style="flex: 1; background: white; padding: 15px; border-radius: 6px; border: 2px solid #e91e63; text-align: center;">
                            <div style="font-weight: 700; color: #e91e63; margin-bottom: 10px; font-size: 14px;">Receipt 1</div>
                            <div style="font-size: 11px; color: #c2185b;">Items: ${splitReceipt1Items.length}</div>
                            <div style="font-size: 18px; font-weight: 700; color: #e91e63; margin-top: 5px;">₱${receipt1Total.toFixed(2)}</div>
                        </div>
                        <div style="flex: 1; background: white; padding: 15px; border-radius: 6px; border: 2px solid #c2185b; text-align: center;">
                            <div style="font-weight: 700; color: #c2185b; margin-bottom: 10px; font-size: 14px;">Receipt 2</div>
                            <div style="font-size: 11px; color: #e91e63;">Items: ${splitReceipt2Items.length}</div>
                            <div style="font-size: 18px; font-weight: 700; color: #c2185b; margin-top: 5px;">₱${receipt2Total.toFixed(2)}</div>
                        </div>
                    </div>
                    
                    <div style="background: #ffeef2; padding: 10px; border-radius: 6px; border-left: 4px solid #e91e63;">
                        <p style="margin: 0; color: #e91e63; font-size: 12px;">
                            💡 You will process Receipt 1 first, then Receipt 2 separately.
                        </p>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="startSplitReceipts()" style="flex: 1;">
                        Start Split Receipts
                    </button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Confirm Split', confirmHTML);
    }

    function startSplitReceipts() {
        // Hide main receipt, show split receipts
        document.getElementById('mainReceipt').style.display = 'none';
        document.getElementById('splitReceiptContainer').style.display = 'block';
        
        // Set receipt numbers
        const receiptNum = document.getElementById('receiptNumber').textContent;
        document.getElementById('splitReceipt1Number').textContent = receiptNum + '-A';
        document.getElementById('splitReceipt2Number').textContent = receiptNum + '-B';
        
        // Render split receipts
        renderSplitReceipt(1, splitReceipt1Items);
        renderSplitReceipt(2, splitReceipt2Items);
        
        splitReceiptActive = true;
        splitReceipt1Paid = false;
        splitPaymentData.receipt1 = null;
        splitPaymentData.receipt2 = null;
        
        // Disable F keys
        updateFKeyState();
        
        closeCustomModal();
        showFunctionFeedback('Split receipts created. Pay Receipt 1 first.');
    }

    function renderSplitReceipt(receiptNumber, items) {
        const container = document.getElementById(`splitReceipt${receiptNumber}Items`);
        const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        
        if (items.length === 0) {
            container.innerHTML = '<div class="empty-cart">No items</div>';
        } else {
            container.innerHTML = items.map((item, i) => `
                <div class="receipt-item">
                    <div class="item-details">
                        <div class="item-name">
                            ${item.product_name} <span class="size-tag">${item.size_name}</span>
                            ${item.price_changed ? `<span class="price-change">Price Changed</span>` : ''}
                            ${item.clerk_name !== '<?php echo htmlspecialchars($employee_name); ?>' ? `<span class="clerk-indicator">${item.clerk_name}</span>` : ''}
                        </div>
                        <div class="item-price">₱${item.final_price.toFixed(2)} × ${item.quantity}</div>
                        ${item.notes ? `<div style="font-size:11px;color:#666;margin-top:3px;background:#f5f5f5;padding:4px 8px;border-radius:4px;">📝 ${item.notes}</div>` : ''}
                        <div class="item-quantity-print">Qty: ${item.quantity}</div>
                    </div>
                    <div>
                        <div class="item-total">₱${(item.final_price * item.quantity).toFixed(2)}</div>
                    </div>
                </div>
            `).join('');
        }
        
        // Update totals
        document.getElementById(`splitReceipt${receiptNumber}Subtotal`).textContent = '₱' + subtotal.toFixed(2);
        document.getElementById(`splitReceipt${receiptNumber}Tax`).textContent = '₱' + tax.toFixed(2);
        document.getElementById(`splitReceipt${receiptNumber}Discount`).textContent = '₱' + discountAmount.toFixed(2);
        document.getElementById(`splitReceipt${receiptNumber}Total`).textContent = '₱' + total.toFixed(2);
    }

    function paySplitReceipt(receiptNumber) {
        const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
        const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        const receiptNum = document.getElementById(`splitReceipt${receiptNumber}Number`).textContent;
        
        // Use the same payment modal structure as single receipt
        document.getElementById('paymentModal').style.display = 'block';
        document.getElementById('modalTitle').textContent = `Pay Split Receipt ${receiptNumber}`;

        let html = `
            <div style="text-align:center;font-size:16px;font-weight:600;margin:10px 0;color:#666;">
                Receipt #${receiptNum}
            </div>
            <div style="text-align:center;font-size:22px;font-weight:700;margin:20px 0;color:#e91e63;">
                ₱${total.toFixed(2)}
            </div>
            <div style="text-align:center;font-size:14px;margin:10px 0;color:#666;">
                ${items.length} item${items.length !== 1 ? 's' : ''}
            </div>
            <div style="margin:20px 0;">
                <div class="payment-modes">
                    <button class="btn-payment btn-cash" onclick="openSplitPaymentMode(${receiptNumber}, 'cash')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/>
                            <line x1="2" y1="10" x2="22" y2="10" stroke-width="2"/>
                        </svg>
                        Cash
                    </button>
                    <button class="btn-payment btn-card" onclick="openSplitPaymentMode(${receiptNumber}, 'card')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/>
                            <line x1="2" y1="10" x2="22" y2="10" stroke-width="2"/>
                        </svg>
                        Card
                    </button>
                    <button class="btn-payment btn-ewallet" onclick="openSplitPaymentMode(${receiptNumber}, 'ewallet')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" stroke-width="2"/>
                            <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" stroke-width="2"/>
                            <line x1="18" y1="12" x2="18" y2="12" stroke-width="2"/>
                        </svg>
                        E-Wallet
                    </button>
                </div>
            </div>
        `;

        document.getElementById('modalBody').innerHTML = html;
    }

    function openSplitPaymentMode(receiptNumber, mode) {
        const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
        const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        const receiptNum = document.getElementById(`splitReceipt${receiptNumber}Number`).textContent;

        let html = `
            <div style="text-align:center;font-size:16px;font-weight:600;margin:10px 0;color:#666;">
                Receipt #${receiptNum}
            </div>
            <div style="text-align:center;font-size:22px;font-weight:700;margin:20px 0;color:#e91e63;">
                ₱${total.toFixed(2)}
            </div>`;

        if (mode === 'cash') {
            html += `
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">Cash Received:</label>
                    <input type="number" id="cashInput" 
                        style="font-size:20px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        placeholder="Enter amount received"
                        step="0.01"
                        autofocus>
                </div>
                <div id="changeBox" style="font-size:18px;margin:15px 0;padding:15px;background:#ffeef2;border-radius:8px;display:none;">
                    <strong>Change:</strong> ₱<span id="changeAmount">0.00</span>
                </div>
                <div id="cashError" style="color:#e91e63;margin:10px 0;display:none;"></div>
            `;
        } else if (mode === 'ewallet') {
            html += `
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">Amount Received:</label>
                    <input type="number" id="cashInput" 
                        style="font-size:20px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        value="${total.toFixed(2)}"
                        step="0.01"
                        autofocus>
                </div>
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">E-Wallet Reference Number:</label>
                    <input type="text" id="refInput" 
                        style="font-size:16px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        placeholder="Enter GCash/PayMaya/GrabPay reference number"
                        maxlength="50">
                </div>
                <p style="text-align:center;margin:20px 0;color:#666;font-size:14px;">
                    <strong>E-Wallet Payment</strong><br>
                    Verify amount and reference number before completing
                </p>
            `;
        } else if (mode === 'card') {
            html += `
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">Card Terminal Reference:</label>
                    <input type="text" id="refInput" 
                        style="font-size:16px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        placeholder="Enter card authorization/reference number"
                        maxlength="50"
                        autofocus>
                </div>
                <p style="text-align:center;margin:20px 0;color:#666;font-size:14px;">
                    <strong>Card Payment</strong><br>
                    Enter the authorization code from the card terminal
                </p>
            `;
        }

        html += `
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button onclick="processSplitReceiptPayment(${receiptNumber}, '${mode}')" class="btn btn-primary" style="flex:1;">Complete Payment</button>
                <button onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
            </div>`;

        document.getElementById('modalBody').innerHTML = html;

        if (mode === 'cash' || mode === 'ewallet') {
            setTimeout(() => {
                const cashInput = document.getElementById('cashInput');
                
                cashInput.addEventListener('input', function() {
                    const received = parseFloat(this.value) || 0;
                    const change = received - total;
                    
                    if (mode === 'cash') {
                        const changeBox = document.getElementById('changeBox');
                        const changeAmount = document.getElementById('changeAmount');
                        const errorBox = document.getElementById('cashError');
                        
                        if (received < total) {
                            changeBox.style.display = 'none';
                            errorBox.textContent = 'Amount received is less than total';
                            errorBox.style.display = 'block';
                        } else {
                            errorBox.style.display = 'none';
                            changeBox.style.display = 'block';
                            changeAmount.textContent = change.toFixed(2);
                        }
                    }
                });
                
                cashInput.focus();
                cashInput.select();
            }, 100);
        } else if (mode === 'card') {
            setTimeout(() => {
                document.getElementById('refInput').focus();
            }, 100);
        }
    }

    function processSplitReceiptPayment(receiptNumber, method) {
        const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
        const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        const receiptNum = document.getElementById(`splitReceipt${receiptNumber}Number`).textContent;
        
        let cashReceived = null;
        let transactionRef = null;
        
        if (method === 'cash' || method === 'ewallet') {
            const cashInput = document.getElementById('cashInput');
            cashReceived = parseFloat(cashInput.value) || 0;
            
            if (method === 'cash' && cashReceived < total) {
                alert('Cash received must be greater than or equal to total amount!');
                return;
            }
            
            if (cashReceived <= 0) {
                alert('Please enter a valid amount!');
                return;
            }
        }
        
        // Generate automated transaction reference for card/ewallet
        if (method === 'card' || method === 'ewallet') {
            const refInput = document.getElementById('refInput');
            transactionRef = refInput ? refInput.value.trim() : null;
            
            // If no reference entered, generate one automatically
            if (!transactionRef || transactionRef === '') {
                const timestamp = Date.now();
                const random = Math.floor(Math.random() * 10000);
                transactionRef = `${method.toUpperCase()}-SPLIT${receiptNumber}-${timestamp}-${random}`;
            }
        }
        
        const saleData = {
            branch_id: <?php echo $branch_id; ?>,
            employee_id: <?php echo $employee_id; ?>,
            items: items,
            subtotal: subtotal,
            tax: tax,
            total_amount: total,
            payment_method: method,
            discount: discountAmount,
            discount_percentage: (discountAmount / subtotal) * 100,
            cash_received: cashReceived,
            transaction_reference: transactionRef,
            receipt_number: receiptNum,
            split_receipt: true,
            split_part: receiptNumber,
            original_receipt: originalReceiptNumber
        };

        fetch('process_sale.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(saleData)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Store payment data
                splitPaymentData[`receipt${receiptNumber}`] = {
                    receipt_number: receiptNum,
                    total: total,
                    method: method,
                    transaction_ref: transactionRef
                };
                
                if (receiptNumber === 1) {
                    splitReceipt1Paid = true;
                    // Enable receipt 2 payment button
                    document.querySelector('#splitReceipt2 .split-pay-btn').disabled = false;
                    // Mark receipt 1 as paid
                    document.querySelector('#splitReceipt1 .split-pay-btn').innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                            <path d="M20 6L9 17l-5-5" stroke-width="2"/>
                        </svg>
                        PAID
                    `;
                    document.querySelector('#splitReceipt1 .split-pay-btn').disabled = true;
                    document.querySelector('#splitReceipt1 .split-pay-btn').style.background = '#00c851';
                    
                    showFunctionFeedback('Receipt 1 paid successfully! Now pay Receipt 2.');
                    closeCustomModal();
                } else {
                    // Both receipts paid
                    completeSplitReceipt();
                }
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            console.error('Split receipt payment error:', err);
            alert('Payment failed. Please try again.');
        });
    }

    function completeSplitReceipt() {
        // Mark receipt 2 as paid
        document.querySelector('#splitReceipt2 .split-pay-btn').innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                <path d="M20 6L9 17l-5-5" stroke-width="2"/>
            </svg>
            PAID
        `;
        document.querySelector('#splitReceipt2 .split-pay-btn').disabled = true;
        document.querySelector('#splitReceipt2 .split-pay-btn').style.background = '#00c851';
        
        // Show success message
        setTimeout(() => {
            alert('Both split receipts have been paid successfully! They have been recorded as two separate transactions.');
            
            // Reset to normal mode
            cancelSplitReceipt();
            
            // Fetch next receipt number
            fetch('get_next_receipt_number.php')
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        receiptCounter = parseInt(res.next_receipt_number);
                        document.getElementById('receiptNumber').textContent = res.next_receipt_number;
                        originalReceiptNumber = res.next_receipt_number;
                    }
                });
        }, 500);
    }

    function cancelSplitReceipt() {
        // Show confirmation
        if (confirm('Cancel split receipt? All items will be returned to the main cart.')) {
            // Hide split receipts, show main receipt
            document.getElementById('splitReceiptContainer').style.display = 'none';
            document.getElementById('mainReceipt').style.display = 'block';
            
            // Reset split receipt data
            splitReceiptActive = false;
            splitReceipt1Items = [];
            splitReceipt2Items = [];
            splitReceipt1Paid = false;
            splitPaymentData.receipt1 = null;
            splitPaymentData.receipt2 = null;
            
            // Enable F keys
            updateFKeyState();
            
            showFunctionFeedback('Split receipt cancelled');
        }
    }

    function printSplitReceipts() {
        if (!splitReceipt1Paid) {
            alert('Please pay Receipt 1 before printing.');
            return;
        }
        
        // Create a print window with both receipts
        const printWindow = window.open('', '_blank');
        const receipt1Num = document.getElementById('splitReceipt1Number').textContent;
        const receipt2Num = document.getElementById('splitReceipt2Number').textContent;
        const receipt1Total = document.getElementById('splitReceipt1Total').textContent;
        const receipt2Total = document.getElementById('splitReceipt2Total').textContent;
        const date = new Date().toLocaleString();
        const employeeName = '<?php echo htmlspecialchars($employee_name); ?>';
        
        printWindow.document.write(`
            <html>
            <head>
                <title>Split Receipts</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .receipt { border: 1px solid #000; padding: 20px; margin-bottom: 20px; width: 80mm; }
                    .header { text-align: center; margin-bottom: 15px; }
                    .header h2 { margin: 0; font-size: 18px; }
                    .header p { margin: 4px 0; font-size: 12px; color: #666; }
                    .items { margin: 15px 0; }
                    .item { display: flex; justify-content: space-between; margin: 5px 0; font-size: 13px; }
                    .item-details { flex: 1; }
                    .item-total { font-weight: bold; }
                    .summary { border-top: 1px dashed #000; margin-top: 10px; padding-top: 10px; }
                    .summary-row { display: flex; justify-content: space-between; margin: 5px 0; }
                    .total { font-weight: bold; border-top: 2px solid #000; padding-top: 10px; margin-top: 10px; }
                    .split-divider { text-align: center; margin: 20px 0; color: #666; font-weight: bold; }
                    .footer { text-align: center; margin-top: 15px; font-size: 11px; color: #666; border-top: 1px dashed #000; padding-top: 10px; }
                </style>
            </head>
            <body>
                <div class="receipt">
                    <div class="header">
                        <h2>Altiere</h2>
                        <p><?php echo htmlspecialchars($branch_name); ?></p>
                        <p>Receipt #: ${receipt1Num}</p>
                        <p>Date: ${date}</p>
                        <p style="color: #e91e63; font-weight: bold;">SPLIT RECEIPT 1</p>
                    </div>
                    <div class="items">
                        ${splitReceipt1Items.map(item => `
                            <div class="item">
                                <div class="item-details">
                                    <div>${item.product_name} (${item.size_name})</div>
                                    <div style="font-size: 11px;">${item.quantity} × ₱${item.final_price.toFixed(2)}</div>
                                    ${item.notes ? `<div style="font-size: 10px; color: #666;">${item.notes}</div>` : ''}
                                </div>
                                <div class="item-total">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                            </div>
                        `).join('')}
                    </div>
                    <div class="summary">
                        <div class="summary-row"><span>Subtotal:</span><span>₱${splitReceipt1Items.reduce((s,i) => s + i.final_price * i.quantity, 0).toFixed(2)}</span></div>
                        <div class="summary-row"><span>Tax (12%):</span><span>${document.getElementById('splitReceipt1Tax').textContent}</span></div>
                        <div class="summary-row"><span>Discount:</span><span>${document.getElementById('splitReceipt1Discount').textContent}</span></div>
                        <div class="summary-row total"><span>TOTAL:</span><span>${receipt1Total}</span></div>
                    </div>
                    <div class="footer">
                        <p>Cashier: ${employeeName}</p>
                        <p>Thank you for shopping with us!</p>
                    </div>
                </div>
                
                <div class="split-divider">--- SPLIT TRANSACTION ---</div>
                
                <div class="receipt">
                    <div class="header">
                        <h2>Altiere</h2>
                        <p><?php echo htmlspecialchars($branch_name); ?></p>
                        <p>Receipt #: ${receipt2Num}</p>
                        <p>Date: ${date}</p>
                        <p style="color: #c2185b; font-weight: bold;">SPLIT RECEIPT 2</p>
                    </div>
                    <div class="items">
                        ${splitReceipt2Items.map(item => `
                            <div class="item">
                                <div class="item-details">
                                    <div>${item.product_name} (${item.size_name})</div>
                                    <div style="font-size: 11px;">${item.quantity} × ₱${item.final_price.toFixed(2)}</div>
                                    ${item.notes ? `<div style="font-size: 10px; color: #666;">${item.notes}</div>` : ''}
                                </div>
                                <div class="item-total">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                            </div>
                        `).join('')}
                    </div>
                    <div class="summary">
                        <div class="summary-row"><span>Subtotal:</span><span>₱${splitReceipt2Items.reduce((s,i) => s + i.final_price * i.quantity, 0).toFixed(2)}</span></div>
                        <div class="summary-row"><span>Tax (12%):</span><span>${document.getElementById('splitReceipt2Tax').textContent}</span></div>
                        <div class="summary-row"><span>Discount:</span><span>${document.getElementById('splitReceipt2Discount').textContent}</span></div>
                        <div class="summary-row total"><span>TOTAL:</span><span>${receipt2Total}</span></div>
                    </div>
                    <div class="footer">
                        <p>Cashier: ${employeeName}</p>
                        <p>Thank you for shopping with us!</p>
                    </div>
                </div>
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }

    // === F8 - DELETE ITEM ===
    function deleteSelectedItem() {
        if (selectedItemIndex === -1 || !cart[selectedItemIndex]) {
            alert('Please add an item to the cart first.');
            return;
        }

        const item = cart[selectedItemIndex];
        
        const modalHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 15px; color: #1a1a2e;">Delete Item</h3>
                
                <div style="background: #fff5f7; border-left: 4px solid #e91e63; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #e91e63; font-size: 14px;">
                        ⚠️ Are you sure you want to delete this item from the cart?
                    </p>
                </div>
                
                <div style="background: #ffeef2; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <div style="font-weight: 600; margin-bottom: 10px; color: #e91e63;">${item.product_name}</div>
                    <div style="color: #c2185b;">
                        <span>Size: ${item.size_name}</span> • 
                        <span>Quantity: ${item.quantity}</span> • 
                        <span>Price: ₱${item.final_price.toFixed(2)}</span>
                    </div>
                    <div style="margin-top: 10px; font-weight: 600; color: #e91e63;">
                        Total: ₱${(item.final_price * item.quantity).toFixed(2)}
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="confirmDelete()" style="flex: 1; background: #e91e63;">Delete Item</button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Delete Item', modalHTML);
    }

    function confirmDelete() {
        cart.splice(selectedItemIndex, 1);
        if (cart.length > 0) {
            selectedItemIndex = cart.length - 1;
        } else {
            selectedItemIndex = -1;
        }
        renderCart();
        showFunctionFeedback('Item deleted from cart');
        closeCustomModal();
    }

    // === F4 - CHANGE PRICE ===
    function changePrice() {
        if (selectedItemIndex === -1 || !cart[selectedItemIndex]) {
            alert('Please add an item to the cart first.');
            return;
        }

        const item = cart[selectedItemIndex];
        
        const modalHTML = `
            <div style="padding: 20px;">
                <h3 style="margin-bottom: 15px; color: #1a1a2e;">Change Price</h3>
                
                <p style="margin-bottom: 15px; color: #666;">
                    Changing price for: <strong>${item.product_name}</strong>
                </p>
                
                <div style="background: #ffeef2; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <div style="margin-bottom: 10px;">
                        <span style="color: #c2185b;">Original Price:</span>
                        <span style="float: right; font-weight: 600; color: #c2185b;">₱${item.original_price.toFixed(2)}</span>
                    </div>
                    <div>
                        <span style="color: #e91e63;">Current Price:</span>
                        <span style="float: right; font-weight: 600; color: #e91e63;">₱${item.final_price.toFixed(2)}</span>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 8px;">Enter New Price:</label>
                    <input type="number" id="priceInput" 
                           style="width: 100%; padding: 15px; border: 2px solid #ffeef2; border-radius: 8px; font-size: 18px;"
                           placeholder="0.00" min="0" step="0.01" value="${item.final_price.toFixed(2)}" autofocus>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="savePrice()" style="flex: 1;">Change Price</button>
                    <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
                </div>
            </div>
        `;
        
        showCustomModal('Change Price', modalHTML);
        setTimeout(() => document.getElementById('priceInput').select(), 100);
    }

    function savePrice() {
        const newPrice = parseFloat(document.getElementById('priceInput').value);
        
        if (isNaN(newPrice) || newPrice < 0) {
            alert('Invalid price. Please enter a valid amount.');
            return;
        }

        cart[selectedItemIndex].final_price = newPrice;
        cart[selectedItemIndex].discount = 0;
        cart[selectedItemIndex].price_changed = true;
        
        renderCart();
        showFunctionFeedback(`Price changed to ₱${newPrice.toFixed(2)}`);
        closeCustomModal();
    }

    // === ESC - LOGOUT ===
    function logoutCashier() {
        if (cart.length > 0) {
            if (!confirm('You have items in the cart. Are you sure you want to logout?')) {
                return;
            }
        }
        
        if (confirm('Logout from POS system?')) {
            window.location.href = 'index.php';
        }
    }

    // === HELPER FUNCTIONS ===
    function showCustomModal(title, content) {
        const modalHTML = `
            <div id="customModal" class="modal" style="display: block;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h2>${title}</h2>
                        <span class="modal-close" onclick="closeCustomModal()">×</span>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        `;
        
        const existing = document.getElementById('customModal');
        if (existing) existing.remove();
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function closeCustomModal() {
        const modal = document.getElementById('customModal');
        if (modal) modal.remove();
    }

    // === PAYMENT MODAL FUNCTIONS ===
    function openPaymentModal(mode) {
        if (splitReceiptActive) {
            alert('Please complete the split receipt payments first.');
            return;
        }
        
        if (cart.length === 0) return alert('Cart is empty');

        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        const receiptNum = document.getElementById('receiptNumber').textContent;
        
        document.getElementById('paymentModal').style.display = 'block';

        let html = `
            <div style="text-align:center;font-size:16px;font-weight:600;margin:10px 0;color:#666;">
                Receipt #${receiptNum}
            </div>
            <div style="text-align:center;font-size:22px;font-weight:700;margin:20px 0;color:#e91e63;">
                ₱${total.toFixed(2)}
            </div>`;

        if (mode === 'cash') {
            html += `
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">Cash Received:</label>
                    <input type="number" id="cashInput" 
                        style="font-size:20px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        placeholder="Enter amount received"
                        step="0.01"
                        autofocus>
                </div>
                <div id="changeBox" style="font-size:18px;margin:15px 0;padding:15px;background:#ffeef2;border-radius:8px;display:none;">
                    <strong>Change:</strong> ₱<span id="changeAmount">0.00</span>
                </div>
                <div id="cashError" style="color:#e91e63;margin:10px 0;display:none;"></div>
            `;
        } else if (mode === 'ewallet') {
            html += `
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">Amount Received:</label>
                    <input type="number" id="cashInput" 
                        style="font-size:20px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        value="${total.toFixed(2)}"
                        step="0.01"
                        autofocus>
                </div>
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">E-Wallet Reference Number:</label>
                    <input type="text" id="refInput" 
                        style="font-size:16px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        placeholder="Enter GCash/PayMaya/GrabPay reference number"
                        maxlength="50">
                </div>
                <p style="text-align:center;margin:20px 0;color:#666;font-size:14px;">
                    <strong>E-Wallet Payment</strong><br>
                    Verify amount and reference number before completing
                </p>
            `;
        } else if (mode === 'card') {
            html += `
                <div style="margin:20px 0;">
                    <label style="font-weight:600;">Card Terminal Reference:</label>
                    <input type="text" id="refInput" 
                        style="font-size:16px;padding:10px;width:100%;margin-top:8px;border:2px solid #ffeef2;border-radius:8px;" 
                        placeholder="Enter card authorization/reference number"
                        maxlength="50"
                        autofocus>
                </div>
                <p style="text-align:center;margin:20px 0;color:#666;font-size:14px;">
                    <strong>Card Payment</strong><br>
                    Enter the authorization code from the card terminal
                </p>
            `;
        }

        html += `
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button onclick="processPayment('${mode}')" class="btn btn-primary" style="flex:1;">Complete Payment</button>
                <button onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
            </div>`;

        document.getElementById('modalBody').innerHTML = html;

        if (mode === 'cash' || mode === 'ewallet') {
            const cashInput = document.getElementById('cashInput');
            
            cashInput.addEventListener('input', function() {
                const received = parseFloat(this.value) || 0;
                const change = received - total;
                
                if (mode === 'cash') {
                    const changeBox = document.getElementById('changeBox');
                    const changeAmount = document.getElementById('changeAmount');
                    const errorBox = document.getElementById('cashError');
                    
                    if (received < total) {
                        changeBox.style.display = 'none';
                        errorBox.textContent = 'Amount received is less than total';
                        errorBox.style.display = 'block';
                    } else {
                        errorBox.style.display = 'none';
                        changeBox.style.display = 'block';
                        changeAmount.textContent = change.toFixed(2);
                    }
                }
            });
            
            setTimeout(() => {
                cashInput.focus();
                cashInput.select();
            }, 100);
        } else if (mode === 'card') {
            setTimeout(() => {
                document.getElementById('refInput').focus();
            }, 100);
        }
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }

    function processPayment(method) {
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        const receiptNum = document.getElementById('receiptNumber').textContent;
        let cashReceived = null;
        let transactionRef = null;
        
        if (method === 'cash' || method === 'ewallet') {
            const cashInput = document.getElementById('cashInput');
            cashReceived = parseFloat(cashInput.value) || 0;
            
            if (method === 'cash' && cashReceived < total) {
                alert('Cash received must be greater than or equal to total amount!');
                return;
            }
            
            if (cashReceived <= 0) {
                alert('Please enter a valid amount!');
                return;
            }
        }
        
        // Generate automated transaction reference for card/ewallet
        if (method === 'card' || method === 'ewallet') {
            const refInput = document.getElementById('refInput');
            transactionRef = refInput ? refInput.value.trim() : null;
            
            // If no reference entered, generate one automatically
            if (!transactionRef || transactionRef === '') {
                const timestamp = Date.now();
                const random = Math.floor(Math.random() * 10000);
                transactionRef = `${method.toUpperCase()}-${timestamp}-${random}`;
            }
        }
        
        const saleData = {
            branch_id: <?php echo $branch_id; ?>,
            employee_id: <?php echo $employee_id; ?>,
            items: cart,
            subtotal: subtotal,
            tax: tax,
            total_amount: total,
            payment_method: method,
            discount: discountAmount,
            discount_percentage: globalDiscount,
            cash_received: cashReceived,
            transaction_reference: transactionRef,
            receipt_number: receiptNum
        };

        fetch('process_sale.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(saleData)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                let message = `Sale completed!\nReceipt #${res.receipt_number}`;
                
                if (method === 'cash' && cashReceived) {
                    const change = cashReceived - total;
                    if (change > 0) {
                        message += `\n\nChange: ₱${change.toFixed(2)}`;
                    }
                }
                
                if (transactionRef) {
                    message += `\n\nReference: ${transactionRef}`;
                }
                
                alert(message);
                
                cart = [];
                selectedItemIndex = -1;
                globalDiscount = 0;
                renderCart();
                
                // Fetch next receipt number from database
                fetch('get_next_receipt_number.php')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            receiptCounter = parseInt(data.next_receipt_number);
                            document.getElementById('receiptNumber').textContent = data.next_receipt_number;
                            originalReceiptNumber = data.next_receipt_number;
                        }
                    });
                
                closePaymentModal();
                searchInput.focus();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            console.error('Payment error:', err);
            alert('Payment failed. Please try again.');
        });
    }

    function printReceipt() {
        if (cart.length === 0) {
            alert('Cart is empty. Nothing to print.');
            return;
        }
        window.print();
    }

    window.onload = () => {
        searchInput.focus();
        updateFKeyState();
    };
</script>
</body>
</html>