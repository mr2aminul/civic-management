/*
  # Advanced File Manager Features

  1. New Features
    - Thumbnail generation and storage for all file types
    - File versioning with non-deletable version history
    - Special folders with role-based access (e.g., HR Documents)
    - Common folders accessible to all users (e.g., Project Pictures)
    - Complete recycle bin with 30-day auto-delete
    - File sharing system similar to Google Drive
    - R2 status tracking for each file
    - Total VPS storage tracking (60GB limit)
    - User folder isolation with organized structure

  2. New Tables
    - `fm_file_versions` - Track all file versions (non-deletable)
    - `fm_thumbnails` - Store thumbnail metadata
    - `fm_special_folders` - Restricted folders (admin-managed)
    - `fm_common_folders` - Shared folders (all users)
    - `fm_folder_access` - Access control for special folders
    - `fm_file_shares` - File sharing with permissions
    - `fm_system_settings` - System-wide settings
    - Enhanced `fm_recycle_bin` - Complete recycle bin

  3. Security
    - User-based file isolation
    - Folder-level permissions
    - Admin-only permanent deletion
    - 30-day recycle bin retention
    - Share token system
*/

-- =====================================================
-- File Versions Table (Non-Deletable)
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
  `is_deletable` TINYINT(1) DEFAULT 0 COMMENT 'Versions are protected from deletion',
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
  `thumbnail_size` VARCHAR(20) DEFAULT 'medium' COMMENT 'small, medium, large',
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
-- Common Folders (All Users Can Access)
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_common_folders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_key` VARCHAR(100) NOT NULL,
  `folder_path` VARCHAR(512) NOT NULL,
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
-- Special Folders (Restricted Access)
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_special_folders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_key` VARCHAR(100) NOT NULL,
  `folder_path` VARCHAR(512) NOT NULL,
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
  `shared_with` INT(11) DEFAULT NULL COMMENT 'NULL = public link share',
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
-- System Settings
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

-- =====================================================
-- Update fm_files table
-- =====================================================

-- Add folder_type column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'folder_type');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN folder_type ENUM(''user'', ''common'', ''special'') DEFAULT ''user''',
  'SELECT "Column folder_type already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add thumbnail_generated column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'thumbnail_generated');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN thumbnail_generated TINYINT(1) DEFAULT 0',
  'SELECT "Column thumbnail_generated already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add version_count column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'version_count');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN version_count INT(11) DEFAULT 0',
  'SELECT "Column version_count already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add current_version column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'current_version');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN current_version INT(11) DEFAULT 1',
  'SELECT "Column current_version already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add special_folder_id column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'special_folder_id');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN special_folder_id INT(11) DEFAULT NULL',
  'SELECT "Column special_folder_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add common_folder_id column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_files' AND COLUMN_NAME = 'common_folder_id');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_files ADD COLUMN common_folder_id INT(11) DEFAULT NULL',
  'SELECT "Column common_folder_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Update fm_recycle_bin table
-- =====================================================

-- Add can_restore column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fm_recycle_bin' AND COLUMN_NAME = 'can_restore');

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE fm_recycle_bin ADD COLUMN can_restore TINYINT(1) DEFAULT 1',
  'SELECT "Column can_restore already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Insert Default Common Folders
-- =====================================================
INSERT INTO `fm_common_folders` (`folder_name`, `folder_key`, `folder_path`, `folder_icon`, `folder_color`, `description`, `sort_order`, `created_by`, `created_at`) VALUES
('Project Pictures', 'project_pictures', 'Common/Project Pictures', 'bi-images', '#3b82f6', 'Shared pictures and images for all projects', 1, 0, NOW()),
('Project Videos', 'project_videos', 'Common/Project Videos', 'bi-camera-video', '#8b5cf6', 'Shared videos and multimedia for projects', 2, 0, NOW()),
('Project Documents', 'project_documents', 'Common/Project Documents', 'bi-file-text', '#10b981', 'Shared documents and files for projects', 3, 0, NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- =====================================================
-- Insert Default Special Folders
-- =====================================================
INSERT INTO `fm_special_folders` (`folder_name`, `folder_key`, `folder_path`, `folder_icon`, `folder_color`, `description`, `requires_permission`, `sort_order`, `created_by`, `created_at`) VALUES
('HR Documents', 'hr_documents', 'Special/HR Documents', 'bi-briefcase', '#ef4444', 'Human Resources documents - restricted access', 1, 1, 0, NOW()),
('Finance', 'finance', 'Special/Finance', 'bi-currency-dollar', '#f59e0b', 'Financial documents and reports - restricted access', 1, 2, 0, NOW()),
('Legal', 'legal', 'Special/Legal', 'bi-shield-check', '#6366f1', 'Legal documents and contracts - restricted access', 1, 3, 0, NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- =====================================================
-- Insert System Settings
-- =====================================================
INSERT INTO `fm_system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
('vps_total_storage_bytes', '64424509440', 'integer', 'Total VPS storage available in bytes (60 GB)', NOW()),
('collabora_enabled', '0', 'boolean', 'Enable Collabora Online for document editing', NOW()),
('collabora_url', '', 'string', 'Collabora Online server URL', NOW()),
('recycle_retention_days', '30', 'integer', 'Days to keep files in recycle bin before auto-delete', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- =====================================================
-- Create Indexes for Performance
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_fm_files_folder_type ON fm_files(folder_type, user_id, is_deleted);
CREATE INDEX IF NOT EXISTS idx_fm_files_special_folder ON fm_files(special_folder_id);
CREATE INDEX IF NOT EXISTS idx_fm_files_common_folder ON fm_files(common_folder_id);
CREATE INDEX IF NOT EXISTS idx_fm_recycle_auto_delete ON fm_recycle_bin(auto_delete_at);
CREATE INDEX IF NOT EXISTS idx_fm_recycle_user ON fm_recycle_bin(user_id, can_restore);

-- =====================================================
-- End of Migration
-- =====================================================
