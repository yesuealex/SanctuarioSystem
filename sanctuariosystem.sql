-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2026 at 12:31 PM
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
(1, 7, 'Added Crop', 'Crop: Tomato in plot_b', '2026-04-12 05:30:12'),
(2, 7, 'Added Crop', 'Crop: cabbage in plot_c', '2026-04-12 08:06:18'),
(3, 7, 'Added Crop', 'Crop: Carrot in plot_c', '2026-04-12 08:06:36'),
(4, 7, 'Added Crop', 'Crop: Tomato in plot_b', '2026-04-12 08:06:56'),
(5, 7, 'Added Crop', 'Crop: Carrot in plot_a', '2026-04-12 08:07:13'),
(6, 7, 'Edited Crop', 'Crop ID: 19 updated', '2026-04-12 09:34:20'),
(7, 7, 'Deleted Crop', 'Crop ID: 11 deleted', '2026-04-12 09:45:38'),
(8, 7, 'Deleted Crop', 'Crop ID: 19 deleted', '2026-04-12 09:45:42'),
(9, 1, 'Edited Crop', 'Crop ID: 12 updated', '2026-08-13 07:05:06'),
(10, 1, 'Edited Crop', 'Crop ID: 12 updated', '2026-08-13 07:12:05'),
(11, 1, 'Deleted Crop', 'Crop ID: 12 deleted', '2026-08-15 06:02:14'),
(12, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-04 22:14:27'),
(13, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-04 22:14:35'),
(14, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-04 22:14:41'),
(15, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-04 22:17:38'),
(16, 1, 'Edited Crop', 'Crop ID: 18 updated', '2026-09-04 22:19:31'),
(17, 1, 'Edited Crop', 'Crop ID: 1 updated', '2026-09-04 22:29:33'),
(18, 1, 'Edited Crop', 'Crop ID: 18 updated', '2026-09-04 22:29:44'),
(19, 1, 'Edited Crop', 'Crop ID: 17 updated', '2026-09-04 22:29:50'),
(20, 1, 'Edited Crop', 'Crop ID: 16 updated', '2026-09-04 22:29:59'),
(21, 1, 'Edited Crop', 'Crop ID: 15 updated', '2026-09-04 22:30:03'),
(22, 1, 'Edited Crop', 'Crop ID: 14 updated', '2026-09-04 22:30:11'),
(23, 1, 'Edited Crop', 'Crop ID: 13 updated', '2026-09-04 22:30:15'),
(24, 1, 'Added Crop', 'Crop: Bawang ni Chaeyoung, Plot ID: 3', '2026-09-04 22:31:17'),
(25, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-08 02:24:22'),
(26, 1, 'Edited Crop', 'Crop ID: 13 updated', '2026-09-08 02:24:46'),
(27, 1, 'Edited Livestock', 'Livestock ID: 11 updated', '2026-09-11 02:19:33'),
(28, 1, 'Edited Livestock', 'Livestock ID: 11 updated', '2026-09-11 02:56:43'),
(29, 1, 'Edited Livestock', 'Livestock ID: 11 updated', '2026-09-11 03:47:12'),
(30, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 04:35:24'),
(31, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 04:35:24'),
(32, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-11 06:50:54'),
(33, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-11 06:50:54'),
(34, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-11 06:52:46'),
(35, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-11 06:52:46'),
(36, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 07:05:09'),
(37, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-11 07:05:09'),
(38, 1, 'Edited Crop', 'Crop ID: 22 (UNASSIGNED CROP) updated.', '2026-09-11 07:05:39'),
(39, 1, 'Edited Crop', 'Crop ID: 22 (UNASSIGNED CROP) updated.', '2026-09-11 07:05:39'),
(40, 1, 'Edited Crop', 'Crop ID: 22 updated', '2026-09-14 05:41:52'),
(41, 1, 'Edited Crop', 'Crop ID: 21 updated', '2026-09-14 05:42:02'),
(42, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-14 05:42:30'),
(43, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-14 05:42:30'),
(44, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-16 21:10:01'),
(45, 1, 'Added Crop', 'Crop: LETTUCE added to plot_a.', '2026-09-16 21:10:01'),
(46, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-16 21:10:31'),
(47, 1, 'Added Crop', 'Crop: Unassigned Crop added to plot_a.', '2026-09-16 21:10:31'),
(48, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-16 21:13:29'),
(49, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-16 21:13:29'),
(50, 1, 'Edited Crop', 'Crop ID: 30 updated', '2026-09-16 21:58:00'),
(51, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-16 21:58:34'),
(52, 1, 'Edited Crop', 'Crop ID: 1 (CARROT NI SUNGHOON) updated.', '2026-09-16 21:58:34'),
(53, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-16 22:52:59'),
(54, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-16 22:52:59'),
(55, 1, 'Edited Crop', 'Crop ID: 22 (OKRA) updated.', '2026-09-16 23:43:13'),
(56, 1, 'Edited Crop', 'Crop ID: 22 (OKRA) updated.', '2026-09-16 23:43:13'),
(57, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 00:29:31'),
(58, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 00:29:31'),
(59, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 00:30:47'),
(60, 1, 'Edited Crop', 'Crop ID: 15 (TOMATO) updated.', '2026-09-17 00:30:47'),
(61, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 00:54:22'),
(62, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 00:54:22'),
(63, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 02:03:55'),
(64, 1, 'Edited Crop', 'Crop ID: 21 (OKRA) updated.', '2026-09-17 02:03:55'),
(65, 1, 'Added Crop', 'Crop: BAWANG, Plot ID: 3', '2026-09-25 23:46:31'),
(66, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-28 06:07:18'),
(67, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-28 06:07:18'),
(68, 1, 'Edited Livestock', 'Livestock ID: 19 updated', '2026-09-28 17:18:12'),
(69, 1, 'Edited Livestock', 'Livestock ID: 18 updated', '2026-09-28 17:18:15'),
(70, 1, 'Edited Livestock', 'Livestock ID: 17 updated', '2026-09-28 17:18:20'),
(71, 1, 'Edited Livestock', 'Livestock ID: 16 updated', '2026-09-28 17:18:23'),
(72, 1, 'Edited Livestock', 'Livestock ID: 11 updated', '2026-09-28 17:18:38'),
(73, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-29 16:34:26'),
(74, 1, 'Edited Crop', 'Crop ID: 1 (EGGPLANT  NI SUNGHOON) updated.', '2026-09-29 16:34:27'),
(75, 1, 'Added Crop', 'Crop: eggplant added to plot_b.', '2026-10-05 18:31:45'),
(76, 1, 'Added Crop', 'Crop: eggplant added to plot_b.', '2026-10-05 18:31:45'),
(77, 1, 'Edited Crop', 'Crop ID: 21 (EGGPLANT) updated.', '2026-10-05 22:02:21'),
(78, 1, 'Edited Crop', 'Crop ID: 21 (EGGPLANT) updated.', '2026-10-05 22:02:21'),
(79, 1, 'Edited Crop', 'Crop ID: 26 updated', '2026-10-06 01:06:04'),
(80, 1, 'Edited Crop', 'Crop ID: 51 updated', '2026-10-06 09:57:49');

-- --------------------------------------------------------

--
-- Table structure for table `archived_chicken_drinking_water`
--

CREATE TABLE `archived_chicken_drinking_water` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `water_code` varchar(100) DEFAULT NULL,
  `number_of_waterers` int(11) DEFAULT NULL,
  `ml_per_16_liter` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_chicken_egg_records`
--

CREATE TABLE `archived_chicken_egg_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `days` varchar(50) DEFAULT NULL,
  `feed_formula` varchar(150) DEFAULT NULL,
  `egg_count` varchar(50) DEFAULT NULL,
  `lightest_weight` varchar(100) DEFAULT NULL,
  `heaviest_weight` varchar(100) DEFAULT NULL,
  `hatching` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_chicken_egg_records`
--

INSERT INTO `archived_chicken_egg_records` (`id`, `livestock_id`, `record_date`, `days`, `feed_formula`, `egg_count`, `lightest_weight`, `heaviest_weight`, `hatching`, `created_at`, `archived`, `archived_at`, `archived_by`) VALUES
(35, 0, '2026-02-04', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0, '2026-09-30 10:04:21', 1),
(36, 0, '2026-02-05', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0, '2026-09-30 09:47:03', 1),
(37, 0, '2026-02-06', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0, '2026-09-30 09:46:56', 1),
(38, 0, '2026-02-07', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 1, '2026-09-30 10:04:00', NULL),
(39, 0, '2026-02-08', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 1, '2026-09-30 10:04:00', NULL),
(40, 0, '2026-02-09', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 1, '2026-09-30 10:04:00', NULL),
(41, 0, '2026-02-10', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 1, '2026-09-30 10:04:00', NULL),
(42, 0, '2026-02-11', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 1, '2026-09-30 10:04:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `archived_chicken_feeding_schedule`
--

CREATE TABLE `archived_chicken_feeding_schedule` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `month_year` varchar(30) NOT NULL,
  `feeding_code` varchar(100) DEFAULT NULL,
  `morning` varchar(100) DEFAULT NULL,
  `afternoon` varchar(150) DEFAULT NULL,
  `snacks` varchar(150) DEFAULT NULL,
  `evening` varchar(150) DEFAULT NULL,
  `night_visit` varchar(100) DEFAULT NULL,
  `eggs_collected` varchar(100) DEFAULT NULL,
  `average_egg_weight` varchar(100) DEFAULT NULL,
  `average_layer_weight` varchar(100) DEFAULT NULL,
  `average_rooster_weight` varchar(100) DEFAULT NULL,
  `housing_nest_disinfection` varchar(150) DEFAULT NULL,
  `general_cleaning` varchar(150) DEFAULT NULL,
  `chicken_bath` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_chicken_feed_ingredients`
--

CREATE TABLE `archived_chicken_feed_ingredients` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `formulation_code` varchar(100) DEFAULT NULL,
  `feed_ingredients` text DEFAULT NULL,
  `quantity` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_chicken_mortality_records`
--

CREATE TABLE `archived_chicken_mortality_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `quantity` int(11) DEFAULT NULL,
  `chicken_type` varchar(100) DEFAULT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `cause_of_death` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_chicken_weight_records`
--

CREATE TABLE `archived_chicken_weight_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `rooster_banaba` varchar(100) DEFAULT NULL,
  `rooster_paraokan` varchar(100) DEFAULT NULL,
  `hen_banaba` varchar(100) DEFAULT NULL,
  `hen_paraokan` varchar(100) DEFAULT NULL,
  `average_weight` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_crops`
--

CREATE TABLE `archived_crops` (
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
  `bed_id` int(11) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_crops`
--

INSERT INTO `archived_crops` (`crop_id`, `recipient`, `plot_id`, `plot_name`, `crop_name`, `variety`, `pcs`, `date_sowing`, `germination_rate`, `target_transplant_date`, `target_area`, `seedlings_received`, `date_transplanted`, `expected_harvest`, `status`, `image`, `transplanted_area`, `archived`, `bed_id`, `archived_at`, `archived_by`) VALUES
(32, 'miradora', 2, NULL, 'eggplant', 'asdas', 90, '2026-10-01', 76, '2026-10-22', '0', 9, '2026-10-06', '2026-11-20', 'planted', NULL, 'B1', 0, NULL, '2026-10-06 10:32:26', 1);

-- --------------------------------------------------------

--
-- Table structure for table `archived_goat_forage_records`
--

CREATE TABLE `archived_goat_forage_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `forage_name` varchar(150) DEFAULT NULL,
  `quantity` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_goat_monitoring_records`
--

CREATE TABLE `archived_goat_monitoring_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `status` varchar(100) DEFAULT NULL,
  `free_range_schedule` varchar(150) DEFAULT NULL,
  `drink_type` varchar(150) DEFAULT NULL,
  `disinfect_cleaning` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_inventory_requests`
--

CREATE TABLE `archived_inventory_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  `requested_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `request_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_livestocks`
--

CREATE TABLE `archived_livestocks` (
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
  `image` varchar(255) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_livestock_houses`
--

CREATE TABLE `archived_livestock_houses` (
  `house_id` int(11) NOT NULL,
  `house_name` varchar(100) NOT NULL,
  `house_type` varchar(50) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `map_x` decimal(6,2) DEFAULT NULL,
  `map_y` decimal(6,2) DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `house_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_pig_records`
--

CREATE TABLE `archived_pig_records` (
  `pig_id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED DEFAULT NULL,
  `tag_number` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `breed` varchar(150) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` varchar(20) DEFAULT 'kg',
  `purpose` varchar(100) DEFAULT NULL,
  `health_status` varchar(100) DEFAULT 'Healthy',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_plots`
--

CREATE TABLE `archived_plots` (
  `plot_id` int(11) NOT NULL,
  `plot_code` varchar(20) NOT NULL,
  `plot_name` varchar(100) DEFAULT NULL,
  `map_x` decimal(6,2) DEFAULT NULL,
  `map_y` decimal(6,2) DEFAULT NULL,
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_seeds`
--

CREATE TABLE `archived_seeds` (
  `archive_id` int(10) UNSIGNED NOT NULL,
  `original_inventory_id` int(10) UNSIGNED NOT NULL,
  `date_received` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `variety` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `pieces` varchar(255) DEFAULT NULL,
  `quantity` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'In Stock',
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `archived_seeds`
--

INSERT INTO `archived_seeds` (`archive_id`, `original_inventory_id`, `date_received`, `category`, `item_name`, `variety`, `brand`, `pieces`, `quantity`, `price`, `status`, `archived_at`) VALUES
(1, 238, '2025-12-15', 'Seeds', 'Lima Beans', 'N/A', 'Advance Seeds', '5 packs', '4 grams per pack', 250.00, 'In Stock', '2026-10-04 20:07:43'),
(2, 239, '2025-12-15', 'Seeds', 'Okra', 'Smooth Green', 'Advance Seeds', '1 can', '1, 388 seeds', 179.00, 'In Stock', '2026-10-04 20:08:01'),
(3, 240, '2025-12-15', 'Seeds', 'Cucumber', 'Abigail F1', 'Advance Seeds', '1 can', '140 seeds', 179.00, 'In Stock', '2026-10-04 20:08:08'),
(4, 236, '2025-11-22', 'Seeds', 'Rosemary', 'N/A', 'Ramgo Seeds', '1 pack', '12 seeds', 159.00, 'In Stock', '2026-10-04 20:08:59'),
(5, 237, '2025-11-22', 'Seeds', 'Ornamental Corn', 'N/A', 'Known-You', '1 pack', 'N/A', 50.00, 'In Stock', '2026-10-04 21:29:09');

-- --------------------------------------------------------

--
-- Table structure for table `archived_sowingmaterial`
--

CREATE TABLE `archived_sowingmaterial` (
  `archive_id` int(10) UNSIGNED NOT NULL,
  `original_inventory_id` int(10) UNSIGNED NOT NULL,
  `date_received` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `variety` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `pieces` varchar(255) DEFAULT NULL,
  `quantity` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'In Stock',
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `archived_sowingmaterial`
--

INSERT INTO `archived_sowingmaterial` (`archive_id`, `original_inventory_id`, `date_received`, `category`, `item_name`, `variety`, `brand`, `pieces`, `quantity`, `price`, `status`, `archived_at`) VALUES
(1, 247, '2025-06-06', 'Others', 'Peat Moss', NULL, 'Ramgo', '3 pieces', NULL, 0.00, 'In Stock', '2026-10-04 20:07:56'),
(2, 246, '2025-06-06', 'Others', 'Grow Pots (30.0 cm)', NULL, 'Ramgo', '12 pieces', NULL, 0.00, 'In Stock', '2026-10-04 20:09:34'),
(3, 2, '2026-09-14', 'Fertilizer', 'asdasdasd', 'asdaasdasd', 'asdassd', '1212', '12121', 1232.00, 'Low Stock', '2026-10-05 16:17:46');

-- --------------------------------------------------------

--
-- Table structure for table `chicken_drinking_water`
--

CREATE TABLE `chicken_drinking_water` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `water_code` varchar(100) DEFAULT NULL,
  `number_of_waterers` int(11) DEFAULT NULL,
  `ml_per_16_liter` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chicken_drinking_water`
--

INSERT INTO `chicken_drinking_water` (`id`, `livestock_id`, `record_date`, `water_code`, `number_of_waterers`, `ml_per_16_liter`, `created_at`, `archived`) VALUES
(1, 0, '2026-01-01', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(2, 0, '2026-01-02', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(3, 0, '2026-01-03', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(4, 0, '2026-01-04', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(5, 0, '2026-01-05', 'FW', 9, NULL, '2026-09-28 18:50:19', 0),
(6, 0, '2026-01-06', 'FW', 9, NULL, '2026-09-28 18:50:19', 0),
(7, 0, '2026-01-07', 'PW', NULL, NULL, '2026-09-28 18:50:19', 0),
(8, 0, '2026-01-14', 'FW', 9, NULL, '2026-09-28 18:50:19', 0),
(9, 0, '2026-01-15', 'FW', 9, NULL, '2026-09-28 18:50:19', 0),
(10, 0, '2026-01-16', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(11, 0, '2026-01-17', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(12, 0, '2026-01-18', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(13, 0, '2026-01-19', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(14, 0, '2026-01-20', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(15, 0, '2026-01-21', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(16, 0, '2026-01-22', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(17, 0, '2026-01-23', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(18, 0, '2026-01-24', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(19, 0, '2026-01-25', 'PW', 9, NULL, '2026-09-28 18:50:19', 0),
(20, 0, '2026-01-26', 'FW', 9, NULL, '2026-09-28 18:50:19', 0),
(21, 0, '2026-01-27', 'OW', 8, NULL, '2026-09-28 18:50:19', 0),
(22, 0, '2026-01-28', 'PW', 8, NULL, '2026-09-28 18:50:19', 0),
(23, 0, '2026-01-29', 'FW', 8, NULL, '2026-09-28 18:50:19', 0),
(24, 0, '2026-01-30', 'PW', 8, NULL, '2026-09-28 18:50:19', 0),
(25, 0, '2026-01-31', 'PW', 8, NULL, '2026-09-28 18:50:19', 0),
(26, 0, '2026-02-01', 'PW', 8, NULL, '2026-09-28 18:50:19', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chicken_egg_records`
--

CREATE TABLE `chicken_egg_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `days` varchar(50) DEFAULT NULL,
  `feed_formula` varchar(150) DEFAULT NULL,
  `egg_count` varchar(50) DEFAULT NULL,
  `lightest_weight` varchar(100) DEFAULT NULL,
  `heaviest_weight` varchar(100) DEFAULT NULL,
  `hatching` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chicken_egg_records`
--

INSERT INTO `chicken_egg_records` (`id`, `livestock_id`, `record_date`, `days`, `feed_formula`, `egg_count`, `lightest_weight`, `heaviest_weight`, `hatching`, `created_at`, `archived`) VALUES
(1, 0, '2026-01-01', '216', NULL, '1', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(2, 0, '2026-01-02', '217', NULL, '0', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(3, 0, '2026-01-03', '218', NULL, '1', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(4, 0, '2026-01-04', '219', NULL, '0', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(5, 0, '2026-01-05', '220', NULL, '0', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(6, 0, '2026-01-06', '221', NULL, '0', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(7, 0, '2026-01-07', '222', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(8, 0, '2026-01-08', '223', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(9, 0, '2026-01-09', '224', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(10, 0, '2026-01-10', '225', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(11, 0, '2026-01-11', '226', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(12, 0, '2026-01-12', '227', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(13, 0, '2026-01-13', '228', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(14, 0, '2026-01-14', '229', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(15, 0, '2026-01-15', '230', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(16, 0, '2026-01-16', '231', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(17, 0, '2026-01-17', '232', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(18, 0, '2026-01-18', '233', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(19, 0, '2026-01-19', '234', NULL, '7', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(20, 0, '2026-01-20', '235', NULL, '10', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(21, 0, '2026-01-21', '236', NULL, '6', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(22, 0, '2026-01-22', '237', NULL, '10', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(23, 0, '2026-01-23', '238', NULL, '12', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(24, 0, '2026-01-24', '239', NULL, '11', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(25, 0, '2026-01-25', '240', NULL, '9', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(26, 0, '2026-01-26', '241', NULL, '12', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(27, 0, '2026-01-27', '242', NULL, '10', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(28, 0, '2026-01-28', '243', NULL, '9', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(29, 0, '2026-01-29', '244', NULL, '12', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(30, 0, '2026-01-30', '245', NULL, '6', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(31, 0, '2026-01-31', '246', NULL, '13', NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(32, 0, '2026-02-01', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(33, 0, '2026-02-02', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0),
(34, 0, '2026-02-03', '246', NULL, NULL, NULL, NULL, NULL, '2026-09-28 18:50:18', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chicken_feeding_schedule`
--

CREATE TABLE `chicken_feeding_schedule` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `month_year` varchar(30) NOT NULL,
  `feeding_code` varchar(100) DEFAULT NULL,
  `morning` varchar(100) DEFAULT NULL,
  `afternoon` varchar(150) DEFAULT NULL,
  `snacks` varchar(150) DEFAULT NULL,
  `evening` varchar(150) DEFAULT NULL,
  `night_visit` varchar(100) DEFAULT NULL,
  `eggs_collected` varchar(100) DEFAULT NULL,
  `average_egg_weight` varchar(100) DEFAULT NULL,
  `average_layer_weight` varchar(100) DEFAULT NULL,
  `average_rooster_weight` varchar(100) DEFAULT NULL,
  `housing_nest_disinfection` varchar(150) DEFAULT NULL,
  `general_cleaning` varchar(150) DEFAULT NULL,
  `chicken_bath` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chicken_feeding_schedule`
--

INSERT INTO `chicken_feeding_schedule` (`id`, `livestock_id`, `month_year`, `feeding_code`, `morning`, `afternoon`, `snacks`, `evening`, `night_visit`, `eggs_collected`, `average_egg_weight`, `average_layer_weight`, `average_rooster_weight`, `housing_nest_disinfection`, `general_cleaning`, `chicken_bath`, `created_at`, `archived`) VALUES
(1, 0, 'January 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Madre de Agua ', '----', '1', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(2, 0, 'January 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Madre de Agua ', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(3, 0, 'January 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Madre de Agua ', '----', '1', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(4, 0, 'January 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Pechay', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(5, 0, 'January 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Sitao', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(6, 0, 'January 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, NULL, '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(7, 0, 'January 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Pechay', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(8, 0, 'January 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, '2', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(9, 0, 'January 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(10, 0, 'January 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(11, 0, 'January 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, '7', '40', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(12, 0, 'January 2026', NULL, '08:00:00', '🏚️🌞', NULL, NULL, NULL, '10', '45', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(13, 0, 'January 2026', NULL, '08:00:00', '🏚️🌞', NULL, NULL, NULL, '6', '45', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(14, 0, 'January 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '10', '50.56', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(15, 0, 'January 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '12', '46.67', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(16, 0, 'January 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(17, 0, 'January 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '9', '48.3', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(18, 0, 'January 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '12', '46.25', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(19, 0, 'February 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Madre de Agua ', '----', '1', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(20, 0, 'February 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Madre de Agua ', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(21, 0, 'February 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Madre de Agua ', '----', '1', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(22, 0, 'February 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Pechay', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(23, 0, 'February 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Sitao', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(24, 0, 'February 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, NULL, '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(25, 0, 'February 2026', 'CF3 ', '08:00:00', '🏚️🌞', NULL, 'Pechay', '----', '0', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(26, 0, 'February 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, '2', NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(27, 0, 'February 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(28, 0, 'February 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(29, 0, 'February 2026', 'F2', '08:00:00', '🏚️🌞', NULL, NULL, NULL, '7', '40', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(30, 0, 'February 2026', NULL, '08:00:00', '🏚️🌞', NULL, NULL, NULL, '10', '45', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(31, 0, 'February 2026', NULL, '08:00:00', '🏚️🌞', NULL, NULL, NULL, '6', '45', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(32, 0, 'February 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '10', '50.56', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(33, 0, 'February 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '12', '46.67', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(34, 0, 'February 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(35, 0, 'February 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '9', '48.3', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0),
(36, 0, 'February 2026', NULL, '06:00:00', '🏚️🌞', NULL, NULL, NULL, '12', '46.25', NULL, NULL, '✅', NULL, NULL, '2026-09-28 18:50:19', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chicken_feed_ingredients`
--

CREATE TABLE `chicken_feed_ingredients` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `formulation_code` varchar(100) DEFAULT NULL,
  `feed_ingredients` text DEFAULT NULL,
  `quantity` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chicken_feed_ingredients`
--

INSERT INTO `chicken_feed_ingredients` (`id`, `livestock_id`, `formulation_code`, `feed_ingredients`, `quantity`, `created_at`, `archived`) VALUES
(1, 0, NULL, 'Azolla', NULL, '2026-09-28 18:50:18', 0),
(2, 0, NULL, 'Wheat (Trigo)', NULL, '2026-09-28 18:50:18', 0),
(3, 0, NULL, 'Corn kernel', NULL, '2026-09-28 18:50:18', 0),
(4, 0, NULL, 'Old cooked rice', NULL, '2026-09-28 18:50:18', 0),
(5, 0, 'F-A0', 'LABS', 'NOT QUANTIFIED', '2026-09-28 18:50:18', 0),
(6, 0, 'F-A0', 'FFJ', NULL, '2026-09-28 18:50:18', 0),
(7, 0, 'F-A0', 'Salt', NULL, '2026-09-28 18:50:18', 0),
(8, 0, 'F-A0', 'BioChar', NULL, '2026-09-28 18:50:19', 0),
(9, 0, 'F-A0', 'Atovi', NULL, '2026-09-28 18:50:19', 0),
(10, 0, 'F-A0', 'RUSF', NULL, '2026-09-28 18:50:19', 0),
(11, 0, 'F-A0', 'Azolla', '500g', '2026-09-28 18:50:19', 0),
(12, 0, 'F-A0', 'Wheat (Trigo)', '400g', '2026-09-28 18:50:19', 0),
(13, 0, 'F-A0', 'Corn kernel', '700g', '2026-09-28 18:50:19', 0),
(14, 0, 'F - A1', 'Old cooked rice', '1 tbsp', '2026-09-28 18:50:19', 0),
(15, 0, 'F - A1', 'FFJ', '1 tbsp', '2026-09-28 18:50:19', 0),
(16, 0, 'F - A1', 'Salt', '½ tbsp', '2026-09-28 18:50:19', 0),
(17, 0, 'F - A1', 'BioChar', '1 ½ tbsp', '2026-09-28 18:50:19', 0),
(18, 0, 'F - A1', 'Atovi', '½ tbsp', '2026-09-28 18:50:19', 0),
(19, 0, 'F - A1', 'RUSF', '1 pack', '2026-09-28 18:50:19', 0),
(20, 0, 'F - A1', 'Azolla', '500g', '2026-09-28 18:50:19', 0),
(21, 0, 'F - A1', 'Wheat (Trigo)', '400g', '2026-09-28 18:50:19', 0),
(22, 0, 'F - A1', 'Corn kernel', '700g', '2026-09-28 18:50:19', 0),
(23, 0, 'F - A1', 'LABS', '1 tbsp', '2026-09-28 18:50:19', 0),
(24, 0, 'F - A1', 'FFJ', '1 tbsp', '2026-09-28 18:50:19', 0),
(25, 0, 'F - A1', 'Salt', '½ tbsp', '2026-09-28 18:50:19', 0),
(26, 0, 'F - A1', 'BioChar', '1 ½ tbsp', '2026-09-28 18:50:19', 0),
(27, 0, 'F - A1', 'Atovi', '½ tbsp', '2026-09-28 18:50:19', 0),
(28, 0, 'F - A1', 'RUSF', '1 pack', '2026-09-28 18:50:19', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chicken_mortality_records`
--

CREATE TABLE `chicken_mortality_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `quantity` int(11) DEFAULT NULL,
  `chicken_type` varchar(100) DEFAULT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `cause_of_death` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chicken_mortality_records`
--

INSERT INTO `chicken_mortality_records` (`id`, `livestock_id`, `record_date`, `quantity`, `chicken_type`, `breed`, `cause_of_death`, `created_at`, `archived`) VALUES
(1, 0, '2026-02-08', 1, 'HEN', 'PARAOKAN', NULL, '2026-09-28 18:50:18', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chicken_weight_records`
--

CREATE TABLE `chicken_weight_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `rooster_banaba` varchar(100) DEFAULT NULL,
  `rooster_paraokan` varchar(100) DEFAULT NULL,
  `hen_banaba` varchar(100) DEFAULT NULL,
  `hen_paraokan` varchar(100) DEFAULT NULL,
  `average_weight` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Mr. Villa', 5, NULL, 'EGGPLANT  NI SUNGHOON', 'leaf', 6, '2026-08-19', 79, '2026-09-02', '', 0, '2026-09-02', '2026-11-16', 'growing', 'crops/uploads/crop_6abc5912f3a05_1790728466.jpg', 'Nursery', 0, NULL),
(13, 'Mr. Villa', 5, NULL, 'CORN NI REI', 'majesta', 20, '2026-03-19', 10, '2026-04-02', NULL, NULL, '2026-04-02', '2026-06-11', 'planted', NULL, 'Nursery', 0, NULL),
(14, 'Mr. Villa', 7, NULL, 'Lettuce', 'majesta', 5, '2026-03-27', 89, '2026-04-10', NULL, NULL, '2026-04-10', '2026-05-25', 'planted', NULL, 'MNO', 0, NULL),
(15, 'Mr. Villa', 7, NULL, 'TOMATO', 'majesta', 5, '2026-03-21', 80, '2026-04-04', '', 0, '2026-04-04', '2026-06-18', 'planted', NULL, 'MNO', 0, NULL),
(16, 'Mr. Villa', 7, NULL, 'cabbage', 'leaf', 5, '2026-03-18', 79, '2026-04-01', NULL, NULL, '2026-04-01', '2026-05-31', 'planted', NULL, 'MNO', 0, NULL),
(17, 'Mr. Villa', 5, NULL, 'Carrot', 'majesta', 5, '2026-02-19', 80, '2026-03-05', NULL, NULL, '2026-03-05', '2026-04-19', 'planted', NULL, 'Nursery', 0, NULL),
(18, 'Mr. Villa', 5, NULL, 'Tomato', 'leaf', 5, '2026-01-30', 79, '2026-02-13', NULL, NULL, '2026-02-13', '2026-04-29', 'planted', NULL, 'Nursery', 0, NULL),
(20, 'Mr. Villa', 5, NULL, 'Bawang ni Chaeyoung', 'leaf', 5, '2026-08-20', 80, '2026-09-03', NULL, NULL, '2026-09-03', '2026-11-02', 'planted', NULL, 'Nursery', 0, NULL),
(21, 'Not assigned', 1, NULL, 'EGGPLANT', 'No variety assigned', 0, '2026-08-28', 0, '2026-09-11', '0', 0, '2026-09-11', '2026-11-25', 'planted', NULL, 'A4', 0, NULL),
(22, 'Not assigned', 1, NULL, 'OKRA', 'No variety assigned', 0, '2026-08-28', 0, '2026-09-11', '0', 0, '2026-09-11', '2026-10-31', 'planted', 'crops/uploads/crop_6aa418c3bd08f_1789139139.jpg', 'A4', 0, NULL),
(23, 'Not assigned', 1, NULL, 'LETTUCE', 'No variety assigned', 0, '2026-08-28', 0, '2026-09-11', '0', 0, '2026-09-11', '2026-10-26', 'planted', NULL, 'A7', 0, NULL),
(24, 'Not assigned', 1, NULL, 'LETTUCE', 'No variety assigned', 0, '2026-08-28', 0, '2026-09-11', '0', 0, '2026-09-11', '2026-10-26', 'planted', NULL, 'A7', 0, NULL),
(25, 'Not assigned', 1, NULL, 'Unassigned Crop', 'No variety assigned', 0, '2026-08-31', 0, '2026-09-14', '0', 0, '2026-09-14', '2026-10-29', 'planted', NULL, 'A16', 0, NULL),
(26, 'LIAGA', 3, NULL, 'EGGPLANT', 'LEAFY', 11, '2026-08-31', 98, '2026-09-14', '0', 0, '2026-09-14', '2026-11-28', 'planted', NULL, 'A16', 0, NULL),
(27, 'MR.  VILLA', 1, NULL, 'LETTUCE', 'LEAFY', 12, '2026-09-03', 71, '2026-09-17', '0', 12, '2026-09-17', '2026-11-01', 'planted', 'crops/uploads/crop_6aab7629880cb_1789621801.png', 'A17', 0, NULL),
(33, 'miradora', 2, NULL, 'eggplant', 'asdas', 90, '2026-09-22', 76, '2026-10-06', '0', 9, '2026-10-06', '2026-12-20', 'planted', NULL, 'B1', 0, NULL),
(34, 'Sanctuario Farm', NULL, NULL, 'Lettuce', 'Green Coral', 50, '2026-10-01', 92, '2026-10-15', 'Nursery Bed 1', 50, '2026-10-15', '2026-11-29', 'growing', NULL, 'NURSERY', 0, NULL),
(35, 'Sanctuario Farm', NULL, NULL, 'Tomato', 'Jewel', 35, '2026-10-02', 88, '2026-10-16', 'Nursery Bed 2', 35, '2026-10-16', '2026-12-30', 'growing', NULL, 'NURSERY', 0, NULL),
(36, 'Sanctuario Farm', NULL, NULL, 'Pepper', 'California Wonder', 30, '2026-10-03', 90, '2026-10-17', 'Nursery Bed 3', 30, '2026-10-17', '2026-12-31', 'growing', NULL, 'NURSERY', 0, NULL),
(37, 'Sanctuario Farm', NULL, NULL, 'Carrot', 'Bonfire', 45, '2026-10-04', 85, '2026-10-18', 'Nursery Bed 4', 45, '2026-10-18', '2026-12-02', 'planted', NULL, 'NURSERY', 0, NULL),
(38, 'Sanctuario Farm', NULL, NULL, 'Cabbage', 'Green Producer', 40, '2026-10-05', 87, '2026-10-19', 'Nursery Bed 5', 40, '2026-10-19', '2026-12-18', 'planted', NULL, 'NURSERY', 0, NULL),
(39, 'Sanctuario Farm', NULL, NULL, 'Eggplant', 'Fortuner F1', 25, '2026-10-06', 91, '2026-10-20', 'Nursery Bed 6', 25, '2026-10-20', '2027-01-03', 'growing', NULL, 'NURSERY', 0, NULL),
(49, 'Sanctuario Farm', 1, 'Plot A', 'Lettuce', 'Leafy', 30, '2026-09-01', 92, '2026-09-15', 'A1', 30, '2026-09-15', '2026-10-30', 'growing', NULL, 'PLOT A', 0, NULL),
(50, 'Sanctuario Farm', 1, 'Plot A', 'Tomato', 'Jewel', 20, '2026-09-02', 88, '2026-09-16', 'A2', 20, '2026-09-16', '2026-11-30', 'growing', NULL, 'PLOT A', 0, NULL),
(51, 'Sanctuario Farm', 1, 'Plot A', 'Carrot', 'Bonfire', 25, '2026-09-03', 86.96, '2026-09-17', 'A3', 25, '2026-09-17', '2026-11-01', 'ready', NULL, 'PLOT A', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `goat_forage_records`
--

CREATE TABLE `goat_forage_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `forage_name` varchar(150) DEFAULT NULL,
  `quantity` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `goat_forage_records`
--

INSERT INTO `goat_forage_records` (`id`, `livestock_id`, `record_date`, `forage_name`, `quantity`, `notes`, `created_at`, `archived`) VALUES
(1, 0, '2026-01-01', 'Rensonii', NULL, 'Rensonii     ', '2026-09-28 18:50:19', 0),
(2, 0, '2026-01-01', 'Kakawate / Madre de cacao', NULL, 'Mother of Cacao / Gliricidia', '2026-09-28 18:50:19', 0),
(3, 0, '2026-01-01', 'Malunggay ', NULL, 'Moringa', '2026-09-28 18:50:19', 0),
(4, 0, '2026-01-01', 'Madre De Agua ', NULL, 'Mother of water / Trichanthera gigantea', '2026-09-28 18:50:19', 0),
(5, 0, '2026-01-01', 'Mani Manian ', NULL, 'Pinto Peanut / Peanut Grass ', '2026-09-28 18:50:19', 0),
(6, 0, '2026-01-01', 'Alibangbang', NULL, 'Orchid Tree', '2026-09-28 18:50:19', 0),
(7, 0, '2026-01-01', 'Kangkong ', NULL, 'Water Spinanch ', '2026-09-28 18:50:19', 0),
(8, 0, '2026-01-01', 'Talbos ng kamote ', NULL, 'Camote TopsNapier Grass', '2026-09-28 18:50:19', 0),
(9, 0, '2026-01-01', 'Napier ', NULL, 'Napier', '2026-09-28 18:50:19', 0),
(10, 0, '2026-01-01', 'Mombasa ', NULL, 'Mombasa Grass', '2026-09-28 18:50:19', 0),
(11, 0, '2026-01-01', 'Malabalatong', NULL, 'Flemingia', '2026-09-28 18:50:19', 0),
(12, 0, '2026-01-01', 'Ipil-Ipil', NULL, 'White Leadtree', '2026-09-28 18:50:19', 0),
(13, 0, '2026-01-01', 'Legume Crops', NULL, NULL, '2026-09-28 18:50:19', 0);

-- --------------------------------------------------------

--
-- Table structure for table `goat_monitoring_records`
--

CREATE TABLE `goat_monitoring_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `status` varchar(100) DEFAULT NULL,
  `free_range_schedule` varchar(150) DEFAULT NULL,
  `drink_type` varchar(150) DEFAULT NULL,
  `disinfect_cleaning` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `goat_monitoring_records`
--

INSERT INTO `goat_monitoring_records` (`id`, `livestock_id`, `record_date`, `status`, `free_range_schedule`, `drink_type`, `disinfect_cleaning`, `notes`, `created_at`, `archived`) VALUES
(1, 0, '2026-01-01', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(2, 0, '2026-01-02', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(3, 0, '2026-01-03', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(4, 0, '2026-01-04', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(5, 0, '2026-01-05', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(6, 0, '2026-01-06', 'H ', '🌧️🌧️ ', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(7, 0, '2026-01-07', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(8, 0, '2026-01-01', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(9, 0, '2026-01-02', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(10, 0, '2026-01-03', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(11, 0, '2026-01-04', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(12, 0, '2026-01-05', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(13, 0, '2026-01-06', 'H ', '🌧️🌧️ ', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(14, 0, '2026-01-07', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(15, 0, '2026-01-01', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(16, 0, '2026-01-02', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(17, 0, '2026-01-03', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(18, 0, '2026-01-04', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(19, 0, '2026-01-05', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(20, 0, '2026-01-06', 'H ', '🌧️🌧️ ', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(21, 0, '2026-01-07', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(22, 0, '2026-01-01', 'L', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(23, 0, '2026-01-02', 'L', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(24, 0, '2026-01-03', 'L', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(25, 0, '2026-01-04', 'L', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(26, 0, '2026-01-05', 'L', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(27, 0, '2026-01-06', 'L', '🌧️🌧️ ', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(28, 0, '2026-01-07', 'L', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(29, 0, '2026-01-01', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(30, 0, '2026-01-02', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(31, 0, '2026-01-03', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(32, 0, '2026-01-04', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(33, 0, '2026-01-05', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(34, 0, '2026-01-06', 'H ', '🌧️🌧️ ', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(35, 0, '2026-01-07', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(36, 0, '2026-01-01', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(37, 0, '2026-01-02', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(38, 0, '2026-01-03', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(39, 0, '2026-01-04', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(40, 0, '2026-01-05', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(41, 0, '2026-01-06', 'H ', '🌧️🌧️ ', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(42, 0, '2026-01-07', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(43, 0, '2026-01-01', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(44, 0, '2026-01-02', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(45, 0, '2026-01-03', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(46, 0, '2026-01-04', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(47, 0, '2026-01-05', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(48, 0, '2026-01-06', 'H ', '🌧️🌧️ ', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(49, 0, '2026-01-07', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0),
(50, 0, '2026-01-01', 'H ', '🌞🏡', 'PW', '/', 'w/ formulated milk ( morning and evening ) ', '2026-09-28 18:50:19', 0),
(51, 0, '2026-01-02', 'H ', '🌞🏡', 'PW', '/', 'w/ formulated milk ( morning and evening ) ', '2026-09-28 18:50:19', 0),
(52, 0, '2026-01-03', 'H ', '🌞🏡', 'PW', '/', 'w/ formulated milk ( morning and evening ) ', '2026-09-28 18:50:19', 0),
(53, 0, '2026-01-04', 'H ', '🌞🏡', 'PW', '/', 'w/ formulated milk ( morning and evening ) ', '2026-09-28 18:50:19', 0),
(54, 0, '2026-01-05', 'H ', '🌞🏡', 'PW', '/', 'w/ formulated milk ( morning and evening ) ', '2026-09-28 18:50:19', 0),
(55, 0, '2026-01-06', 'H ', '🌧️🌧️ ', 'PW', '/', 'w/ formulated milk ( morning and evening ) ', '2026-09-28 18:50:19', 0),
(56, 0, '2026-01-07', 'H ', '🌞🏡', 'PW', '/', NULL, '2026-09-28 18:50:19', 0);

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
(1, '2026-09-14', 'Seeds', 'asd', 'asda', 'asdas', '12', '12', 1232.00, 'In Stock', 1, 1, '2026-09-14 06:17:33', '2026-10-04 19:39:10'),
(4, '2023-09-16', 'Seeds', 'Seed', 'Diamante Max F1 Tomato', 'East-West Seed', '2 packs', '400 seeds', 150.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(5, '2023-09-16', 'Seeds', 'Seed', 'Mestisa F1 Ampalaya', 'East-West Seed', '2 packs', '200 seeds', 150.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(6, '2023-09-16', 'Seeds', 'Seed', 'Long Purple Eggplant', 'Condor', '3 packs', '3 grams', 260.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(7, '2023-09-16', 'Seeds', 'Seed', 'Super F1 Hybrid Cucumber', 'Condor', '3 packs', '1.5 gram', 210.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(8, '2023-09-16', 'Seeds', 'Seed', 'Magara Yard Long Bean', 'Condor', '3 packs', '12 grams', 195.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(9, '2023-09-16', 'Seeds', 'Seed', 'Smooth Green Okra', 'R.M Advance', '2 packs', '20 grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(10, '2023-09-16', 'Seeds', 'Seed', 'Kulantro Coriander', 'R.M Advance', '1 pack', '8 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(11, '2023-09-16', 'Seeds', 'Seed', 'Black Behi Petchay', 'R.M Advance', '1 pack', '10 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(12, '2023-09-16', 'Seeds', 'Seed', 'Grand Rapid Lettuce', 'Known You', '1 pack', '10 grams', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(13, '2023-10-17', 'Seeds', 'Seed', 'Hybrid Cucumber', 'Kaneko Seed', '1 pack', '1 gram', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(14, '2023-10-17', 'Seeds', 'Seed', 'Hybrid Hot Pepper', 'Kaneko Seed', '1 pack', '100 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(15, '2023-10-17', 'Seeds', 'Seed', 'Grand Romaine Lettuce', 'Known You', '1 pack', '11 050 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(16, '2023-10-17', 'Seeds', 'Seed', 'Arugulla Rocquette', 'Ramgo Seeds', '1 pack', '200 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(17, '2023-10-17', 'Seeds', 'Seed', 'Kulantro Coriander', 'Ramgo Seeds', '1 pack', '4 900 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(18, '2023-10-17', 'Seeds', 'Seed', 'Petchay', 'Ramgo Seeds', '2 packs', '800 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(19, '2023-10-17', 'Seeds', 'Seed', 'Hari Digma', 'Ramgo Seeds', '2 packs', '4 900 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(20, '2023-11-13', 'Seeds', 'Seed', 'Kuroda Carrots', 'R.M Advance', '2 packs', '3 grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(21, '2023-11-13', 'Seeds', 'Seed', 'Cloudine French Beans', 'Condor', '3 packs', '5 grams', 210.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(22, '2023-11-13', 'Seeds', 'Seed', 'Nagcarlan Radish', 'Condor', '3 packs', '20 grams', 150.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(23, '2023-11-13', 'Seeds', 'Seed', 'Cloudine French Beans', 'Condor', '2 packs', 'NA', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(24, '2023-12-22', 'Seeds', 'Seed', 'Christine Pole Beans', 'Condor', '1 pack', '10 grams', 65.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(25, '2023-12-22', 'Seeds', 'Seed', 'Trident F1 Ampalaya', 'Condor', '1 pack', '3 grams', 75.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(26, '2023-12-22', 'Seeds', 'Seed', 'Gracia F1', 'East-West Seed', '1 pack', '30 seeds', 75.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(27, '2023-12-22', 'Seeds', 'Seed', 'Tambuli Upo', 'Condor', '1 pack', '4 grams', 65.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(28, '2024-01-17', 'Seeds', 'Seed', 'Diamante Max F1', 'East-West Seed', '1 pc', '200 seeds', 75.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(29, '2024-01-17', 'Seeds', 'Seed', 'Detroit Dark Red', 'Condor', '1 pack', '5 grams', 65.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(30, '2024-01-17', 'Seeds', 'Seed', 'Green Giant Spinach', 'Known You', '1 pack', '850 seeds', 129.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(31, '2024-01-17', 'Seeds', 'Seed', 'Poseidon F1', 'Condor', '1 pack', '3 grams', 70.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(32, '2024-01-17', 'Seeds', 'Seed', 'Atsalking Bellpepper', 'R.M Advance', '1 pack', '1.5 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(33, '2024-01-17', 'Seeds', 'Seed', 'Green Shinkang', 'Condor', '1 pack', '5 grams', 65.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(34, '2024-01-17', 'Seeds', 'Seed', 'Kuroda Carrots', 'R.M Advance', '1 pack', '5 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(35, '2024-01-17', 'Seeds', 'Seed', 'Emperor F1', 'East-West Seed', '1 pack', '100 seeds', 75.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(36, '2024-01-17', 'Seeds', 'Seed', 'Magara', 'Condor', '2 packs', '24 grams', 130.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(37, '2024-01-17', 'Seeds', 'Seed', 'Hybrid Cucumber F1', 'Condor', '1 can', '260 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(38, '2024-01-25', 'Seeds', 'Seed', 'Smooth Green Okra', 'Advance Seeds', '1 pack', '20 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(39, '2024-01-25', 'Seeds', 'Seed', 'White Light Green', 'R.M Advance', '1 pack', '20 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(40, '2024-01-25', 'Seeds', 'Seed', 'Majesta Lettuce', 'Condor', '1 pack', '10 grams', 299.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(41, '2024-01-25', 'Seeds', 'Seed', 'Diamond Mix F1 Dianthus', 'Condor', '1 pack', '20 seeds', 128.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(42, '2024-01-25', 'Seeds', 'Seed', 'Gomphrena Pingpong Mix', 'Condor', '1 pack', '30 seeds', 120.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(43, '2024-01-25', 'Seeds', 'Seed', 'Proposio Double Mix F1 Zinnia', 'Condor', '1 pack', '15 seeds', 120.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(44, '2024-01-25', 'Seeds', 'Seed', 'Grand Filora Mix F1 Petunia', 'Condor', '1 pack', '60 seeds', 120.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(45, '2024-02-22', 'Seeds', 'Seed', 'Cloudine French Beans', 'Condor', '¼', 'N/A', 560.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(46, '2024-02-22', 'Seeds', 'Seed', 'Korean Radish', 'Havason', '1 pack', '2 grams', 149.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(47, '2024-02-22', 'Seeds', 'Seed', 'Green Giant Spinach', 'Known You', '1 pack', '10 grams', 129.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(48, '2024-02-22', 'Seeds', 'Seed', 'Aura F1 Patola', 'Condor', '1 pack', '2 grams', 70.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(49, '2024-02-22', 'Seeds', 'Seed', 'Sitao', 'Organic Seed', '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(50, '2024-02-22', 'Seeds', 'Seed', 'Cowpea', 'Organic Seed', '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(51, '2024-02-22', 'Seeds', 'Seed', 'Corn', 'Organic Seed', '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(52, '2024-02-22', 'Seeds', 'Seed', 'Cucumber', 'Organic Seed', '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(53, '2024-03-14', 'Seeds', 'Seed', 'Petchay', 'N/A', '16 pc', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(54, '2024-03-14', 'Seeds', 'Seed', 'Mustard', 'N/A', '15 pc', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(55, '2024-05-21', 'Seeds', 'Seed', 'Aura F1 Hybrid RidgPatola', 'Condor', '1 pack', '57 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(56, '2024-05-21', 'Seeds', 'Seed', 'Green Alpha F1 Hybrid Ampalaya', 'Condor', '1 pack', '32 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(57, '2024-05-21', 'Seeds', 'Seed', 'Purple Tipstar Pole Sitao', 'R.M Advance', '2 packs', '12 grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(58, '2024-05-21', 'Seeds', 'Seed', 'Kuroda Carrots', 'Condor', '1 pack', '3 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(59, '2024-05-21', 'Seeds', 'Seed', 'Natsugozen Hybrid Petchay', 'Kaneko Seeds', '1 pack', '5 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(60, '2024-05-21', 'Seeds', 'Seed', 'Hot Pepper', 'R.M Advance', '1 pack', '1.5 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(61, '2024-05-31', 'Seeds', 'Seed', 'Hybrid Spinacch', 'M.C Enterprises', '1 pack', '10 grams', 49.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(62, '2025-05-31', 'Seeds', 'Seed', 'Long Purple Eggplant', 'Condor', '1 pack', '3 grams', 65.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(63, '2025-05-31', 'Seeds', 'Seed', 'Poseodon F1 Ampalaya', 'Condor', '2 packs', '3 grams', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(64, '2025-05-31', 'Seeds', 'Seed', 'Espertanza F1', 'Condor', '2 packs', '2 grams', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(65, '2025-05-31', 'Seeds', 'Seed', 'Camiling Smooth Okra', 'Condor', '1 pack', '750 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(66, '2025-05-31', 'Seeds', 'Seed', 'Yard Long Bean', 'Condor', '1 pack', '450 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(67, '2025-05-31', 'Seeds', 'Seed', 'Hybrid Cucumber', 'Condor', '1 can', '260 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(68, '2025-05-31', 'Seeds', 'Seed', 'Diamante Max F1', 'East-West Seed', '1 pack', '200 seeds', 78.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(69, '2025-05-31', 'Seeds', 'Seed', 'Majesta Lettuce', 'Condor', '1 pack', '10 grams', 299.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(70, '2025-05-31', 'Seeds', 'Seed', 'Balsa Lemon Balm', 'Known You', '1 pack', '22 seeds', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(71, '2025-05-31', 'Seeds', 'Seed', 'Kuroda Carrots', 'Condor', '1 pack', '3 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(72, '2025-05-31', 'Seeds', 'Seed', 'Squash F1', 'Green Seeds', '1 pack', '10 seeds', 65.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(73, '2025-05-31', 'Seeds', 'Seed', 'Radish', 'Condor', '1 pack', '10 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(74, '2025-05-31', 'Seeds', 'Seed', 'Spinach', 'Known You', '1 pack', '850', 129.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(75, '2024-07-30', 'Seeds', 'Seed', 'Red Warrior Onion', 'R.M Advance', '2 packs', '6  grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(76, '2024-07-30', 'Seeds', 'Seed', 'Bataw Black Seed', 'Kaneko Seeds', '2 packs', '78 seeds', 116.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(77, '2024-07-30', 'Seeds', 'Seed', 'Squash Cutie F1', 'R.M Advance', '2 packs', '8  grams', 120.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(78, '2024-07-30', 'Seeds', 'Seed', 'Dill', 'Known You', '2 packs', 'N/A', 150.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(79, '2024-07-30', 'Seeds', 'Seed', 'Green Belt F1 Chinese Cabbage', 'R.M Advance', '1 pack', '1 gram', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(80, '2024-07-30', 'Seeds', 'Seed', 'Majesta Lettuce', 'Condor', '1 pack', '10 grams', 299.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(81, '2024-07-30', 'Seeds', 'Seed', 'Kale Borezole', 'Rush More', '1 pack', '140  seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(82, '2024-07-30', 'Seeds', 'Seed', 'Lollo Rosa Lettuce NRBL - 2', 'Condor', '1 pack', '1 gram', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(83, '2024-07-30', 'Seeds', 'Seed', 'Green Wave Lettuce', 'Condor', '1 pack', '3 grams', 299.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(84, '2024-07-30', 'Seeds', 'Seed', 'Dwarf Sunbright Kids F1 Sunflower', 'Condor', '1 pack', '20 seeds', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(85, '2024-07-30', 'Seeds', 'Seed', 'Green Towers Romaine Lettuce', 'Condor', '1 pack', '11 grams', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(86, '2024-07-30', 'Seeds', 'Seed', 'Snap Beans', 'R.M Advance', '2 packs', '20 grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(87, '2024-07-30', 'Seeds', 'Seed', 'Sigarilyas', 'Kaneko Seeds', '2 packs', '2  grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(88, '2024-07-30', 'Seeds', 'Seed', 'Ampalaya', 'Condor', '1 pack', '40 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(89, '2024-09-26', 'Seeds', 'Seed', 'Sweet Corn Bright Jean', 'Known You', '2 packs', '120 seeds', 200.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(90, '2024-09-26', 'Seeds', 'Seed', 'Bonfire Carrots', 'Kaneko Seeds', '1 pack', '2 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(91, '2024-09-26', 'Seeds', 'Seed', 'Sweet Basil', 'Green Seed Corporation', '1 pack', '1 800 seeds', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(92, '2024-09-26', 'Seeds', 'Seed', 'Upland Kangkong', 'R.M Advance', '2 packs', '15 grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(93, '2024-09-26', 'Seeds', 'Seed', 'Cloudine French Beans', 'Condor', '¼', 'N/A', 560.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(94, '2024-09-26', 'Seeds', 'Seed', 'Radish', 'Kaneko Seeds', '1 pack', '20 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(95, '2024-09-26', 'Seeds', 'Seed', 'Super Heat F1', 'East-West Seed', '1 pack', '150 seeds', 78.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(96, '2024-09-26', 'Seeds', 'Seed', 'Petunia', 'Horti Tbs Est.', '1 pack', '2 grams', 120.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(97, '2024-09-26', 'Seeds', 'Seed', 'Cucumber', 'Condor', '1 can', '185 seeds', 185.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(98, '2024-09-26', 'Seeds', 'Seed', 'Parsley', 'Known You', '1 pack', 'N/A', 90.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(99, '2024-11-21', 'Seeds', 'Seed', 'Hybrid Petchay', 'Kaneko Seeds', '2 packs', '1 651 seeds', 136.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(100, '2024-11-21', 'Seeds', 'Seed', 'Arugulla Rocquette', 'Condor', '1 pack', '12 grams', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(101, '2024-11-21', 'Seeds', 'Seed', 'PHLOX DRUMMONDI', 'Yates', '1 pack', 'N/A', 90.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(102, '2024-11-21', 'Seeds', 'Seed', 'Kangkong Tsinal', 'East-West Seed', '1 pack', '200 seeds', 70.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(103, '2024-11-21', 'Seeds', 'Seed', 'Petchay', 'East-West Seed', '1 pack', '1 422 seeds', 58.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(104, '2025-05-02', 'Seeds', 'Corn', 'Purple Gem F1', 'Condor', '2 packs', '10 grams', 240.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(105, '2025-05-02', 'Seeds', 'Hybrid Broccoli', 'Tenjiku', 'Kaneko Seeds', '1 pack', '229 seeds', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(106, '2025-05-02', 'Seeds', 'Okra', 'Red Ruby', 'Kaneko Seeds', '1 pack', '136 seeds', 58.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(107, '2025-05-02', 'Seeds', 'Tomato', 'Jewel', 'East-West Seed', '1 pack', '200 seeds', 78.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(108, '2025-05-02', 'Seeds', 'Sunflower', 'Big Smile', 'Ramgo Seeds', '2 packs', '20 seeds', 280.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(109, '2025-05-02', 'Seeds', 'Parsley', 'Curled', 'Yates', '1 pack', 'N/A', 120.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(110, '2025-05-02', 'Seeds', 'Watermelon', 'King Zebra F1', 'R.M Advance', '1 pack', '2 grams', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(111, '2025-05-02', 'Seeds', 'Nagcarlan Radish', 'N/A', 'Condor', '1 pack', '10 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(112, '2025-05-02', 'Seeds', 'Water Spinach', 'All Terrain Upland', 'R.M Advance', '1 pack', '15 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(113, '2025-05-02', 'Seeds', 'Snap Beans', 'Banahaw Pride', 'R.M Advance', '1 pack', '15 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(114, '2025-05-02', 'Seeds', 'Coriander', 'Kulantro', 'R.M Advance', '1 pack', '10 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(115, '2025-05-02', 'Seeds', 'Lady\'s Finger', 'Smooth Green', 'R.M Advance', '2 packs', '20  grams', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(116, '2025-05-02', 'Seeds', 'Aster', 'N/A', 'Yates', '2 packs', 'N/A', 180.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(117, '2025-02-15', 'Seeds', 'Bottle Gourd', 'Micah F1', 'Greenseeds', '2  packs', '3 grams', 130.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(118, '2025-02-15', 'Seeds', 'Hybrid Carrot', 'Bonfire', 'Kaneko Seeds', '1 pack', '2 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(119, '2025-02-15', 'Seeds', 'Cabbage', 'Green Producer F1', 'R.M Advance', '1 pack', '1 gram', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:23', '2026-09-28 05:46:23'),
(120, '2025-02-15', 'Seeds', 'Yard Long Bean', 'Green Mamba', 'Chia Tai', '1 pack', '20 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(121, '2025-03-19', 'Seeds', 'Sitao', 'Bush/Paayap', 'Kaneko Seeds', '1 pc', '20 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(122, '2025-03-19', 'Seeds', 'Snap Beans', 'Mayabong', 'Kaneko Seeds', '1 pc', '20 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(123, '2025-03-19', 'Seeds', 'Hybrid Spinach', 'Smooth', 'RIJK ZWAAN', '1 pc', '10 grams', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(124, '2025-03-19', 'Seeds', 'Eggplant', 'Fortuner F1', 'East-West Seed', '1 pc', '300 seeds', 78.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(125, '2025-03-19', 'Seeds', 'Eggplant', 'Calixto F1', 'East-West Seed', '1 pc', '300 seeds', 78.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(126, '2025-03-19', 'Seeds', 'Sweet Pepper', 'California Wonder', 'Kaneko Seeds', '2 pcs', '4 grams', 116.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(127, '2025-03-19', 'Seeds', 'Hybrid Squash', 'Victoria', 'Kaneko Seeds', '1 pc', '3 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(128, '2025-03-19', 'Seeds', 'Garden Pea', 'DK-30 White Flower', 'Kaneko Seeds', '1 pc', '12 grams', 58.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(129, '2025-03-19', 'Seeds', 'Radish', 'Snow White 45 Days', 'Kaneko Seeds', '1 pc', '10 grams', 58.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(130, '2025-03-19', 'Seeds', 'Turnip Bean/Yam Bean', 'N/A', 'Kaneko Seeds', '2 pcs', '10 grams', 116.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(131, '2025-03-19', 'Seeds', 'Sunflower', 'Giant Daylight', 'Ever Rich Seeds', '3 pcs', '0.84 grams', 270.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(132, '2025-03-19', 'Seeds', 'Pumpkin', 'Suprema Gold', 'East-West Seed', '1 pc', '30 seeds', 90.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(133, '2025-03-19', 'Seeds', 'Hybrid Carrot', 'Akari', 'Kaneko Seeds', '1 pc', '2 grams', 55.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(134, '2025-03-19', 'Seeds', 'Bush Sitao', 'Los Banos White', 'Kaneko Seeds', '1 pc', '15 grams', 45.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(135, '2025-03-19', 'Seeds', 'French Beans', 'Cloudine', 'Condor', NULL, '25 grams', 419.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(136, '2025-03-19', 'Seeds', 'Cucumber', 'Poinsett 76', 'Condor', '1 can', '100 grams', 600.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(137, '2025-04-28', 'Seeds', 'Corn', 'Bright Jean', 'Known You', '8 packs', '10g (60 seeds)', 792.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(138, '2025-04-28', 'Seeds', 'Corn', 'Purple (waxy) Sweet Candy', 'R.M Advance', '6 packs', '10 grams', 360.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(139, '2025-04-28', 'Seeds', 'Radish', 'Mt. Kinanglad', 'R.M Advance', '1 pack', '10 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(140, '2025-04-28', 'Seeds', 'Carrots', 'Alpha F1', 'Greenseeds Corporation', '1 pack', '1 000 seeds', 65.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(141, '2025-04-28', 'Seeds', 'Kale', 'Curly', 'RIJK ZWAAN', '1 pack', '5 grams', 179.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(142, '2025-04-28', 'Seeds', 'Hybrid Spinach', 'Smooth', 'RIJK ZWAAN', '1 pack', '10 grams', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(143, '2025-04-28', 'Seeds', 'Lettuce NRBL-2', 'Lollo Rosa Lettuce', 'Condor', '1 pack', '1 grams', 140.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(144, '2025-04-28', 'Seeds', 'Petchay', 'Black Behi', 'Kaneko Seeds', '1 pack', '20 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(145, '2025-04-28', 'Seeds', 'Onion', 'Red Beet', 'Ramgo Seeds', '2 packs', 'N/A', 160.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(146, '2025-04-28', 'Seeds', 'Onion', 'Super Diamond', 'Kaneko Seeds', '2 packs', '3 grams', 136.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(147, '2025-04-28', 'Seeds', 'Hybrid Melon', 'Sweety', 'Kaneko Seeds', '1 pack', '15 seeds', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(148, '2025-06-06', 'Seeds', 'Melampodium', 'Jackpot Gold', 'Ramgo Seeds', '5 packs', '20 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(149, '2025-06-06', 'Seeds', 'Celosia', 'Castle Pink', 'Ramgo Seeds', '5 packs', '70 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(150, '2025-06-06', 'Seeds', 'Celosia', 'Armor Orange', 'Ramgo Seeds', '4 packs', '70 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(151, '2025-06-06', 'Seeds', 'Sunflower', 'Big Smile', 'Ramgo Seeds', '5 packs', '20 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(152, '2025-06-06', 'Seeds', 'Hybrid Helianthus Sunflower', 'Sunrich Orange', 'Ramgo Seeds', '5 packs', '20 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(153, '2025-06-06', 'Seeds', 'Gomphrena', 'Audree White', 'Ramgo Seeds', '5 packs', '20 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(154, '2025-06-06', 'Seeds', 'Gomphrena', 'Audree Rose Pink', 'Ramgo Seeds', '5 packs', '20 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(155, '2025-06-06', 'Seeds', 'Vinca Carmina', 'Pink', 'Ramgo Seeds', '5 packs', '500 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(156, '2025-06-06', 'Seeds', 'Vinca Carmina', 'Purple', 'Ramgo Seeds', '5 packs', '500 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(157, '2025-06-06', 'Seeds', 'Vinca Carmina', 'Rose', 'Ramgo Seeds', '5 packs', '500 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(158, '2025-06-06', 'Seeds', 'Marigold', 'Harmony Yellow', 'Ramgo Seeds', '5 packs', '55 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(159, '2025-06-06', 'Seeds', 'Marigold', 'Harmony Gold', 'Ramgo Seeds', '5 packs', '55 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(160, '2025-06-06', 'Seeds', 'Marigold', 'Harmony Orange', 'Ramgo Seeds', '5 packs', '55 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(161, '2025-06-14', 'Seeds', 'Bitter Gourd', 'N/A', 'DA BPI', '2 packs', '27 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(162, '2025-06-14', 'Seeds', 'Bottle Gourd', 'N/A', 'DA BPI', '2 packs', '35 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(163, '2025-06-14', 'Seeds', 'Long Pepper', 'N/A', 'DA BPI', '2 packs', '2 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(164, '2025-06-14', 'Seeds', 'Cucumber', 'N/A', 'DA BPI', '2 packs', '2 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(165, '2025-06-14', 'Seeds', 'Parsley', 'N/A', 'DA BPI', '2 packs', '1 gram per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(166, '2025-06-14', 'Seeds', 'Tomato', 'N/A', 'DA BPI', '2 packs', '1 gram per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(167, '2025-06-14', 'Seeds', 'Mustard', 'N/A', 'DA BPI', '2 packs', '6 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(168, '2025-06-14', 'Seeds', 'Sponge Gourd', 'N/A', 'DA BPI', '2 packs', '4 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(169, '2025-06-14', 'Seeds', 'Eggplant', 'Calixto F1', 'DA BPI', '2 packs', '1 gram per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(170, '2025-06-14', 'Seeds', 'String Beans', 'N/A', 'DA BPI', '2 packs', '9 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(171, '2025-06-14', 'Seeds', 'Radish', 'N/A', 'DA BPI', '2 packs', '9 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(172, '2025-06-14', 'Seeds', 'Onion', 'Red Dragon', 'DA BPI (Ramgo Seeds)', '2 packs', '6 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(173, '2025-06-14', 'Seeds', 'Pechay', 'Black Behi', 'DA BPI (Ramgo Seeds)', '2 packs', '6 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(174, '2025-06-14', 'Seeds', 'Bitter Gourd', 'Sta. Isabelle', 'DA BPI (Ramgo Seeds)', '2 packs', '6 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(175, '2025-06-14', 'Seeds', 'Tomato', 'Discovery', 'DA BPI (Ramgo Seeds)', '2 packs', '6 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(176, '2025-06-14', 'Seeds', 'Okra', 'Smooth Green', 'DA BPI (Ramgo Seeds)', '2 packs', '6 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(177, '2025-06-14', 'Seeds', 'Lettuce', 'Green Towers', 'DA BPI', '5 packs', '2 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(178, '2025-06-14', 'Seeds', 'Pepper', 'Bicol Express', 'DA BPI', '5 packs', '2 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(179, '2025-06-14', 'Seeds', 'Squash', 'Rosalinda', 'DA BPI', '5 packs', '3 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(180, '2025-06-14', 'Seeds', 'Sponge Gourd', 'Tagalog', 'DA BPI', '5 packs', '3 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(181, '2025-06-14', 'Seeds', 'Eggplant', 'Long Purple', 'DA BPI', '5 packs', '2 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(182, '2025-06-14', 'Seeds', 'Bitter Gourd', 'Sta. Lucia', 'DA BPI', '5 packs', '3 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(183, '2025-06-14', 'Seeds', 'Celery', 'Ventura', 'DA BPI', '5 packs', '3 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(184, '2025-06-14', 'Seeds', 'Bottle Gourd', 'Tambuli', 'DA BPI', '5 packs', '5 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(185, '2025-06-14', 'Seeds', 'Pepper', 'California Wonder', 'DA BPI', '5 packs', '3 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(186, '2025-06-14', 'Seeds', 'Tomato', 'Athena', 'DA BPI', '5 packs', '3 grams per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(187, '2025-06-25', 'Seeds', 'Lettuce', 'Green Wave', NULL, '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(188, '2025-06-25', 'Seeds', 'Pechay', 'Pak choy', NULL, '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(189, '2025-07-10', 'Seeds', 'Hybrid Sugar Beet', 'Red Magic', 'Kaneko Seeds', '7 packs', '348 seeds per pack', 476.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(190, '2025-07-10', 'Seeds', 'Hybrid Cauliflower', 'Sunshine F1', 'Condor', '2 packs', '1 gram per pack', 130.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(191, '2025-07-10', 'Seeds', 'Hybrid Broccoli', 'Green Top F1', 'Condor', '2  packs', '.5 gram per pack', 130.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(192, '2025-07-10', 'Seeds', 'Lettuce', 'Grand Rapid LG', 'Kaneko Seeds', '1 pack', '2 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(193, '2025-07-10', 'Seeds', 'Coriander', 'N/A', 'Kaneko Seeds', '1 pack', '10 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(194, '2025-07-10', 'Seeds', 'Hybrid Spinach', 'Smooth', 'RIJK ZWAAN', '1 pack', '10 grams', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(195, '2025-07-10', 'Seeds', 'Coriander', 'N/A', 'Chia Tai', '1 pack', '20  grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(196, '2025-07-10', 'Seeds', 'Snap Beans', 'Barak', 'N/A', '3 packs', 'N/A', 150.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(197, '2025-07-10', 'Seeds', 'Papaya', 'Red Lady', 'Seeds of Hope', '1 pack', '20 seeds', 200.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(198, '2025-07-11', 'Seeds', 'Squash', 'N/A', 'N/A', '5 packs', '12 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(199, '2025-07-11', 'Seeds', 'Sweet Pepper', 'N/A', 'N/A', '5 packs', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(200, '2025-07-11', 'Seeds', 'Mustard', 'N/A', 'N/A', '5 packs', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(201, '2025-07-28', 'Seeds', 'Lettuce', 'Green Garden', 'Donation', '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(202, '2025-07-28', 'Seeds', 'Spinach', 'Spinacia Oleracea', 'Donation', '1 pack', 'N/A', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(203, '2025-09-25', 'Seeds', 'Tomato', 'Blue Zebra', 'Ceen\'s Haven & Gardens', '2  packs', '10 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(204, '2025-09-25', 'Seeds', 'Tomato', 'Rev Michael Keyes', 'Ceen\'s Haven & Gardens', '3 packs', '10 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(205, '2025-09-25', 'Seeds', 'Tomato', 'Woolly Kate', 'Ceen\'s Haven & Gardens', '2  packs', '10 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(206, '2025-09-25', 'Seeds', 'Tomato', 'Russian Big Yellow', 'Ceen\'s Haven & Gardens', '1 pack', '10 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(207, '2025-09-25', 'Seeds', 'Tomato', 'Golden Hour Tomato', 'Ceen\'s Haven & Gardens', '1 pack', '10 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(208, '2025-09-25', 'Seeds', 'Tomato', 'Abraham Brown Tomato', 'Ceen\'s Haven & Gardens', '3 packs', '10 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(209, '2025-09-25', 'Seeds', 'Tomato', 'Zluta Kytice', 'Ceen\'s Haven & Gardens', '3 packs', '12 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(210, '2025-09-25', 'Seeds', 'Tomato', 'Berner Rose Tomato', 'Ceen\'s Haven & Gardens', '3 packs', '10 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(211, '2025-09-25', 'Seeds', 'Tomato', 'Candy', 'Ceen\'s Haven & Gardens', '2  packs', '10 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(212, '2025-09-25', 'Seeds', 'Tomato', 'Finocchiaro Ribbed Red', 'Ceen\'s Haven & Gardens', '3 packs', '12 seeds per pack', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(213, '2025-09-25', 'Seeds', 'Cucumber', 'Long Heirloom', 'Ceen\'s Haven & Gardens', '1 pack', '10 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(214, '2025-09-25', 'Seeds', 'Melon', 'Sweet Heirloom', 'Ceen\'s Haven & Gardens', '1 pack', '10 seeds', 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(215, '2025-10-08', 'Seeds', 'Bitter Gourd', 'Green Diamond F1', 'R.M Advance Seeds', '1 can', '40 seeds', 179.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(216, '2025-10-08', 'Seeds', 'Carrot', 'Kuroda GT', 'Condor', '2 packs', '3 grams per pack', 136.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(217, '2025-10-08', 'Seeds', 'Onion', 'Onion 88', 'Condor', '1 pack', '5 grams', 75.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(218, '2025-10-08', 'Seeds', 'Eggplant', 'Spitfire F1', 'Condor', '2 packs', '1 gram per pack', 156.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(219, '2025-10-08', 'Seeds', 'Cabbage', 'Gladiator F1', 'Condor', '1 pack', '1 gram', 75.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(220, '2025-10-08', 'Seeds', 'Lettuce', 'Green Wave', 'Condor', '1 pack', '3 grams', 78.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(221, '2025-10-08', 'Seeds', 'Okra', 'Smooth Green', 'R.M Advance Seeds', '1 pack', '10 grams', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(222, '2025-10-08', 'Seeds', 'Pole Sitao', 'Super Green XL', 'R.M Advance Seeds', '2  packs', '12 grams per pack', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(223, '2025-10-08', 'Seeds', 'Pechay Green Pakchoy', 'Green Rainbow F1', 'R.M Advance Seeds', '1 pack', '5 grams', 60.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(224, '2025-10-08', 'Seeds', 'Radish', 'Mt. Kinanglad', 'R.M Advance Seeds', '2  packs', '10 grams per pack', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(225, '2025-10-08', 'Seeds', 'Asparagus Bean', 'Ky Bush', 'Known You', '1 pack', '10g (60 seeds)', 89.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(226, '2025-10-08', 'Seeds', 'Hybrid Kale', 'Borecole Reflex', 'Rushmore', '1 pack', '140 seeds', 199.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(227, '2025-10-08', 'Seeds', 'Pepper', 'N/A', 'N/A', '1 pot', 'N/A', 100.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(228, '2025-11-07', 'Seeds', 'Hot Pepper', 'Chili Labuyo', 'Green Garden', '1 pack', '30 seeds', 68.00, 'Low Stock', 0, NULL, '2026-09-28 05:46:24', '2026-10-05 17:16:42'),
(229, '2025-11-07', 'Seeds', 'Parsley', 'Italian', 'Ramgo Seeds', '2 packs', '400 seeds', 178.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(230, '2025-11-07', 'Seeds', 'Celery', 'Chinese', 'Known-You', '1 pack', '500 seeds', 50.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(231, '2025-11-07', 'Seeds', 'Okra', 'N/A', 'Green Garden', '2 packs', '50 seeds', 136.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(232, '2025-11-07', 'Seeds', 'Spinach', 'N/A', 'Green Garden', '1 pack', '5 grams', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(233, '2025-11-07', 'Seeds', 'Sweet Basil', 'N/A', 'Green Garden', '1 pack', '100 seeds', 68.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(234, '2025-11-15', 'Seeds', 'Kale', 'Kale Red', 'Ramgo Seeds', '1 pack', '75 seeds', 69.00, 'No Stock', 0, NULL, '2026-09-28 05:46:24', '2026-10-05 17:03:13'),
(235, '2025-11-15', 'Seeds', 'Sprout Seeds', 'Alfalfa', 'Ramgo Seeds', '1 pack', '50 grams', 169.00, 'Low Stock', 0, NULL, '2026-09-28 05:46:24', '2026-10-05 17:16:30'),
(241, '2025-12-17', 'Seeds', 'Lima Beans', 'N/A', 'Advance Seeds', '5 packs', '4 grams per pack', 250.00, 'No Stock', 1, NULL, '2026-09-28 05:46:24', '2026-10-04 19:39:37'),
(242, '2025-12-17', 'Seeds', 'Bitter Gourd', 'Green Diamond F1', 'Advance Seeds', '4 packs', '1.5 gram per pack', 240.00, 'Low Stock', 1, NULL, '2026-09-28 05:46:24', '2026-10-04 19:44:55'),
(243, '2025-06-06', 'Others', 'Plastic Pots', NULL, 'Ramgo', '50 pieces', NULL, 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(244, '2025-06-06', 'Others', 'Grow Pots (15.0 cm)', NULL, 'Ramgo', '50 pieces', NULL, 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(245, '2025-06-06', 'Others', 'Grow Pots (21.0 cm)', NULL, 'Ramgo', '15 pieces', NULL, 0.00, 'In Stock', 0, NULL, '2026-09-28 05:46:24', '2026-09-28 05:46:24'),
(248, '2025-06-06', 'Others', 'Trays (50 holes)', NULL, 'Ramgo', '80 pieces', NULL, 0.00, 'In Stock', 1, NULL, '2026-09-28 05:46:24', '2026-10-04 19:39:46'),
(249, '2025-06-06', 'Others', 'Trays (115 holes)', NULL, 'Ramgo', '80 pieces', NULL, 0.00, 'In Stock', 1, NULL, '2026-09-28 05:46:24', '2026-10-04 19:39:21');

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
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `inventory_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('IN','OUT') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
(11, 'Goat', 'Gino', 'WOWOWOW', '2025-03-01', '10 months', 25.00, 0, 2, 'male', 'AFTERNOON', 0, 1, 'Happy goatsdf', 'livestock/uploads/livestock_11_1789628608.jfif'),
(12, 'Goat', 'MEH', 'Native', '2026-09-17', '', NULL, 0, 1, 'female', 'MORNING', 0, 1, 'Manganak na this week', 'livestock/uploads/livestock_12_1789636523.jpg'),
(13, 'Rabbit', 'WONYOUNG', 'FURRY', '2026-09-26', '', 25.00, 0, 1, 'female', 'MORNING', 0, 3, 'CUTIE BUNNY', ''),
(14, 'Chicken', 'CHICKEN JOCKEY', 'Native', '2026-09-26', '', 20.00, 1, 1, 'female', 'AFTERNOON', 0, 4, 'LAVA', ''),
(15, 'Pig', 'YOBAB', 'UHM', '2026-09-26', '', 50.00, 1, 1, 'male', 'MORNING', 0, 5, 'CUTIE', ''),
(16, 'Chicken', 'Paraokan Rooster', 'Paraokan', '2025-05-31', '9 months', NULL, 0, 3, 'male', 'Morning and evening', 0, 4, '', NULL),
(17, 'Chicken', 'Paraokan Hen', 'Paraokan', '2025-05-31', '9 months', NULL, 0, 17, 'female', 'Morning and evening', 0, 4, '', NULL),
(18, 'Chicken', 'Banaba Rooster', 'Banaba', '2025-05-31', '9 months', NULL, 0, 2, 'male', 'Morning and evening', 0, 4, '', NULL),
(19, 'Chicken', 'Banaba Hen', 'Banaba', '2025-05-31', '9 months', NULL, 0, 5, 'female', 'Morning and evening', 0, 4, '', NULL);

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
-- Table structure for table `pig_records`
--

CREATE TABLE `pig_records` (
  `pig_id` int(10) UNSIGNED NOT NULL,
  `livestock_id` int(10) UNSIGNED DEFAULT NULL,
  `tag_number` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `breed` varchar(150) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` varchar(20) DEFAULT 'kg',
  `purpose` varchar(100) DEFAULT NULL,
  `health_status` varchar(100) DEFAULT 'Healthy',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pig_records`
--

INSERT INTO `pig_records` (`pig_id`, `livestock_id`, `tag_number`, `name`, `sex`, `breed`, `date_of_birth`, `weight`, `weight_unit`, `purpose`, `health_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, NULL, 'PIG-001', 'Bacon', 'Male', 'Large White', '2025-02-15', 85.50, 'kg', 'Breeding', 'Healthy', 'Active and healthy. Good appetite.', '2026-09-29 17:53:07', '2026-09-29 17:53:07'),
(2, NULL, 'PIG-002', 'Rosie', 'Female', 'Landrace', '2025-03-08', 78.20, 'kg', 'Breeding', 'Healthy', 'Healthy female with good body condition.', '2026-09-29 17:53:07', '2026-09-29 17:53:07'),
(3, NULL, 'PIG-003', 'Bruno', 'Male', 'Duroc', '2025-01-22', 92.75, 'kg', 'Meat Production', 'Healthy', 'Fast-growing and regularly monitored.', '2026-09-29 17:53:07', '2026-09-29 17:53:07'),
(4, NULL, 'PIG-004', 'Bella', 'Female', 'Yorkshire', '2025-04-10', 64.80, 'kg', 'Breeding', 'Healthy', 'Good temperament and normal activity.', '2026-09-29 17:53:07', '2026-09-29 17:53:07'),
(5, NULL, 'PIG-005', 'Max', 'Male', 'Hampshire', '2025-05-03', 58.40, 'kg', 'Meat Production', 'Under Observation', 'Recently monitored due to slight decrease in appetite.', '2026-09-29 17:53:07', '2026-09-29 17:53:07'),
(6, NULL, 'PIG-006', 'Daisy', 'Female', 'Large White', '2025-02-28', 81.30, 'kg', 'Breeding', 'Healthy', 'Good appetite and active behavior.', '2026-09-29 17:53:07', '2026-09-29 17:53:07'),
(7, NULL, 'PIG-007', 'Rocky', 'Male', 'Duroc', '2025-06-17', 47.60, 'kg', 'Meat Production', 'Healthy', 'Young pig with normal growth and activity.', '2026-09-29 17:53:07', '2026-09-29 17:53:07');

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
(3, 'plot_c', 'Plot C', NULL, NULL, 0),
(5, 'nursery', 'NURSERY', NULL, NULL, 0),
(6, 'plot_9', 'Plot 9', NULL, NULL, 0),
(7, 'mno', 'MNO', NULL, NULL, 0);

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
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(50) NOT NULL,
  `role` enum('super admin','admin','staff','maintenance') NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `account_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `email`, `password`, `role`, `profile_picture`, `failed_login_attempts`, `account_locked`, `locked_at`) VALUES
(1, 'Manalo', 'adminManalo', NULL, 'admin1pass', 'admin', 'accountSettings/uploads/profile/profile_1_5d8810002870.jpg', 0, 0, '2026-10-05 09:03:56'),
(4, 'Liaga', 'staffLiaga', NULL, 'staff1pass', 'staff', NULL, 0, 0, NULL),
(7, 'Mr. Villa', 'adminVilla', NULL, 'adminVilla', 'admin', NULL, 0, 0, NULL),
(8, 'Manalo', 'staffManalo', NULL, 'staffManalo', 'staff', NULL, 0, 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `archived_chicken_drinking_water`
--
ALTER TABLE `archived_chicken_drinking_water`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `archived_chicken_egg_records`
--
ALTER TABLE `archived_chicken_egg_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `archived_chicken_feeding_schedule`
--
ALTER TABLE `archived_chicken_feeding_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `month_year` (`month_year`);

--
-- Indexes for table `archived_chicken_feed_ingredients`
--
ALTER TABLE `archived_chicken_feed_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`);

--
-- Indexes for table `archived_chicken_mortality_records`
--
ALTER TABLE `archived_chicken_mortality_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `archived_chicken_weight_records`
--
ALTER TABLE `archived_chicken_weight_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `archived_crops`
--
ALTER TABLE `archived_crops`
  ADD PRIMARY KEY (`crop_id`),
  ADD KEY `fk_crops_plot` (`plot_id`),
  ADD KEY `fk_crops_bed` (`bed_id`);

--
-- Indexes for table `archived_goat_forage_records`
--
ALTER TABLE `archived_goat_forage_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `archived_goat_monitoring_records`
--
ALTER TABLE `archived_goat_monitoring_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `archived_inventory_requests`
--
ALTER TABLE `archived_inventory_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_inventory_requests_status` (`status`),
  ADD KEY `idx_inventory_requests_requested_by` (`requested_by`),
  ADD KEY `idx_inventory_requests_created_at` (`created_at`);

--
-- Indexes for table `archived_livestocks`
--
ALTER TABLE `archived_livestocks`
  ADD PRIMARY KEY (`livestock_id`),
  ADD KEY `fk_livestock_house` (`house_id`);

--
-- Indexes for table `archived_livestock_houses`
--
ALTER TABLE `archived_livestock_houses`
  ADD PRIMARY KEY (`house_id`),
  ADD UNIQUE KEY `house_code` (`house_code`);

--
-- Indexes for table `archived_pig_records`
--
ALTER TABLE `archived_pig_records`
  ADD PRIMARY KEY (`pig_id`),
  ADD KEY `idx_tag_number` (`tag_number`),
  ADD KEY `idx_livestock_id` (`livestock_id`);

--
-- Indexes for table `archived_plots`
--
ALTER TABLE `archived_plots`
  ADD PRIMARY KEY (`plot_id`);

--
-- Indexes for table `archived_seeds`
--
ALTER TABLE `archived_seeds`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_archived_seeds_original_inventory_id` (`original_inventory_id`),
  ADD KEY `idx_archived_seeds_date_received` (`date_received`);

--
-- Indexes for table `archived_sowingmaterial`
--
ALTER TABLE `archived_sowingmaterial`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_archived_sowing_material_original_inventory_id` (`original_inventory_id`),
  ADD KEY `idx_archived_sowing_material_date_received` (`date_received`);

--
-- Indexes for table `chicken_drinking_water`
--
ALTER TABLE `chicken_drinking_water`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `chicken_egg_records`
--
ALTER TABLE `chicken_egg_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `chicken_feeding_schedule`
--
ALTER TABLE `chicken_feeding_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `month_year` (`month_year`);

--
-- Indexes for table `chicken_feed_ingredients`
--
ALTER TABLE `chicken_feed_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`);

--
-- Indexes for table `chicken_mortality_records`
--
ALTER TABLE `chicken_mortality_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `chicken_weight_records`
--
ALTER TABLE `chicken_weight_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `crops`
--
ALTER TABLE `crops`
  ADD PRIMARY KEY (`crop_id`),
  ADD KEY `fk_crops_plot` (`plot_id`),
  ADD KEY `fk_crops_bed` (`bed_id`);

--
-- Indexes for table `goat_forage_records`
--
ALTER TABLE `goat_forage_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

--
-- Indexes for table `goat_monitoring_records`
--
ALTER TABLE `goat_monitoring_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_id` (`livestock_id`),
  ADD KEY `record_date` (`record_date`);

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
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_inventory_transaction_item` (`inventory_id`),
  ADD KEY `idx_inventory_transaction_created` (`created_at`);

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
-- Indexes for table `pig_records`
--
ALTER TABLE `pig_records`
  ADD PRIMARY KEY (`pig_id`),
  ADD KEY `idx_tag_number` (`tag_number`),
  ADD KEY `idx_livestock_id` (`livestock_id`);

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
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `archived_chicken_drinking_water`
--
ALTER TABLE `archived_chicken_drinking_water`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_chicken_egg_records`
--
ALTER TABLE `archived_chicken_egg_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `archived_chicken_feeding_schedule`
--
ALTER TABLE `archived_chicken_feeding_schedule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_chicken_feed_ingredients`
--
ALTER TABLE `archived_chicken_feed_ingredients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_chicken_mortality_records`
--
ALTER TABLE `archived_chicken_mortality_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_chicken_weight_records`
--
ALTER TABLE `archived_chicken_weight_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_crops`
--
ALTER TABLE `archived_crops`
  MODIFY `crop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `archived_goat_forage_records`
--
ALTER TABLE `archived_goat_forage_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_goat_monitoring_records`
--
ALTER TABLE `archived_goat_monitoring_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_inventory_requests`
--
ALTER TABLE `archived_inventory_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_livestocks`
--
ALTER TABLE `archived_livestocks`
  MODIFY `livestock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `archived_livestock_houses`
--
ALTER TABLE `archived_livestock_houses`
  MODIFY `house_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_plots`
--
ALTER TABLE `archived_plots`
  MODIFY `plot_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_seeds`
--
ALTER TABLE `archived_seeds`
  MODIFY `archive_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `archived_sowingmaterial`
--
ALTER TABLE `archived_sowingmaterial`
  MODIFY `archive_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chicken_drinking_water`
--
ALTER TABLE `chicken_drinking_water`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `chicken_egg_records`
--
ALTER TABLE `chicken_egg_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `chicken_feeding_schedule`
--
ALTER TABLE `chicken_feeding_schedule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `chicken_feed_ingredients`
--
ALTER TABLE `chicken_feed_ingredients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `chicken_mortality_records`
--
ALTER TABLE `chicken_mortality_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chicken_weight_records`
--
ALTER TABLE `chicken_weight_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crops`
--
ALTER TABLE `crops`
  MODIFY `crop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `goat_forage_records`
--
ALTER TABLE `goat_forage_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `goat_monitoring_records`
--
ALTER TABLE `goat_monitoring_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `inventory_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=251;

--
-- AUTO_INCREMENT for table `inventory_requests`
--
ALTER TABLE `inventory_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `transaction_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `livestocks`
--
ALTER TABLE `livestocks`
  MODIFY `livestock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `livestock_houses`
--
ALTER TABLE `livestock_houses`
  MODIFY `house_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pig_records`
--
ALTER TABLE `pig_records`
  MODIFY `pig_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `plots`
--
ALTER TABLE `plots`
  MODIFY `plot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inventory_transaction_item` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`inventory_id`) ON DELETE CASCADE;

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
