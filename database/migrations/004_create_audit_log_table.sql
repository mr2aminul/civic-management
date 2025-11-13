-- Create CRM Audit Log Table
CREATE TABLE IF NOT EXISTS `crm_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL COMMENT 'payment_schedule, transfer, refund, client, etc',
  `action` varchar(20) NOT NULL COMMENT 'create, update, view, archive, etc',
  `record_id` int(11) NOT NULL COMMENT 'ID of the modified record',
  `changes` json DEFAULT NULL COMMENT 'JSON of field changes',
  `user_id` int(11) DEFAULT NULL COMMENT 'User who made the change',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of request',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser user agent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_module_record` (`module`, `record_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add status column to payment schedule for archiving (if not exists)
ALTER TABLE `crm_payment_schedule` ADD COLUMN `status` tinyint(4) DEFAULT 0 COMMENT '0=pending, 1=paid, 2=partial, 3=overdue, 99=archived' AFTER `is_adjustment`;
