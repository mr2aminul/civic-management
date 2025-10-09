/*
  # File Manager Complete Clean Migration

  This is a complete, clean migration that creates all file manager tables from scratch.
  It consolidates and fixes all previous migrations with proper structure and relationships.

  ## Tables Created:

  1. **fm_files** - Core file and folder storage
  2. **fm_user_quotas** - User storage quotas (primary quota table)
  3. **fm_permissions** - File and folder permissions
  4. **fm_recycle_bin** - Soft-deleted items with 30-day retention
  5. **fm_upload_queue** - R2 upload queue management
  6. **fm_activity_log** - File operation logging
  7. **fm_file_versions** - File version history
  8. **fm_thumbnails** - Image/video thumbnails
  9. **fm_common_folders** - Shared folders accessible to all users
  10. **fm_special_folders** - Restricted folders with explicit permissions
  11. **fm_folder_access** - User access control for special folders
  12. **fm_file_shares** - File sharing management
  13. **fm_system_settings** - System-wide settings
  14. **fm_folder_structure** - Hierarchical folder organization
  15. **backup_logs** - Backup operation logs
  16. **backup_schedules** - Automated backup schedules
  17. **restore_history** - Backup restore tracking

  ## Key Features:

  - Unified storage tracking through fm_user_quotas
  - Efficient triggers for automatic quota updates
  - Proper indexes for performance
  - Clean data migration from old tables
  - R2 cloud storage support
*/

-- =====================================================
-- Core File Storage
-- =====================================================

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
  `storage_type` ENUM('user', 'common', 'special', 'system') DEFAULT 'user',
  `storage_folder_id` INT(11) DEFAULT NULL,
  `is_in_user_storage` TINYINT(1) DEFAULT 0,
  `relative_path` VARCHAR(1024) DEFAULT NULL,
  `common_folder_id` INT(11) DEFAULT NULL,
  `special_folder_id` INT(11) DEFAULT NULL,
  `r2_key` VARCHAR(1024) DEFAULT NULL,
  `r2_uploaded` TINYINT(1) DEFAULT 0,
  `r2_uploaded_at` DATETIME DEFAULT NULL,
  `checksum` VARCHAR(64) DEFAULT NULL,
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
  KEY `idx_r2` (`r2_uploaded`, `r2_key`(255)),
  KEY `idx_storage_type` (`storage_type`, `user_id`),
  KEY `idx_user_storage` (`is_in_user_storage`, `user_id`),
  KEY `idx_storage_folder` (`storage_folder_id`),
  KEY `idx_common_folder` (`common_folder_id`),
  KEY `idx_special_folder` (`special_folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- User Quotas (Primary Storage Tracking)
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_user_quotas` (
  `user_id` INT(11) NOT NULL PRIMARY KEY,
  `quota_bytes` BIGINT(20) NOT NULL DEFAULT 1073741824 COMMENT '1GB default',
  `used_bytes` BIGINT(20) NOT NULL DEFAULT 0,
  `total_files` INT(11) DEFAULT 0,
  `total_folders` INT(11) DEFAULT 0,
  `r2_uploaded_bytes` BIGINT(20) DEFAULT 0,
  `local_only_bytes` BIGINT(20) DEFAULT 0,
  `last_upload_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  KEY `idx_usage` (`used_bytes`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- File Permissions
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL COMMENT 'NULL = all users',
  `permission` ENUM('view', 'edit', 'delete', 'admin') DEFAULT 'view',
  `granted_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_file_user` (`file_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Recycle Bin
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_recycle_bin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `original_path` VARCHAR(1024) NOT NULL,
  `filename` VARCHAR(512) NOT NULL,
  `size` BIGINT(20) DEFAULT 0,
  `deleted_at` DATETIME NOT NULL,
  `auto_delete_at` DATETIME NOT NULL,
  `restored_at` DATETIME DEFAULT NULL,
  `force_deleted_at` DATETIME DEFAULT NULL,
  `force_deleted_by` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_file` (`file_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_auto_delete` (`auto_delete_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Upload Queue
-- =====================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Activity Log
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_activity_log` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `file_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(512) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_file` (`file_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- File Versions
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_file_versions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `version_number` INT(11) NOT NULL,
  `filename` VARCHAR(512) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `r2_key` VARCHAR(1024) DEFAULT NULL,
  `size` BIGINT(20) DEFAULT 0,
  `checksum` VARCHAR(64) DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_file_version` (`file_id`, `version_number`),
  KEY `idx_file` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Thumbnails
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_thumbnails` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `thumbnail_size` ENUM('small', 'medium', 'large') DEFAULT 'medium',
  `thumbnail_path` VARCHAR(1024) NOT NULL,
  `r2_key` VARCHAR(1024) DEFAULT NULL,
  `width` INT(11) DEFAULT NULL,
  `height` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_file_size` (`file_id`, `thumbnail_size`),
  KEY `idx_file` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Common Folders
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_common_folders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_key` VARCHAR(100) NOT NULL,
  `folder_path` VARCHAR(1024) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(100) DEFAULT NULL,
  `sort_order` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `read_only` TINYINT(1) DEFAULT 0,
  `max_file_size_mb` INT(11) DEFAULT NULL,
  `allowed_extensions` TEXT DEFAULT NULL,
  `total_files` INT(11) DEFAULT 0,
  `total_size_bytes` BIGINT(20) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_key` (`folder_key`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Special Folders
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_special_folders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_key` VARCHAR(100) NOT NULL,
  `folder_path` VARCHAR(1024) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(100) DEFAULT NULL,
  `requires_permission` TINYINT(1) DEFAULT 1,
  `auto_assign_roles` TEXT DEFAULT NULL,
  `max_file_size_mb` INT(11) DEFAULT NULL,
  `allowed_extensions` TEXT DEFAULT NULL,
  `total_files` INT(11) DEFAULT 0,
  `total_size_bytes` BIGINT(20) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_key` (`folder_key`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Folder Access
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_folder_access` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_id` INT(11) NOT NULL,
  `folder_type` ENUM('common', 'special') NOT NULL,
  `user_id` INT(11) NOT NULL,
  `permission` ENUM('view', 'upload', 'edit', 'delete', 'admin') DEFAULT 'view',
  `granted_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_user` (`folder_id`, `folder_type`, `user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- File Shares
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_file_shares` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `share_token` VARCHAR(64) NOT NULL,
  `share_type` ENUM('public', 'password', 'user') DEFAULT 'public',
  `password` VARCHAR(255) DEFAULT NULL,
  `allowed_user_id` INT(11) DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `max_downloads` INT(11) DEFAULT NULL,
  `download_count` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`share_token`),
  KEY `idx_file` (`file_id`),
  KEY `idx_active` (`is_active`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- System Settings
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_system_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `setting_type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
  `description` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Folder Structure
-- =====================================================

CREATE TABLE IF NOT EXISTS `fm_folder_structure` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_path` VARCHAR(1024) NOT NULL,
  `folder_type` ENUM('user', 'common', 'special', 'system') DEFAULT 'user',
  `parent_id` INT(11) DEFAULT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_path` (`folder_path`(512)),
  KEY `idx_user_type` (`user_id`, `folder_type`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_type` (`folder_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Backup System Tables
-- =====================================================

CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `backup_type` ENUM('full', 'table', 'incremental') DEFAULT 'full',
  `filename` VARCHAR(512) NOT NULL,
  `file_path` VARCHAR(1024) DEFAULT NULL,
  `table_name` VARCHAR(255) DEFAULT NULL,
  `size` BIGINT(20) DEFAULT 0,
  `status` ENUM('pending', 'inprogress', 'completed', 'failed') DEFAULT 'pending',
  `r2_key` VARCHAR(1024) DEFAULT NULL,
  `r2_uploaded` TINYINT(1) DEFAULT 0,
  `r2_uploaded_at` DATETIME DEFAULT NULL,
  `compression` VARCHAR(50) DEFAULT 'gzip',
  `checksum` VARCHAR(64) DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`backup_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_table` (`table_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backup_schedules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `schedule_name` VARCHAR(255) NOT NULL,
  `backup_type` ENUM('full', 'incremental') DEFAULT 'full',
  `frequency_hours` INT(11) DEFAULT 6,
  `tables` TEXT DEFAULT NULL,
  `enabled` TINYINT(1) DEFAULT 1,
  `last_run_at` DATETIME DEFAULT NULL,
  `next_run_at` DATETIME DEFAULT NULL,
  `retention_days` INT(11) DEFAULT 30,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_next_run` (`enabled`, `next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `restore_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `backup_id` INT(11) DEFAULT NULL,
  `backup_filename` VARCHAR(512) NOT NULL,
  `restored_from` ENUM('local', 'r2') DEFAULT 'local',
  `target_database` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'inprogress', 'completed', 'failed') DEFAULT 'pending',
  `tables_restored` TEXT DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `restored_by` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backup` (`backup_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Database Triggers for Automatic Quota Tracking
-- =====================================================

DELIMITER $$

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS `trg_fm_after_file_insert`$$
DROP TRIGGER IF EXISTS `trg_fm_after_file_update`$$
DROP TRIGGER IF EXISTS `trg_fm_after_file_delete`$$

-- Trigger: After file insert
CREATE TRIGGER `trg_fm_after_file_insert`
AFTER INSERT ON `fm_files`
FOR EACH ROW
BEGIN
  IF NEW.is_deleted = 0 THEN
    IF NEW.is_folder = 0 THEN
      -- File inserted
      INSERT INTO fm_user_quotas
        (user_id, used_bytes, total_files, r2_uploaded_bytes, local_only_bytes, last_upload_at, created_at, updated_at)
      VALUES
        (NEW.user_id, NEW.size, 1,
         IF(NEW.r2_uploaded = 1, NEW.size, 0),
         IF(NEW.r2_uploaded = 0, NEW.size, 0),
         NOW(), NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        used_bytes = used_bytes + NEW.size,
        total_files = total_files + 1,
        r2_uploaded_bytes = r2_uploaded_bytes + IF(NEW.r2_uploaded = 1, NEW.size, 0),
        local_only_bytes = local_only_bytes + IF(NEW.r2_uploaded = 0, NEW.size, 0),
        last_upload_at = NOW(),
        updated_at = NOW();
    ELSE
      -- Folder inserted
      INSERT INTO fm_user_quotas
        (user_id, total_folders, created_at, updated_at)
      VALUES
        (NEW.user_id, 1, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        total_folders = total_folders + 1,
        updated_at = NOW();
    END IF;
  END IF;
END$$

-- Trigger: After file update (for deletions and R2 status changes)
CREATE TRIGGER `trg_fm_after_file_update`
AFTER UPDATE ON `fm_files`
FOR EACH ROW
BEGIN
  -- Handle soft delete
  IF NEW.is_deleted = 1 AND OLD.is_deleted = 0 AND NEW.is_folder = 0 THEN
    UPDATE fm_user_quotas
    SET
      used_bytes = GREATEST(0, used_bytes - NEW.size),
      total_files = GREATEST(0, total_files - 1),
      r2_uploaded_bytes = GREATEST(0, r2_uploaded_bytes - IF(NEW.r2_uploaded = 1, NEW.size, 0)),
      local_only_bytes = GREATEST(0, local_only_bytes - IF(NEW.r2_uploaded = 0, NEW.size, 0)),
      updated_at = NOW()
    WHERE user_id = NEW.user_id;
  END IF;

  -- Handle restore from soft delete
  IF NEW.is_deleted = 0 AND OLD.is_deleted = 1 AND NEW.is_folder = 0 THEN
    UPDATE fm_user_quotas
    SET
      used_bytes = used_bytes + NEW.size,
      total_files = total_files + 1,
      r2_uploaded_bytes = r2_uploaded_bytes + IF(NEW.r2_uploaded = 1, NEW.size, 0),
      local_only_bytes = local_only_bytes + IF(NEW.r2_uploaded = 0, NEW.size, 0),
      updated_at = NOW()
    WHERE user_id = NEW.user_id;
  END IF;

  -- Handle R2 upload status change
  IF NEW.is_deleted = 0 AND NEW.is_folder = 0 AND OLD.r2_uploaded != NEW.r2_uploaded THEN
    IF NEW.r2_uploaded = 1 THEN
      UPDATE fm_user_quotas
      SET
        r2_uploaded_bytes = r2_uploaded_bytes + NEW.size,
        local_only_bytes = GREATEST(0, local_only_bytes - NEW.size),
        updated_at = NOW()
      WHERE user_id = NEW.user_id;
    ELSE
      UPDATE fm_user_quotas
      SET
        r2_uploaded_bytes = GREATEST(0, r2_uploaded_bytes - NEW.size),
        local_only_bytes = local_only_bytes + NEW.size,
        updated_at = NOW()
      WHERE user_id = NEW.user_id;
    END IF;
  END IF;

  -- Handle folder deletion
  IF NEW.is_deleted = 1 AND OLD.is_deleted = 0 AND NEW.is_folder = 1 THEN
    UPDATE fm_user_quotas
    SET
      total_folders = GREATEST(0, total_folders - 1),
      updated_at = NOW()
    WHERE user_id = NEW.user_id;
  END IF;

  -- Handle folder restore
  IF NEW.is_deleted = 0 AND OLD.is_deleted = 1 AND NEW.is_folder = 1 THEN
    UPDATE fm_user_quotas
    SET
      total_folders = total_folders + 1,
      updated_at = NOW()
    WHERE user_id = NEW.user_id;
  END IF;
END$$

DELIMITER ;

-- =====================================================
-- Default Data
-- =====================================================

-- System Settings
INSERT INTO `fm_system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`)
VALUES
  ('vps_total_storage_bytes', '64424509440', 'integer', 'Total VPS storage (60 GB)', NOW()),
  ('storage_alert_threshold_percent', '85', 'integer', 'Alert when storage reaches this percentage', NOW()),
  ('auto_create_user_folders', '1', 'boolean', 'Auto-create user storage folders on first upload', NOW()),
  ('default_user_subfolders', '["Documents", "Images", "Videos", "Downloads", "Archives"]', 'json', 'Default subfolders for user storage', NOW())
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `updated_at` = VALUES(`updated_at`);

-- Default Backup Schedule
INSERT INTO `backup_schedules` (`schedule_name`, `backup_type`, `frequency_hours`, `enabled`, `retention_days`, `next_run_at`, `created_at`)
VALUES ('Auto Full Backup', 'full', 6, 1, 30, DATE_ADD(NOW(), INTERVAL 6 HOUR), NOW())
ON DUPLICATE KEY UPDATE `schedule_name` = `schedule_name`;

-- Default Common Folders
INSERT INTO `fm_common_folders` (`folder_name`, `folder_key`, `folder_path`, `description`, `icon`, `sort_order`, `is_active`, `created_at`)
VALUES
  ('Shared Documents', 'shared_documents', 'Common/Shared Documents', 'Documents accessible to all users', 'fa-folder-open', 1, 1, NOW()),
  ('Company Resources', 'company_resources', 'Common/Company Resources', 'Company-wide resources and materials', 'fa-building', 2, 1, NOW()),
  ('Templates', 'templates', 'Common/Templates', 'Document and file templates', 'fa-file-text', 3, 1, NOW()),
  ('Project Pictures', 'project_pictures', 'Common/Project Pictures', 'Shared project images', 'fa-image', 4, 1, NOW()),
  ('Project Videos', 'project_videos', 'Common/Project Videos', 'Shared project videos', 'fa-video', 5, 1, NOW()),
  ('Project Documents', 'project_documents', 'Common/Project Documents', 'Shared project documents', 'fa-file', 6, 1, NOW())
ON DUPLICATE KEY UPDATE `folder_name` = VALUES(`folder_name`);

-- Default Global Folders in fm_files
INSERT INTO `fm_files` (`user_id`, `parent_folder_id`, `filename`, `original_filename`, `path`, `is_folder`, `is_global`, `created_at`)
SELECT 0, NULL, 'Shared Documents', 'Shared Documents', '/Shared Documents', 1, 1, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fm_files` WHERE `path` = '/Shared Documents' AND `is_global` = 1 LIMIT 1);

INSERT INTO `fm_files` (`user_id`, `parent_folder_id`, `filename`, `original_filename`, `path`, `is_folder`, `is_global`, `created_at`)
SELECT 0, NULL, 'Company Resources', 'Company Resources', '/Company Resources', 1, 1, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fm_files` WHERE `path` = '/Company Resources' AND `is_global` = 1 LIMIT 1);

INSERT INTO `fm_files` (`user_id`, `parent_folder_id`, `filename`, `original_filename`, `path`, `is_folder`, `is_global`, `created_at`)
SELECT 0, NULL, 'Templates', 'Templates', '/Templates', 1, 1, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fm_files` WHERE `path` = '/Templates' AND `is_global` = 1 LIMIT 1);

-- =====================================================
-- Data Migration from Old Tables (if they exist)
-- =====================================================

-- Migrate quota data if old fm_user_storage_tracking exists
INSERT INTO fm_user_quotas (user_id, used_bytes, quota_bytes, total_files, total_folders, r2_uploaded_bytes, local_only_bytes, last_upload_at, created_at, updated_at)
SELECT
  user_id,
  used_bytes,
  quota_bytes,
  total_files,
  total_folders,
  r2_uploaded_bytes,
  local_only_bytes,
  last_upload_at,
  created_at,
  updated_at
FROM fm_user_storage_tracking
WHERE EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_NAME = 'fm_user_storage_tracking' AND TABLE_SCHEMA = DATABASE())
ON DUPLICATE KEY UPDATE
  used_bytes = VALUES(used_bytes),
  total_files = VALUES(total_files),
  total_folders = VALUES(total_folders),
  r2_uploaded_bytes = VALUES(r2_uploaded_bytes),
  local_only_bytes = VALUES(local_only_bytes),
  updated_at = VALUES(updated_at);

-- =====================================================
-- Stored Procedures
-- =====================================================

DELIMITER $$

-- Recalculate storage for a specific user
CREATE PROCEDURE IF NOT EXISTS `sp_recalculate_user_storage`(IN p_user_id INT)
BEGIN
  DECLARE v_total_files INT DEFAULT 0;
  DECLARE v_total_folders INT DEFAULT 0;
  DECLARE v_used_bytes BIGINT DEFAULT 0;
  DECLARE v_r2_bytes BIGINT DEFAULT 0;
  DECLARE v_local_bytes BIGINT DEFAULT 0;

  SELECT
    COUNT(CASE WHEN is_folder = 0 THEN 1 END),
    COUNT(CASE WHEN is_folder = 1 THEN 1 END),
    COALESCE(SUM(CASE WHEN is_folder = 0 THEN size ELSE 0 END), 0),
    COALESCE(SUM(CASE WHEN is_folder = 0 AND r2_uploaded = 1 THEN size ELSE 0 END), 0)
  INTO v_total_files, v_total_folders, v_used_bytes, v_r2_bytes
  FROM fm_files
  WHERE user_id = p_user_id AND is_deleted = 0;

  SET v_local_bytes = v_used_bytes - v_r2_bytes;

  INSERT INTO fm_user_quotas
    (user_id, used_bytes, total_files, total_folders, r2_uploaded_bytes, local_only_bytes, created_at, updated_at)
  VALUES
    (p_user_id, v_used_bytes, v_total_files, v_total_folders, v_r2_bytes, v_local_bytes, NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    used_bytes = v_used_bytes,
    total_files = v_total_files,
    total_folders = v_total_folders,
    r2_uploaded_bytes = v_r2_bytes,
    local_only_bytes = v_local_bytes,
    updated_at = NOW();
END$$

-- Recalculate storage for all users
CREATE PROCEDURE IF NOT EXISTS `sp_recalculate_all_storage`()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_user_id INT;
  DECLARE cur CURSOR FOR SELECT DISTINCT user_id FROM fm_files WHERE is_deleted = 0;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_user_id;
    IF done THEN
      LEAVE read_loop;
    END IF;
    CALL sp_recalculate_user_storage(v_user_id);
  END LOOP;
  CLOSE cur;
END$$

DELIMITER ;

-- =====================================================
-- Initial Storage Recalculation
-- =====================================================

-- Recalculate storage for all users who have files
CALL sp_recalculate_all_storage();

-- =====================================================
-- End of Migration
-- =====================================================
