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

function isShoeCategory() {
    const categorySelect = document.querySelector('[name="category_id"]');
    const selectedCategory = categorySelect.options[categorySelect.selectedIndex].text.toLowerCase();
    return selectedCategory.includes('shoe') || selectedCategory.includes('footwear');
}

function getSizeOptions() {
    if (isShoeCategory()) {
        return `
            <option value="">Select shoe size...</option>
            <option value="6.0">6.0 US</option>
            <option value="6.5">6.5 US</option>
            <option value="7.0">7.0 US</option>
            <option value="7.5">7.5 US</option>
            <option value="8.0">8.0 US</option>
            <option value="8.5">8.5 US</option>
            <option value="9.0">9.0 US</option>
            <option value="9.5">9.5 US</option>
            <option value="10.0">10.0 US</option>
            <option value="10.5">10.5 US</option>
            <option value="11.0">11.0 US</option>
            <option value="11.5">11.5 US</option>
            <option value="12.0">12.0 US</option>
            <option value="13.0">13.0 US</option>
        `;
    } else {
        return `
            <option value="">Select size...</option>
            <option value="XS">XS</option>
            <option value="S">S</option>
            <option value="M">M</option>
            <option value="L">L</option>
            <option value="XL">XL</option>
            <option value="XXL">XXL</option>
            <option value="XXXL">XXXL</option>
        `;
    }
}

function updateCategoryIndicator() {
    const indicator = document.getElementById('categoryIndicator');
    const sizeHeader = document.getElementById('sizeHeader');
    
    if (isShoeCategory()) {
        indicator.textContent = 'Shoe Sizes';
        indicator.className = 'category-indicator shoe-category';
        sizeHeader.textContent = 'Shoe Size (US)';
    } else {
        indicator.textContent = 'Clothing Sizes';
        indicator.className = 'category-indicator clothing-category';
        sizeHeader.textContent = 'Size';
    }
}

// Update addSizeRow function - REMOVED BARCODE FIELD
function addSizeRow() {
    const tbody = document.getElementById('sizesTableBody');
    const table = document.getElementById('sizesTable');
    const emptyState = document.getElementById('emptySizeState');
    
    // Show table and hide empty state if this is the first row
    if (table && emptyState) {
        table.style.display = 'table';
        emptyState.style.display = 'none';
    }
    
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-800 hover:bg-gray-800/50 transition new-size-row';
    row.innerHTML = `
        <td class="px-8 py-4">
            <select name="new_sizes[]" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white w-full size-select" required>
                ${getSizeOptions()}
            </select>
        </td>
        <td class="px-8 py-4">
            <input type="number" name="new_quantities[]" value="0" min="0" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white w-24" required>
        </td>
        <td class="px-8 py-4">
            <div class="input-with-prefix">
                <span>₱</span>
                <input type="number" name="new_price_adjustments[]" value="0.00" step="0.01" class="bg-gray-800 border border-gray-700 rounded-lg pl-8 pr-3 py-2 text-white w-28">
            </div>
        </td>
        <td class="px-8 py-4">
            <label class="switch">
                <input type="checkbox" name="new_is_available[]" checked>
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
    const sizeId = row.getAttribute('data-size-id');
    const table = document.getElementById('sizesTable');
    const emptyState = document.getElementById('emptySizeState');
    
    if (sizeId) {
        // Existing size - mark for deletion
        if (confirm('Are you sure you want to delete this size variant? This action cannot be undone.')) {
            // Add hidden input to track deletion
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_sizes[]';
            deleteInput.value = sizeId;
            document.getElementById('editForm').appendChild(deleteInput);
            
            row.remove();
            
            // Show empty state if no rows left
            if (table && emptyState && document.querySelectorAll('#sizesTableBody tr').length === 0) {
                table.style.display = 'none';
                emptyState.style.display = 'block';
            }
        }
    } else {
        // New size row - just remove it
        row.remove();
        
        // Show empty state if no rows left
        if (table && emptyState && document.querySelectorAll('#sizesTableBody tr').length === 0) {
            table.style.display = 'none';
            emptyState.style.display = 'block';
        }
    }
}

document.querySelector('[name="category_id"]').addEventListener('change', function() {
    // Update existing new size rows when category changes
    const sizeSelects = document.querySelectorAll('.size-select');
    sizeSelects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = getSizeOptions();
        select.value = currentValue; // Try to maintain current selection
    });
    
    updateCategoryIndicator();
});

// Initialize category indicator
document.addEventListener('DOMContentLoaded', function() {
    updateCategoryIndicator();
});

// Form validation
document.getElementById('editForm').addEventListener('submit', function(e) {
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
    submitBtn.innerHTML = '<span class="material-icons animate-spin">refresh</span> Saving...';
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