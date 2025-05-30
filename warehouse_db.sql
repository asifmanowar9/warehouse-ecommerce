-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 30, 2025 at 02:53 PM
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
-- Database: `warehouse_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `date_added`) VALUES
(1, 2, 11, 1, '2025-05-30 06:52:22'),
(4, 3, 13, 5, '2025-05-30 12:24:58');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `shipping_address` text NOT NULL,
  `shipping_city` varchar(100) NOT NULL,
  `shipping_state` varchar(100) DEFAULT NULL,
  `shipping_zipcode` varchar(20) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('PENDING','PROCESSING','SHIPPED','DELIVERED','COMPLETED','CANCELLED') DEFAULT 'PENDING'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_zipcode`, `payment_method`, `total_amount`, `status`) VALUES
(1, 3, '2025-05-30 09:14:12', 'dgfdg', 'dgdfg', '', '12312', 'CREDIT_CARD', 379.98, 'PENDING');

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_insert` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
    INSERT INTO order_status_history (order_id, status, notes)
    VALUES (NEW.id, NEW.status, 'Order created');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_order_update` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO order_status_history (order_id, status, notes)
        VALUES (NEW.id, NEW.status, CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 13, 1, 149.99),
(2, 1, 15, 1, 219.99);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `status` enum('PENDING','PROCESSING','SHIPPED','DELIVERED','COMPLETED','CANCELLED') NOT NULL,
  `notes` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `notes`, `timestamp`) VALUES
(1, 1, 'PENDING', 'Order created', '2025-05-30 09:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_level` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `description`, `image_path`, `unit_price`, `reorder_level`, `supplier_id`, `created_at`) VALUES
(1, 'PWR-DRILL-01', 'Industrial Power Drill', 'Heavy-duty power drill with variable speed settings and impact functionality', 'uploads/products/power_drill.jpg', 89.99, 5, 1, '2025-05-30 06:50:46'),
(2, 'SFTY-HELM-02', 'Safety Helmet', 'OSHA-approved hard hat for construction and industrial use', 'uploads/products/safety_helmet.jpg', 24.99, 10, 1, '2025-05-30 06:50:46'),
(3, 'HVY-GLOVES-03', 'Heavy-Duty Work Gloves', 'Cut-resistant work gloves with reinforced palms', 'uploads/products/work_gloves.jpg', 18.50, 15, 1, '2025-05-30 06:50:46'),
(4, 'CPU-INTEL-04', 'Intel i7 Processor', 'Latest generation Intel i7 processor for high-performance computing', 'uploads/products/intel_cpu.jpg', 349.99, 3, 2, '2025-05-30 06:50:46'),
(5, 'RAM-32GB-05', '32GB RAM Module', 'High-speed DDR4 memory module for servers and workstations', 'uploads/products/ram_module.jpg', 129.99, 5, 2, '2025-05-30 06:50:46'),
(6, 'SSD-1TB-06', '1TB Solid State Drive', 'Enterprise-grade SSD with high read/write speeds', NULL, 189.99, 4, 2, '2025-05-30 06:50:46'),
(7, 'CEMENT-50KG-07', 'Premium Cement (50kg)', 'High-quality Portland cement for construction projects', 'uploads/products/cement_bag.jpg', 12.99, 20, 3, '2025-05-30 06:50:46'),
(8, 'LUMBER-2X4-08', 'Construction Lumber 2x4', 'Pressure-treated lumber for framing and construction', 'uploads/products/lumber_2x4.jpg', 8.75, 30, 3, '2025-05-30 06:50:46'),
(9, 'PAINT-WHITE-09', 'Industrial White Paint (5 Gal)', 'Weather-resistant exterior paint for industrial applications', 'uploads/products/white_paint.jpg', 75.50, 8, 3, '2025-05-30 06:50:46'),
(10, 'PAPER-REAM-10', 'Premium Copy Paper', '500 sheets of high-quality copy paper for office use', 'uploads/products/copy_paper.jpg', 6.99, 25, 4, '2025-05-30 06:50:46'),
(11, 'INK-BLACK-11', 'Black Printer Ink', 'Compatible with most office printers', 'uploads/products/printer_ink.jpg', 34.99, 12, 4, '2025-05-30 06:50:46'),
(12, 'STAPLER-12', 'Heavy-Duty Stapler', 'Industrial stapler for high-volume document processing', 'uploads/products/stapler.jpg', 22.50, 8, 4, '2025-05-30 06:50:46'),
(13, 'WRENCH-SET-13', 'Professional Wrench Set', 'Complete set of metric and standard wrenches', 'uploads/products/wrench_set.jpg', 149.99, 4, 5, '2025-05-30 06:50:46'),
(14, 'TOOLBOX-14', 'Large Metal Toolbox', 'Durable steel toolbox with multiple compartments', 'uploads/products/toolbox.jpg', 79.99, 6, 5, '2025-05-30 06:50:46'),
(15, 'LADDER-15', 'Extension Ladder (24ft)', 'Heavy-duty aluminum extension ladder', 'uploads/products/extension_ladder.jpg', 219.99, 3, 5, '2025-05-30 06:50:46');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `ordered_by` int(10) UNSIGNED DEFAULT NULL,
  `order_date` date NOT NULL,
  `status` enum('OPEN','RECEIVED','CANCELLED') DEFAULT 'OPEN',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `po_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `qty_ordered` int(10) UNSIGNED NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `location_id` int(10) UNSIGNED DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `movement_type` enum('PURCHASE','SALE','ADJUST','TRANSFER') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `moved_by` int(10) UNSIGNED DEFAULT NULL,
  `moved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `location_id`, `qty`, `movement_type`, `reference`, `moved_by`, `moved_at`) VALUES
(9, 7, NULL, 21, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(10, 4, NULL, 41, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(11, 3, NULL, 34, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(12, 11, NULL, 36, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(13, 15, NULL, 20, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(14, 8, NULL, 34, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(15, 9, NULL, 47, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(16, 10, NULL, 26, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(17, 1, NULL, 29, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(18, 5, NULL, 57, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(19, 2, NULL, 39, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(20, 6, NULL, 12, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(21, 12, NULL, 37, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(22, 14, NULL, 37, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(23, 13, NULL, 16, 'PURCHASE', 'Initial inventory', NULL, '2025-05-30 06:51:01'),
(24, 11, NULL, -5, 'SALE', 'Order #1001', NULL, '2025-05-30 06:51:01'),
(25, 11, NULL, -8, 'SALE', 'Order #1002', NULL, '2025-05-30 06:51:01'),
(26, 11, NULL, -3, 'SALE', 'Order #1003', NULL, '2025-05-30 06:51:01'),
(27, 13, NULL, -4, 'SALE', 'Order #1004', NULL, '2025-05-30 06:51:01'),
(28, 13, NULL, -6, 'SALE', 'Order #1005', NULL, '2025-05-30 06:51:01'),
(29, 15, NULL, -2, 'SALE', 'Order #1006', NULL, '2025-05-30 06:51:01'),
(30, 15, NULL, -3, 'SALE', 'Order #1007', NULL, '2025-05-30 06:51:01'),
(31, 14, NULL, -5, 'SALE', 'Order #1008', NULL, '2025-05-30 06:51:01'),
(32, 13, NULL, 1, 'SALE', 'Order #1', 3, '2025-05-30 09:14:12'),
(33, 15, NULL, 1, 'SALE', 'Order #1', 3, '2025-05-30 09:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `contact_name` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_name`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'Industrial Supplies Co.', 'John Smith', '555-123-4567', 'john@industrial-supplies.com', '123 Factory Rd, Industrial Park', '2025-05-30 06:45:50'),
(2, 'Tech Components Ltd', 'Sarah Johnson', '555-765-4321', 'sarah@techcomponents.com', '456 Circuit Ave, Tech District', '2025-05-30 06:45:50'),
(3, 'Construction Materials Inc', 'Mike Builder', '555-987-6543', 'mike@constructionmaterials.com', '789 Foundation St, Builder Zone', '2025-05-30 06:45:50'),
(4, 'Office Supply Depot', 'Lisa Admin', '555-456-7890', 'lisa@officesupplies.com', '101 Corporate Drive, Business Park', '2025-05-30 06:45:50'),
(5, 'Professional Tools & Equipment', 'David Craftsman', '555-321-6547', 'david@protools.com', '202 Workshop Lane, Tool Town', '2025-05-30 06:45:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(2, 'test', 'test@gmail.com', '$2y$10$jzVP6qlqCha/MJgJadXBM.nlXEsdDXKcb2wvPSx4zpQa0GvUhXdba', 'admin', '2025-05-29 18:31:37'),
(3, 'user', 'user@gmaill.com', '$2y$10$jvvE9cw8A1gJVlAlNYf3kOrXtN7yPJDy4GSkT45xOGmMsO8qH3uZu', 'user', '2025-05-30 09:12:41'),
(4, 'staff', 'staff@example.com', '$2y$10$iNezzwELUnXgoK7ktFuSHuZFrHSKpo3TU84G14sWLIF/E5SPVDhUK', 'staff', '2025-05-30 12:47:51');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_product_stock`
-- (See below for the actual view)
--
CREATE TABLE `v_product_stock` (
`id` int(10) unsigned
,`sku` varchar(50)
,`name` varchar(120)
,`on_hand` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Structure for view `v_product_stock`
--
DROP TABLE IF EXISTS `v_product_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_product_stock`  AS SELECT `p`.`id` AS `id`, `p`.`sku` AS `sku`, `p`.`name` AS `name`, coalesce(sum(`sm`.`qty`),0) AS `on_hand` FROM (`products` `p` left join `stock_movements` `sm` on(`sm`.`product_id` = `p`.`id`)) GROUP BY `p`.`id`, `p`.`sku`, `p`.`name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `ordered_by` (`ordered_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_id` (`po_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `moved_by` (`moved_by`),
  ADD KEY `product_id` (`product_id`,`moved_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`moved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
