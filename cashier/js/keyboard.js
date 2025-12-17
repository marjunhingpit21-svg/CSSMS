// Keyboard Shortcuts Handler

document.addEventListener('keydown', function(e) {
    const activeElement = document.activeElement;
    const isTyping = activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA';
    
    // Disable F keys if split receipt is active
    if (splitReceiptActive) {
        if (e.key.startsWith('F') && e.key.length === 2) {
            e.preventDefault();
            return;
        }
    }
    
    // F-Key handlers
    if (e.key === 'F1') { 
        e.preventDefault(); 
        applyDiscount(); 
    }
    else if (e.key === 'F2') { 
        e.preventDefault(); 
        viewTransactionHistory(); 
    }
    else if (e.key === 'F3') { 
        e.preventDefault(); 
        deleteAllItems(); 
    }
    else if (e.key === 'F4') { 
        e.preventDefault(); 
        changePrice(); 
    }
    else if (e.key === 'F5') { 
        e.preventDefault(); 
        addNotes(); 
    }
    else if (e.key === 'F6') { 
        e.preventDefault(); 
        changeQuantity(); 
    }
    else if (e.key === 'F7') { 
        e.preventDefault(); 
        splitReceipt(); 
    }
    else if (e.key === 'F8') { 
        e.preventDefault(); 
        deleteSelectedItem(); 
    }
    else if (e.key === 'Escape' && !isTyping) { 
        e.preventDefault(); 
        logoutCashier(); 
    }
});