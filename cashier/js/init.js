// Initialization Code

// Set receipt date on load
document.getElementById('receiptDate').textContent = new Date().toLocaleString();

// Load receipt number from database
fetch('get_next_receipt_number.php')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            receiptCounter = parseInt(data.next_receipt_number);
            document.getElementById('receiptNumber').textContent = data.next_receipt_number;
            originalReceiptNumber = data.next_receipt_number;
        }
    })
    .catch(err => {
        console.error('Error loading receipt number:', err);
    });

// Window onload - Focus search input and update F-key state
window.onload = () => {
    searchInput.focus();
    updateFKeyState();
};