-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 27, 2025 at 10:52 AM
-- Server version: 10.11.14-MariaDB
-- PHP Version: 8.4.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `civicbd_group2`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feature` varchar(100) NOT NULL,
  `activity_type` enum('login','view','create','edit','update','delete','message','comment','other','error') NOT NULL,
  `details` mediumtext DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atten_in_out`
--

CREATE TABLE `atten_in_out` (
  `id` int(11) NOT NULL,
  `USERID` int(11) NOT NULL,
  `Badgenumber` int(11) DEFAULT NULL,
  `CHECKTIME` varchar(300) DEFAULT NULL,
  `CHECKTYPE` varchar(100) DEFAULT NULL,
  `VERIFYCODE` int(11) DEFAULT NULL,
  `SENSORID` int(11) DEFAULT NULL,
  `Memoinfo` varchar(100) DEFAULT NULL,
  `WorkCode` int(11) DEFAULT NULL,
  `sn` varchar(300) DEFAULT NULL,
  `UserExtFmt` int(11) DEFAULT NULL,
  `active` int(11) DEFAULT 1,
  `entry_time` timestamp(6) NULL DEFAULT current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atten_reason`
--

CREATE TABLE `atten_reason` (
  `id` int(11) NOT NULL,
  `Badgenumber` int(11) NOT NULL,
  `reason` varchar(300) NOT NULL,
  `text` text NOT NULL,
  `date` varchar(300) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `atten_users`
--

CREATE TABLE `atten_users` (
  `USERID` int(11) NOT NULL,
  `Badgenumber` int(11) DEFAULT NULL,
  `SSN` varchar(255) DEFAULT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Gender` char(1) DEFAULT NULL,
  `TITLE` varchar(255) DEFAULT NULL,
  `PAGER` int(11) DEFAULT NULL,
  `BIRTHDAY` varchar(255) DEFAULT NULL,
  `HIREDDAY` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `CITY` varchar(255) DEFAULT NULL,
  `STATE` varchar(255) DEFAULT NULL,
  `ZIP` varchar(255) DEFAULT NULL,
  `OPHONE` varchar(255) DEFAULT NULL,
  `FPHONE` varchar(255) DEFAULT NULL,
  `VERIFICATIONMETHOD` int(11) DEFAULT NULL,
  `DEFAULTDEPTID` int(11) DEFAULT NULL,
  `SECURITYFLAGS` varchar(255) DEFAULT NULL,
  `ATT` int(11) DEFAULT NULL,
  `INLATE` int(11) DEFAULT NULL,
  `OUTEARLY` int(11) DEFAULT NULL,
  `OVERTIME` int(11) DEFAULT NULL,
  `SEP` int(11) DEFAULT NULL,
  `HOLIDAY` int(11) DEFAULT NULL,
  `MINZU` varchar(255) DEFAULT NULL,
  `PASSWORD` varchar(255) DEFAULT NULL,
  `LUNCHDURATION` int(11) DEFAULT NULL,
  `PHOTO` varchar(255) DEFAULT NULL,
  `mverifypass` varchar(255) DEFAULT NULL,
  `Notes` varchar(255) DEFAULT NULL,
  `privilege` int(11) DEFAULT NULL,
  `InheritDeptSch` int(11) DEFAULT NULL,
  `InheritDeptSchClass` int(11) DEFAULT NULL,
  `AutoSchPlan` int(11) DEFAULT NULL,
  `MinAutoSchInterval` int(11) DEFAULT NULL,
  `RegisterOT` int(11) DEFAULT NULL,
  `InheritDeptRule` int(11) DEFAULT NULL,
  `EMPRIVILEGE` int(11) DEFAULT NULL,
  `CardNo` varchar(255) DEFAULT NULL,
  `FaceGroup` int(11) DEFAULT NULL,
  `AccGroup` int(11) DEFAULT NULL,
  `UseAccGroupTZ` int(11) DEFAULT NULL,
  `VerifyCode` int(11) DEFAULT NULL,
  `Expires` int(11) DEFAULT NULL,
  `ValidCount` int(11) DEFAULT NULL,
  `ValidTimeBegin` varchar(255) DEFAULT NULL,
  `ValidTimeEnd` varchar(255) DEFAULT NULL,
  `TimeZone1` int(11) DEFAULT NULL,
  `TimeZone2` int(11) DEFAULT NULL,
  `TimeZone3` int(11) DEFAULT NULL,
  `active` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `awards`
--

CREATE TABLE `awards` (
  `id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 999,
  `name` text NOT NULL,
  `distance` text NOT NULL,
  `image` text NOT NULL,
  `product` text NOT NULL,
  `featured` int(11) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `backup_type` enum('full','table','incremental') DEFAULT 'full',
  `filename` varchar(512) NOT NULL,
  `file_path` varchar(1024) DEFAULT NULL,
  `table_name` varchar(255) DEFAULT NULL COMMENT 'For table-specific backups',
  `size` bigint(20) DEFAULT 0,
  `status` enum('pending','inprogress','completed','failed') DEFAULT 'pending',
  `r2_key` varchar(1024) DEFAULT NULL,
  `r2_uploaded` tinyint(1) DEFAULT 0,
  `r2_uploaded_at` datetime DEFAULT NULL,
  `compression` varchar(50) DEFAULT 'gzip',
  `checksum` varchar(64) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_by` int(11) DEFAULT 0 COMMENT '0 = auto/cron, >0 = user',
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_schedules`
--

CREATE TABLE `backup_schedules` (
  `id` int(11) NOT NULL,
  `schedule_name` varchar(255) NOT NULL,
  `backup_type` enum('full','incremental') DEFAULT 'full',
  `frequency_hours` int(11) DEFAULT 6 COMMENT 'Every 6 hours',
  `tables` text DEFAULT NULL COMMENT 'JSON array of tables, NULL = all',
  `enabled` tinyint(1) DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `retention_days` int(11) DEFAULT 30,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_receipts`
--

CREATE TABLE `bank_receipts` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `fund_id` int(11) NOT NULL DEFAULT 0,
  `description` tinytext NOT NULL,
  `price` varchar(50) NOT NULL DEFAULT '0',
  `mode` varchar(50) NOT NULL DEFAULT '',
  `approved` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `receipt_file` varchar(250) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `broadcast`
--

CREATE TABLE `broadcast` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `image` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'upload/photos/d-group.jpg',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `broadcast_users`
--

CREATE TABLE `broadcast_users` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `broadcast_id` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certifications`
--

CREATE TABLE `certifications` (
  `id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 999,
  `name` text NOT NULL,
  `distance` text NOT NULL,
  `image` text NOT NULL,
  `product` text NOT NULL,
  `featured` int(11) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients_review`
--

CREATE TABLE `clients_review` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `designation` text NOT NULL,
  `image` text NOT NULL,
  `review` text NOT NULL,
  `product` text NOT NULL,
  `rating` text NOT NULL,
  `featured` int(11) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_advance`
--

CREATE TABLE `crm_advance` (
  `id` int(11) NOT NULL,
  `Badgenumber` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `time` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_assignment_rules`
--

CREATE TABLE `crm_assignment_rules` (
  `project` varchar(120) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL,
  `raw_weight` int(11) NOT NULL DEFAULT 100,
  `participating` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_assignment_state`
--

CREATE TABLE `crm_assignment_state` (
  `project` varchar(120) NOT NULL,
  `type` varchar(32) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `current_weight` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_bazar`
--

CREATE TABLE `crm_bazar` (
  `id` int(11) NOT NULL,
  `icon` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(120) NOT NULL,
  `unit` varchar(32) NOT NULL DEFAULT 'pcs',
  `quantity` varchar(32) NOT NULL DEFAULT '0',
  `price` varchar(32) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL,
  `low_bazar_threshold` int(11) NOT NULL DEFAULT 15
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_bazar_items`
--

CREATE TABLE `crm_bazar_items` (
  `id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `unit` varchar(64) DEFAULT '',
  `icon` varchar(191) DEFAULT '',
  `quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `price` decimal(18,2) DEFAULT 0.00,
  `low_threshold` int(11) DEFAULT 0,
  `created_at` int(11) DEFAULT unix_timestamp(),
  `updated_at` int(11) DEFAULT unix_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_bazar_logs`
--

CREATE TABLE `crm_bazar_logs` (
  `id` bigint(20) NOT NULL,
  `date_ts` int(11) NOT NULL,
  `bazar_id` int(11) NOT NULL,
  `type` enum('add','use') NOT NULL,
  `quantity` decimal(18,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(18,2) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_hidden` tinyint(1) DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `created_at` int(11) DEFAULT unix_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (`date_ts`)
(
PARTITION p202501 VALUES LESS THAN (1738346400) ENGINE=InnoDB,
PARTITION p202502 VALUES LESS THAN (1740765600) ENGINE=InnoDB,
PARTITION p202503 VALUES LESS THAN (1743444000) ENGINE=InnoDB,
PARTITION p202504 VALUES LESS THAN (1746036000) ENGINE=InnoDB,
PARTITION p202505 VALUES LESS THAN (1748714400) ENGINE=InnoDB,
PARTITION p202506 VALUES LESS THAN (1751306400) ENGINE=InnoDB,
PARTITION p202507 VALUES LESS THAN (1753984800) ENGINE=InnoDB,
PARTITION p202508 VALUES LESS THAN (1756576800) ENGINE=InnoDB,
PARTITION p202509 VALUES LESS THAN (1759255200) ENGINE=InnoDB,
PARTITION p202510 VALUES LESS THAN (1761847200) ENGINE=InnoDB,
PARTITION p202511 VALUES LESS THAN (1764525600) ENGINE=InnoDB,
PARTITION p202512 VALUES LESS THAN (1767117600) ENGINE=InnoDB,
PARTITION p202601 VALUES LESS THAN (1769796000) ENGINE=InnoDB,
PARTITION p202602 VALUES LESS THAN (1772388000) ENGINE=InnoDB,
PARTITION p202603 VALUES LESS THAN (1775066400) ENGINE=InnoDB,
PARTITION p202604 VALUES LESS THAN (1777744800) ENGINE=InnoDB,
PARTITION p202605 VALUES LESS THAN (1780336800) ENGINE=InnoDB,
PARTITION p202606 VALUES LESS THAN (1783015200) ENGINE=InnoDB,
PARTITION p202607 VALUES LESS THAN (1785607200) ENGINE=InnoDB,
PARTITION p202608 VALUES LESS THAN (1788285600) ENGINE=InnoDB,
PARTITION p202609 VALUES LESS THAN (1790877600) ENGINE=InnoDB,
PARTITION p202610 VALUES LESS THAN (1793556000) ENGINE=InnoDB,
PARTITION p202611 VALUES LESS THAN (1796148000) ENGINE=InnoDB,
PARTITION p202612 VALUES LESS THAN (1798826400) ENGINE=InnoDB,
PARTITION p202701 VALUES LESS THAN (1801418400) ENGINE=InnoDB,
PARTITION p202702 VALUES LESS THAN (1804096800) ENGINE=InnoDB,
PARTITION p202703 VALUES LESS THAN (1806688800) ENGINE=InnoDB,
PARTITION p202704 VALUES LESS THAN (1809367200) ENGINE=InnoDB,
PARTITION p202705 VALUES LESS THAN (1811959200) ENGINE=InnoDB,
PARTITION p202706 VALUES LESS THAN (1814637600) ENGINE=InnoDB,
PARTITION p202707 VALUES LESS THAN (1817229600) ENGINE=InnoDB,
PARTITION p202708 VALUES LESS THAN (1819908000) ENGINE=InnoDB,
PARTITION p202709 VALUES LESS THAN (1822496400) ENGINE=InnoDB,
PARTITION p202710 VALUES LESS THAN (1825088400) ENGINE=InnoDB,
PARTITION p202711 VALUES LESS THAN (1827766800) ENGINE=InnoDB,
PARTITION p202712 VALUES LESS THAN (1830358800) ENGINE=InnoDB,
PARTITION p202801 VALUES LESS THAN (1833037200) ENGINE=InnoDB,
PARTITION p202802 VALUES LESS THAN (1835629200) ENGINE=InnoDB,
PARTITION p202803 VALUES LESS THAN (1838307600) ENGINE=InnoDB,
PARTITION p202804 VALUES LESS THAN (1840899600) ENGINE=InnoDB,
PARTITION p202805 VALUES LESS THAN (1843578000) ENGINE=InnoDB,
PARTITION p202806 VALUES LESS THAN (1846170000) ENGINE=InnoDB,
PARTITION p202807 VALUES LESS THAN (1848848400) ENGINE=InnoDB,
PARTITION p202808 VALUES LESS THAN (1851440400) ENGINE=InnoDB,
PARTITION p202809 VALUES LESS THAN (1854118800) ENGINE=InnoDB,
PARTITION p202810 VALUES LESS THAN (1856710800) ENGINE=InnoDB,
PARTITION p202811 VALUES LESS THAN (1859389200) ENGINE=InnoDB,
PARTITION p202812 VALUES LESS THAN (1861971600) ENGINE=InnoDB,
PARTITION p_future VALUES LESS THAN MAXVALUE ENGINE=InnoDB
);

-- --------------------------------------------------------

--
-- Table structure for table `crm_bazar_price_history`
--

CREATE TABLE `crm_bazar_price_history` (
  `id` int(11) NOT NULL,
  `bazar_id` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `price` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_bazar_quantity_history`
--

CREATE TABLE `crm_bazar_quantity_history` (
  `id` int(11) NOT NULL,
  `bazar_id` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quantity` varchar(32) NOT NULL,
  `used` int(11) NOT NULL,
  `remaining` int(11) NOT NULL,
  `is_hidden` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_bazar_usage_history`
--

CREATE TABLE `crm_bazar_usage_history` (
  `id` int(11) NOT NULL,
  `bazar_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `date` int(11) NOT NULL,
  `quantity` varchar(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_customers`
--

CREATE TABLE `crm_customers` (
  `id` int(11) NOT NULL,
  `name` varchar(300) NOT NULL,
  `phone` varchar(65) NOT NULL,
  `address` text NOT NULL,
  `permanent_addr` text DEFAULT NULL,
  `profession` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `nid` varchar(300) DEFAULT NULL,
  `passport` varchar(300) DEFAULT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  `additional` longtext NOT NULL,
  `additional_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_json`)),
  `spouse_name` varchar(255) DEFAULT NULL,
  `fathers_name` varchar(255) DEFAULT NULL,
  `mothers_name` varchar(255) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_debit`
--

CREATE TABLE `crm_debit` (
  `id` int(11) NOT NULL,
  `name` varchar(300) NOT NULL,
  `particular` varchar(300) NOT NULL,
  `amount` int(11) NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_holidays`
--

CREATE TABLE `crm_holidays` (
  `id` int(11) NOT NULL,
  `holiday` varchar(191) NOT NULL COMMENT 'slug, e.g. new_years_day',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `year` int(4) NOT NULL,
  `full_date` date NOT NULL,
  `type` text DEFAULT NULL COMMENT 'JSON array of types',
  `official` tinyint(1) NOT NULL DEFAULT 0,
  `recurring` tinyint(1) NOT NULL DEFAULT 0,
  `source` varchar(64) DEFAULT 'manual',
  `manual_override` tinyint(1) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `import_meta` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_invoice`
--

CREATE TABLE `crm_invoice` (
  `inv_id` int(11) NOT NULL,
  `purchase_id` varchar(300) NOT NULL,
  `customer_id` varchar(300) NOT NULL,
  `is_booking` int(11) NOT NULL DEFAULT 0,
  `pay_amount` varchar(300) NOT NULL,
  `down_pay` varchar(300) NOT NULL,
  `pay_type` varchar(300) NOT NULL,
  `bank_name` varchar(300) NOT NULL,
  `bank_branch` varchar(300) NOT NULL,
  `remarks` varchar(300) NOT NULL,
  `inv_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_items_master`
--

CREATE TABLE `crm_items_master` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `default_unit` varchar(30) DEFAULT 'pcs',
  `created_at` int(11) DEFAULT unix_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_leads`
--

CREATE TABLE `crm_leads` (
  `lead_id` int(11) NOT NULL,
  `name` varchar(300) NOT NULL,
  `profession` varchar(300) NOT NULL,
  `company` varchar(300) NOT NULL,
  `ad_name` varchar(300) NOT NULL DEFAULT '',
  `email` varchar(300) NOT NULL,
  `source` varchar(150) NOT NULL,
  `project` varchar(120) NOT NULL,
  `phone` varchar(300) NOT NULL,
  `created` int(11) NOT NULL DEFAULT 0,
  `given_date` varchar(11) NOT NULL DEFAULT '0',
  `status` int(11) NOT NULL DEFAULT 0,
  `assigned` int(11) NOT NULL DEFAULT 0,
  `member` int(11) NOT NULL,
  `page_id` varchar(120) NOT NULL,
  `time` int(11) NOT NULL DEFAULT 0,
  `last_activity` int(11) NOT NULL DEFAULT 0,
  `remarks` varchar(300) NOT NULL,
  `quick_remarks` varchar(300) NOT NULL,
  `additional` text NOT NULL,
  `thread_id` bigint(20) NOT NULL DEFAULT 0,
  `viewed` int(11) NOT NULL DEFAULT 0,
  `response` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_leads_report`
--

CREATE TABLE `crm_leads_report` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `date` text NOT NULL,
  `positive` int(11) NOT NULL DEFAULT 0,
  `negative` int(11) NOT NULL DEFAULT 0,
  `visit` int(11) NOT NULL DEFAULT 0,
  `sale` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_lead_reassignments`
--

CREATE TABLE `crm_lead_reassignments` (
  `id` int(11) NOT NULL,
  `lead_id` bigint(20) NOT NULL,
  `project` varchar(120) NOT NULL,
  `from_user` int(11) NOT NULL,
  `to_user` int(11) NOT NULL,
  `mode` enum('punishment','normal','transfer') NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_leaves`
--

CREATE TABLE `crm_leaves` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(300) NOT NULL,
  `reason` varchar(300) NOT NULL,
  `leave_from` int(11) NOT NULL,
  `leave_to` int(11) NOT NULL,
  `days` int(11) NOT NULL DEFAULT 0,
  `is_approved` int(11) NOT NULL DEFAULT 0,
  `is_paid` int(11) NOT NULL DEFAULT 1,
  `posted` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_locations`
--

CREATE TABLE `crm_locations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lat` varchar(32) NOT NULL,
  `lng` varchar(32) NOT NULL,
  `time` int(11) NOT NULL,
  `device_model` varchar(120) NOT NULL,
  `device_id` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_nominees`
--

CREATE TABLE `crm_nominees` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL COMMENT 'crm_customers.id',
  `name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `birthday` int(11) DEFAULT NULL,
  `share_parcent` varchar(32) DEFAULT NULL,
  `relation` varchar(100) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_projects`
--

CREATE TABLE `crm_projects` (
  `id` int(11) NOT NULL,
  `name` varchar(300) NOT NULL,
  `slug` varchar(120) NOT NULL DEFAULT '',
  `type` varchar(120) NOT NULL,
  `progress` varchar(120) NOT NULL,
  `location` varchar(300) NOT NULL,
  `description` text NOT NULL,
  `avatar` varchar(300) NOT NULL,
  `banner` varchar(300) NOT NULL,
  `default_rate` int(11) NOT NULL,
  `active` int(11) NOT NULL DEFAULT 0,
  `additional` longtext NOT NULL,
  `website` varchar(120) NOT NULL,
  `posted` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_punished_users`
--

CREATE TABLE `crm_punished_users` (
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_purchase`
--

CREATE TABLE `crm_purchase` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `katha` int(11) NOT NULL DEFAULT 0,
  `rate` int(11) NOT NULL DEFAULT 0,
  `block` varchar(300) NOT NULL DEFAULT 'a',
  `plot` text NOT NULL,
  `road` text NOT NULL,
  `type` text NOT NULL,
  `facing` text NOT NULL,
  `installment` longtext NOT NULL,
  `p_time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_remarks`
--

CREATE TABLE `crm_remarks` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `remarks` text NOT NULL,
  `time` int(11) NOT NULL DEFAULT 0,
  `remind_at` int(11) NOT NULL DEFAULT 0,
  `is_system` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_rent_report`
--

CREATE TABLE `crm_rent_report` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `visit_date` int(11) NOT NULL,
  `up_time` varchar(120) DEFAULT NULL,
  `down_time` varchar(120) DEFAULT NULL,
  `vendor` varchar(255) DEFAULT 'Office',
  `destination` text NOT NULL,
  `vehcal_type` varchar(120) DEFAULT 'Private Car',
  `bill_date` int(11) DEFAULT NULL,
  `payment` int(11) NOT NULL DEFAULT 0,
  `person` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_salary`
--

CREATE TABLE `crm_salary` (
  `id` int(11) NOT NULL,
  `Badgenumber` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_sms_report`
--

CREATE TABLE `crm_sms_report` (
  `id` int(11) NOT NULL,
  `sms_vendor` varchar(32) NOT NULL DEFAULT 'elitbuzz',
  `sms_id` varchar(300) NOT NULL,
  `senderid` varchar(120) NOT NULL,
  `type` varchar(120) NOT NULL,
  `contacts` varchar(300) NOT NULL,
  `msg` text NOT NULL,
  `cost` text NOT NULL,
  `status` varchar(120) NOT NULL DEFAULT 'Sending',
  `tried` int(11) NOT NULL DEFAULT 0,
  `last_tried` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_stock`
--

CREATE TABLE `crm_stock` (
  `id` int(11) NOT NULL,
  `icon` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(120) NOT NULL,
  `unit` varchar(32) NOT NULL DEFAULT 'pcs',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `price` varchar(32) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 15
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_stock_price_history`
--

CREATE TABLE `crm_stock_price_history` (
  `id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `price` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_stock_quantity_history`
--

CREATE TABLE `crm_stock_quantity_history` (
  `id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `quantity` varchar(32) NOT NULL,
  `used` int(11) NOT NULL,
  `remaining` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_stock_usage_history`
--

CREATE TABLE `crm_stock_usage_history` (
  `id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `date` int(11) NOT NULL,
  `quantity` varchar(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crm_user_capacity`
--

CREATE TABLE `crm_user_capacity` (
  `user_id` int(11) NOT NULL,
  `global_share` float DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `field_positions`
--

CREATE TABLE `field_positions` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `field_name` varchar(255) NOT NULL,
  `style_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`style_json`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sort_order` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_activity_log`
--

CREATE TABLE `fm_activity_log` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL COMMENT 'upload, download, delete, restore, share, edit',
  `details` text DEFAULT NULL COMMENT 'JSON',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_common_folders`
--

CREATE TABLE `fm_common_folders` (
  `id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `folder_key` varchar(100) NOT NULL,
  `folder_path` varchar(512) NOT NULL,
  `folder_icon` varchar(50) DEFAULT 'bi-folder',
  `folder_color` varchar(20) DEFAULT '#3b82f6',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `read_only` tinyint(1) DEFAULT 0 COMMENT 'If true, only admins can upload',
  `max_file_size_mb` int(11) DEFAULT NULL COMMENT 'Max file size in MB, NULL = no limit',
  `allowed_extensions` text DEFAULT NULL COMMENT 'JSON array of allowed extensions',
  `total_files` int(11) DEFAULT 0,
  `total_size_bytes` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_files`
--

CREATE TABLE `fm_files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_folder_id` int(11) DEFAULT NULL,
  `filename` varchar(512) NOT NULL,
  `original_filename` varchar(512) NOT NULL,
  `path` varchar(1024) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `size` bigint(20) DEFAULT 0,
  `is_folder` tinyint(1) DEFAULT 0,
  `is_global` tinyint(1) DEFAULT 0 COMMENT 'Global folders visible to all',
  `r2_key` varchar(1024) DEFAULT NULL COMMENT 'R2 storage key',
  `r2_uploaded` tinyint(1) DEFAULT 0,
  `r2_uploaded_at` datetime DEFAULT NULL,
  `checksum` varchar(64) DEFAULT NULL COMMENT 'MD5 or SHA256',
  `version` int(11) DEFAULT 1,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `folder_type` enum('user','common','special') DEFAULT 'user',
  `thumbnail_generated` tinyint(1) DEFAULT 0,
  `version_count` int(11) DEFAULT 0,
  `current_version` int(11) DEFAULT 1,
  `special_folder_id` int(11) DEFAULT NULL,
  `common_folder_id` int(11) DEFAULT NULL,
  `storage_type` enum('user','common','special','system') DEFAULT 'user' COMMENT 'Storage location type',
  `storage_folder_id` int(11) DEFAULT NULL COMMENT 'References fm_folder_structure or common/special folders',
  `is_in_user_storage` tinyint(1) DEFAULT 0 COMMENT 'True if in /Storage/{user_id}/ path',
  `relative_path` varchar(1024) DEFAULT NULL COMMENT 'Path relative to storage root'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_file_shares`
--

CREATE TABLE `fm_file_shares` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `shared_by` int(11) NOT NULL,
  `shared_with` int(11) DEFAULT NULL COMMENT 'NULL = public link share',
  `share_type` enum('private','link','public') DEFAULT 'private',
  `permission` enum('view','edit','download') DEFAULT 'view',
  `share_token` varchar(64) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `max_downloads` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL,
  `last_accessed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_file_versions`
--

CREATE TABLE `fm_file_versions` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL DEFAULT 1,
  `filename` varchar(512) NOT NULL,
  `path` varchar(1024) NOT NULL,
  `size` bigint(20) NOT NULL DEFAULT 0,
  `checksum` varchar(64) DEFAULT NULL,
  `r2_key` varchar(1024) DEFAULT NULL,
  `r2_uploaded` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `comment` text DEFAULT NULL,
  `is_deletable` tinyint(1) DEFAULT 0 COMMENT 'Versions are protected from deletion'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_folder_access`
--

CREATE TABLE `fm_folder_access` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `folder_type` enum('special','common') DEFAULT 'special',
  `user_id` int(11) NOT NULL,
  `permission_level` enum('view','edit','admin') DEFAULT 'view',
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_folder_structure`
--

CREATE TABLE `fm_folder_structure` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL for system folders',
  `folder_name` varchar(255) NOT NULL,
  `folder_path` varchar(1024) NOT NULL,
  `folder_type` enum('user','common','special','system') DEFAULT 'user',
  `parent_id` int(11) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Default subfolders like Documents, Images, etc.',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_permissions`
--

CREATE TABLE `fm_permissions` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL = all users',
  `permission` enum('view','edit','delete','admin') DEFAULT 'view',
  `granted_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_recycle_bin`
--

CREATE TABLE `fm_recycle_bin` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `original_path` varchar(1024) NOT NULL,
  `filename` varchar(512) NOT NULL,
  `size` bigint(20) DEFAULT 0,
  `deleted_at` datetime NOT NULL,
  `auto_delete_at` datetime NOT NULL COMMENT '30 days from deleted_at',
  `restored_at` datetime DEFAULT NULL,
  `force_deleted_at` datetime DEFAULT NULL,
  `force_deleted_by` int(11) DEFAULT NULL COMMENT 'Admin who force deleted',
  `can_restore` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_special_folders`
--

CREATE TABLE `fm_special_folders` (
  `id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `folder_key` varchar(100) NOT NULL,
  `folder_path` varchar(512) NOT NULL,
  `folder_icon` varchar(50) DEFAULT 'bi-folder-lock',
  `folder_color` varchar(20) DEFAULT '#ef4444',
  `description` text DEFAULT NULL,
  `requires_permission` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `auto_assign_roles` text DEFAULT NULL COMMENT 'JSON array of role IDs that get auto access',
  `max_file_size_mb` int(11) DEFAULT NULL,
  `allowed_extensions` text DEFAULT NULL COMMENT 'JSON array of allowed extensions',
  `total_files` int(11) DEFAULT 0,
  `total_size_bytes` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_system_settings`
--

CREATE TABLE `fm_system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` varchar(50) DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_thumbnails`
--

CREATE TABLE `fm_thumbnails` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `thumbnail_path` varchar(512) NOT NULL,
  `thumbnail_size` varchar(20) DEFAULT 'medium' COMMENT 'small, medium, large',
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `r2_key` varchar(1024) DEFAULT NULL,
  `r2_uploaded` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_upload_queue`
--

CREATE TABLE `fm_upload_queue` (
  `id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `local_path` varchar(1024) NOT NULL,
  `remote_key` varchar(1024) NOT NULL,
  `status` enum('pending','processing','done','error') DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_user_quotas`
--

CREATE TABLE `fm_user_quotas` (
  `user_id` int(11) NOT NULL,
  `quota_bytes` bigint(20) NOT NULL DEFAULT 1073741824 COMMENT '1GB default',
  `used_bytes` bigint(20) NOT NULL DEFAULT 0,
  `total_files` int(11) DEFAULT 0,
  `total_folders` int(11) DEFAULT 0,
  `r2_uploaded_bytes` bigint(20) DEFAULT 0,
  `local_only_bytes` bigint(20) DEFAULT 0,
  `last_upload_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fm_user_storage_tracking`
--

CREATE TABLE `fm_user_storage_tracking` (
  `user_id` int(11) NOT NULL,
  `total_files` int(11) DEFAULT 0,
  `total_folders` int(11) DEFAULT 0,
  `used_bytes` bigint(20) DEFAULT 0,
  `quota_bytes` bigint(20) DEFAULT 1073741824 COMMENT '1 GB default',
  `r2_uploaded_bytes` bigint(20) DEFAULT 0,
  `local_only_bytes` bigint(20) DEFAULT 0,
  `last_calculated_at` datetime DEFAULT NULL,
  `last_upload_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `home_slider`
--

CREATE TABLE `home_slider` (
  `id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 999,
  `name` text NOT NULL,
  `image` text NOT NULL,
  `product` text NOT NULL,
  `featured` int(11) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `key_locations`
--

CREATE TABLE `key_locations` (
  `id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 999,
  `name` text NOT NULL,
  `distance` text NOT NULL,
  `image` text NOT NULL,
  `product` text NOT NULL,
  `featured` int(11) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` varchar(300) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `url` varchar(300) NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications_views`
--

CREATE TABLE `notifications_views` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` varchar(190) NOT NULL,
  `notif_id` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offers`
--

CREATE TABLE `offers` (
  `id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL DEFAULT '',
  `content` longtext NOT NULL,
  `thumbnail` varchar(300) NOT NULL,
  `active` varchar(11) NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `photo_gallery`
--

CREATE TABLE `photo_gallery` (
  `id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 999,
  `name` text NOT NULL,
  `image` text NOT NULL,
  `product` text NOT NULL,
  `featured` int(11) NOT NULL,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restore_history`
--

CREATE TABLE `restore_history` (
  `id` int(11) NOT NULL,
  `backup_id` int(11) DEFAULT NULL,
  `backup_filename` varchar(512) NOT NULL,
  `restored_from` enum('local','r2') DEFAULT 'local',
  `target_database` varchar(255) DEFAULT NULL,
  `status` enum('pending','inprogress','completed','failed') DEFAULT 'pending',
  `tables_restored` text DEFAULT NULL COMMENT 'JSON array',
  `error_message` text DEFAULT NULL,
  `restored_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wondertage_settings`
--

CREATE TABLE `wondertage_settings` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` varchar(20000) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_activities`
--

CREATE TABLE `wo_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `reply_id` int(10) UNSIGNED DEFAULT 0,
  `comment_id` int(10) UNSIGNED DEFAULT 0,
  `follow_id` int(11) NOT NULL DEFAULT 0,
  `activity_type` varchar(32) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_admininvitations`
--

CREATE TABLE `wo_admininvitations` (
  `id` int(11) NOT NULL,
  `code` varchar(300) NOT NULL DEFAULT '0',
  `posted` varchar(50) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_ads`
--

CREATE TABLE `wo_ads` (
  `id` int(11) NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT '',
  `code` text DEFAULT NULL,
  `active` enum('0','1') NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_affiliates_requests`
--

CREATE TABLE `wo_affiliates_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `amount` varchar(100) NOT NULL DEFAULT '0',
  `full_amount` varchar(100) NOT NULL DEFAULT '',
  `iban` varchar(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `country` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `full_name` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `swift_code` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `address` varchar(600) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `transfer_info` varchar(600) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `status` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_agoravideocall`
--

CREATE TABLE `wo_agoravideocall` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL DEFAULT 0,
  `to_id` int(11) NOT NULL DEFAULT 0,
  `type` varchar(50) NOT NULL DEFAULT 'video',
  `room_name` varchar(50) NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 0,
  `called` int(11) NOT NULL DEFAULT 0,
  `declined` int(11) NOT NULL DEFAULT 0,
  `access_token` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `access_token_2` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_albums_media`
--

CREATE TABLE `wo_albums_media` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `review_id` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_announcement`
--

CREATE TABLE `wo_announcement` (
  `id` int(11) NOT NULL,
  `text` text DEFAULT NULL,
  `time` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_announcement_views`
--

CREATE TABLE `wo_announcement_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `announcement_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_apps`
--

CREATE TABLE `wo_apps` (
  `id` int(11) NOT NULL,
  `app_user_id` int(11) NOT NULL DEFAULT 0,
  `app_name` varchar(32) NOT NULL,
  `app_website_url` varchar(55) NOT NULL,
  `app_description` text NOT NULL,
  `app_avatar` varchar(100) NOT NULL DEFAULT 'upload/photos/app-default-icon.png',
  `app_callback_url` varchar(255) NOT NULL,
  `app_id` varchar(32) NOT NULL,
  `app_secret` varchar(55) NOT NULL,
  `active` enum('0','1') NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_appssessions`
--

CREATE TABLE `wo_appssessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `session_id` varchar(120) NOT NULL DEFAULT '',
  `platform` varchar(32) NOT NULL DEFAULT '',
  `platform_details` text DEFAULT NULL,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_apps_hash`
--

CREATE TABLE `wo_apps_hash` (
  `id` int(11) NOT NULL,
  `hash_id` varchar(200) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `active` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_apps_permission`
--

CREATE TABLE `wo_apps_permission` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `app_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_audiocalls`
--

CREATE TABLE `wo_audiocalls` (
  `id` int(11) NOT NULL,
  `call_id` varchar(30) NOT NULL DEFAULT '0',
  `access_token` text DEFAULT NULL,
  `call_id_2` varchar(30) NOT NULL DEFAULT '',
  `access_token_2` text DEFAULT NULL,
  `from_id` int(11) NOT NULL DEFAULT 0,
  `to_id` int(11) NOT NULL DEFAULT 0,
  `room_name` varchar(50) NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 0,
  `called` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `declined` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_backup_codes`
--

CREATE TABLE `wo_backup_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `codes` varchar(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_bad_login`
--

CREATE TABLE `wo_bad_login` (
  `id` int(11) NOT NULL,
  `ip` varchar(100) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_banned_ip`
--

CREATE TABLE `wo_banned_ip` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(100) NOT NULL DEFAULT '',
  `reason` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blocks`
--

CREATE TABLE `wo_blocks` (
  `id` int(11) NOT NULL,
  `blocker` int(11) NOT NULL DEFAULT 0,
  `blocked` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blog`
--

CREATE TABLE `wo_blog` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL DEFAULT 0,
  `title` varchar(120) NOT NULL DEFAULT '',
  `content` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `posted` varchar(300) DEFAULT '0',
  `category` int(11) DEFAULT 0,
  `thumbnail` varchar(100) DEFAULT 'upload/photos/d-blog.jpg',
  `view` int(11) DEFAULT 0,
  `shared` int(11) DEFAULT 0,
  `tags` varchar(300) DEFAULT '',
  `active` enum('0','1') NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blogcommentreplies`
--

CREATE TABLE `wo_blogcommentreplies` (
  `id` int(11) NOT NULL,
  `comm_id` int(11) NOT NULL DEFAULT 0,
  `blog_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `posted` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blogcomments`
--

CREATE TABLE `wo_blogcomments` (
  `id` int(11) NOT NULL,
  `blog_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `posted` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blogmoviedislikes`
--

CREATE TABLE `wo_blogmoviedislikes` (
  `id` int(11) NOT NULL,
  `blog_comm_id` int(11) NOT NULL DEFAULT 0,
  `blog_commreply_id` int(11) NOT NULL DEFAULT 0,
  `movie_comm_id` int(11) NOT NULL DEFAULT 0,
  `movie_commreply_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `blog_id` int(11) NOT NULL DEFAULT 0,
  `movie_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blogmovielikes`
--

CREATE TABLE `wo_blogmovielikes` (
  `id` int(11) NOT NULL,
  `blog_comm_id` int(11) NOT NULL DEFAULT 0,
  `blog_commreply_id` int(11) NOT NULL DEFAULT 0,
  `movie_comm_id` int(11) NOT NULL DEFAULT 0,
  `movie_commreply_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `blog_id` int(11) NOT NULL DEFAULT 0,
  `movie_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blogs_categories`
--

CREATE TABLE `wo_blogs_categories` (
  `id` int(11) NOT NULL,
  `lang_key` varchar(160) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_blog_reaction`
--

CREATE TABLE `wo_blog_reaction` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `blog_id` int(11) NOT NULL DEFAULT 0,
  `comment_id` int(11) NOT NULL DEFAULT 0,
  `reply_id` int(11) NOT NULL DEFAULT 0,
  `reaction` varchar(50) NOT NULL DEFAULT '',
  `time` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_booking`
--

CREATE TABLE `wo_booking` (
  `id` int(11) NOT NULL,
  `project` varchar(300) NOT NULL,
  `block` varchar(11) NOT NULL,
  `road` text NOT NULL,
  `plot` varchar(120) DEFAULT '0',
  `katha` varchar(120) NOT NULL DEFAULT '0',
  `facing` text NOT NULL,
  `file_num` text NOT NULL,
  `object_id` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_booking_helper`
--

CREATE TABLE `wo_booking_helper` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL DEFAULT 0,
  `client_id` varchar(32) NOT NULL DEFAULT '0',
  `file_num` varchar(32) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL,
  `nominee_ids` longtext DEFAULT NULL COMMENT 'JSON array of crm_nominees ids, e.g. [23,45]',
  `per_katha` decimal(12,2) DEFAULT NULL,
  `booking_money` decimal(12,2) NOT NULL,
  `down_payment` decimal(12,2) NOT NULL,
  `installment` longtext DEFAULT NULL COMMENT 'serialized or JSON installment payload',
  `cancel_date` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_career`
--

CREATE TABLE `wo_career` (
  `id` int(11) NOT NULL,
  `name` varchar(300) NOT NULL DEFAULT '',
  `phone` varchar(300) NOT NULL DEFAULT '',
  `position` varchar(300) NOT NULL DEFAULT '',
  `sub_position` varchar(300) NOT NULL DEFAULT '',
  `email` varchar(300) NOT NULL DEFAULT '',
  `message` varchar(300) NOT NULL DEFAULT '',
  `postPhotos` varchar(300) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_codes`
--

CREATE TABLE `wo_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL DEFAULT '',
  `app_id` varchar(50) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_colored_posts`
--

CREATE TABLE `wo_colored_posts` (
  `id` int(11) NOT NULL,
  `color_1` varchar(50) NOT NULL DEFAULT '',
  `color_2` varchar(50) NOT NULL DEFAULT '',
  `text_color` varchar(50) NOT NULL DEFAULT '',
  `image` varchar(250) NOT NULL DEFAULT '',
  `time` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_commentlikes`
--

CREATE TABLE `wo_commentlikes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `comment_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_comments`
--

CREATE TABLE `wo_comments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record` varchar(255) NOT NULL DEFAULT '',
  `c_file` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_commentwonders`
--

CREATE TABLE `wo_commentwonders` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `comment_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_comment_replies`
--

CREATE TABLE `wo_comment_replies` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `c_file` varchar(300) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_comment_replies_likes`
--

CREATE TABLE `wo_comment_replies_likes` (
  `id` int(11) NOT NULL,
  `reply_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_comment_replies_wonders`
--

CREATE TABLE `wo_comment_replies_wonders` (
  `id` int(11) NOT NULL,
  `reply_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_config`
--

CREATE TABLE `wo_config` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `value` varchar(20000) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_custompages`
--

CREATE TABLE `wo_custompages` (
  `id` int(11) NOT NULL,
  `page_name` varchar(50) NOT NULL DEFAULT '',
  `page_title` varchar(200) NOT NULL DEFAULT '',
  `page_content` text DEFAULT NULL,
  `page_type` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_custom_fields`
--

CREATE TABLE `wo_custom_fields` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT '',
  `length` int(11) NOT NULL DEFAULT 0,
  `placement` varchar(50) NOT NULL DEFAULT '',
  `required` varchar(11) NOT NULL DEFAULT 'on',
  `options` text DEFAULT NULL,
  `active` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_egoing`
--

CREATE TABLE `wo_egoing` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_einterested`
--

CREATE TABLE `wo_einterested` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_einvited`
--

CREATE TABLE `wo_einvited` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `inviter_id` int(11) NOT NULL,
  `invited_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_emails`
--

CREATE TABLE `wo_emails` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `email_to` varchar(50) NOT NULL DEFAULT '',
  `subject` varchar(32) NOT NULL DEFAULT '',
  `message` text DEFAULT NULL,
  `project` varchar(300) NOT NULL,
  `name` varchar(300) NOT NULL,
  `katha` varchar(300) NOT NULL,
  `phone` varchar(300) NOT NULL,
  `email` varchar(300) NOT NULL,
  `address` varchar(300) NOT NULL,
  `profession` varchar(300) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_events`
--

CREATE TABLE `wo_events` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT '',
  `location` varchar(300) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `start_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_date` date NOT NULL,
  `end_time` time NOT NULL,
  `poster_id` int(11) NOT NULL,
  `cover` varchar(500) NOT NULL DEFAULT 'upload/photos/d-cover.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_family`
--

CREATE TABLE `wo_family` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `member` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `requesting` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_followers`
--

CREATE TABLE `wo_followers` (
  `id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL DEFAULT 0,
  `follower_id` int(11) NOT NULL DEFAULT 0,
  `is_typing` int(11) NOT NULL DEFAULT 0,
  `active` int(11) NOT NULL DEFAULT 1,
  `notify` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_forums`
--

CREATE TABLE `wo_forums` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL DEFAULT '',
  `description` varchar(300) NOT NULL DEFAULT '',
  `sections` int(11) NOT NULL DEFAULT 0,
  `posts` int(11) NOT NULL DEFAULT 0,
  `last_post` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_forumthreadreplies`
--

CREATE TABLE `wo_forumthreadreplies` (
  `id` int(11) NOT NULL,
  `thread_id` int(11) NOT NULL DEFAULT 0,
  `forum_id` int(11) NOT NULL DEFAULT 0,
  `poster_id` int(11) NOT NULL DEFAULT 0,
  `post_subject` varchar(300) NOT NULL DEFAULT '',
  `post_text` text NOT NULL,
  `post_quoted` int(11) NOT NULL DEFAULT 0,
  `posted_time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_forum_sections`
--

CREATE TABLE `wo_forum_sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(200) NOT NULL DEFAULT '',
  `description` varchar(300) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_forum_threads`
--

CREATE TABLE `wo_forum_threads` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `headline` varchar(300) NOT NULL DEFAULT '',
  `post` text NOT NULL,
  `posted` varchar(20) NOT NULL DEFAULT '0',
  `last_post` int(11) NOT NULL DEFAULT 0,
  `forum` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_funding`
--

CREATE TABLE `wo_funding` (
  `id` int(11) NOT NULL,
  `hashed_id` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `amount` varchar(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `image` varchar(200) NOT NULL DEFAULT '',
  `time` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_funding_raise`
--

CREATE TABLE `wo_funding_raise` (
  `id` int(11) NOT NULL,
  `funding_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `amount` varchar(11) NOT NULL DEFAULT '0',
  `time` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_games`
--

CREATE TABLE `wo_games` (
  `id` int(11) NOT NULL,
  `game_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `game_avatar` varchar(100) NOT NULL,
  `game_link` varchar(100) NOT NULL,
  `active` enum('0','1') NOT NULL DEFAULT '1',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_games_players`
--

CREATE TABLE `wo_games_players` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `game_id` int(11) NOT NULL DEFAULT 0,
  `last_play` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_gender`
--

CREATE TABLE `wo_gender` (
  `id` int(11) NOT NULL,
  `gender_id` varchar(50) NOT NULL DEFAULT '0',
  `image` varchar(300) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_gifts`
--

CREATE TABLE `wo_gifts` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(250) DEFAULT NULL,
  `media_file` varchar(250) NOT NULL,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_groupadmins`
--

CREATE TABLE `wo_groupadmins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `general` int(11) NOT NULL DEFAULT 1,
  `privacy` int(11) NOT NULL DEFAULT 1,
  `avatar` int(11) NOT NULL DEFAULT 1,
  `members` int(11) NOT NULL DEFAULT 0,
  `analytics` int(11) NOT NULL DEFAULT 1,
  `delete_group` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_groupchat`
--

CREATE TABLE `wo_groupchat` (
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `avatar` varchar(3000) NOT NULL DEFAULT 'upload/photos/d-group.jpg',
  `time` varchar(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_groupchatusers`
--

CREATE TABLE `wo_groupchatusers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `active` enum('1','0') NOT NULL DEFAULT '1',
  `last_seen` varchar(50) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_groups`
--

CREATE TABLE `wo_groups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `group_name` varchar(32) NOT NULL DEFAULT '',
  `group_title` varchar(40) NOT NULL DEFAULT '',
  `avatar` varchar(120) NOT NULL DEFAULT 'upload/photos/d-group.jpg ',
  `cover` varchar(120) NOT NULL DEFAULT 'upload/photos/d-cover.jpg  ',
  `about` varchar(550) NOT NULL DEFAULT '',
  `category` int(11) NOT NULL DEFAULT 1,
  `sub_category` varchar(50) NOT NULL DEFAULT '',
  `privacy` enum('1','2') NOT NULL DEFAULT '1',
  `join_privacy` enum('1','2') NOT NULL DEFAULT '1',
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `registered` varchar(32) NOT NULL DEFAULT '0/0000',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_groups_categories`
--

CREATE TABLE `wo_groups_categories` (
  `id` int(11) NOT NULL,
  `lang_key` varchar(160) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_group_members`
--

CREATE TABLE `wo_group_members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_hashtags`
--

CREATE TABLE `wo_hashtags` (
  `id` int(11) NOT NULL,
  `hash` varchar(255) NOT NULL DEFAULT '',
  `tag` varchar(255) NOT NULL DEFAULT '',
  `last_trend_time` int(11) NOT NULL DEFAULT 0,
  `trend_use_num` int(11) NOT NULL DEFAULT 0,
  `expire` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_hiddenposts`
--

CREATE TABLE `wo_hiddenposts` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_html_emails`
--

CREATE TABLE `wo_html_emails` (
  `id` int(11) NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_invitation_links`
--

CREATE TABLE `wo_invitation_links` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `invited_id` int(11) NOT NULL DEFAULT 0,
  `code` varchar(300) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_job`
--

CREATE TABLE `wo_job` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `title` varchar(200) NOT NULL DEFAULT '',
  `location` varchar(100) NOT NULL DEFAULT '',
  `lat` varchar(50) NOT NULL DEFAULT '',
  `lng` varchar(50) NOT NULL DEFAULT '',
  `minimum` varchar(50) NOT NULL DEFAULT '0',
  `maximum` varchar(50) NOT NULL DEFAULT '0',
  `salary_date` varchar(50) NOT NULL DEFAULT '',
  `job_type` varchar(50) NOT NULL DEFAULT '',
  `category` varchar(50) NOT NULL DEFAULT '',
  `question_one` varchar(200) NOT NULL DEFAULT '',
  `question_one_type` varchar(100) NOT NULL DEFAULT '',
  `question_one_answers` text DEFAULT NULL,
  `question_two` varchar(200) NOT NULL DEFAULT '',
  `question_two_type` varchar(100) NOT NULL DEFAULT '',
  `question_two_answers` text DEFAULT NULL,
  `question_three` varchar(200) NOT NULL DEFAULT '',
  `question_three_type` varchar(100) NOT NULL DEFAULT '',
  `question_three_answers` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(300) NOT NULL DEFAULT '',
  `image_type` varchar(11) NOT NULL DEFAULT '',
  `currency` varchar(11) NOT NULL DEFAULT '0',
  `status` int(11) NOT NULL DEFAULT 1,
  `time` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_job_apply`
--

CREATE TABLE `wo_job_apply` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `job_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `user_name` varchar(100) NOT NULL DEFAULT '',
  `phone_number` varchar(50) NOT NULL DEFAULT '',
  `location` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `question_one_answer` varchar(200) NOT NULL DEFAULT '',
  `question_two_answer` varchar(200) NOT NULL DEFAULT '',
  `question_three_answer` varchar(200) NOT NULL DEFAULT '',
  `position` varchar(100) NOT NULL DEFAULT '',
  `where_did_you_work` varchar(100) NOT NULL DEFAULT '',
  `experience_description` varchar(300) NOT NULL DEFAULT '',
  `experience_start_date` varchar(50) NOT NULL DEFAULT '',
  `experience_end_date` varchar(50) NOT NULL DEFAULT '',
  `time` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_job_categories`
--

CREATE TABLE `wo_job_categories` (
  `id` int(11) NOT NULL,
  `lang_key` varchar(160) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_langiso`
--

CREATE TABLE `wo_langiso` (
  `id` int(11) NOT NULL,
  `lang_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `iso` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `image` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `direction` varchar(50) NOT NULL DEFAULT 'ltr'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_langs`
--

CREATE TABLE `wo_langs` (
  `id` int(11) NOT NULL,
  `lang_key` varchar(160) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `type` varchar(100) NOT NULL DEFAULT '',
  `english` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `arabic` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `dutch` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `french` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `german` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `italian` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `portuguese` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `russian` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `spanish` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `turkish` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `hindi` longtext DEFAULT NULL,
  `chinese` longtext DEFAULT NULL,
  `urdu` longtext DEFAULT NULL,
  `indonesian` longtext DEFAULT NULL,
  `croatian` longtext DEFAULT NULL,
  `hebrew` longtext DEFAULT NULL,
  `bengali` longtext DEFAULT NULL,
  `japanese` longtext DEFAULT NULL,
  `persian` longtext DEFAULT NULL,
  `swedish` longtext DEFAULT NULL,
  `vietnamese` longtext DEFAULT NULL,
  `danish` longtext DEFAULT NULL,
  `filipino` longtext DEFAULT NULL,
  `korean` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_likes`
--

CREATE TABLE `wo_likes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_live_sub_users`
--

CREATE TABLE `wo_live_sub_users` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `is_watching` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_manage_pro`
--

CREATE TABLE `wo_manage_pro` (
  `id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL DEFAULT '',
  `price` varchar(11) NOT NULL DEFAULT '0',
  `featured_member` int(11) NOT NULL DEFAULT 0,
  `profile_visitors` int(11) NOT NULL DEFAULT 0,
  `last_seen` int(11) NOT NULL DEFAULT 0,
  `verified_badge` int(11) NOT NULL DEFAULT 0,
  `posts_promotion` int(11) NOT NULL DEFAULT 0,
  `pages_promotion` int(11) NOT NULL DEFAULT 0,
  `discount` text NOT NULL,
  `image` varchar(300) NOT NULL DEFAULT '',
  `night_image` varchar(300) NOT NULL DEFAULT '',
  `color` varchar(50) NOT NULL DEFAULT '#fafafa',
  `description` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `time` varchar(20) NOT NULL DEFAULT 'week',
  `time_count` int(11) NOT NULL DEFAULT 0,
  `max_upload` varchar(100) NOT NULL DEFAULT '96000000',
  `features` varchar(800) DEFAULT '{"can_use_funding":1,"can_use_jobs":1,"can_use_games":1,"can_use_market":1,"can_use_events":1,"can_use_forum":1,"can_use_groups":1,"can_use_pages":1,"can_use_audio_call":1,"can_use_video_call":1,"can_use_offer":1,"can_use_blog":1,"can_use_movies":1,"can_use_story":1,"can_use_stickers":1,"can_use_gif":1,"can_use_gift":1,"can_use_nearby":1,"can_use_video_upload":1,"can_use_audio_upload":1,"can_use_shout_box":1,"can_use_colored_posts":1,"can_use_poll":1,"can_use_live":1,"can_use_background":1,"can_use_chat":1,"can_use_ai_image":1,"can_use_ai_post":1,"can_use_ai_user":1,"can_use_ai_blog":1}'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_messages`
--

CREATE TABLE `wo_messages` (
  `id` bigint(20) NOT NULL,
  `from_id` bigint(20) NOT NULL DEFAULT 0,
  `thread_id` bigint(20) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `page_id` bigint(20) NOT NULL DEFAULT 0,
  `to_id` bigint(20) NOT NULL DEFAULT 0,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media` varchar(255) NOT NULL DEFAULT '',
  `mediaFileName` varchar(200) NOT NULL DEFAULT '',
  `mediaFileNames` varchar(200) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `seen` int(11) NOT NULL DEFAULT 0,
  `deleted_one` enum('0','1') NOT NULL DEFAULT '0',
  `deleted_two` enum('0','1') NOT NULL DEFAULT '0',
  `sent_push` int(11) NOT NULL DEFAULT 0,
  `notification_id` varchar(50) NOT NULL DEFAULT '',
  `type_two` varchar(32) NOT NULL DEFAULT '',
  `stickers` text DEFAULT NULL,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `lat` varchar(200) NOT NULL DEFAULT '0',
  `lng` varchar(200) NOT NULL DEFAULT '0',
  `reply_id` int(11) NOT NULL DEFAULT 0,
  `story_id` int(11) NOT NULL DEFAULT 0,
  `broadcast_id` int(11) NOT NULL DEFAULT 0,
  `forward` int(11) NOT NULL DEFAULT 0,
  `listening` int(11) NOT NULL DEFAULT 0,
  `msg_id` varchar(300) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_messages_assign`
--

CREATE TABLE `wo_messages_assign` (
  `id` int(11) NOT NULL,
  `thread_id` varchar(120) NOT NULL,
  `user_id` int(11) NOT NULL,
  `response_rate` int(11) NOT NULL DEFAULT 0,
  `is_phone` enum('0','1') NOT NULL DEFAULT '0',
  `is_good` enum('0','1','2') NOT NULL DEFAULT '0',
  `msg_type` enum('hotline','get_msg','lead','unknown') NOT NULL DEFAULT 'unknown',
  `push_send` enum('0','1') NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_messages_meta`
--

CREATE TABLE `wo_messages_meta` (
  `id` int(11) NOT NULL,
  `thread_id` varchar(120) NOT NULL,
  `name` varchar(120) NOT NULL,
  `value` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_moviecommentreplies`
--

CREATE TABLE `wo_moviecommentreplies` (
  `id` int(11) NOT NULL,
  `comm_id` int(11) NOT NULL DEFAULT 0,
  `movie_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `posted` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_moviecomments`
--

CREATE TABLE `wo_moviecomments` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posted` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_movies`
--

CREATE TABLE `wo_movies` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '',
  `genre` varchar(50) NOT NULL DEFAULT '',
  `stars` varchar(300) NOT NULL DEFAULT '',
  `producer` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(50) NOT NULL DEFAULT '',
  `release` year(4) DEFAULT NULL,
  `quality` varchar(10) DEFAULT '',
  `duration` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `cover` varchar(500) NOT NULL DEFAULT 'upload/photos/d-film.jpg',
  `source` varchar(1000) NOT NULL DEFAULT '',
  `iframe` varchar(1000) NOT NULL DEFAULT '',
  `video` varchar(3000) NOT NULL DEFAULT '',
  `views` int(11) NOT NULL DEFAULT 0,
  `rating` varchar(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_mute`
--

CREATE TABLE `wo_mute` (
  `id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL DEFAULT 0,
  `message_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `notify` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'yes',
  `call_chat` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'yes',
  `archive` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'no',
  `pin` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'no',
  `fav` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'no',
  `type` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_notifications`
--

CREATE TABLE `wo_notifications` (
  `id` int(11) NOT NULL,
  `notifier_id` int(11) NOT NULL DEFAULT 0,
  `recipient_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `reply_id` int(10) UNSIGNED DEFAULT 0,
  `comment_id` int(10) UNSIGNED DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `group_chat_id` int(11) NOT NULL DEFAULT 0,
  `event_id` int(11) NOT NULL DEFAULT 0,
  `thread_id` int(11) NOT NULL DEFAULT 0,
  `blog_id` int(11) NOT NULL DEFAULT 0,
  `story_id` int(11) NOT NULL DEFAULT 0,
  `seen_pop` int(11) NOT NULL DEFAULT 0,
  `type` varchar(255) NOT NULL DEFAULT '',
  `type2` varchar(32) NOT NULL DEFAULT '',
  `text` text DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `full_link` varchar(1000) NOT NULL DEFAULT '',
  `seen` int(11) NOT NULL DEFAULT 0,
  `sent_push` int(11) NOT NULL DEFAULT 0,
  `admin` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_offers`
--

CREATE TABLE `wo_offers` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `discount_type` varchar(200) NOT NULL DEFAULT '',
  `discount_percent` int(11) NOT NULL DEFAULT 0,
  `discount_amount` int(11) NOT NULL DEFAULT 0,
  `discounted_items` varchar(150) DEFAULT '',
  `buy` int(11) NOT NULL DEFAULT 0,
  `get_price` int(11) NOT NULL DEFAULT 0,
  `spend` int(11) NOT NULL DEFAULT 0,
  `amount_off` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `expire_date` date NOT NULL,
  `expire_time` time NOT NULL,
  `image` varchar(300) NOT NULL DEFAULT '',
  `currency` varchar(50) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pageadmins`
--

CREATE TABLE `wo_pageadmins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `general` int(11) NOT NULL DEFAULT 1,
  `info` int(11) NOT NULL DEFAULT 1,
  `social` int(11) NOT NULL DEFAULT 1,
  `avatar` int(11) NOT NULL DEFAULT 1,
  `design` int(11) NOT NULL DEFAULT 1,
  `admins` int(11) NOT NULL DEFAULT 0,
  `analytics` int(11) NOT NULL DEFAULT 1,
  `delete_page` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pagerating`
--

CREATE TABLE `wo_pagerating` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `valuation` int(11) DEFAULT 0,
  `review` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pages`
--

CREATE TABLE `wo_pages` (
  `page_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL DEFAULT 0,
  `page_name` varchar(32) NOT NULL DEFAULT '',
  `page_title` varchar(32) NOT NULL DEFAULT '',
  `page_description` varchar(1000) NOT NULL DEFAULT '',
  `avatar` varchar(255) NOT NULL DEFAULT 'upload/photos/d-page.jpg',
  `cover` varchar(255) NOT NULL DEFAULT 'upload/photos/d-cover.jpg',
  `users_post` int(11) NOT NULL DEFAULT 0,
  `page_category` int(11) NOT NULL DEFAULT 1,
  `sub_category` varchar(50) NOT NULL DEFAULT '',
  `website` varchar(255) NOT NULL DEFAULT '',
  `facebook` varchar(32) NOT NULL DEFAULT '',
  `google` varchar(32) NOT NULL DEFAULT '',
  `vk` varchar(32) NOT NULL DEFAULT '',
  `twitter` varchar(32) NOT NULL DEFAULT '',
  `linkedin` varchar(32) NOT NULL DEFAULT '',
  `company` varchar(32) NOT NULL DEFAULT '',
  `phone` varchar(32) NOT NULL DEFAULT '',
  `address` varchar(100) NOT NULL DEFAULT '',
  `call_action_type` int(11) NOT NULL DEFAULT 0,
  `call_action_type_url` varchar(255) NOT NULL DEFAULT '',
  `background_image` varchar(200) NOT NULL DEFAULT '',
  `background_image_status` int(11) NOT NULL DEFAULT 0,
  `instgram` varchar(32) NOT NULL DEFAULT '',
  `youtube` varchar(100) NOT NULL DEFAULT '',
  `verified` enum('0','1') NOT NULL DEFAULT '0',
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `registered` varchar(32) NOT NULL DEFAULT '0/0000',
  `boosted` enum('0','1') NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL DEFAULT 0,
  `message_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pages_categories`
--

CREATE TABLE `wo_pages_categories` (
  `id` int(11) NOT NULL,
  `lang_key` varchar(160) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pages_invites`
--

CREATE TABLE `wo_pages_invites` (
  `id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `inviter_id` int(11) NOT NULL DEFAULT 0,
  `invited_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pages_likes`
--

CREATE TABLE `wo_pages_likes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_patreonsubscribers`
--

CREATE TABLE `wo_patreonsubscribers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `subscriber_id` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_payments`
--

CREATE TABLE `wo_payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `amount` int(11) NOT NULL DEFAULT 0,
  `type` varchar(15) NOT NULL DEFAULT '',
  `date` varchar(30) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_payment_transactions`
--

CREATE TABLE `wo_payment_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `kind` varchar(100) NOT NULL,
  `amount` decimal(11,0) UNSIGNED NOT NULL,
  `transaction_dt` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pendingpayments`
--

CREATE TABLE `wo_pendingpayments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `payment_data` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `method_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pinnedposts`
--

CREATE TABLE `wo_pinnedposts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `event_id` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_pokes`
--

CREATE TABLE `wo_pokes` (
  `id` int(11) NOT NULL,
  `received_user_id` int(11) NOT NULL DEFAULT 0,
  `send_user_id` int(11) NOT NULL DEFAULT 0,
  `dt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_polls`
--

CREATE TABLE `wo_polls` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `text` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_posts`
--

CREATE TABLE `wo_posts` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `recipient_id` int(11) NOT NULL DEFAULT 0,
  `postText` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `event_id` int(11) NOT NULL DEFAULT 0,
  `page_event_id` int(11) NOT NULL DEFAULT 0,
  `postLink` varchar(1000) NOT NULL DEFAULT '',
  `postLinkTitle` text DEFAULT NULL,
  `postLinkImage` varchar(100) NOT NULL DEFAULT '',
  `postLinkContent` text DEFAULT NULL,
  `postVimeo` varchar(100) NOT NULL DEFAULT '',
  `postDailymotion` varchar(100) NOT NULL DEFAULT '',
  `postFacebook` varchar(100) NOT NULL DEFAULT '',
  `postFile` varchar(255) NOT NULL DEFAULT '',
  `postFileName` varchar(200) NOT NULL DEFAULT '',
  `postFileThumb` varchar(3000) NOT NULL DEFAULT '',
  `postYoutube` varchar(255) NOT NULL DEFAULT '',
  `postVine` varchar(32) NOT NULL DEFAULT '',
  `postSoundCloud` varchar(255) NOT NULL DEFAULT '',
  `postPlaytube` varchar(500) NOT NULL DEFAULT '',
  `postDeepsound` varchar(500) NOT NULL DEFAULT '',
  `postMap` varchar(255) NOT NULL DEFAULT '',
  `postShare` int(11) NOT NULL DEFAULT 0,
  `postPrivacy` enum('0','1','2','3','4','5') NOT NULL DEFAULT '1',
  `postType` varchar(30) NOT NULL DEFAULT '',
  `postFeeling` varchar(255) NOT NULL DEFAULT '',
  `postListening` varchar(255) NOT NULL DEFAULT '',
  `postTraveling` varchar(255) NOT NULL DEFAULT '',
  `postWatching` varchar(255) NOT NULL DEFAULT '',
  `postPlaying` varchar(255) NOT NULL DEFAULT '',
  `postPhoto` varchar(3000) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `registered` varchar(32) NOT NULL DEFAULT '0/0000',
  `album_name` varchar(52) NOT NULL DEFAULT '',
  `multi_image` enum('0','1') NOT NULL DEFAULT '0',
  `multi_image_post` int(11) NOT NULL DEFAULT 0,
  `boosted` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `poll_id` int(11) NOT NULL DEFAULT 0,
  `blog_id` int(11) NOT NULL DEFAULT 0,
  `forum_id` int(11) NOT NULL DEFAULT 0,
  `thread_id` int(11) NOT NULL DEFAULT 0,
  `videoViews` int(11) NOT NULL DEFAULT 0,
  `postRecord` varchar(3000) NOT NULL DEFAULT '',
  `postSticker` text DEFAULT NULL,
  `shared_from` int(11) NOT NULL DEFAULT 0,
  `post_url` text DEFAULT NULL,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `cache` int(11) NOT NULL DEFAULT 0,
  `comments_status` int(11) NOT NULL DEFAULT 1,
  `blur` int(11) NOT NULL DEFAULT 0,
  `color_id` int(11) NOT NULL DEFAULT 0,
  `job_id` int(11) NOT NULL DEFAULT 0,
  `offer_id` int(11) NOT NULL DEFAULT 0,
  `fund_raise_id` int(11) NOT NULL DEFAULT 0,
  `fund_id` int(11) NOT NULL DEFAULT 0,
  `active` int(11) NOT NULL DEFAULT 1,
  `stream_name` varchar(100) NOT NULL DEFAULT '',
  `agora_token` text DEFAULT NULL,
  `live_time` int(11) NOT NULL DEFAULT 0,
  `live_ended` int(11) NOT NULL DEFAULT 0,
  `agora_resource_id` text DEFAULT NULL,
  `agora_sid` varchar(500) NOT NULL DEFAULT '',
  `send_notify` varchar(11) NOT NULL DEFAULT '',
  `240p` int(11) NOT NULL DEFAULT 0,
  `360p` int(11) NOT NULL DEFAULT 0,
  `480p` int(11) NOT NULL DEFAULT 0,
  `720p` int(11) NOT NULL DEFAULT 0,
  `1080p` int(11) NOT NULL DEFAULT 0,
  `2048p` int(11) NOT NULL DEFAULT 0,
  `4096p` int(11) NOT NULL DEFAULT 0,
  `processing` int(11) NOT NULL DEFAULT 0,
  `ai_post` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_productreview`
--

CREATE TABLE `wo_productreview` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `review` text DEFAULT NULL,
  `star` int(11) NOT NULL DEFAULT 1,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_products`
--

CREATE TABLE `wo_products` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `category` int(11) NOT NULL DEFAULT 0,
  `sub_category` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `price` float NOT NULL DEFAULT 0,
  `location` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `type` enum('0','1') NOT NULL,
  `currency` varchar(40) NOT NULL DEFAULT 'USD',
  `lng` varchar(100) NOT NULL DEFAULT '0',
  `lat` varchar(100) NOT NULL DEFAULT '0',
  `units` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_products_categories`
--

CREATE TABLE `wo_products_categories` (
  `id` int(11) NOT NULL,
  `lang_key` varchar(160) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_products_media`
--

CREATE TABLE `wo_products_media` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `image` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_profilefields`
--

CREATE TABLE `wo_profilefields` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `type` text DEFAULT NULL,
  `length` int(11) NOT NULL DEFAULT 0,
  `placement` varchar(32) NOT NULL DEFAULT 'profile',
  `registration_page` int(11) NOT NULL DEFAULT 0,
  `profile_page` int(11) NOT NULL DEFAULT 0,
  `select_type` varchar(32) NOT NULL DEFAULT 'none',
  `active` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_purchases`
--

CREATE TABLE `wo_purchases` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `order_hash_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `owner_id` int(11) NOT NULL DEFAULT 0,
  `data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `final_price` float NOT NULL DEFAULT 0,
  `commission` float NOT NULL DEFAULT 0,
  `price` float NOT NULL DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_reactions`
--

CREATE TABLE `wo_reactions` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `post_id` int(10) UNSIGNED DEFAULT 0,
  `comment_id` int(10) UNSIGNED DEFAULT 0,
  `replay_id` int(10) UNSIGNED DEFAULT 0,
  `message_id` int(11) NOT NULL DEFAULT 0,
  `story_id` int(11) NOT NULL DEFAULT 0,
  `reaction` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_reactions_types`
--

CREATE TABLE `wo_reactions_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '',
  `wowonder_icon` varchar(300) NOT NULL DEFAULT '',
  `sunshine_icon` varchar(300) NOT NULL DEFAULT '',
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_recentsearches`
--

CREATE TABLE `wo_recentsearches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `search_id` int(11) NOT NULL DEFAULT 0,
  `search_type` varchar(32) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_refund`
--

CREATE TABLE `wo_refund` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `order_hash_id` varchar(100) NOT NULL DEFAULT '',
  `pro_type` varchar(50) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_relationship`
--

CREATE TABLE `wo_relationship` (
  `id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL DEFAULT 0,
  `to_id` int(11) NOT NULL DEFAULT 0,
  `relationship` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1') NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_reports`
--

CREATE TABLE `wo_reports` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `comment_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `profile_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `text` text DEFAULT NULL,
  `reason` varchar(100) NOT NULL DEFAULT '',
  `seen` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_savedposts`
--

CREATE TABLE `wo_savedposts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_stickers`
--

CREATE TABLE `wo_stickers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(250) DEFAULT NULL,
  `media_file` varchar(250) NOT NULL,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_story_seen`
--

CREATE TABLE `wo_story_seen` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `story_id` int(11) NOT NULL DEFAULT 0,
  `time` varchar(20) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_sub_categories`
--

CREATE TABLE `wo_sub_categories` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL DEFAULT 0,
  `lang_key` varchar(200) NOT NULL DEFAULT '',
  `type` varchar(200) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_terms`
--

CREATE TABLE `wo_terms` (
  `id` int(11) NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT '',
  `text` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_tokens`
--

CREATE TABLE `wo_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `app_id` int(11) NOT NULL DEFAULT 0,
  `token` varchar(200) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_uploadedmedia`
--

CREATE TABLE `wo_uploadedmedia` (
  `id` int(11) NOT NULL,
  `filename` varchar(200) NOT NULL DEFAULT '',
  `storage` varchar(34) NOT NULL,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_useraddress`
--

CREATE TABLE `wo_useraddress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL DEFAULT '',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `zip` varchar(20) NOT NULL DEFAULT '',
  `address` varchar(500) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userads`
--

CREATE TABLE `wo_userads` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `url` varchar(3000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `headline` varchar(200) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `location` varchar(1000) NOT NULL DEFAULT 'us',
  `audience` longtext DEFAULT NULL,
  `ad_media` varchar(3000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `gender` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_danish_ci NOT NULL DEFAULT 'all',
  `bidding` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `clicks` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `posted` varchar(15) NOT NULL DEFAULT '',
  `status` int(11) NOT NULL DEFAULT 1,
  `appears` varchar(10) NOT NULL DEFAULT 'post',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` varchar(50) NOT NULL DEFAULT '',
  `start` varchar(50) NOT NULL DEFAULT '',
  `end` varchar(50) NOT NULL DEFAULT '',
  `budget` float UNSIGNED NOT NULL DEFAULT 0,
  `spent` float UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userads_data`
--

CREATE TABLE `wo_userads_data` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `ad_id` int(11) NOT NULL DEFAULT 0,
  `clicks` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `spend` float UNSIGNED NOT NULL DEFAULT 0,
  `dt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_usercard`
--

CREATE TABLE `wo_usercard` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `units` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_usercertification`
--

CREATE TABLE `wo_usercertification` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `issuing_organization` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `credential_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `credential_url` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `certification_start` date NOT NULL,
  `certification_end` date NOT NULL,
  `pdf` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `filename` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userexperience`
--

CREATE TABLE `wo_userexperience` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `title` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `location` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `experience_start` date NOT NULL,
  `experience_end` date NOT NULL,
  `industry` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `image` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `link` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `headline` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `company_name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `employment_type` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userfields`
--

CREATE TABLE `wo_userfields` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userlanguages`
--

CREATE TABLE `wo_userlanguages` (
  `id` int(11) NOT NULL,
  `lang_key` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_useropento`
--

CREATE TABLE `wo_useropento` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `job_title` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `job_location` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `workplaces` varchar(600) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `job_type` varchar(600) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `services` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `description` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userorders`
--

CREATE TABLE `wo_userorders` (
  `id` int(11) NOT NULL,
  `hash_id` varchar(100) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `product_owner_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `address_id` int(11) NOT NULL DEFAULT 0,
  `price` float NOT NULL DEFAULT 0,
  `commission` float NOT NULL DEFAULT 0,
  `final_price` float NOT NULL DEFAULT 0,
  `units` int(11) NOT NULL DEFAULT 0,
  `tracking_url` varchar(500) NOT NULL DEFAULT '',
  `tracking_id` varchar(50) NOT NULL DEFAULT '',
  `status` varchar(30) NOT NULL DEFAULT 'placed',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userprojects`
--

CREATE TABLE `wo_userprojects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(600) NOT NULL DEFAULT '',
  `associated_with` varchar(200) NOT NULL DEFAULT '',
  `project_url` varchar(300) NOT NULL DEFAULT '',
  `project_start` date NOT NULL,
  `project_end` date NOT NULL,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_users`
--

CREATE TABLE `wo_users` (
  `user_id` int(11) NOT NULL,
  `serial` int(11) NOT NULL DEFAULT 999,
  `username` varchar(32) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(70) NOT NULL DEFAULT '',
  `first_name` varchar(60) NOT NULL DEFAULT '',
  `last_name` varchar(32) NOT NULL DEFAULT '',
  `designation` varchar(300) NOT NULL DEFAULT '',
  `department` varchar(300) NOT NULL,
  `avatar` varchar(100) NOT NULL DEFAULT 'upload/photos/d-avatar.jpg',
  `cover` varchar(100) NOT NULL DEFAULT 'upload/photos/d-cover.jpg',
  `signature` varchar(120) NOT NULL,
  `background_image` varchar(100) NOT NULL DEFAULT '',
  `background_image_status` enum('0','1') NOT NULL DEFAULT '0',
  `relationship_id` int(11) NOT NULL DEFAULT 0,
  `address` varchar(100) NOT NULL DEFAULT '',
  `working` varchar(32) NOT NULL DEFAULT '',
  `working_link` varchar(32) NOT NULL DEFAULT '',
  `about` mediumtext DEFAULT NULL,
  `school` varchar(32) NOT NULL DEFAULT '',
  `gender` varchar(100) NOT NULL DEFAULT 'male',
  `birthday` varchar(50) NOT NULL DEFAULT '0000-00-00',
  `joining_date` varchar(50) NOT NULL DEFAULT '0000-00-00',
  `country_id` int(11) NOT NULL DEFAULT 0,
  `website` varchar(50) NOT NULL DEFAULT '',
  `facebook` varchar(50) NOT NULL DEFAULT '',
  `google` varchar(50) NOT NULL DEFAULT '',
  `twitter` varchar(50) NOT NULL DEFAULT '',
  `linkedin` varchar(32) NOT NULL DEFAULT '',
  `youtube` varchar(100) NOT NULL DEFAULT '',
  `vk` varchar(32) NOT NULL DEFAULT '',
  `instagram` varchar(32) NOT NULL DEFAULT '',
  `qq` mediumtext DEFAULT NULL,
  `wechat` mediumtext DEFAULT NULL,
  `discord` mediumtext DEFAULT NULL,
  `mailru` mediumtext DEFAULT NULL,
  `okru` varchar(30) NOT NULL DEFAULT '',
  `language` varchar(31) NOT NULL DEFAULT 'english',
  `email_code` varchar(32) NOT NULL DEFAULT '',
  `src` varchar(32) NOT NULL DEFAULT 'Undefined',
  `ip_address` varchar(100) DEFAULT '',
  `follow_privacy` enum('1','0') NOT NULL DEFAULT '0',
  `friend_privacy` enum('0','1','2','3') NOT NULL DEFAULT '0',
  `post_privacy` varchar(255) NOT NULL DEFAULT 'ifollow',
  `message_privacy` enum('1','0','2') NOT NULL DEFAULT '0',
  `confirm_followers` enum('1','0') NOT NULL DEFAULT '0',
  `show_activities_privacy` enum('0','1') NOT NULL DEFAULT '1',
  `birth_privacy` enum('0','1','2') NOT NULL DEFAULT '0',
  `visit_privacy` enum('0','1') NOT NULL DEFAULT '0',
  `verified` enum('1','0') NOT NULL DEFAULT '0',
  `lastseen` int(11) NOT NULL DEFAULT 0,
  `showlastseen` enum('1','0') NOT NULL DEFAULT '1',
  `emailNotification` enum('1','0') NOT NULL DEFAULT '1',
  `e_liked` enum('0','1') NOT NULL DEFAULT '1',
  `e_wondered` enum('0','1') NOT NULL DEFAULT '1',
  `e_shared` enum('0','1') NOT NULL DEFAULT '1',
  `e_followed` enum('0','1') NOT NULL DEFAULT '1',
  `e_commented` enum('0','1') NOT NULL DEFAULT '1',
  `e_visited` enum('0','1') NOT NULL DEFAULT '1',
  `e_liked_page` enum('0','1') NOT NULL DEFAULT '1',
  `e_mentioned` enum('0','1') NOT NULL DEFAULT '1',
  `e_joined_group` enum('0','1') NOT NULL DEFAULT '1',
  `e_accepted` enum('0','1') NOT NULL DEFAULT '1',
  `e_profile_wall_post` enum('0','1') NOT NULL DEFAULT '1',
  `e_sentme_msg` enum('0','1') NOT NULL DEFAULT '0',
  `e_last_notif` varchar(50) NOT NULL DEFAULT '0',
  `notification_settings` varchar(400) NOT NULL DEFAULT '{"e_liked":1,"e_shared":1,"e_wondered":0,"e_commented":1,"e_followed":1,"e_accepted":1,"e_mentioned":1,"e_joined_group":1,"e_liked_page":1,"e_visited":1,"e_profile_wall_post":1,"e_memory":1}',
  `status` enum('1','0') NOT NULL DEFAULT '0',
  `is_team_leader` int(11) NOT NULL DEFAULT 0,
  `leader_id` int(11) NOT NULL DEFAULT 0,
  `active` enum('0','1','2') NOT NULL DEFAULT '0',
  `admin` enum('0','1','2') NOT NULL DEFAULT '0',
  `is_bazar` enum('0','1','2') NOT NULL DEFAULT '0',
  `type` varchar(11) NOT NULL DEFAULT 'user',
  `registered` varchar(32) NOT NULL DEFAULT '0/0000',
  `start_up` enum('0','1') NOT NULL DEFAULT '0',
  `start_up_info` enum('0','1') NOT NULL DEFAULT '0',
  `startup_follow` enum('0','1') NOT NULL DEFAULT '0',
  `startup_image` enum('0','1') NOT NULL DEFAULT '0',
  `last_email_sent` int(11) NOT NULL DEFAULT 0,
  `phone_number` varchar(32) NOT NULL DEFAULT '',
  `sms_code` int(11) NOT NULL DEFAULT 0,
  `is_pro` enum('0','1') NOT NULL DEFAULT '0',
  `pro_time` int(11) NOT NULL DEFAULT 0,
  `pro_type` int(11) NOT NULL DEFAULT 0,
  `pro_remainder` varchar(20) NOT NULL DEFAULT '',
  `joined` int(11) NOT NULL DEFAULT 0,
  `css_file` varchar(100) NOT NULL DEFAULT '',
  `timezone` varchar(50) NOT NULL DEFAULT '',
  `referrer` int(11) NOT NULL DEFAULT 0,
  `ref_user_id` int(11) NOT NULL DEFAULT 0,
  `ref_level` mediumtext DEFAULT NULL,
  `balance` varchar(100) NOT NULL DEFAULT '0',
  `paypal_email` varchar(100) NOT NULL DEFAULT '',
  `notifications_sound` enum('0','1') NOT NULL DEFAULT '0',
  `order_posts_by` enum('0','1') NOT NULL DEFAULT '1',
  `social_login` enum('0','1') NOT NULL DEFAULT '0',
  `android_m_device_id` varchar(50) NOT NULL DEFAULT '',
  `ios_m_device_id` varchar(50) NOT NULL DEFAULT '',
  `android_n_device_id` varchar(50) NOT NULL DEFAULT '',
  `ios_n_device_id` varchar(50) NOT NULL DEFAULT '',
  `web_device_id` varchar(100) NOT NULL DEFAULT '',
  `wallet` varchar(20) NOT NULL DEFAULT '0.00',
  `lat` varchar(200) NOT NULL DEFAULT '0',
  `lng` varchar(200) NOT NULL DEFAULT '0',
  `last_location_update` varchar(30) NOT NULL DEFAULT '0',
  `share_my_location` int(11) NOT NULL DEFAULT 1,
  `last_data_update` int(11) NOT NULL DEFAULT 0,
  `details` varchar(300) NOT NULL DEFAULT '{"post_count":0,"album_count":0,"following_count":0,"followers_count":0,"groups_count":0,"likes_count":0}',
  `sidebar_data` mediumtext DEFAULT NULL,
  `last_avatar_mod` int(11) NOT NULL DEFAULT 0,
  `last_cover_mod` int(11) NOT NULL DEFAULT 0,
  `points` float UNSIGNED NOT NULL DEFAULT 0,
  `daily_points` int(11) NOT NULL DEFAULT 0,
  `converted_points` float UNSIGNED NOT NULL DEFAULT 0,
  `point_day_expire` varchar(50) NOT NULL DEFAULT '',
  `last_follow_id` int(11) NOT NULL DEFAULT 0,
  `share_my_data` int(11) NOT NULL DEFAULT 1,
  `last_login_data` mediumtext DEFAULT NULL,
  `two_factor` int(11) NOT NULL DEFAULT 0,
  `two_factor_hash` varchar(50) NOT NULL DEFAULT '',
  `new_email` varchar(255) NOT NULL DEFAULT '',
  `two_factor_verified` int(11) NOT NULL DEFAULT 0,
  `new_phone` varchar(32) NOT NULL DEFAULT '',
  `info_file` varchar(300) NOT NULL DEFAULT '',
  `city` varchar(50) NOT NULL DEFAULT '',
  `state` varchar(50) NOT NULL DEFAULT '',
  `zip` varchar(11) NOT NULL DEFAULT '',
  `school_completed` int(11) NOT NULL DEFAULT 0,
  `weather_unit` varchar(11) NOT NULL DEFAULT 'us',
  `paystack_ref` varchar(100) NOT NULL DEFAULT '',
  `code_sent` int(11) NOT NULL DEFAULT 0,
  `time_code_sent` int(11) NOT NULL DEFAULT 0,
  `permission` mediumtext DEFAULT NULL,
  `skills` mediumtext DEFAULT NULL,
  `languages` mediumtext DEFAULT NULL,
  `manage_pass` varchar(70) NOT NULL DEFAULT '',
  `currently_working` varchar(50) NOT NULL DEFAULT '',
  `banned` int(11) NOT NULL DEFAULT 0,
  `banned_reason` varchar(500) NOT NULL DEFAULT '',
  `credits` float DEFAULT 0,
  `authy_id` varchar(100) NOT NULL DEFAULT '',
  `google_secret` varchar(100) NOT NULL DEFAULT '',
  `two_factor_method` varchar(50) NOT NULL DEFAULT 'two_factor',
  `management` int(11) NOT NULL DEFAULT 0,
  `exclude_attendance` int(11) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 999,
  `last_lead_count` int(11) NOT NULL DEFAULT 0,
  `app_version` varchar(32) NOT NULL DEFAULT '0',
  `maintainance_override` enum('1','0') DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userschat`
--

CREATE TABLE `wo_userschat` (
  `id` int(11) NOT NULL,
  `thread_id` varchar(120) NOT NULL,
  `assigned` int(11) NOT NULL DEFAULT 0,
  `page_id` varchar(120) NOT NULL DEFAULT '0',
  `time` int(11) NOT NULL DEFAULT 0,
  `color` varchar(100) NOT NULL DEFAULT '',
  `message_count` int(11) NOT NULL DEFAULT 0,
  `unread_count` int(11) NOT NULL DEFAULT 0,
  `can_reply` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userskills`
--

CREATE TABLE `wo_userskills` (
  `id` int(11) NOT NULL,
  `name` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userstory`
--

CREATE TABLE `wo_userstory` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `description` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `posted` varchar(50) NOT NULL DEFAULT '',
  `expire` varchar(100) DEFAULT '',
  `thumbnail` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_userstorymedia`
--

CREATE TABLE `wo_userstorymedia` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL DEFAULT 0,
  `type` varchar(30) NOT NULL DEFAULT '',
  `filename` text DEFAULT NULL,
  `expire` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_usertiers`
--

CREATE TABLE `wo_usertiers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `title` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `price` float NOT NULL DEFAULT 0,
  `image` varchar(400) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `description` varchar(1000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `chat` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `live_stream` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_user_gifts`
--

CREATE TABLE `wo_user_gifts` (
  `id` int(11) NOT NULL,
  `from` int(11) NOT NULL DEFAULT 0,
  `to` int(11) NOT NULL DEFAULT 0,
  `gift_id` int(11) NOT NULL DEFAULT 0,
  `time` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_verification_requests`
--

CREATE TABLE `wo_verification_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_id` int(11) NOT NULL DEFAULT 0,
  `message` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `user_name` varchar(150) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `passport` varchar(3000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `photo` varchar(3000) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `type` varchar(32) NOT NULL DEFAULT '',
  `seen` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_videocalles`
--

CREATE TABLE `wo_videocalles` (
  `id` int(11) NOT NULL,
  `access_token` text DEFAULT NULL,
  `access_token_2` text DEFAULT NULL,
  `from_id` int(11) NOT NULL DEFAULT 0,
  `to_id` int(11) NOT NULL DEFAULT 0,
  `room_name` varchar(50) NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 0,
  `called` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `declined` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_votes`
--

CREATE TABLE `wo_votes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `option_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wo_wonders`
--

CREATE TABLE `wo_wonders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `post_id` int(11) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `atten_in_out`
--
ALTER TABLE `atten_in_out`
  ADD PRIMARY KEY (`id`),
  ADD KEY `CHECKTIME` (`CHECKTIME`),
  ADD KEY `USERID` (`USERID`),
  ADD KEY `CHECKTYPE` (`CHECKTYPE`);

--
-- Indexes for table `atten_reason`
--
ALTER TABLE `atten_reason`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date` (`date`),
  ADD KEY `Badgenumber` (`Badgenumber`);

--
-- Indexes for table `atten_users`
--
ALTER TABLE `atten_users`
  ADD PRIMARY KEY (`USERID`);

--
-- Indexes for table `awards`
--
ALTER TABLE `awards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`backup_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_table` (`table_name`);

--
-- Indexes for table `backup_schedules`
--
ALTER TABLE `backup_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_next_run` (`enabled`,`next_run_at`);

--
-- Indexes for table `bank_receipts`
--
ALTER TABLE `bank_receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fund_id` (`fund_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `approved_at` (`approved_at`),
  ADD KEY `approved` (`approved`),
  ADD KEY `mode` (`mode`);

--
-- Indexes for table `broadcast`
--
ALTER TABLE `broadcast`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `time` (`time`),
  ADD KEY `user_id_2` (`user_id`),
  ADD KEY `time_2` (`time`);

--
-- Indexes for table `broadcast_users`
--
ALTER TABLE `broadcast_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `broadcast_id` (`broadcast_id`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients_review`
--
ALTER TABLE `clients_review`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crm_advance`
--
ALTER TABLE `crm_advance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crm_assignment_rules`
--
ALTER TABLE `crm_assignment_rules`
  ADD UNIQUE KEY `user_project_unique` (`user_id`,`project`),
  ADD KEY `project` (`project`),
  ADD KEY `raw_weight` (`raw_weight`);

--
-- Indexes for table `crm_assignment_state`
--
ALTER TABLE `crm_assignment_state`
  ADD PRIMARY KEY (`project`,`type`,`entity_id`);

--
-- Indexes for table `crm_bazar`
--
ALTER TABLE `crm_bazar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `id` (`id`),
  ADD KEY `quantity` (`quantity`),
  ADD KEY `updated_at` (`updated_at`);

--
-- Indexes for table `crm_bazar_items`
--
ALTER TABLE `crm_bazar_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_name` (`name`);

--
-- Indexes for table `crm_bazar_logs`
--
ALTER TABLE `crm_bazar_logs`
  ADD PRIMARY KEY (`id`,`date_ts`),
  ADD KEY `ix_bazar_date` (`bazar_id`,`date_ts`),
  ADD KEY `ix_date` (`date_ts`),
  ADD KEY `ix_type_date` (`type`,`date_ts`);

--
-- Indexes for table `crm_bazar_price_history`
--
ALTER TABLE `crm_bazar_price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bazar_id` (`bazar_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `crm_bazar_quantity_history`
--
ALTER TABLE `crm_bazar_quantity_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bazar_id` (`bazar_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `crm_bazar_usage_history`
--
ALTER TABLE `crm_bazar_usage_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bazar_id` (`bazar_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `crm_customers`
--
ALTER TABLE `crm_customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_crm_phone` (`phone`) USING BTREE,
  ADD KEY `idx_crm_nid` (`nid`),
  ADD KEY `idx_crm_passport` (`passport`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `crm_debit`
--
ALTER TABLE `crm_debit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crm_holidays`
--
ALTER TABLE `crm_holidays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_holiday_date` (`holiday`,`full_date`),
  ADD KEY `idx_full_date` (`full_date`),
  ADD KEY `idx_year` (`year`);

--
-- Indexes for table `crm_invoice`
--
ALTER TABLE `crm_invoice`
  ADD PRIMARY KEY (`inv_id`);

--
-- Indexes for table `crm_items_master`
--
ALTER TABLE `crm_items_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `crm_leads`
--
ALTER TABLE `crm_leads`
  ADD PRIMARY KEY (`lead_id`),
  ADD KEY `member` (`member`),
  ADD KEY `assigned` (`assigned`),
  ADD KEY `status` (`status`),
  ADD KEY `created` (`created`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `phone` (`phone`),
  ADD KEY `thread_id` (`thread_id`),
  ADD KEY `project` (`project`),
  ADD KEY `quick_remarks` (`quick_remarks`),
  ADD KEY `name` (`name`),
  ADD KEY `response` (`response`),
  ADD KEY `viewed` (`viewed`);

--
-- Indexes for table `crm_leads_report`
--
ALTER TABLE `crm_leads_report`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crm_lead_reassignments`
--
ALTER TABLE `crm_lead_reassignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project` (`project`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `to_user` (`to_user`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `crm_leaves`
--
ALTER TABLE `crm_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_approved` (`is_approved`),
  ADD KEY `is_paid` (`is_paid`),
  ADD KEY `leave_from` (`leave_from`),
  ADD KEY `leave_to` (`leave_to`),
  ADD KEY `type` (`type`),
  ADD KEY `days` (`days`);

--
-- Indexes for table `crm_locations`
--
ALTER TABLE `crm_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lat` (`lat`),
  ADD KEY `lng` (`lng`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `crm_nominees`
--
ALTER TABLE `crm_nominees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `crm_projects`
--
ALTER TABLE `crm_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `slug` (`slug`),
  ADD KEY `active` (`active`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `crm_punished_users`
--
ALTER TABLE `crm_punished_users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `crm_purchase`
--
ALTER TABLE `crm_purchase`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crm_remarks`
--
ALTER TABLE `crm_remarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_system` (`is_system`),
  ADD KEY `remind_at` (`remind_at`);

--
-- Indexes for table `crm_rent_report`
--
ALTER TABLE `crm_rent_report`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `crm_salary`
--
ALTER TABLE `crm_salary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Badgenumber` (`Badgenumber`),
  ADD KEY `amount` (`amount`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `crm_sms_report`
--
ALTER TABLE `crm_sms_report`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tried` (`tried`),
  ADD KEY `last_tried` (`last_tried`);

--
-- Indexes for table `crm_stock`
--
ALTER TABLE `crm_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `id` (`id`),
  ADD KEY `quantity` (`quantity`),
  ADD KEY `updated_at` (`updated_at`);

--
-- Indexes for table `crm_stock_price_history`
--
ALTER TABLE `crm_stock_price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `crm_stock_quantity_history`
--
ALTER TABLE `crm_stock_quantity_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `crm_stock_usage_history`
--
ALTER TABLE `crm_stock_usage_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `crm_user_capacity`
--
ALTER TABLE `crm_user_capacity`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `global_share` (`global_share`);

--
-- Indexes for table `field_positions`
--
ALTER TABLE `field_positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `fm_activity_log`
--
ALTER TABLE `fm_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_file` (`file_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `fm_common_folders`
--
ALTER TABLE `fm_common_folders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_key` (`folder_key`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `fm_files`
--
ALTER TABLE `fm_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_parent` (`parent_folder_id`),
  ADD KEY `idx_deleted` (`is_deleted`,`deleted_at`),
  ADD KEY `idx_path` (`path`(255)),
  ADD KEY `idx_r2` (`r2_uploaded`,`r2_key`(255)),
  ADD KEY `idx_fm_files_folder_type` (`folder_type`,`user_id`,`is_deleted`),
  ADD KEY `idx_fm_files_special_folder` (`special_folder_id`),
  ADD KEY `idx_fm_files_common_folder` (`common_folder_id`),
  ADD KEY `idx_fm_files_storage_type` (`storage_type`,`user_id`),
  ADD KEY `idx_fm_files_user_storage` (`is_in_user_storage`,`user_id`),
  ADD KEY `idx_fm_files_storage_folder` (`storage_folder_id`);

--
-- Indexes for table `fm_file_shares`
--
ALTER TABLE `fm_file_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_share_token` (`share_token`),
  ADD KEY `idx_file_id` (`file_id`),
  ADD KEY `idx_shared_by` (`shared_by`),
  ADD KEY `idx_shared_with` (`shared_with`),
  ADD KEY `idx_share_token` (`share_token`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `fm_file_versions`
--
ALTER TABLE `fm_file_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_id` (`file_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_version` (`file_id`,`version_number`);

--
-- Indexes for table `fm_folder_access`
--
ALTER TABLE `fm_folder_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_user` (`folder_id`,`folder_type`,`user_id`),
  ADD KEY `idx_folder` (`folder_id`,`folder_type`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `fm_folder_structure`
--
ALTER TABLE `fm_folder_structure`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_path` (`folder_path`(512)),
  ADD KEY `idx_user_type` (`user_id`,`folder_type`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_type` (`folder_type`,`is_active`);

--
-- Indexes for table `fm_permissions`
--
ALTER TABLE `fm_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_user` (`file_id`,`user_id`);

--
-- Indexes for table `fm_recycle_bin`
--
ALTER TABLE `fm_recycle_bin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file` (`file_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_auto_delete` (`auto_delete_at`),
  ADD KEY `idx_fm_recycle_auto_delete` (`auto_delete_at`),
  ADD KEY `idx_fm_recycle_user` (`user_id`,`can_restore`);

--
-- Indexes for table `fm_special_folders`
--
ALTER TABLE `fm_special_folders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_key` (`folder_key`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `fm_system_settings`
--
ALTER TABLE `fm_system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`);

--
-- Indexes for table `fm_thumbnails`
--
ALTER TABLE `fm_thumbnails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_file_size` (`file_id`,`thumbnail_size`),
  ADD KEY `idx_file_id` (`file_id`);

--
-- Indexes for table `fm_upload_queue`
--
ALTER TABLE `fm_upload_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_upload` (`local_path`(255),`remote_key`(255)),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `fm_user_quotas`
--
ALTER TABLE `fm_user_quotas`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_usage` (`used_bytes`),
  ADD KEY `idx_updated` (`updated_at`);

--
-- Indexes for table `fm_user_storage_tracking`
--
ALTER TABLE `fm_user_storage_tracking`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_used` (`used_bytes`),
  ADD KEY `idx_updated` (`updated_at`);

--
-- Indexes for table `home_slider`
--
ALTER TABLE `home_slider`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `key_locations`
--
ALTER TABLE `key_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type` (`type`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `notifications_views`
--
ALTER TABLE `notifications_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_notif` (`user_id`,`notif_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_notif` (`notif_id`);

--
-- Indexes for table `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `photo_gallery`
--
ALTER TABLE `photo_gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `restore_history`
--
ALTER TABLE `restore_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_backup` (`backup_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `wondertage_settings`
--
ALTER TABLE `wondertage_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `wo_activities`
--
ALTER TABLE `wo_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `order1` (`user_id`,`id`),
  ADD KEY `order2` (`post_id`,`id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `reply_id` (`reply_id`),
  ADD KEY `follow_id` (`follow_id`);

--
-- Indexes for table `wo_admininvitations`
--
ALTER TABLE `wo_admininvitations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `code` (`code`(255));

--
-- Indexes for table `wo_ads`
--
ALTER TABLE `wo_ads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active` (`active`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `wo_affiliates_requests`
--
ALTER TABLE `wo_affiliates_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `time` (`time`),
  ADD KEY `status` (`status`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `wo_agoravideocall`
--
ALTER TABLE `wo_agoravideocall`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_id` (`from_id`),
  ADD KEY `to_id` (`to_id`),
  ADD KEY `type` (`type`),
  ADD KEY `room_name` (`room_name`),
  ADD KEY `time` (`time`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `wo_albums_media`
--
ALTER TABLE `wo_albums_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `order1` (`post_id`,`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `review_id` (`review_id`);

--
-- Indexes for table `wo_announcement`
--
ALTER TABLE `wo_announcement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `wo_announcement_views`
--
ALTER TABLE `wo_announcement_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `wo_apps`
--
ALTER TABLE `wo_apps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `app_user_id` (`app_user_id`),
  ADD KEY `app_id` (`app_id`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `wo_appssessions`
--
ALTER TABLE `wo_appssessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `platform` (`platform`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_apps_hash`
--
ALTER TABLE `wo_apps_hash`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hash_id` (`hash_id`),
  ADD KEY `active` (`active`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_apps_permission`
--
ALTER TABLE `wo_apps_permission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`app_id`);

--
-- Indexes for table `wo_audiocalls`
--
ALTER TABLE `wo_audiocalls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `to_id` (`to_id`),
  ADD KEY `from_id` (`from_id`),
  ADD KEY `call_id` (`call_id`),
  ADD KEY `called` (`called`),
  ADD KEY `declined` (`declined`);

--
-- Indexes for table `wo_backup_codes`
--
ALTER TABLE `wo_backup_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_bad_login`
--
ALTER TABLE `wo_bad_login`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip` (`ip`);

--
-- Indexes for table `wo_banned_ip`
--
ALTER TABLE `wo_banned_ip`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_address` (`ip_address`);

--
-- Indexes for table `wo_blocks`
--
ALTER TABLE `wo_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blocker` (`blocker`),
  ADD KEY `blocked` (`blocked`);

--
-- Indexes for table `wo_blog`
--
ALTER TABLE `wo_blog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`),
  ADD KEY `title` (`title`),
  ADD KEY `category` (`category`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `wo_blogcommentreplies`
--
ALTER TABLE `wo_blogcommentreplies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comm_id` (`comm_id`),
  ADD KEY `blog_id` (`blog_id`),
  ADD KEY `order1` (`comm_id`,`id`),
  ADD KEY `order2` (`blog_id`,`id`),
  ADD KEY `order3` (`user_id`,`id`);

--
-- Indexes for table `wo_blogcomments`
--
ALTER TABLE `wo_blogcomments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_id` (`blog_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_blogmoviedislikes`
--
ALTER TABLE `wo_blogmoviedislikes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_comm_id` (`blog_comm_id`),
  ADD KEY `movie_comm_id` (`movie_comm_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `blog_commreply_id` (`blog_commreply_id`),
  ADD KEY `movie_commreply_id` (`movie_commreply_id`),
  ADD KEY `blog_id` (`blog_id`),
  ADD KEY `movie_id` (`movie_id`);

--
-- Indexes for table `wo_blogmovielikes`
--
ALTER TABLE `wo_blogmovielikes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blog_id` (`blog_comm_id`),
  ADD KEY `movie_id` (`movie_comm_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `blog_commreply_id` (`blog_commreply_id`),
  ADD KEY `movie_commreply_id` (`movie_commreply_id`),
  ADD KEY `blog_id_2` (`blog_id`),
  ADD KEY `movie_id_2` (`movie_id`);

--
-- Indexes for table `wo_blogs_categories`
--
ALTER TABLE `wo_blogs_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_key` (`lang_key`);

--
-- Indexes for table `wo_blog_reaction`
--
ALTER TABLE `wo_blog_reaction`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `blog_id` (`blog_id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `reply_id` (`reply_id`);

--
-- Indexes for table `wo_booking`
--
ALTER TABLE `wo_booking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project` (`project`),
  ADD KEY `block` (`block`),
  ADD KEY `plot` (`plot`),
  ADD KEY `katha` (`katha`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `wo_booking_helper`
--
ALTER TABLE `wo_booking_helper`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `file_id` (`client_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `wo_career`
--
ALTER TABLE `wo_career`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_codes`
--
ALTER TABLE `wo_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `code` (`code`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `app_id` (`app_id`);

--
-- Indexes for table `wo_colored_posts`
--
ALTER TABLE `wo_colored_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `color_1` (`color_1`),
  ADD KEY `color_2` (`color_2`);

--
-- Indexes for table `wo_commentlikes`
--
ALTER TABLE `wo_commentlikes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `wo_comments`
--
ALTER TABLE `wo_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `order1` (`user_id`,`id`),
  ADD KEY `order2` (`page_id`,`id`),
  ADD KEY `order3` (`post_id`,`id`),
  ADD KEY `order4` (`user_id`,`id`),
  ADD KEY `order5` (`post_id`,`id`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_commentwonders`
--
ALTER TABLE `wo_commentwonders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_comment_replies`
--
ALTER TABLE `wo_comment_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `user_id` (`user_id`,`page_id`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_comment_replies_likes`
--
ALTER TABLE `wo_comment_replies_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reply_id` (`reply_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_comment_replies_wonders`
--
ALTER TABLE `wo_comment_replies_wonders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reply_id` (`reply_id`,`user_id`);

--
-- Indexes for table `wo_config`
--
ALTER TABLE `wo_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_name` (`name`);

--
-- Indexes for table `wo_custompages`
--
ALTER TABLE `wo_custompages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_type` (`page_type`);

--
-- Indexes for table `wo_custom_fields`
--
ALTER TABLE `wo_custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `wo_egoing`
--
ALTER TABLE `wo_egoing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_einterested`
--
ALTER TABLE `wo_einterested`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_einvited`
--
ALTER TABLE `wo_einvited`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `inviter_id` (`invited_id`),
  ADD KEY `inviter_id_2` (`inviter_id`);

--
-- Indexes for table `wo_emails`
--
ALTER TABLE `wo_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `email_to` (`email_to`);

--
-- Indexes for table `wo_events`
--
ALTER TABLE `wo_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `poster_id` (`poster_id`),
  ADD KEY `name` (`name`),
  ADD KEY `start_date` (`start_date`),
  ADD KEY `start_time` (`start_time`),
  ADD KEY `end_time` (`end_time`),
  ADD KEY `end_date` (`end_date`),
  ADD KEY `order1` (`poster_id`,`id`),
  ADD KEY `order2` (`id`);

--
-- Indexes for table `wo_family`
--
ALTER TABLE `wo_family`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `active` (`active`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `requesting` (`requesting`);

--
-- Indexes for table `wo_followers`
--
ALTER TABLE `wo_followers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `following_id` (`following_id`),
  ADD KEY `follower_id` (`follower_id`),
  ADD KEY `active` (`active`),
  ADD KEY `order1` (`following_id`,`id`),
  ADD KEY `order2` (`follower_id`,`id`),
  ADD KEY `is_typing` (`is_typing`),
  ADD KEY `notify` (`notify`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_forums`
--
ALTER TABLE `wo_forums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `description` (`description`(255)),
  ADD KEY `posts` (`posts`);

--
-- Indexes for table `wo_forumthreadreplies`
--
ALTER TABLE `wo_forumthreadreplies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `thread_id` (`thread_id`),
  ADD KEY `forum_id` (`forum_id`),
  ADD KEY `poster_id` (`poster_id`),
  ADD KEY `post_subject` (`post_subject`(255)),
  ADD KEY `post_quoted` (`post_quoted`),
  ADD KEY `posted_time` (`posted_time`);

--
-- Indexes for table `wo_forum_sections`
--
ALTER TABLE `wo_forum_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_name` (`section_name`),
  ADD KEY `description` (`description`(255));

--
-- Indexes for table `wo_forum_threads`
--
ALTER TABLE `wo_forum_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`),
  ADD KEY `views` (`views`),
  ADD KEY `posted` (`posted`),
  ADD KEY `headline` (`headline`(255)),
  ADD KEY `forum` (`forum`);

--
-- Indexes for table `wo_funding`
--
ALTER TABLE `wo_funding`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hashed_id` (`hashed_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_funding_raise`
--
ALTER TABLE `wo_funding_raise`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `funding_id` (`funding_id`);

--
-- Indexes for table `wo_games`
--
ALTER TABLE `wo_games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `wo_games_players`
--
ALTER TABLE `wo_games_players`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`game_id`,`active`);

--
-- Indexes for table `wo_gender`
--
ALTER TABLE `wo_gender`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gender_id` (`gender_id`);

--
-- Indexes for table `wo_gifts`
--
ALTER TABLE `wo_gifts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_groupadmins`
--
ALTER TABLE `wo_groupadmins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `members` (`members`);

--
-- Indexes for table `wo_groupchat`
--
ALTER TABLE `wo_groupchat`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_groupchatusers`
--
ALTER TABLE `wo_groupchatusers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `wo_groups`
--
ALTER TABLE `wo_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `privacy` (`privacy`),
  ADD KEY `time` (`time`),
  ADD KEY `active` (`active`),
  ADD KEY `group_title` (`group_title`),
  ADD KEY `group_name` (`group_name`),
  ADD KEY `registered` (`registered`);

--
-- Indexes for table `wo_groups_categories`
--
ALTER TABLE `wo_groups_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_group_members`
--
ALTER TABLE `wo_group_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active` (`active`),
  ADD KEY `time` (`time`),
  ADD KEY `user_id` (`user_id`,`group_id`,`active`);

--
-- Indexes for table `wo_hashtags`
--
ALTER TABLE `wo_hashtags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `last_trend_time` (`last_trend_time`),
  ADD KEY `trend_use_num` (`trend_use_num`),
  ADD KEY `tag` (`tag`),
  ADD KEY `expire` (`expire`),
  ADD KEY `hash` (`hash`);

--
-- Indexes for table `wo_hiddenposts`
--
ALTER TABLE `wo_hiddenposts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_html_emails`
--
ALTER TABLE `wo_html_emails`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_invitation_links`
--
ALTER TABLE `wo_invitation_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `code` (`code`(255)),
  ADD KEY `invited_id` (`invited_id`),
  ADD KEY `time` (`time`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_job`
--
ALTER TABLE `wo_job`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `title` (`title`),
  ADD KEY `category` (`category`),
  ADD KEY `lng` (`lng`),
  ADD KEY `lat` (`lat`),
  ADD KEY `status` (`status`),
  ADD KEY `job_type` (`job_type`),
  ADD KEY `minimum` (`minimum`),
  ADD KEY `maximum` (`maximum`);

--
-- Indexes for table `wo_job_apply`
--
ALTER TABLE `wo_job_apply`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `user_name` (`user_name`);

--
-- Indexes for table `wo_job_categories`
--
ALTER TABLE `wo_job_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_langiso`
--
ALTER TABLE `wo_langiso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_name` (`lang_name`),
  ADD KEY `iso` (`iso`),
  ADD KEY `image` (`image`);

--
-- Indexes for table `wo_langs`
--
ALTER TABLE `wo_langs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_name` (`lang_key`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `wo_likes`
--
ALTER TABLE `wo_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_live_sub_users`
--
ALTER TABLE `wo_live_sub_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `time` (`time`),
  ADD KEY `is_watching` (`is_watching`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_manage_pro`
--
ALTER TABLE `wo_manage_pro`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_messages`
--
ALTER TABLE `wo_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_id` (`from_id`),
  ADD KEY `to_id` (`to_id`),
  ADD KEY `seen` (`seen`),
  ADD KEY `time` (`time`),
  ADD KEY `deleted_two` (`deleted_two`),
  ADD KEY `deleted_one` (`deleted_one`),
  ADD KEY `sent_push` (`sent_push`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `order1` (`from_id`,`id`),
  ADD KEY `order2` (`group_id`,`id`),
  ADD KEY `order3` (`to_id`,`id`),
  ADD KEY `order7` (`seen`,`id`),
  ADD KEY `order8` (`time`,`id`),
  ADD KEY `order4` (`from_id`,`id`),
  ADD KEY `order5` (`group_id`,`id`),
  ADD KEY `order6` (`to_id`,`id`),
  ADD KEY `reply_id` (`reply_id`),
  ADD KEY `broadcast_id` (`broadcast_id`),
  ADD KEY `story_id` (`story_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `page_id_2` (`page_id`),
  ADD KEY `notification_id_2` (`notification_id`),
  ADD KEY `product_id_2` (`product_id`),
  ADD KEY `story_id_2` (`story_id`),
  ADD KEY `reply_id_2` (`reply_id`),
  ADD KEY `broadcast_id_2` (`broadcast_id`),
  ADD KEY `forward` (`forward`),
  ADD KEY `listening` (`listening`),
  ADD KEY `thread_id` (`thread_id`);

--
-- Indexes for table `wo_messages_assign`
--
ALTER TABLE `wo_messages_assign`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_messages_meta`
--
ALTER TABLE `wo_messages_meta`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_moviecommentreplies`
--
ALTER TABLE `wo_moviecommentreplies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comm_id` (`comm_id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_moviecomments`
--
ALTER TABLE `wo_moviecomments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_movies`
--
ALTER TABLE `wo_movies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `genre` (`genre`),
  ADD KEY `country` (`country`),
  ADD KEY `release` (`release`);

--
-- Indexes for table `wo_mute`
--
ALTER TABLE `wo_mute`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_id` (`chat_id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `time` (`time`),
  ADD KEY `user_id_2` (`user_id`),
  ADD KEY `chat_id_2` (`chat_id`),
  ADD KEY `message_id_2` (`message_id`),
  ADD KEY `notify` (`notify`),
  ADD KEY `type` (`type`),
  ADD KEY `fav` (`fav`);

--
-- Indexes for table `wo_notifications`
--
ALTER TABLE `wo_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifier_id` (`notifier_id`),
  ADD KEY `user_id` (`recipient_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `seen` (`seen`),
  ADD KEY `time` (`time`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `group_id` (`group_id`,`seen_pop`),
  ADD KEY `sent_push` (`sent_push`),
  ADD KEY `order1` (`seen`,`id`),
  ADD KEY `order2` (`notifier_id`,`id`),
  ADD KEY `order3` (`recipient_id`,`id`),
  ADD KEY `order4` (`post_id`,`id`),
  ADD KEY `order5` (`page_id`,`id`),
  ADD KEY `order6` (`group_id`,`id`),
  ADD KEY `order7` (`time`,`id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `reply_id` (`reply_id`),
  ADD KEY `blog_id` (`blog_id`),
  ADD KEY `story_id` (`story_id`),
  ADD KEY `admin` (`admin`),
  ADD KEY `group_chat_id` (`group_chat_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `thread_id` (`thread_id`);

--
-- Indexes for table `wo_offers`
--
ALTER TABLE `wo_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `spend` (`spend`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_pageadmins`
--
ALTER TABLE `wo_pageadmins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `wo_pagerating`
--
ALTER TABLE `wo_pagerating`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `wo_pages`
--
ALTER TABLE `wo_pages`
  ADD PRIMARY KEY (`page_id`),
  ADD KEY `registered` (`registered`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `page_category` (`page_category`),
  ADD KEY `active` (`active`),
  ADD KEY `verified` (`verified`),
  ADD KEY `boosted` (`boosted`),
  ADD KEY `time` (`time`),
  ADD KEY `page_name` (`page_name`),
  ADD KEY `page_title` (`page_title`),
  ADD KEY `sub_category` (`sub_category`);

--
-- Indexes for table `wo_pages_categories`
--
ALTER TABLE `wo_pages_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_pages_invites`
--
ALTER TABLE `wo_pages_invites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `page_id` (`page_id`,`inviter_id`,`invited_id`);

--
-- Indexes for table `wo_pages_likes`
--
ALTER TABLE `wo_pages_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `active` (`active`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `wo_patreonsubscribers`
--
ALTER TABLE `wo_patreonsubscribers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `subscriber_id` (`subscriber_id`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_payments`
--
ALTER TABLE `wo_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `wo_payment_transactions`
--
ALTER TABLE `wo_payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `userid` (`userid`),
  ADD KEY `kind` (`kind`),
  ADD KEY `transaction_dt` (`transaction_dt`);

--
-- Indexes for table `wo_pendingpayments`
--
ALTER TABLE `wo_pendingpayments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `payment_data` (`payment_data`),
  ADD KEY `method_name` (`method_name`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_pinnedposts`
--
ALTER TABLE `wo_pinnedposts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `active` (`active`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `wo_pokes`
--
ALTER TABLE `wo_pokes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `received_user_id` (`received_user_id`),
  ADD KEY `user_id` (`send_user_id`);

--
-- Indexes for table `wo_polls`
--
ALTER TABLE `wo_polls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_posts`
--
ALTER TABLE `wo_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `postFile` (`postFile`),
  ADD KEY `postShare` (`postShare`),
  ADD KEY `postType` (`postType`),
  ADD KEY `postYoutube` (`postYoutube`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `registered` (`registered`),
  ADD KEY `time` (`time`),
  ADD KEY `boosted` (`boosted`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `poll_id` (`poll_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `videoViews` (`videoViews`),
  ADD KEY `shared_from` (`shared_from`),
  ADD KEY `order1` (`user_id`,`id`),
  ADD KEY `order2` (`page_id`,`id`),
  ADD KEY `order3` (`group_id`,`id`),
  ADD KEY `order4` (`recipient_id`,`id`),
  ADD KEY `order5` (`event_id`,`id`),
  ADD KEY `order6` (`parent_id`,`id`),
  ADD KEY `multi_image` (`multi_image`),
  ADD KEY `album_name` (`album_name`),
  ADD KEY `postFacebook` (`postFacebook`),
  ADD KEY `postVimeo` (`postVimeo`),
  ADD KEY `postDailymotion` (`postDailymotion`),
  ADD KEY `postSoundCloud` (`postSoundCloud`),
  ADD KEY `postYoutube_2` (`postYoutube`),
  ADD KEY `fund_raise_id` (`fund_raise_id`),
  ADD KEY `fund_id` (`fund_id`),
  ADD KEY `offer_id` (`offer_id`),
  ADD KEY `live_time` (`live_time`),
  ADD KEY `live_ended` (`live_ended`),
  ADD KEY `active` (`active`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `page_event_id` (`page_event_id`),
  ADD KEY `blog_id` (`blog_id`),
  ADD KEY `color_id` (`color_id`),
  ADD KEY `thread_id` (`thread_id`),
  ADD KEY `forum_id` (`forum_id`),
  ADD KEY `processing` (`processing`);

--
-- Indexes for table `wo_productreview`
--
ALTER TABLE `wo_productreview`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `star` (`star`);

--
-- Indexes for table `wo_products`
--
ALTER TABLE `wo_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category` (`category`),
  ADD KEY `price` (`price`),
  ADD KEY `status` (`status`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `active` (`active`),
  ADD KEY `units` (`units`);

--
-- Indexes for table `wo_products_categories`
--
ALTER TABLE `wo_products_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_products_media`
--
ALTER TABLE `wo_products_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `wo_profilefields`
--
ALTER TABLE `wo_profilefields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `registration_page` (`registration_page`),
  ADD KEY `active` (`active`),
  ADD KEY `profile_page` (`profile_page`);

--
-- Indexes for table `wo_purchases`
--
ALTER TABLE `wo_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `timestamp` (`timestamp`),
  ADD KEY `time` (`time`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `final_price` (`final_price`),
  ADD KEY `order_hash_id` (`order_hash_id`),
  ADD KEY `data` (`data`(1024));

--
-- Indexes for table `wo_reactions`
--
ALTER TABLE `wo_reactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_reaction` (`reaction`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `message_id_2` (`message_id`),
  ADD KEY `replay_id` (`replay_id`),
  ADD KEY `story_id` (`story_id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `comment_id_2` (`comment_id`),
  ADD KEY `replay_id_2` (`replay_id`),
  ADD KEY `message_id_3` (`message_id`),
  ADD KEY `story_id_2` (`story_id`);

--
-- Indexes for table `wo_reactions_types`
--
ALTER TABLE `wo_reactions_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_recentsearches`
--
ALTER TABLE `wo_recentsearches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`search_id`),
  ADD KEY `search_type` (`search_type`);

--
-- Indexes for table `wo_refund`
--
ALTER TABLE `wo_refund`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `pro_type` (`pro_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `wo_relationship`
--
ALTER TABLE `wo_relationship`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_id` (`from_id`),
  ADD KEY `relationship` (`relationship`),
  ADD KEY `active` (`active`),
  ADD KEY `to_id` (`to_id`);

--
-- Indexes for table `wo_reports`
--
ALTER TABLE `wo_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `seen` (`seen`),
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `comment_id` (`comment_id`);

--
-- Indexes for table `wo_savedposts`
--
ALTER TABLE `wo_savedposts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_stickers`
--
ALTER TABLE `wo_stickers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_story_seen`
--
ALTER TABLE `wo_story_seen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `story_id` (`story_id`);

--
-- Indexes for table `wo_sub_categories`
--
ALTER TABLE `wo_sub_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `lang_key` (`lang_key`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `wo_terms`
--
ALTER TABLE `wo_terms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wo_tokens`
--
ALTER TABLE `wo_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `user_id_2` (`user_id`),
  ADD KEY `app_id` (`app_id`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `wo_uploadedmedia`
--
ALTER TABLE `wo_uploadedmedia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `filename` (`filename`),
  ADD KEY `time` (`time`),
  ADD KEY `filename_2` (`filename`,`storage`);

--
-- Indexes for table `wo_useraddress`
--
ALTER TABLE `wo_useraddress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_userads`
--
ALTER TABLE `wo_userads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appears` (`appears`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `location` (`location`(255)),
  ADD KEY `gender` (`gender`),
  ADD KEY `status` (`status`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `wo_userads_data`
--
ALTER TABLE `wo_userads_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `ad_id` (`ad_id`);

--
-- Indexes for table `wo_usercard`
--
ALTER TABLE `wo_usercard`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `units` (`units`);

--
-- Indexes for table `wo_usercertification`
--
ALTER TABLE `wo_usercertification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_userexperience`
--
ALTER TABLE `wo_userexperience`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `wo_userfields`
--
ALTER TABLE `wo_userfields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wo_userlanguages`
--
ALTER TABLE `wo_userlanguages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lang_key` (`lang_key`);

--
-- Indexes for table `wo_useropento`
--
ALTER TABLE `wo_useropento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `job_title` (`job_title`),
  ADD KEY `job_location` (`job_location`),
  ADD KEY `workplaces` (`workplaces`),
  ADD KEY `job_type` (`job_type`),
  ADD KEY `type` (`type`),
  ADD KEY `time` (`time`),
  ADD KEY `services` (`services`),
  ADD KEY `description` (`description`);

--
-- Indexes for table `wo_userorders`
--
ALTER TABLE `wo_userorders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_owner_id` (`product_owner_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `final_price` (`final_price`),
  ADD KEY `status` (`status`),
  ADD KEY `time` (`time`),
  ADD KEY `hash_id` (`hash_id`),
  ADD KEY `units` (`units`),
  ADD KEY `address_id` (`address_id`);

--
-- Indexes for table `wo_userprojects`
--
ALTER TABLE `wo_userprojects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `wo_users`
--
ALTER TABLE `wo_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `active` (`active`),
  ADD KEY `admin` (`admin`),
  ADD KEY `src` (`src`),
  ADD KEY `gender` (`gender`),
  ADD KEY `avatar` (`avatar`),
  ADD KEY `first_name` (`first_name`),
  ADD KEY `last_name` (`last_name`),
  ADD KEY `registered` (`registered`),
  ADD KEY `joined` (`joined`),
  ADD KEY `phone_number` (`phone_number`) USING BTREE,
  ADD KEY `referrer` (`referrer`),
  ADD KEY `wallet` (`wallet`),
  ADD KEY `friend_privacy` (`friend_privacy`),
  ADD KEY `lat` (`lat`),
  ADD KEY `lng` (`lng`),
  ADD KEY `order1` (`username`,`user_id`),
  ADD KEY `order2` (`email`,`user_id`),
  ADD KEY `order3` (`lastseen`,`user_id`),
  ADD KEY `order4` (`active`,`user_id`),
  ADD KEY `last_data_update` (`last_data_update`),
  ADD KEY `points` (`points`),
  ADD KEY `paystack_ref` (`paystack_ref`),
  ADD KEY `relationship_id` (`relationship_id`),
  ADD KEY `post_privacy` (`post_privacy`),
  ADD KEY `email_code` (`email_code`),
  ADD KEY `password` (`password`),
  ADD KEY `status` (`status`),
  ADD KEY `type` (`type`),
  ADD KEY `is_pro` (`is_pro`),
  ADD KEY `ref_user_id` (`ref_user_id`),
  ADD KEY `currently_working` (`currently_working`),
  ADD KEY `banned` (`banned`),
  ADD KEY `two_factor_hash` (`two_factor_hash`),
  ADD KEY `pro_remainder` (`pro_remainder`),
  ADD KEY `converted_points` (`converted_points`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `designation` (`designation`),
  ADD KEY `joining_date` (`joining_date`),
  ADD KEY `is_team_leader` (`is_team_leader`),
  ADD KEY `leader_id` (`leader_id`),
  ADD KEY `position` (`position`),
  ADD KEY `exclude_attendance` (`exclude_attendance`),
  ADD KEY `management` (`management`),
  ADD KEY `maintainance_override` (`maintainance_override`);

--
-- Indexes for table `wo_userschat`
--
ALTER TABLE `wo_userschat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`assigned`),
  ADD KEY `time` (`time`),
  ADD KEY `order1` (`assigned`,`id`),
  ADD KEY `order2` (`assigned`,`id`),
  ADD KEY `order3` (`id`),
  ADD KEY `order4` (`id`),
  ADD KEY `page_id` (`page_id`),
  ADD KEY `color` (`color`),
  ADD KEY `thread_id` (`thread_id`);

--
-- Indexes for table `wo_userskills`
--
ALTER TABLE `wo_userskills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `wo_userstory`
--
ALTER TABLE `wo_userstory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expires` (`expire`);

--
-- Indexes for table `wo_userstorymedia`
--
ALTER TABLE `wo_userstorymedia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expire` (`expire`),
  ADD KEY `story_id` (`story_id`);

--
-- Indexes for table `wo_usertiers`
--
ALTER TABLE `wo_usertiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `chat` (`chat`),
  ADD KEY `live_stream` (`live_stream`);

--
-- Indexes for table `wo_user_gifts`
--
ALTER TABLE `wo_user_gifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from` (`from`),
  ADD KEY `to` (`to`),
  ADD KEY `gift_id` (`gift_id`);

--
-- Indexes for table `wo_verification_requests`
--
ALTER TABLE `wo_verification_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `page_id` (`page_id`);

--
-- Indexes for table `wo_videocalles`
--
ALTER TABLE `wo_videocalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `to_id` (`to_id`),
  ADD KEY `from_id` (`from_id`),
  ADD KEY `called` (`called`),
  ADD KEY `declined` (`declined`);

--
-- Indexes for table `wo_votes`
--
ALTER TABLE `wo_votes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `option_id` (`option_id`);

--
-- Indexes for table `wo_wonders`
--
ALTER TABLE `wo_wonders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `type` (`type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atten_in_out`
--
ALTER TABLE `atten_in_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atten_reason`
--
ALTER TABLE `atten_reason`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `atten_users`
--
ALTER TABLE `atten_users`
  MODIFY `USERID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_schedules`
--
ALTER TABLE `backup_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_receipts`
--
ALTER TABLE `bank_receipts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `broadcast`
--
ALTER TABLE `broadcast`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `broadcast_users`
--
ALTER TABLE `broadcast_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients_review`
--
ALTER TABLE `clients_review`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_advance`
--
ALTER TABLE `crm_advance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_bazar`
--
ALTER TABLE `crm_bazar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_bazar_items`
--
ALTER TABLE `crm_bazar_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_bazar_logs`
--
ALTER TABLE `crm_bazar_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_bazar_price_history`
--
ALTER TABLE `crm_bazar_price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_bazar_quantity_history`
--
ALTER TABLE `crm_bazar_quantity_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_bazar_usage_history`
--
ALTER TABLE `crm_bazar_usage_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_customers`
--
ALTER TABLE `crm_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_debit`
--
ALTER TABLE `crm_debit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_holidays`
--
ALTER TABLE `crm_holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_invoice`
--
ALTER TABLE `crm_invoice`
  MODIFY `inv_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_items_master`
--
ALTER TABLE `crm_items_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_leads`
--
ALTER TABLE `crm_leads`
  MODIFY `lead_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_leads_report`
--
ALTER TABLE `crm_leads_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_lead_reassignments`
--
ALTER TABLE `crm_lead_reassignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_leaves`
--
ALTER TABLE `crm_leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_locations`
--
ALTER TABLE `crm_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_nominees`
--
ALTER TABLE `crm_nominees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_projects`
--
ALTER TABLE `crm_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_purchase`
--
ALTER TABLE `crm_purchase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_remarks`
--
ALTER TABLE `crm_remarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_rent_report`
--
ALTER TABLE `crm_rent_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_salary`
--
ALTER TABLE `crm_salary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_sms_report`
--
ALTER TABLE `crm_sms_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_stock`
--
ALTER TABLE `crm_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_stock_price_history`
--
ALTER TABLE `crm_stock_price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_stock_quantity_history`
--
ALTER TABLE `crm_stock_quantity_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crm_stock_usage_history`
--
ALTER TABLE `crm_stock_usage_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `field_positions`
--
ALTER TABLE `field_positions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_activity_log`
--
ALTER TABLE `fm_activity_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_common_folders`
--
ALTER TABLE `fm_common_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_files`
--
ALTER TABLE `fm_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_file_shares`
--
ALTER TABLE `fm_file_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_file_versions`
--
ALTER TABLE `fm_file_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_folder_access`
--
ALTER TABLE `fm_folder_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_folder_structure`
--
ALTER TABLE `fm_folder_structure`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_permissions`
--
ALTER TABLE `fm_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_recycle_bin`
--
ALTER TABLE `fm_recycle_bin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_special_folders`
--
ALTER TABLE `fm_special_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_system_settings`
--
ALTER TABLE `fm_system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_thumbnails`
--
ALTER TABLE `fm_thumbnails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fm_upload_queue`
--
ALTER TABLE `fm_upload_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `home_slider`
--
ALTER TABLE `home_slider`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `key_locations`
--
ALTER TABLE `key_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications_views`
--
ALTER TABLE `notifications_views`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `photo_gallery`
--
ALTER TABLE `photo_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restore_history`
--
ALTER TABLE `restore_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wondertage_settings`
--
ALTER TABLE `wondertage_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_activities`
--
ALTER TABLE `wo_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_admininvitations`
--
ALTER TABLE `wo_admininvitations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_ads`
--
ALTER TABLE `wo_ads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_affiliates_requests`
--
ALTER TABLE `wo_affiliates_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_agoravideocall`
--
ALTER TABLE `wo_agoravideocall`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_albums_media`
--
ALTER TABLE `wo_albums_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_announcement`
--
ALTER TABLE `wo_announcement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_announcement_views`
--
ALTER TABLE `wo_announcement_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_apps`
--
ALTER TABLE `wo_apps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_appssessions`
--
ALTER TABLE `wo_appssessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_apps_hash`
--
ALTER TABLE `wo_apps_hash`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_apps_permission`
--
ALTER TABLE `wo_apps_permission`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_audiocalls`
--
ALTER TABLE `wo_audiocalls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_backup_codes`
--
ALTER TABLE `wo_backup_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_bad_login`
--
ALTER TABLE `wo_bad_login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_banned_ip`
--
ALTER TABLE `wo_banned_ip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blocks`
--
ALTER TABLE `wo_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blog`
--
ALTER TABLE `wo_blog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blogcommentreplies`
--
ALTER TABLE `wo_blogcommentreplies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blogcomments`
--
ALTER TABLE `wo_blogcomments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blogmoviedislikes`
--
ALTER TABLE `wo_blogmoviedislikes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blogmovielikes`
--
ALTER TABLE `wo_blogmovielikes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blogs_categories`
--
ALTER TABLE `wo_blogs_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_blog_reaction`
--
ALTER TABLE `wo_blog_reaction`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_booking`
--
ALTER TABLE `wo_booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_booking_helper`
--
ALTER TABLE `wo_booking_helper`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_career`
--
ALTER TABLE `wo_career`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_codes`
--
ALTER TABLE `wo_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_colored_posts`
--
ALTER TABLE `wo_colored_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_commentlikes`
--
ALTER TABLE `wo_commentlikes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_comments`
--
ALTER TABLE `wo_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_commentwonders`
--
ALTER TABLE `wo_commentwonders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_comment_replies`
--
ALTER TABLE `wo_comment_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_comment_replies_likes`
--
ALTER TABLE `wo_comment_replies_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_comment_replies_wonders`
--
ALTER TABLE `wo_comment_replies_wonders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_config`
--
ALTER TABLE `wo_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_custompages`
--
ALTER TABLE `wo_custompages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_custom_fields`
--
ALTER TABLE `wo_custom_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_egoing`
--
ALTER TABLE `wo_egoing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_einterested`
--
ALTER TABLE `wo_einterested`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_einvited`
--
ALTER TABLE `wo_einvited`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_emails`
--
ALTER TABLE `wo_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_events`
--
ALTER TABLE `wo_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_family`
--
ALTER TABLE `wo_family`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_followers`
--
ALTER TABLE `wo_followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_forums`
--
ALTER TABLE `wo_forums`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_forumthreadreplies`
--
ALTER TABLE `wo_forumthreadreplies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_forum_sections`
--
ALTER TABLE `wo_forum_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_forum_threads`
--
ALTER TABLE `wo_forum_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_funding`
--
ALTER TABLE `wo_funding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_funding_raise`
--
ALTER TABLE `wo_funding_raise`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_games`
--
ALTER TABLE `wo_games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_games_players`
--
ALTER TABLE `wo_games_players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_gender`
--
ALTER TABLE `wo_gender`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_gifts`
--
ALTER TABLE `wo_gifts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_groupadmins`
--
ALTER TABLE `wo_groupadmins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_groupchat`
--
ALTER TABLE `wo_groupchat`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_groupchatusers`
--
ALTER TABLE `wo_groupchatusers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_groups`
--
ALTER TABLE `wo_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_groups_categories`
--
ALTER TABLE `wo_groups_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_group_members`
--
ALTER TABLE `wo_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_hashtags`
--
ALTER TABLE `wo_hashtags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_hiddenposts`
--
ALTER TABLE `wo_hiddenposts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_html_emails`
--
ALTER TABLE `wo_html_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_invitation_links`
--
ALTER TABLE `wo_invitation_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_job`
--
ALTER TABLE `wo_job`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_job_apply`
--
ALTER TABLE `wo_job_apply`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_job_categories`
--
ALTER TABLE `wo_job_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_langiso`
--
ALTER TABLE `wo_langiso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_langs`
--
ALTER TABLE `wo_langs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_likes`
--
ALTER TABLE `wo_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_live_sub_users`
--
ALTER TABLE `wo_live_sub_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_manage_pro`
--
ALTER TABLE `wo_manage_pro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_messages`
--
ALTER TABLE `wo_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_messages_assign`
--
ALTER TABLE `wo_messages_assign`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_messages_meta`
--
ALTER TABLE `wo_messages_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_moviecommentreplies`
--
ALTER TABLE `wo_moviecommentreplies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_moviecomments`
--
ALTER TABLE `wo_moviecomments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_movies`
--
ALTER TABLE `wo_movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_mute`
--
ALTER TABLE `wo_mute`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_notifications`
--
ALTER TABLE `wo_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_offers`
--
ALTER TABLE `wo_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pageadmins`
--
ALTER TABLE `wo_pageadmins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pagerating`
--
ALTER TABLE `wo_pagerating`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pages`
--
ALTER TABLE `wo_pages`
  MODIFY `page_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pages_categories`
--
ALTER TABLE `wo_pages_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pages_invites`
--
ALTER TABLE `wo_pages_invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pages_likes`
--
ALTER TABLE `wo_pages_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_patreonsubscribers`
--
ALTER TABLE `wo_patreonsubscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_payments`
--
ALTER TABLE `wo_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_payment_transactions`
--
ALTER TABLE `wo_payment_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pendingpayments`
--
ALTER TABLE `wo_pendingpayments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pinnedposts`
--
ALTER TABLE `wo_pinnedposts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_pokes`
--
ALTER TABLE `wo_pokes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_polls`
--
ALTER TABLE `wo_polls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_posts`
--
ALTER TABLE `wo_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_productreview`
--
ALTER TABLE `wo_productreview`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_products`
--
ALTER TABLE `wo_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_products_categories`
--
ALTER TABLE `wo_products_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_products_media`
--
ALTER TABLE `wo_products_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_profilefields`
--
ALTER TABLE `wo_profilefields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_purchases`
--
ALTER TABLE `wo_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_reactions`
--
ALTER TABLE `wo_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_reactions_types`
--
ALTER TABLE `wo_reactions_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_recentsearches`
--
ALTER TABLE `wo_recentsearches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_refund`
--
ALTER TABLE `wo_refund`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_relationship`
--
ALTER TABLE `wo_relationship`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_reports`
--
ALTER TABLE `wo_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_savedposts`
--
ALTER TABLE `wo_savedposts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_stickers`
--
ALTER TABLE `wo_stickers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_story_seen`
--
ALTER TABLE `wo_story_seen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_sub_categories`
--
ALTER TABLE `wo_sub_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_terms`
--
ALTER TABLE `wo_terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_tokens`
--
ALTER TABLE `wo_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_uploadedmedia`
--
ALTER TABLE `wo_uploadedmedia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_useraddress`
--
ALTER TABLE `wo_useraddress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userads`
--
ALTER TABLE `wo_userads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userads_data`
--
ALTER TABLE `wo_userads_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_usercard`
--
ALTER TABLE `wo_usercard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_usercertification`
--
ALTER TABLE `wo_usercertification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userexperience`
--
ALTER TABLE `wo_userexperience`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userfields`
--
ALTER TABLE `wo_userfields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userlanguages`
--
ALTER TABLE `wo_userlanguages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_useropento`
--
ALTER TABLE `wo_useropento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userorders`
--
ALTER TABLE `wo_userorders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userprojects`
--
ALTER TABLE `wo_userprojects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_users`
--
ALTER TABLE `wo_users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userschat`
--
ALTER TABLE `wo_userschat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userskills`
--
ALTER TABLE `wo_userskills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userstory`
--
ALTER TABLE `wo_userstory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_userstorymedia`
--
ALTER TABLE `wo_userstorymedia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_usertiers`
--
ALTER TABLE `wo_usertiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_user_gifts`
--
ALTER TABLE `wo_user_gifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_verification_requests`
--
ALTER TABLE `wo_verification_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_videocalles`
--
ALTER TABLE `wo_videocalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_votes`
--
ALTER TABLE `wo_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wo_wonders`
--
ALTER TABLE `wo_wonders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
