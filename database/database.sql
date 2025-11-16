-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 16, 2025 at 09:43 AM
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
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=overdue',
  `is_adjustment` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=regular, 1=adjustment entry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated',
  `previous_amount` decimal(12,2) DEFAULT NULL COMMENT 'Track changes for audit',
  `change_reason` text DEFAULT NULL COMMENT 'Reason for any adjustments',
  `manual_adjustment` tinyint(1) DEFAULT 0,
  `manual_edit` tinyint(1) DEFAULT 0,
  `history_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crm_payment_schedule`
--

INSERT INTO `crm_payment_schedule` (`id`, `purchase_id`, `client_id`, `installment_number`, `particular`, `type`, `due_date`, `installment_amount`, `paid_amount`, `payment_date`, `payment_method`, `money_receipt_no`, `remarks`, `status`, `is_adjustment`, `created_at`, `updated_at`, `created_by`, `updated_by`, `previous_amount`, `change_reason`, `manual_adjustment`, `manual_edit`, `history_json`) VALUES
(1, 11, 32, 0, 'Booking Money', 'booking', '2023-11-08', 50000.00, 0.00, '2023-11-08', '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 50000.00, NULL, 0, 0, NULL),
(2, 11, 32, 0, 'Down Payment', 'down', '2023-11-08', 700000.00, 700000.00, '2023-11-08', '', '', '', 1, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 700000.00, NULL, 0, 0, NULL),
(3, 11, 32, 1, '1st Installment', 'installment', '2025-11-16', 204546.00, 204546.00, '2025-11-16', '', '', 'Interest 3% on due ৳2,04,546 (after grace: 0d)', 1, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(4, 11, 32, 2, '2th Installment', 'installment', '2025-12-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(5, 11, 32, 3, '3th Installment', 'installment', '2026-01-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(6, 11, 32, 4, '4th Installment', 'installment', '2026-02-16', 204546.00, 204546.00, '2026-02-16', '', '', '', 1, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(7, 11, 32, 5, '5th Installment', 'installment', '2026-03-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(8, 11, 32, 6, '6th Installment', 'installment', '2026-04-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(9, 11, 32, 7, '7th Installment', 'installment', '2026-05-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(10, 11, 32, 8, '8th Installment', 'installment', '2026-06-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(11, 11, 32, 9, '9th Installment', 'installment', '2026-07-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(12, 11, 32, 10, '10th Installment', 'installment', '2026-08-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(13, 11, 32, 11, '11th Installment', 'installment', '2026-09-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(14, 11, 32, 12, '12th Installment', 'installment', '2026-10-16', 100000.00, 0.00, NULL, '', '', 'Yearly Adjustment', 0, 1, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 100000.00, NULL, 0, 0, NULL),
(15, 11, 32, 13, '13th Installment', 'installment', '2026-11-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(16, 11, 32, 14, '14th Installment', 'installment', '2026-12-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(17, 11, 32, 15, '15th Installment', 'installment', '2027-01-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(18, 11, 32, 16, '16th Installment', 'installment', '2027-02-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(19, 11, 32, 17, '17th Installment', 'installment', '2027-03-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(20, 11, 32, 18, '18th Installment', 'installment', '2027-04-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(21, 11, 32, 19, '19th Installment', 'installment', '2027-05-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(22, 11, 32, 20, '20th Installment', 'installment', '2027-06-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(23, 11, 32, 21, '21th Installment', 'installment', '2027-07-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(24, 11, 32, 22, '22th Installment', 'installment', '2027-08-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(25, 11, 32, 23, '23th Installment', 'installment', '2027-09-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(26, 11, 32, 24, '24th Installment', 'installment', '2027-10-16', 100000.00, 0.00, NULL, '', '', 'Yearly Adjustment', 0, 1, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 100000.00, NULL, 0, 0, NULL),
(27, 11, 32, 25, '25th Installment', 'installment', '2027-11-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(28, 11, 32, 26, '26th Installment', 'installment', '2027-12-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(29, 11, 32, 27, '27th Installment', 'installment', '2028-01-16', 204546.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204546.00, NULL, 0, 0, NULL),
(30, 11, 32, 28, '28th Installment', 'installment', '2028-02-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(31, 11, 32, 29, '29th Installment', 'installment', '2028-03-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(32, 11, 32, 30, '30th Installment', 'installment', '2028-04-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(33, 11, 32, 31, '31th Installment', 'installment', '2028-05-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(34, 11, 32, 32, '32th Installment', 'installment', '2028-06-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(35, 11, 32, 33, '33th Installment', 'installment', '2028-07-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(36, 11, 32, 34, '34th Installment', 'installment', '2028-08-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(37, 11, 32, 35, '35th Installment', 'installment', '2028-09-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(38, 11, 32, 36, '36th Installment', 'installment', '2028-10-16', 100000.00, 0.00, NULL, '', '', 'Yearly Adjustment', 0, 1, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 100000.00, NULL, 0, 0, NULL),
(39, 11, 32, 37, '37th Installment', 'installment', '2028-11-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(40, 11, 32, 38, '38th Installment', 'installment', '2028-12-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(41, 11, 32, 39, '39th Installment', 'installment', '2029-01-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(42, 11, 32, 40, '40th Installment', 'installment', '2029-02-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(43, 11, 32, 41, '41th Installment', 'installment', '2029-03-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(44, 11, 32, 42, '42th Installment', 'installment', '2029-04-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(45, 11, 32, 43, '43th Installment', 'installment', '2029-05-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(46, 11, 32, 44, '44th Installment', 'installment', '2029-06-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(47, 11, 32, 45, '45th Installment', 'installment', '2029-07-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(48, 11, 32, 46, '46th Installment', 'installment', '2029-08-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(49, 11, 32, 47, '47th Installment', 'installment', '2029-09-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(50, 11, 32, 48, '48th Installment', 'installment', '2029-10-16', 100000.00, 0.00, NULL, '', '', 'Yearly Adjustment', 0, 1, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 100000.00, NULL, 0, 0, NULL),
(51, 11, 32, 49, '49th Installment', 'installment', '2029-11-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(52, 11, 32, 50, '50th Installment', 'installment', '2029-12-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(53, 11, 32, 51, '51th Installment', 'installment', '2030-01-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(54, 11, 32, 52, '52th Installment', 'installment', '2030-02-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(55, 11, 32, 53, '53th Installment', 'installment', '2030-03-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(56, 11, 32, 54, '54th Installment', 'installment', '2030-04-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(57, 11, 32, 55, '55th Installment', 'installment', '2030-05-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(58, 11, 32, 56, '56th Installment', 'installment', '2030-06-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(59, 11, 32, 57, '57th Installment', 'installment', '2030-07-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(60, 11, 32, 58, '58th Installment', 'installment', '2030-08-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(61, 11, 32, 59, '59th Installment', 'installment', '2030-09-16', 204545.00, 0.00, NULL, '', '', '', 0, 0, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 204545.00, NULL, 0, 0, NULL),
(62, 11, 32, 60, '60th Installment', 'installment', '2030-10-16', 100000.00, 0.00, NULL, '', '', 'Yearly Adjustment', 0, 1, '2025-11-16 15:24:45', '2025-11-16 15:24:45', 1, 1, 100000.00, NULL, 0, 0, NULL);

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
(1, 11, 32, '2025-11-13', 750000.00, 10.00, 75000.00, 675000.00, 1, 675000.00, '2026-01-09', 0.00, NULL, NULL, NULL, NULL, 0, '2025-11-13 12:34:07', '2025-11-13 12:34:07', 1, NULL);

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
(11, 1672, '32', '633', '4', 1762945301, 1763285085, '[13]', 1250000.00, 50000.00, '2023-11-08', '2023-11-08', 700000.00, '2023-11-08', '2023-11-08', '[]', 1762884000),
(12, 3, '34', 'wer', '4', 1762947544, 1762947687, '[15]', 456456.00, 456.00, '2025-11-12', '2025-11-12', 45.00, '2025-11-12', '2025-11-12', '[]', 1762884000),
(13, 2, '35', '634', '4', 1763032632, 1763032165, '[16]', 234234423.00, 2343.00, '2025-11-13', '2025-11-13', 234.00, '2025-11-13', '2025-11-13', '[]', 1762970400),
(14, 1673, '32', '1242', '2', 1763285543, 0, '[13]', 300000.00, 50000.00, '', '', 150000.00, '', '', NULL, 0);

--
-- Indexes for dumped tables
--

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
  ADD KEY `idx_combined_tracking` (`purchase_id`,`status`,`created_at`);

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
-- AUTO_INCREMENT for table `crm_payment_schedule`
--
ALTER TABLE `crm_payment_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `crm_refund_schedule`
--
ALTER TABLE `crm_refund_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wo_booking_helper`
--
ALTER TABLE `wo_booking_helper`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
