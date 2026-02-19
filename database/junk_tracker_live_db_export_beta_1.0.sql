-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Feb 18, 2026 at 02:05 PM
-- Server version: 8.0.44
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `junk_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_text` tinyint(1) NOT NULL DEFAULT '0',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_type` enum('realtor','client','company','estate','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `first_name`, `last_name`, `business_name`, `phone`, `can_text`, `email`, `address_1`, `address_2`, `city`, `state`, `zip`, `client_type`, `note`, `active`, `deleted_at`, `deleted_by`, `created_at`, `created_by`, `updated_at`) VALUES
(1, 'Judy', 'Symington', '', '4012185791', 1, '', '5 Atlas St', '', 'Westerly', 'RI', '02891', 'client', '', 1, NULL, NULL, '2026-01-08 17:19:17', 1, '2026-02-06 19:56:07'),
(2, 'Manny', 'Theadore', 'MT Generators', '4013642954', 1, '', '22 Littlebrook Rd', '', 'Westerly', 'RI', '02891', 'client', '', 1, NULL, NULL, '2026-01-08 17:33:04', 1, '2026-02-06 19:56:07'),
(3, 'Joyce', 'Valentine', '', '7248330887', 1, '', '52 Rawlinson Dr', '', 'Coventry', 'RI', '02816', 'client', 'Lives in PA but her daughter lives in this house', 1, NULL, NULL, '2026-01-08 17:35:15', 1, '2026-02-06 19:56:07'),
(4, 'Warren', 'Davis', '', '4012062908', 0, '', '59 Resevoir Rd', '', 'Coventry', 'RI', '02816', 'client', 'Testing note for Warren', 1, NULL, NULL, '2026-01-09 13:52:46', 1, '2026-02-06 20:43:11'),
(5, 'Barbara', 'McGovern', 'McGovern FPS', '4012599265', 1, 'bam@mcgovernfps.com', '28 W Arch St', '', 'Pawcatuck', 'CT', '06379', 'client', 'Silly willy', 1, NULL, NULL, '2026-01-13 21:40:58', 1, '2026-02-07 17:05:02'),
(6, 'Kyle', 'Peiczarek', 'The Fixer', '751-555-1212', 1, 'kpeiczarek@gmail.com', '', '', '', '', '', 'company', '', 1, NULL, NULL, '2026-01-13 21:45:19', 1, '2026-02-06 21:13:50'),
(7, 'Tony', 'McGovern', 'Dad\'s Junk', '401-555-1221', 1, 'tony@baloney.com', '28 w arch st', '', 'Pawcatuck', 'ct', '06379', 'client', '', 1, NULL, NULL, '2026-02-06 19:41:54', 1, '2026-02-06 19:56:07'),
(8, 'Test', 'Client', '', '', 0, '', '', '', '', '', '', 'client', '', 0, '2026-02-08 12:39:02', 1, '2026-02-06 21:39:53', 1, '2026-02-08 12:39:02'),
(9, 'Test', 'Client', '', '', 0, '', '', '', '', '', '', 'client', '', 1, NULL, NULL, '2026-02-06 21:45:24', 1, '2026-02-06 21:45:24'),
(10, 'Barbara', 'McGovern', 'Fryer Estate', '4012699265', 0, 'bam@mcgovernfps.com', '', '', '', '', '', 'estate', '', 1, NULL, NULL, '2026-02-08 12:41:31', 1, '2026-02-08 12:41:31'),
(11, 'Jazzy', 'Fae', '', '4015559999', 0, '', '', '', '', '', '', 'client', '', 1, NULL, NULL, '2026-02-14 14:27:57', 1, '2026-02-14 14:27:57'),
(12, 'Sarah', 'Wheaton', '', '4015551212', 0, '', '', '', '', '', '', 'client', '', 1, NULL, NULL, '2026-02-16 15:58:59', 1, '2026-02-16 15:58:59'),
(17, 'Caleb', 'Donahue', NULL, '4015556001', 0, NULL, NULL, NULL, 'Providence', 'RI', '02908', 'client', NULL, 1, NULL, NULL, '2026-02-18 08:59:48', 1, '2026-02-18 08:59:48'),
(18, 'Maria', 'Ventura', NULL, '4015556002', 0, NULL, NULL, NULL, 'Warwick', 'RI', '02889', 'client', NULL, 1, NULL, NULL, '2026-02-18 08:59:48', 1, '2026-02-18 08:59:48'),
(19, 'Steven', 'Parker', NULL, '4015556003', 0, NULL, NULL, NULL, 'Cranston', 'RI', '02920', 'realtor', NULL, 1, NULL, NULL, '2026-02-18 08:59:48', 1, '2026-02-18 08:59:48'),
(20, 'Harper', 'Estate', NULL, '4015556004', 0, NULL, NULL, NULL, 'Barrington', 'RI', '02806', 'estate', NULL, 1, NULL, NULL, '2026-02-18 08:59:48', 1, '2026-02-18 08:59:48');

-- --------------------------------------------------------

--
-- Table structure for table `client_contacts`
--

CREATE TABLE `client_contacts` (
  `id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `link_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `link_id` bigint UNSIGNED DEFAULT NULL,
  `contact_method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'call',
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outbound',
  `subject` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `contacted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `follow_up_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_contacts`
--

INSERT INTO `client_contacts` (`id`, `client_id`, `link_type`, `link_id`, `contact_method`, `direction`, `subject`, `notes`, `contacted_at`, `follow_up_at`, `created_by`, `updated_by`, `deleted_by`, `active`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 5, 'prospect', 6, 'call', 'outbound', '', '', '2026-02-17 22:16:00', '2026-02-18 10:00:00', 1, 1, NULL, 1, NULL, '2026-02-17 22:16:27', '2026-02-17 22:16:27');

-- --------------------------------------------------------

--
-- Table structure for table `client_reminders`
--

CREATE TABLE `client_reminders` (
  `id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reminder_at` datetime NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('open','done','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_reminders`
--

INSERT INTO `client_reminders` (`id`, `client_id`, `title`, `reminder_at`, `note`, `status`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 4, 'Call warren about coins', '2026-02-09 10:00:00', NULL, 'done', 1, '2026-02-06 20:37:40', '2026-02-06 20:38:17', NULL),
(2, 4, 'Second reminder', '2026-02-10 20:37:00', NULL, 'done', 1, '2026-02-06 20:37:54', '2026-02-06 20:38:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `note` text,
  `phone` varchar(20) DEFAULT NULL,
  `web_address` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `linkedin` varchar(100) DEFAULT NULL,
  `address_1` varchar(50) DEFAULT NULL,
  `address_2` varchar(50) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(25) DEFAULT NULL,
  `zip` varchar(10) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `name`, `note`, `phone`, `web_address`, `facebook`, `instagram`, `linkedin`, `address_1`, `address_2`, `city`, `state`, `zip`, `created_at`, `updated_at`, `deleted_at`, `created_by`, `updated_by`, `deleted_by`, `active`) VALUES
(1, 'Jimmy\'s Junk, LLC', 'Test Company Add', '4015599834', 'jimmysjunk.com', 'facebook.com/jimmysjunk', 'ig.com/jimmysjunk', NULL, '113 Tiogue Ave', 'Unit 4', 'Coventry', 'RI', '02816', '2026-02-13 17:38:23', '2026-02-13 17:38:23', NULL, 1, 1, NULL, 1),
(2, 'Eddy\'s Junk', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-13 18:01:51', '2026-02-13 18:01:51', NULL, NULL, NULL, NULL, 1),
(3, 'Junk City', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-13 18:01:59', '2026-02-13 18:01:59', NULL, NULL, NULL, NULL, 1),
(4, 'Test Company Add', 'Test Note company add', '4015551212', '', 'facebook.com/jimmysjunk', '', '', '113 Tiogue Ave', 'Unit 8', 'Coventry', 'RI', '02816', '2026-02-13 18:28:01', '2026-02-13 18:28:01', NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `companies_x_clients`
--

CREATE TABLE `companies_x_clients` (
  `id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consignors`
--

CREATE TABLE `consignors` (
  `id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_1` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_2` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consignor_number` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consignment_start_date` date DEFAULT NULL,
  `consignment_end_date` date DEFAULT NULL,
  `payment_schedule` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_payment_due_date` date DEFAULT NULL,
  `inventory_estimate_amount` decimal(12,2) DEFAULT NULL,
  `inventory_description` text COLLATE utf8mb4_unicode_ci,
  `note` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consignor_contacts`
--

CREATE TABLE `consignor_contacts` (
  `id` bigint UNSIGNED NOT NULL,
  `consignor_id` bigint UNSIGNED NOT NULL,
  `link_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `link_id` bigint UNSIGNED DEFAULT NULL,
  `contact_method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'call',
  `direction` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outbound',
  `subject` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `contacted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `follow_up_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consignor_contracts`
--

CREATE TABLE `consignor_contracts` (
  `id` bigint UNSIGNED NOT NULL,
  `consignor_id` bigint UNSIGNED NOT NULL,
  `contract_title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint UNSIGNED DEFAULT NULL,
  `contract_signed_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consignor_payouts`
--

CREATE TABLE `consignor_payouts` (
  `id` bigint UNSIGNED NOT NULL,
  `consignor_id` bigint UNSIGNED NOT NULL,
  `payout_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `estimate_amount` decimal(12,2) DEFAULT NULL,
  `payout_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `reference_no` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disposal_locations`
--

CREATE TABLE `disposal_locations` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` enum('dump','scrap','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dump',
  `address_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `disposal_locations`
--

INSERT INTO `disposal_locations` (`id`, `name`, `note`, `type`, `address_1`, `address_2`, `city`, `state`, `zip`, `phone`, `email`, `active`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'Westerly Transfer', '', 'dump', '', '', 'Westerly', 'RI', '', '', NULL, 1, NULL, '2026-01-08 17:21:23', '2026-01-08 17:21:23'),
(2, 'Central Landfill', '', 'dump', '', '', 'Johnston', 'RI', '', '', NULL, 1, NULL, '2026-01-08 17:21:37', '2026-01-08 17:21:37'),
(3, 'Yerringtons', '', 'scrap', '', '', 'North Stonington', 'CT', '', '', NULL, 1, NULL, '2026-01-08 17:21:59', '2026-01-08 17:21:59'),
(4, 'Full Circle', '', 'scrap', '', '', 'Johnston', 'RI', '', '', NULL, 1, NULL, '2026-01-08 17:22:14', '2026-01-08 17:22:14'),
(5, 'Exeter Scrap', '', 'scrap', '', '', 'Exeter', 'RI', '', '', NULL, 1, NULL, '2026-01-08 17:22:28', '2026-01-08 17:22:28');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `fire_date` date DEFAULT NULL,
  `wage_rate` decimal(12,2) UNSIGNED DEFAULT NULL,
  `wage_type` enum('hourly','salary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hourly',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `hourly_rate` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `first_name`, `last_name`, `phone`, `email`, `hire_date`, `fire_date`, `wage_rate`, `wage_type`, `note`, `active`, `deleted_at`, `created_at`, `updated_at`, `hourly_rate`) VALUES
(1, 'Julian', 'Keena', '', '', NULL, NULL, NULL, 'hourly', '', 1, NULL, '2026-01-08 17:41:50', '2026-01-08 17:41:50', 20.00),
(2, 'Joe', 'Goins Jr', '', '', NULL, NULL, NULL, 'hourly', '', 1, NULL, '2026-01-08 17:42:09', '2026-01-08 17:42:09', 25.00),
(3, 'Logan', 'Goins', '', '', NULL, NULL, NULL, 'hourly', '', 1, NULL, '2026-01-08 17:42:18', '2026-01-08 17:42:18', 20.00),
(4, 'Annabelle', 'McGovern', '8608033249', 'belle@bellestinks.com', '2013-05-18', NULL, 25.00, 'hourly', '', 1, NULL, '2026-02-14 18:02:32', '2026-02-14 18:02:32', 25.00);

-- --------------------------------------------------------

--
-- Table structure for table `employee_time_entries`
--

CREATE TABLE `employee_time_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `employee_id` bigint UNSIGNED NOT NULL,
  `job_id` bigint UNSIGNED DEFAULT NULL,
  `work_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `minutes_worked` int UNSIGNED DEFAULT NULL,
  `pay_rate` decimal(12,2) UNSIGNED DEFAULT NULL,
  `total_paid` decimal(12,2) UNSIGNED DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_time_entries`
--

INSERT INTO `employee_time_entries` (`id`, `employee_id`, `job_id`, `work_date`, `start_time`, `end_time`, `minutes_worked`, `pay_rate`, `total_paid`, `note`, `active`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 3, 4, '2026-01-08', '09:45:00', '13:30:00', 225, 20.00, 75.00, '', 1, NULL, '2026-01-08 17:42:59', '2026-01-08 17:42:59'),
(2, 2, 4, '2026-01-08', '09:45:00', '13:30:00', 225, 25.00, 93.75, '', 1, NULL, '2026-01-08 17:43:14', '2026-01-08 17:43:14'),
(3, 1, 4, '2026-01-08', '09:45:00', '13:30:00', 225, 20.00, 75.00, '', 1, NULL, '2026-01-08 17:43:26', '2026-01-08 17:43:26'),
(4, 3, 4, '2026-02-14', '08:30:00', '20:30:00', 720, 20.00, 240.00, '', 1, NULL, '2026-02-14 18:30:24', '2026-02-14 18:30:24'),
(5, 3, 10, '2026-02-16', '10:00:00', '14:15:00', 255, 20.00, 85.00, '', 1, NULL, '2026-02-15 21:07:27', '2026-02-15 21:07:27'),
(6, 4, 3, '2026-02-16', '17:43:10', '17:43:26', 1, 25.00, 0.42, NULL, 1, NULL, '2026-02-16 12:43:10', '2026-02-16 12:43:26'),
(7, 2, 10, '2026-02-16', '17:57:56', '17:58:09', 1, 25.00, 0.42, NULL, 1, NULL, '2026-02-16 12:57:56', '2026-02-16 12:58:09'),
(9, 2, NULL, '2026-02-16', '13:22:54', '13:23:04', 0, 25.00, 0.00, '', 1, NULL, '2026-02-16 13:22:54', '2026-02-16 13:23:04'),
(10, 2, 9, '2026-02-16', '18:58:00', '22:18:00', 640, 25.00, 683.33, '', 1, NULL, '2026-02-16 18:58:35', '2026-02-17 22:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `estates`
--

CREATE TABLE `estates` (
  `id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `address_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_text` tinyint(1) NOT NULL DEFAULT '0',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estates_x_clients`
--

CREATE TABLE `estates_x_clients` (
  `id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `estate_id` bigint UNSIGNED NOT NULL,
  `note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `updated_by` int UNSIGNED DEFAULT NULL,
  `deleted_by` int UNSIGNED DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint UNSIGNED NOT NULL,
  `job_id` bigint UNSIGNED DEFAULT NULL,
  `disposal_location_id` bigint UNSIGNED DEFAULT NULL,
  `expense_category_id` bigint UNSIGNED DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `description` text,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `job_id`, `disposal_location_id`, `expense_category_id`, `category`, `description`, `amount`, `expense_date`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 1, 2, 'Dump', '', 50.00, '2026-01-08', 1, '2026-01-08 17:23:02', '2026-02-13 13:52:26', NULL),
(2, 1, 1, 2, 'Dump', '', 150.00, '2026-01-08', 1, '2026-01-08 17:23:15', '2026-02-13 13:52:26', NULL),
(3, 4, NULL, 3, 'Test', 'Test', 500.00, '2026-02-13', 1, '2026-02-13 12:57:23', '2026-02-13 13:52:26', NULL),
(4, NULL, NULL, 1, 'Supplies', 'Fuel', 250.00, '2026-02-14', 1, '2026-02-14 13:25:04', '2026-02-14 13:28:35', NULL),
(9, 16, 2, 2, 'Dump', NULL, 300.00, '2026-03-03', 1, '2026-02-18 08:59:48', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `note`, `active`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'Supplies', '', 1, NULL, '2026-02-13 13:43:59', '2026-02-13 13:43:59'),
(2, 'Dump', NULL, 1, NULL, '2026-02-13 13:52:26', '2026-02-13 13:52:26'),
(3, 'Test', NULL, 0, '2026-02-13 13:52:41', '2026-02-13 13:52:26', '2026-02-13 13:52:41');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `estate_id` bigint UNSIGNED DEFAULT NULL,
  `job_owner_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_owner_id` bigint UNSIGNED DEFAULT NULL,
  `contact_client_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `address_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_text` tinyint(1) NOT NULL DEFAULT '0',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote_date` datetime DEFAULT NULL,
  `scheduled_date` datetime DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `billed_date` datetime DEFAULT NULL,
  `paid_date` datetime DEFAULT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  `total_quote` decimal(12,2) UNSIGNED DEFAULT NULL,
  `total_billed` decimal(12,2) UNSIGNED DEFAULT NULL,
  `job_status` enum('pending','active','complete','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `cancelled_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `bill_to_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_address_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_address_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_city` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_zip` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_to_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `client_id`, `estate_id`, `job_owner_type`, `job_owner_id`, `contact_client_id`, `name`, `note`, `address_1`, `address_2`, `city`, `state`, `zip`, `phone`, `can_text`, `email`, `quote_date`, `scheduled_date`, `start_date`, `end_date`, `billed_date`, `paid_date`, `paid`, `total_quote`, `total_billed`, `job_status`, `cancelled_at`, `active`, `deleted_at`, `deleted_by`, `created_at`, `created_by`, `updated_at`, `bill_to_name`, `bill_to_phone`, `bill_to_email`, `bill_to_address_1`, `bill_to_address_2`, `bill_to_city`, `bill_to_state`, `bill_to_zip`, `bill_to_note`) VALUES
(1, 1, NULL, 'client', 1, 1, 'Mom\'s garage clean out', '', '21 Riverdale Rd', '', 'Westerly', 'RI', '02891', '', 0, '', '2026-01-02 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', NULL, 0, 300.00, 375.00, 'complete', NULL, 1, NULL, NULL, '2026-01-08 17:20:24', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, NULL, 'client', 1, 1, 'Driveway pickup', '', '5 Atlas St', '', 'Westerly', 'RI', '02891', '', 0, '', '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', NULL, 0, 150.00, 150.00, 'complete', NULL, 1, NULL, NULL, '2026-01-08 17:21:01', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 2, NULL, 'client', 2, 2, 'TV Pickup', 'One large TV', '22 Littlebrook Rd', '', 'Westerly', 'RI', '02891', '', 0, '', NULL, '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-01-06 00:00:00', '2026-02-13 18:21:21', 1, 125.00, 180.00, 'complete', NULL, 1, NULL, NULL, '2026-01-08 17:33:44', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 3, NULL, 'client', 3, 3, 'Shed demo and power wash side of house', '', '52 Rawlinson Dr', '', 'Coventry', 'RI', '02816', '', 0, '', '2026-01-03 00:00:00', '2026-01-08 00:00:00', '2026-01-08 00:00:00', '2026-01-08 00:00:00', '2026-01-08 00:00:00', '2026-02-13 18:15:01', 1, 2500.00, 2550.00, 'complete', NULL, 1, NULL, NULL, '2026-01-08 17:37:00', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 4, NULL, 'client', 4, 4, 'House clean out', '', '59 Resevoir Rd', '', 'Coventry', 'RI', '02816', '', 0, '', '2026-01-09 00:00:00', '2026-01-12 00:00:00', NULL, NULL, '2026-01-09 00:00:00', NULL, 0, 100.00, 100.00, 'pending', NULL, 0, '2026-02-12 16:59:47', 1, '2026-01-09 16:41:47', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 2, NULL, 'client', 2, 2, 'Job for Manny Theadore', '', '', '', '', '', '', '', 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'pending', NULL, 0, '2026-01-09 17:38:17', NULL, '2026-01-09 17:38:05', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 9, NULL, 'client', 9, 9, 'Test jo0b', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'pending', NULL, 0, '2026-02-12 16:59:38', 1, '2026-02-06 21:45:33', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 4, NULL, 'client', 4, 4, 'Test Job', NULL, '59 Resevoir Rd', NULL, 'Coventry', 'RI', '02815', NULL, 0, NULL, NULL, '2025-12-25 00:00:00', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'cancelled', NULL, 0, '2026-02-12 20:36:11', 1, '2026-02-07 17:10:05', 1, '2026-02-13 13:26:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 5, NULL, 'client', 5, 5, 'Job for McGovern FPS', 'Converted from prospect #3.\r\nContacted: 2026-02-14\r\nFollow Up: 2026-02-18\r\nNext Step: Make Appointment\r\n\r\nProspect Notes:\r\nMake appointment for clean out date', '28 W Arch St', '', 'Pawcatuck', 'CT', '06379', '4012599265', 0, 'bam@mcgovernfps.com', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'pending', NULL, 1, NULL, NULL, '2026-02-14 12:48:15', 1, '2026-02-14 12:48:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 11, NULL, 'client', 11, 11, 'Job for Jazzy Fae', 'Converted from prospect #4.\r\nContacted: 2026-02-14', '', '', '', '', '', '4015559999', 0, '', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'pending', NULL, 1, NULL, NULL, '2026-02-14 14:28:33', 1, '2026-02-14 14:28:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 20, NULL, 'client', 20, 20, 'Demo Cleanout A', NULL, '14 Elm St', NULL, 'Providence', 'RI', '02908', NULL, 0, NULL, '2026-03-01 00:00:00', '2026-03-03 00:00:00', '2026-03-03 00:00:00', NULL, NULL, NULL, 0, 1250.00, 1350.00, 'complete', NULL, 1, NULL, NULL, '2026-02-18 08:59:48', 1, '2026-02-18 09:05:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 19, NULL, 'client', 19, 19, 'Demo Cleanout B', NULL, '88 Post Rd', NULL, 'Warwick', 'RI', '02889', NULL, 0, NULL, '2026-03-02 00:00:00', '2026-03-05 00:00:00', '2026-03-05 00:00:00', NULL, NULL, NULL, 0, 2200.00, NULL, 'active', NULL, 1, NULL, NULL, '2026-02-18 08:59:48', 1, '2026-02-18 09:05:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_actions`
--

CREATE TABLE `job_actions` (
  `id` bigint UNSIGNED NOT NULL,
  `job_id` bigint UNSIGNED NOT NULL,
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_at` datetime NOT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `ref_table` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` bigint UNSIGNED DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_actions`
--

INSERT INTO `job_actions` (`id`, `job_id`, `action_type`, `action_at`, `amount`, `ref_table`, `ref_id`, `note`, `created_at`) VALUES
(1, 1, 'job_created', '2026-01-08 22:20:24', NULL, NULL, NULL, NULL, '2026-01-08 17:20:24'),
(2, 2, 'job_created', '2026-01-08 22:21:01', NULL, NULL, NULL, NULL, '2026-01-08 17:21:01'),
(3, 2, 'dump', '2026-01-08 22:23:02', 50.00, 'expenses', 1, NULL, '2026-01-08 17:23:02'),
(4, 1, 'dump', '2026-01-08 22:23:15', 150.00, 'expenses', 2, NULL, '2026-01-08 17:23:15'),
(5, 1, 'payment', '2026-01-08 22:26:52', 375.00, 'job_payments', 1, 'Method: Venmo', '2026-01-08 17:26:52'),
(6, 2, 'payment', '2026-01-08 22:27:08', 150.00, 'job_payments', 2, 'Method: Venmo', '2026-01-08 17:27:08'),
(7, 3, 'job_created', '2026-01-08 22:33:44', NULL, NULL, NULL, NULL, '2026-01-08 17:33:44'),
(8, 3, 'payment', '2026-01-08 22:34:01', 180.00, 'job_payments', 3, 'Method: Check', '2026-01-08 17:34:01'),
(9, 4, 'job_created', '2026-01-08 22:37:00', NULL, NULL, NULL, NULL, '2026-01-08 17:37:00'),
(10, 4, 'payment', '2026-01-08 22:37:00', 2500.00, 'billing_entry', NULL, 'Method: Check', '2026-01-08 17:37:56'),
(11, 5, 'converted_from_prospect', '2026-01-09 21:41:47', NULL, 'prospects', 1, NULL, '2026-01-09 16:41:47'),
(12, 5, 'payment', '2026-01-09 21:44:01', 100.00, 'job_payments', 5, 'Method: Silver', '2026-01-09 16:44:01'),
(13, 6, 'converted_from_prospect', '2026-01-09 22:38:05', NULL, 'prospects', 2, NULL, '2026-01-09 17:38:05'),
(14, 9, 'job_created', '2026-02-14 17:48:15', NULL, NULL, NULL, 'Job created.', '2026-02-14 12:48:15'),
(15, 9, 'note_updated', '2026-02-14 17:48:15', NULL, NULL, NULL, 'Job note was added.', '2026-02-14 12:48:15'),
(16, 9, 'prospect_converted', '2026-02-14 17:48:15', NULL, 'prospects', 3, 'Converted from prospect #3.', '2026-02-14 12:48:15'),
(17, 3, 'disposal_added', '2026-02-14 19:27:21', 115.00, 'job_disposal_events', 2, 'Disposal event added (dump).', '2026-02-14 14:27:21'),
(18, 10, 'job_created', '2026-02-14 19:28:33', NULL, NULL, NULL, 'Job created.', '2026-02-14 14:28:33'),
(19, 10, 'note_updated', '2026-02-14 19:28:33', NULL, NULL, NULL, 'Job note was added.', '2026-02-14 14:28:33'),
(20, 10, 'prospect_converted', '2026-02-14 19:28:33', NULL, 'prospects', 4, 'Converted from prospect #4.', '2026-02-14 14:28:33'),
(21, 4, 'time_entry_added', '2026-02-14 08:30:00', 240.00, 'employee_time_entries', 4, 'Time entry added (12h 00m).', '2026-02-14 18:30:24'),
(22, 10, 'time_entry_added', '2026-02-16 10:00:00', 85.00, 'employee_time_entries', 5, 'Time entry added (4h 15m).', '2026-02-15 21:07:27'),
(23, 3, 'time_punched_in', '2026-02-16 17:43:10', NULL, 'employee_time_entries', 6, 'Annabelle McGovern punched in.', '2026-02-16 12:43:10'),
(24, 3, 'time_punched_out', '2026-02-16 17:43:26', 0.42, 'employee_time_entries', 6, 'Annabelle McGovern punched out (0h 01m).', '2026-02-16 12:43:26'),
(25, 10, 'crew_member_added', '2026-02-16 17:57:51', NULL, 'employees', 2, 'Joe Goins Jr added to crew.', '2026-02-16 12:57:51'),
(26, 10, 'time_punched_in', '2026-02-16 17:57:56', NULL, 'employee_time_entries', 7, 'Joe Goins Jr punched in.', '2026-02-16 12:57:56'),
(27, 10, 'time_punched_out', '2026-02-16 17:58:09', 0.42, 'employee_time_entries', 7, 'Joe Goins Jr punched out (0h 01m).', '2026-02-16 12:58:09'),
(28, 9, 'crew_member_added', '2026-02-16 18:58:32', NULL, 'employees', 2, 'Joe Goins Jr added to crew.', '2026-02-16 18:58:32'),
(29, 9, 'time_punched_in', '2026-02-16 18:58:35', NULL, 'employee_time_entries', 10, 'Joe Goins Jr punched in.', '2026-02-16 18:58:35'),
(30, 9, 'time_punched_out', '2026-02-17 22:18:56', 683.33, 'employee_time_entries', 10, 'Employee punched out (27h 20m).', '2026-02-17 22:18:56'),
(31, 9, 'time_entry_updated', '2026-02-17 22:43:22', 683.33, 'employee_time_entries', 10, 'Time entry updated (10h 40m).', '2026-02-17 22:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `job_crew`
--

CREATE TABLE `job_crew` (
  `id` bigint UNSIGNED NOT NULL,
  `job_id` bigint UNSIGNED NOT NULL,
  `employee_id` bigint UNSIGNED NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_crew`
--

INSERT INTO `job_crew` (`id`, `job_id`, `employee_id`, `active`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 10, 2, 1, NULL, '2026-02-16 12:57:51', '2026-02-16 12:57:51'),
(2, 9, 2, 1, NULL, '2026-02-16 18:58:32', '2026-02-16 18:58:32');

-- --------------------------------------------------------

--
-- Table structure for table `job_disposal_events`
--

CREATE TABLE `job_disposal_events` (
  `id` bigint UNSIGNED NOT NULL,
  `job_id` bigint UNSIGNED NOT NULL,
  `disposal_location_id` bigint UNSIGNED NOT NULL,
  `event_date` date NOT NULL,
  `type` enum('dump','transfer_station','landfill','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dump',
  `amount` decimal(12,2) UNSIGNED NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_disposal_events`
--

INSERT INTO `job_disposal_events` (`id`, `job_id`, `disposal_location_id`, `event_date`, `type`, `amount`, `note`, `active`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 4, 2, '2026-02-13', 'dump', 250.00, 'Test', 1, NULL, '2026-02-13 12:57:00', '2026-02-13 12:57:00'),
(2, 3, 2, '2026-02-11', 'dump', 115.00, NULL, 1, NULL, '2026-02-14 14:27:21', '2026-02-14 14:27:21');

-- --------------------------------------------------------

--
-- Table structure for table `job_payments`
--

CREATE TABLE `job_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `job_id` bigint UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_payments`
--

INSERT INTO `job_payments` (`id`, `job_id`, `payment_date`, `amount`, `method`, `note`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-01-06', 375.00, 'Venmo', '', '2026-01-08 17:26:52', '2026-01-08 17:26:52'),
(2, 2, '2026-01-06', 150.00, 'Venmo', '', '2026-01-08 17:27:08', '2026-01-08 17:27:08'),
(3, 3, '2026-01-06', 180.00, 'Check', '', '2026-01-08 17:34:01', '2026-01-08 17:34:01'),
(4, 4, '2026-01-08', 2550.00, 'Check', '', '2026-01-08 17:37:56', '2026-01-08 17:37:56'),
(5, 5, '2026-01-09', 100.00, 'Silver', '', '2026-01-09 16:44:01', '2026-01-09 16:44:01'),
(8, 16, '2026-03-03', 1350.00, 'Cash', NULL, '2026-02-18 08:59:48', '2026-02-18 08:59:48');

-- --------------------------------------------------------

--
-- Table structure for table `prospects`
--

CREATE TABLE `prospects` (
  `id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `converted_job_id` bigint UNSIGNED DEFAULT NULL,
  `job_id` bigint UNSIGNED DEFAULT NULL,
  `contacted_on` date DEFAULT NULL,
  `follow_up_on` date DEFAULT NULL,
  `status` enum('active','converted','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `priority_rating` tinyint UNSIGNED DEFAULT NULL,
  `next_step` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prospects`
--

INSERT INTO `prospects` (`id`, `client_id`, `converted_job_id`, `job_id`, `contacted_on`, `follow_up_on`, `status`, `priority_rating`, `next_step`, `note`, `active`, `deleted_at`, `deleted_by`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 4, NULL, NULL, '2026-01-09', '2026-01-09', 'converted', NULL, NULL, '', 0, NULL, NULL, '2026-01-09 13:52:53', NULL, '2026-01-09 16:41:47', NULL),
(2, 2, NULL, NULL, '2026-01-09', '2026-01-12', 'converted', NULL, NULL, '', 0, NULL, NULL, '2026-01-09 17:37:53', NULL, '2026-01-09 17:38:05', NULL),
(3, 5, NULL, NULL, '2026-02-14', '2026-02-18', 'converted', 2, 'make_appointment', 'Make appointment for clean out date\nConverted to job #9', 0, '2026-02-14 12:48:15', 1, '2026-02-14 12:41:58', 1, '2026-02-14 12:48:15', 1),
(4, 11, NULL, NULL, '2026-02-14', NULL, 'converted', 2, NULL, 'Converted to job #10', 0, '2026-02-14 14:28:33', 1, '2026-02-14 14:28:19', 1, '2026-02-14 14:28:33', 1),
(5, 12, NULL, NULL, '2026-02-16', NULL, 'active', 2, NULL, '', 1, NULL, NULL, '2026-02-16 15:59:22', 1, '2026-02-16 15:59:22', 1),
(6, 5, NULL, NULL, '2026-02-16', '2026-02-17', 'active', 2, 'call', '', 1, NULL, NULL, '2026-02-16 16:22:22', 1, '2026-02-16 16:22:22', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` bigint UNSIGNED NOT NULL,
  `job_id` bigint UNSIGNED DEFAULT NULL,
  `type` enum('shop','scrap','ebay','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shop',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `gross_amount` decimal(12,2) UNSIGNED NOT NULL,
  `net_amount` decimal(12,2) UNSIGNED DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `disposal_location_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `job_id`, `type`, `name`, `note`, `start_date`, `end_date`, `gross_amount`, `net_amount`, `active`, `created_at`, `created_by`, `updated_at`, `deleted_at`, `deleted_by`, `disposal_location_id`) VALUES
(1, NULL, 'shop', 'Weekend Sales', '', '2026-01-03', '2026-01-04', 1281.65, NULL, 1, '2026-01-08 17:28:04', 1, '2026-02-06 19:34:31', NULL, 1, NULL),
(2, NULL, 'scrap', 'Scrap', '', '2026-01-05', NULL, 222.00, NULL, 0, '2026-01-08 17:28:36', 1, '2026-02-06 19:34:31', '2026-02-06 19:19:03', 1, NULL),
(3, NULL, 'scrap', 'Scrap', 'Copper, aluminum, other', '2026-01-06', NULL, 370.00, NULL, 1, '2026-01-08 17:31:25', 1, '2026-02-13 19:15:12', NULL, NULL, NULL),
(4, NULL, 'ebay', 'Sales Summary', '', '2026-01-01', '2026-01-08', 1889.36, 1293.57, 1, '2026-01-08 17:39:14', 1, '2026-02-13 19:19:25', '2026-02-06 19:19:07', 1, NULL),
(5, NULL, 'scrap', 'Exeter Copper', 'test', '2026-01-22', NULL, 322.00, NULL, 1, '2026-02-06 18:41:26', 1, '2026-02-13 19:19:26', '2026-02-06 19:17:55', 1, NULL),
(6, NULL, 'shop', 'Test sale', 'with really long note. I mean really long. like way long. Like super long. Like oh yea long.', '2026-02-17', NULL, 323.00, 44.00, 1, '2026-02-06 19:08:41', 1, '2026-02-13 19:19:29', '2026-02-06 19:18:04', 1, NULL),
(7, NULL, 'scrap', 'Exeter', 'Copper', '2026-01-13', NULL, 322.00, NULL, 1, '2026-02-06 19:20:18', 1, '2026-02-06 19:34:31', NULL, 1, NULL),
(8, 16, 'scrap', 'Auto Generated Scrap Load', NULL, '2026-03-03', NULL, 550.00, NULL, 1, '2026-02-18 08:59:48', 1, '2026-02-18 08:59:48', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `todos`
--

CREATE TABLE `todos` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text,
  `link_type` varchar(30) NOT NULL DEFAULT 'general',
  `link_id` bigint UNSIGNED DEFAULT NULL,
  `assigned_user_id` bigint UNSIGNED DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL,
  `importance` tinyint UNSIGNED NOT NULL DEFAULT '3',
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `outcome` text,
  `due_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `todos`
--

INSERT INTO `todos` (`id`, `title`, `body`, `link_type`, `link_id`, `assigned_user_id`, `created_by`, `updated_by`, `deleted_by`, `importance`, `status`, `outcome`, `due_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Prospect Follow-Up', 'Auto-created from prospect follow-up date.\r\nNext step: Call', 'prospect', 6, 1, 1, 1, NULL, 2, 'open', '', '2026-02-18 09:00:00', NULL, '2026-02-16 16:22:22', '2026-02-17 22:17:41', NULL),
(2, 'Call Demo Client', 'Follow up after service', 'job', 16, 1, 1, NULL, NULL, 3, 'open', NULL, '2026-03-06 09:00:00', NULL, '2026-02-18 08:59:49', '2026-02-18 08:59:49', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_setup_token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_setup_expires_at` datetime DEFAULT NULL,
  `password_setup_sent_at` datetime DEFAULT NULL,
  `password_setup_used_at` datetime DEFAULT NULL,
  `two_factor_code_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_expires_at` datetime DEFAULT NULL,
  `two_factor_sent_at` datetime DEFAULT NULL,
  `last_2fa_at` datetime DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` smallint UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `password_setup_token_hash`, `password_setup_expires_at`, `password_setup_sent_at`, `password_setup_used_at`, `two_factor_code_hash`, `two_factor_expires_at`, `two_factor_sent_at`, `last_2fa_at`, `first_name`, `last_name`, `role`, `is_active`, `email_verified_at`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 'jimmy@jimmysjunk.com', '$2y$10$rMlv0WpDaUvQuRLMrdCYleQG.LRr0noDRGe/1w1Kq8lDUYHCJd7hG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Jimmy', 'McGovern', 99, 1, '2026-01-08 15:16:44', '2026-01-08 15:17:58', 1, '2026-02-09 16:20:59', 1, NULL, NULL),
(2, 'bam@mcgovernfps.com', '$2y$10$5fHvbjEYhCRcAuV5IdaRhuTc8V2eLlRcUWmoWVJQQ/02a26oD4qza', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Barbara', 'McGovern', 1, 0, NULL, '2026-02-07 14:25:18', 1, '2026-02-12 14:57:50', 1, '2026-02-12 14:57:50', 1),
(3, 'babygirl@daddysgirl.com', '$2y$10$DW2um6uR1PGldrYyyF4E3uDdXhaOnv5NfCFChpCgYJrqD1AoMu.nq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Annabelle', 'McGovern', 1, 0, NULL, '2026-02-09 16:11:23', 1, '2026-02-12 15:04:56', 1, '2026-02-12 15:04:56', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_actions`
--

CREATE TABLE `user_actions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `action_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_table` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `summary` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_actions`
--

INSERT INTO `user_actions` (`id`, `user_id`, `action_key`, `entity_table`, `entity_id`, `summary`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'client_contact_created', 'client_contacts', 1, 'Logged client contact #1.', NULL, '::1', '2026-02-17 22:16:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clients_phone` (`phone`),
  ADD KEY `idx_clients_email` (`email`),
  ADD KEY `idx_clients_name` (`last_name`,`first_name`),
  ADD KEY `idx_clients_created_by` (`created_by`),
  ADD KEY `idx_clients_deleted_by` (`deleted_by`);

--
-- Indexes for table `client_contacts`
--
ALTER TABLE `client_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_contacts_client_date` (`client_id`,`contacted_at`),
  ADD KEY `idx_client_contacts_link` (`link_type`,`link_id`),
  ADD KEY `idx_client_contacts_method` (`contact_method`),
  ADD KEY `idx_client_contacts_active` (`active`,`deleted_at`),
  ADD KEY `idx_client_contacts_created_by` (`created_by`),
  ADD KEY `idx_client_contacts_updated_by` (`updated_by`),
  ADD KEY `idx_client_contacts_deleted_by` (`deleted_by`);

--
-- Indexes for table `client_reminders`
--
ALTER TABLE `client_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_reminders_client` (`client_id`),
  ADD KEY `idx_client_reminders_reminder_at` (`reminder_at`),
  ADD KEY `idx_client_reminders_status` (`status`),
  ADD KEY `fk_client_reminders_created_by` (`created_by`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `companies_x_clients`
--
ALTER TABLE `companies_x_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_client_id` (`client_id`);

--
-- Indexes for table `consignors`
--
ALTER TABLE `consignors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_consignors_number` (`consignor_number`),
  ADD KEY `idx_consignors_name` (`last_name`,`first_name`,`business_name`),
  ADD KEY `idx_consignors_active` (`active`,`deleted_at`),
  ADD KEY `idx_consignors_created_by` (`created_by`),
  ADD KEY `idx_consignors_updated_by` (`updated_by`),
  ADD KEY `idx_consignors_deleted_by` (`deleted_by`),
  ADD KEY `idx_consignors_next_payment_due` (`next_payment_due_date`);

--
-- Indexes for table `consignor_contacts`
--
ALTER TABLE `consignor_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consignor_contacts_consignor_date` (`consignor_id`,`contacted_at`),
  ADD KEY `idx_consignor_contacts_link` (`link_type`,`link_id`),
  ADD KEY `idx_consignor_contacts_active` (`active`,`deleted_at`),
  ADD KEY `idx_consignor_contacts_created_by` (`created_by`),
  ADD KEY `idx_consignor_contacts_updated_by` (`updated_by`),
  ADD KEY `idx_consignor_contacts_deleted_by` (`deleted_by`);

--
-- Indexes for table `consignor_contracts`
--
ALTER TABLE `consignor_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consignor_contracts_consignor` (`consignor_id`),
  ADD KEY `idx_consignor_contracts_active` (`active`,`deleted_at`),
  ADD KEY `idx_consignor_contracts_created_by` (`created_by`),
  ADD KEY `idx_consignor_contracts_updated_by` (`updated_by`),
  ADD KEY `idx_consignor_contracts_deleted_by` (`deleted_by`);

--
-- Indexes for table `consignor_payouts`
--
ALTER TABLE `consignor_payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consignor_payouts_consignor_date` (`consignor_id`,`payout_date`),
  ADD KEY `idx_consignor_payouts_status` (`status`),
  ADD KEY `idx_consignor_payouts_active` (`active`,`deleted_at`),
  ADD KEY `idx_consignor_payouts_created_by` (`created_by`),
  ADD KEY `idx_consignor_payouts_updated_by` (`updated_by`),
  ADD KEY `idx_consignor_payouts_deleted_by` (`deleted_by`);

--
-- Indexes for table `disposal_locations`
--
ALTER TABLE `disposal_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_disposal_locations_type` (`type`),
  ADD KEY `idx_disposal_locations_name` (`name`),
  ADD KEY `idx_disposal_locations_city_state` (`city`,`state`),
  ADD KEY `idx_disposal_locations_deleted_at` (`deleted_at`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employees_name` (`last_name`,`first_name`),
  ADD KEY `idx_employees_phone` (`phone`),
  ADD KEY `idx_employees_email` (`email`),
  ADD KEY `idx_employees_hourly_rate` (`hourly_rate`);

--
-- Indexes for table `employee_time_entries`
--
ALTER TABLE `employee_time_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_time_employee_date` (`employee_id`,`work_date`),
  ADD KEY `idx_time_job_date` (`job_id`,`work_date`);

--
-- Indexes for table `estates`
--
ALTER TABLE `estates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estates_client_id` (`client_id`),
  ADD KEY `idx_estates_name` (`name`),
  ADD KEY `idx_estates_city_state` (`city`,`state`);

--
-- Indexes for table `estates_x_clients`
--
ALTER TABLE `estates_x_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_estate_id` (`estate_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_expenses_category_id` (`expense_category_id`),
  ADD KEY `idx_expenses_job_date` (`job_id`,`expense_date`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_expense_categories_name` (`name`),
  ADD KEY `idx_expense_categories_deleted_at` (`deleted_at`),
  ADD KEY `idx_expense_categories_active` (`active`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jobs_client_id` (`client_id`),
  ADD KEY `idx_jobs_estate_id` (`estate_id`),
  ADD KEY `idx_jobs_status` (`job_status`),
  ADD KEY `idx_jobs_dates` (`scheduled_date`,`start_date`,`end_date`),
  ADD KEY `idx_jobs_created_by` (`created_by`),
  ADD KEY `idx_jobs_deleted_by` (`deleted_by`),
  ADD KEY `idx_jobs_owner` (`job_owner_type`,`job_owner_id`),
  ADD KEY `idx_jobs_contact_client` (`contact_client_id`);

--
-- Indexes for table `job_actions`
--
ALTER TABLE `job_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_actions_job` (`job_id`),
  ADD KEY `idx_job_actions_at` (`action_at`);

--
-- Indexes for table `job_crew`
--
ALTER TABLE `job_crew`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_job_crew_member` (`job_id`,`employee_id`),
  ADD KEY `idx_job_crew_employee` (`employee_id`);

--
-- Indexes for table `job_disposal_events`
--
ALTER TABLE `job_disposal_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_disposal_job_date` (`job_id`,`event_date`),
  ADD KEY `idx_disposal_location_date` (`disposal_location_id`,`event_date`);

--
-- Indexes for table `job_payments`
--
ALTER TABLE `job_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_payments_job` (`job_id`);

--
-- Indexes for table `prospects`
--
ALTER TABLE `prospects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prospects_client` (`client_id`),
  ADD KEY `idx_prospects_followup` (`follow_up_on`),
  ADD KEY `idx_prospects_converted_job` (`converted_job_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_type_dates` (`type`,`start_date`,`end_date`),
  ADD KEY `idx_sales_deleted_at` (`deleted_at`),
  ADD KEY `idx_sales_disposal_location_id` (`disposal_location_id`),
  ADD KEY `idx_sales_created_by` (`created_by`),
  ADD KEY `idx_sales_deleted_by` (`deleted_by`);

--
-- Indexes for table `todos`
--
ALTER TABLE `todos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_link` (`link_type`,`link_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_importance` (`importance`),
  ADD KEY `idx_assigned_user` (`assigned_user_id`),
  ADD KEY `idx_due_at` (`due_at`),
  ADD KEY `idx_todos_assigned_status_due` (`assigned_user_id`,`status`,`due_at`),
  ADD KEY `idx_todos_link_status` (`link_type`,`link_id`,`status`),
  ADD KEY `fk_todos_created_by` (`created_by`),
  ADD KEY `fk_todos_updated_by` (`updated_by`),
  ADD KEY `fk_todos_deleted_by` (`deleted_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_users_email` (`email`),
  ADD KEY `idx_users_password_setup_expires` (`password_setup_expires_at`);

--
-- Indexes for table `user_actions`
--
ALTER TABLE `user_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_actions_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_user_actions_entity` (`entity_table`,`entity_id`),
  ADD KEY `idx_user_actions_action_key` (`action_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `client_contacts`
--
ALTER TABLE `client_contacts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `client_reminders`
--
ALTER TABLE `client_reminders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `companies_x_clients`
--
ALTER TABLE `companies_x_clients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consignors`
--
ALTER TABLE `consignors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consignor_contacts`
--
ALTER TABLE `consignor_contacts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consignor_contracts`
--
ALTER TABLE `consignor_contracts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consignor_payouts`
--
ALTER TABLE `consignor_payouts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disposal_locations`
--
ALTER TABLE `disposal_locations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employee_time_entries`
--
ALTER TABLE `employee_time_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `estates`
--
ALTER TABLE `estates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estates_x_clients`
--
ALTER TABLE `estates_x_clients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `job_actions`
--
ALTER TABLE `job_actions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `job_crew`
--
ALTER TABLE `job_crew`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_disposal_events`
--
ALTER TABLE `job_disposal_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_payments`
--
ALTER TABLE `job_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `prospects`
--
ALTER TABLE `prospects`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `todos`
--
ALTER TABLE `todos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_actions`
--
ALTER TABLE `user_actions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_contacts`
--
ALTER TABLE `client_contacts`
  ADD CONSTRAINT `fk_client_contacts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_client_contacts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_client_contacts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_client_contacts_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `client_reminders`
--
ALTER TABLE `client_reminders`
  ADD CONSTRAINT `fk_client_reminders_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_client_reminders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `companies_x_clients`
--
ALTER TABLE `companies_x_clients`
  ADD CONSTRAINT `fk_cxc_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_cxc_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Constraints for table `consignors`
--
ALTER TABLE `consignors`
  ADD CONSTRAINT `fk_consignors_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignors_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignors_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `consignor_contacts`
--
ALTER TABLE `consignor_contacts`
  ADD CONSTRAINT `fk_consignor_contacts_consignor` FOREIGN KEY (`consignor_id`) REFERENCES `consignors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_contacts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_contacts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_contacts_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `consignor_contracts`
--
ALTER TABLE `consignor_contracts`
  ADD CONSTRAINT `fk_consignor_contracts_consignor` FOREIGN KEY (`consignor_id`) REFERENCES `consignors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_contracts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_contracts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_contracts_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `consignor_payouts`
--
ALTER TABLE `consignor_payouts`
  ADD CONSTRAINT `fk_consignor_payouts_consignor` FOREIGN KEY (`consignor_id`) REFERENCES `consignors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_payouts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_payouts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consignor_payouts_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `employee_time_entries`
--
ALTER TABLE `employee_time_entries`
  ADD CONSTRAINT `fk_time_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_time_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `estates`
--
ALTER TABLE `estates`
  ADD CONSTRAINT `fk_estates_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `estates_x_clients`
--
ALTER TABLE `estates_x_clients`
  ADD CONSTRAINT `fk_estates_x_clients_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_estates_x_clients_estate` FOREIGN KEY (`estate_id`) REFERENCES `estates` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_category` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_expenses_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `fk_jobs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jobs_contact_client` FOREIGN KEY (`contact_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jobs_estate` FOREIGN KEY (`estate_id`) REFERENCES `estates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `job_disposal_events`
--
ALTER TABLE `job_disposal_events`
  ADD CONSTRAINT `fk_disposal_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_disposal_location` FOREIGN KEY (`disposal_location_id`) REFERENCES `disposal_locations` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `prospects`
--
ALTER TABLE `prospects`
  ADD CONSTRAINT `fk_prospects_converted_job` FOREIGN KEY (`converted_job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `todos`
--
ALTER TABLE `todos`
  ADD CONSTRAINT `fk_todos_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_todos_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_actions`
--
ALTER TABLE `user_actions`
  ADD CONSTRAINT `fk_user_actions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
