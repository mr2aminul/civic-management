-- Add tracking columns to payment schedule if not exist
ALTER TABLE `crm_payment_schedule` 
ADD COLUMN `created_by` int(11) DEFAULT NULL COMMENT 'User who created' AFTER `updated_by`,
ADD COLUMN `previous_amount` decimal(12,2) DEFAULT NULL COMMENT 'Track changes for audit',
ADD COLUMN `change_reason` text DEFAULT NULL COMMENT 'Reason for any adjustments';

-- Create index for tracking
CREATE INDEX `idx_created_by` ON `crm_payment_schedule` (`created_by`);
CREATE INDEX `idx_combined_tracking` ON `crm_payment_schedule` (`purchase_id`, `status`, `created_at`);

-- Archive older payment records instead of deleting
UPDATE `crm_payment_schedule` SET `status` = 99 WHERE `status` = 4;
