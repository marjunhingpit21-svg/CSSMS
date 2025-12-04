<?php
include '../includes/auth.php';
include '../includes/db.php';

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
if ($supplier_id <= 0) {
    die("Invalid supplier ID");
}

// Fetch supplier name
$supplier_name = "Unknown Supplier";
$stmt = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $supplier_name = htmlspecialchars($row['supplier_name']);
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Purchase Transaction</title>
    <link rel="stylesheet" href="css/add_transaction.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .line-item { 
            border: 1px solid #3d3d5c; 
            padding: 20px; 
            margin-bottom: 20px; 
            border-radius: 12px; 
            background: #16213e; 
        }
        .remove-item { 
            color: #f87171; 
            cursor: pointer; 
            font-size: 1.4rem;
        }
        .total-cost-display { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: #34d399; 
        }
        .new-product-fields { 
            display: none; 
            margin-top: 15px; 
            padding: 15px; 
            background: #1e293b; 
            border-radius: 8px; 
            border: 1px dashed #64748b;
        }
        .cost-locked {
            background: #1e293b !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../adminheader.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="header">
                <h1>Add Purchase Transaction</h1>
                <p>Receiving stock from <strong><?= $supplier_name ?></strong> (#SUP-<?= str_pad($supplier_id, 4, '0', STR_PAD_LEFT) ?>)</p>
            </div>

            <form action="ajax/process_add_purchase.php" method="POST" id="purchaseForm">
                <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">

                <!-- Header Info -->
                <div class="form-grid">
                    <div class="form-section">
                        <h3><i class="fas fa-truck"></i> Delivery & Rating</h3>
                        <div class="form-group">
                            <label>Expected Delivery Date</label>
                            <input type="date" name="expected_delivery">
                        </div>
                        <div class="form-group">
                            <label>Actual Delivery Date</label>
                            <input type="date" name="actual_delivery">
                        </div>
                        <div class="form-group">
                            <label>Supplier Rating (1-5)</label>
                            <div class="rating-stars">
                                <?php for($i=5; $i>=1; $i--): ?>
                                    <input type="radio" name="supplier_rating" value="<?= $i ?>" id="star<?= $i ?>">
                                    <label for="star<?= $i ?>">★</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Quality issues, delays, packaging condition..."></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-chart-line"></i> Summary</h3>
                        <p><strong>Total Items:</strong> <span id="totalItems">0</span></p>
                        <p><strong>Total Quantity:</strong> <span id="totalQty">0</span></p>
                        <p class="total-cost-display">
                            <strong>Total Cost:</strong> ₱<span id="grandTotal">0.00</span>
                        </p>
                    </div>
                </div>

                <hr style="border-color: #2d2d44; margin: 2rem 0;">

                <h3><i class="fas fa-boxes"></i> Products Received</h3>
                <div id="lineItems">
                    <!-- First Row -->
                    <div class="line-item" data-index="0">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Product</label>
                                <select name="items[0][product_id]" class="product-select" required>
                                    <option value="">-- Select Existing Product --</option>
                                    <?php
                                    $products = $conn->query("
                                        SELECT p.product_id, p.product_name, c.category_name, c.category_id
                                        FROM products p 
                                        JOIN categories c ON p.category_id = c.category_id 
                                        ORDER BY p.product_name
                                    ");
                                    while ($p = $products->fetch_assoc()) {
                                        $isShoeCategory = ($p['category_id'] == 5); // 5 = Shoes
                                        echo "<option value='{$p['product_id']}' 
                                                data-is-shoe='" . ($isShoeCategory ? '1' : '0') . "'
                                                data-category-id='{$p['category_id']}'>" . 
                                            htmlspecialchars($p['product_name']) . "</option>";
                                    }
                                    ?>
                                    <option value="new">➕ Add New Product (Not in list)</option>
                                </select>
                            </div>

                            <div class="new-product-fields" id="newProductFields0">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="form-group">
                                        <label>Product Name <span class="text-red-400">*</span></label>
                                        <input type="text" name="items[0][new_product_name]" required placeholder="e.g. Nike Air Force 1">
                                    </div>

                                    <div class="form-group">
                                        <label>Category <span class="text-red-400">*</span></label>
                                        <select name="items[0][new_category_id]" required class="new-category-select">
                                            <option value="">Select category</option>
                                            <?php
                                            $cats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
                                            while ($c = $cats->fetch_assoc()) {
                                                $isShoeCategory = ($c['category_id'] == 5); // Shoes category
                                                echo "<option value='{$c['category_id']}' 
                                                        data-is-shoe='" . ($isShoeCategory ? '1' : '0') . "'>{$c['category_name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Gender</label>
                                        <select name="items[0][new_gender_id]">
                                            <option value="">Any Gender</option>
                                            <?php
                                            $genders = $conn->query("SELECT gender_id, gender_name FROM gender_sections");
                                            while ($g = $genders->fetch_assoc()) {
                                                echo "<option value='{$g['gender_id']}'>{$g['gender_name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Age Group</label>
                                        <select name="items[0][new_age_group_id]">
                                            <option value="">Any Age</option>
                                            <?php
                                            $ages = $conn->query("SELECT age_group_id, age_group_name FROM age_groups");
                                            while ($a = $ages->fetch_assoc()) {
                                                echo "<option value='{$a['age_group_id']}'>{$a['age_group_name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Selling Price (₱) <span class="text-red-400">*</span></label>
                                        <input type="number" step="0.01" name="items[0][new_selling_price]" min="0" required placeholder="e.g. 3590.00">
                                    </div>

                                    <div class="form-group">
                                        <label>Cost Price (₱) <span class="text-red-400">*</span></label>
                                        <input type="number" step="0.01" name="items[0][new_cost_price]" min="0" required placeholder="This batch cost">
                                    </div>

                                    <div class="form-group col-span-2">
                                        <label>Description</label>
                                        <textarea name="items[0][new_description]" rows="2" placeholder="Optional product description..."></textarea>
                                    </div>

                                    <div class="form-group col-span-2">
                                        <label>Product Image <small class="text-gray-400">(Optional, max 5MB)</small></label>
                                        <input type="file" name="items[0][new_product_image]" accept="image/*">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Size / Variant</label>
                                <select name="items[0][size_id]" class="size-select" required>
                                    <option value="">-- Select Product First --</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Qty Received</label>
                                <input type="number" name="items[0][quantity]" class="qty-input" min="1" value="1" required>
                            </div>

                            <div class="form-group">
                                <label>Unit Cost (₱)</label>
                                <input type="number" step="0.01" name="items[0][unit_cost]" class="cost-input" required>
                            </div>

                            <div class="form-group">
                                <label>Defective</label>
                                <input type="number" name="items[0][defective]" min="0" value="0">
                            </div>

                            <div class="form-group" style="align-self: end;">
                                <button type="button" class="remove-item" onclick="removeItem(this)" style="display:none;">Remove</button>
                            </div>
                        </div>
                        <small>Line Total: ₱<span class="line-total">0.00</span></small>
                    </div>
                </div>

                <button type="button" id="addItemBtn" class="btn-submit" style="padding: 12px 24px; font-size: 1rem;">
                    <i class="fas fa-plus"></i> Add Another Product
                </button>

                <div class="form-actions">
                    <a href="supplier_details.php?id=<?= $supplier_id ?>" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Save Purchase Transaction
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
// ==== FIXED & WORKING SCRIPT FOR add_transaction.php ====
let itemIndex = 1;

async function loadSizes(lineItem) {
    const sizeSelect = lineItem.querySelector('.size-select');
    sizeSelect.innerHTML = '<option value="">-- Loading sizes... --</option>';
    sizeSelect.disabled = true;

    const productSelect = lineItem.querySelector('.product-select');
    const productId = productSelect.value;

    // For existing products, use get_product_sizes.php
    if (productId && productId !== 'new') {
        try {
            const response = await fetch(`ajax/get_product_sizes.php?product_id=${productId}`);
            const sizes = await response.json();
            
            sizeSelect.innerHTML = '<option value="">-- Select Size --</option>';
            sizes.forEach(s => {
                const opt = new Option(s.size_name, s.product_size_id);
                sizeSelect.add(opt);
            });
        } catch (error) {
            console.error('Error loading sizes:', error);
            sizeSelect.innerHTML = '<option value="">-- Error loading sizes --</option>';
        }
    }
    // For new products, dynamically load sizes based on category
    else if (productId === 'new') {
        const catSel = lineItem.querySelector('select[name$="[new_category_id]"]');
        const ageSel = lineItem.querySelector('select[name$="[new_age_group_id]"]');
        const genSel = lineItem.querySelector('select[name$="[new_gender_id]"]');
        
        const category_id = catSel?.value || null;
        const age_group_id = ageSel?.value || null;
        const gender_id = genSel?.value || null;
        
        const isShoeCategory = (category_id == '5');
        
        let apiUrl = isShoeCategory ? 'ajax/get_shoe_sizes.php' : 'ajax/get_clothing_sizes.php';
        const params = new URLSearchParams();
        
        if (age_group_id) params.append('age_group_id', age_group_id);
        if (gender_id) params.append('gender_id', gender_id);
        
        try {
            const queryString = params.toString() ? `?${params.toString()}` : '';
            const response = await fetch(`${apiUrl}${queryString}`);
            const sizes = await response.json();
            
            sizeSelect.innerHTML = '<option value="">-- Select Size --</option>';
            sizes.forEach(s => {
                const opt = new Option(s.name, s.id);
                sizeSelect.add(opt);
            });
        } catch (error) {
            console.error('Error loading sizes:', error);
            sizeSelect.innerHTML = '<option value="">-- Error loading sizes --</option>';
        }
    }
    
    sizeSelect.disabled = false;
}

// Add new line item
document.getElementById('addItemBtn').addEventListener('click', () => {
    const container = document.getElementById('lineItems');
    const clone = container.children[0].cloneNode(true);

    clone.dataset.index = itemIndex;

    // Reset all fields
    clone.querySelectorAll('input, select, textarea').forEach(el => {
        const name = el.name?.replace(/\[\d+\]/, `[${itemIndex}]`) || '';
        el.name = name;
        el.value = (el.classList.contains('qty-input')) ? 1 : '';
        if (el.classList.contains('cost-input')) {
            el.readOnly = false;
            el.classList.remove('cost-locked');
        }
    });

    clone.querySelector('.size-select').innerHTML = '<option value="">-- Select Product First --</option>';
    clone.querySelector('.new-product-fields').style.display = 'none';
    clone.querySelector('.remove-item').style.display = 'inline-block';
    clone.querySelector('.line-total').textContent = '0.00';

    container.appendChild(clone);
    itemIndex++;
    updateSummary();
});

function removeItem(btn) {
    if (document.querySelectorAll('.line-item').length > 1) {
        btn.closest('.line-item').remove();
        updateSummary();
    }
}

function updateSummary() {
    let totalQty = 0, grandTotal = 0;
    document.querySelectorAll('.line-item').forEach(item => {
        const qty = parseFloat(item.querySelector('.qty-input').value) || 0;
        const cost = parseFloat(item.querySelector('.cost-input').value) || 0;
        const lineTotal = qty * cost;
        item.querySelector('.line-total').textContent = lineTotal.toFixed(2);
        totalQty += qty;
        grandTotal += lineTotal;
    });
    document.getElementById('totalItems').textContent = document.querySelectorAll('.line-item').length;
    document.getElementById('totalQty').textContent = totalQty;
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
}

// Product / Age Group change → reload sizes
document.addEventListener('change', e => {
    const lineItem = e.target.closest('.line-item');
    if (!lineItem) return;

    if (e.target.classList.contains('product-select')) {
        const val = e.target.value;
        lineItem.querySelector('.new-product-fields').style.display = val === 'new' ? 'block' : 'none';
        loadSizes(lineItem);
    }

    if (e.target.name?.includes('new_age_group_id') || 
        e.target.name?.includes('new_gender_id') ||
        e.target.classList.contains('new-category-select')) {
        const prodSel = lineItem.querySelector('.product-select');
        if (prodSel.value === 'new') loadSizes(lineItem);
    }

    if (e.target.classList.contains('qty-input') || e.target.classList.contains('cost-input')) {
        updateSummary();
    }
});

// FINAL FIX: Prevent default only if validation fails
document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    let valid = true;

    document.querySelectorAll('.line-item').forEach(item => {
        const prod = item.querySelector('.product-select').value;
        const size = item.querySelector('.size-select').value;
        const qty  = item.querySelector('.qty-input').value;
        const cost = item.querySelector('.cost-input').value;

        if (!prod || !size || !qty || !cost) {
            valid = false;
        }

        // For new products → required fields
        if (prod === 'new') {
            const name = item.querySelector('input[name$="[new_product_name]"]');
            const cat  = item.querySelector('select[name$="[new_category_id]"]');
            const price = item.querySelector('input[name$="[new_selling_price]"]');
            if (!name?.value || !cat?.value || !price?.value) valid = false;
        }
    });

    if (!valid) {
        e.preventDefault();
        alert('Please fill in all required fields (Product, Size, Qty, Cost)');
        return false;
    }

    // Optional: show loading state
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});

updateSummary(); // initial call
</script>
</body>
</html>