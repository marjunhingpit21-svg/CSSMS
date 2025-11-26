<?php include 'Header.php'; ?>

<div class="page-wrapper">
    <section class="filters-section">
        <div class="container">
            <div class="filters-bar">
                <input type="text" id="searchBar" placeholder="Search clothes..." class="search-input">

                <select id="categoryFilter" class="filter-select">
                    <option value="all">All Categories</option>
                    <option value="tshirt">T-Shirts</option>
                    <option value="hoodie">Hoodies</option>
                    <option value="jacket">Jackets</option>
                    <option value="pants">Pants</option>
                    <option value="shoes">Shoes</option>
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
            <!-- Sample Products -->
            <div class="product-card" data-category="tshirt" data-price="29.99">
                <img src="clothes/tshirt1.jpg" alt="Classic T-Shirt">
                <h3>Classic White T-Shirt</h3>
                <p class="price">$29.99</p>
            </div>
            <div class="product-card" data-category="hoodie" data-price="69.99">
                <img src="clothes/hoodie1.jpg" alt="Cozy Hoodie">
                <h3>Cozy Oversized Hoodie</h3>
                <p class="price">$69.99</p>
            </div>
            <div class="product-card" data-category="jacket" data-price="129.99">
                <img src="clothes/jacket1.jpg" alt="Leather Jacket">
                <h3>Premium Leather Jacket</h3>
                <p class="price">$129.99</p>
            </div>
            <div class="product-card" data-category="pants" data-price="49.99">
                <img src="clothes/pants1.jpg" alt="Denim Jeans">
                <h3>Classic Denim Jeans</h3>
                <p class="price">$49.99</p>
            </div>
            <div class="product-card" data-category="shoes" data-price="89.99">
                <img src="clothes/shoes1.jpg" alt="Sneakers">
                <h3>Urban Sneakers</h3>
                <p class="price">$89.99</p>
            </div>
            <div class="product-card" data-category="tshirt" data-price="34.99">
                <img src="clothes/tshirt2.jpg" alt="Graphic T-Shirt">
                <h3>Graphic Print T-Shirt</h3>
                <p class="price">$34.99</p>
            </div>
        </div>
    </div>
</section>
</div>

<script>
    // Simple client-side filtering & sorting
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