// Cart Management Functions

function renderCart() {
    const container = document.getElementById('receiptItems');
    if (cart.length === 0) {
        container.innerHTML = '<div class="empty-cart"><p>Cart is empty</p></div>';
        updateTotals();
        selectedItemIndex = -1;
        return;
    }

    container.innerHTML = cart.map((item, i) => `
        <div class="receipt-item ${i === selectedItemIndex ? 'latest-item' : ''}">
            <div class="item-details">
                <div class="item-name">
                    ${item.product_name} <span class="size-tag">${item.size_name}</span>
                    ${item.price_changed ? `<span class="price-change">Price Changed</span>` : ''}
                    ${item.clerk_name !== EMPLOYEE_NAME ? `<span class="clerk-indicator">${item.clerk_name}</span>` : ''}
                </div>
                <div class="item-price">₱${item.final_price.toFixed(2)} × ${item.quantity}</div>
                ${item.notes ? `<div style="font-size:11px;color:#666;margin-top:3px;background:#f5f5f5;padding:4px 8px;border-radius:4px;">📝 ${item.notes}</div>` : ''}
                <div class="item-quantity-print">Qty: ${item.quantity}</div>
            </div>
            <div>
                <div class="item-total">₱${(item.final_price * item.quantity).toFixed(2)}</div>
                <span class="remove-btn no-print" onclick="event.stopPropagation(); removeItem(${i})">×</span>
            </div>
        </div>
    `).join('');

    updateTotals();
}

function removeItem(index) {
    if (confirm(`Remove "${cart[index].product_name}" from cart?`)) {
        cart.splice(index, 1);
        if (cart.length > 0) {
            selectedItemIndex = cart.length - 1;
        } else {
            selectedItemIndex = -1;
        }
        renderCart();
    }
}

function updateTotals() {
    const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const total = subtotal + tax - discountAmount;

    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('tax').textContent = '₱' + tax.toFixed(2);
    document.getElementById('discount').textContent = '₱' + discountAmount.toFixed(2);
    document.getElementById('total').textContent = '₱' + total.toFixed(2);
}

function clearCart() {
    if (cart.length && confirm('Clear cart?')) {
        cart = [];
        selectedItemIndex = -1;
        globalDiscount = 0;
        globalDiscountType = '';
        globalDiscountIdNumber = '';
        renderCart();
    }
}

function addProductFromSearch(product) {
    const qty = 1;
    
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
        selectedItemIndex = cart.indexOf(existing);
    } else {
        cart.push({
            product_size_id: product.product_size_id,
            product_name: product.product_name,
            size_name: product.size_name,
            final_price: parseFloat(product.final_price),
            original_price: parseFloat(product.final_price),
            stock_quantity: product.stock_quantity,
            quantity: qty,
            discount: 0,
            notes: '',
            clerk_name: EMPLOYEE_NAME,
            price_changed: false
        });
        selectedItemIndex = cart.length - 1;
    }

    lastScannedProduct = product.barcode || product.product_id.toString();
    lastScannedTime = Date.now();

    renderCart();
    showFunctionFeedback('Item added to cart');
    document.getElementById('productPreview').style.display = 'none';
}

function printReceipt() {
    if (cart.length === 0) {
        alert('Cart is empty. Nothing to print.');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    const receiptNum = document.getElementById('receiptNumber').textContent;
    const date = new Date().toLocaleString();
    const subtotal = cart.reduce((s,i) => s + i.final_price * i.quantity, 0);
    const tax = subtotal * 0.12;
    const discountAmount = subtotal * (globalDiscount / 100);
    const total = subtotal + tax - discountAmount;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Receipt ${receiptNum}</title>
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
                .discount-info { font-size: 11px; color: #666; margin-top: 5px; }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <h2>Altiere</h2>
                    <p>Receipt #: ${receiptNum}</p>
                    <p>Date: ${date}</p>
                    ${globalDiscountType ? `<p class="discount-info">Discount: ${globalDiscountType.toUpperCase()} ${globalDiscountIdNumber ? `(${globalDiscountIdNumber})` : ''}</p>` : ''}
                </div>
                <div class="items">
                    ${cart.map(item => `
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
                </div>
                <div class="footer">
                    <p>Cashier: ${EMPLOYEE_NAME}</p>
                    <p>Thank you for shopping with us!</p>
                </div>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}