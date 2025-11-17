-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 17, 2025 at 11:42 AM
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
-- Database: `civicbd_group`
--

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

--
-- Dumping data for table `crm_customers`
--

INSERT INTO `crm_customers` (`id`, `name`, `phone`, `address`, `permanent_addr`, `profession`, `email`, `nationality`, `birthday`, `religion`, `nid`, `passport`, `time`, `additional`, `additional_json`, `spouse_name`, `fathers_name`, `mothers_name`, `reference`) VALUES
(36, 'Md. Kawsar', '+8801907110816', 'sdafsadf,asdf #4/6, kasd', 'sdafsadf,asdf #4/6, kasd', NULL, 'civicbackup22@gmail.com', 'Bangladeshi', '2025-11-25', 'Muslim', '24152311241', '234234141241234', '2025-11-17 05:44:36', 'a:12:{s:11:\"spouse_name\";s:4:\"Amin\";s:12:\"fathers_name\";s:4:\"asdf\";s:12:\"mothers_name\";s:4:\"asdf\";s:14:\"permanent_addr\";s:24:\"sdafsadf,asdf #4/6, kasd\";s:9:\"reference\";s:2:\"59\";s:10:\"profession\";s:0:\"\";s:5:\"email\";s:23:\"civicbackup22@gmail.com\";s:11:\"nationality\";s:11:\"Bangladeshi\";s:8:\"birthday\";s:10:\"2025-11-25\";s:8:\"religion\";s:6:\"Muslim\";s:3:\"nid\";s:11:\"24152311241\";s:8:\"passport\";s:15:\"234234141241234\";}', '{\"phone_raw\":\"01907110816\"}', 'Amin', 'asdf', 'asdf', '59'),
(37, 'MMA Khondaker', '+8801907110814', '4/6, Road-9 (Level-6), Block-J, Baridhara', '4/6, Road-9 (Level-6), Block-J, Baridhara', NULL, 'civicbackup22@gmail.com', 'Bangladeshi', '2025-11-02', 'Muslim', '23423423', '2342312412344', '2025-11-17 05:49:29', 'a:12:{s:11:\"spouse_name\";s:10:\"asdfasdfwe\";s:12:\"fathers_name\";s:4:\"werq\";s:12:\"mothers_name\";s:6:\"erqwer\";s:14:\"permanent_addr\";s:41:\"4/6, Road-9 (Level-6), Block-J, Baridhara\";s:9:\"reference\";s:3:\"112\";s:10:\"profession\";s:0:\"\";s:5:\"email\";s:23:\"civicbackup22@gmail.com\";s:11:\"nationality\";s:11:\"Bangladeshi\";s:8:\"birthday\";s:10:\"2025-11-02\";s:8:\"religion\";s:6:\"Muslim\";s:3:\"nid\";s:8:\"23423423\";s:8:\"passport\";s:13:\"2342312412344\";}', '{\"phone_raw\":\"01907110814\"}', 'asdfasdfwe', 'werq', 'erqwer', '112'),
(38, 'Khondaker', '+8801907110812', '4/6, Road-9 (Level-6), Block-J, Baridhara', '4/6, Road-9 (Level-6), Block-J, Baridhara', NULL, 'civicbackup22@gmail.com', 'Bangladeshi', '2025-12-09', 'Muslim', '2415311241', '23424534', '2025-11-17 05:50:08', 'a:12:{s:11:\"spouse_name\";s:7:\"asdfasd\";s:12:\"fathers_name\";s:5:\"fasdf\";s:12:\"mothers_name\";s:4:\"asdf\";s:14:\"permanent_addr\";s:41:\"4/6, Road-9 (Level-6), Block-J, Baridhara\";s:9:\"reference\";s:3:\"112\";s:10:\"profession\";s:0:\"\";s:5:\"email\";s:23:\"civicbackup22@gmail.com\";s:11:\"nationality\";s:11:\"Bangladeshi\";s:8:\"birthday\";s:10:\"2025-12-09\";s:8:\"religion\";s:6:\"Muslim\";s:3:\"nid\";s:10:\"2415311241\";s:8:\"passport\";s:8:\"23424534\";}', '{\"phone_raw\":\"01907110812\"}', 'asdfasd', 'fasdf', 'asdf', '112');

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

--
-- Dumping data for table `crm_nominees`
--

INSERT INTO `crm_nominees` (`id`, `customer_id`, `name`, `address`, `birthday`, `share_parcent`, `relation`, `phone`, `time`) VALUES
(15, 34, 'asdfasf', 'fadsfasdf', 955130400, '32', 'Wife', '01907110816', '2025-11-09 07:01:16'),
(16, 35, 'sadf', 'wqrerewq', 1761588000, '32', 'Wife', '01907110816', '2025-11-09 07:02:47'),
(17, 36, 'adfasdf', 'sdafsadf,asdf #4/6, kasd', 1764093600, '43', 'Wife', '01907110816', '2025-11-17 05:44:36'),
(18, 37, 'adsf', '4/6, Road-9 (Level-6), Block-J, Baridhara', 1764525600, '32', 'Daughter', '01907110811', '2025-11-17 05:49:29'),
(19, 38, 'asdfasd', '4/6, Road-9 (Level-6), Block-J, Baridhara', 1764612000, '45', 'Brother', '01907110814', '2025-11-17 05:50:08');

-- --------------------------------------------------------

--
-- Table structure for table `crm_payment_schedule`
--

CREATE TABLE `crm_payment_schedule` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'References crm_customers.id',
  `installment_number` int(11) NOT NULL COMMENT 'Sequence number (1, 2, 3...)',
  `particular` varchar(255) DEFAULT NULL COMMENT 'e.g., "1st Installment", "Booking Money"',
  `type` varchar(120) NOT NULL DEFAULT '',
  `due_date` date DEFAULT NULL,
  `installment_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Installment amount',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually paid',
  `payment_date` date DEFAULT NULL COMMENT 'When payment was made',
  `payment_method` varchar(100) DEFAULT NULL COMMENT 'Cash/Cheque/Bank Transfer/Online',
  `money_receipt_no` varchar(100) DEFAULT NULL COMMENT 'Receipt number',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=overdue, 4=cancelled',
  `is_adjustment` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=regular, 1=adjustment entry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated',
  `transfer_affected_by` int(11) DEFAULT NULL COMMENT 'Transfer ID that affected this schedule',
  `recalculated_due_to` varchar(50) DEFAULT NULL COMMENT 'Reason for recalculation: plot_change, rate_change, transfer',
  `recalculation_date` datetime DEFAULT NULL COMMENT 'When schedule was recalculated',
  `previous_amount` decimal(12,2) DEFAULT NULL COMMENT 'Track changes for audit',
  `change_reason` text DEFAULT NULL COMMENT 'Reason for any adjustments',
  `manual_adjustment` tinyint(1) DEFAULT 0,
  `manual_edit` tinyint(1) DEFAULT 0,
  `history_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_payment_schedule`
--

INSERT INTO `crm_payment_schedule` (`id`, `purchase_id`, `client_id`, `installment_number`, `particular`, `type`, `due_date`, `installment_amount`, `paid_amount`, `payment_date`, `payment_method`, `money_receipt_no`, `remarks`, `status`, `is_adjustment`, `created_at`, `updated_at`, `created_by`, `updated_by`, `transfer_affected_by`, `recalculated_due_to`, `recalculation_date`, `previous_amount`, `change_reason`, `manual_adjustment`, `manual_edit`, `history_json`) VALUES
(1, 1, 37, 0, 'Booking Money', 'booking', '2025-11-17', 50.00, 50.00, '2025-11-17', '', '', 'Plot cancelled. Reason: fasdf', 4, 0, '2025-11-17 12:02:49', '2025-11-17 17:31:36', 1, 1, NULL, NULL, NULL, 50.00, NULL, 0, 0, NULL),
(2, 1, 37, 0, 'Down Payment', 'down', '2025-11-17', 500.00, 500.00, '2025-11-17', '', '', 'Plot cancelled. Reason: fasdf', 4, 0, '2025-11-17 12:02:49', '2025-11-17 17:31:36', 1, 1, NULL, NULL, NULL, 500.00, NULL, 0, 0, NULL),
(3, 1, 37, 1, '1st Installment', 'installment', '2025-06-01', 100.00, 100.00, '2025-06-01', '', '', 'Plot cancelled. Reason: fasdf', 4, 0, '2025-11-17 12:02:49', '2025-11-17 17:31:36', 1, 1, NULL, NULL, NULL, 100.00, NULL, 0, 0, NULL),
(4, 1, 37, 2, '2th Installment', 'installment', '2025-07-01', 100.00, 100.00, '2025-07-01', '', '', 'Plot cancelled. Reason: fasdf', 4, 0, '2025-11-17 12:02:49', '2025-11-17 17:31:36', 1, 1, NULL, NULL, NULL, 100.00, NULL, 0, 0, NULL),
(5, 1, 37, 3, '3th Installment', 'installment', '2025-08-01', 100.00, 0.00, NULL, '', '', 'Plot cancelled. Reason: fasdf', 4, 0, '2025-11-17 12:02:49', '2025-11-17 17:31:36', 1, 1, NULL, NULL, NULL, 100.00, NULL, 0, 0, NULL),

-- --------------------------------------------------------

--
-- Table structure for table `crm_refund_schedule`
--

CREATE TABLE `crm_refund_schedule` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'References crm_customers.id',
  `refund_initiation_date` date NOT NULL COMMENT 'Date when refund was requested',
  `total_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount client has paid',
  `deduction_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Penalty percentage (5-25%)',
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Calculated penalty amount',
  `refundable_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount to refund after deduction',
  `installment_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Refund installment sequence (1, 2, 3...)',
  `installment_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount for this refund installment',
  `due_date` date DEFAULT NULL COMMENT 'When this refund installment is due',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually refunded',
  `payment_date` date DEFAULT NULL COMMENT 'When refund was paid',
  `payment_method` varchar(100) DEFAULT NULL COMMENT 'Cash/Cheque/Bank Transfer/Online',
  `money_receipt_no` varchar(100) DEFAULT NULL COMMENT 'Receipt number',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=cancelled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Refund schedule with deduction tracking';

--
-- Dumping data for table `crm_refund_schedule`
--

INSERT INTO `crm_refund_schedule` (`id`, `purchase_id`, `client_id`, `refund_initiation_date`, `total_paid_amount`, `deduction_percentage`, `deduction_amount`, `refundable_amount`, `installment_number`, `installment_amount`, `due_date`, `paid_amount`, `payment_date`, `payment_method`, `money_receipt_no`, `remarks`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 11, 32, '2025-11-13', 750000.00, 10.00, 75000.00, 675000.00, 1, 675000.00, '2026-01-09', 0.00, NULL, NULL, NULL, NULL, 0, '2025-11-13 12:34:07', '2025-11-13 12:34:07', 1, NULL),
(2, 14, 35, '2025-11-16', 283638.00, 0.00, 32.00, 283606.00, 1, 283606.00, '2025-12-16', 0.00, NULL, NULL, NULL, NULL, 0, '2025-11-16 11:05:46', '2025-11-16 11:05:46', 1, NULL),
(3, 12, 34, '2025-11-17', 0.00, 0.00, 0.00, 0.00, 1, 0.00, '2025-12-17', 0.00, NULL, NULL, NULL, NULL, 0, '2025-11-17 04:31:49', '2025-11-17 04:31:49', 1, NULL),
(4, 1, 36, '2025-11-17', 750.00, 10.00, 75.00, 675.00, 1, 675.00, '2025-12-17', 0.00, NULL, NULL, NULL, NULL, 0, '2025-11-17 17:18:32', '2025-11-17 11:18:32', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `crm_refund_transactions`
--

CREATE TABLE `crm_refund_transactions` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'References crm_customers.id',
  `refund_request_date` date NOT NULL COMMENT 'Date refund was requested',
  `total_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount client paid',
  `deduction_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Penalty percentage (5-25%)',
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Calculated penalty amount',
  `refundable_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount available for refund',
  `transaction_date` date NOT NULL COMMENT 'Date of this refund transaction',
  `transaction_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount refunded in this transaction (custom)',
  `payment_method` varchar(100) DEFAULT NULL COMMENT 'Cash/Bank Transfer/Cheque/bKash',
  `money_receipt_no` varchar(100) DEFAULT NULL COMMENT 'Receipt/transaction reference',
  `remarks` text DEFAULT NULL COMMENT 'Transaction notes',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=completed, 3=cancelled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User who created',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User who last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Flexible refund transactions with custom amounts per transaction';

--
-- Dumping data for table `crm_refund_transactions`
--

INSERT INTO `crm_refund_transactions` (`id`, `purchase_id`, `client_id`, `refund_request_date`, `total_paid_amount`, `deduction_percentage`, `deduction_amount`, `refundable_amount`, `transaction_date`, `transaction_amount`, `payment_method`, `money_receipt_no`, `remarks`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 13, 35, '2025-11-16', 2577.00, 10.00, 257.70, 2319.30, '2025-11-16', 45.00, 'Bank Transfer', '14', 'asdfsadf', 1, '2025-11-16 08:44:10', '2025-11-16 08:44:10', NULL, NULL),
(2, 11, 32, '2025-11-16', 750000.00, 10.00, 75000.00, 675000.00, '2025-11-16', 2342.00, 'Cash', '234', 'CANCELLED: Cancelled by user', 3, '2025-11-16 08:48:16', '2025-11-17 10:18:23', NULL, NULL),
(3, 11, 32, '2025-11-16', 750000.00, 10.00, 75000.00, 675000.00, '2025-11-19', 324324.00, 'Cash', '2342', 'CANCELLED: Cancelled by user', 3, '2025-11-16 08:51:49', '2025-11-16 15:25:43', NULL, NULL);

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
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0 or 1=avillable, 2=sold, 3=complete, 4=canceled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wo_booking`
--

INSERT INTO `wo_booking` (`id`, `project`, `block`, `road`, `plot`, `katha`, `facing`, `file_num`, `object_id`, `status`) VALUES
(1, 'hill-town', 'b', 'Road 1/A', '20/A', '6.59', 'west', '', 0, 4),
(2, 'hill-town', 'a', 'Road 1', '1', '11.52', 'south', '2', 0, 2),
(3, 'hill-town', 'a', 'Road 1', '3', '9.83', 'south', '', 0, 0),
(4, 'hill-town', 'a', 'Road 1', '2', '5.22', 'north', '', 0, 0),
(3225, 'hill-town', 'a', 'Avenue Road 02, 60feet', '59', '14.13', 'south', '', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `wo_booking_helper`
--

CREATE TABLE `wo_booking_helper` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL DEFAULT 0,
  `client_id` varchar(32) NOT NULL DEFAULT '0',
  `file_num` varchar(32) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT '0' COMMENT '0 or 1=avillable, 2=sold, 3=complete, 4=canceled',
  `time` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `nominee_ids` longtext DEFAULT NULL COMMENT 'JSON array of crm_nominees ids, e.g. [23,45]',
  `per_katha` decimal(12,2) DEFAULT NULL,
  `booking_money` decimal(12,2) NOT NULL,
  `booking_due_date` varchar(120) NOT NULL,
  `booking_payment_date` varchar(120) NOT NULL,
  `down_payment` decimal(12,2) NOT NULL,
  `down_due_date` varchar(120) NOT NULL,
  `down_payment_date` varchar(120) NOT NULL,
  `installment` longtext DEFAULT NULL COMMENT 'serialized or JSON installment payload',
  `cancel_date` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `wo_booking_helper`
--

INSERT INTO `wo_booking_helper` (`id`, `booking_id`, `client_id`, `file_num`, `status`, `time`, `updated_at`, `nominee_ids`, `per_katha`, `booking_money`, `booking_due_date`, `booking_payment_date`, `down_payment`, `down_due_date`, `down_payment_date`, `installment`, `cancel_date`) VALUES
(1, 1, '36', '1', '4', 1763363909, 2025, '[18]', 1000.00, 50.00, '', '', 500.00, '', '', NULL, 1763379096),
(2, 1672, '38', '2', '2', 1763358710, 0, '[19]', 2000.00, 75.00, '', '', 425.00, '', '', NULL, 0),
(3, 2, '36', '2', '2', 1763378837, 0, '[17]', 231.00, 123.00, '', '', 123.00, '', '', NULL, 0);

--
-- Indexes for dumped tables
--

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
-- Indexes for table `crm_nominees`
--
ALTER TABLE `crm_nominees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `crm_payment_schedule`
--
ALTER TABLE `crm_payment_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_helper` (`purchase_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_installment_number` (`installment_number`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_combined_tracking` (`purchase_id`,`status`,`created_at`),
  ADD KEY `idx_recalculation` (`recalculated_due_to`,`recalculation_date`);

--
-- Indexes for table `crm_refund_schedule`
--
ALTER TABLE `crm_refund_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_helper` (`purchase_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_installment_number` (`installment_number`);

--
-- Indexes for table `crm_refund_transactions`
--
ALTER TABLE `crm_refund_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_status` (`status`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `crm_customers`
--
ALTER TABLE `crm_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `crm_nominees`
--
ALTER TABLE `crm_nominees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `crm_payment_schedule`
--
ALTER TABLE `crm_payment_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `crm_refund_schedule`
--
ALTER TABLE `crm_refund_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `crm_refund_transactions`
--
ALTER TABLE `crm_refund_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wo_booking`
--
ALTER TABLE `wo_booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3226;

--
-- AUTO_INCREMENT for table `wo_booking_helper`
--
ALTER TABLE `wo_booking_helper`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
