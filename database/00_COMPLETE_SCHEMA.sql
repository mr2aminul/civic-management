-- =====================================================
-- COMPLETE CIVIC GROUP DATABASE SCHEMA - CONSOLIDATED
-- =====================================================
-- This is the definitive schema combining all systems:
-- 1. Core: Customers, Nominees, Bookings
-- 2. Payment: Schedules, Invoices, Money Receipts
-- 3. Refunds: Refund schedules and transactions
-- 4. Transfers: Purchase transfers and history
-- 5. Email/Audit: Email logs, audit trail
-- 6. Reschedule: Payment rescheduling history
-- 7. Pending: Pending changes and approvals
-- 8. Credits: Overpayment credits and applications
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- 1. CORE TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(300) NOT NULL,
  `phone` varchar(65) NOT NULL UNIQUE,
  `address` text NOT NULL,
  `permanent_addr` text DEFAULT NULL,
  `profession` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `religion` varchar(100) DEFAULT NULL,
  `nid` varchar(300) DEFAULT NULL,
  `passport` varchar(300) DEFAULT NULL,
  `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `additional` longtext NOT NULL,
  `additional_json` longtext DEFAULT NULL,
  `spouse_name` varchar(255) DEFAULT NULL,
  `fathers_name` varchar(255) DEFAULT NULL,
  `mothers_name` varchar(255) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_phone` (`phone`),
  KEY `idx_nid` (`nid`),
  KEY `idx_passport` (`passport`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `crm_nominees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL COMMENT 'crm_customers.id',
  `name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `birthday` int(11) DEFAULT NULL,
  `share_parcent` varchar(32) DEFAULT NULL,
  `relation` varchar(100) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wo_booking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project` varchar(300) NOT NULL,
  `block` varchar(11) NOT NULL,
  `road` text NOT NULL,
  `plot` varchar(120) DEFAULT '0',
  `katha` varchar(120) NOT NULL DEFAULT '0',
  `facing` text NOT NULL,
  `file_num` text NOT NULL,
  `object_id` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0=available, 1=available, 2=sold, 3=complete, 4=cancelled',
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project`),
  KEY `idx_block` (`block`),
  KEY `idx_plot` (`plot`),
  KEY `idx_katha` (`katha`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  `monthly_amount` decimal(12,2) DEFAULT NULL,
  `yearly_adjustment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_date` varchar(120) DEFAULT NULL,
  `installment_start_option` varchar(32) DEFAULT NULL,
  `mode_of_payment` varchar(32) NOT NULL,
  `booking_money` decimal(12,2) NOT NULL,
  `booking_due_date` varchar(120) NOT NULL,
  `booking_payment_date` varchar(120) NOT NULL,
  `down_payment` decimal(12,2) NOT NULL,
  `down_due_date` varchar(120) NOT NULL,
  `down_payment_date` varchar(120) NOT NULL,
  `installment_count` int(11) DEFAULT NULL,
  `default_installments` int(11) DEFAULT 60,
  `adjustment_type` varchar(64) NOT NULL DEFAULT 'year_end',
  `cancel_date` int(11) DEFAULT 0,
  `transfer_history_id` int(11) DEFAULT NULL COMMENT 'Latest transfer record ID',
  `is_transferred` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=original, 1=transferred in/out',
  `transfer_source_type` enum('original','name_transfer','plot_transfer') DEFAULT 'original' COMMENT 'How this purchase was acquired',
  `has_pending_changes` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Flag for quick check for is there are any pending changes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

ALTER TABLE `wo_booking_helper`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `file_id` (`client_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_transfer_history` (`transfer_history_id`),
  ADD KEY `idx_is_transferred` (`is_transferred`),
  ADD KEY `idx_transfer_source` (`transfer_source_type`),
  ADD KEY `idx_pending_changes` (`has_pending_changes`) USING BTREE;

ALTER TABLE `wo_booking_helper`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

-- ========================================
-- 2. PAYMENT SCHEDULE TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_payment_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL COMMENT 'wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'crm_customers.id',
  `installment_number` int(11) NOT NULL,
  `particular` varchar(255) DEFAULT NULL,
  `type` varchar(120) NOT NULL DEFAULT '',
  `due_date` date DEFAULT NULL,
  `installment_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `money_receipt_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=overdue, 4=cancelled',
  `is_adjustment` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `transfer_affected_by` int(11) DEFAULT NULL,
  `recalculated_due_to` varchar(50) DEFAULT NULL,
  `recalculation_date` datetime DEFAULT NULL,
  `previous_amount` decimal(12,2) DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `manual_adjustment` tinyint(1) DEFAULT 0,
  `manual_edit` tinyint(1) DEFAULT 0,
  `history_json` longtext DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `reschedule_id` int(11) DEFAULT NULL,
  `is_rescheduled` tinyint(1) NOT NULL DEFAULT 0,
  `reschedule_sequence` int(11) NOT NULL DEFAULT 0,
  `overpayment_amount` decimal(12,2) DEFAULT 0.00,
  `overpayment_distributed` decimal(12,2) DEFAULT 0.00,
  `late_fee_amount` decimal(12,2) DEFAULT 0.00,
  `late_fee_waived` tinyint(1) DEFAULT 0,
  `overdue_fee_amount` decimal(12,2) DEFAULT 0.00,
  `overdue_fee_waived` tinyint(1) DEFAULT 0,
  `days_overdue` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`),
  KEY `idx_installment_number` (`installment_number`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_reschedule_id` (`reschedule_id`),
  KEY `idx_combined_tracking` (`purchase_id`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 3. INVOICE TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(100) NOT NULL UNIQUE,
  `purchase_id` int(11) NOT NULL COMMENT 'wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'crm_customers.id',
  `invoice_type` varchar(32) NOT NULL COMMENT 'booking_money, down_payment, installment',
  `payment_schedule_id` int(11) DEFAULT NULL COMMENT 'crm_payment_schedule.id',
  `description` text DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, issued, partial, paid, overdue, cancelled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_invoice_type` (`invoice_type`),
  KEY `idx_combined` (`purchase_id`, `status`, `invoice_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 4. MONEY RECEIPTS TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_money_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(100) NOT NULL UNIQUE COMMENT 'Format: MR-YYYY-MM-00001',
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `receipt_date` date NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` decimal(14,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `invoices_paid` longtext NOT NULL COMMENT 'JSON array: [{invoice_id, amount_applied}]',
  `notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'issued',
  `sent_to_email` varchar(255) DEFAULT NULL,
  `sent_to_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_number` (`receipt_number`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 5. REFUND TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_refund_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `refund_initiation_date` date NOT NULL,
  `total_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refundable_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `installment_number` int(11) NOT NULL DEFAULT 1,
  `installment_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `money_receipt_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=cancelled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `crm_refund_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `refund_request_date` date NOT NULL,
  `total_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deduction_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refundable_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transaction_date` date NOT NULL,
  `transaction_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(100) DEFAULT NULL,
  `money_receipt_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=completed, 3=cancelled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `crm_purchase_cancellations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `cancellation_reason` text NOT NULL,
  `total_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cancellation_fee_mode` enum('none','fixed','percent') DEFAULT 'none',
  `cancellation_fee_value` decimal(12,2) DEFAULT 0.00,
  `cancellation_fee_amount` decimal(12,2) GENERATED ALWAYS AS (
    CASE 
      WHEN cancellation_fee_mode = 'fixed' THEN cancellation_fee_value
      WHEN cancellation_fee_mode = 'percent' THEN ROUND(total_paid_amount * cancellation_fee_value / 100, 2)
      ELSE 0
    END
  ) STORED,
  `refundable_amount` decimal(12,2) GENERATED ALWAYS AS (total_paid_amount - cancellation_fee_amount) STORED,
  `refund_initiated` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cancelled_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_purchase_cancel` (`purchase_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_cancelled_at` (`cancelled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 6. TRANSFER & HISTORY TABLES
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_purchase_transfer_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_purchase_id` int(11) NOT NULL COMMENT 'Original wo_booking_helper.id',
  `new_purchase_id` int(11) DEFAULT NULL COMMENT 'New wo_booking_helper.id (for plot transfer)',
  `transfer_type` enum('name_transfer','plot_transfer','cancel_plot') NOT NULL,
  `transfer_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `previous_client_id` int(11) NOT NULL,
  `previous_client_name` varchar(300) DEFAULT NULL,
  `new_client_id` int(11) DEFAULT NULL,
  `new_client_name` varchar(300) DEFAULT NULL,
  `previous_plot_id` int(11) DEFAULT NULL,
  `previous_project` varchar(300) DEFAULT NULL,
  `previous_block` varchar(11) DEFAULT NULL,
  `previous_plot` varchar(120) DEFAULT NULL,
  `previous_road` text DEFAULT NULL,
  `previous_katha` varchar(120) DEFAULT NULL,
  `previous_per_katha` decimal(12,2) DEFAULT NULL,
  `previous_total_amount` decimal(12,2) DEFAULT NULL,
  `new_plot_id` int(11) DEFAULT NULL,
  `new_project` varchar(300) DEFAULT NULL,
  `new_block` varchar(11) DEFAULT NULL,
  `new_plot` varchar(120) DEFAULT NULL,
  `new_road` text DEFAULT NULL,
  `new_katha` varchar(120) DEFAULT NULL,
  `new_per_katha` decimal(12,2) DEFAULT NULL,
  `new_total_amount` decimal(12,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `transfer_fee_mode` varchar(50) DEFAULT NULL,
  `transfer_fee_value` decimal(12,2) DEFAULT NULL,
  `transfer_fee_amount` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_original_purchase` (`original_purchase_id`),
  KEY `idx_new_purchase` (`new_purchase_id`),
  KEY `idx_previous_client` (`previous_client_id`),
  KEY `idx_new_client` (`new_client_id`),
  KEY `idx_transfer_type` (`transfer_type`),
  KEY `idx_transfer_date` (`transfer_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 7. RESCHEDULE HISTORY TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_payment_reschedule_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `reschedule_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `monthly_installment_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `adjustment_mode` enum('yearly_adjustment','last_installment') NOT NULL DEFAULT 'last_installment',
  `reason` text DEFAULT NULL,
  `last_paid_schedule_id` int(11) DEFAULT NULL,
  `last_paid_installment_number` int(11) DEFAULT NULL,
  `affected_rows_count` int(11) NOT NULL DEFAULT 0,
  `total_amount_before` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_paid_before` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_balance_before` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_balance_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `previous_schedule_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_reschedule_date` (`reschedule_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 8. PAYMENT CREDITS TABLE
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_payment_credits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `credit_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `applied_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(12,2) GENERATED ALWAYS AS (credit_amount - applied_amount) STORED,
  `source_invoice_id` int(11) DEFAULT NULL,
  `source_installment_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchase` (`purchase_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_remaining` (`remaining_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `crm_payment_credit_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `credit_id` int(11) NOT NULL,
  `installment_id` int(11) NOT NULL,
  `applied_amount` decimal(12,2) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `applied_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_purchase` (`purchase_id`),
  KEY `idx_credit` (`credit_id`),
  KEY `idx_installment` (`installment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 9. EMAIL QUEUE & LOGS
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `email_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `recipient_type` varchar(20) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_variables` longtext DEFAULT NULL COMMENT 'JSON',
  `queue_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scheduled_send_date` datetime DEFAULT NULL,
  `send_date` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `failure_reason` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `reference_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_queue_date` (`queue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `crm_email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `queue_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `email_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) NOT NULL COMMENT 'sent, failed, bounced, opened, clicked',
  `delivery_status` varchar(100) DEFAULT NULL,
  `sent_by_user` int(11) DEFAULT NULL,
  `sent_by_system` tinyint(1) DEFAULT 0,
  `attachments` longtext DEFAULT NULL COMMENT 'JSON array',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 10. PENDING CHANGES & APPROVALS
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_pending_changes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `change_type` enum('reschedule','transfer','cancel','rate_change') NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `request_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `request_reason` text DEFAULT NULL,
  `change_data_json` longtext DEFAULT NULL,
  `preview_data_json` longtext DEFAULT NULL,
  `status` enum('pending','approved','denied','expired') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_id` (`purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_change_type` (`change_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `crm_approval_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pending_change_id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `notification_type` enum('email','in_app','sms') NOT NULL DEFAULT 'in_app',
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','read','dismissed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pending_change_id` (`pending_change_id`),
  KEY `idx_admin_user_id` (`admin_user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 11. AUDIT TRAIL
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `action_category` varchar(50) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_description` text NOT NULL,
  `before_values` longtext DEFAULT NULL COMMENT 'JSON',
  `after_values` longtext DEFAULT NULL COMMENT 'JSON',
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `performed_by` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_purchase` (`purchase_id`),
  KEY `idx_category` (`action_category`),
  KEY `idx_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- 12. MERGE REQUESTS
-- ========================================

CREATE TABLE IF NOT EXISTS `crm_merge_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_purchase_id` int(11) NOT NULL,
  `target_purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `consolidate_schedule` tinyint(1) NOT NULL DEFAULT 1,
  `transfer_paid_amount` tinyint(1) NOT NULL DEFAULT 1,
  `transfer_credits` tinyint(1) NOT NULL DEFAULT 1,
  `reschedule_payments` tinyint(1) NOT NULL DEFAULT 1,
  `merge_reason` text NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requested_by` int(11) NOT NULL,
  `approval_status` varchar(20) NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `merge_executed_at` datetime DEFAULT NULL,
  `audit_trail_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_purchase` (`source_purchase_id`),
  KEY `idx_target_purchase` (`target_purchase_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_approval_status` (`approval_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `crm_purchase_merge_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merge_request_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `performed_by` int(11) DEFAULT NULL,
  `details` longtext DEFAULT NULL COMMENT 'JSON',
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_merge_request` (`merge_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `wo_payment_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `kind` varchar(100) NOT NULL,
  `amount` decimal(11,0) UNSIGNED NOT NULL,
  `transaction_dt` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ========================================
-- INDEXES FOR COMMON QUERIES
-- ========================================

ALTER TABLE `crm_payment_schedule` ADD KEY `idx_status_date` (`status`,`payment_date`);
ALTER TABLE `crm_payment_schedule` ADD KEY `idx_combined_tracking_complete` (`purchase_id`, `status`, `created_at`, `due_date`);

-- ========================================
-- AUTO_INCREMENT INITIALIZATION
-- ========================================

ALTER TABLE `crm_payment_schedule` AUTO_INCREMENT = 125;
ALTER TABLE `crm_invoices` AUTO_INCREMENT = 1;
ALTER TABLE `crm_money_receipts` AUTO_INCREMENT = 1;
ALTER TABLE `crm_refund_schedule` AUTO_INCREMENT = 5;
ALTER TABLE `crm_refund_transactions` AUTO_INCREMENT = 4;
ALTER TABLE `wo_booking` AUTO_INCREMENT = 3226;
ALTER TABLE `wo_booking_helper` AUTO_INCREMENT = 4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
