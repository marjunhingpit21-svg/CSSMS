<?php
include '../includes/auth.php';
include '../db.php';

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
                                        SELECT p.product_id, p.product_name, c.category_name 
                                        FROM products p 
                                        JOIN categories c ON p.category_id = c.category_id 
                                        ORDER BY p.product_name
                                    ");
                                    while ($p = $products->fetch_assoc()) {
                                        $isShoe = ($p['category_name'] === 'Shoes');
                                        echo "<option value='{$p['product_id']}' data-is-shoe='" . ($isShoe ? '1' : '0') . "'>" . 
                                             htmlspecialchars($p['product_name']) . " (" . htmlspecialchars($p['category_name']) . ")</option>";
                                    }
                                    ?>
                                    <option value="new">➕ Add New Product (Not in list)</option>
                                </select>
                            </div>

                            <!-- Replace the existing <div class="new-product-fields"> block with this enhanced version -->
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
                                                $isShoe = in_array($c['category_name'], ['Shoes', 'Footwear']);
                                                echo "<option value='{$c['category_id']}' data-is-shoe='" . ($isShoe ? '1' : '0') . "'>{$c['category_name']}</option>";
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

                                    <!-- Image Upload (Optional but recommended) -->
                                    <div class="form-group col-span-2">
                                        <label>Product Image <small class="text-gray-400">(Optional, max 5MB)</small></label>
                                        <input type="file" name="items[0][new_product_image]" accept="image/*">
                                    </div>

                                    <!-- Size Variants Section -->
                                    <div class="col-span-2 mt-4 p-4 bg-gray-800 rounded-lg border border-gray-700">
                                        <h4 class="text-lg font-semibold mb-3 flex items-center gap-2">
                                            <i class="fas fa-ruler"></i> Size Variants (Add at least one)
                                        </h4>
                                        <div class="new-size-variants space-y-3">
                                            <div class="variant-row grid grid-cols-12 gap-3 items-end">
                                                <div class="col-span-4">
                                                    <label>Size</label>
                                                    <select name="items[0][new_sizes][0][size]" class="new-size-select w-full" required>
                                                        <option value="">Select size...</option>
                                                    </select>
                                                </div>
                                                <div class="col-span-3">
                                                    <label>Qty Received</label>
                                                    <input type="number" name="items[0][new_sizes][0][quantity]" min="1" value="1" required>
                                                </div>
                                                <div class="col-span-4">
                                                    <label>Price Adjustment (±₱)</label>
                                                    <input type="number" step="0.01" name="items[0][new_sizes][0][price_adj]" value="0.00">
                                                </div>
                                                <div class="col-span-1 text-center">
                                                    <button type="button" class="text-red-400 hover:text-red-300 remove-variant" title="Remove">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="text-sm text-violet-400 hover:text-violet-300 mt-3 add-new-variant">
                                            <i class="fas fa-plus"></i> Add Another Size
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Size / Variant</label>
                                <select name="items[0][size_id]" class="size-select" required>
                                    <option value="">-- Select Size --</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Qty Received</label>
                                <input type="number" name="items[0][quantity]" class="qty-input" min="1" value="1" required>
                            </div>

                            <div class="form-group">
                                <label>Unit Cost (₱)</label>
                                <input type="number" step="0.01" name="items[0][unit_cost]" class="cost-input cost-locked" readonly required>
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
        let itemIndex = 1;

        // Load sizes based on product category
        async function loadSizes(selectElement, isShoe) {
            const sizeSelect = selectElement.closest('.line-item').querySelector('.size-select');
            sizeSelect.innerHTML = '<option value="">-- Loading sizes... --</option>';

            const url = isShoe 
                ? 'ajax/get_shoe_sizes.php' 
                : 'ajax/get_clothing_sizes.php';

            const response = await fetch(url);
            const sizes = await response.json();

            sizeSelect.innerHTML = '<option value="">-- Select Size --</option>';
            sizes.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                sizeSelect.appendChild(opt);
            });
        }

        // Load unit cost for product_size
        async function loadUnitCost(productId, sizeId, costInput) {
            if (!productId || productId === 'new' || !sizeId) {
                costInput.value = '';
                costInput.readOnly = false;
                costInput.classList.remove('cost-locked');
                return;
            }

            const response = await fetch(`ajax/get_unit_cost.php?product_id=${productId}&size_id=${sizeId}`);
            const data = await response.json();
            costInput.value = data.unit_cost || '';
            costInput.readOnly = !!data.unit_cost;
            costInput.classList.toggle('cost-locked', !!data.unit_cost);
        }

        // Handle product change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const lineItem = e.target.closest('.line-item');
                const productId = e.target.value;
                const isShoe = e.target.selectedOptions[0]?.dataset.isShoe === '1';
                const newProductFields = lineItem.querySelector('.new-product-fields');
                const sizeSelect = lineItem.querySelector('.size-select');
                const costInput = lineItem.querySelector('.cost-input');

                if (productId === 'new') {
                    newProductFields.style.display = 'block';
                    sizeSelect.innerHTML = '<option value="">-- Select Size After Saving Product --</option>';
                    sizeSelect.disabled = true;
                    costInput.readOnly = false;
                    costInput.classList.remove('cost-locked');
                } else {
                    newProductFields.style.display = 'none';
                    sizeSelect.disabled = false;
                    loadSizes(e.target, isShoe);
                    loadUnitCost(productId, sizeSelect.value, costInput);
                }
            }

            if (e.target.classList.contains('size-select')) {
                const lineItem = e.target.closest('.line-item');
                const productSelect = lineItem.querySelector('.product-select');
                const costInput = lineItem.querySelector('.cost-input');
                if (productSelect.value && productSelect.value !== 'new') {
                    loadUnitCost(productSelect.value, e.target.value, costInput);
                }
            }
        });

        // Add new item row
        document.getElementById('addItemBtn').addEventListener('click', function() {
            const container = document.getElementById('lineItems');
            const firstItem = container.children[0];
            const newItem = firstItem.cloneNode(true);

            newItem.dataset.index = itemIndex;

            // Reset values
            newItem.querySelectorAll('input, select').forEach(el => {
                if (el.name) {
                    el.name = el.name.replace(/\[\d+\]/, '[' + itemIndex + ']');
                }
                if (!el.classList.contains('cost-input')) el.value = '';
                if (el.type === 'number' && el.name.includes('quantity')) el.value = 1;
            });

            newItem.querySelector('.remove-item').style.display = 'inline-block';
            newItem.querySelector('.line-total').textContent = '0.00';
            newItem.querySelector('.new-product-fields').style.display = 'none';
            newItem.querySelector('.new-product-fields').id = 'newProductFields' + itemIndex;

            container.appendChild(newItem);
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
            let totalQty = 0;
            let grandTotal = 0;

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

        document.getElementById('purchaseForm').addEventListener('input', updateSummary);
        updateSummary();
    </script>

    <script>
    // Enhanced handling for new product + size variants
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('new-category-select')) {
            const lineItem = e.target.closest('.line-item');
            const categorySelect = e.target;
            const isShoe = categorySelect.selectedOptions[0]?.dataset.isShoe === '1';
            const sizeSelects = lineItem.querySelectorAll('.new-size-select');

            sizeSelects.forEach(select => {
                const currentVal = select.value;
                select.innerHTML = '<option value="">Loading sizes...</option>';

                const url = isShoe ? 'ajax/get_shoe_sizes.php' : 'ajax/get_clothing_sizes.php';
                fetch(url)
                    .then(r => r.json())
                    .then(sizes => {
                        select.innerHTML = '<option value="">Select size...</option>';
                        sizes.forEach(s => {
                            const opt = new Option(s.name, s.id);
                            select.add(opt);
                        });
                        select.value = currentVal;
                    });
            });
        }
    });

    // Add another size variant in new product
    document.addEventListener('click', function(e) {
        if (e.target.closest('.add-new-variant')) {
            const container = e.target.closest('.new-size-variants');
            const index = container.children.length;
            const lineItem = e.target.closest('.line-item');
            const itemIdx = lineItem.dataset.index;

            const row = document.createElement('div');
            row.className = 'variant-row grid grid-cols-12 gap-3 items-end mt-3';
            row.innerHTML = `
                <div class="col-span-4">
                    <select name="items[${itemIdx}][new_sizes][${index}][size]" class="new-size-select w-full" required>
                        <option value="">Select size...</option>
                    </select>
                </div>
                <div class="col-span-3">
                    <input type="number" name="items[${itemIdx}][new_sizes][${index}][quantity]" min="1" value="1" required>
                </div>
                <div class="col-span-4">
                    <input type="number" step="0.01" name="items[${itemIdx}][new_sizes][${index}][price_adj]" value="0.00">
                </div>
                <div class="col-span-1 text-center">
                    <button type="button" class="text-red-400 hover:text-red-300 remove-variant"><i class="fas fa-trash"></i></button>
                </div>
            `;

            // Load sizes based on selected category
            const categorySelect = lineItem.querySelector('.new-category-select');
            if (categorySelect?.value) {
                const isShoe = categorySelect.selectedOptions[0].dataset.isShoe === '1';
                const url = isShoe ? 'ajax/get_shoe_sizes.php' : 'ajax/get_clothing_sizes.php';
                fetch(url).then(r => r.json()).then(sizes => {
                    const select = row.querySelector('.new-size-select');
                    select.innerHTML = '<option value="">Select size...</option>';
                    sizes.forEach(s => select.add(new Option(s.name, s.id)));
                });
            }

            container.appendChild(row);
        }

        if (e.target.closest('.remove-variant')) {
            e.target.closest('.variant-row').remove();
        }
    });
    </script>
</body>
</html>