
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
                            echo '
                            <div class="product-card" data-category="'.$row['category_id'].'" data-price="'.$row['price'].'">
                                <img src="'.$row['image_url'].'" alt="'.$row['product_name'].'" onerror="this.src=\'https://via.placeholder.com/300x400?text=No+Image\'">
                                <h3>'.$row['product_name'].'</h3>
                                <p class="price">$'.number_format($row['price'], 2).'</p>
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
