// F-Key Functions (F1-F8)

// Initialize F-key state on page load
document.addEventListener('DOMContentLoaded', function() {
    updateFKeyState();
});

// F1 - Apply Discount
function applyDiscount() {
    if (cart.length === 0) {
        alert('Please add items to the cart first.');
        return;
    }

    const modalHTML = `
        <div style="padding: 20px;">
           <div style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 12px;">Discount Type:</label>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                    <button class="discount-type-btn ${globalDiscountType === 'pwd' ? 'selected' : ''}" 
                            onclick="selectDiscountType('pwd', 20)" 
                            style="padding: 12px; border: 2px solid ${globalDiscountType === 'pwd' ? '#2196f3' : '#e5e7eb'}; 
                                   border-radius: 8px; background: ${globalDiscountType === 'pwd' ? '#e3f2fd' : 'white'}; 
                                   cursor: pointer; text-align: center;">
                        <div style="font-weight: 600; color: ${globalDiscountType === 'pwd' ? '#2196f3' : '#666'};">PWD</div>
                        <div style="font-size: 12px; color: ${globalDiscountType === 'pwd' ? '#2196f3' : '#999'};">20% Discount</div>
                    </button>
                    <button class="discount-type-btn ${globalDiscountType === 'senior' ? 'selected' : ''}" 
                            onclick="selectDiscountType('senior', 20)" 
                            style="padding: 12px; border: 2px solid ${globalDiscountType === 'senior' ? '#4caf50' : '#e5e7eb'}; 
                                   border-radius: 8px; background: ${globalDiscountType === 'senior' ? '#e8f5e9' : 'white'}; 
                                   cursor: pointer; text-align: center;">
                        <div style="font-weight: 600; color: ${globalDiscountType === 'senior' ? '#4caf50' : '#666'};">Senior</div>
                        <div style="font-size: 12px; color: ${globalDiscountType === 'senior' ? '#4caf50' : '#999'};">20% Discount</div>
                    </button>
                    <button class="discount-type-btn ${globalDiscountType === 'others' ? 'selected' : ''}" 
                            onclick="selectDiscountType('others', 0)" 
                            style="padding: 12px; border: 2px solid ${globalDiscountType === 'others' ? '#ff9800' : '#e5e7eb'}; 
                                   border-radius: 8px; background: ${globalDiscountType === 'others' ? '#fff3e0' : 'white'}; 
                                   cursor: pointer; text-align: center;">
                        <div style="font-weight: 600; color: ${globalDiscountType === 'others' ? '#ff9800' : '#666'};">Others</div>
                        <div style="font-size: 12px; color: ${globalDiscountType === 'others' ? '#ff9800' : '#999'};">Custom %</div>
                    </button>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;" id="discountIdNumberSection" style="display: ${globalDiscountType ? 'block' : 'none'}">
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">ID Number:</label>
                <input type="text" id="discountIdNumber" 
                       style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px;"
                       placeholder="Enter ID number"
                       value="${globalDiscountIdNumber}">
            </div>
            
            <div style="margin-bottom: 20px;" id="othersDiscountSection" style="display: ${globalDiscountType === 'others' ? 'block' : 'none'}">
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">Custom Discount Percentage (0-100):</label>
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
}

function selectDiscountType(type, percentage) {
    globalDiscountType = type;
    if (type !== 'others') {
        globalDiscount = percentage;
    }
    applyDiscount();
}

function saveDiscount() {
    let discount = globalDiscount;
    
    if (globalDiscountType === 'others') {
        const discountInput = document.getElementById('discountInput');
        discount = parseFloat(discountInput?.value) || 0;
    }
    
    const idNumber = document.getElementById('discountIdNumber')?.value.trim() || '';
    
    if (globalDiscountType && !idNumber) {
        alert('Please enter ID number for the discount.');
        return;
    }
    
    if (discount < 0 || discount > 100) {
        alert('Invalid discount. Please enter a value between 0 and 100.');
        return;
    }
    
    globalDiscount = discount;
    globalDiscountIdNumber = idNumber;
    
    updateTotals();
    showFunctionFeedback(`${globalDiscountType ? globalDiscountType.toUpperCase() + ' ' : ''}Discount applied: ${discount}%`);
    closeCustomModal();
}

// F3 - Delete All Items
function deleteAllItems() {
    if (cart.length === 0) {
        showFunctionFeedback('Cart is already empty');
        return;
    }
    
    if (confirm(`Are you sure you want to delete all ${cart.length} item(s) from the cart?`)) {
        cart = [];
        selectedItemIndex = -1;
        globalDiscount = 0;
        globalDiscountType = '';
        globalDiscountIdNumber = '';
        renderCart();
        showFunctionFeedback('All items deleted from cart');
    }
}

// F4 - Change Price
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

// F5 - Add Notes
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
                          placeholder="Enter notes for this item..."
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

// F6 - Change Quantity
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

// F8 - Delete Selected Item
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

// ESC - Logout
// ESC - Logout
function logoutCashier() {
    if (cart.length > 0) {
        if (!confirm('You have items in the cart. Are you sure you want to logout?')) {
            return;
        }
    }
    
    if (confirm('Logout from POS system?.')) {
        window.location.href = '../logout.php';  // Changed from index.php to logout.php
    }
}

// Update F-key state when split receipt is active
function updateFKeyState() {
    const fKeys = ['fn-f1', 'fn-f2', 'fn-f3', 'fn-f4', 'fn-f5', 'fn-f6', 'fn-f7', 'fn-f8'];
    fKeys.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.disabled = splitReceiptActive;
            if (splitReceiptActive) {
                // Make button look disabled (greyish)
                btn.style.opacity = '0.4';
                btn.style.cursor = 'not-allowed';
                btn.style.pointerEvents = 'none';
                btn.style.background = '#d3d3d3';
                btn.style.color = '#808080';
                btn.style.border = '2px solid #c0c0c0';
            } else {
                // Re-enable button (restore original appearance)
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
                btn.style.pointerEvents = 'auto';
                btn.style.background = '';
                btn.style.color = '';
                btn.style.border = '';
            }
        }
    });
}