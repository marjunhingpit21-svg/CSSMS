// Payment Functions

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
    
    if (method === 'card' || method === 'ewallet') {
        const refInput = document.getElementById('refInput');
        transactionRef = refInput ? refInput.value.trim() : null;
        
        if (!transactionRef || transactionRef === '') {
            const timestamp = Date.now();
            const random = Math.floor(Math.random() * 10000);
            transactionRef = `${method.toUpperCase()}-${timestamp}-${random}`;
        }
    }
    
    const saleData = {
        employee_id: EMPLOYEE_ID,
        items: cart,
        subtotal: subtotal,
        tax: tax,
        total_amount: total,
        payment_method: method,
        discount: discountAmount,
        discount_percentage: globalDiscount,
        discount_type: globalDiscountType,
        discount_id_number: globalDiscountIdNumber,
        cash_received: cashReceived,
        transaction_reference: transactionRef,
        receipt_number: receiptNum
    };

    console.log('Processing payment:', saleData);

    // Disable the button to prevent double-clicks
    const completeBtn = document.querySelector('#paymentModal .btn-primary');
    const originalBtnText = completeBtn ? completeBtn.innerHTML : '';
    if (completeBtn) {
        completeBtn.disabled = true;
        completeBtn.innerHTML = 'Processing...';
    }

    fetch('process_sale.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(saleData)
    })
    .then(r => r.json())
    .then(res => {
        console.log('Payment response:', res);
        
        if (res.success) {
            showFunctionFeedback('Payment successful!');
            
            // Save cart items for receipt preview BEFORE clearing
            localStorage.setItem('lastCartItems', JSON.stringify(cart));
            
            // Store payment data for receipt preview
            window.mainReceiptPaymentData = {
                receipt_number: receiptNum,
                total: total,
                method: method,
                transaction_ref: transactionRef,
                cash_received: cashReceived
            };
            
            // Show receipt preview instead of immediately printing
            setTimeout(() => {
                showMainReceiptPrintPreview();
            }, 100);
            
            // Clear cart and reset
            cart = [];
            selectedItemIndex = -1;
            globalDiscount = 0;
            globalDiscountType = '';
            globalDiscountIdNumber = '';
            renderCart();
            
            // Get new receipt number
            fetch('get_next_receipt_number.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        receiptCounter = parseInt(data.next_receipt_number);
                        document.getElementById('receiptNumber').textContent = data.next_receipt_number;
                        originalReceiptNumber = data.next_receipt_number;
                    }
                })
                .catch(err => console.error('Error getting receipt number:', err));
            
            // ✅ REFRESH TRANSACTION HISTORY IF MODAL IS OPEN
            const transactionModal = document.getElementById('transactionModal');
            if (transactionModal && transactionModal.style.display === 'block') {
                console.log('Refreshing transaction history...');
                const currentDate = document.getElementById('transactionDate')?.value || new Date().toISOString().split('T')[0];
                loadTransactionHistory(currentDate);
            }
            
            closePaymentModal();
            
        } else {
            // Re-enable button on error
            if (completeBtn) {
                completeBtn.disabled = false;
                completeBtn.innerHTML = originalBtnText;
            }
            alert('Error: ' + res.message);
        }
    })
    .catch(err => {
        console.error('Payment error:', err);
        // Re-enable button on error
        if (completeBtn) {
            completeBtn.disabled = false;
            completeBtn.innerHTML = originalBtnText;
        }
        alert('Payment failed. Please try again.');
    });
}

// NEW FUNCTION: Show receipt preview for main receipt
function showMainReceiptPrintPreview() {
    if (!window.mainReceiptPaymentData) {
        alert('No receipt data available.');
        return;
    }
    
    const receiptNum = window.mainReceiptPaymentData.receipt_number;
    const date = new Date().toLocaleString();
    const employeeName = EMPLOYEE_NAME;
    
    // Get last cart items from localStorage
    const lastCartItems = JSON.parse(localStorage.getItem('lastCartItems') || '[]');
    const paymentData = window.mainReceiptPaymentData;
    
    // Calculate totals from last cart items
    const subtotal = lastCartItems.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const total = subtotal + tax - discountAmount;
    
    const printPreviewHTML = `
        <div style="padding: 20px;">
            <h3 style="margin-bottom: 15px; color: #1a1a2e;">Print Preview - Main Receipt</h3>
            
            <div style="background: white; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; font-family: monospace;">
                <div style="text-align: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; color: #e91e63;">Altiere</h4>
                    <div style="font-size: 12px; color: #666;">Receipt #: ${receiptNum}</div>
                    <div style="font-size: 12px; color: #666;">Date: ${date}</div>
                    ${paymentData ? `<div style="font-size: 12px; color: #666;">Payment: ${paymentData.method.toUpperCase()}</div>` : ''}
                </div>
                <hr style="border: none; border-top: 1px dashed #ccc; margin: 10px 0;">
                <div style="margin-bottom: 10px;">
                    ${lastCartItems.map(item => `
                        <div style="display: flex; justify-content: space-between; margin: 5px 0; font-size: 13px;">
                            <div style="flex: 1;">
                                <div>${item.product_name} (${item.size_name})</div>
                                <div style="font-size: 11px;">${item.quantity} × ₱${item.final_price.toFixed(2)}</div>
                                ${item.notes ? `<div style="font-size: 10px; color: #666;">${item.notes}</div>` : ''}
                            </div>
                            <div style="font-weight: bold;">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                        </div>
                    `).join('')}
                </div>
                <hr style="border: none; border-top: 1px dashed #ccc; margin: 10px 0;">
                <div style="font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; margin: 3px 0;">
                        <span>Subtotal:</span>
                        <span>₱${subtotal.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 3px 0;">
                        <span>Tax (12%):</span>
                        <span>₱${tax.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 3px 0;">
                        <span>Discount:</span>
                        <span>₱${discountAmount.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 8px 0; font-weight: bold; border-top: 1px solid #000; padding-top: 5px;">
                        <span>TOTAL:</span>
                        <span>₱${total.toFixed(2)}</span>
                    </div>
                    ${paymentData && paymentData.method === 'cash' ? `
                        <div style="display: flex; justify-content: space-between; margin: 3px 0;">
                            <span>Cash Received:</span>
                            <span>₱${paymentData.cash_received.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 3px 0;">
                            <span>Change:</span>
                            <span>₱${(paymentData.cash_received - total).toFixed(2)}</span>
                        </div>
                    ` : ''}
                    ${paymentData && paymentData.transaction_ref ? `
                        <div style="display: flex; justify-content: space-between; margin: 3px 0; font-size: 11px; color: #666;">
                            <span>Reference:</span>
                            <span>${paymentData.transaction_ref}</span>
                        </div>
                    ` : ''}
                </div>
                <hr style="border: none; border-top: 1px dashed #ccc; margin: 10px 0;">
                <div style="text-align: center; font-size: 11px; color: #666;">
                    <div>Cashier: ${employeeName}</div>
                    <div>Thank you for shopping with us!</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" onclick="printReceipt()" style="flex: 1;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right:5px;">
                        <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                        <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                    </svg>
                    Print Receipt
                </button>
                <button class="btn btn-secondary" onclick="closeCustomModal(); if(searchInput) searchInput.focus();">Print Later</button>
            </div>
        </div>
    `;
    
    showCustomModal('Print Preview', printPreviewHTML);
}

// Updated printReceipt function to work with preview
function printReceipt() {
    const receiptNum = window.mainReceiptPaymentData ? window.mainReceiptPaymentData.receipt_number : document.getElementById('receiptNumber').textContent;
    const date = new Date().toLocaleString();
    const employeeName = EMPLOYEE_NAME;
    
    // Get last cart items from localStorage
    const lastCartItems = JSON.parse(localStorage.getItem('lastCartItems') || '[]');
    const paymentData = window.mainReceiptPaymentData;
    
    // Calculate totals
    const subtotal = lastCartItems.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const total = subtotal + tax - discountAmount;
    
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Receipt #${receiptNum}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .receipt { border: 1px solid #000; padding: 20px; width: 80mm; }
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
                .footer { text-align: center; margin-top: 15px; font-size: 11px; color: #666; border-top: 1px dashed #000; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <h2>Altiere</h2>
                    <p>Receipt #: ${receiptNum}</p>
                    <p>Date: ${date}</p>
                    ${paymentData ? `<p>Payment: ${paymentData.method.toUpperCase()}</p>` : ''}
                </div>
                <div class="items">
                    ${lastCartItems.map(item => `
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
                    <div class="summary-row"><span>Subtotal:</span><span>₱${subtotal.toFixed(2)}</span></div>
                    <div class="summary-row"><span>Tax (12%):</span><span>₱${tax.toFixed(2)}</span></div>
                    <div class="summary-row"><span>Discount:</span><span>₱${discountAmount.toFixed(2)}</span></div>
                    <div class="summary-row total"><span>TOTAL:</span><span>₱${total.toFixed(2)}</span></div>
                    ${paymentData && paymentData.method === 'cash' ? `
                        <div class="summary-row"><span>Cash Received:</span><span>₱${paymentData.cash_received.toFixed(2)}</span></div>
                        <div class="summary-row"><span>Change:</span><span>₱${(paymentData.cash_received - total).toFixed(2)}</span></div>
                    ` : ''}
                    ${paymentData && paymentData.transaction_ref ? `
                        <div class="summary-row" style="font-size: 10px; color: #666;">
                            <span>Reference:</span><span>${paymentData.transaction_ref}</span>
                        </div>
                    ` : ''}
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
    closeCustomModal();
    
    // Clear temporary data
    window.mainReceiptPaymentData = null;
    localStorage.removeItem('lastCartItems');
    
    // Focus on search input
    if (searchInput) searchInput.focus();
}