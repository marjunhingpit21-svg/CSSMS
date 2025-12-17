// Product Search Functions

function isBarcodeInput(input) {
    const now = Date.now();
    const timeDiff = now - lastScannedTime;
    
    if (/^\d+$/.test(input) && input.length >= 6) {
        if (timeDiff < 1000 && input.length > lastScannedProduct?.length) {
            return true;
        }
        if (input.startsWith('8') || input.startsWith('9') || input.length >= 12) {
            return true;
        }
    }
    return false;
}

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();

    if (isBarcodeInput(q) && q.length >= 6) {
        clearTimeout(searchTimeout);
        searchProducts(q, true);
        return;
    }

    if (q.length < 1) {
        resultsBox.style.display = 'none';
        allProducts = [];
        return;
    }

    searchTimeout = setTimeout(() => {
        searchProducts(q, false);
    }, 400);
});

function searchProducts(query, isBarcode = false) {
    fetch(`search_products.php?q=${encodeURIComponent(query)}&barcode=${isBarcode ? '1' : '0'}`)
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            const products = data.products || data || [];
            allProducts = products;
            
            if (products.length === 0) {
                resultsBox.innerHTML = `
                    <div class="search-item" style="padding: 20px; text-align: center; color: #666;">
                        <p>No products found for "${query}"</p>
                        <p style="font-size: 12px; margin-top: 10px;">
                            Try searching by:<br>
                            • Product ID (e.g., 1)<br>
                            • Product name<br>
                            • Barcode
                        </p>
                    </div>
                `;
                resultsBox.style.display = 'block';
                return;
            }

            if (isBarcode && products.length === 1) {
                addProductFromSearch(products[0]);
                searchInput.value = '';
                resultsBox.style.display = 'none';
                allProducts = [];
                searchInput.focus();
                return;
            }

            resultsBox.innerHTML = products.map((p, i) => `
                <div class="search-item ${i===0?'selected':''}" 
                     data-index="${i}"
                     onclick="selectProduct(${i})"
                     onmouseover="highlightProduct(${i})"
                     style="cursor: pointer; transition: transform 0.1s ease;"
                     onmousedown="this.style.transform='scale(0.98)'"
                     onmouseup="this.style.transform='scale(1)'">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:60px;height:60px;border-radius:8px;overflow:hidden;background:#f8f8f8;border:1px solid #eee;flex-shrink:0;">
                            <img src="${p.image_url}" 
                                 style="width:100%;height:100%;object-fit:cover;"
                                 onerror="this.src='https://via.placeholder.com/60x60/eee/999?text=IMG'">
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:600;">
                                ${p.product_name}
                                <span style="color:#1976d2;font-size:11px;margin-left:8px;">ID: ${p.product_id}</span>
                            </div>
                            <div style="font-size:12px;color:#555;margin-top:4px;">
                                <strong>${p.size_name}</strong>
                                ${p.barcode ? ` • ${p.barcode}` : ''}  
                                <br>Stock: <span style="color:${p.stock_quantity<5?'#d32f2f':'#2e7d32'}">${p.stock_quantity}</span>
                            </div>
                        </div>
                        <div style="font-size:16px;font-weight:700;color:#1976d2;">
                            ₱${parseFloat(p.final_price).toFixed(2)}
                        </div>
                    </div>
                </div>
            `).join('');

            resultsBox.style.display = 'block';
        })
        .catch(err => {
            console.error('Search error:', err);
            resultsBox.innerHTML = `
                <div class="search-item" style="padding: 20px; text-align: center; color: #d32f2f;">
                    <p><strong>Error loading products</strong></p>
                    <p style="font-size: 12px; margin-top: 10px;">${err.message}</p>
                </div>
            `;
            resultsBox.style.display = 'block';
        });
}

function highlightProduct(index) {
    document.querySelectorAll('.search-item').forEach((item, i) => {
        item.classList.toggle('selected', i === index);
    });
}

function selectProduct(index) {
    if (allProducts[index]) {
        addProductFromSearch(allProducts[index]);
        searchInput.value = '';
        resultsBox.style.display = 'none';
        allProducts = [];
        searchInput.focus();
    }
}

searchInput.addEventListener('keydown', function(e) {
    const items = resultsBox.querySelectorAll('.search-item');
    let sel = resultsBox.querySelector('.selected');
    let idx = sel ? Array.from(items).indexOf(sel) : -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (idx < items.length-1) {
            sel.classList.remove('selected');
            items[idx+1].classList.add('selected');
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (idx > 0) {
            sel.classList.remove('selected');
            items[idx-1].classList.add('selected');
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (sel) {
            sel.click();
        } else if (allProducts.length === 1) {
            selectProduct(0);
        }
    } else if (e.key === 'Escape') {
        resultsBox.style.display = 'none';
    }
});

document.addEventListener('click', e => {
    if (!e.target.closest('.barcode-display')) {
        resultsBox.style.display = 'none';
    }
});

function appendNumber(n) {
    searchInput.value += n;
    searchInput.focus();
    searchInput.dispatchEvent(new Event('input'));
}

function clearNumber() {
    searchInput.value = '';
    searchInput.focus();
    resultsBox.style.display = 'none';
}

function backspaceNumber() {
    searchInput.value = searchInput.value.slice(0, -1);
    searchInput.focus();
    searchInput.dispatchEvent(new Event('input'));
}