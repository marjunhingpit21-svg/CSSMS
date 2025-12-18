<!-- Right: Receipt -->
<div class="receipt-panel">
    <!-- Main receipt (shown by default) -->
    <div id="mainReceipt">
        <div class="receipt-header">
            <h2>Altiere</h2>
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
                <button class="btn-payment btn-card" onclick="openPaymentModal('card')">Bank Transfer</button>
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
        <div class="receipt" id="splitReceipt1">
            <div class="receipt-header">
                <h2>Altiere</h2>
                <p style="font-size:11px;margin-top:4px;">Receipt #<span id="splitReceipt1Number">00001-A</span></p>
                <p style="font-size:11px;margin-bottom:10px;color:#e91e63;font-weight:600;">SPLIT RECEIPT 1</p>
            </div>

            <div class="receipt-items" id="splitReceipt1Items">
                <div class="empty-cart">No items</div>
            </div>

            <div class="receipt-summary">
                <div class="summary-row"><span>Subtotal:</span><span id="splitReceipt1Subtotal">₱0.00</span></div>
                <div class="summary-row"><span>Tax (12%):</span><span id="splitReceipt1Tax">₱0.00</span></div>
                <div class="summary-row"><span>Discount:</span><span id="splitReceipt1Discount">₱0.00</span></div>
                <div class="summary-row total"><span>TOTAL:</span><span id="splitReceipt1Total">₱0.00</span></div>
            </div>

            <div class="receipt-actions">
                <div class="payment-modes">
                    <button class="btn-payment btn-cash" onclick="openSplitPaymentModal(1, 'cash')">Cash</button>
                    <button class="btn-payment btn-card" onclick="openSplitPaymentModal(1, 'card')">Bank Transfer</button>
                    <button class="btn-payment btn-ewallet" onclick="openSplitPaymentModal(1, 'ewallet')">E-Wallet</button>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button class="btn btn-secondary" onclick="printIndividualSplitReceipt(1)" style="flex: 1;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="vertical-align: middle; margin-right: 5px;">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                        </svg>
                        Print Receipt
                    </button>
                </div>
            </div>
        </div>

        <!-- Divider between split receipts -->
        <div class="split-receipt-divider">
            <div class="divider-line"></div>
            <div class="divider-text">SPLIT TRANSACTION</div>
            <div class="divider-line"></div>
        </div>

        <!-- Receipt 2 -->
        <div class="receipt" id="splitReceipt2">
            <div class="receipt-header">
                <h2>Altiere</h2>
                <p style="font-size:11px;margin-top:4px;">Receipt #<span id="splitReceipt2Number">00001-B</span></p>
                <p style="font-size:11px;margin-bottom:10px;color:#c2185b;font-weight:600;">SPLIT RECEIPT 2</p>
            </div>

            <div class="receipt-items" id="splitReceipt2Items">
                <div class="empty-cart">No items</div>
            </div>

            <div class="receipt-summary">
                <div class="summary-row"><span>Subtotal:</span><span id="splitReceipt2Subtotal">₱0.00</span></div>
                <div class="summary-row"><span>Tax (12%):</span><span id="splitReceipt2Tax">₱0.00</span></div>
                <div class="summary-row"><span>Discount:</span><span id="splitReceipt2Discount">₱0.00</span></div>
                <div class="summary-row total"><span>TOTAL:</span><span id="splitReceipt2Total">₱0.00</span></div>
            </div>

            <div class="receipt-actions">
                <div class="payment-modes">
                    <button class="btn-payment btn-cash" onclick="openSplitPaymentModal(2, 'cash')">Cash</button>
                    <button class="btn-payment btn-card" onclick="openSplitPaymentModal(2, 'card')">Bank Transfer</button>
                    <button class="btn-payment btn-ewallet" onclick="openSplitPaymentModal(2, 'ewallet')">E-Wallet</button>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button class="btn btn-secondary" onclick="printIndividualSplitReceipt(2)" style="flex: 1;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="vertical-align: middle; margin-right: 5px;">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="6" y="14" width="12" height="8" stroke-width="2"/>
                        </svg>
                        Print Receipt
                    </button>
                </div>
            </div>
        </div>

        <!-- Split receipt controls -->
        <div class="split-controls">
            <button class="btn btn-secondary" onclick="cancelSplitReceipt()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="margin-right: 5px;">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <line x1="15" y1="9" x2="9" y2="15" stroke-width="2"/>
                    <line x1="9" y1="9" x2="15" y2="15" stroke-width="2"/>
                </svg>
                Cancel Split
            </button>
        </div>
    </div>
</div>