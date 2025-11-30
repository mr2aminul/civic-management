-- Migration: Add metadata and reference columns to fm_files
-- Date: 2025-11-29
-- Purpose: Support CRM document storage with client/purchase tracking

ALTER TABLE `fm_files` 
ADD COLUMN `metadata` LONGTEXT NULL COMMENT 'JSON metadata for file' AFTER `relative_path`,
ADD COLUMN `client_id` INT(11) NULL COMMENT 'Reference to crm_customers.id' AFTER `metadata`,
ADD COLUMN `purchase_id` INT(11) NULL COMMENT 'Reference to wo_booking_helper.id' AFTER `client_id`,
ADD INDEX `idx_fm_files_client` (`client_id`),
ADD INDEX `idx_fm_files_purchase` (`purchase_id`);

-- Add foreign key constraints (optional, uncomment if needed)
-- ALTER TABLE `fm_files` 
-- ADD CONSTRAINT `fk_fm_files_client` FOREIGN KEY (`client_id`) REFERENCES `crm_customers` (`id`) ON DELETE SET NULL,
-- ADD CONSTRAINT `fk_fm_files_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `wo_booking_helper` (`id`) ON DELETE SET NULL;
