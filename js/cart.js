// Cart Management Functions

// Add item to cart
function addToCart(productId) {
    fetch('cart_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            updateCartCount(data.cart_count);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

// Update quantity by increment/decrement
function updateQuantity(productId, change) {
    const input = document.querySelector(`.cart-item[data-product-id="${productId}"] .qty-input`);
    const currentQty = parseInt(input.value);
    const newQty = currentQty + change;
    
    if (newQty < 1) {
        if (confirm('Remove this item from cart?')) {
            removeFromCart(productId);
        }
        return;
    }
    
    if (newQty > 99) {
        showMessage('Maximum quantity is 99', 'error');
        return;
    }
    
    updateCartQuantity(productId, newQty);
}

// Update quantity directly from input
function updateQuantityDirect(productId, quantity) {
    const qty = parseInt(quantity);
    
    if (isNaN(qty) || qty < 1) {
        showMessage('Please enter a valid quantity', 'error');
        return;
    }
    
    if (qty > 99) {
        showMessage('Maximum quantity is 99', 'error');
        return;
    }
    
    updateCartQuantity(productId, qty);
}

// Send update request to server
function updateCartQuantity(productId, quantity) {
    fetch('cart_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update&product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
            const input = cartItem.querySelector('.qty-input');
            input.value = quantity;
            
            // Update subtotal for this item
            const price = parseFloat(cartItem.querySelector('.item-price').textContent.replace('$', ''));
            const subtotal = price * quantity;
            cartItem.querySelector('.item-subtotal p').textContent = `$${subtotal.toFixed(2)}`;
            
            // Update cart totals
            updateCartTotals(data);
            updateCartCount(data.cart_count);
            
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

// Remove item from cart
function removeFromCart(productId) {
    fetch('cart_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=remove&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove item from DOM
            const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
            cartItem.style.animation = 'slideOut 0.3s ease';
            
            setTimeout(() => {
                cartItem.remove();
                
                // Check if cart is empty
                const remainingItems = document.querySelectorAll('.cart-item');
                if (remainingItems.length === 0) {
                    location.reload(); // Reload to show empty cart message
                } else {
                    // Update totals
                    updateCartTotals(data);
                    updateCartCount(data.cart_count);
                }
            }, 300);
            
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

// Update cart totals in summary
function updateCartTotals(data) {
    if (data.subtotal !== undefined) {
        document.getElementById('subtotal').textContent = `$${data.subtotal}`;
        document.getElementById('tax').textContent = `$${data.tax}`;
        document.getElementById('total').textContent = `$${data.total}`;
    }
}

// Update cart count badge
function updateCartCount(count) {
    const badge = document.getElementById('cart-count');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}

// Show success/error message
function showMessage(message, type) {
    // Remove existing messages
    const existingMessage = document.querySelector('.cart-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `cart-message ${type}`;
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        messageDiv.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => messageDiv.remove(), 300);
    }, 3000);
}

// Load cart count on page load
document.addEventListener('DOMContentLoaded', function() {
    fetch('cart_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_count'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cart_count);
        }
    })
    .catch(error => console.error('Error loading cart count:', error));
});

// Animation for item removal
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);