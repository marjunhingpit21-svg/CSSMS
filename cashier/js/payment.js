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

    fetch('process_sale.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(saleData)
    })
    .then(r => r.json())
    .then(res => {
        console.log('Payment response:', res);
        
        if (res.success) {
            showFunctionFeedback('Payment successful! Printing receipt...');
            
            setTimeout(() => {
                printReceipt();
            }, 500);
            
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