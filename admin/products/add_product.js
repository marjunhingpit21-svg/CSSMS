// Image upload handling
const dropZone = document.getElementById('dropZone');
const imageInput = document.getElementById('productImage');
const imagePreview = document.getElementById('imagePreview');
const uploadPlaceholder = document.getElementById('uploadPlaceholder');

// Open file picker on click
dropZone.addEventListener('click', () => imageInput.click());

// Drag & Drop
['dragover', 'dragenter'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
        e.preventDefault();
        dropZone.classList.add('border-violet-500', 'bg-violet-900', 'bg-opacity-10');
    });
});

['dragleave', 'dragend'].forEach(evt => {
    dropZone.addEventListener(evt, () => {
        dropZone.classList.remove('border-violet-500', 'bg-violet-900', 'bg-opacity-10');
    });
});

dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('border-violet-500', 'bg-violet-900', 'bg-opacity-10');
    if (e.dataTransfer.files.length) {
        imageInput.files = e.dataTransfer.files;
        handleImage(e.dataTransfer.files[0]);
    }
});

imageInput.addEventListener('change', () => {
    if (imageInput.files.length) {
        handleImage(imageInput.files[0]);
    }
});

function handleImage(file) {
    if (!file.type.startsWith('image/')) {
        alert('Please select a valid image file');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = e => {
        imagePreview.src = e.target.result;
        imagePreview.classList.remove('hidden');
        uploadPlaceholder.classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

function addSizeRow() {
    const tbody = document.getElementById('sizesTableBody');
    const table = tbody.closest('table');
    const emptyState = document.querySelector('.empty-state');
    
    // Hide empty state and show table
    if (emptyState) {
        emptyState.classList.add('hidden');
    }
    table.classList.remove('hidden');
    
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-800 hover:bg-gray-800/50 transition new-size-row';
    row.innerHTML = `
        <td class="px-8 py-4">
            <select name="sizes[]" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white w-full" required>
                <option value="">Select size...</option>
                <option value="XS">XS</option>
                <option value="S">S</option>
                <option value="M">M</option>
                <option value="L">L</option>
                <option value="XL">XL</option>
                <option value="XXL">XXL</option>
                <option value="XXXL">XXXL</option>
            </select>
        </td>
        <td class="px-8 py-4">
            <input type="text" name="barcodes[]" placeholder="Auto-generated" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm font-mono w-full">
        </td>
        <td class="px-8 py-4">
            <input type="number" name="quantities[]" value="0" min="0" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white w-24" required>
        </td>
        <td class="px-8 py-4">
            <div class="input-with-prefix">
                <span>₱</span>
                <input type="number" name="price_adjustments[]" value="0.00" step="0.01" class="bg-gray-800 border border-gray-700 rounded-lg pl-8 pr-3 py-2 text-white w-28">
            </div>
        </td>
        <td class="px-8 py-4">
            <label class="switch">
                <input type="checkbox" name="is_available[]" checked>
                <span class="slider"></span>
            </label>
        </td>
        <td class="px-8 py-4">
            <button type="button" onclick="deleteSizeRow(this)" class="delete-btn">
                <span class="material-icons">delete</span>
            </button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function deleteSizeRow(btn) {
    const row = btn.closest('tr');
    const tbody = document.getElementById('sizesTableBody');
    
    row.remove();
    
    // Show empty state if no rows left
    if (tbody.children.length === 0) {
        const table = tbody.closest('table');
        const emptyState = document.querySelector('.empty-state');
        table.classList.add('hidden');
        emptyState.classList.remove('hidden');
    }
}


// Form validation
document.getElementById('addForm').addEventListener('submit', function(e) {
    const productName = document.querySelector('[name="product_name"]').value.trim();
    const price = document.querySelector('[name="price"]').value;
    const categoryId = document.querySelector('[name="category_id"]').value;
    
    if (!productName) {
        e.preventDefault();
        alert('Please enter a product name');
        return;
    }
    
    if (!price || parseFloat(price) <= 0) {
        e.preventDefault();
        alert('Please enter a valid price');
        return;
    }
    
    if (!categoryId) {
        e.preventDefault();
        alert('Please select a category');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons animate-spin">refresh</span> Adding Product...';
});

// Auto-calculate profit margin
const priceInput = document.querySelector('[name="price"]');
const costInput = document.querySelector('[name="cost_price"]');

function updateProfit() {
    const price = parseFloat(priceInput.value) || 0;
    const cost = parseFloat(costInput.value) || 0;
    const profit = price - cost;
    
    const profitDisplay = document.querySelector('.profit-box strong');
    if (profitDisplay) {
        profitDisplay.textContent = '₱' + profit.toFixed(2);
        
        // Color code based on profit margin
        const parent = profitDisplay.closest('.profit-box');
        if (profit < 0) {
            parent.className = 'profit-box bg-red-900 bg-opacity-20 border border-red-700 rounded-lg p-4 text-red-300';
        } else if (profit === 0) {
            parent.className = 'profit-box bg-gray-900 bg-opacity-20 border border-gray-700 rounded-lg p-4 text-gray-300';
        } else {
            parent.className = 'profit-box bg-violet-900 bg-opacity-20 border border-violet-700 rounded-lg p-4 text-violet-300';
        }
    }
}

priceInput.addEventListener('input', updateProfit);
costInput.addEventListener('input', updateProfit);

// Initialize profit calculation
updateProfit();