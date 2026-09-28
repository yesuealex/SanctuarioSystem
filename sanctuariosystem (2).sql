-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 18, 2026 at 08:45 AM
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
-- Database: `sanctuariosystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 7, 'Added Crop', 'Crop: Tomato in plot_b', '2026-04-12 13:30:12'),
(2, 7, 'Added Crop', 'Crop: cabbage in plot_c', '2026-04-12 16:06:18'),
(3, 7, 'Added Crop', 'Crop: Carrot in plot_c', '2026-04-12 16:06:36'),
(4, 7, 'Added Crop', 'Crop: Tomato in plot_b', '2026-04-12 16:06:56'),
(5, 7, 'Added Crop', 'Crop: Carrot in plot_a', '2026-04-12 16:07:13'),
(6, 7, 'Edited Crop', 'Crop ID: 19 updated', '2026-04-12 17:34:20'),
(7, 7, 'Deleted Crop', 'Crop ID: 11 deleted', '2026-04-12 17:45:38'),
(8, 7, 'Deleted Crop', 'Crop ID: 19 deleted', '2026-04-12 17:45:42'),
(9, 1, 'Edited Crop', 'Crop ID: 12 updated', '2026-08-13 15:05:06'),
(10, 1, 'Edited Crop', 'Crop ID: 12 updated', '2026-08-13 15:12:05'),
(11, 1, 'Deleted Crop', 'Crop ID: 12 deleted', '2026-08-15 14:02:14'),
(12, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-05 06:14:27'),
(13, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-05 06:14:35'),
(14, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-05 06:14:41'),
(15, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-05 06:17:38'),
(16, 1, 'Edited Crop', 'Crop ID: 18 updated', '2026-09-05 06:19:31'),
(17, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-05 06:29:33'),
(18, 1, 'Edited Crop', 'Crop ID: 18 updated', '2026-09-05 06:29:44'),
(19, 1, 'Edited Crop', 'Crop ID: 17 updated', '2026-09-05 06:29:50'),
(20, 1, 'Edited Crop', 'Crop ID: 16 updated', '2026-09-05 06:29:59'),
(21, 1, 'Edited Crop', 'Crop ID: 15 updated', '2026-09-05 06:30:03'),
(22, 1, 'Edited Crop', 'Crop ID: 14 updated', '2026-09-05 06:30:11'),
(23, 1, 'Edited Crop', 'Crop ID: 13 updated', '2026-09-05 06:30:15'),
(24, 1, 'Added Crop', 'Crop: Bawang ni Chaeyoung, Plot ID: 3', '2026-09-05 06:31:17'),
(25, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-08 10:24:22'),
(26, 1, 'Edited Crop', 'Crop ID: 13 updated', '2026-09-08 10:24:46'),
(27, 1, 'Edited Livestock', 'Livestock ID: 11 updated', '2026-09-11 10:19:33'),
(28, 1, 'Edited Livestock', 'Livestock ID: 11 updated', '2026-09-11 10:56:43'),
(29, 1, 'Edited Livestock', 'Livestock ID: 11 updated', '2026-09-11 11:47:12'),
(30, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 12:35:24'),
(31, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 12:35:24'),
(32, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-11 14:50:54'),
(33, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-11 14:50:54'),
(34, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-11 14:52:46'),
(35, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-11 14:52:46'),
(36, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 15:05:09'),
(37, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 15:05:09'),
(38, 1, 'Edited Crop', 'Crop ID: 22 (UNASSIGNED CROP) updated.', '2026-09-11 15:05:39'),
(39, 1, 'Edited Crop', 'Crop ID: 22 (UNASSIGNED CROP) updated.', '2026-09-11 15:05:39'),
(40, 1, 'Edited Crop', 'Crop ID: 22 updated', '2026-09-14 13:41:52'),
(41, 1, 'Edited Crop', 'Crop ID: 21 updated', '2026-09-14 13:42:02'),
(42, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-14 13:42:30'),
(43, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-14 13:42:30'),
(44, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-17 05:10:01'),
(45, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-17 05:10:01'),
(46, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-17 05:10:31'),
(47, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-17 05:10:31'),
(48, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-17 05:13:29'),
(49, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-17 05:13:29'),
(50, 1, 'Edited Crop', 'Crop ID: 30 updated', '2026-09-17 05:58:00'),
(51, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-17 05:58:34'),
(52, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-17 05:58:34'),
(53, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-17 06:52:59'),
(54, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-17 06:52:59'),
(55, 1, 'Edited Crop', 'Crop ID: 22 (OKRA) updated.', '2026-09-17 07:43:13'),
(56, 1, 'Edited Crop', 'Crop ID: 22 (OKRA) updated.', '2026-09-17 07:43:13'),
(57, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 08:29:31'),
(58, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 08:29:31'),
(59, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 08:30:47'),
(60, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 08:30:47'),
(61, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 08:54:22'),
(62, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 08:54:22'),
(63, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 10:03:55'),
(64, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 10:03:55');

-- --------------------------------------------------------

--
-- Table structure for table `crops`
--

CREATE TABLE `crops` (
  `crop_id` int(11) NOT NULL,
  `recipient` varchar(100) DEFAULT NULL,
  `plot_id` int(11) DEFAULT NULL,
  `plot_name` varchar(100) DEFAULT NULL,
  `crop_name` varchar(100) DEFAULT NULL,
  `variety` varchar(100) DEFAULT NULL,
  `pcs` int(11) DEFAULT NULL,
  `date_sowing` date DEFAULT NULL,
  `germination_rate` double DEFAULT NULL,
  `target_transplant_date` date DEFAULT NULL,
  `target_area` varchar(100) DEFAULT NULL,
  `seedlings_received` int(11) DEFAULT NULL,
  `date_transplanted` date DEFAULT NULL,
  `expected_harvest` date DEFAULT NULL,
  `status` enum('planted','growing','ready','harvested') DEFAULT 'planted',
  `image` varchar(255) DEFAULT NULL,
  `transplanted_area` varchar(100) NOT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `bed_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crops`
--

INSERT INTO `crops` (`crop_id`, `recipient`, `plot_id`, `plot_name`, `crop_name`, `variety`, `pcs`, `date_sowing`, `germination_rate`, `target_transplant_date`, `target_area`, `seedlings_received`, `date_transplanted`, `expected_harvest`, `status`, `image`, `transplanted_area`, `archived`, `bed_id`) VALUES
(1, 'Mr. Villa', 1, NULL, 'EGGPLANT  NI SUNGHOON', 'leaf', 6, NULL, 79, NULL, '', 0, '2026-09-02', '2026-09-23', 'growing', 'crops/uploads/crop_6aab8e4b22a16_1789627979.jpg', 'Nursery', 0, NULL),
(13, 'Mr. Villa', 1, NULL, 'CORN NI REI', 'majesta', 20, NULL, 10, NULL, NULL, NULL, '2026-04-02', '2026-04-04', 'planted', NULL, 'Nursery', 0, NULL),
(14, 'Mr. Villa', 2, NULL, 'Lettuce', 'majesta', 5, NULL, 89, NULL, NULL, NULL, '2026-04-10', '2026-04-30', 'planted', NULL, 'MNO', 0, NULL),
(15, 'Mr. Villa', 1, NULL, 'TOMATO', 'majesta', 5, NULL, 80, NULL, '', 0, '2026-04-04', '2026-04-12', 'planted', NULL, 'MNO', 0, NULL),
(16, 'Mr. Villa', 3, NULL, 'cabbage', 'leaf', 5, NULL, 79, NULL, NULL, NULL, '2026-04-01', '2026-04-13', 'planted', NULL, 'MNO', 0, NULL),
(17, 'Mr. Villa', 3, NULL, 'Carrot', 'majesta', 5, NULL, 80, NULL, NULL, NULL, '2026-03-05', '2026-04-13', 'planted', NULL, 'Nursery', 0, NULL),
(18, 'Mr. Villa', 2, NULL, 'Tomato', 'leaf', 5, NULL, 79, NULL, NULL, NULL, '2026-02-13', '2026-07-25', 'planted', NULL, 'Nursery', 0, NULL),
(20, 'Mr. Villa', 3, NULL, 'Bawang ni Chaeyoung', 'leaf', 5, NULL, 80, NULL, NULL, NULL, '2026-09-03', '2026-09-05', 'planted', NULL, 'Nursery', 0, NULL),
(21, 'Not assigned', 1, NULL, 'OKRA', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-11', NULL, 'planted', NULL, 'A4', 0, NULL),
(22, 'Not assigned', 1, NULL, 'OKRA', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-11', NULL, 'planted', 'crops/uploads/crop_6aa418c3bd08f_1789139139.jpg', 'A4', 0, NULL),
(23, 'Not assigned', 1, NULL, 'LETTUCE', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-11', NULL, 'planted', NULL, 'A7', 0, NULL),
(24, 'Not assigned', 1, NULL, 'LETTUCE', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-11', NULL, 'planted', NULL, 'A7', 0, NULL),
(25, 'Not assigned', 1, NULL, 'Unassigned Crop', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-14', NULL, 'planted', NULL, 'A16', 0, NULL),
(26, 'Not assigned', 1, NULL, 'Unassigned Crop', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-14', NULL, 'planted', NULL, 'A16', 0, NULL),
(27, 'MR.  VILLA', 1, NULL, 'LETTUCE', 'LEAFY', 12, '2026-09-01', 71, '2026-09-03', '0', 12, '2026-09-17', NULL, 'planted', 'crops/uploads/crop_6aab7629880cb_1789621801.png', 'A17', 0, NULL),
(28, 'MR.  VILLA', 1, NULL, 'LETTUCE', 'LEAFY', 12, '2026-09-01', 71, '2026-09-03', '0', 12, '2026-09-17', NULL, 'planted', 'crops/uploads/crop_6aab76298b824_1789621801.png', 'A17', 0, NULL),
(29, 'Not assigned', 1, NULL, 'Unassigned Crop', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-17', NULL, 'planted', 'crops/uploads/crop_6aab764731dd4_1789621831.png', 'A17', 0, NULL),
(30, 'Not assigned', 1, NULL, 'Unassigned Crop', 'No variety assigned', 0, NULL, 0, NULL, '0', 0, '2026-09-17', NULL, 'planted', 'crops/uploads/crop_6aab7647336a8_1789621831.png', 'A17', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `inventory_id` int(10) UNSIGNED NOT NULL,
  `date_received` date NOT NULL,
  `category` enum('Seeds','Fertilizer','Tools','Feeds','Others') NOT NULL DEFAULT 'Others',
  `item_name` varchar(150) NOT NULL,
  `variety` varchar(150) DEFAULT NULL,
  `brand` varchar(150) DEFAULT NULL,
  `pieces` varchar(100) DEFAULT NULL,
  `quantity` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('In Stock','Low Stock','No Stock') NOT NULL DEFAULT 'In Stock',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`inventory_id`, `date_received`, `category`, `item_name`, `variety`, `brand`, `pieces`, `quantity`, `price`, `status`, `is_archived`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-09-14', 'Seeds', 'asd', 'asda', 'asdas', '12', '12', 1232.00, 'In Stock', 0, 1, '2026-09-14 14:17:33', '2026-09-14 14:17:33'),
(2, '2026-09-14', 'Fertilizer', 'asdasdasd', 'asdaasdasd', 'asdassd', '1212', '12121', 1232.00, 'Low Stock', 0, 1, '2026-09-14 14:17:46', '2026-09-14 14:17:46'),
(3, '2026-09-14', 'Others', 'sdasd', 'dasdas', 'asdasd2', '123213', '324234', 234234.00, 'No Stock', 1, 1, '2026-09-14 14:17:57', '2026-09-14 14:18:21');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_requests`
--

CREATE TABLE `inventory_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  `requested_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `request_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `livestocks`
--

CREATE TABLE `livestocks` (
  `livestock_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` varchar(100) NOT NULL,
  `estimated_weight` decimal(10,2) DEFAULT NULL,
  `for_sale` tinyint(1) DEFAULT 0,
  `number` int(100) NOT NULL,
  `sex` enum('male','female','mixed') NOT NULL,
  `feeding_schedule` varchar(100) NOT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `house_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `livestocks`
--

INSERT INTO `livestocks` (`livestock_id`, `type`, `name`, `breed`, `date_of_birth`, `age`, `estimated_weight`, `for_sale`, `number`, `sex`, `feeding_schedule`, `archived`, `house_id`, `notes`, `image`) VALUES
(5, 'rabbit', NULL, NULL, NULL, '1', NULL, 0, 3, 'mixed', '1pm', 0, NULL, NULL, NULL),
(6, 'rabbit', NULL, NULL, NULL, '2', NULL, 0, 4, 'mixed', '1pm', 0, NULL, NULL, NULL),
(7, 'Goat', 'Gino Buck', 'Native', '2025-03-15', '17 Months', 25.00, 0, 1, 'male', 'Morning and Afternoon', 0, NULL, NULL, NULL),
(8, 'Goat', 'Bella', 'Anglo-Nubian', '2024-08-20', '23 Months', 32.50, 0, 1, 'female', 'Morning and Afternoon', 0, NULL, NULL, NULL),
(9, 'Chicken', 'Coco', 'Native', '2026-01-10', '7 Months', 1.80, 0, 5, 'female', 'Morning', 0, NULL, NULL, NULL),
(10, 'Pig', 'Bambi', 'Native', '2025-05-12', '15 Months', 45.00, 1, 1, 'female', 'Morning and Afternoon', 0, NULL, NULL, NULL),
(11, 'Goat', 'Gino', 'WOWOWOW', '2025-03-01', '10 months', 25.00, 0, 2, 'male', '1pm', 0, 1, 'Happy goatsdf', 'livestock/uploads/livestock_11_1789628608.jfif'),
(12, 'Goat', 'MEH', 'Native', '2026-09-17', '', NULL, 0, 1, 'female', 'MORNING', 0, 1, 'smol', 'livestock/uploads/livestock_12_1789636523.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `livestock_houses`
--

CREATE TABLE `livestock_houses` (
  `house_id` int(11) NOT NULL,
  `house_name` varchar(100) NOT NULL,
  `house_type` varchar(50) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `map_x` decimal(6,2) DEFAULT NULL,
  `map_y` decimal(6,2) DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `house_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `livestock_houses`
--

INSERT INTO `livestock_houses` (`house_id`, `house_name`, `house_type`, `image`, `map_x`, `map_y`, `archived`, `house_code`, `description`) VALUES
(1, 'Goat House', '', 'farmMap/goat_house.jpg', 45.00, 35.00, 0, 'goat-house', 'Main goat housing area'),
(2, 'Goat House 2', '', 'farmMap/goat_house_2.jpg', 50.00, 40.00, 0, 'goat-house-2', 'Secondary goat housing area'),
(3, 'Rabbit House', '', 'farmMap/rabbit_house.jpg', 55.00, 45.00, 0, 'rabbit-house', 'Rabbit housing area'),
(4, 'Poultry House', '', 'farmMap/poultry_house.jpg', 60.00, 50.00, 0, 'poultry-house', 'Poultry housing area'),
(5, 'Swine House', '', 'farmMap/swine_house.jpg', 65.00, 55.00, 0, 'swine-house', 'Swine housing area');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
  `status` enum('Pending','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `requester_id` int(10) UNSIGNED DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`request_id`, `title`, `description`, `location`, `priority`, `status`, `requester_id`, `assigned_to`, `created_by`, `created_at`, `assigned_at`, `completed_at`, `updated_at`) VALUES
(1, 'Repair', 'asdasd', 'PLOT A', 'Medium', 'Cancelled', NULL, 9, 1, '2026-09-14 13:52:01', '2026-09-14 21:59:34', NULL, '2026-09-17 05:06:29'),
(2, 'asdas', 'asdsads', 'asdsad', 'High', 'Completed', NULL, 9, 1, '2026-09-14 14:22:29', '2026-09-14 22:22:36', '2026-09-14 22:22:54', '2026-09-14 14:22:54');

-- --------------------------------------------------------

--
-- Table structure for table `plots`
--

CREATE TABLE `plots` (
  `plot_id` int(11) NOT NULL,
  `plot_code` varchar(20) NOT NULL,
  `plot_name` varchar(100) DEFAULT NULL,
  `map_x` decimal(6,2) DEFAULT NULL,
  `map_y` decimal(6,2) DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plots`
--

INSERT INTO `plots` (`plot_id`, `plot_code`, `plot_name`, `map_x`, `map_y`, `archived`) VALUES
(1, 'plot_a', 'Plot A', NULL, NULL, 0),
(2, 'plot_b', 'Plot B', NULL, NULL, 0),
(3, 'plot_c', 'Plot C', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `plot_beds`
--

CREATE TABLE `plot_beds` (
  `bed_id` int(11) NOT NULL,
  `plot_id` int(11) NOT NULL,
  `bed_number` int(11) NOT NULL,
  `bed_code` varchar(50) NOT NULL,
  `map_x` decimal(6,2) DEFAULT NULL,
  `map_y` decimal(6,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_activities`
--

CREATE TABLE `staff_activities` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `activity_type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `icon` varchar(50) DEFAULT 'fa-circle-info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(50) NOT NULL,
  `role` enum('super admin','admin','staff','maintenance') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `password`, `role`) VALUES
(1, 'Manalo', 'admin1', 'admin1pass', 'admin'),
(4, 'Liaga', 'staff1', 'staff1pass', 'staff'),
(5, 'super admin1', 'superAdmin1', 'sAdmin1pass', 'super admin'),
(7, 'Mr. Villa', 'adminVilla', 'adminVilla', 'admin'),
(8, 'Manalo', 'staffManalo', 'staffManalo', 'staff'),
(9, 'joyce', 'maintenance', 'maintenancespass', 'maintenance');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `crops`
--
ALTER TABLE `crops`
  ADD PRIMARY KEY (`crop_id`),
  ADD KEY `fk_crops_plot` (`plot_id`),
  ADD KEY `fk_crops_bed` (`bed_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `idx_inventory_category` (`category`),
  ADD KEY `idx_inventory_status` (`status`),
  ADD KEY `idx_inventory_archived` (`is_archived`),
  ADD KEY `idx_inventory_created_by` (`created_by`);

--
-- Indexes for table `inventory_requests`
--
ALTER TABLE `inventory_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_inventory_requests_status` (`status`),
  ADD KEY `idx_inventory_requests_requested_by` (`requested_by`),
  ADD KEY `idx_inventory_requests_created_at` (`created_at`);

--
-- Indexes for table `livestocks`
--
ALTER TABLE `livestocks`
  ADD PRIMARY KEY (`livestock_id`),
  ADD KEY `fk_livestock_house` (`house_id`);

--
-- Indexes for table `livestock_houses`
--
ALTER TABLE `livestock_houses`
  ADD PRIMARY KEY (`house_id`),
  ADD UNIQUE KEY `house_code` (`house_code`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_maintenance_status` (`status`),
  ADD KEY `idx_maintenance_assigned_to` (`assigned_to`),
  ADD KEY `idx_maintenance_requester` (`requester_id`),
  ADD KEY `idx_maintenance_created_by` (`created_by`),
  ADD KEY `idx_maintenance_created_at` (`created_at`);

--
-- Indexes for table `plots`
--
ALTER TABLE `plots`
  ADD PRIMARY KEY (`plot_id`);

--
-- Indexes for table `plot_beds`
--
ALTER TABLE `plot_beds`
  ADD PRIMARY KEY (`bed_id`),
  ADD KEY `fk_plot_beds_plot` (`plot_id`);

--
-- Indexes for table `staff_activities`
--
ALTER TABLE `staff_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `crops`
--
ALTER TABLE `crops`
  MODIFY `crop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `inventory_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_requests`
--
ALTER TABLE `inventory_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `livestocks`
--
ALTER TABLE `livestocks`
  MODIFY `livestock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `livestock_houses`
--
ALTER TABLE `livestock_houses`
  MODIFY `house_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `plots`
--
ALTER TABLE `plots`
  MODIFY `plot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `plot_beds`
--
ALTER TABLE `plot_beds`
  MODIFY `bed_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_activities`
--
ALTER TABLE `staff_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `crops`
--
ALTER TABLE `crops`
  ADD CONSTRAINT `crops_ibfk_1` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`),
  ADD CONSTRAINT `fk_crops_bed` FOREIGN KEY (`bed_id`) REFERENCES `plot_beds` (`bed_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_crops_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `livestocks`
--
ALTER TABLE `livestocks`
  ADD CONSTRAINT `fk_livestock_house` FOREIGN KEY (`house_id`) REFERENCES `livestock_houses` (`house_id`);

--
-- Constraints for table `plot_beds`
--
ALTER TABLE `plot_beds`
  ADD CONSTRAINT `fk_plot_beds_plot` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`plot_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_activities`
--
ALTER TABLE `staff_activities`
  ADD CONSTRAINT `fk_staff_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
