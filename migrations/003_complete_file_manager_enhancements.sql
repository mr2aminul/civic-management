/*
  # Complete File Manager Enhancements

  1. New Features
    - File versioning with automatic version control
    - Thumbnail generation and storage
    - Special folders with role-based access control
    - Common folders accessible to all users
    - File sharing system (Google Drive-like)
    - Enhanced recycle bin with 30-day retention
    - Total storage calculation (60GB VPS limit)
    - R2 status indicators

  2. Tables Added/Modified
    - `fm_file_versions` - Track all file versions
    - `fm_thumbnails` - Store thumbnail metadata
    - `fm_special_folders` - Define special restricted folders
    - `fm_common_folders` - Define common shared folders
    - `fm_folder_access` - Control who can access special folders
    - `fm_file_shares` - Share files with specific users or public
    - Updated `fm_files` with new columns
    - Updated `fm_recycle_bin` with proper deletion tracking

  3. Security
    - User-based file isolation
    - Folder-level permission system
    - Sharing with granular permissions
    - Admin-only permanent deletion
    - 30-day automatic recycle bin cleanup
*/

-- =====================================================
-- File Versions Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_file_versions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `version_number` INT(11) NOT NULL DEFAULT 1,
  `filename` VARCHAR(512) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `size` BIGINT(20) NOT NULL DEFAULT 0,
  `checksum` VARCHAR(64) DEFAULT NULL,
  `r2_key` VARCHAR(1024) DEFAULT NULL,
  `r2_uploaded` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `is_deletable` TINYINT(1) DEFAULT 0 COMMENT 'Versions are not deletable by users',
  PRIMARY KEY (`id`),
  KEY `idx_file_id` (`file_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_version` (`file_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Thumbnails Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_thumbnails` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `thumbnail_path` VARCHAR(512) NOT NULL,
  `thumbnail_size` VARCHAR(20) DEFAULT 'medium',
  `width` INT(11) DEFAULT NULL,
  `height` INT(11) DEFAULT NULL,
  `r2_key` VARCHAR(1024) DEFAULT NULL,
  `r2_uploaded` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_file_size` (`file_id`, `thumbnail_size`),
  KEY `idx_file_id` (`file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Common Folders Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_common_folders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_key` VARCHAR(100) NOT NULL,
  `folder_path` VARCHAR(512) NOT NULL COMMENT 'Path in storage',
  `folder_icon` VARCHAR(50) DEFAULT 'bi-folder',
  `folder_color` VARCHAR(20) DEFAULT '#3b82f6',
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  `created_by` INT(11) DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_key` (`folder_key`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Special Folders with Access Control
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_special_folders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_key` VARCHAR(100) NOT NULL,
  `folder_path` VARCHAR(512) NOT NULL COMMENT 'Path in storage',
  `folder_icon` VARCHAR(50) DEFAULT 'bi-folder-lock',
  `folder_color` VARCHAR(20) DEFAULT '#ef4444',
  `description` TEXT DEFAULT NULL,
  `requires_permission` TINYINT(1) DEFAULT 1,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_key` (`folder_key`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Folder Access Control
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_folder_access` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_id` INT(11) NOT NULL,
  `folder_type` ENUM('special', 'common') DEFAULT 'special',
  `user_id` INT(11) NOT NULL,
  `permission_level` ENUM('view', 'edit', 'admin') DEFAULT 'view',
  `granted_by` INT(11) DEFAULT NULL,
  `granted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_folder_user` (`folder_id`, `folder_type`, `user_id`),
  KEY `idx_folder` (`folder_id`, `folder_type`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- File Sharing System
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_file_shares` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `file_id` INT(11) NOT NULL,
  `shared_by` INT(11) NOT NULL,
  `shared_with` INT(11) DEFAULT NULL COMMENT 'NULL = public share',
  `share_type` ENUM('private', 'link', 'public') DEFAULT 'private',
  `permission` ENUM('view', 'edit', 'download') DEFAULT 'view',
  `share_token` VARCHAR(64) DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `max_downloads` INT(11) DEFAULT NULL,
  `download_count` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `last_accessed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_share_token` (`share_token`),
  KEY `idx_file_id` (`file_id`),
  KEY `idx_shared_by` (`shared_by`),
  KEY `idx_shared_with` (`shared_with`),
  KEY `idx_share_token` (`share_token`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Update fm_files table with new columns
-- =====================================================

-- Add folder_type column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'folder_type';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN folder_type ENUM(''user'', ''common'', ''special'') DEFAULT ''user'' AFTER is_folder',
  'SELECT "Column folder_type already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add thumbnail_generated column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'thumbnail_generated';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN thumbnail_generated TINYINT(1) DEFAULT 0 AFTER r2_uploaded_at',
  'SELECT "Column thumbnail_generated already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add version_count column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'version_count';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN version_count INT(11) DEFAULT 0 AFTER thumbnail_generated',
  'SELECT "Column version_count already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add current_version column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'current_version';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN current_version INT(11) DEFAULT 1 AFTER version_count',
  'SELECT "Column current_version already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add special_folder_id column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'special_folder_id';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN special_folder_id INT(11) DEFAULT NULL AFTER parent_folder_id',
  'SELECT "Column special_folder_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add common_folder_id column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'common_folder_id';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN common_folder_id INT(11) DEFAULT NULL AFTER special_folder_id',
  'SELECT "Column common_folder_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Update fm_recycle_bin table
-- =====================================================

-- Add can_restore column
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_recycle_bin' AND COLUMN_NAME = 'can_restore';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_recycle_bin ADD COLUMN can_restore TINYINT(1) DEFAULT 1 AFTER force_deleted_by',
  'SELECT "Column can_restore already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Create Indexes for Performance
-- =====================================================

-- fm_files indexes
CREATE INDEX IF NOT EXISTS idx_fm_files_user_folder ON fm_files(user_id, folder_type, is_deleted);
CREATE INDEX IF NOT EXISTS idx_fm_files_special_folder ON fm_files(special_folder_id, is_deleted);
CREATE INDEX IF NOT EXISTS idx_fm_files_common_folder ON fm_files(common_folder_id, is_deleted);

-- fm_recycle_bin indexes
CREATE INDEX IF NOT EXISTS idx_fm_recycle_auto_delete ON fm_recycle_bin(auto_delete_at, restored_at, force_deleted_at);
CREATE INDEX IF NOT EXISTS idx_fm_recycle_can_restore ON fm_recycle_bin(can_restore, user_id);

-- =====================================================
-- Insert Default Common Folders
-- =====================================================
INSERT INTO `fm_common_folders` (`folder_name`, `folder_key`, `folder_path`, `folder_icon`, `folder_color`, `description`, `sort_order`, `created_by`, `created_at`) VALUES
('Project Pictures', 'project_pictures', '/Common/Project Pictures', 'bi-images', '#3b82f6', 'Shared pictures and images for all projects', 1, 0, NOW()),
('Project Videos', 'project_videos', '/Common/Project Videos', 'bi-camera-video', '#8b5cf6', 'Shared videos and multimedia for projects', 2, 0, NOW()),
('Project Documents', 'project_documents', '/Common/Project Documents', 'bi-file-text', '#10b981', 'Shared documents and files for projects', 3, 0, NOW())
ON DUPLICATE KEY UPDATE `folder_name` = VALUES(`folder_name`);

-- =====================================================
-- Insert Default Special Folders
-- =====================================================
INSERT INTO `fm_special_folders` (`folder_name`, `folder_key`, `folder_path`, `folder_icon`, `folder_color`, `description`, `requires_permission`, `sort_order`, `created_by`, `created_at`) VALUES
('HR Documents', 'hr_documents', '/Special/HR Documents', 'bi-briefcase', '#ef4444', 'Human Resources documents - restricted access', 1, 1, 0, NOW()),
('Finance', 'finance', '/Special/Finance', 'bi-currency-dollar', '#f59e0b', 'Financial documents and reports - restricted access', 1, 2, 0, NOW()),
('Legal', 'legal', '/Special/Legal', 'bi-shield-check', '#6366f1', 'Legal documents and contracts - restricted access', 1, 3, 0, NOW())
ON DUPLICATE KEY UPDATE `folder_name` = VALUES(`folder_name`);

-- =====================================================
-- System Settings Table for VPS Storage Limit
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_system_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `setting_type` VARCHAR(50) DEFAULT 'string',
  `description` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert VPS storage limit setting (60 GB)
INSERT INTO `fm_system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
('vps_total_storage_bytes', '64424509440', 'integer', 'Total VPS storage available in bytes (60 GB)', NOW()),
('collabora_enabled', '0', 'boolean', 'Enable Collabora Online for document editing', NOW()),
('collabora_url', '', 'string', 'Collabora Online server URL', NOW())
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- =====================================================
-- End of Migration
-- =====================================================
