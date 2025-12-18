// Global state variables
let cart = [];
let receiptCounter = 1;
let selectedItemIndex = -1;
let globalDiscount = 0;
let globalDiscountType = '';
let globalDiscountIdNumber = '';
let splitReceiptActive = false;
let splitReceipt1Items = [];
let splitReceipt2Items = [];
let splitReceipt1Paid = false;
let splitReceipt2Paid = false;
let originalReceiptNumber = '';
let splitPaymentData = {
    receipt1: null,
    receipt2: null
};
let lastScannedProduct = null;
let lastScannedTime = 0;



// NEW: Add these 3 lines here
let globalDiscountAuthorizedBy = '';
let globalDiscountAuthPosition = '';
let globalDiscountAuthId = null;

// ... rest of your globals.js continues

// Search-related globals
let searchTimeout;
let allProducts = [];
let barcodeBuffer = '';
let barcodeTimeout;

// DOM element references
const searchInput = document.getElementById('searchInput');
const resultsBox = document.getElementById('searchResults');

// Utility Functions
function showFunctionFeedback(message) {
    const existing = document.querySelector('.function-feedback');
    if (existing) existing.remove();
    
    const feedback = document.createElement('div');
    feedback.className = 'function-feedback';
    feedback.textContent = message;
    document.body.appendChild(feedback);
    
    setTimeout(() => {
        if (feedback.parentNode) {
            feedback.remove();
        }
    }, 2000);
}

function showCustomModal(title, content) {
    const modalHTML = `
        <div id="customModal" class="modal" style="display: block;">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2>${title}</h2>
                    <span class="modal-close" onclick="closeCustomModal()">×</span>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
            </div>
        </div>
    `;
    
    const existing = document.getElementById('customModal');
    if (existing) existing.remove();
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeCustomModal() {
    const modal = document.getElementById('customModal');
    if (modal) modal.remove();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}