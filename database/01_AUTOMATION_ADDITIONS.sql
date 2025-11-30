-- =====================================================
-- SCHEMA ADDITIONS FOR COMPLETE AUTOMATION
-- Run this after 00_COMPLETE_SCHEMA.sql
-- =====================================================

-- 1. Add soft delete support to crm_customers
ALTER TABLE `crm_customers`
ADD COLUMN `is_deleted` TINYINT(1) DEFAULT 0 COMMENT '0=active, 1=deleted (in recycle bin)',
ADD COLUMN `deleted_at` DATETIME NULL COMMENT 'When moved to recycle bin',
ADD COLUMN `deleted_by` INT(11) NULL COMMENT 'User who deleted',
ADD COLUMN `restored_at` DATETIME NULL COMMENT 'When restored from recycle bin',
ADD COLUMN `restored_by` INT(11) NULL COMMENT 'User who restored',
ADD INDEX `idx_deleted` (`is_deleted`, `deleted_at`);

-- 2. Add document folder tracking to purchases  
ALTER TABLE `wo_booking_helper`
ADD COLUMN `document_folder_path` VARCHAR(512) NULL COMMENT 'Path to purchase documents folder, e.g. storage/client_docs/{client_id}/Purchases/{file_num}/';

-- 3. Add booking_date for purchase date tracking
ALTER TABLE `wo_booking_helper`
ADD COLUMN `booking_date` DATE NULL COMMENT 'Date when purchase/booking was made',
ADD INDEX `idx_booking_date` (`booking_date`);

-- 4. Add plot hold feature to wo_booking
ALTER TABLE `wo_booking`
ADD COLUMN `hold_status` TINYINT(1) DEFAULT 0 COMMENT '0=not held, 1=held',
ADD COLUMN `held_by` INT(11) NULL COMMENT 'Employee user_id who placed hold',
ADD COLUMN `hold_start_date` DATETIME NULL COMMENT 'When hold started',
ADD COLUMN `hold_end_date` DATETIME NULL COMMENT 'When hold expires',
ADD COLUMN `hold_reason` TEXT NULL COMMENT 'Reason for holding plot',
ADD COLUMN `hold_auto_release` TINYINT(1) DEFAULT 1 COMMENT '1=auto-release on expiry, 0=manual only',
ADD COLUMN `hold_released_at` DATETIME NULL COMMENT 'When hold was released',
ADD COLUMN `hold_released_by` INT(11) NULL COMMENT 'User who released hold',
ADD COLUMN `hold_override_by_admin` TINYINT(1) DEFAULT 0 COMMENT '1=admin overrode hold for purchase',
ADD INDEX `idx_hold_status` (`hold_status`, `hold_end_date`),
ADD INDEX `idx_held_by` (`held_by`);

-- 5. Add missing columns to crm_invoices for production readiness
ALTER TABLE `crm_invoices`
ADD COLUMN `client_id` INT(11) NOT NULL COMMENT 'crm_customers.id' AFTER `purchase_id`,
ADD COLUMN `payment_schedule_id` INT(11) NULL COMMENT 'crm_payment_schedule.id - linked schedule item' AFTER `invoice_type`,
ADD COLUMN `description` TEXT NULL COMMENT 'Invoice description/particulars' AFTER `payment_schedule_id`,
ADD COLUMN `invoice_date` DATE NULL COMMENT 'Date invoice was issued' AFTER `description`,
ADD COLUMN `remaining_amount` DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Calculated: amount - paid_amount' AFTER `paid_amount`,
ADD COLUMN `created_by` INT(11) NULL COMMENT 'User who created invoice' AFTER `created_at`,
ADD COLUMN `updated_by` INT(11) NULL COMMENT 'User who last updated' AFTER `updated_at`,
ADD INDEX `idx_client` (`client_id`),
ADD INDEX `idx_schedule` (`payment_schedule_id`),
ADD INDEX `idx_status_due` (`status`, `due_date`),
ADD INDEX `idx_invoice_date` (`invoice_date`);

-- 6. Add missing columns to crm_money_receipts
ALTER TABLE `crm_money_receipts`
ADD COLUMN `client_id` INT(11) NOT NULL COMMENT 'crm_customers.id' AFTER `purchase_id`,
ADD COLUMN `transaction_reference` VARCHAR(255) NULL COMMENT 'Bank/cheque reference' AFTER `payment_method`,
ADD COLUMN `invoices_paid` JSON NULL COMMENT 'Array of {invoice_id, invoice_number, amount_applied}' AFTER `notes`,
ADD COLUMN `status` ENUM('draft','issued','cancelled') DEFAULT 'issued' COMMENT 'Receipt status' AFTER `invoices_paid`,
ADD COLUMN `created_by` INT(11) NULL COMMENT 'User who created receipt' AFTER `created_at`,
ADD INDEX `idx_client` (`client_id`),
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_payment_date` (`payment_date`);

-- 7. SMS Queue table
CREATE TABLE IF NOT EXISTS `crm_sms_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `phone_number` VARCHAR(20) NOT NULL,
  `message` TEXT NOT NULL,
  `sms_type` VARCHAR(50) NOT NULL COMMENT 'payment_received, reminder_7_days, etc.',
  `metadata` JSON NULL COMMENT 'Additional data',
  `status` ENUM('queued','sent','failed') DEFAULT 'queued',
  `sent_at` DATETIME NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_client` (`client_id`),
  INDEX `idx_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Reminders tracking table
CREATE TABLE IF NOT EXISTS `crm_reminders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` INT(11) NOT NULL,
  `schedule_id` INT(11) NULL COMMENT 'crm_payment_schedule.id',
  `client_id` INT(11) NOT NULL,
  `reminder_type` VARCHAR(50) NOT NULL COMMENT 'due_in_7_days, due_in_3_days, overdue, etc.',
  `sent_via` ENUM('email','sms','both') DEFAULT 'both',
  `sent_at` DATETIME NOT NULL,
  `status` ENUM('sent','failed','bounced') DEFAULT 'sent',
  `metadata` JSON NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_purchase` (`purchase_id`),
  INDEX `idx_schedule` (`schedule_id`),
  INDEX `idx_client` (`client_id`),
  INDEX `idx_type` (`reminder_type`),
  INDEX `idx_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Documents table (for tracking generated PDFs)
CREATE TABLE IF NOT EXISTS `crm_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` INT(11) NULL,
  `client_id` INT(11) NULL,
  `invoice_id` INT(11) NULL,
  `receipt_id` INT(11) NULL,
  `document_type` VARCHAR(50) NOT NULL COMMENT 'invoice, receipt, schedule, agreement, certificate, etc.',
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(512) NOT NULL,
  `file_size` BIGINT(20) DEFAULT 0,
  `mime_type` VARCHAR(100) DEFAULT 'application/pdf',
  `generated_at` DATETIME NOT NULL,
  `generated_by` INT(11) NULL COMMENT 'User who generated, NULL=system',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fm_file_id` INT(11) NULL COMMENT 'Reference to fm_files.id if stored in file manager',
  PRIMARY KEY (`id`),
  INDEX `idx_purchase` (`purchase_id`),
  INDEX `idx_client` (`client_id`),
  INDEX `idx_type` (`document_type`),
  INDEX `idx_invoice` (`invoice_id`),
  INDEX `idx_receipt` (`receipt_id`),
  INDEX `idx_fm_file` (`fm_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. Refund installments table (for installment-based refunds)
CREATE TABLE IF NOT EXISTS `crm_refund_installments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `refund_schedule_id` INT(11) NOT NULL COMMENT 'crm_refund_schedule.id',
  `installment_number` INT(11) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `due_date` DATE NOT NULL,
  `paid_date` DATE NULL,
  `payment_method` VARCHAR(100) NULL,
  `transaction_reference` VARCHAR(255) NULL,
  `status` ENUM('pending','paid','cancelled') DEFAULT 'pending',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_refund_schedule` (`refund_schedule_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- COMMIT CHANGES
-- =====================================================
COMMIT;
