-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: May 23, 2025 at 12:22 PM
-- Server version: 8.0.40
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `supplies`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` bigint NOT NULL,
  `user_id` int DEFAULT NULL,
  `username_snapshot` varchar(50) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `target_entity` varchar(50) DEFAULT NULL,
  `target_entity_id` int DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(2, 'Tyres', 'tyres of any size but new', '2025-05-12 13:56:32', '2025-05-21 13:44:57'),
(3, 'test', 'category for testing items.', '2025-05-15 12:47:03', '2025-05-15 21:20:52'),
(4, 'others', 'other items', '2025-05-19 13:04:48', '2025-05-19 13:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'default_vat_rate', '16.50', '2025-05-09 12:52:22', '2025-05-09 12:52:22'),
(2, 'default_ppda_rate', '1.00', '2025-05-09 12:52:22', '2025-05-09 12:52:22'),
(3, 'company_tpin', '', '2025-05-09 12:52:22', '2025-05-09 12:52:22'),
(4, 'default_signature_path', '', '2025-05-09 12:52:22', '2025-05-09 12:52:22'),
(5, 'default_company_logo_path', '', '2025-05-09 12:52:22', '2025-05-09 12:52:22');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `customer_code` varchar(10) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city_location` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tpin_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by_user_id` int NOT NULL,
  `updated_by_user_id` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `name`, `address_line1`, `address_line2`, `city_location`, `phone`, `email`, `tpin_no`, `created_at`, `updated_at`, `created_by_user_id`, `updated_by_user_id`) VALUES
(5, 'CB003', 'CENTENARY BANK LIMITED', 'Conventional Drive', 'P.O.BOX 31567', 'Lilongwe, Malawi', NULL, 'ruth.chitsulo@centenarybank.co.mw', NULL, '2025-05-22 12:48:49', '2025-05-22 12:48:49', 3, 3),
(2, 'TST', 'Test company', '123 eml street', NULL, 'westeros', '11226969', 'info@test.com', '7778874', '2025-05-16 07:32:47', '2025-05-16 07:32:47', 1, NULL),
(3, 'SHL', 'Sunbird Hotel Livingstonia', 'S122, Salima', NULL, 'Salima', '+265888965222 / +265111263222', 'livingstonia@sunbirdmalawi.com', '1', '2025-05-19 13:02:10', '2025-05-19 13:02:10', 1, 1),
(4, 'KCG', 'KAASINTHULA CANE GROWERS', 'PO Box 138, Chikwawa, Malawi', NULL, 'Chikwawa, Malawi', '+265 42 0320', 'kasinthula@africa-online.net', '1', '2025-05-20 13:18:27', '2025-05-20 13:18:27', 1, 1),
(6, 'CL6', 'CANDLEX LIMITED', 'Macleod Road, Makata', 'P.O. Box 80140', 'Masalema, Blantyre', NULL, 'candlex@candlexmw.com', NULL, '2025-05-22 15:17:28', '2025-05-22 15:17:28', 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `default_unit_price` decimal(10,2) DEFAULT NULL,
  `default_unit_of_measurement` varchar(50) DEFAULT NULL,
  `default_image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by_user_id` int NOT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `description`, `default_unit_price`, `default_unit_of_measurement`, `default_image_path`, `created_at`, `updated_at`, `created_by_user_id`, `updated_by_user_id`, `category_id`) VALUES
(25, 'SDFC', 'FOLDING CHAIRS/ WHITE', NULL, 95000.00, 'pcs', 'uploads/product_images/prod_682f1dc1491277.62176182.jpg', '2025-05-22 12:51:13', '2025-05-22 12:51:13', 3, 3, 4),
(26, 'SDFT', 'FOLDING TABLES/ WHITE', NULL, 235000.00, 'PCS', NULL, '2025-05-22 12:53:34', '2025-05-22 12:53:34', 3, 3, 4),
(27, '2055516', 'TYRE 205/65R15', NULL, 200000.00, 'pcs', NULL, '2025-05-22 15:18:27', '2025-05-23 09:14:48', 3, 3, 2),
(4, 'EL1', 'Galvanized elbow', 'galvanized elbow measured in inches', 12500.00, 'Inches', NULL, '2025-05-19 13:06:10', '2025-05-19 13:06:10', 1, 1, 4),
(5, 'EL2', 'Galvanized Nipples', 'galvanized nipples', 13500.00, 'Inches', NULL, '2025-05-19 13:07:03', '2025-05-19 13:07:03', 1, 1, 4),
(6, 'SO1', 'Galvanized Sockets', 'galvanized sockets measured in inches', 11500.00, 'Inches', NULL, '2025-05-19 13:07:45', '2025-05-19 13:07:45', 1, 1, 4),
(7, 'IPST4', '3/4 IPS Tee', '3/4 IPS Tee', 3500.00, 'pcs', NULL, '2025-05-19 13:09:17', '2025-05-19 13:09:17', 1, 1, 4),
(8, 'IPST5', '3/4 IPS Nipples', '3/4 IPS nipples', 3500.00, 'pcs', NULL, '2025-05-19 13:10:14', '2025-05-19 13:10:14', 1, 1, 4),
(9, 'IPSLB1', '3/4 IPS Elbows', '3/4 IPS elbows', 4500.00, 'pcs', NULL, '2025-05-19 13:14:10', '2025-05-19 13:14:10', 1, 1, 4),
(10, 'ONP5', '3/4 IPS Unions', '3/4 IPS Unions', 10500.00, 'pcs', NULL, '2025-05-19 13:15:21', '2025-05-19 13:36:57', 1, 1, 4),
(11, 'CYLC6', 'Cylinder Lock', 'Cylinder Lock', 260000.00, 'pcs', NULL, '2025-05-19 13:16:37', '2025-05-19 13:16:37', 1, 1, 4),
(12, 'DPM9', 'Drum Pump', 'drum pump', 150000.00, 'pcs', NULL, '2025-05-19 13:17:41', '2025-05-19 13:17:41', 1, 1, 4),
(13, 'GLW7', '5 Liters Gloss (white)', '5 Liters Gloss (white)', 140000.00, 'pcs', NULL, '2025-05-19 13:18:51', '2025-05-19 13:18:51', 1, 1, 4),
(14, 'GLMG3', '5 Liters Gloss (Misty Gray)', '5 Liters Gloss (white)', 135000.00, 'pcs', NULL, '2025-05-19 13:20:05', '2025-05-19 13:20:05', 1, 1, 4),
(15, 'HDPEMD34', 'HDPE MAD 1.5 inches x a.25 inches', 'HDPE MAD 1.5 inches x a.25 inches', 80000.00, 'pcs', NULL, '2025-05-19 13:21:39', '2025-05-19 13:21:39', 1, 1, 4),
(16, 'GVO4', 'Galvanized Union 1.25', 'Galvanized Union 1.25', 21500.00, 'pcs', NULL, '2025-05-19 13:22:49', '2025-05-19 13:35:53', 1, 1, 4),
(17, 'GVSO5', 'Galvanized Socket 1.25', 'Galvanized socket 1.25', 13500.00, 'pcs', NULL, '2025-05-19 13:23:22', '2025-05-19 13:23:22', 1, 1, 4),
(18, 'IPSP1', 'IPS Pipe 1.25', 'IPS Pipe 1.25', 65000.00, 'pcs', NULL, '2025-05-19 13:24:12', '2025-05-19 13:24:12', 1, 1, 4),
(19, 'RTT9', 'Rolls Thread Tape', 'Rolls Thread Tape', 15000.00, 'pcs', NULL, '2025-05-19 13:25:05', '2025-05-19 13:25:05', 1, 1, 4),
(20, 'PBFB7', 'Pens Basin Fixing Bolt', 'Pens Basin Fixing Bolt', 9500.00, 'pcs', NULL, '2025-05-19 13:25:51', '2025-05-19 13:25:51', 1, 1, 4),
(21, 'TVB43', 'TV Brackets 43 Inches', 'TV Brackets 43 Inches', 350000.00, 'inches', NULL, '2025-05-19 13:27:11', '2025-05-19 13:27:11', 1, 1, 4),
(22, 'CJ6', 'Car Jumpers', 'Car Jumpers', 350000.00, 'pcs', NULL, '2025-05-19 13:27:56', '2025-05-19 13:27:56', 1, 1, 4),
(23, 'HM518445', 'TIMKEN FAG Bearing KHM 518445', 'FAG bearing KHM 518445', 199500.00, 'pcs', NULL, '2025-05-20 13:14:39', '2025-05-20 13:15:47', 1, 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int NOT NULL,
  `quotation_number` varchar(100) DEFAULT NULL,
  `shop_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `customer_name_override` varchar(255) DEFAULT NULL,
  `customer_address_override` text,
  `quotation_date` date DEFAULT NULL,
  `company_tpin` varchar(50) DEFAULT NULL,
  `notes_general` text,
  `delivery_period` varchar(255) DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `quotation_validity_days` int DEFAULT NULL,
  `mra_wht_note` text,
  `apply_ppda_levy` tinyint(1) DEFAULT '0',
  `ppda_levy_percentage` decimal(5,2) DEFAULT '1.00',
  `vat_percentage` decimal(5,2) DEFAULT '16.50',
  `gross_total_amount` decimal(15,2) DEFAULT NULL,
  `ppda_levy_amount` decimal(15,2) DEFAULT NULL,
  `amount_before_vat` decimal(15,2) DEFAULT NULL,
  `vat_amount` decimal(15,2) DEFAULT NULL,
  `total_net_amount` decimal(15,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Draft',
  `created_by_user_id` int NOT NULL,
  `updated_by_user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `mra_wht_note_content` varchar(255) DEFAULT NULL,
  `approved_by_user_id` int DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `admin_notes` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `quotation_number`, `shop_id`, `customer_id`, `customer_name_override`, `customer_address_override`, `quotation_date`, `company_tpin`, `notes_general`, `delivery_period`, `payment_terms`, `quotation_validity_days`, `mra_wht_note`, `apply_ppda_levy`, `ppda_levy_percentage`, `vat_percentage`, `gross_total_amount`, `ppda_levy_amount`, `amount_before_vat`, `vat_amount`, `total_net_amount`, `status`, `created_by_user_id`, `updated_by_user_id`, `created_at`, `updated_at`, `mra_wht_note_content`, `approved_by_user_id`, `approval_date`, `admin_notes`) VALUES
(15, 'SDL/BT/SHL-202505190001', 1, 3, NULL, NULL, '2025-05-19', '1', '', '', '', 30, NULL, 0, 0.00, 16.50, 4786000.00, 0.00, 4786000.00, 789690.00, 5575690.00, 'Draft', 5, 1, '2025-05-19 13:40:19', '2025-05-21 09:52:55', '', NULL, NULL, NULL),
(17, 'SDL/BT/KCG-202505200001', 1, 4, NULL, NULL, '2025-05-20', '1', '', '', '', 30, NULL, 0, 0.00, 16.50, 798000.00, 0.00, 0.00, 131670.00, 929670.00, 'Approved', 1, 1, '2025-05-20 13:19:21', '2025-05-22 21:01:00', '', 3, '2025-05-22 23:01:00', ''),
(18, 'SDL/TST/TST-202505210001', 2, 2, NULL, NULL, '2025-05-21', '1', '', '', '', 30, NULL, 1, 1.00, 16.50, 3500.00, 35.00, 0.00, 577.50, 4112.50, 'Rejected', 1, 1, '2025-05-21 09:37:01', '2025-05-21 13:28:13', '', 3, '2025-05-21 15:28:13', 'HIGH PRICES'),
(19, 'SDL/TST/TST-202505210002', 2, 2, NULL, NULL, '2025-05-21', '', '', '', '', 30, NULL, 0, 0.00, 16.50, 350000.00, 0.00, 0.00, 57750.00, 407750.00, 'Approved', 1, 1, '2025-05-21 09:45:11', '2025-05-21 13:25:08', '', 3, '2025-05-21 15:25:08', ''),
(20, 'SDL/SD001/TST-202505210001', 3, 2, NULL, NULL, '2025-05-21', '', '', '7', '30', 30, NULL, 1, 1.00, 16.50, 4843000.00, 48430.00, 0.00, 799095.00, 5690525.00, 'Draft', 3, 3, '2025-05-21 13:52:36', '2025-05-21 13:52:36', '', NULL, NULL, NULL),
(21, 'SDL/SD002/CB003-202505220001', 1, 5, NULL, NULL, '2025-05-22', '', '', '', '', 30, NULL, 0, 0.00, 16.50, 12750000.00, 0.00, 0.00, 2103750.00, 14853750.00, 'Draft', 3, 3, '2025-05-22 12:54:38', '2025-05-22 12:54:38', '', NULL, NULL, NULL),
(22, 'SDL/SD001/CL6-202505220001', 3, 6, NULL, NULL, '2025-05-22', '', '', '', '', 30, NULL, 0, 0.00, 16.50, 400000.00, 0.00, 0.00, 66000.00, 466000.00, 'Draft', 3, 3, '2025-05-22 15:19:22', '2025-05-22 15:19:22', '', NULL, NULL, NULL),
(23, 'SDL/SD001/CL6-202505220002', 3, 6, NULL, NULL, '2025-05-22', '', '', '7 days', '30 days', 30, NULL, 1, 1.00, 16.50, 65300000.00, 653000.00, 0.00, 10774500.00, 76727500.00, 'Draft', 3, 3, '2025-05-22 21:04:06', '2025-05-22 21:04:06', '', NULL, NULL, NULL),
(24, 'SDL/SD001/CL6-202505230001', 3, 6, NULL, NULL, '2025-05-23', '', '', '', '', 30, NULL, 0, 0.00, 16.50, 400000.00, 0.00, 0.00, 66000.00, 466000.00, 'Draft', 3, 3, '2025-05-23 09:15:39', '2025-05-23 09:15:39', '', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int NOT NULL,
  `quotation_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `item_number` int DEFAULT NULL,
  `description` text,
  `image_path_override` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit_of_measurement` varchar(50) DEFAULT NULL,
  `rate_per_unit` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by_user_id` int NOT NULL,
  `updated_by_user_id` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quotation_id`, `product_id`, `item_number`, `description`, `image_path_override`, `quantity`, `unit_of_measurement`, `rate_per_unit`, `total_amount`, `created_at`, `updated_at`, `created_by_user_id`, `updated_by_user_id`) VALUES
(1, 5, NULL, 1, 'jj', NULL, 5.00, '', 50000.00, 250000.00, '2025-05-15 12:40:47', '2025-05-15 12:40:47', 1, NULL),
(2, 6, 3, 1, 'test product', NULL, 5.00, 'Kg', 20000.00, 100000.00, '2025-05-16 07:32:47', '2025-05-16 07:32:47', 1, NULL),
(3, 6, 2, 2, 'good tyres', NULL, 8.00, 'pcs', 50000.00, 400000.00, '2025-05-16 07:32:47', '2025-05-16 07:32:47', 1, NULL),
(4, 12, 2, 1, 'good tyres', NULL, 1.00, '', 50000.00, 50000.00, '2025-05-18 11:12:21', '2025-05-18 11:12:21', 1, 1),
(5, 13, 2, 1, 'good tyres', NULL, 1.00, '', 50000.00, 50000.00, '2025-05-19 09:36:31', '2025-05-19 09:36:31', 1, 1),
(6, 13, 3, 2, 'test product', NULL, 5.00, '', 45000.00, 225000.00, '2025-05-19 09:36:31', '2025-05-19 09:36:31', 1, 1),
(7, 14, 2, 1, 'good tyres', NULL, 1.00, '', 50000.00, 50000.00, '2025-05-19 09:52:38', '2025-05-19 09:52:38', 1, 1),
(8, 15, 4, 1, 'galvanized elbow measured in inches', NULL, 2.00, '', 12500.00, 25000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(9, 15, 5, 2, 'galvanized nipples', NULL, 2.00, '', 13500.00, 27000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(10, 15, 6, 3, 'galvanized sockets measured in inches', NULL, 2.00, '', 11500.00, 23000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(11, 15, 7, 4, '3/4 IPS Tee', NULL, 4.00, '', 6500.00, 26000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(12, 15, 8, 5, '3/4 IPS nipples', NULL, 4.00, '', 3500.00, 14000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(13, 15, 9, 6, '3/4 IPS elbows', NULL, 4.00, '', 4500.00, 18000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(14, 15, 10, 7, '3/4 IPS Onions', NULL, 4.00, '', 10500.00, 42000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(15, 15, 11, 8, 'Cylinder Lock', NULL, 1.00, '', 260000.00, 260000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(16, 15, 12, 9, 'drum pump', NULL, 2.00, '', 150000.00, 300000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(17, 15, 13, 10, '5 Liters Gloss (white)', NULL, 5.00, '', 140000.00, 700000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(18, 15, 14, 11, '5 Liters Gloss (white)', NULL, 2.00, '', 135000.00, 270000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(19, 15, 15, 12, 'HDPE MAD 1.5 inches x a.25 inches', NULL, 6.00, '', 80000.00, 480000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(20, 15, 16, 13, 'Galvanized Onion 1.25', NULL, 6.00, '', 21500.00, 129000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(21, 15, 17, 14, 'Galvanized socket 1.25', NULL, 6.00, '', 13500.00, 81000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(22, 15, 18, 15, 'IPS Pipe 1.25', NULL, 1.00, '', 65000.00, 65000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(23, 15, 19, 16, 'Rolls Thread Tape', NULL, 10.00, '', 15000.00, 150000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(24, 15, 20, 17, 'Pens Basin Fixing Bolt', NULL, 8.00, '', 9500.00, 76000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(25, 15, 21, 18, 'TV Brackets 43 Inches', NULL, 5.00, '', 350000.00, 1750000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(26, 15, 22, 19, 'Car Jumpers', NULL, 1.00, '', 350000.00, 350000.00, '2025-05-19 13:40:19', '2025-05-19 13:40:19', 1, 1),
(27, 16, 5, 1, 'galvanized nipples', NULL, 1.00, '', 100.00, 100.00, '2025-05-20 12:57:53', '2025-05-20 12:57:53', 1, 1),
(28, 17, 23, 1, 'FAG bearing KHM 518445', NULL, 4.00, '', 199500.00, 798000.00, '2025-05-20 13:19:21', '2025-05-20 13:19:21', 1, 1),
(29, 18, 7, 1, '3/4 IPS Tee', NULL, 1.00, '', 3500.00, 3500.00, '2025-05-21 09:37:01', '2025-05-21 09:37:01', 1, 1),
(30, 19, 21, 1, 'TV Brackets 43 Inches', NULL, 1.00, '', 350000.00, 350000.00, '2025-05-21 09:45:11', '2025-05-21 09:45:11', 1, 1),
(31, 20, 14, 1, '5 Liters Gloss (white)', 'uploads/quotation_item_images/20_1_1747835556_IMG_0016.jpg', 19.00, '', 135000.00, 2565000.00, '2025-05-21 13:52:36', '2025-05-21 13:52:36', 3, 3),
(32, 20, 4, 2, 'galvanized elbow measured in inches', 'uploads/quotation_item_images/20_2_1747835556_A6A7FA99-DE36-4C82-A143-8215E293AAB8.JPG', 340.00, '', 6700.00, 2278000.00, '2025-05-21 13:52:36', '2025-05-21 13:52:36', 3, 3),
(33, 21, 25, 1, 'FOLDING CHAIRS/ WHITE', NULL, 60.00, '', 95000.00, 5700000.00, '2025-05-22 12:54:38', '2025-05-22 12:54:38', 3, 3),
(34, 21, 26, 2, 'FOLDING TABLES/ WHITE', NULL, 30.00, '', 235000.00, 7050000.00, '2025-05-22 12:54:38', '2025-05-22 12:54:38', 3, 3),
(35, 22, 27, 1, 'TYRE 205/55R16', NULL, 2.00, '', 200000.00, 400000.00, '2025-05-22 15:19:22', '2025-05-22 15:19:22', 3, 3),
(36, 23, 14, 1, '5 Liters Gloss (white)', NULL, 500.00, '', 130600.00, 65300000.00, '2025-05-22 21:04:06', '2025-05-22 21:04:06', 3, 3),
(37, 24, 27, 1, 'TYRE 205/65R15', NULL, 2.00, '', 200000.00, 400000.00, '2025-05-23 09:15:39', '2025-05-23 09:15:39', 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` int NOT NULL,
  `shop_code` varchar(10) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `tpin_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by_user_id` int NOT NULL,
  `updated_by_user_id` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `shop_code`, `name`, `address_line1`, `address_line2`, `city`, `country`, `phone`, `email`, `logo_path`, `tpin_no`, `created_at`, `updated_at`, `created_by_user_id`, `updated_by_user_id`) VALUES
(1, 'SD002', 'Supplies Direct Limited', 'Mzimba Road, Area 4 next to Garda World', 'P.O.BOX NO.5206', 'Lilongwe', 'Malawi', '0991168991 / 0997398298', 'info@suppliesdirectmw.com', 'uploads/shop_logos/logo_682311e2efd3a0.00480687.png', '70030009', '2025-05-13 09:33:22', '2025-05-21 13:38:48', 1, 3),
(2, 'TST', 'test shop', 'sda', 'ads', 'blantyre', 'malawi', '4652564', 'test@info.com', 'uploads/shop_logos/logo_68270bfe81db41.22510433.png', '54645', '2025-05-16 09:56:53', '2025-05-16 09:57:18', 1, 1),
(3, 'SD001', 'SUPPLIES DIRECT LIMITED', 'Churchill road, next to xerographics', 'P.O. Box 5206', 'Limbe CBD', 'MALAWI', '+265991168991', 'info@suppliesdirectltd.mw.com', 'uploads/shop_logos/logo_682dd674872d20.24838165.jpg', '70030009', '2025-05-21 13:34:44', '2025-05-21 13:35:47', 3, 3);

-- --------------------------------------------------------

--
-- Table structure for table `units_of_measurement`
--

CREATE TABLE `units_of_measurement` (
  `id` int NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units_of_measurement`
--

INSERT INTO `units_of_measurement` (`id`, `name`) VALUES
(1, 'PC'),
(2, 'Dozen'),
(3, 'KG'),
(4, '25KG Bag'),
(5, '50KG Bag'),
(6, 'Roll'),
(7, 'Meter'),
(8, 'Tins');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `role` enum('admin','manager','staff','supervisor','viewer') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$V50DVq8dmyLghHCNGmAmt.lH8cip/TfhTBAVhKyCQdU3EaLFO/6lK', 'Administrator', 'admin@sdl.com', 'admin', 1, '2025-05-21 13:09:57', '2025-05-12 12:34:11', '2025-05-21 13:09:57'),
(2, 'Aabidoon', '$2y$10$T1/ndkD.ypcFzXrb2cqBsOcuhdFByDj9Bpx502JVcMWHA3ZL.GYfy', 'Aabidoon', 'Aabidoon@sdl.com', 'supervisor', 1, '2025-05-19 09:46:42', '2025-05-12 12:56:05', '2025-05-19 09:46:42'),
(3, 'Muhammad', '$2y$10$WvrpeOE055q4yuqkeDuj7u7/eld8tXIStky0jEzkTEP7cGU81PeMq', 'Muhammad', 'suppliesdirectltd@gmail.com', 'admin', 1, '2025-05-22 14:26:50', '2025-05-21 08:08:54', '2025-05-22 14:26:50'),
(4, 'Lydia', '$2y$10$j/EY06TOl2rv7HrclHhlAeNHW/TVDDnVJv7yvDqrCqs/wWFSGkbu2', 'Lydia', 'lydia@suppliesdirect.com', 'staff', 1, NULL, '2025-05-21 08:10:12', '2025-05-21 08:10:12'),
(5, 'Denzel', '$2y$10$jmz60C6zNfylRpkdG91PAer9Czi8N6SAfZuvvez9.XUI2cff5a9gu', 'Denzel Khonje', 'denzel@suppliesdirect.com', 'supervisor', 1, '2025-05-21 09:36:19', '2025-05-21 08:18:01', '2025-05-21 09:36:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `updated_by_user_id` (`updated_by_user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `updated_by_user_id` (`updated_by_user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `updated_by_user_id` (`updated_by_user_id`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `updated_by_user_id` (`updated_by_user_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shop_code` (`shop_code`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `updated_by_user_id` (`updated_by_user_id`);

--
-- Indexes for table `units_of_measurement`
--
ALTER TABLE `units_of_measurement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

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
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `units_of_measurement`
--
ALTER TABLE `units_of_measurement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
