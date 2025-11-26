<?php 
include 'Database/db.php';
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrendyWear - Your Fashion Store</title>
    <link rel="stylesheet" href="css/Header.css">
    <link rel="stylesheet" href="css/MainPage.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'Header.php'; ?>

    <div class="page-wrapper">
        <section class="filters-section">
            <div class="container">
                <div class="filters-bar">
                    <input type="text" id="searchBar" placeholder="Search clothes..." class="search-input">

                    <select id="categoryFilter" class="filter-select">
                        <option value="all">All Categories</option>
                        <option value="1">T-Shirts</option>
                        <option value="2">Hoodies</option>
                        <option value="3">Jackets</option>
                        <option value="4">Pants</option>
                        <option value="5">Shoes</option>
                    </select>

                    <select id="priceSort" class="filter-select">
                        <option value="default">Sort by</option>
                        <option value="low">Price: Low to High</option>
                        <option value="high">Price: High to Low</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="products-section">
            <div class="container">
                <div class="products-grid" id="productsGrid">
                    <?php
                    // Fetch products from database
                    $sql = "SELECT p.*, c.category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.category_id 
                            ORDER BY p.created_at DESC";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $product_id = $row['product_id'];
                            $product_name = htmlspecialchars($row['product_name']);
                            $price = number_format($row['price'], 2);
                            $image_url = htmlspecialchars($row['image_url']);
                            $stock = $row['stock_quantity'];
                            
                            echo '
                            <div class="product-card" data-category="'.$row['category_id'].'" data-price="'.$row['price'].'">
                                <div class="product-image">
                                    <img src="'.$image_url.'" alt="'.$product_name.'" onerror="this.src=\'https://via.placeholder.com/300x400?text=No+Image\'">
                                    '.($stock < 10 && $stock > 0 ? '<span class="stock-badge low">Only '.$stock.' left</span>' : '').'
                                    '.($stock == 0 ? '<span class="stock-badge out">Out of Stock</span>' : '').'
                                </div>
                                <div class="product-info">
                                    <h3>'.$product_name.'</h3>
                                    <p class="price">$'.$price.'</p>
                                    <button class="btn-add-cart" 
                                            onclick="addToCart('.$product_id.')"
                                            '.($stock == 0 ? 'disabled' : '').'>
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        '.($stock == 0 ? 'Out of Stock' : 'Add to Cart').'
                                    </button>
                                </div>
                            </div>';
                        }
                    } else {
                        echo '<p>No products found.</p>';
                    }
                    ?>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Client-side filtering & sorting
        const searchBar = document.getElementById('searchBar');
        const categoryFilter = document.getElementById('categoryFilter');
        const priceSort = document.getElementById('priceSort');
        const productsGrid = document.getElementById('productsGrid');
        const products = document.querySelectorAll('.product-card');

        function filterProducts() {
            const searchText = searchBar.value.toLowerCase();
            const selectedCategory = categoryFilter.value;
            const sortOrder = priceSort.value;

            let productArray = Array.from(products);

            // Filter by search and category
            productArray = productArray.filter(product => {
                const title = product.querySelector('h3').textContent.toLowerCase();
                const category = product.getAttribute('data-category');
                const matchesSearch = title.includes(searchText);
                const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
                return matchesSearch && matchesCategory;
            });

            // Sort by price
            if (sortOrder === 'low') {
                productArray.sort((a, b) => parseFloat(a.dataset.price) - parseFloat(b.dataset.price));
            } else if (sortOrder === 'high') {
                productArray.sort((a, b) => parseFloat(b.dataset.price) - parseFloat(a.dataset.price));
            }

            // Clear and re-append
            productsGrid.innerHTML = '';
            productArray.forEach(p => productsGrid.appendChild(p));
        }

        searchBar.addEventListener('input', filterProducts);
        categoryFilter.addEventListener('change', filterProducts);
        priceSort.addEventListener('change', filterProducts);

        // Initial load
        filterProducts();
    </script>
</body>
</html>
<?php $conn->close(); ?>