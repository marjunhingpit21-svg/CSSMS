<?php
session_start();

// UNCOMMENT WHEN LOGIN IS READY
// if (!isset($_SESSION['employee_id'])) { header('Location: login.php'); exit(); }

require_once '../database/db.php';

$employee_id   = $_SESSION['employee_id'] ?? 1;
$employee_name = $_SESSION['employee_name'] ?? 'Cashier';
$branch_name   = $_SESSION['branch_name'] ?? 'Cebu Main';
$branch_id     = $_SESSION['branch_id'] ?? 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendyWear POS</title>
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

            <!-- Old preview (kept for visual only) -->
            <div id="productPreview" class="product-preview" style="display:none;"></div>

        </div>

        <!-- Numpad -->
        <div class="numpad-container">
            <div class="numpad-grid">
                <!-- ROW 1 -->
                <button class="numpad-btn fn-btn" onclick="applyDiscount()" title="Apply Discount">
                    <span class="fn-key">F2</span>
                    <span class="fn-label">Discount</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="viewTransactions()" title="View Transactions">
                    <span class="fn-key">F3</span>
                    <span class="fn-label">Transactions</span>
                </button>
                <button class="numpad-btn" onclick="appendNumber('7')">7</button>
                <button class="numpad-btn" onclick="appendNumber('8')">8</button>
                <button class="numpad-btn" onclick="appendNumber('9')">9</button>

                <!-- ROW 2 -->
                <button class="numpad-btn fn-btn" onclick="skuLookup()" title="SKU Lookup">
                    <span class="fn-key">F5</span>
                    <span class="fn-label">SKU Lookup</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="changePrice()" title="Change Price">
                    <span class="fn-key">F7</span>
                    <span class="fn-label">Change Price</span>
                </button>
                <button class="numpad-btn" onclick="appendNumber('4')">4</button>
                <button class="numpad-btn" onclick="appendNumber('5')">5</button>
                <button class="numpad-btn" onclick="appendNumber('6')">6</button>

                <!-- ROW 3 -->
                <button class="numpad-btn fn-btn" onclick="changeTax()" title="Change Tax">
                    <span class="fn-key">F8</span>
                    <span class="fn-label">Change Tax</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="changeQuantity()" title="Change Quantity">
                    <span class="fn-key">F9</span>
                    <span class="fn-label">Change Qty</span>
                </button>
                <button class="numpad-btn" onclick="appendNumber('1')">1</button>
                <button class="numpad-btn" onclick="appendNumber('2')">2</button>
                <button class="numpad-btn" onclick="appendNumber('3')">3</button>

                <!-- ROW 4 -->
                <button class="numpad-btn fn-btn" onclick="changeClerk()" title="Change Clerk">
                    <span class="fn-key">F10</span>
                    <span class="fn-label">Change Clerk</span>
                </button>
                <button class="numpad-btn fn-btn" onclick="deleteSelectedItem()" title="Delete Item">
                    <span class="fn-key">F11</span>
                    <span class="fn-label">Delete Item</span>
                </button>
                <button class="numpad-btn numpad-clear" onclick="clearNumber()">C</button>
                <button class="numpad-btn" onclick="appendNumber('0')">0</button>
                <button class="numpad-btn numpad-backspace" onclick="backspaceNumber()">×</button>
                <!-- ROW 6 - Logout Button (Bottom, Full Width) -->
                <button class="numpad-btn fn-btn fn-logout" onclick="logoutCashier()" title="Logout">
                    <span class="fn-key">ESC</span>
                    <span class="fn-label">Logout</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Right: Receipt -->
    <div class="receipt-panel">
        <div class="receipt-header">
            <h2>TrendyWear</h2>
            <p><?php echo htmlspecialchars($branch_name); ?></p>
            <p style="font-size:11px;margin-top:4px;">Receipt #<span id="receiptNumber">00001</span></p>
            <p style="font-size:11px;" id="receiptDate"></p>
        </div>

        <div class="receipt-items" id="receiptItems">
            <div class="empty-cart">Cart is empty</div>
        </div>

       <div class="receipt-summary">
            <div class="summary-row"><span>Subtotal:</span><span id="subtotal">₱0.00</span></div>
            <div class="summary-row"><span>Discount:</span><span id="discount">₱0.00</span></div>
            <div class="summary-row total"><span>TOTAL:</span><span id="total">₱0.00</span></div>
        </div>

        <div class="receipt-actions">
            <div class="payment-modes">
                <button class="btn-payment btn-cash" onclick="openPaymentModal('cash')">Cash</button>
                <button class="btn-payment btn-card" onclick="openPaymentModal('card')">Card</button>
                <button class="btn-payment btn-ewallet" onclick="openPaymentModal('ewallet')">E-Wallet</button>
            </div>
            <button class="btn btn-secondary" onclick="clearCart()">Clear Cart</button>
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

<script>
    let cart = [];
    let receiptCounter = 1;
    let numpadValue = '1';

    // Init
    document.getElementById('receiptDate').textContent = new Date().toLocaleString();
    document.getElementById('receiptNumber').textContent = String(receiptCounter).padStart(5,'0');

    // === SEARCH LOGIC ===
    let searchTimeout;
    let allProducts = []; // Store search results
    let barcodeBuffer = '';
    let barcodeTimeout;
    const searchInput = document.getElementById('searchInput');
    const resultsBox = document.getElementById('searchResults');

    // === BARCODE SCANNER DETECTION ===
    // === BARCODE SCANNER DETECTION ===
        document.addEventListener('keypress', function(e) {
            // ALWAYS ignore if search input is focused - let user type normally
            if (document.activeElement === searchInput) return;
            
            // Build barcode buffer
            clearTimeout(barcodeTimeout);
            barcodeBuffer += e.key;
            
            // After 200ms of no input, process as barcode scan
            barcodeTimeout = setTimeout(() => {
                if (barcodeBuffer.length >= 8) { // Barcodes are usually longer
                    processBarcodeScan(barcodeBuffer);
                }
                barcodeBuffer = '';
            }, 200); // Increased from 100ms to 200ms
        });

        function processBarcodeScan(barcode) {
            console.log('Barcode scanned:', barcode); // Debug log
            fetch(`search_products.php?q=${encodeURIComponent(barcode)}`)
                .then(r => r.json())
                .then(products => {
                    if (products.length > 0) {
                        // Auto-add first match
                        addProductFromSearch(products[0]);
                    } else {
                        alert('Product not found: ' + barcode);
                    }
                })
                .catch(err => {
                    console.error('Barcode scan error:', err);
                    alert('Error scanning barcode');
                });
        }
    searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();

    if (q.length < 1) {
        resultsBox.style.display = 'none';
        allProducts = [];
        return;
    }

    searchTimeout = setTimeout(() => {
        fetch(`search_products.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(products => {
                allProducts = products;
                
                if (products.length === 0) {
                    resultsBox.innerHTML = '<div class="search-item">No product found</div>';
                    resultsBox.style.display = 'block';
                    return;
                }

                // Always show results - never auto-add
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

    // Keyboard navigation
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

    // Click outside → hide results
    document.addEventListener('click', e => {
        if (!e.target.closest('.barcode-display')) {
            resultsBox.style.display = 'none';
        }
    });

    function addProductFromSearch(product) {
        const qty = parseInt(numpadValue) || 1;
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
        } else {
            cart.push({
                product_size_id: product.product_size_id,
                product_name: product.product_name,
                size_name: product.size_name,
                final_price: parseFloat(product.final_price),
                stock_quantity: product.stock_quantity,
                quantity: qty
            });
        }

        renderCart();
        searchInput.value = '';
        resultsBox.style.display = 'none';
        allProducts = [];
        searchInput.focus();
    }

    function triggerSearch() {
        searchInput.dispatchEvent(new Event('input'));
    }

    // === CART RENDERING ===
    function renderCart() {
        const container = document.getElementById('receiptItems');
        if (cart.length === 0) {
            container.innerHTML = '<div class="empty-cart"><p>Cart is empty</p></div>';
            updateTotals();
            return;
        }

        container.innerHTML = cart.map((item, i) => `
            <div class="receipt-item">
                <div class="item-details">
                    <div class="item-name">
                        ${item.product_name} <span class="size-tag">${item.size_name}</span>
                    </div>
                    <div class="item-price">₱${item.final_price.toFixed(2)} × ${item.quantity}</div>
                </div>
                <div>
                    <div class="item-total">₱${(item.final_price * item.quantity).toFixed(2)}</div>
                    <span class="remove-btn no-print" onclick="cart.splice(${i},1); renderCart()">×</span>
                </div>
            </div>
        `).join('');

        updateTotals();
    }

   function updateTotals() {
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const total = subtotal; // No tax added

        document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
        document.getElementById('discount').textContent = '₱0.00';
        document.getElementById('total').textContent = '₱' + total.toFixed(2);
    }

    function clearCart() {
        if (cart.length && confirm('Clear cart?')) {
            cart = [];
            renderCart();
        }
    }

    // === NUMPAD ===
    function appendNumber(n) {
            // Add to search input
            searchInput.value += n;
            searchInput.focus();
            searchInput.dispatchEvent(new Event('input'));
        }
    function clearNumber() {
            // Clear search input
            searchInput.value = '';
            searchInput.focus();
            resultsBox.style.display = 'none';
        }
   function backspaceNumber() {
            // Backspace in search input
            searchInput.value = searchInput.value.slice(0, -1);
            searchInput.focus();
            searchInput.dispatchEvent(new Event('input'));
        }

    // === PAYMENT ===
    function openPaymentModal(mode) {
        if (cart.length === 0) return alert('Cart is empty');

        const total = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        document.getElementById('paymentModal').style.display = 'block';

        let html = `<div style="text-align:center;font-size:22px;font-weight:700;margin:20px 0;color:#1976d2;">
                        ₱${total.toFixed(2)}
                    </div>`;

        if (mode === 'cash') {
            html += `
                <div style="margin:20px 0;">
                    <label>Cash Received:</label>
                    <input type="number" id="cashInput" style="font-size:20px;padding:10px;width:100%;margin-top:8px;" autofocus>
                </div>
                <div id="changeBox" style="font-size:18px;margin:15px 0;display:none;">
                    Change: ₱<span id="changeAmount">0.00</span>
                </div>
            `;
        } else {
            html += `<p style="text-align:center;margin:30px 0;">Processing ${mode.toUpperCase()} payment...</p>`;
        }

        html += `
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button onclick="processPayment('${mode}')" class="btn btn-primary" style="flex:1;">Complete</button>
                <button onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
            </div>`;

        document.getElementById('modalBody').innerHTML = html;

        if (mode === 'cash') {
            document.getElementById('cashInput').addEventListener('input', function() {
                const change = this.value - total;
                const box = document.getElementById('changeBox');
                const amt = document.getElementById('changeAmount');
                if (change >= 0) {
                    box.style.display = 'block';
                    amt.textContent = change.toFixed(2);
                } else box.style.display = 'none';
            });
        }
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }

    function processPayment(method) {
        const saleData = {
            branch_id: <?php echo $branch_id; ?>,
            employee_id: <?php echo $employee_id; ?>,
            items: cart,
            subtotal: cart.reduce((s,i)=>s + i.final_price*i.quantity, 0),
            tax: 0, // No tax
            total_amount: cart.reduce((s,i)=>s + i.final_price*i.quantity, 0),
            payment_method: method,
            discount: 0
        };

        fetch('process_sale.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(saleData)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Sale completed! Receipt #' + res.sale_id);
                cart = [];
                renderCart();
                receiptCounter++;
                document.getElementById('receiptNumber').textContent = String(receiptCounter).padStart(5,'0');
                closePaymentModal();
                searchInput.focus();
            } else {
                alert('Error: ' + res.message);
            }
        });
    }

    // Focus on load
    window.onload = () => searchInput.focus();
</script>
</body>
</html>