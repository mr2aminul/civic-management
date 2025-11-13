-- Complete payment schedule table with audit fields and no-delete archive support

CREATE TABLE IF NOT EXISTS `crm_payment_schedule` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `booking_helper_id` BIGINT NOT NULL,
  `particular` VARCHAR(255),
  `type` VARCHAR(50),
  `due_date` DATE,
  `payment_date` DATE,
  `payment_method` VARCHAR(100),
  `installment_amount` BIGINT DEFAULT 0,
  `paid_amount` BIGINT DEFAULT 0,
  `installment_number` VARCHAR(50),
  `money_receipt_no` VARCHAR(100),
  `remarks` TEXT,
  `is_adjustment` TINYINT DEFAULT 0,
  `manual_adjustment` TINYINT DEFAULT 0,
  `manual_edit` TINYINT DEFAULT 0,
  `original_amount` BIGINT DEFAULT 0,
  `history_json` LONGTEXT,
  `is_paid` TINYINT DEFAULT 0,
  `is_archived` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT,
  `archived_at` TIMESTAMP NULL,
  `archived_by` BIGINT,
  FOREIGN KEY (`booking_helper_id`) REFERENCES `wo_booking_helper` (`id`) ON DELETE CASCADE,
  INDEX `idx_booking_helper_id` (`booking_helper_id`),
  INDEX `idx_is_archived` (`is_archived`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add audit log table
CREATE TABLE IF NOT EXISTS `crm_audit_log` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `entity_type` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `table_name` VARCHAR(100),
  `record_id` BIGINT,
  `old_values` LONGTEXT,
  `new_values` LONGTEXT,
  `user_id` BIGINT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_entity_type` (`entity_type`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_record_id` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
