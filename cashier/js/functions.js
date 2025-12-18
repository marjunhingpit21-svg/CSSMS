// F-Key Functions (F1-F8)

// Initialize F-key state on page load
document.addEventListener('DOMContentLoaded', function() {
    updateFKeyState();
});

// Add a flag to track if "Others" discount authorization was successful
let othersDiscountAuthorized = false;

// Function to format notes with proper spacing
function formatNotesForDisplay(notes) {
    if (!notes) return '';
    
    // Replace single newlines with double newlines for better spacing
    return notes.replace(/\n\n+/g, '\n\n'); // Ensure consistent spacing
}

// F1 - Apply Discount
function applyDiscount() {
    if (cart.length === 0) {
        alert('Please add items to the cart first.');
        return;
    }
    
    // If Others discount is selected but not authorized, trigger authorization
    if (globalDiscountType === 'others' && !othersDiscountAuthorized) {
        selectDiscountType('others', 0);
        return;
    }
    
    showDiscountModalWithOthersSelected();
}

function selectDiscountType(type, percentage) {
    if (type === 'others') {
        // For "Others" discount, require authorization first
        showAuthorizationModal(
            'APPLY CUSTOM DISCOUNT',
            (extraData, authData) => {
                // After SUCCESSFUL authorization - store auth data globally
                othersDiscountAuthorized = true;
                globalDiscountType = 'others'; // Set type to others
                globalDiscount = 0; // Reset to 0 for custom input
                
                // Store authorization data globally for later use in payment
                window.othersDiscountAuthData = authData;
                
                showDiscountModalWithOthersSelected(authData); // Show discount modal with Others selected
            },
            null, // extraData
            () => {
                // Callback when authorization is cancelled or failed
                // Reset everything
                othersDiscountAuthorized = false;
                globalDiscountType = ''; // Clear discount type
                window.othersDiscountAuthData = null; // Clear auth data
                showFunctionFeedback('Custom discount authorization cancelled');
            }
        );
    } else {
        globalDiscountType = type;
        globalDiscount = percentage;
        othersDiscountAuthorized = false; // Reset for non-others discounts
        window.othersDiscountAuthData = null; // Clear auth data for non-others
        applyDiscount(); // Show discount modal immediately for non-others
    }
}

// New function to show discount modal with Others already selected
function showDiscountModalWithOthersSelected(authData = null) {
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
            
            ${authData ? `
                <div style="background: #e8f5e9; border-left: 4px solid #4caf50; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="margin: 0; color: #2e7d32; font-size: 12px;">
                        ✓ Authorized by: ${authData.employee_name} (${authData.position})
                    </p>
                </div>
            ` : ''}
            
            <div style="margin-bottom: 20px;" id="discountIdNumberSection">
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
            
            <div style="margin-bottom: 20px; display: ${globalDiscountType === 'others' ? 'block' : 'none'};" id="discountReasonSection">
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                    Reason for Custom Discount: <span style="color: #d32f2f;">*</span>
                </label>
                <textarea id="discountReason" 
                       style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; min-height: 80px; font-size: 14px; resize: vertical;"
                       placeholder="Please provide a reason for the custom discount (e.g., special promotion, customer request, price adjustment)..."></textarea>
                <div id="discountReasonError" style="color: #d32f2f; font-size: 12px; margin-top: 5px; display: none;">
                    Please provide a reason for the custom discount
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" onclick="saveDiscount()" style="flex: 1;">
                    Apply Discount
                </button>
                <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
            </div>
        </div>
    `;
    
    showCustomModal('Apply Discount', modalHTML);
    
    // Auto-focus the discount input field if Others is selected
    if (globalDiscountType === 'others') {
        setTimeout(() => {
            const discountInput = document.getElementById('discountInput');
            if (discountInput) {
                discountInput.focus();
                discountInput.select();
            }
        }, 100);
    }
}

// Update the saveDiscount function
function saveDiscount() {
    let discount = globalDiscount;
    let discountInput = null;
    let discountReason = '';
    let discountReasonError = null;
    let discountAuthName = '';
    let discountAuthPosition = '';
    
    if (globalDiscountType === 'others') {
        discountInput = document.getElementById('discountInput');
        discountReason = document.getElementById('discountReason')?.value.trim() || '';
        discountReasonError = document.getElementById('discountReasonError');
        discount = parseFloat(discountInput?.value) || 0;
        
        // Validate reason for Others discount
        if (!discountReason) {
            if (discountReasonError) {
                discountReasonError.textContent = 'Please provide a reason for the custom discount';
                discountReasonError.style.display = 'block';
            } else {
                alert('Please provide a reason for the custom discount.');
            }
            return;
        }
        
        // Store authorization info for Others discount
        globalDiscountAuthorizedBy = pendingAuthorization?.authData?.employee_name || 'System';
        globalDiscountAuthId = pendingAuthorization?.authData?.employee_id || null;
        globalDiscountAuthPosition = pendingAuthorization?.authData?.position || '';
        discountAuthName = globalDiscountAuthorizedBy;
        discountAuthPosition = globalDiscountAuthPosition;
    } else {
        // For PWD and Senior discounts, use the fixed percentage
        discount = globalDiscountType === 'pwd' || globalDiscountType === 'senior' ? 20 : 0;
    }
    
    const idNumber = document.getElementById('discountIdNumber')?.value.trim() || '';
    
    if (globalDiscountType && !idNumber) {
        alert('Please enter ID number for the discount.');
        return;
    }
    
    if (globalDiscountType === 'others' && (discount < 0 || discount > 100)) {
        alert('Invalid discount. Please enter a value between 0 and 100.');
        return;
    }
    
    // Create discount note for all cart items
    const now = new Date();
    const dateTime = now.toLocaleString();
    let discountNote = '';
    
    if (globalDiscountType === 'others') {
        discountNote = `[CUSTOM DISCOUNT ${dateTime}]: ${discount}% discount applied. ID: ${idNumber}. Reason: ${discountReason}. Authorized by: ${discountAuthName} (${discountAuthPosition}).`;
    } else if (globalDiscountType) {
        discountNote = `[${globalDiscountType.toUpperCase()} DISCOUNT ${dateTime}]: ${discount}% discount applied. ID: ${idNumber}.`;
    }
    
    // Add discount note to all cart items with proper formatting
    if (discountNote) {
        cart.forEach(item => {
            // Format existing notes with proper spacing
            const existingNotes = formatNotesForDisplay(item.notes || '');
            
            if (existingNotes) {
                item.notes = existingNotes + '\n\n' + discountNote;
            } else {
                item.notes = discountNote;
            }
        });
    }
    
    globalDiscount = discount;
    globalDiscountIdNumber = idNumber;
    
    updateTotals();
    showFunctionFeedback(`${globalDiscountType ? globalDiscountType.toUpperCase() + ' ' : ''}Discount applied: ${discount}%`);
    closeCustomModal();
    
    // Reset authorization flag after saving (only for Others)
    if (globalDiscountType === 'others') {
        othersDiscountAuthorized = false;
    }
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
        othersDiscountAuthorized = false; // Reset authorization flag
        renderCart();
        showFunctionFeedback('All items deleted from cart');
    }
}

// F4 - Change Price (with authorization)
function changePrice() {
    if (selectedItemIndex === -1 || !cart[selectedItemIndex]) {
        alert('Please add an item to the cart first.');
        return;
    }

    const item = cart[selectedItemIndex];
    
    // Show authorization modal first
    showAuthorizationModal(
        `CHANGE PRICE for "${item.product_name}"`,
        (extraData, authData) => {
            // After authorization, show price change modal
            showPriceChangeModal(item, authData);
        },
        { item }
    );
}

function showPriceChangeModal(item, authData) {
    const modalHTML = `
        <div style="padding: 20px;">
            <h3 style="margin-bottom: 15px; color: #1a1a2e;">Change Price</h3>
            
            <div style="background: #ffeef2; border-left: 4px solid #e91e63; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="margin: 0; color: #c2185b; font-size: 14px;">
                    Authorized by: ${authData.employee_name} (${authData.position})
                </p>
            </div>
            
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
            
            <div style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                    Reason for Price Change: <span style="color: #d32f2f;">*</span>
                </label>
                <textarea id="priceChangeReason" 
                       style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; min-height: 80px; font-size: 14px; resize: vertical;"
                       placeholder="Please provide a reason for changing the price (e.g., customer request, special discount, price adjustment)..."></textarea>
                <div id="priceChangeReasonError" style="color: #d32f2f; font-size: 12px; margin-top: 5px; display: none;">
                    Please provide a reason for the price change
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" id="changePriceBtn" style="flex: 1;">Change Price</button>
                <button class="btn btn-secondary" onclick="closeCustomModal()">Cancel</button>
            </div>
        </div>
    `;
    
    showCustomModal('Change Price', modalHTML);
    setTimeout(() => document.getElementById('priceInput').select(), 100);
    
    // Add event listener AFTER the modal is shown
    setTimeout(() => {
        const changePriceBtn = document.getElementById('changePriceBtn');
        if (changePriceBtn) {
            changePriceBtn.addEventListener('click', function() {
                savePrice(item, authData);
            });
        }
    }, 100);
}

function savePrice(item, authData) {
    const newPrice = parseFloat(document.getElementById('priceInput').value);
    const reason = document.getElementById('priceChangeReason')?.value.trim() || '';
    const errorDiv = document.getElementById('priceChangeReasonError');
    const authName = authData.employee_name;
    const authPosition = authData.position;
    
    if (isNaN(newPrice) || newPrice < 0) {
        alert('Invalid price. Please enter a valid amount.');
        return;
    }
    
    // Check if price is actually changing
    if (newPrice === item.final_price) {
        alert('No changes detected. The new price is the same as the current price.');
        return;
    }
    
    // Validate reason
    if (!reason) {
        if (errorDiv) {
            errorDiv.textContent = 'Please provide a reason for the price change';
            errorDiv.style.display = 'block';
        } else {
            alert('Please provide a reason for the price change.');
        }
        return;
    }

    // Find the item in cart (in case index changed)
    const itemIndex = cart.findIndex(cartItem => 
        cartItem.product_id === item.product_id && 
        cartItem.product_size_id === item.product_size_id
    );
    
    if (itemIndex === -1) {
        alert('Item not found in cart.');
        return;
    }

    // Store authorization info with the price change
    cart[itemIndex].final_price = newPrice;
    cart[itemIndex].discount = 0;
    cart[itemIndex].price_changed = true;
    cart[itemIndex].price_changed_by = authName;
    cart[itemIndex].price_change_position = authPosition; // Fixed variable name
    cart[itemIndex].price_change_auth_id = authData.employee_id;
    cart[itemIndex].price_change_reason = reason;
    
    // Create or update notes with price change information
    const now = new Date();
    const dateTime = now.toLocaleString();
    const priceChangeNote = `[PRICE CHANGE ${dateTime}]: Changed to ₱${newPrice.toFixed(2)}. Reason: ${reason}. Authorized by: ${authName} (${authPosition}).`;
    
    // Append to existing notes with proper formatting
    if (cart[itemIndex].notes) {
        // Format existing notes with proper spacing
        const formattedNotes = formatNotesForDisplay(cart[itemIndex].notes);
        cart[itemIndex].notes = formattedNotes + '\n\n' + priceChangeNote;
    } else {
        cart[itemIndex].notes = priceChangeNote;
    }
    
    // Update selected item index if needed
    if (selectedItemIndex === itemIndex) {
        selectedItemIndex = itemIndex;
    }
    
    renderCart();
    
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
                          style="width: 100%; padding: 15px; border: 2px solid #e5e7eb; border-radius: 8px; min-height: 120px; font-size: 16px; resize: vertical;">${formatNotesForDisplay(item.notes) || ''}</textarea>
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
function logoutCashier() {
    if (cart.length > 0) {
        if (!confirm('You have items in the cart. Are you sure you want to logout?')) {
            return;
        }
    }
    
    if (confirm('Logout from POS system?.')) {
        window.location.href = 'logout.php';  // Changed from index.php to logout.php
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

// Authorization System for Manager/Supervisor Actions

let pendingAuthorization = null;

// Show authorization modal
function showAuthorizationModal(action, successCallback, extraData = {}, failCallback = null) {
    pendingAuthorization = { 
        action, 
        successCallback, 
        extraData, 
        failCallback 
    };
    
    const modalHTML = `
        <div style="padding: 20px;">
            <h3 style="margin-bottom: 15px; color: #1a1a2e;">Authorization Required</h3>
            
            <div style="background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    ⚠️ Manager/Supervisor authorization required for:
                </p>
                <p style="margin: 8px 0 0 0; color: #856404; font-size: 14px; font-weight: 600;">
                    ${action}
                </p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                    Manager/Supervisor Employee Number:
                </label>
                <input type="text" id="authEmployeeNumber" 
                       style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px;"
                       placeholder="EMP-XXX"
                       autofocus>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                    Password:
                </label>
                <input type="password" id="authPassword" 
                       style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 16px;"
                       placeholder="Enter password">
            </div>
            
            <div id="authError" style="color: #d32f2f; font-size: 14px; margin-bottom: 15px; display: none;">
                Invalid employee number or password
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-primary" onclick="verifyAuthorization()" style="flex: 1;">
                    Verify Authorization
                </button>
                <button class="btn btn-secondary" id="cancelAuthBtn">Cancel</button>
            </div>
        </div>
    `;
    
    showCustomModal('Manager Authorization', modalHTML);
    
    // Add event listener for cancel button
    setTimeout(() => {
        const cancelBtn = document.getElementById('cancelAuthBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (pendingAuthorization?.failCallback) {
                    pendingAuthorization.failCallback();
                }
                cancelAuthorization();
            });
        }
    }, 100);
}

// Add this function to format transaction notes with authorization details
function formatTransactionNotes(item) {
    let notes = item.notes || '';
    
    // Add price change authorization info if available
    if (item.price_changed_by && item.price_change_position) {
        const priceChangeAuthNote = `[Price Change Authorized by: ${item.price_changed_by} (${item.price_change_position})]`;
        if (notes) {
            notes += '\n\n' + priceChangeAuthNote;
        } else {
            notes = priceChangeAuthNote;
        }
    }
    
    // Add discount authorization info if available (for Others discount)
    if (globalDiscountType === 'others' && globalDiscountAuthorizedBy && globalDiscountAuthPosition) {
        const discountAuthNote = `[Custom Discount Authorized by: ${globalDiscountAuthorizedBy} (${globalDiscountAuthPosition})]`;
        if (notes) {
            notes += '\n\n' + discountAuthNote;
        } else {
            notes = discountAuthNote;
        }
    }
    
    return notes;
}

// Verify authorization credentials
function verifyAuthorization() {
    const employeeNumber = document.getElementById('authEmployeeNumber').value.trim();
    const password = document.getElementById('authPassword').value;
    const errorDiv = document.getElementById('authError');
    
    if (!employeeNumber || !password) {
        errorDiv.textContent = 'Please enter both employee number and password';
        errorDiv.style.display = 'block';
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span style="opacity: 0.7;">Verifying...</span>';
    btn.disabled = true;
    
    // Verify credentials via AJAX
    fetch('verify_authorization.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            employee_number: employeeNumber,
            password: password,
            action: pendingAuthorization.action
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Authorization successful
            showFunctionFeedback(`Authorized by ${data.employee_name} (${data.position})`);
            closeCustomModal();
            
            // Store authData for later use
            pendingAuthorization.authData = data;
            
            // Execute the pending callback with extra data
            if (pendingAuthorization.successCallback) {
                pendingAuthorization.successCallback(pendingAuthorization.extraData, data);
            }
            
            pendingAuthorization = null;
        } else {
            // Authorization failed
            errorDiv.textContent = data.message || 'Invalid credentials or insufficient permissions';
            errorDiv.style.display = 'block';
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            // Clear password field
            document.getElementById('authPassword').value = '';
            document.getElementById('authPassword').focus();
        }
    })
    .catch(err => {
        console.error('Authorization error:', err);
        errorDiv.textContent = 'Error verifying authorization. Please try again.';
        errorDiv.style.display = 'block';
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function cancelAuthorization() {
    if (pendingAuthorization?.failCallback) {
        pendingAuthorization.failCallback();
    }
    pendingAuthorization = null;
    closeCustomModal();
}