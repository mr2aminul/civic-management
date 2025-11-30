-- =====================================================
-- COMPLETE CIVIC GROUP DATABASE SCHEMA - CONSOLIDATED
-- =====================================================
-- This is the definitive schema combining all systems:
-- 1. Core: Customers, Nominees, Bookings
-- 2. Payment: Schedules, Invoices, Money Receipts
-- 3. Refunds: Refund schedules and transactions
-- 4. Transfers: Purchase transfers and history
-- 5. Cancellation: Purchase cancellations and history
-- 6. Email/Audit: Email logs, audit trail
-- 7. Reschedule: Payment rescheduling history
-- 8. Pending: Pending changes and approvals
-- 9. Credits: Overpayment credits and applications
-- =====================================================


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `civicbd_group`
--

CREATE TABLE `crm_approval_notifications` (
  `id` int(11) NOT NULL,
  `pending_change_id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `notification_type` enum('email','in_app','sms') NOT NULL DEFAULT 'in_app',
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','read','dismissed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `crm_audit_trail` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `action_category` varchar(50) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_description` text NOT NULL,
  `before_values` longtext DEFAULT NULL COMMENT 'JSON',
  `after_values` longtext DEFAULT NULL COMMENT 'JSON',
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `performed_by` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `crm_email_logs` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `email_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL COMMENT 'sent, failed, bounced, opened, clicked',
  `delivery_status` varchar(100) DEFAULT NULL,
  `sent_by_user` int(11) DEFAULT NULL,
  `sent_by_system` tinyint(1) DEFAULT 0,
  `attachments` longtext DEFAULT NULL COMMENT 'JSON array',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_email_queue` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `email_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `recipient_type` varchar(20) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_variables` longtext DEFAULT NULL COMMENT 'JSON',
  `queue_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_send_date` datetime DEFAULT NULL,
  `send_date` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `failure_reason` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `reference_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_merge_requests` (
  `id` int(11) NOT NULL,
  `source_purchase_id` int(11) NOT NULL,
  `target_purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `merge_payment_schedule` tinyint(1) NOT NULL DEFAULT 1,
  `merge_paid_amount` tinyint(1) NOT NULL DEFAULT 1,
  `merge_credits` tinyint(1) NOT NULL DEFAULT 1,
  `reschedule_payments` tinyint(1) NOT NULL DEFAULT 1,
  `merge_reason` text NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `requested_by` int(11) NOT NULL,
  `approval_status` varchar(20) NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `review_notes` varchar(120) DEFAULT '',
  `rejection_reason` text DEFAULT NULL,
  `merge_executed_at` datetime DEFAULT NULL,
  `audit_trail_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_money_receipts` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(100) NOT NULL COMMENT 'Format: MR-YYYY-MM-00001',
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `crm_overpayment_credits` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'References crm_customers.id',
  `receipt_id` int(11) NOT NULL COMMENT 'References crm_money_receipts.id - source of credit',
  `credit_amount` decimal(14,2) NOT NULL COMMENT 'Amount of overpayment',
  `remaining_credit` decimal(14,2) NOT NULL COMMENT 'Not yet used',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active, used, expired, refunded',
  `applied_to` longtext DEFAULT NULL COMMENT 'JSON array of {payment_id, amount_applied, applied_date}',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_overpayment_distribution` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL COMMENT 'crm_payment_schedule.id - source of overpayment',
  `applied_to_schedule_id` int(11) NOT NULL COMMENT 'Target schedule where overpayment was applied',
  `amount` decimal(12,2) NOT NULL,
  `distribution_date` date DEFAULT NULL,
  `distribution_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_payment_credits` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `credit_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `applied_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(12,2) GENERATED ALWAYS AS (`credit_amount` - `applied_amount`) STORED,
  `source_invoice_id` int(11) DEFAULT NULL,
  `source_installment_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_payment_credit_applications` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `credit_id` int(11) NOT NULL,
  `installment_id` int(11) NOT NULL,
  `applied_amount` decimal(12,2) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `applied_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_payment_reschedule_history` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `reschedule_date` datetime NOT NULL DEFAULT current_timestamp(),
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_payment_schedule` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL COMMENT 'wo_booking_helper.id',
  `client_id` int(11) NOT NULL COMMENT 'crm_customers.id',
  `installment_number` int(11) NOT NULL,
  `particular` varchar(255) DEFAULT NULL,
  `installment_type` varchar(120) NOT NULL,
  `due_date` date DEFAULT NULL,
  `installment_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `money_receipt_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=overdue, 4=cancelled',
  `is_adjustment` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
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
  `days_overdue` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_pending_changes` (
  `id` int(11) NOT NULL,
  `change_type` enum('reschedule','transfer','cancel','rate_change') NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `request_reason` text DEFAULT NULL,
  `change_data_json` longtext DEFAULT NULL,
  `preview_data_json` longtext DEFAULT NULL,
  `status` enum('pending','approved','denied','expired') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_pending_emails` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `email_type` enum('invoice','money_receipt','payment_schedule','custom') NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `email_subject` varchar(255) NOT NULL,
  `email_body` longtext NOT NULL,
  `email_template_file` varchar(255) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `installment_ids` longtext DEFAULT NULL COMMENT 'JSON array',
  `status` enum('draft','reviewed','sent','cancelled') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Queue for emails pending review before sending';

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

CREATE TABLE `crm_purchase_cancellations` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `cancellation_reason` text NOT NULL,
  `total_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cancellation_fee_mode` enum('none','fixed','percent') DEFAULT 'none',
  `cancellation_fee_value` decimal(12,2) DEFAULT 0.00,
  `cancellation_fee_amount` decimal(12,2) GENERATED ALWAYS AS (case when `cancellation_fee_mode` = 'fixed' then `cancellation_fee_value` when `cancellation_fee_mode` = 'percent' then round(`total_paid_amount` * `cancellation_fee_value` / 100,2) else 0 end) STORED,
  `refundable_amount` decimal(12,2) GENERATED ALWAYS AS (`total_paid_amount` - `cancellation_fee_amount`) STORED,
  `refund_initiated` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancelled_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_purchase_merges` (
  `id` int(11) NOT NULL,
  `primary_purchase_id` int(11) NOT NULL COMMENT 'Purchase to keep (destination)',
  `secondary_purchase_id` int(11) NOT NULL COMMENT 'Purchase to merge into primary',
  `primary_client_id` int(11) NOT NULL,
  `secondary_client_id` int(11) NOT NULL,
  `merge_reason` text NOT NULL,
  `merge_paid_amount` tinyint(1) DEFAULT 1 COMMENT '1=apply secondary paid amount to primary, 0=don''t merge',
  `merge_payment_schedule` tinyint(1) DEFAULT 1 COMMENT '1=consolidate schedules, 0=keep separate',
  `merge_invoices` tinyint(1) DEFAULT 1 COMMENT '1=consolidate invoices, 0=keep separate',
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Approval status',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `requested_by` int(11) NOT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `rejection_by` int(11) DEFAULT NULL,
  `total_paid_primary` decimal(12,2) DEFAULT NULL COMMENT 'Original paid on primary',
  `total_paid_secondary` decimal(12,2) DEFAULT NULL COMMENT 'Original paid on secondary',
  `total_paid_after_merge` decimal(12,2) DEFAULT NULL COMMENT 'Combined paid after merge',
  `credit_generated` decimal(12,2) DEFAULT NULL COMMENT 'Credit if overpaid',
  `merged_at` timestamp NULL DEFAULT NULL,
  `merged_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Track purchase merge requests with approval workflow';

CREATE TABLE `crm_purchase_merge_history` (
  `id` int(11) NOT NULL,
  `merge_request_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `performed_by` int(11) DEFAULT NULL,
  `details` longtext DEFAULT NULL COMMENT 'JSON',
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_purchase_transfer_history` (
  `id` int(11) NOT NULL,
  `original_purchase_id` int(11) NOT NULL COMMENT 'Original wo_booking_helper.id',
  `new_purchase_id` int(11) DEFAULT NULL COMMENT 'New wo_booking_helper.id (for plot transfer)',
  `transfer_type` enum('name_transfer','plot_transfer','cancel_plot') NOT NULL,
  `transfer_date` datetime NOT NULL DEFAULT current_timestamp(),
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_refund_schedule` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_refund_transactions` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `crm_transfer_fee_config` (
  `id` int(11) NOT NULL COMMENT 'Configuration ID',
  `transfer_type` varchar(50) NOT NULL COMMENT 'name_transfer, plot_transfer, cancel_plot, etc',
  `fee_mode` varchar(20) NOT NULL COMMENT 'fixed (amount), percent (%), or free',
  `fee_value` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee amount or percentage',
  `description` varchar(255) DEFAULT NULL COMMENT 'Fee description for display',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Transfer fee configuration';

CREATE TABLE `crm_transfer_history` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL COMMENT 'References wo_booking_helper.id',
  `transfer_type` varchar(50) NOT NULL COMMENT 'name_transfer or plot_transfer',
  `from_client_id` int(11) NOT NULL COMMENT 'Original client',
  `to_client_id` int(11) NOT NULL COMMENT 'New client',
  `transfer_date` date NOT NULL COMMENT 'When transfer occurred',
  `approval_status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=approved, 2=rejected, 3=cancelled',
  `approval_date` date DEFAULT NULL COMMENT 'When approved/rejected',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Approver user id',
  `name_transfer_details` text DEFAULT NULL COMMENT 'JSON details for name transfers',
  `plot_transfer_rate_old` decimal(12,2) DEFAULT NULL COMMENT 'Old rate for plot transfer',
  `plot_transfer_rate_new` decimal(12,2) DEFAULT NULL COMMENT 'New rate for plot transfer',
  `rate_adjustment_reason` varchar(255) DEFAULT NULL COMMENT 'Reason for rate change',
  `rate_adjustment_amount` decimal(12,2) DEFAULT NULL COMMENT 'Difference in rate amount',
  `plot_transfer_details` text DEFAULT NULL COMMENT 'JSON details for plot transfers',
  `transfer_fee` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee charged for transfer',
  `transfer_fee_paid` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee amount paid',
  `transfer_fee_due` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Fee amount outstanding',
  `payment_method` varchar(100) DEFAULT NULL COMMENT 'Cash/Cheque/Bank Transfer/Online',
  `money_receipt_no` varchar(100) DEFAULT NULL COMMENT 'Receipt number',
  `remarks` text DEFAULT NULL COMMENT 'Additional notes or conditions',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason if rejected',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who initiated',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Transfer history with approval workflow and rate adjustments';

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
  `interest_rate` tinyint(4) NOT NULL DEFAULT 0,
  `installment_count` int(11) DEFAULT NULL,
  `default_installments` int(11) DEFAULT 60,
  `adjustment_type` varchar(64) NOT NULL DEFAULT 'year_end',
  `cancel_date` int(11) DEFAULT 0,
  `transfer_history_id` int(11) DEFAULT NULL COMMENT 'Latest transfer record ID',
  `is_transferred` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=original, 1=transferred in/out',
  `transfer_source_type` enum('original','name_transfer','plot_transfer') DEFAULT 'original' COMMENT 'How this purchase was acquired',
  `has_pending_changes` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Flag for quick check for is there are any pending changes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

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

CREATE TABLE `fm_folder_access` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `folder_type` enum('special','common') DEFAULT 'special',
  `user_id` int(11) NOT NULL,
  `permission_level` enum('view','edit','admin') DEFAULT 'view',
  `granted_by` int(11) DEFAULT NULL,
  `granted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `fm_permissions` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL = all users',
  `permission` enum('view','edit','delete','admin') DEFAULT 'view',
  `granted_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE `fm_system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` varchar(50) DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

ALTER TABLE `crm_approval_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pending_change_id` (`pending_change_id`),
  ADD KEY `idx_admin_user_id` (`admin_user_id`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `crm_audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_purchase` (`purchase_id`),
  ADD KEY `idx_category` (`action_category`),
  ADD KEY `idx_performed_at` (`performed_at`);

ALTER TABLE `crm_customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_crm_phone` (`phone`) USING BTREE,
  ADD KEY `idx_crm_nid` (`nid`),
  ADD KEY `idx_crm_passport` (`passport`),
  ADD KEY `name` (`name`);

ALTER TABLE `crm_email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_email_type` (`email_type`),
  ADD KEY `idx_sent_at` (`sent_at`);

ALTER TABLE `crm_email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email_type` (`email_type`),
  ADD KEY `idx_queue_date` (`queue_date`);

ALTER TABLE `crm_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_invoice_type` (`invoice_type`),
  ADD KEY `idx_combined` (`purchase_id`,`status`,`invoice_date`);

ALTER TABLE `crm_merge_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source_purchase` (`source_purchase_id`),
  ADD KEY `idx_target_purchase` (`target_purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_approval_status` (`approval_status`);

ALTER TABLE `crm_money_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD UNIQUE KEY `uq_receipt_number` (`receipt_number`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

ALTER TABLE `crm_nominees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

ALTER TABLE `crm_payment_credits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase` (`purchase_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_remaining` (`remaining_amount`);

ALTER TABLE `crm_payment_credit_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase` (`purchase_id`),
  ADD KEY `idx_credit` (`credit_id`),
  ADD KEY `idx_installment` (`installment_id`);

ALTER TABLE `crm_payment_reschedule_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_reschedule_date` (`reschedule_date`);

ALTER TABLE `crm_payment_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_installment_number` (`installment_number`),
  ADD KEY `idx_invoice_id` (`invoice_id`),
  ADD KEY `idx_reschedule_id` (`reschedule_id`),
  ADD KEY `idx_combined_tracking` (`purchase_id`,`status`,`created_at`),
  ADD KEY `idx_status_date` (`status`,`payment_date`),
  ADD KEY `idx_combined_tracking_complete` (`purchase_id`,`status`,`created_at`,`due_date`);

ALTER TABLE `crm_pending_changes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_change_type` (`change_type`);

ALTER TABLE `crm_pending_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email_type` (`email_type`);

ALTER TABLE `crm_projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `slug` (`slug`),
  ADD KEY `active` (`active`),
  ADD KEY `type` (`type`);

ALTER TABLE `crm_purchase_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_purchase_cancel` (`purchase_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_cancelled_at` (`cancelled_at`);

ALTER TABLE `crm_purchase_merges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_merge_pair` (`primary_purchase_id`,`secondary_purchase_id`),
  ADD KEY `idx_primary_purchase` (`primary_purchase_id`),
  ADD KEY `idx_secondary_purchase` (`secondary_purchase_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_at` (`requested_at`),
  ADD KEY `idx_merged_at` (`merged_at`);

ALTER TABLE `crm_purchase_merge_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_merge_request` (`merge_request_id`);

ALTER TABLE `crm_purchase_transfer_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_original_purchase` (`original_purchase_id`),
  ADD KEY `idx_new_purchase` (`new_purchase_id`),
  ADD KEY `idx_previous_client` (`previous_client_id`),
  ADD KEY `idx_new_client` (`new_client_id`),
  ADD KEY `idx_transfer_type` (`transfer_type`),
  ADD KEY `idx_transfer_date` (`transfer_date`);

ALTER TABLE `crm_refund_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `crm_refund_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_id` (`purchase_id`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `crm_transfer_fee_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_transfer_type` (`transfer_type`),
  ADD KEY `idx_is_active` (`is_active`);

ALTER TABLE `crm_transfer_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pending_transfer` (`purchase_id`,`transfer_type`,`approval_status`),
  ADD KEY `idx_booking_helper` (`purchase_id`),
  ADD KEY `idx_from_client` (`from_client_id`),
  ADD KEY `idx_to_client` (`to_client_id`),
  ADD KEY `idx_transfer_type` (`transfer_type`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_transfer_date` (`transfer_date`);

ALTER TABLE `wo_booking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project` (`project`),
  ADD KEY `block` (`block`),
  ADD KEY `plot` (`plot`),
  ADD KEY `katha` (`katha`),
  ADD KEY `status` (`status`);

ALTER TABLE `wo_booking_helper`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `file_id` (`client_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_transfer_history` (`transfer_history_id`),
  ADD KEY `idx_is_transferred` (`is_transferred`),
  ADD KEY `idx_transfer_source` (`transfer_source_type`),
  ADD KEY `idx_pending_changes` (`has_pending_changes`) USING BTREE;

ALTER TABLE `fm_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_file` (`file_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

ALTER TABLE `fm_common_folders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_key` (`folder_key`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

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

ALTER TABLE `fm_file_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_share_token` (`share_token`),
  ADD KEY `idx_file_id` (`file_id`),
  ADD KEY `idx_shared_by` (`shared_by`),
  ADD KEY `idx_shared_with` (`shared_with`),
  ADD KEY `idx_share_token` (`share_token`),
  ADD KEY `idx_active` (`is_active`);

ALTER TABLE `fm_file_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_id` (`file_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_version` (`file_id`,`version_number`);

ALTER TABLE `fm_folder_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_user` (`folder_id`,`folder_type`,`user_id`),
  ADD KEY `idx_folder` (`folder_id`,`folder_type`),
  ADD KEY `idx_user` (`user_id`);

ALTER TABLE `fm_folder_structure`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_path` (`folder_path`(512)),
  ADD KEY `idx_user_type` (`user_id`,`folder_type`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_type` (`folder_type`,`is_active`);

ALTER TABLE `fm_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_user` (`file_id`,`user_id`);

ALTER TABLE `fm_recycle_bin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file` (`file_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_auto_delete` (`auto_delete_at`),
  ADD KEY `idx_fm_recycle_auto_delete` (`auto_delete_at`),
  ADD KEY `idx_fm_recycle_user` (`user_id`,`can_restore`);

ALTER TABLE `fm_special_folders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_folder_key` (`folder_key`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

ALTER TABLE `fm_system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`);

ALTER TABLE `fm_thumbnails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_file_size` (`file_id`,`thumbnail_size`),
  ADD KEY `idx_file_id` (`file_id`);

ALTER TABLE `fm_upload_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_upload` (`local_path`(255),`remote_key`(255)),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `fm_user_quotas`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_usage` (`used_bytes`),
  ADD KEY `idx_updated` (`updated_at`);

ALTER TABLE `fm_user_storage_tracking`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_used` (`used_bytes`),
  ADD KEY `idx_updated` (`updated_at`);

ALTER TABLE `crm_approval_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `crm_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

ALTER TABLE `crm_email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

ALTER TABLE `crm_merge_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `crm_money_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_nominees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

ALTER TABLE `crm_payment_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_payment_credit_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_payment_reschedule_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_payment_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=569;

ALTER TABLE `crm_pending_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `crm_pending_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `crm_purchase_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_purchase_merges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_purchase_merge_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_purchase_transfer_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_refund_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `crm_refund_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `crm_transfer_fee_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Configuration ID', AUTO_INCREMENT=5;

ALTER TABLE `crm_transfer_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `wo_booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3226;

ALTER TABLE `wo_booking_helper`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

ALTER TABLE `fm_activity_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `fm_common_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

ALTER TABLE `fm_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

ALTER TABLE `fm_file_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `fm_file_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `fm_folder_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `fm_folder_structure`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `fm_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `fm_recycle_bin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `fm_special_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `fm_system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

ALTER TABLE `fm_thumbnails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

ALTER TABLE `fm_upload_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;
COMMIT;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
