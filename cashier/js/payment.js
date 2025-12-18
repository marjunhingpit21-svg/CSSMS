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
                <label style="font-weight:600;display:block;margin-bottom:10px;">Select E-Wallet:</label>
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <button onclick="selectEWallet('gcash', ${total})" class="btn" style="flex:1;padding:15px;background:white;color:#007DFE;border:2px solid #007DFE;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#007DFE';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#007DFE';">
                        <span>GCash</span>
                    </button>
                    <button onclick="selectEWallet('paymaya', ${total})" class="btn" style="flex:1;padding:15px;background:white;color:#00D632;border:2px solid #00D632;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#00D632';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#00D632';">
                        <span>Maya</span>
                    </button>
                </div>
            </div>
        `;
    } else if (mode === 'card') {
        html += `
            <div style="margin:20px 0;">
                <label style="font-weight:600;display:block;margin-bottom:10px;">Select Bank:</label>
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <button onclick="selectBank('landbank', ${total})" class="btn" style="flex:1;padding:15px;background:white;color:#00843D;border:2px solid #00843D;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#00843D';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#00843D';">
                        <span>LANDBANK</span>
                    </button>
                    <button onclick="selectBank('seabank', ${total})" class="btn" style="flex:1;padding:15px;background:white;color:#FF6B00;border:2px solid #FF6B00;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#FF6B00';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#FF6B00';">
                        <span>MariBank</span>
                    </button>
                </div>
            </div>
        `;
    }

    html += `
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button onclick="processPayment('${mode}')" class="btn btn-primary" style="flex:1;">Complete Payment</button>
            <button onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
        </div>`;

    document.getElementById('modalBody').innerHTML = html;

    if (mode === 'cash') {
        const cashInput = document.getElementById('cashInput');
        
        cashInput.addEventListener('input', function() {
            const received = parseFloat(this.value) || 0;
            const change = received - total;
            
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
        });
        
        setTimeout(() => {
            cashInput.focus();
            cashInput.select();
        }, 100);
    }
}

// New function to handle E-Wallet selection
function selectEWallet(provider, total) {
    // Store selected provider
    window.selectedEWalletProvider = provider;
    window.selectedPaymentTotal = total;
    
    // Close the payment modal
    closePaymentModal();
    
    // Show QR payment modal
    showQRPaymentModal('ewallet', provider, total);
}

// New function to handle Bank selection
function selectBank(bank, total) {
    // Store selected bank
    window.selectedBank = bank;
    window.selectedPaymentTotal = total;
    
    // Close the payment modal
    closePaymentModal();
    
    // Show QR payment modal
    showQRPaymentModal('card', bank, total);
}

// NEW FUNCTION: Show QR Payment Modal
function showQRPaymentModal(paymentMethod, provider, total) {
    const receiptNum = document.getElementById('receiptNumber').textContent;
    
    let providerName = '';
    let qrImage = '';
    let titleColor = '#e91e63';
    
    if (paymentMethod === 'ewallet') {
        if (provider === 'gcash') {
            providerName = 'GCash';
            qrImage = 'img/gcash.jpeg';
            titleColor = '#007DFE';
        } else if (provider === 'paymaya') {
            providerName = 'Maya';
            qrImage = 'img/Maya.jpeg';
            titleColor = '#00D632';
        }
    } else if (paymentMethod === 'card') {
        if (provider === 'landbank') {
            providerName = 'LANDBANK';
            qrImage = 'img/LandBank.jpg';
            titleColor = '#00843D';
        } else if (provider === 'seabank') {
            providerName = 'MariBank';
            qrImage = 'img/MariBank.jpeg';
            titleColor = '#FF6B00';
        }
    }
    
    const html = `
        <div style="text-align:center;font-size:16px;font-weight:600;margin:10px 0;color:#666;">
            Receipt #${receiptNum}
        </div>
        <div style="text-align:center;font-size:22px;font-weight:700;margin:15px 0;color:${titleColor};">
            ₱${total.toFixed(2)}
        </div>
        
        <div style="text-align:center;margin:15px 0;">
            <div style="font-weight:700;font-size:18px;margin-bottom:10px;color:${titleColor};">
                ${providerName} Payment
            </div>
            <div style="padding:15px;background:#f9f9f9;border-radius:10px;display:inline-block;margin-bottom:10px;">
                <img src="${qrImage}" alt="QR Code" style="width:200px;height:200px;border:2px solid #ddd;border-radius:6px;">
            </div>
            <div style="margin-top:8px;font-size:14px;color:#666;">
                Scan to pay <strong>₱${total.toFixed(2)}</strong>
            </div>
        </div>
        
        <div style="margin:15px 0;">
            <label style="font-weight:600;display:block;margin-bottom:6px;font-size:14px;">Amount Received:</label>
            <input type="number" id="qrCashInput" 
                style="font-size:16px;padding:10px;width:100%;margin-top:6px;border:2px solid #ffeef2;border-radius:6px;" 
                value="${total.toFixed(2)}"
                step="0.01">
        </div>
        
        <div style="margin:15px 0;">
            <label style="font-weight:600;display:block;margin-bottom:6px;font-size:14px;">Reference Number:</label>
            <input type="text" id="qrRefInput" 
                style="font-size:14px;padding:10px;width:100%;margin-top:6px;border:2px solid #ffeef2;border-radius:6px;" 
                placeholder="Enter reference number from your payment"
                maxlength="50"
                autofocus>
        </div>
        
        <div style="display:flex;gap:8px;margin-top:20px;">
            <button onclick="processQRPayment('${paymentMethod}', '${provider}')" class="btn btn-primary" style="flex:1;padding:12px;">
                Complete Payment
            </button>
            <button onclick="closeQRPaymentModal()" class="btn btn-secondary" style="padding:12px;">
                Cancel
            </button>
        </div>
    `;
    
    // Create modal if it doesn't exist
    if (!document.getElementById('qrPaymentModal')) {
        const modalDiv = document.createElement('div');
        modalDiv.id = 'qrPaymentModal';
        modalDiv.className = 'modal';
        modalDiv.style.display = 'none';
        modalDiv.innerHTML = `
            <div class="modal-content" style="max-width:420px;max-height:100vh;margin-top:50px;position:relative;top:10px;">
                <div class="modal-header">
                    <h2 style="font-size:20px;">QR Payment</h2>
                    <span class="close" onclick="closeQRPaymentModal()">&times;</span>
                </div>
                <div class="modal-body" id="qrModalBody" style="max-height:calc(85vh - 60px);overflow-y:auto;padding:15px;"></div>
            </div>
        `;
        document.body.appendChild(modalDiv);
    }
    
    document.getElementById('qrModalBody').innerHTML = html;
    document.getElementById('qrPaymentModal').style.display = 'block';
    
    // Focus on reference input
    setTimeout(() => {
        const refInput = document.getElementById('qrRefInput');
        if (refInput) refInput.focus();
    }, 100);
}

// Function to close QR payment modal
function closeQRPaymentModal() {
    const modal = document.getElementById('qrPaymentModal');
    if (modal) {
        modal.style.display = 'none';
    }
    window.selectedEWalletProvider = null;
    window.selectedBank = null;
    window.selectedPaymentTotal = null;
}

// Function to process QR payment
function processQRPayment(paymentMethod, provider) {
    const cashInput = document.getElementById('qrCashInput');
    const refInput = document.getElementById('qrRefInput');
    
    const cashReceived = parseFloat(cashInput.value) || 0;
    const transactionRef = refInput ? refInput.value.trim() : null;
    const total = window.selectedPaymentTotal || 0;
    
    // Validation
    if (cashReceived <= 0) {
        alert('Please enter a valid amount!');
        return;
    }
    
    if (!transactionRef || transactionRef === '') {
        alert(`Please enter the reference number from your ${paymentMethod === 'ewallet' ? 'e-wallet' : 'bank'} payment!`);
        return;
    }
    
    // Process the payment
    let method = paymentMethod;
    let fullTransactionRef = transactionRef;
    
    if (paymentMethod === 'ewallet' && provider) {
        fullTransactionRef = `${provider.toUpperCase()}-${transactionRef}`;
    } else if (paymentMethod === 'card' && provider) {
        fullTransactionRef = `${provider.toUpperCase()}-${transactionRef}`;
    }
    
    // Close QR modal
    closeQRPaymentModal();
    
    // Process the actual payment with the collected data
    processPaymentWithData(method, cashReceived, fullTransactionRef, total);
}

// Helper function to process payment with collected data
function processPaymentWithData(method, cashReceived, transactionRef, total) {
    const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const actualTotal = subtotal + tax - discountAmount;
    const receiptNum = document.getElementById('receiptNumber').textContent;
    
    // Verify total matches
    if (Math.abs(total - actualTotal) > 0.01) {
        alert('Payment amount does not match cart total. Please try again.');
        return;
    }
    
    // Check if authorization is needed
    const requiresAuthorization = checkIfAuthorizationNeeded();
    
    if (requiresAuthorization && !window.pendingAuthorization) {
        // Show authorization modal with specific details
        const authDetails = buildAuthorizationDetails();
        
        showAuthorizationModal(
            'AUTHORIZATION REQUIRED FOR SPECIAL CHANGES',
            (extraData, authData) => {
                // Store authorization data with additional details
                window.pendingAuthorization = {
                    authorized_by: authData.employee_name,
                    position: authData.position,
                    reason: authData.reason || 'No reason provided',
                    employee_id: authData.employee_id,
                    employee_name: authData.employee_name,
                    details: authDetails
                };
                // Retry payment with authorization
                processPaymentWithData(method, cashReceived, transactionRef, total);
            },
            { details: authDetails },
            () => {
                alert('Payment cancelled. Authorization is required for this transaction.');
                window.pendingAuthorization = null;
            }
        );
        return;
    }
    
    const saleData = {
        employee_id: EMPLOYEE_ID,
        items: cart,
        subtotal: subtotal,
        tax: tax,
        total_amount: actualTotal,
        payment_method: method,
        discount: discountAmount,
        discount_percentage: globalDiscount,
        discount_type: globalDiscountType,
        discount_id_number: globalDiscountIdNumber,
        cash_received: cashReceived,
        transaction_reference: transactionRef,
        receipt_number: receiptNum
    };
    
    // Add discount authorization data if "Others" discount was used
    if (globalDiscountType === 'others' && window.othersDiscountAuthData) {
        saleData.authorization_data = window.othersDiscountAuthData;
        saleData.discount_authorization = {
            authorized_by: window.othersDiscountAuthData.employee_name,
            authorized_position: window.othersDiscountAuthData.position
        };
    }
    
    // Add authorization data if available (from price changes)
    if (window.pendingAuthorization && window.pendingAuthorization.authData) {
        if (!saleData.authorization_data) {
            saleData.authorization_data = window.pendingAuthorization.authData;
        }
        saleData.authorization_action = 'SALE_AUTHORIZATION';
    }

    // Add authorization data if available
    if (window.pendingAuthorization) {
        saleData.authorization_data = window.pendingAuthorization;
    }

    console.log('Processing payment with data:', saleData);

    // Disable the button to prevent double-clicks
    const completeBtn = document.querySelector('#qrPaymentModal .btn-primary') || 
                       document.querySelector('#paymentModal .btn-primary');
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
            const cartDataForPreview = [...cart];
            console.log('Saving cart data for preview:', cartDataForPreview);
            
            localStorage.setItem('lastCartItems', JSON.stringify(cartDataForPreview));
            
            // Store payment data for receipt preview
            window.mainReceiptPaymentData = {
                receipt_number: receiptNum,
                total: actualTotal,
                method: method,
                transaction_ref: transactionRef,
                cash_received: cashReceived,
                subtotal: subtotal,
                tax: tax,
                discount_amount: discountAmount,
                global_discount: globalDiscount,
                global_discount_type: globalDiscountType,
                global_discount_id_number: globalDiscountIdNumber,
                authorization_log_id: res.authorization_log_id,
                requires_authorization: res.requires_authorization
            };
            
            console.log('Saved payment data:', window.mainReceiptPaymentData);
            
            // Clear cart and reset
            cart = [];
            selectedItemIndex = -1;
            globalDiscount = 0;
            globalDiscountType = '';
            globalDiscountIdNumber = '';
            renderCart();
            
            // Clear selected providers
            window.selectedEWalletProvider = null;
            window.selectedBank = null;
            window.selectedPaymentTotal = null;
            
            // Clear authorization
            window.pendingAuthorization = null;
            
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
            
            // Refresh transaction history if modal is open
            const transactionModal = document.getElementById('transactionModal');
            if (transactionModal && transactionModal.style.display === 'block') {
                console.log('Refreshing transaction history...');
                const currentDate = document.getElementById('transactionDate')?.value || new Date().toISOString().split('T')[0];
                loadTransactionHistory(currentDate);
            }
            
            // Show receipt preview
            setTimeout(() => {
                console.log('Attempting to show receipt preview...');
                showMainReceiptPrintPreview();
            }, 200);
            
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

// Function to check if authorization is needed
function checkIfAuthorizationNeeded() {
    // Check for price changes
    const hasPriceChanges = cart.some(item => item.price_changed);
    
    // Check for custom discount
    const hasCustomDiscount = globalDiscountType === 'others' && globalDiscount > 0;
    
    return hasPriceChanges || hasCustomDiscount;
}

// Function to build authorization details for the modal
function buildAuthorizationDetails() {
    const details = {
        price_changes: [],
        discount: null,
        timestamp: new Date().toISOString()
    };
    
    // Collect price change details
    cart.forEach(item => {
        if (item.price_changed) {
            details.price_changes.push({
                product: item.product_name,
                size: item.size_name,
                old_price: item.original_price,
                new_price: item.final_price,
                authorized_by: item.price_changed_by || null,
                position: item.price_changed_position || null,
                reason: item.price_change_reason || null
            });
        }
    });
    
    // Collect discount details
    if (globalDiscountType === 'others' && globalDiscount > 0) {
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        details.discount = {
            type: 'custom',
            percentage: globalDiscount,
            amount: subtotal * (globalDiscount / 100),
            id_number: globalDiscountIdNumber || ''
        };
    } else if (globalDiscountType && globalDiscount > 0) {
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        details.discount = {
            type: globalDiscountType,
            percentage: globalDiscount,
            amount: subtotal * (globalDiscount / 100),
            id_number: globalDiscountIdNumber || ''
        };
    }
    
    return details;
}
// Updated processPayment function for cash payments
function processPayment(method) {
    // If method is cash, process normally
    if (method === 'cash') {
        const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
        const tax = subtotal * 0.12;
        const discountAmount = subtotal * (globalDiscount / 100);
        const total = subtotal + tax - discountAmount;
        const receiptNum = document.getElementById('receiptNumber').textContent;
        
        const cashInput = document.getElementById('cashInput');
        const cashReceived = parseFloat(cashInput.value) || 0;
        
        if (cashReceived < total) {
            alert('Cash received must be greater than or equal to total amount!');
            return;
        }
        
        if (cashReceived <= 0) {
            alert('Please enter a valid amount!');
            return;
        }
        
        // Generate transaction reference for cash
        const timestamp = Date.now();
        const random = Math.floor(Math.random() * 10000);
        const transactionRef = `CASH-${timestamp}-${random}`;
        
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

        console.log('Processing cash payment:', saleData);

        // Disable the button to prevent double-clicks
        const completeBtn = document.querySelector('#paymentModal .btn-primary');
        const originalBtnText = completeBtn ? completeBtn.innerHTML : '';
        if (completeBtn) {
            completeBtn.disabled = true;
            completeBtn.innerHTML = 'Processing...';
        }

        // ADD DEBUG LOGGING
        console.log('Sending cash payment request to process_sale.php with data:', saleData);
        
        fetch('process_sale.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(saleData)
        })
        .then(response => {
            // Log the raw response
            console.log('Response status:', response.status);
            
            // First, get the response as text to see what we're getting
            return response.text().then(text => {
                console.log('Raw response text (first 500 chars):', text.substring(0, 500));
                
                // Try to parse as JSON
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    console.error('Raw response was:', text);
                    
                    // Check if it's an HTML error page
                    if (text.includes('<br />') || text.includes('<b>') || text.includes('<!DOCTYPE')) {
                        throw new Error('Server returned HTML error page. Check PHP errors.');
                    }
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(res => {
            console.log('Payment response:', res);
            
            if (res.success) {
                showFunctionFeedback('Payment successful!');
                
                // Save cart items for receipt preview BEFORE clearing
                const cartDataForPreview = [...cart];
                console.log('Saving cart data for preview:', cartDataForPreview);
                
                localStorage.setItem('lastCartItems', JSON.stringify(cartDataForPreview));
                
                // Store payment data for receipt preview
                window.mainReceiptPaymentData = {
                    receipt_number: receiptNum,
                    total: total,
                    method: method,
                    transaction_ref: transactionRef,
                    cash_received: cashReceived,
                    subtotal: subtotal,
                    tax: tax,
                    discount_amount: discountAmount,
                    global_discount: globalDiscount,
                    global_discount_type: globalDiscountType,
                    global_discount_id_number: globalDiscountIdNumber
                };
                
                console.log('Saved payment data:', window.mainReceiptPaymentData);
                
                // Clear cart and reset
                cart = [];
                selectedItemIndex = -1;
                globalDiscount = 0;
                globalDiscountType = '';
                globalDiscountIdNumber = '';
                renderCart();
                
                closePaymentModal();
                
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
                
                // Refresh transaction history if modal is open
                const transactionModal = document.getElementById('transactionModal');
                if (transactionModal && transactionModal.style.display === 'block') {
                    console.log('Refreshing transaction history...');
                    const currentDate = document.getElementById('transactionDate')?.value || new Date().toISOString().split('T')[0];
                    loadTransactionHistory(currentDate);
                }
                
                // Show receipt preview
                setTimeout(() => {
                    console.log('Attempting to show receipt preview...');
                    showMainReceiptPrintPreview();
                }, 200);
                
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
            alert('Payment failed: ' + err.message + '\n\nPlease check the server error logs.');
        });
    } else {
        // For ewallet or card, we should have already handled it via QR modal
        alert(`Please select a ${method === 'ewallet' ? 'e-wallet' : 'bank'} payment method first.`);
    }
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// NEW FUNCTION: Show receipt preview for main receipt - DEBUG VERSION
function showMainReceiptPrintPreview() {
    console.log('showMainReceiptPrintPreview called');
    
    // Check if showCustomModal function exists
    if (typeof showCustomModal !== 'function') {
        console.error('showCustomModal function not found!');
        alert('Error: Print preview system not available. Please check console.');
        return;
    }
    
    // Get data from localStorage
    const lastCartItems = JSON.parse(localStorage.getItem('lastCartItems') || '[]');
    const paymentData = window.mainReceiptPaymentData;
    
    console.log('lastCartItems from localStorage:', lastCartItems);
    console.log('paymentData from window:', paymentData);
    
    if (!paymentData || lastCartItems.length === 0) {
        console.error('No receipt data available for preview');
        console.log('Payment data:', paymentData);
        console.log('Cart items:', lastCartItems);
        alert('No receipt data available for preview. Please check console for details.');
        return;
    }
    
    const receiptNum = paymentData.receipt_number;
    const date = new Date().toLocaleString();
    const employeeName = EMPLOYEE_NAME;
    
    // Calculate totals
    const subtotal = lastCartItems.reduce((s,i) => s + (i.final_price || 0) * (i.quantity || 1), 0);
    const tax = subtotal * 0.12;
    const discountAmount = paymentData.discount_amount || 0;
    const total = paymentData.total || (subtotal + tax - discountAmount);
    
    console.log('Calculated totals:', { subtotal, tax, discountAmount, total });
    
    const printPreviewHTML = `
        <div style="padding: 20px;">
            <h3 style="margin-bottom: 15px; color: #1a1a2e;">Print Preview - Main Receipt</h3>
            
            <div style="background: white; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; font-family: monospace;">
                <div style="text-align: center; margin-bottom: 10px;max-height:450px;overflow-y:auto;">
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
                                <div>${item.product_name || 'Unknown'} (${item.size_name || 'N/A'})</div>
                                <div style="font-size: 11px;">${item.quantity || 1} × ₱${(item.final_price || 0).toFixed(2)}</div>
                            </div>
                            <div style="font-weight: bold;">₱${((item.quantity || 1) * (item.final_price || 0)).toFixed(2)}</div>
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
                    ${paymentData && paymentData.method === 'cash' && paymentData.cash_received ? `
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
    
    console.log('Showing custom modal with receipt preview');
    showCustomModal('Print Preview', printPreviewHTML);
}

// Updated printReceipt function to work with preview
function printReceipt() {
    console.log('printReceipt called');
    
    // Get data from localStorage
    const lastCartItems = JSON.parse(localStorage.getItem('lastCartItems') || '[]');
    const paymentData = window.mainReceiptPaymentData;
    
    if (!paymentData || lastCartItems.length === 0) {
        alert('No receipt data available to print.');
        return;
    }
    
    const receiptNum = paymentData.receipt_number;
    const date = new Date().toLocaleString();
    const employeeName = EMPLOYEE_NAME;
    
    // Calculate totals
    const subtotal = lastCartItems.reduce((s,i) => s + (i.final_price || 0) * (i.quantity || 1), 0);
    const tax = subtotal * 0.12;
    const discountAmount = paymentData.discount_amount || 0;
    const total = paymentData.total || (subtotal + tax - discountAmount);
    
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
                                <div>${item.product_name || 'Unknown'} (${item.size_name || 'N/A'})</div>
                                <div style="font-size: 11px;">${item.quantity || 1} × ₱${(item.final_price || 0).toFixed(2)}</div>
                            </div>
                            <div class="item-total">₱${((item.quantity || 1) * (item.final_price || 0)).toFixed(2)}</div>
                        </div>
                    `).join('')}
                </div>
                <div class="summary">
                    <div class="summary-row"><span>Subtotal:</span><span>₱${subtotal.toFixed(2)}</span></div>
                    <div class="summary-row"><span>Tax (12%):</span><span>₱${tax.toFixed(2)}</span></div>
                    <div class="summary-row"><span>Discount:</span><span>₱${discountAmount.toFixed(2)}</span></div>
                    <div class="summary-row total"><span>TOTAL:</span><span>₱${total.toFixed(2)}</span></div>
                    ${paymentData && paymentData.method === 'cash' && paymentData.cash_received ? `
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
    
    if (typeof closeCustomModal === 'function') {
        closeCustomModal();
    }
    
    // Clear temporary data
    window.mainReceiptPaymentData = null;
    localStorage.removeItem('lastCartItems');
    
    // Focus on search input
    if (searchInput) {
        searchInput.focus();
    }
}