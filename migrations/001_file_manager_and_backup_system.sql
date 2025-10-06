-- =====================================================
-- File Manager & Backup System - Complete Migration
-- Created: 2025-10-06
-- =====================================================
-- This migration creates all necessary tables for:
-- 1. File Manager with Drive-like features
-- 2. User quotas and permissions
-- 3. Recycle Bin with 30-day retention
-- 4. Automated backup system
-- 5. Restore tracking and logging
-- =====================================================

-- File Manager: Files & Folders
CREATE TABLE IF NOT EXISTS `fm_files` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `parent_folder_id` INT(11) DEFAULT NULL,
  `filename` VARCHAR(512) NOT NULL,
  `original_filename` VARCHAR(512) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `file_type` VARCHAR(100) DEFAULT NULL,
  `mime_type` VARCHAR(255) DEFAULT NULL,
  `size` BIGINT(20) DEFAULT 0,
  `is_folder` TINYINT(1) DEFAULT 0,
  `is_global` TINYINT(1) DEFAULT 0 COMMENT 'Global folders visible to all',
  `r2_key` VARCHAR(1024) DEFAULT NULL COMMENT 'R2 storage key',
  `r2_uploaded` TINYINT(1) DEFAULT 0,
  `r2_uploaded_at` DATETIME DEFAULT NULL,
  `checksum` VARCHAR(64) DEFAULT NULL COMMENT 'MD5 or SHA256',
  `version` INT(11) DEFAULT 1,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent` (`parent_folder_id`),
  KEY `idx_deleted` (`is_deleted`, `deleted_at`),
  KEY `idx_path` (`path`(255)),
  KEY `idx_r2` (`r2_uploaded`, `r2_key`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Quotas
CREATE TABLE IF NOT EXISTS `fm_user_quotas` (
  `user_id` INT(11) NOT NULL PRIMARY KEY,
  `quota_bytes` BIGINT(20) NOT NULL DEFAULT 1073741824 COMMENT '1GB default',
  `used_bytes` BIGINT(20) NOT NULL DEFAULT 0,
  `updated_at` DATETIME DEFAULT NULL,
  KEY `idx_usage` (`used_bytes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- File Permissions (for shared folders/files)
CREATE TABLE IF NOT EXISTS `fm_permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL COMMENT 'NULL = all users',
  `permission` ENUM('view', 'edit', 'delete', 'admin') DEFAULT 'view',
  `granted_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_file_user` (`file_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recycle Bin (30-day retention)
CREATE TABLE IF NOT EXISTS `fm_recycle_bin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `original_path` VARCHAR(1024) NOT NULL,
  `filename` VARCHAR(512) NOT NULL,
  `size` BIGINT(20) DEFAULT 0,
  `deleted_at` DATETIME NOT NULL,
  `auto_delete_at` DATETIME NOT NULL COMMENT '30 days from deleted_at',
  `restored_at` DATETIME DEFAULT NULL,
  `force_deleted_at` DATETIME DEFAULT NULL,
  `force_deleted_by` INT(11) DEFAULT NULL COMMENT 'Admin who force deleted',
  PRIMARY KEY (`id`),
  KEY `idx_file` (`file_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_auto_delete` (`auto_delete_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- File Upload Queue (for R2)
CREATE TABLE IF NOT EXISTS `fm_upload_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) DEFAULT NULL,
  `local_path` VARCHAR(1024) NOT NULL,
  `remote_key` VARCHAR(1024) NOT NULL,
  `status` ENUM('pending', 'processing', 'done', 'error') DEFAULT 'pending',
  `message` TEXT DEFAULT NULL,
  `retry_count` INT(11) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `unique_upload` (`local_path`(255), `remote_key`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backup Logs
CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `backup_type` ENUM('full', 'table', 'incremental') DEFAULT 'full',
  `filename` VARCHAR(512) NOT NULL,
  `file_path` VARCHAR(1024) DEFAULT NULL,
  `table_name` VARCHAR(255) DEFAULT NULL COMMENT 'For table-specific backups',
  `size` BIGINT(20) DEFAULT 0,
  `status` ENUM('pending', 'inprogress', 'completed', 'failed') DEFAULT 'pending',
  `r2_key` VARCHAR(1024) DEFAULT NULL,
  `r2_uploaded` TINYINT(1) DEFAULT 0,
  `r2_uploaded_at` DATETIME DEFAULT NULL,
  `compression` VARCHAR(50) DEFAULT 'gzip',
  `checksum` VARCHAR(64) DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT 0 COMMENT '0 = auto/cron, >0 = user',
  `created_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`backup_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backup Schedules
CREATE TABLE IF NOT EXISTS `backup_schedules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `schedule_name` VARCHAR(255) NOT NULL,
  `backup_type` ENUM('full', 'incremental') DEFAULT 'full',
  `frequency_hours` INT(11) DEFAULT 6 COMMENT 'Every 6 hours',
  `tables` TEXT DEFAULT NULL COMMENT 'JSON array of tables, NULL = all',
  `enabled` TINYINT(1) DEFAULT 1,
  `last_run_at` DATETIME DEFAULT NULL,
  `next_run_at` DATETIME DEFAULT NULL,
  `retention_days` INT(11) DEFAULT 30,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_next_run` (`enabled`, `next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Restore History
CREATE TABLE IF NOT EXISTS `restore_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `backup_id` INT(11) DEFAULT NULL,
  `backup_filename` VARCHAR(512) NOT NULL,
  `restored_from` ENUM('local', 'r2') DEFAULT 'local',
  `target_database` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'inprogress', 'completed', 'failed') DEFAULT 'pending',
  `tables_restored` TEXT DEFAULT NULL COMMENT 'JSON array',
  `error_message` TEXT DEFAULT NULL,
  `restored_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backup` (`backup_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- File Activity Log
CREATE TABLE IF NOT EXISTS `fm_activity_log` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `file_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL COMMENT 'upload, download, delete, restore, share, edit',
  `details` TEXT DEFAULT NULL COMMENT 'JSON',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(512) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_file` (`file_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default backup schedule (every 6 hours)
INSERT INTO `backup_schedules` (`schedule_name`, `backup_type`, `frequency_hours`, `enabled`, `retention_days`, `next_run_at`, `created_at`)
VALUES ('Auto Full Backup', 'full', 6, 1, 30, DATE_ADD(NOW(), INTERVAL 6 HOUR), NOW())
ON DUPLICATE KEY UPDATE `schedule_name` = `schedule_name`;

-- Create default global folders
INSERT INTO `fm_files` (`user_id`, `parent_folder_id`, `filename`, `original_filename`, `path`, `is_folder`, `is_global`, `created_at`)
VALUES
(0, NULL, 'Shared Documents', 'Shared Documents', '/Shared Documents', 1, 1, NOW()),
(0, NULL, 'Company Resources', 'Company Resources', '/Company Resources', 1, 1, NOW()),
(0, NULL, 'Templates', 'Templates', '/Templates', 1, 1, NOW())
ON DUPLICATE KEY UPDATE `filename` = `filename`;

-- =====================================================
-- End of Migration
-- =====================================================
