function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}


// Filter functionality
const searchInput = document.querySelector('.search-input');
const categoryFilter = document.getElementById('category-filter');
const stockFilter = document.getElementById('stock-filter');
const sortFilter = document.getElementById('sort-filter');
const tableBody = document.querySelector('tbody');

function filterProducts() {
    const searchTerm = searchInput.value.toLowerCase();
    const categoryValue = categoryFilter.value;
    const stockValue = stockFilter.value;
    const sortValue = sortFilter.value;
    
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    let visibleCount = 0;
    
    rows.forEach(row => {
        const productName = row.querySelector('.product-name').textContent.toLowerCase();
        const category = row.querySelector('.category-text').textContent;
        const stockStatus = row.querySelector('.status-badge').textContent;
        
        let showRow = true;
        
        // Search filter
        if (searchTerm && !productName.includes(searchTerm)) {
            showRow = false;
        }
        
        // Category filter
        if (categoryValue !== 'All Categories' && category !== categoryValue) {
            showRow = false;
        }
        
        // Stock status filter
        if (stockValue !== 'All Stock Status') {
            if (stockStatus !== stockValue) {
                showRow = false;
            }
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Sort functionality
    sortProducts(sortValue);
    updatePaginationInfo(visibleCount);
}

function sortProducts(sortValue) {
    const visibleRows = Array.from(tableBody.querySelectorAll('tr:not([style*="display: none"])'));
    
    visibleRows.sort((a, b) => {
        switch(sortValue) {
            case 'Sort by: Name A-Z':
                const nameA = a.querySelector('.product-name').textContent.toLowerCase();
                const nameB = b.querySelector('.product-name').textContent.toLowerCase();
                return nameA.localeCompare(nameB);
                
            case 'Sort by: Price Low-High':
                const priceA = parseFloat(a.querySelector('.price-text').textContent.replace('₱', '').replace(',', ''));
                const priceB = parseFloat(b.querySelector('.price-text').textContent.replace('₱', '').replace(',', ''));
                return priceA - priceB;
                
            case 'Sort by: Stock Level':
                const stockA = parseInt(a.querySelector('.stock-text').textContent);
                const stockB = parseInt(b.querySelector('.stock-text').textContent);
                return stockB - stockA; // Descending order (high to low)
                
            default: // 'Sort by: Newest'
                const idA = parseInt(a.getAttribute('product-id'));
                const idB = parseInt(b.getAttribute('product-id'));
                return idB - idA; // Descending order for newest first
        }
    });
    
    // Reorder rows in table
    visibleRows.forEach(row => tableBody.appendChild(row));
}


function updatePaginationInfo(visibleCount) {
    const paginationInfo = document.querySelector('.pagination-info');
    
    // Only update if we're actively filtering (not on initial page load)
    if (paginationInfo && isFiltering()) {
        const totalRows = tableBody.querySelectorAll('tr').length;
        const start = visibleCount > 0 ? 1 : 0;
        const end = visibleCount;
        paginationInfo.innerHTML = `Showing <span>${start}-${end}</span> of <span>${totalRows}</span> products`;
    }
}

function isFiltering() {
    return searchInput.value.trim() !== '' ||
           categoryFilter.value !== 'All Categories' ||
           stockFilter.value !== 'All Stock Status' ||
           sortFilter.value !== 'Sort by: Date Added';
}

// Event listeners for filters
searchInput.addEventListener('input', filterProducts);
categoryFilter.addEventListener('change', filterProducts);
stockFilter.addEventListener('change', filterProducts);
sortFilter.addEventListener('change', filterProducts);



const checkboxes = document.querySelectorAll('.select-product');
const btnView    = document.getElementById('btn-view');
const btnEdit    = document.getElementById('btn-edit');
const btnDelete  = document.getElementById('btn-delete');

function getSelectedProductId() {
    const checked = document.querySelector('.select-product:checked');
    if (!checked) return null;
    const row = checked.closest('tr');
    return row.getAttribute('product-id'); // This attribute exists in your <tr product-id="...">
}

function updateHeaderButtons() {
    const checkedCount = document.querySelectorAll('.select-product:checked').length;

    // Reset all
    [btnView, btnEdit, btnDelete].forEach(btn => {
        btn.classList.add('disabled');
        btn.disabled = true;
    });

    if (checkedCount === 1) {
        btnView.classList.remove('disabled');
        btnEdit.classList.remove('disabled');
        btnDelete.classList.remove('disabled');
        btnView.disabled = btnEdit.disabled = btnDelete.disabled = false;
    } else if (checkedCount > 1) {
        btnDelete.classList.remove('disabled');
        btnDelete.disabled = false;
    }
}

btnView.addEventListener('click', () => {
    if (btnView.classList.contains('disabled')) return;
    const id = getSelectedProductId();
    if (id) {
        window.location.href = `view_product.php?id=${id}`;
    }
});

// EDIT BUTTON → Go to edit page (you can create edit_product.php later)
btnEdit.addEventListener('click', () => {
    if (btnEdit.classList.contains('disabled')) return;
    const id = getSelectedProductId();
    if (id) {
        window.location.href = `edit_product.php?id=${id}`;
    }
});

// DELETE BUTTON → Styled modal confirmation
btnDelete.addEventListener('click', () => {
    const checkedCheckboxes = document.querySelectorAll('.select-product:checked');
    if (checkedCheckboxes.length === 0 || btnDelete.classList.contains('disabled')) return;

    const productNames = Array.from(checkedCheckboxes).map(cb => {
        return cb.closest('tr').querySelector('.product-name').textContent;
    });

    const deleteModal = document.getElementById('deleteModal');
    const deleteMessage = document.getElementById('deleteMessage');
    const confirmDeleteBtn = document.getElementById('confirmDelete');

    // Set the confirmation message
    if (checkedCheckboxes.length === 1) {
        deleteMessage.textContent = `Delete "${productNames[0]}"? This action cannot be undone.`;
    } else {
        deleteMessage.innerHTML = `Delete ${checkedCheckboxes.length} products?<br><br><div class="text-sm text-gray-400 max-h-32 overflow-y-auto">${productNames.map(name => `• ${name}`).join('<br>')}</div><br>This action cannot be undone.`;
    }

    // Remove any existing event listener to prevent duplicates
    const newConfirmDeleteBtn = confirmDeleteBtn.cloneNode(true);
    confirmDeleteBtn.parentNode.replaceChild(newConfirmDeleteBtn, confirmDeleteBtn);

    // Add new event listener
    newConfirmDeleteBtn.addEventListener('click', () => {
        const ids = Array.from(checkedCheckboxes).map(cb => 
            cb.closest('tr').getAttribute('product-id')
        );

        // Close modal and redirect to delete script
        closeModal('deleteModal');
        window.location.href = `delete_products.php?ids=${ids.join(',')}`;
    });

    // Show the modal
    deleteModal.classList.remove('hidden');
});

// Update buttons on checkbox change
checkboxes.forEach(cb => cb.addEventListener('change', updateHeaderButtons));

// Initial check
updateHeaderButtons();