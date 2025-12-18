// F2 - Transaction History Functions

function viewTransactionHistory() {
    document.getElementById('transactionModal').style.display = 'block';
    loadTransactionHistory();
}

function loadTransactionHistory(date = null) {
    if (!date) {
        // Get local date in YYYY-MM-DD format
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        date = `${year}-${month}-${day}`;
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

function filterTransactions() {
    const date = document.getElementById('transactionDate').value;
    const search = document.getElementById('transactionSearch').value;
    
    if (search) {
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
// transactions.js - Key Functions with Authorization Support

// Void Transaction with Authorization
function voidTransaction(saleId, saleNumber) {
    // Show authorization modal first
    showAuthorizationModal(
        `VOID TRANSACTION #${saleNumber}`,
        (extraData, authData) => {
            // After authorization, show the void reason modal
            showVoidReasonModal(saleId, saleNumber, authData);
        },
        { saleId, saleNumber }
    );
}

function showVoidReasonModal(saleId, saleNumber, authData) {
    const modalHTML = `
        <div style="padding: 20px;">
            <h3 style="margin-bottom: 15px; color: #1a1a2e;">Void Transaction</h3>
            
            <div style="background: #ffeef2; border-left: 4px solid #e91e63; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="margin: 0; color: #c2185b; font-size: 14px;">
                    ⚠️ Transaction: <strong>${saleNumber}</strong>
                </p>
                <p style="margin: 5px 0 0 0; color: #c2185b; font-size: 12px;">
                    Authorized by: ${authData.employee_name} (${authData.position})
                </p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                    Reason for Void: <span style="color: #d32f2f;">*</span>
                </label>
                <textarea id="voidReason" 
                        style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; min-height: 100px; font-size: 14px; resize: vertical;"
                        placeholder="Please provide a reason for voiding this transaction (e.g., customer request, wrong order, system error)..."
                        autofocus></textarea>
                <div id="voidReasonError" style="color: #d32f2f; font-size: 12px; margin-top: 5px; display: none;">
                    Please provide a detailed reason (at least 10 characters)
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" id="confirmVoidBtn" style="flex: 1; background: #e91e63;">
                    Confirm Void
                </button>
                <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
            </div>
        </div>
    `;
    
    showCustomModal('Void Transaction', modalHTML);
    setTimeout(() => document.getElementById('voidReason').focus(), 100);
    
    // Add event listener AFTER the modal is shown
    setTimeout(() => {
        const confirmBtn = document.getElementById('confirmVoidBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                confirmVoidTransaction(saleId, authData);
            });
        }
    }, 100);
}

function confirmVoidTransaction(saleId, authData) {
    const reason = document.getElementById('voidReason').value.trim();
    const errorDiv = document.getElementById('voidReasonError');
    
    if (!reason || reason.length < 10) {
        errorDiv.textContent = 'Please provide a detailed reason (at least 10 characters)';
        errorDiv.style.display = 'block';
        document.getElementById('voidReason').focus();
        return;
    }
    
    const btn = document.getElementById('confirmVoidBtn');
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span style="opacity: 0.7;">Processing...</span>';
    btn.disabled = true;
    
    const now = new Date();
    const dateTime = now.toLocaleString();
    
    fetch('void_transaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            sale_id: saleId,
            void_reason: reason,
            voided_by: EMPLOYEE_ID,
            authorized_by: authData.employee_id,
            authorized_by_name: authData.employee_name,
            authorized_by_position: authData.position,
            void_timestamp: dateTime
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showFunctionFeedback('Transaction voided successfully');
            closeCustomModal();
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

function showTransactionDetailsModal(sale, items) {
    const isVoided = sale.status === 'voided';
    
    const modalHTML = `
        <div class="transaction-detail-modal">
            <div class="receipt-preview">
                <div class="receipt-preview-header">
                    <h3>Altiere</h3>
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
                                ${item.price_change_authorized_by && item.price_change_authorized_position ? `
                                    <div style="font-size: 10px; color: #2196f3; margin-top: 3px; padding: 3px 6px; background: #e3f2fd; border-radius: 3px; display: inline-block;">
                                        🔒 Price changed by: ${item.price_change_authorized_by} (${item.price_change_authorized_position})
                                    </div>
                                ` : ''}
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
                
                ${sale.notes || sale.discount_authorized_by || (sale.status === 'voided' && sale.voided_by_name) ? `
                    <div style="margin-top: 15px; padding: 12px; background: #f9f9f9; border-radius: 6px; font-size: 11px; font-family: monospace; line-height: 1.4; white-space: pre-wrap; border: 1px solid #e0e0e0;">
                        <strong style="display: block; margin-bottom: 8px; color: #333;">Transaction Notes:</strong>
                        ${sale.notes || ''}
                        
                        ${(sale.discount_authorized_by && sale.discount_authorized_position) ? `
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #4caf50;">
                                <strong style="color: #4caf50;">✓ Custom Discount Authorized by:</strong><br/>
                                ${sale.discount_authorized_by} (${sale.discount_authorized_position})
                            </div>
                        ` : ''}
                        
                        ${(sale.status === 'voided' && sale.voided_by_name && sale.voided_by_position) ? `
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ff9800;">
                                <strong style="color: #ff9800;">⚠️ Transaction Voided by:</strong><br/>
                                ${sale.voided_by_name} (${sale.voided_by_position})
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
                
                ${isVoided && sale.void_reason ? `
                    <div class="void-note">
                        <strong>Void Reason:</strong>
                        <p>${sale.void_reason}</p>
                        ${sale.voided_by_name ? `
                            <p style="margin-top: 5px; font-size: 11px;">
                                Voided by: ${sale.voided_by_name}${sale.voided_by_position ? ' (' + sale.voided_by_position + ')' : ''} on ${new Date(sale.voided_at).toLocaleString()}
                            </p>
                        ` : ''}
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

function reprintTransaction(saleId) {
    alert(`Reprinting receipt for transaction #${saleId}`);
}

function printTransactionReceipt(saleId) {
    window.print();
}

function closeTransactionModal() {
    document.getElementById('transactionModal').style.display = 'none';
}

// Utility Functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}