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