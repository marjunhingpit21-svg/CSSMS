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
    // Close modal first
    closeCustomModal();
    
    // Hide main receipt, show split receipts
    const mainReceipt = document.getElementById('mainReceipt');
    const splitContainer = document.getElementById('splitReceiptContainer');
    
    if (mainReceipt) mainReceipt.style.display = 'none';
    if (splitContainer) splitContainer.style.display = 'block';
    
    // Set receipt numbers
    const receiptNum = document.getElementById('receiptNumber').textContent;
    const receipt1NumEl = document.getElementById('splitReceipt1Number');
    const receipt2NumEl = document.getElementById('splitReceipt2Number');
    
    if (receipt1NumEl) receipt1NumEl.textContent = receiptNum + '-A';
    if (receipt2NumEl) receipt2NumEl.textContent = receiptNum + '-B';
    
    // Render split receipts
    renderSplitReceipt(1, splitReceipt1Items);
    renderSplitReceipt(2, splitReceipt2Items);
    
    splitReceiptActive = true;
    splitReceipt1Paid = false;
    splitReceipt2Paid = false;
    splitPaymentData.receipt1 = null;
    splitPaymentData.receipt2 = null;
    
    // Hide individual print button if it exists
    const printIndividualBtn = document.getElementById('printIndividualSplitBtn');
    if (printIndividualBtn) {
        printIndividualBtn.style.display = 'none';
    }
    
    // Update cancel button state
    updateCancelSplitButton();
    
    // Disable F keys (IMPORTANT: Call this AFTER setting splitReceiptActive = true)
    updateFKeyState();
    
    showFunctionFeedback('Split receipts created. Pay Receipt 1 first.');
}

function renderSplitReceipt(receiptNumber, items) {
    const container = document.getElementById(`splitReceipt${receiptNumber}Items`);
    if (!container) return;
    
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
                        ${item.clerk_name !== EMPLOYEE_NAME ? `<span class="clerk-indicator">${item.clerk_name}</span>` : ''}
                    </div>
                    <div class="item-price">₱${item.final_price.toFixed(2)} × ${item.quantity}</div>
                    ${item.notes ? `<div style="font-size:11px;color:#666;margin-top:3px;padding:4px 8px;border-radius:4px;background:#f9f9f9;border-left:3px solid #007bff;">📝 ${item.notes}</div>` : ''}
                    <div class="item-quantity-print">Qty: ${item.quantity}</div>
                </div>
                <div>
                    <div class="item-total">₱${(item.final_price * item.quantity).toFixed(2)}</div>
                </div>
            </div>
        `).join('');
    }
    
    // Update totals
    const subtotalEl = document.getElementById(`splitReceipt${receiptNumber}Subtotal`);
    const taxEl = document.getElementById(`splitReceipt${receiptNumber}Tax`);
    const discountEl = document.getElementById(`splitReceipt${receiptNumber}Discount`);
    const totalEl = document.getElementById(`splitReceipt${receiptNumber}Total`);
    
    if (subtotalEl) subtotalEl.textContent = '₱' + subtotal.toFixed(2);
    if (taxEl) taxEl.textContent = '₱' + tax.toFixed(2);
    if (discountEl) discountEl.textContent = '₱' + discountAmount.toFixed(2);
    if (totalEl) totalEl.textContent = '₱' + total.toFixed(2);
}

function paySplitReceipt(receiptNumber) {
    const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
    const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const total = subtotal + tax - discountAmount;
    const receiptNumEl = document.getElementById(`splitReceipt${receiptNumber}Number`);
    const receiptNum = receiptNumEl ? receiptNumEl.textContent : `SPLIT-${receiptNumber}`;
    
    // Use the same payment modal structure as single receipt
    const paymentModal = document.getElementById('paymentModal');
    const modalTitle = document.getElementById('modalTitle');
    
    if (!paymentModal || !modalTitle) {
        alert('Payment system error. Please refresh the page.');
        return;
    }
    
    paymentModal.style.display = 'block';
    modalTitle.textContent = `Pay Split Receipt ${receiptNumber}`;

    updateFKeyState();

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
                <button class="btn-payment btn-cash" onclick="openSplitPaymentModal(${receiptNumber}, 'cash')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/>
                        <line x1="2" y1="10" x2="22" y2="10" stroke-width="2"/>
                    </svg>
                    Cash
                </button>
                <button class="btn-payment btn-card" onclick="openSplitPaymentModal(${receiptNumber}, 'card')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/>
                        <line x1="2" y1="10" x2="22" y2="10" stroke-width="2"/>
                    </svg>
                    Card
                </button>
                <button class="btn-payment btn-ewallet" onclick="openSplitPaymentModal(${receiptNumber}, 'ewallet')">
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

    const modalBody = document.getElementById('modalBody');
    if (modalBody) {
        modalBody.innerHTML = html;
    }
}

// === SPLIT PAYMENT FUNCTIONS (Same as main receipt but for split) ===
function openSplitPaymentModal(receiptNumber, paymentMethod) {
    const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
    const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const total = subtotal + tax - discountAmount;
    const receiptNumEl = document.getElementById(`splitReceipt${receiptNumber}Number`);
    const receiptNum = receiptNumEl ? receiptNumEl.textContent : `SPLIT-${receiptNumber}`;
    
    // Store which receipt we're processing
    window.currentSplitReceiptNumber = receiptNumber;
    window.currentSplitPaymentMethod = paymentMethod;
    
    // Show payment modal
    const paymentModal = document.getElementById('paymentModal');
    const modalTitle = document.getElementById('modalTitle');
    
    if (!paymentModal || !modalTitle) {
        alert('Payment system error. Please refresh the page.');
        return;
    }
    
    paymentModal.style.display = 'block';
    modalTitle.textContent = `Pay Split Receipt ${receiptNumber}`;
    
    updateFKeyState();
    
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
    `;
    
    if (paymentMethod === 'cash') {
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
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button onclick="processSplitPayment()" class="btn btn-primary" style="flex:1;">Complete Payment</button>
                <button onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
            </div>
        `;
    } else if (paymentMethod === 'ewallet') {
    html += `
            <div style="margin:20px 0;">
                <label style="font-weight:600;display:block;margin-bottom:10px;">Select E-Wallet:</label>
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <button onclick="selectSplitEWallet('gcash', ${total}, ${receiptNumber})" class="btn" style="flex:1;padding:15px;background:white;color:#007DFE;border:2px solid #007DFE;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#007DFE';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#007DFE';">
                        <span>GCash</span>
                    </button>
                    <button onclick="selectSplitEWallet('paymaya', ${total}, ${receiptNumber})" class="btn" style="flex:1;padding:15px;background:white;color:#00D632;border:2px solid #00D632;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#00D632';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#00D632';">
                        <span>Maya</span>
                    </button>
                </div>
            </div>
        `;
    } else if (paymentMethod === 'card') {
        html += `
            <div style="margin:20px 0;">
                <label style="font-weight:600;display:block;margin-bottom:10px;">Select Bank:</label>
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <button onclick="selectSplitBank('landbank', ${total}, ${receiptNumber})" class="btn" style="flex:1;padding:15px;background:white;color:#00843D;border:2px solid #00843D;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#00843D';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#00843D';">
                        <span>LANDBANK</span>
                    </button>
                    <button onclick="selectSplitBank('seabank', ${total}, ${receiptNumber})" class="btn" style="flex:1;padding:15px;background:white;color:#FF6B00;border:2px solid #FF6B00;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;" onmouseover="this.style.background='#FF6B00';this.style.color='white';" onmouseout="this.style.background='white';this.style.color='#FF6B00';">
                        <span>MariBank</span>
                    </button>
                </div>
            </div>
        `;
    }
    
    const modalBody = document.getElementById('modalBody');
    if (modalBody) {
        modalBody.innerHTML = html;
    }
    
    // Add event listeners for cash input
    if (paymentMethod === 'cash') {
        setTimeout(() => {
            const cashInput = document.getElementById('cashInput');
            if (cashInput) {
                cashInput.addEventListener('input', function() {
                    const received = parseFloat(this.value) || 0;
                    const change = received - total;
                    const changeBox = document.getElementById('changeBox');
                    const changeAmount = document.getElementById('changeAmount');
                    const errorBox = document.getElementById('cashError');
                    
                    if (received < total) {
                        if (changeBox) changeBox.style.display = 'none';
                        if (errorBox) {
                            errorBox.textContent = 'Amount received is less than total';
                            errorBox.style.display = 'block';
                        }
                    } else {
                        if (errorBox) errorBox.style.display = 'none';
                        if (changeBox) {
                            changeBox.style.display = 'block';
                            if (changeAmount) changeAmount.textContent = change.toFixed(2);
                        }
                    }
                });
                
                cashInput.focus();
                cashInput.select();
            }
        }, 100);
    }
}

// === SPLIT QR PAYMENT FUNCTIONS ===
function selectSplitEWallet(provider, total, receiptNumber) {
    // Store selected provider and receipt number
    window.selectedSplitEWalletProvider = provider;
    window.selectedSplitPaymentTotal = total;
    window.selectedSplitReceiptNumber = receiptNumber;
    
    // Close the payment modal
    closePaymentModal();
    
    // Show QR payment modal
    showSplitQRPaymentModal('ewallet', provider, total, receiptNumber);
}

function selectSplitBank(bank, total, receiptNumber) {
    // Store selected bank and receipt number
    window.selectedSplitBank = bank;
    window.selectedSplitPaymentTotal = total;
    window.selectedSplitReceiptNumber = receiptNumber;
    
    // Close the payment modal
    closePaymentModal();
    
    // Show QR payment modal
    showSplitQRPaymentModal('card', bank, total, receiptNumber);
}

// Function to show QR payment modal for split receipts
function showSplitQRPaymentModal(paymentMethod, provider, total, receiptNumber) {
    const receiptNumEl = document.getElementById(`splitReceipt${receiptNumber}Number`);
    const receiptNum = receiptNumEl ? receiptNumEl.textContent : `SPLIT-${receiptNumber}`;
    
    let providerName = '';
    let qrImage = '';
    let titleColor = '#e91e63';
    
    if (paymentMethod === 'ewallet') {
        if (provider === 'gcash') {
            providerName = 'GCash';
            qrImage = 'img/gcash.jpg';
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
            Split Receipt ${receiptNumber} - #${receiptNum}
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
            <input type="number" id="splitQrCashInput" 
                style="font-size:16px;padding:10px;width:100%;margin-top:6px;border:2px solid #ffeef2;border-radius:6px;" 
                value="${total.toFixed(2)}"
                step="0.01">
        </div>
        
        <div style="margin:15px 0;">
            <label style="font-weight:600;display:block;margin-bottom:6px;font-size:14px;">Reference Number:</label>
            <input type="text" id="splitQrRefInput" 
                style="font-size:14px;padding:10px;width:100%;margin-top:6px;border:2px solid #ffeef2;border-radius:6px;" 
                placeholder="Enter reference number from your payment"
                maxlength="50"
                autofocus>
        </div>
        
        <div style="display:flex;gap:8px;margin-top:20px;">
            <button onclick="processSplitQRPayment('${paymentMethod}', '${provider}', ${receiptNumber})" class="btn btn-primary" style="flex:1;padding:12px;">
                Complete Payment
            </button>
            <button onclick="closeSplitQRPaymentModal()" class="btn btn-secondary" style="padding:12px;">
                Cancel
            </button>
        </div>
    `;
    
    // Create modal if it doesn't exist
    if (!document.getElementById('splitQrPaymentModal')) {
        const modalDiv = document.createElement('div');
        modalDiv.id = 'splitQrPaymentModal';
        modalDiv.className = 'modal';
        modalDiv.style.display = 'none';
        modalDiv.innerHTML = `
            <div class="modal-content" style="max-width:420px;max-height:100vh;margin-top:50px;position:relative;top:10px;">
                <div class="modal-header">
                    <h2 style="font-size:20px;">QR Payment - Split Receipt</h2>
                    <span class="close" onclick="closeSplitQRPaymentModal()">&times;</span>
                </div>
                <div class="modal-body" id="splitQrModalBody" style="max-height:calc(85vh - 60px);overflow-y:auto;padding:15px;"></div>
            </div>
        `;
        document.body.appendChild(modalDiv);
    }
    
    document.getElementById('splitQrModalBody').innerHTML = html;
    document.getElementById('splitQrPaymentModal').style.display = 'block';
    
    // Focus on reference input
    setTimeout(() => {
        const refInput = document.getElementById('splitQrRefInput');
        if (refInput) refInput.focus();
    }, 100);
}

// Function to close split QR payment modal
function closeSplitQRPaymentModal() {
    const modal = document.getElementById('splitQrPaymentModal');
    if (modal) {
        modal.style.display = 'none';
    }
    window.selectedSplitEWalletProvider = null;
    window.selectedSplitBank = null;
    window.selectedSplitPaymentTotal = null;
    window.selectedSplitReceiptNumber = null;
}

// Function to process split QR payment
function processSplitQRPayment(paymentMethod, provider, receiptNumber) {
    const cashInput = document.getElementById('splitQrCashInput');
    const refInput = document.getElementById('splitQrRefInput');
    
    const cashReceived = parseFloat(cashInput.value) || 0;
    const transactionRef = refInput ? refInput.value.trim() : null;
    const total = window.selectedSplitPaymentTotal || 0;
    
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
    let fullTransactionRef = transactionRef;
    
    if (paymentMethod === 'ewallet' && provider) {
        fullTransactionRef = `${provider.toUpperCase()}-SPLIT${receiptNumber}-${transactionRef}`;
    } else if (paymentMethod === 'card' && provider) {
        fullTransactionRef = `${provider.toUpperCase()}-SPLIT${receiptNumber}-${transactionRef}`;
    }
    
    // Close QR modal
    closeSplitQRPaymentModal();
    
    // Process the actual payment with the collected data
    processSplitPaymentWithData(paymentMethod, cashReceived, fullTransactionRef, total, receiptNumber);
}

// Helper function to process split payment with collected data
function processSplitPaymentWithData(method, cashReceived, transactionRef, total, receiptNumber) {
    const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
    const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const actualTotal = subtotal + tax - discountAmount;
    const receiptNumEl = document.getElementById(`splitReceipt${receiptNumber}Number`);
    const receiptNum = receiptNumEl ? receiptNumEl.textContent : `SPLIT-${receiptNumber}`;
    
    // Verify total matches
    if (Math.abs(total - actualTotal) > 0.01) {
        alert('Payment amount does not match cart total. Please try again.');
        return;
    }
    
    const saleData = {
        employee_id: EMPLOYEE_ID,
        items: items,
        subtotal: subtotal,
        tax: tax,
        total_amount: actualTotal,
        payment_method: method,
        discount: discountAmount,
        discount_percentage: (discountAmount / subtotal) * 100,
        discount_type: globalDiscountType,
        discount_id_number: globalDiscountIdNumber,
        cash_received: cashReceived,
        transaction_reference: transactionRef,
        receipt_number: receiptNum,
        split_receipt: true,
        split_part: receiptNumber,
        original_receipt: originalReceiptNumber
    };

    console.log('Processing split QR payment:', saleData);

    // Disable the button to prevent double-clicks
    const completeBtn = document.querySelector('#splitQrPaymentModal .btn-primary');
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
        console.log('Split payment response:', res);
        
        if (res.success) {
            // Store payment data
            splitPaymentData[`receipt${receiptNumber}`] = {
                receipt_number: receiptNum,
                total: actualTotal,
                method: method,
                transaction_ref: transactionRef
            };
            
            // Update UI to show receipt as paid
            if (receiptNumber === 1) {
                splitReceipt1Paid = true;
                
                // Disable payment buttons for receipt 1
                const receipt1Buttons = document.querySelectorAll('#splitReceipt1 .btn-payment');
                receipt1Buttons.forEach(btn => {
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                    }
                });
                
                // Add PAID indicator
                const receipt1Header = document.querySelector('#splitReceipt1 .receipt-header');
                if (receipt1Header && !receipt1Header.querySelector('.paid-indicator')) {
                    const paidIndicator = document.createElement('p');
                    paidIndicator.className = 'paid-indicator';
                    paidIndicator.style.cssText = 'font-size:11px;margin-bottom:10px;color:#00c851;font-weight:600;';
                    paidIndicator.textContent = '✓ PAID';
                    receipt1Header.appendChild(paidIndicator);
                }
                
                showFunctionFeedback('Receipt 1 paid successfully! Now pay Receipt 2.');
            } else {
                splitReceipt2Paid = true;
                
                // Disable payment buttons for receipt 2
                const receipt2Buttons = document.querySelectorAll('#splitReceipt2 .btn-payment');
                receipt2Buttons.forEach(btn => {
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                    }
                });
                
                // Add PAID indicator
                const receipt2Header = document.querySelector('#splitReceipt2 .receipt-header');
                if (receipt2Header && !receipt2Header.querySelector('.paid-indicator')) {
                    const paidIndicator = document.createElement('p');
                    paidIndicator.className = 'paid-indicator';
                    paidIndicator.style.cssText = 'font-size:11px;margin-bottom:10px;color:#00c851;font-weight:600;';
                    paidIndicator.textContent = '✓ PAID';
                    receipt2Header.appendChild(paidIndicator);
                }
                
                // Both receipts are now paid
                showFunctionFeedback('Receipt 1 paid successfully!');
                completeSplitReceipt();
            }
            
            // Update cancel button state after payment
            updateCancelSplitButton();
            
            // Refresh transaction history if modal is open
            const transactionModal = document.getElementById('transactionModal');
            if (transactionModal && transactionModal.style.display === 'block') {
                console.log('Refreshing transaction history...');
                const currentDate = document.getElementById('transactionDate')?.value || new Date().toISOString().split('T')[0];
                if (typeof loadTransactionHistory === 'function') {
                    loadTransactionHistory(currentDate);
                }
            }
            
            // Show print preview for this receipt
            setTimeout(() => {
                showSplitReceiptPrintPreview(receiptNumber);
            }, 300);
            
            // If both receipts are paid, show print button
            if (splitReceipt1Paid && splitReceipt2Paid) {
                console.log('Both receipts paid, enabling print button');
                const splitControls = document.querySelector('.split-controls');
                if (splitControls && !splitControls.querySelector('.print-both-btn')) {
                    const printBtn = document.createElement('button');
                    printBtn.className = 'btn btn-primary print-both-btn';
                    printBtn.style.flex = '1';
                    printBtn.onclick = printSplitReceipts;
                    printBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right:5px;">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                            <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                        </svg>
                        Print Both Receipts
                    `;
                    splitControls.appendChild(printBtn);
                }
            }
            
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
        console.error('Split QR payment error:', err);
        // Re-enable button on error
        if (completeBtn) {
            completeBtn.disabled = false;
            completeBtn.innerHTML = originalBtnText;
        }
        alert('Payment failed. Please try again.');
    });
}
function processSplitPayment() {
    const receiptNumber = window.currentSplitReceiptNumber;
    const method = window.currentSplitPaymentMethod;
    const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
    const subtotal = items.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const total = subtotal + tax - discountAmount;
    const receiptNumEl = document.getElementById(`splitReceipt${receiptNumber}Number`);
    const receiptNum = receiptNumEl ? receiptNumEl.textContent : `SPLIT-${receiptNumber}`;
    
    console.log('Processing split payment for receipt:', receiptNumber);
    console.log('Payment method:', method);
    console.log('Total amount:', total);
    
    let cashReceived = null;
    let transactionRef = null;
    
    if (method === 'cash' || method === 'ewallet') {
        const cashInput = document.getElementById('cashInput');
        cashReceived = cashInput ? parseFloat(cashInput.value) || 0 : 0;
        
        if (method === 'cash' && cashReceived < total) {
            alert('Cash received must be greater than or equal to total amount!');
            return;
        }
        
        if (cashReceived <= 0) {
            alert('Please enter a valid amount!');
            return;
        }
    }
    
    // Get transaction reference for card/ewallet
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
        employee_id: EMPLOYEE_ID,
        items: items,
        subtotal: subtotal,
        tax: tax,
        total_amount: total,
        payment_method: method,
        discount: discountAmount,
        discount_percentage: (discountAmount / subtotal) * 100,
        discount_type: globalDiscountType,
        discount_id_number: globalDiscountIdNumber,
        cash_received: cashReceived,
        transaction_reference: transactionRef,
        receipt_number: receiptNum,
        split_receipt: true,
        split_part: receiptNumber,
        original_receipt: originalReceiptNumber
    };
    
    console.log('Sending sale data:', saleData);
    
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
    .then(r => {
        console.log('Response status:', r.status);
        if (!r.ok) {
            throw new Error(`HTTP error! status: ${r.status}`);
        }
        return r.json();
    })
    .then(res => {
        console.log('Split payment response:', res);
        
        if (res.success) {
            console.log('Payment successful for receipt', receiptNumber);
            
            // Store payment data
            splitPaymentData[`receipt${receiptNumber}`] = {
                receipt_number: receiptNum,
                total: total,
                method: method,
                transaction_ref: transactionRef
            };
            
            // Update UI to show receipt as paid FIRST
            if (receiptNumber === 1) {
                splitReceipt1Paid = true;
                
                // Disable payment buttons for receipt 1
                const receipt1Buttons = document.querySelectorAll('#splitReceipt1 .btn-payment');
                receipt1Buttons.forEach(btn => {
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                    }
                });
                
                // Add PAID indicator - check if it doesn't already exist
                const receipt1Header = document.querySelector('#splitReceipt1 .receipt-header');
                if (receipt1Header && !receipt1Header.querySelector('.paid-indicator')) {
                    const paidIndicator = document.createElement('p');
                    paidIndicator.className = 'paid-indicator';
                    paidIndicator.style.cssText = 'font-size:11px;margin-bottom:10px;color:#00c851;font-weight:600;';
                    paidIndicator.textContent = '✓ PAID';
                    receipt1Header.appendChild(paidIndicator);
                }
                
                showFunctionFeedback('Receipt 1 paid successfully! Now pay Receipt 2.');
            } else {
                splitReceipt2Paid = true;
                
                // Disable payment buttons for receipt 2
                const receipt2Buttons = document.querySelectorAll('#splitReceipt2 .btn-payment');
                receipt2Buttons.forEach(btn => {
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                        btn.style.cursor = 'not-allowed';
                    }
                });
                
                // Add PAID indicator - check if it doesn't already exist
                const receipt2Header = document.querySelector('#splitReceipt2 .receipt-header');
                if (receipt2Header && !receipt2Header.querySelector('.paid-indicator')) {
                    const paidIndicator = document.createElement('p');
                    paidIndicator.className = 'paid-indicator';
                    paidIndicator.style.cssText = 'font-size:11px;margin-bottom:10px;color:#00c851;font-weight:600;';
                    paidIndicator.textContent = '✓ PAID';
                    receipt2Header.appendChild(paidIndicator);
                }
                
                // Both receipts are now paid - call completion
                showFunctionFeedback('Both split receipts paid successfully!');
                completeSplitReceipt();
            }
            
            // Update cancel button state after payment
            updateCancelSplitButton();
            
            // Refresh transaction history if modal is open
            const transactionModal = document.getElementById('transactionModal');
            if (transactionModal) {
                // Check if style property exists before accessing it
                if (transactionModal.style && transactionModal.style.display === 'block') {
                    console.log('Refreshing transaction history...');
                    const transactionDateEl = document.getElementById('transactionDate');
                    const currentDate = transactionDateEl?.value || new Date().toISOString().split('T')[0];
                    if (typeof loadTransactionHistory === 'function') {
                        loadTransactionHistory(currentDate);
                    }
                }
            }
            
            // Close payment modal AFTER UI updates
            setTimeout(() => {
                if (typeof closePaymentModal === 'function') {
                    closePaymentModal();
                }
            }, 100);
            
            // Show print preview for this receipt
            setTimeout(() => {
                showSplitReceiptPrintPreview(receiptNumber);
            }, 300);
            
            // If both receipts are paid, show print button
            if (splitReceipt1Paid && splitReceipt2Paid) {
                console.log('Both receipts paid, enabling print button');
                const splitControls = document.querySelector('.split-controls');
                if (splitControls && !splitControls.querySelector('.print-both-btn')) {
                    const printBtn = document.createElement('button');
                    printBtn.className = 'btn btn-primary print-both-btn';
                    printBtn.style.flex = '1';
                    printBtn.onclick = printSplitReceipts;
                    printBtn.innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right:5px;">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                            <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                        </svg>
                        Print Both Receipts
                    `;
                    splitControls.appendChild(printBtn);
                }
            }
        } else {
            console.error('Payment failed:', res.message);
            // Re-enable button on error
            if (completeBtn) {
                completeBtn.disabled = false;
                completeBtn.innerHTML = originalBtnText;
            }
            alert('Error: ' + res.message);
        }
    })
    .catch(err => {
        console.error('Split receipt payment error:', err);
        console.error('Error details:', err.message, err.stack);
        // Re-enable button on error
        if (completeBtn) {
            completeBtn.disabled = false;
            completeBtn.innerHTML = originalBtnText;
        }
        alert('Payment failed: ' + err.message + '. Please check the browser console for details.');
    });
}

            function showSplitReceiptPrintPreview(receiptNumber) {
    const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
    const receiptNumEl = document.getElementById(`splitReceipt${receiptNumber}Number`);
    const receiptTotalEl = document.getElementById(`splitReceipt${receiptNumber}Total`);
    const receiptNum = receiptNumEl ? receiptNumEl.textContent : `SPLIT-${receiptNumber}`;
    const receiptTotal = receiptTotalEl ? receiptTotalEl.textContent : '₱0.00';
    const date = new Date().toLocaleString();
    const employeeName = EMPLOYEE_NAME;
    
    const printPreviewHTML = `
        <div style="padding: 20px;">
            <h3 style="margin-bottom: 15px; color: #1a1a2e;">Print Preview - Receipt ${receiptNumber}</h3>
            
            <div style="background: white; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; font-family: monospace; border-radius: 8px;overflow-y:auto;max-height:450px;">
                <div style="text-align: center; margin-bottom: 10px; border-bottom: 1px dashed #ddd; padding-bottom: 10px;max-height:450px;overflow-y:auto">
                    <h4 style="margin: 0; color: #e91e63; font-size: 18px;">Altiere</h4>
                    <div style="font-size: 12px; color: #666;">Receipt #: ${receiptNum}</div>
                    <div style="font-size: 12px; color: #666;">Date: ${date}</div>
                    <div style="font-size: 13px; font-weight: bold; margin: 8px 0; padding: 5px; background: ${receiptNumber === 1 ? '#ffeef2' : '#f2eef8'}; color: ${receiptNumber === 1 ? '#e91e63' : '#c2185b'}; border-radius: 4px;">
                        SPLIT RECEIPT ${receiptNumber}
                    </div>
                </div>
                
                <div style="max-height: 300px; overflow-y: auto; margin-bottom: 15px; padding-right: 5px;">
                    <div style="margin-bottom: 10px;">
                        ${items.map(item => `
                            <div style="display: flex; justify-content: space-between; margin: 8px 0; font-size: 13px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600;">${item.product_name} <span style="font-size: 11px; color: #666;">(${item.size_name})</span></div>
                                    <div style="font-size: 11px; color: #888; margin-top: 3px;">${item.quantity} × ₱${item.final_price.toFixed(2)}</div>
                                </div>
                                <div style="font-weight: bold; text-align: right;">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div style="border-top: 1px dashed #ccc; padding-top: 10px;">
                    <div style="font-size: 13px;">
                        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                            <span>Subtotal:</span>
                            <span>₱${items.reduce((s,i) => s + i.final_price * i.quantity, 0).toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                            <span>Tax (12%):</span>
                            <span>${document.getElementById(`splitReceipt${receiptNumber}Tax`)?.textContent || '₱0.00'}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                            <span>Discount:</span>
                            <span>${document.getElementById(`splitReceipt${receiptNumber}Discount`)?.textContent || '₱0.00'}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin: 8px 0; font-weight: bold; border-top: 2px solid #000; padding-top: 8px; font-size: 14px;">
                            <span>TOTAL:</span>
                            <span>${receiptTotal}</span>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; font-size: 11px; color: #666; margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ccc;">
                    <div>Cashier: ${employeeName}</div>
                    <div style="margin-top: 5px;">Thank you for shopping with us!</div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" onclick="printIndividualSplitReceipt(${receiptNumber})" style="flex: 1;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right:5px;">
                        <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                        <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                    </svg>
                    Print Receipt ${receiptNumber}
                </button>
                ${splitReceipt1Paid && splitReceipt2Paid ? `
                    <button class="btn btn-primary" onclick="printSplitReceipts(); closeCustomModal();" style="flex: 1;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right:5px;">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                            <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                        </svg>
                        Print Both Receipts
                    </button>
                ` : ''}
                <button class="btn btn-secondary" onclick="closeCustomModal()">Print Later</button>
            </div>
        </div>
    `;
    
    showCustomModal('Print Preview', printPreviewHTML);
}
              
function updateCancelSplitButton() {
    const cancelBtn = document.querySelector('.split-controls .btn-secondary');
    if (cancelBtn) {
        if (splitReceipt1Paid || splitReceipt2Paid) {
            cancelBtn.disabled = true;
            cancelBtn.style.opacity = '0.5';
            cancelBtn.style.cursor = 'not-allowed';
            cancelBtn.title = 'Cannot cancel split receipt after payment has been made';
        } else {
            cancelBtn.disabled = false;
            cancelBtn.style.opacity = '1';
            cancelBtn.style.cursor = 'pointer';
            cancelBtn.title = '';
        }
    }
}

function printIndividualSplitReceipt(receiptNumber = null) {
    if (!receiptNumber) {
        receiptNumber = splitReceipt2Paid ? 2 : (splitReceipt1Paid ? 1 : null);
    }
    
    if (!receiptNumber) {
        alert('No paid receipt to print.');
        return;
    }
    
    const items = receiptNumber === 1 ? splitReceipt1Items : splitReceipt2Items;
    const receiptNumEl = document.getElementById(`splitReceipt${receiptNumber}Number`);
    const receiptTotalEl = document.getElementById(`splitReceipt${receiptNumber}Total`);
    const receiptNum = receiptNumEl ? receiptNumEl.textContent : `SPLIT-${receiptNumber}`;
    const receiptTotal = receiptTotalEl ? receiptTotalEl.textContent : '₱0.00';
    const date = new Date().toLocaleString();
    const employeeName = EMPLOYEE_NAME;
    
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Split Receipt ${receiptNumber}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 10px; margin: 0; }
                .receipt { width: 80mm; margin: 0 auto; padding: 10px; border: 1px solid #000; }
                .header { text-align: center; margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
                .header h2 { margin: 0; font-size: 16px; }
                .header p { margin: 3px 0; font-size: 11px; color: #666; }
                .items { margin: 10px 0; max-height: 400px; overflow-y: auto; }
                .item { display: flex; justify-content: space-between; margin: 6px 0; font-size: 12px; padding-bottom: 6px; border-bottom: 1px dotted #eee; }
                .item-details { flex: 1; }
                .item-name { font-weight: bold; }
                .item-size { font-size: 10px; color: #666; }
                .item-total { font-weight: bold; }
                .summary { border-top: 1px dashed #000; margin-top: 10px; padding-top: 10px; }
                .summary-row { display: flex; justify-content: space-between; margin: 4px 0; font-size: 12px; }
                .total { font-weight: bold; border-top: 2px solid #000; padding-top: 8px; margin-top: 8px; font-size: 13px; }
                .footer { text-align: center; margin-top: 15px; font-size: 10px; color: #666; border-top: 1px dashed #000; padding-top: 10px; }
                .split-indicator { color: ${receiptNumber === 1 ? '#e91e63' : '#c2185b'}; font-weight: bold; margin: 8px 0; padding: 4px; background: ${receiptNumber === 1 ? '#ffeef2' : '#f2eef8'}; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <h2>Altiere</h2>
                    <p>Receipt #: ${receiptNum}</p>
                    <p>Date: ${date}</p>
                    <div class="split-indicator">SPLIT RECEIPT ${receiptNumber}</div>
                </div>
                <div class="items">
                    ${items.map(item => `
                        <div class="item">
                            <div class="item-details">
                                <div class="item-name">${item.product_name} <span class="item-size">(${item.size_name})</span></div>
                                <div style="font-size: 10px; color: #888;">${item.quantity} × ₱${item.final_price.toFixed(2)}</div>
                            </div>
                            <div class="item-total">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                        </div>
                    `).join('')}
                </div>
                <div class="summary">
                    <div class="summary-row"><span>Subtotal:</span><span>₱${items.reduce((s,i) => s + i.final_price * i.quantity, 0).toFixed(2)}</span></div>
                    <div class="summary-row"><span>Tax (12%):</span><span>${document.getElementById(`splitReceipt${receiptNumber}Tax`)?.textContent || '₱0.00'}</span></div>
                    <div class="summary-row"><span>Discount:</span><span>${document.getElementById(`splitReceipt${receiptNumber}Discount`)?.textContent || '₱0.00'}</span></div>
                    <div class="summary-row total"><span>TOTAL:</span><span>${receiptTotal}</span></div>
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
    
    // Check if both receipts are paid
    const isBothPaid = splitReceipt1Paid && splitReceipt2Paid;
    
    // If this was the last receipt to print (receipt 2) OR both are already paid
    if (receiptNumber === 2 || isBothPaid) {
        // Clear the items and go back to main receipt
        setTimeout(() => {
            if (isBothPaid || confirm('Receipt printed. Do you want to close the split receipt view?')) {
                closeSplitReceiptView();
            }
        }, 300);
    }
}
             

function completeSplitReceipt() {
    showFunctionFeedback('Both split receipts paid successfully!');
    
    const printIndividualBtn = document.getElementById('printIndividualSplitBtn');
    if (printIndividualBtn) {
        printIndividualBtn.style.display = 'block';
        printIndividualBtn.onclick = () => printSplitReceipts();
        
        printIndividualBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/>
                <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
            </svg>
            Print Both Receipts
        `;
    }
    
    // Update cancel button state
    updateCancelSplitButton();
}

function cancelSplitReceipt() {
    if (splitReceipt1Paid || splitReceipt2Paid) {
        alert('Cannot cancel split receipt after payment has been made.');
        return;
    }
    
    if (confirm('Cancel split receipt? All items will be returned to the main cart.')) {
        const splitContainer = document.getElementById('splitReceiptContainer');
        const mainReceipt = document.getElementById('mainReceipt');
        
        if (splitContainer) splitContainer.style.display = 'none';
        if (mainReceipt) mainReceipt.style.display = 'block';
        
        splitReceiptActive = false;
        splitReceipt1Items = [];
        splitReceipt2Items = [];
        splitReceipt1Paid = false;
        splitReceipt2Paid = false;
        splitPaymentData.receipt1 = null;
        splitPaymentData.receipt2 = null;
        
        const printBtn = document.getElementById('printIndividualSplitBtn');
        if (printBtn) printBtn.style.display = 'none';
        
        // Re-enable F keys
        updateFKeyState();
        
        showFunctionFeedback('Split receipt cancelled');
    }
}

function printSplitReceipts() {
    if (!splitReceipt1Paid) {
        alert('Please pay Receipt 1 before printing.');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    const receipt1NumEl = document.getElementById('splitReceipt1Number');
    const receipt2NumEl = document.getElementById('splitReceipt2Number');
    const receipt1TotalEl = document.getElementById('splitReceipt1Total');
    const receipt2TotalEl = document.getElementById('splitReceipt2Total');
    
    const receipt1Num = receipt1NumEl ? receipt1NumEl.textContent : 'SPLIT-1';
    const receipt2Num = receipt2NumEl ? receipt2NumEl.textContent : 'SPLIT-2';
    const receipt1Total = receipt1TotalEl ? receipt1TotalEl.textContent : '₱0.00';
    const receipt2Total = receipt2TotalEl ? receipt2TotalEl.textContent : '₱0.00';
    const date = new Date().toLocaleString();
    const employeeName = EMPLOYEE_NAME;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Split Receipts</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 10px; margin: 0; }
                .receipt { width: 80mm; margin: 0 auto 20px auto; padding: 10px; border: 1px solid #000; page-break-inside: avoid; }
                .header { text-align: center; margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
                .header h2 { margin: 0; font-size: 16px; }
                .header p { margin: 3px 0; font-size: 11px; color: #666; }
                .items { margin: 10px 0; max-height: 400px; overflow-y: auto; }
                .item { display: flex; justify-content: space-between; margin: 6px 0; font-size: 12px; padding-bottom: 6px; border-bottom: 1px dotted #eee; }
                .item-details { flex: 1; }
                .item-name { font-weight: bold; }
                .item-size { font-size: 10px; color: #666; }
                .item-total { font-weight: bold; }
                .summary { border-top: 1px dashed #000; margin-top: 10px; padding-top: 10px; }
                .summary-row { display: flex; justify-content: space-between; margin: 4px 0; font-size: 12px; }
                .total { font-weight: bold; border-top: 2px solid #000; padding-top: 8px; margin-top: 8px; font-size: 13px; }
                .footer { text-align: center; margin-top: 15px; font-size: 10px; color: #666; border-top: 1px dashed #000; padding-top: 10px; }
                .split-divider { text-align: center; margin: 20px 0; color: #666; font-weight: bold; font-size: 12px; }
                .split-indicator { font-weight: bold; margin: 8px 0; padding: 4px; border-radius: 3px; }
                .split-1 { color: #e91e63; background: #ffeef2; }
                .split-2 { color: #c2185b; background: #f2eef8; }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <h2>Altiere</h2>
                    <p>Receipt #: ${receipt1Num}</p>
                    <p>Date: ${date}</p>
                    <div class="split-indicator split-1">SPLIT RECEIPT 1</div>
                </div>
                <div class="items">
                    ${splitReceipt1Items.map(item => `
                        <div class="item">
                            <div class="item-details">
                                <div class="item-name">${item.product_name} <span class="item-size">(${item.size_name})</span></div>
                                <div style="font-size: 10px; color: #888;">${item.quantity} × ₱${item.final_price.toFixed(2)}</div>
                            </div>
                            <div class="item-total">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                        </div>
                    `).join('')}
                </div>
                <div class="summary">
                    <div class="summary-row"><span>Subtotal:</span><span>₱${splitReceipt1Items.reduce((s,i) => s + i.final_price * i.quantity, 0).toFixed(2)}</span></div>
                    <div class="summary-row"><span>Tax (12%):</span><span>${document.getElementById('splitReceipt1Tax')?.textContent || '₱0.00'}</span></div>
                    <div class="summary-row"><span>Discount:</span><span>${document.getElementById('splitReceipt1Discount')?.textContent || '₱0.00'}</span></div>
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
                    <p>Receipt #: ${receipt2Num}</p>
                    <p>Date: ${date}</p>
                    <div class="split-indicator split-2">SPLIT RECEIPT 2</div>
                </div>
                <div class="items">
                    ${splitReceipt2Items.map(item => `
                        <div class="item">
                            <div class="item-details">
                                <div class="item-name">${item.product_name} <span class="item-size">(${item.size_name})</span></div>
                                <div style="font-size: 10px; color: #888;">${item.quantity} × ₱${item.final_price.toFixed(2)}</div>
                            </div>
                            <div class="item-total">₱${(item.quantity * item.final_price).toFixed(2)}</div>
                        </div>
                    `).join('')}
                </div>
                <div class="summary">
                    <div class="summary-row"><span>Subtotal:</span><span>₱${splitReceipt2Items.reduce((s,i) => s + i.final_price * i.quantity, 0).toFixed(2)}</span></div>
                    <div class="summary-row"><span>Tax (12%):</span><span>${document.getElementById('splitReceipt2Tax')?.textContent || '₱0.00'}</span></div>
                    <div class="summary-row"><span>Discount:</span><span>${document.getElementById('splitReceipt2Discount')?.textContent || '₱0.00'}</span></div>
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
    
    // Automatically close split view after printing both receipts
    setTimeout(() => {
        closeSplitReceiptView();
    }, 300);
}

function closeSplitReceiptView() {
    const splitContainer = document.getElementById('splitReceiptContainer');
    const mainReceipt = document.getElementById('mainReceipt');
    
    if (splitContainer) splitContainer.style.display = 'none';
    if (mainReceipt) mainReceipt.style.display = 'block';
    
    cart = [];
    selectedItemIndex = -1;
    globalDiscount = 0;
    globalDiscountType = '';
    globalDiscountIdNumber = '';
    renderCart();
    
    splitReceiptActive = false;
    splitReceipt1Items = [];
    splitReceipt2Items = [];
    splitReceipt1Paid = false;
    splitReceipt2Paid = false;
    splitPaymentData.receipt1 = null;
    splitPaymentData.receipt2 = null;
    
    const printBtn = document.getElementById('printIndividualSplitBtn');
    if (printBtn) printBtn.style.display = 'none';
    
    // Re-enable F keys
    updateFKeyState();
    
    fetch('get_next_receipt_number.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                receiptCounter = parseInt(res.next_receipt_number);
                const receiptNumberEl = document.getElementById('receiptNumber');
                if (receiptNumberEl) {
                    receiptNumberEl.textContent = res.next_receipt_number;
                }
                originalReceiptNumber = res.next_receipt_number;
            }
        });
    
    if (searchInput) searchInput.focus();
}