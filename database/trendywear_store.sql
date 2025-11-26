-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2025 at 02:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `trendywear_store`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `get_sales_report` (IN `start_date` DATE, IN `end_date` DATE)   BEGIN
    SELECT 
        DATE(o.order_date) as sale_date,
        COUNT(DISTINCT o.order_id) as orders,
        SUM(oi.quantity) as items_sold,
        SUM(o.total_amount) as revenue,
        SUM(oi.quantity * oi.unit_cost) as cost,
        SUM(o.total_amount - (oi.quantity * oi.unit_cost)) as profit,
        AVG(o.total_amount) as avg_order_value
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE DATE(o.order_date) BETWEEN start_date AND end_date
        AND o.status != 'cancelled'
    GROUP BY DATE(o.order_date)
    ORDER BY sale_date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `process_sale` (IN `inv_id` INT, IN `sale_qty` INT, IN `ord_id` INT, IN `sale_price` DECIMAL(10,2), IN `product_cost` DECIMAL(10,2))   BEGIN
    DECLARE current_qty INT;
    
    -- Get current quantity
    SELECT quantity INTO current_qty FROM inventory WHERE inventory_id = inv_id;
    
    -- Check if enough stock
    IF current_qty >= sale_qty THEN
        -- Update inventory
        UPDATE inventory SET quantity = quantity - sale_qty WHERE inventory_id = inv_id;
        
        -- Add order item
        INSERT INTO order_items (order_id, inventory_id, quantity, unit_price, unit_cost, subtotal)
        VALUES (ord_id, inv_id, sale_qty, sale_price, product_cost, sale_qty * sale_price);
        
        -- Log transaction
        INSERT INTO stock_transactions (inventory_id, transaction_type, quantity, notes)
        VALUES (inv_id, 'sale', -sale_qty, CONCAT('Sold via order #', ord_id));
        
        SELECT 'Sale processed successfully' as message, TRUE as success;
    ELSE
        SELECT 'Insufficient stock' as message, FALSE as success;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `restock_inventory` (IN `inv_id` INT, IN `restock_qty` INT, IN `sup_id` INT)   BEGIN
    -- Update inventory
    UPDATE inventory 
    SET quantity = quantity + restock_qty,
        last_restocked = CURRENT_TIMESTAMP
    WHERE inventory_id = inv_id;
    
    -- Log transaction
    INSERT INTO stock_transactions (inventory_id, transaction_type, quantity, supplier_id, notes)
    VALUES (inv_id, 'purchase', restock_qty, sup_id, 'Inventory restocked');
    
    SELECT 'Inventory restocked successfully' as message;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_daily_sales` (IN `sale_date` DATE)   BEGIN
    INSERT INTO daily_sales (sales_date, total_orders, total_revenue, total_cost, total_profit, total_items_sold)
    SELECT 
        DATE(o.order_date),
        COUNT(DISTINCT o.order_id),
        SUM(o.total_amount),
        SUM(oi.quantity * oi.unit_cost),
        SUM(o.total_amount - (oi.quantity * oi.unit_cost)),
        SUM(oi.quantity)
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE DATE(o.order_date) = sale_date AND o.status != 'cancelled'
    GROUP BY DATE(o.order_date)
    ON DUPLICATE KEY UPDATE
        total_orders = VALUES(total_orders),
        total_revenue = VALUES(total_revenue),
        total_cost = VALUES(total_cost),
        total_profit = VALUES(total_profit),
        total_items_sold = VALUES(total_items_sold);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'T-Shirts', 'Casual and comfortable t-shirts', '2025-11-26 01:07:51'),
(2, 'Hoodies', 'Cozy hoodies and sweatshirts', '2025-11-26 01:07:51'),
(3, 'Jackets', 'Stylish jackets for all seasons', '2025-11-26 01:07:51'),
(4, 'Pants', 'Jeans, trousers, and casual pants', '2025-11-26 01:07:51'),
(5, 'Shoes', 'Footwear collection', '2025-11-26 01:07:51'),
(6, 'Accessories', 'Fashion accessories and extras', '2025-11-26 01:07:51');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_sales`
--

CREATE TABLE `daily_sales` (
  `sales_date` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `total_profit` decimal(10,2) DEFAULT 0.00,
  `total_items_sold` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_sales_summary` (
`sales_date` date
,`total_orders` bigint(21)
,`total_revenue` decimal(32,2)
,`total_cost` decimal(42,2)
,`total_profit` decimal(43,2)
,`total_items_sold` decimal(32,0)
,`avg_order_value` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `last_restocked` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `size_id`, `quantity`, `reorder_level`, `last_restocked`) VALUES
(1, 1, 2, 50, 10, NULL),
(2, 1, 3, 75, 10, NULL),
(3, 1, 4, 60, 10, NULL),
(4, 1, 5, 40, 10, NULL),
(5, 2, 2, 30, 10, NULL),
(6, 2, 3, 45, 10, NULL),
(7, 2, 4, 35, 10, NULL),
(8, 2, 5, 25, 10, NULL),
(9, 3, 3, 20, 5, NULL),
(10, 3, 4, 25, 5, NULL),
(11, 3, 5, 15, 5, NULL),
(12, 4, 2, 40, 10, NULL),
(13, 4, 3, 55, 10, NULL),
(14, 4, 4, 45, 10, NULL),
(15, 4, 5, 30, 10, NULL),
(16, 5, 2, 25, 8, NULL),
(17, 5, 3, 35, 8, NULL),
(18, 5, 4, 30, 8, NULL),
(19, 5, 5, 20, 8, NULL),
(20, 6, 2, 45, 10, NULL),
(21, 6, 3, 60, 10, NULL),
(22, 6, 4, 50, 10, NULL),
(23, 6, 5, 35, 10, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `low_stock_items`
-- (See below for the actual view)
--
CREATE TABLE `low_stock_items` (
`product_name` varchar(100)
,`size_name` varchar(10)
,`quantity` int(11)
,`reorder_level` int(11)
,`units_needed` bigint(12)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_sales_report`
-- (See below for the actual view)
--
CREATE TABLE `monthly_sales_report` (
`month` varchar(7)
,`total_orders` bigint(21)
,`total_items_sold` decimal(32,0)
,`revenue` decimal(32,2)
,`total_cost` decimal(42,2)
,`profit` decimal(43,2)
,`avg_order_value` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','online','bank_transfer') DEFAULT 'cash',
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `shipping_address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `category_id`, `description`, `price`, `cost_price`, `image_url`, `created_at`, `updated_at`) VALUES
(1, 'Classic White T-Shirt', 1, 'Premium cotton t-shirt', 29.99, 12.00, 'clothes/tshirt1.jpg', '2025-11-26 01:07:51', '2025-11-26 01:07:51'),
(2, 'Cozy Oversized Hoodie', 2, 'Comfortable fleece hoodie', 69.99, 30.00, 'clothes/hoodie1.jpg', '2025-11-26 01:07:51', '2025-11-26 01:07:51'),
(3, 'Premium Leather Jacket', 3, 'Genuine leather jacket', 129.99, 60.00, 'clothes/jacket1.jpg', '2025-11-26 01:07:51', '2025-11-26 01:07:51'),
(4, 'Classic Denim Jeans', 4, 'Slim fit denim jeans', 49.99, 20.00, 'clothes/pants1.jpg', '2025-11-26 01:07:51', '2025-11-26 01:07:51'),
(5, 'Urban Sneakers', 5, 'Comfortable everyday sneakers', 89.99, 40.00, 'clothes/shoes1.jpg', '2025-11-26 01:07:51', '2025-11-26 01:07:51'),
(6, 'Graphic Print T-Shirt', 1, 'Trendy graphic design', 34.99, 14.00, 'clothes/tshirt2.jpg', '2025-11-26 01:07:51', '2025-11-26 01:07:51');

-- --------------------------------------------------------

--
-- Stand-in structure for view `product_stock_summary`
-- (See below for the actual view)
--
CREATE TABLE `product_stock_summary` (
`product_id` int(11)
,`product_name` varchar(100)
,`category_name` varchar(50)
,`price` decimal(10,2)
,`cost_price` decimal(10,2)
,`total_stock` decimal(32,0)
,`size_variants` bigint(21)
,`profit_per_unit` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `sales_by_category`
-- (See below for the actual view)
--
CREATE TABLE `sales_by_category` (
`category_name` varchar(50)
,`total_orders` bigint(21)
,`total_units_sold` decimal(32,0)
,`total_revenue` decimal(32,2)
,`total_cost` decimal(42,2)
,`total_profit` decimal(43,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `sales_by_product`
-- (See below for the actual view)
--
CREATE TABLE `sales_by_product` (
`product_id` int(11)
,`product_name` varchar(100)
,`category_name` varchar(50)
,`total_orders` bigint(21)
,`total_units_sold` decimal(32,0)
,`total_revenue` decimal(32,2)
,`total_cost` decimal(42,2)
,`total_profit` decimal(43,2)
,`avg_selling_price` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `size_id` int(11) NOT NULL,
  `size_name` varchar(10) NOT NULL,
  `size_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`size_id`, `size_name`, `size_order`) VALUES
(1, 'XS', 1),
(2, 'S', 2),
(3, 'M', 3),
(4, 'L', 4),
(5, 'XL', 5),
(6, 'XXL', 6);

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `transaction_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','sale','return','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `created_at`) VALUES
(1, 'Fashion Wholesale Inc.', 'John Smith', 'john@fashionwholesale.com', '+1-555-0101', '123 Supplier St, New York, NY', '2025-11-26 01:07:51'),
(2, 'Apparel Direct', 'Sarah Johnson', 'sarah@appareldirect.com', '+1-555-0102', '456 Clothing Ave, Los Angeles, CA', '2025-11-26 01:07:51'),
(3, 'Premium Textiles Co.', 'Mike Chen', 'mike@premiumtextiles.com', '+1-555-0103', '789 Fabric Rd, Chicago, IL', '2025-11-26 01:07:51');

-- --------------------------------------------------------

--
-- Stand-in structure for view `top_selling_products`
-- (See below for the actual view)
--
CREATE TABLE `top_selling_products` (
`product_name` varchar(100)
,`category_name` varchar(50)
,`units_sold` decimal(32,0)
,`total_revenue` decimal(32,2)
,`total_profit` decimal(43,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','customer') DEFAULT 'customer',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `daily_sales_summary`
--
DROP TABLE IF EXISTS `daily_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales_summary`  AS SELECT cast(`o`.`order_date` as date) AS `sales_date`, count(distinct `o`.`order_id`) AS `total_orders`, sum(`o`.`total_amount`) AS `total_revenue`, sum(`oi`.`quantity` * `oi`.`unit_cost`) AS `total_cost`, sum(`o`.`total_amount` - `oi`.`quantity` * `oi`.`unit_cost`) AS `total_profit`, sum(`oi`.`quantity`) AS `total_items_sold`, avg(`o`.`total_amount`) AS `avg_order_value` FROM (`orders` `o` left join `order_items` `oi` on(`o`.`order_id` = `oi`.`order_id`)) WHERE `o`.`status` <> 'cancelled' GROUP BY cast(`o`.`order_date` as date) ORDER BY cast(`o`.`order_date` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `low_stock_items`
--
DROP TABLE IF EXISTS `low_stock_items`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `low_stock_items`  AS SELECT `p`.`product_name` AS `product_name`, `s`.`size_name` AS `size_name`, `i`.`quantity` AS `quantity`, `i`.`reorder_level` AS `reorder_level`, `i`.`reorder_level`- `i`.`quantity` AS `units_needed` FROM ((`inventory` `i` join `products` `p` on(`i`.`product_id` = `p`.`product_id`)) left join `sizes` `s` on(`i`.`size_id` = `s`.`size_id`)) WHERE `i`.`quantity` <= `i`.`reorder_level` ORDER BY `i`.`quantity` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_sales_report`
--
DROP TABLE IF EXISTS `monthly_sales_report`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_sales_report`  AS SELECT date_format(`o`.`order_date`,'%Y-%m') AS `month`, count(distinct `o`.`order_id`) AS `total_orders`, sum(`oi`.`quantity`) AS `total_items_sold`, sum(`o`.`subtotal`) AS `revenue`, sum(`oi`.`quantity` * `oi`.`unit_cost`) AS `total_cost`, sum(`o`.`subtotal` - `oi`.`quantity` * `oi`.`unit_cost`) AS `profit`, avg(`o`.`total_amount`) AS `avg_order_value` FROM (`orders` `o` left join `order_items` `oi` on(`o`.`order_id` = `oi`.`order_id`)) WHERE `o`.`status` <> 'cancelled' GROUP BY date_format(`o`.`order_date`,'%Y-%m') ORDER BY date_format(`o`.`order_date`,'%Y-%m') DESC ;

-- --------------------------------------------------------

--
-- Structure for view `product_stock_summary`
--
DROP TABLE IF EXISTS `product_stock_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_stock_summary`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `cat`.`category_name` AS `category_name`, `p`.`price` AS `price`, `p`.`cost_price` AS `cost_price`, sum(`i`.`quantity`) AS `total_stock`, count(distinct `i`.`size_id`) AS `size_variants`, `p`.`price`- `p`.`cost_price` AS `profit_per_unit` FROM ((`products` `p` join `categories` `cat` on(`p`.`category_id` = `cat`.`category_id`)) left join `inventory` `i` on(`p`.`product_id` = `i`.`product_id`)) GROUP BY `p`.`product_id`, `p`.`product_name`, `cat`.`category_name`, `p`.`price`, `p`.`cost_price` ;

-- --------------------------------------------------------

--
-- Structure for view `sales_by_category`
--
DROP TABLE IF EXISTS `sales_by_category`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sales_by_category`  AS SELECT `cat`.`category_name` AS `category_name`, count(distinct `oi`.`order_id`) AS `total_orders`, sum(`oi`.`quantity`) AS `total_units_sold`, sum(`oi`.`subtotal`) AS `total_revenue`, sum(`oi`.`quantity` * `oi`.`unit_cost`) AS `total_cost`, sum(`oi`.`subtotal` - `oi`.`quantity` * `oi`.`unit_cost`) AS `total_profit` FROM (((`categories` `cat` left join `products` `p` on(`cat`.`category_id` = `p`.`category_id`)) left join `inventory` `i` on(`p`.`product_id` = `i`.`product_id`)) left join `order_items` `oi` on(`i`.`inventory_id` = `oi`.`inventory_id`)) GROUP BY `cat`.`category_name` ORDER BY sum(`oi`.`subtotal`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `sales_by_product`
--
DROP TABLE IF EXISTS `sales_by_product`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sales_by_product`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `cat`.`category_name` AS `category_name`, count(distinct `oi`.`order_id`) AS `total_orders`, sum(`oi`.`quantity`) AS `total_units_sold`, sum(`oi`.`subtotal`) AS `total_revenue`, sum(`oi`.`quantity` * `oi`.`unit_cost`) AS `total_cost`, sum(`oi`.`subtotal` - `oi`.`quantity` * `oi`.`unit_cost`) AS `total_profit`, avg(`oi`.`unit_price`) AS `avg_selling_price` FROM (((`products` `p` join `categories` `cat` on(`p`.`category_id` = `cat`.`category_id`)) left join `inventory` `i` on(`p`.`product_id` = `i`.`product_id`)) left join `order_items` `oi` on(`i`.`inventory_id` = `oi`.`inventory_id`)) GROUP BY `p`.`product_id`, `p`.`product_name`, `cat`.`category_name` ORDER BY sum(`oi`.`subtotal`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `top_selling_products`
--
DROP TABLE IF EXISTS `top_selling_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `top_selling_products`  AS SELECT `p`.`product_name` AS `product_name`, `cat`.`category_name` AS `category_name`, sum(`oi`.`quantity`) AS `units_sold`, sum(`oi`.`subtotal`) AS `total_revenue`, sum(`oi`.`subtotal` - `oi`.`quantity` * `oi`.`unit_cost`) AS `total_profit` FROM ((((`products` `p` join `categories` `cat` on(`p`.`category_id` = `cat`.`category_id`)) join `inventory` `i` on(`p`.`product_id` = `i`.`product_id`)) join `order_items` `oi` on(`i`.`inventory_id` = `oi`.`inventory_id`)) join `orders` `o` on(`oi`.`order_id` = `o`.`order_id`)) WHERE `o`.`status` <> 'cancelled' GROUP BY `p`.`product_id`, `p`.`product_name`, `cat`.`category_name` ORDER BY sum(`oi`.`quantity`) DESC LIMIT 0, 10 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `daily_sales`
--
ALTER TABLE `daily_sales`
  ADD PRIMARY KEY (`sales_date`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `unique_product_variant` (`product_id`,`size_id`),
  ADD KEY `size_id` (`size_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `inventory_id` (`inventory_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`size_id`),
  ADD UNIQUE KEY `size_name` (`size_name`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_transactions_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
