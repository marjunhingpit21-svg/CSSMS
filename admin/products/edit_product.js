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

// Size management
function addSizeRow() {
    const tbody = document.getElementById('sizesTableBody');
    
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-800 hover:bg-gray-800/50 transition new-size-row';
    row.innerHTML = `
        <td class="px-8 py-4">
            <select name="new_sizes[]" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white w-full" required>
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
            <input type="text" name="new_barcodes[]" placeholder="Auto-generated" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm font-mono w-full">
        </td>
        <td class="px-8 py-4">
            <input type="number" name="new_quantities[]" value="0" min="0" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white w-24" required>
        </td>
        <td class="px-8 py-4">
            <div class="relative">
                <span class="absolute left-3 top-2.5 text-gray-400 text-sm">₱</span>
                <input type="number" name="new_price_adjustments[]" value="0.00" step="0.01" class="bg-gray-800 border border-gray-700 rounded-lg pl-8 pr-3 py-2 text-white w-28">
            </div>
        </td>
        <td class="px-8 py-4">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="new_is_available[]" checked class="sr-only peer">
                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-violet-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
            </label>
        </td>
        <td class="px-8 py-4">
            <button type="button" onclick="deleteSizeRow(this)" class="text-red-400 hover:text-red-300 transition">
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
    
    const profitDisplay = document.querySelector('.text-violet-300 strong');
    if (profitDisplay) {
        profitDisplay.textContent = '₱' + profit.toFixed(2);
        
        // Color code based on profit margin
        const parent = profitDisplay.closest('.bg-violet-900');
        if (profit < 0) {
            parent.className = 'bg-red-900 bg-opacity-20 border border-red-700 rounded-lg p-4';
            profitDisplay.parentElement.className = 'text-red-300 text-sm';
        } else if (profit === 0) {
            parent.className = 'bg-gray-900 bg-opacity-20 border border-gray-700 rounded-lg p-4';
            profitDisplay.parentElement.className = 'text-gray-300 text-sm';
        } else {
            parent.className = 'bg-violet-900 bg-opacity-20 border border-violet-700 rounded-lg p-4';
            profitDisplay.parentElement.className = 'text-violet-300 text-sm';
        }
    }
}

priceInput.addEventListener('input', updateProfit);
costInput.addEventListener('input', updateProfit);

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
            <select name="new_sizes[]" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white w-full" required>
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
            <input type="text" name="new_barcodes[]" placeholder="Auto-generated" class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm font-mono w-full">
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