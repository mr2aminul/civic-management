/*
  # Enhanced Folder Management and Storage Tracking

  1. Enhanced Folder Structure
    - User folders: /Storage/{user_id}/ with organized subfolders
    - Common folders: accessible to all users (created/managed by admins)
    - Special folders: restricted access folders with explicit permissions

  2. Storage Tracking
    - Per-user storage usage tracking
    - Global VPS storage tracking (60 GB limit)
    - Admin view: global usage + per-user breakdown
    - Regular users: only their personal usage

  3. Enhanced Tables
    - `fm_user_storage_tracking` - detailed per-user storage metrics
    - `fm_folder_structure` - hierarchical folder organization
    - Updates to `fm_files` for folder type classification
    - Updates to `fm_common_folders` and `fm_special_folders` with additional fields

  4. Important Notes
    - R2 Storage maintains identical structure to local storage
    - Admins can view/manage all folders and files
    - Regular users can only access their own folders, common folders, and assigned special folders
*/

-- =====================================================
-- Enhanced User Storage Tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_user_storage_tracking` (
  `user_id` INT(11) NOT NULL PRIMARY KEY,
  `total_files` INT(11) DEFAULT 0,
  `total_folders` INT(11) DEFAULT 0,
  `used_bytes` BIGINT(20) DEFAULT 0,
  `quota_bytes` BIGINT(20) DEFAULT 1073741824 COMMENT '1 GB default',
  `r2_uploaded_bytes` BIGINT(20) DEFAULT 0,
  `local_only_bytes` BIGINT(20) DEFAULT 0,
  `last_calculated_at` DATETIME DEFAULT NULL,
  `last_upload_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  KEY `idx_used` (`used_bytes`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing quota data to enhanced tracking table
INSERT INTO `fm_user_storage_tracking`
  (`user_id`, `used_bytes`, `quota_bytes`, `created_at`, `updated_at`)
SELECT
  `user_id`,
  `used_bytes`,
  `quota_bytes`,
  NOW(),
  `updated_at`
FROM `fm_user_quotas`
ON DUPLICATE KEY UPDATE
  `used_bytes` = VALUES(`used_bytes`),
  `quota_bytes` = VALUES(`quota_bytes`),
  `updated_at` = VALUES(`updated_at`);

-- =====================================================
-- Enhanced Folder Structure Table
-- =====================================================
CREATE TABLE IF NOT EXISTS `fm_folder_structure` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL COMMENT 'NULL for system folders',
  `folder_name` VARCHAR(255) NOT NULL,
  `folder_path` VARCHAR(1024) NOT NULL,
  `folder_type` ENUM('user', 'common', 'special', 'system') DEFAULT 'user',
  `parent_id` INT(11) DEFAULT NULL,
  `is_default` TINYINT(1) DEFAULT 0 COMMENT 'Default subfolders like Documents, Images, etc.',
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
-- Update fm_common_folders
-- =====================================================
ALTER TABLE `fm_common_folders`
  ADD COLUMN IF NOT EXISTS `read_only` TINYINT(1) DEFAULT 0 COMMENT 'If true, only admins can upload',
  ADD COLUMN IF NOT EXISTS `max_file_size_mb` INT(11) DEFAULT NULL COMMENT 'Max file size in MB, NULL = no limit',
  ADD COLUMN IF NOT EXISTS `allowed_extensions` TEXT DEFAULT NULL COMMENT 'JSON array of allowed extensions',
  ADD COLUMN IF NOT EXISTS `total_files` INT(11) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `total_size_bytes` BIGINT(20) DEFAULT 0;

-- =====================================================
-- Update fm_special_folders
-- =====================================================
ALTER TABLE `fm_special_folders`
  ADD COLUMN IF NOT EXISTS `auto_assign_roles` TEXT DEFAULT NULL COMMENT 'JSON array of role IDs that get auto access',
  ADD COLUMN IF NOT EXISTS `max_file_size_mb` INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `allowed_extensions` TEXT DEFAULT NULL COMMENT 'JSON array of allowed extensions',
  ADD COLUMN IF NOT EXISTS `total_files` INT(11) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `total_size_bytes` BIGINT(20) DEFAULT 0;

-- =====================================================
-- Update fm_files table for enhanced folder management
-- =====================================================
ALTER TABLE `fm_files`
  ADD COLUMN IF NOT EXISTS `storage_type` ENUM('user', 'common', 'special', 'system') DEFAULT 'user' COMMENT 'Storage location type',
  ADD COLUMN IF NOT EXISTS `storage_folder_id` INT(11) DEFAULT NULL COMMENT 'References fm_folder_structure or common/special folders',
  ADD COLUMN IF NOT EXISTS `is_in_user_storage` TINYINT(1) DEFAULT 0 COMMENT 'True if in /Storage/{user_id}/ path',
  ADD COLUMN IF NOT EXISTS `relative_path` VARCHAR(1024) DEFAULT NULL COMMENT 'Path relative to storage root';

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_fm_files_storage_type ON fm_files(storage_type, user_id);
CREATE INDEX IF NOT EXISTS idx_fm_files_user_storage ON fm_files(is_in_user_storage, user_id);
CREATE INDEX IF NOT EXISTS idx_fm_files_storage_folder ON fm_files(storage_folder_id);

-- =====================================================
-- Global Storage Settings
-- =====================================================
INSERT INTO `fm_system_settings`
  (`setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`)
VALUES
  ('vps_total_storage_bytes', '64424509440', 'integer', 'Total VPS storage (60 GB)', NOW()),
  ('storage_alert_threshold_percent', '85', 'integer', 'Alert when storage reaches this percentage', NOW()),
  ('auto_create_user_folders', '1', 'boolean', 'Automatically create user storage folders on first upload', NOW()),
  ('default_user_subfolders', '["Documents", "Images", "Videos", "Downloads", "Archives"]', 'json', 'Default subfolders for user storage', NOW())
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `updated_at` = VALUES(`updated_at`);

-- =====================================================
-- Create Default Common Folders with Enhanced Settings
-- =====================================================
UPDATE `fm_common_folders`
SET
  `read_only` = 0,
  `total_files` = 0,
  `total_size_bytes` = 0
WHERE `folder_key` IN ('project_pictures', 'project_videos', 'project_documents');

-- =====================================================
-- Create Storage Tracking Views
-- =====================================================

-- View: Per-user storage summary
CREATE OR REPLACE VIEW `v_user_storage_summary` AS
SELECT
  ust.user_id,
  ust.used_bytes,
  ust.quota_bytes,
  ust.total_files,
  ust.total_folders,
  ust.r2_uploaded_bytes,
  ust.local_only_bytes,
  ROUND((ust.used_bytes / ust.quota_bytes * 100), 2) as usage_percentage,
  ust.last_upload_at,
  ust.last_calculated_at
FROM fm_user_storage_tracking ust
WHERE ust.used_bytes > 0
ORDER BY ust.used_bytes DESC;

-- View: Global storage summary
CREATE OR REPLACE VIEW `v_global_storage_summary` AS
SELECT
  COUNT(DISTINCT ust.user_id) as total_users,
  SUM(ust.total_files) as total_files_count,
  SUM(ust.used_bytes) as total_used_bytes,
  (SELECT setting_value FROM fm_system_settings WHERE setting_key = 'vps_total_storage_bytes' LIMIT 1) as vps_total_bytes,
  ROUND(
    (SUM(ust.used_bytes) /
    CAST((SELECT setting_value FROM fm_system_settings WHERE setting_key = 'vps_total_storage_bytes' LIMIT 1) AS DECIMAL)) * 100,
    2
  ) as global_usage_percentage,
  SUM(ust.r2_uploaded_bytes) as total_r2_bytes,
  SUM(ust.local_only_bytes) as total_local_only_bytes
FROM fm_user_storage_tracking ust;

-- View: Common folders summary
CREATE OR REPLACE VIEW `v_common_folders_summary` AS
SELECT
  cf.id,
  cf.folder_name,
  cf.folder_key,
  cf.folder_path,
  cf.total_files,
  cf.total_size_bytes,
  cf.is_active,
  COUNT(f.id) as actual_file_count,
  COALESCE(SUM(f.size), 0) as actual_size_bytes
FROM fm_common_folders cf
LEFT JOIN fm_files f ON f.common_folder_id = cf.id AND f.is_deleted = 0
WHERE cf.is_active = 1
GROUP BY cf.id, cf.folder_name, cf.folder_key, cf.folder_path, cf.total_files, cf.total_size_bytes, cf.is_active;

-- View: Special folders summary
CREATE OR REPLACE VIEW `v_special_folders_summary` AS
SELECT
  sf.id,
  sf.folder_name,
  sf.folder_key,
  sf.folder_path,
  sf.total_files,
  sf.total_size_bytes,
  sf.requires_permission,
  sf.is_active,
  COUNT(DISTINCT fa.user_id) as total_users_with_access,
  COUNT(f.id) as actual_file_count,
  COALESCE(SUM(f.size), 0) as actual_size_bytes
FROM fm_special_folders sf
LEFT JOIN fm_folder_access fa ON fa.folder_id = sf.id AND fa.folder_type = 'special'
LEFT JOIN fm_files f ON f.special_folder_id = sf.id AND f.is_deleted = 0
WHERE sf.is_active = 1
GROUP BY sf.id, sf.folder_name, sf.folder_key, sf.folder_path, sf.total_files, sf.total_size_bytes, sf.requires_permission, sf.is_active;

-- =====================================================
-- Stored Procedures for Storage Management
-- =====================================================

-- Procedure: Create user storage structure
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `sp_create_user_storage_structure`(IN p_user_id INT)
BEGIN
  DECLARE user_storage_path VARCHAR(255);
  DECLARE subfolder_name VARCHAR(100);
  DECLARE done INT DEFAULT FALSE;
  DECLARE cur CURSOR FOR
    SELECT JSON_UNQUOTE(JSON_EXTRACT(setting_value, CONCAT('$[', idx, ']'))) as folder_name
    FROM fm_system_settings,
         JSON_TABLE(setting_value, '$[*]' COLUMNS (idx FOR ORDINALITY)) as jt
    WHERE setting_key = 'default_user_subfolders';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  SET user_storage_path = CONCAT('Storage/', p_user_id);

  -- Create main user storage folder
  INSERT IGNORE INTO fm_folder_structure
    (user_id, folder_name, folder_path, folder_type, is_default, created_at)
  VALUES
    (p_user_id, CONCAT('User ', p_user_id, ' Storage'), user_storage_path, 'user', 1, NOW());

  -- Create default subfolders
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO subfolder_name;
    IF done THEN
      LEAVE read_loop;
    END IF;

    INSERT IGNORE INTO fm_folder_structure
      (user_id, folder_name, folder_path, folder_type, is_default, parent_id, created_at)
    VALUES
      (p_user_id, subfolder_name, CONCAT(user_storage_path, '/', subfolder_name), 'user', 1,
       (SELECT id FROM fm_folder_structure WHERE folder_path = user_storage_path LIMIT 1),
       NOW());
  END LOOP;
  CLOSE cur;

  -- Initialize storage tracking
  INSERT IGNORE INTO fm_user_storage_tracking
    (user_id, total_files, total_folders, used_bytes, created_at)
  VALUES
    (p_user_id, 0, 0, 0, NOW());
END$$
DELIMITER ;

-- Procedure: Update user storage stats
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `sp_update_user_storage_stats`(IN p_user_id INT)
BEGIN
  DECLARE total_files_count INT DEFAULT 0;
  DECLARE total_folders_count INT DEFAULT 0;
  DECLARE total_size BIGINT DEFAULT 0;
  DECLARE r2_size BIGINT DEFAULT 0;
  DECLARE local_size BIGINT DEFAULT 0;

  -- Count files and calculate sizes
  SELECT
    COUNT(*) INTO total_files_count
  FROM fm_files
  WHERE user_id = p_user_id AND is_deleted = 0 AND is_folder = 0;

  SELECT
    COUNT(*) INTO total_folders_count
  FROM fm_files
  WHERE user_id = p_user_id AND is_deleted = 0 AND is_folder = 1;

  SELECT
    COALESCE(SUM(size), 0) INTO total_size
  FROM fm_files
  WHERE user_id = p_user_id AND is_deleted = 0 AND is_folder = 0;

  SELECT
    COALESCE(SUM(size), 0) INTO r2_size
  FROM fm_files
  WHERE user_id = p_user_id AND is_deleted = 0 AND is_folder = 0 AND r2_uploaded = 1;

  SET local_size = total_size - r2_size;

  -- Update tracking table
  INSERT INTO fm_user_storage_tracking
    (user_id, total_files, total_folders, used_bytes, r2_uploaded_bytes, local_only_bytes, last_calculated_at, updated_at)
  VALUES
    (p_user_id, total_files_count, total_folders_count, total_size, r2_size, local_size, NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    total_files = total_files_count,
    total_folders = total_folders_count,
    used_bytes = total_size,
    r2_uploaded_bytes = r2_size,
    local_only_bytes = local_size,
    last_calculated_at = NOW(),
    updated_at = NOW();
END$$
DELIMITER ;

-- Procedure: Update folder statistics
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `sp_update_folder_stats`(
  IN p_folder_id INT,
  IN p_folder_type ENUM('common', 'special')
)
BEGIN
  DECLARE file_count INT DEFAULT 0;
  DECLARE total_bytes BIGINT DEFAULT 0;

  IF p_folder_type = 'common' THEN
    SELECT
      COUNT(*), COALESCE(SUM(size), 0) INTO file_count, total_bytes
    FROM fm_files
    WHERE common_folder_id = p_folder_id AND is_deleted = 0;

    UPDATE fm_common_folders
    SET
      total_files = file_count,
      total_size_bytes = total_bytes,
      updated_at = NOW()
    WHERE id = p_folder_id;

  ELSEIF p_folder_type = 'special' THEN
    SELECT
      COUNT(*), COALESCE(SUM(size), 0) INTO file_count, total_bytes
    FROM fm_files
    WHERE special_folder_id = p_folder_id AND is_deleted = 0;

    UPDATE fm_special_folders
    SET
      total_files = file_count,
      total_size_bytes = total_bytes,
      updated_at = NOW()
    WHERE id = p_folder_id;
  END IF;
END$$
DELIMITER ;

-- =====================================================
-- Triggers for Automatic Storage Tracking
-- =====================================================

-- Trigger: After file insert
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_after_file_insert`
AFTER INSERT ON `fm_files`
FOR EACH ROW
BEGIN
  IF NEW.is_folder = 0 THEN
    -- Update user storage tracking
    UPDATE fm_user_storage_tracking
    SET
      total_files = total_files + 1,
      used_bytes = used_bytes + NEW.size,
      r2_uploaded_bytes = r2_uploaded_bytes + IF(NEW.r2_uploaded = 1, NEW.size, 0),
      local_only_bytes = local_only_bytes + IF(NEW.r2_uploaded = 0, NEW.size, 0),
      last_upload_at = NOW(),
      updated_at = NOW()
    WHERE user_id = NEW.user_id;

    -- Insert if not exists
    IF ROW_COUNT() = 0 THEN
      INSERT INTO fm_user_storage_tracking
        (user_id, total_files, used_bytes, r2_uploaded_bytes, local_only_bytes, last_upload_at, created_at)
      VALUES
        (NEW.user_id, 1, NEW.size, IF(NEW.r2_uploaded = 1, NEW.size, 0), IF(NEW.r2_uploaded = 0, NEW.size, 0), NOW(), NOW());
    END IF;
  ELSE
    -- It's a folder
    UPDATE fm_user_storage_tracking
    SET total_folders = total_folders + 1, updated_at = NOW()
    WHERE user_id = NEW.user_id;

    IF ROW_COUNT() = 0 THEN
      INSERT INTO fm_user_storage_tracking
        (user_id, total_folders, created_at)
      VALUES
        (NEW.user_id, 1, NOW());
    END IF;
  END IF;
END$$
DELIMITER ;

-- Trigger: After file update (for R2 status changes)
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_after_file_update`
AFTER UPDATE ON `fm_files`
FOR EACH ROW
BEGIN
  IF NEW.is_folder = 0 AND OLD.r2_uploaded != NEW.r2_uploaded THEN
    IF NEW.r2_uploaded = 1 THEN
      -- File was uploaded to R2
      UPDATE fm_user_storage_tracking
      SET
        r2_uploaded_bytes = r2_uploaded_bytes + NEW.size,
        local_only_bytes = local_only_bytes - NEW.size,
        updated_at = NOW()
      WHERE user_id = NEW.user_id;
    ELSE
      -- File R2 status was removed
      UPDATE fm_user_storage_tracking
      SET
        r2_uploaded_bytes = r2_uploaded_bytes - NEW.size,
        local_only_bytes = local_only_bytes + NEW.size,
        updated_at = NOW()
      WHERE user_id = NEW.user_id;
    END IF;
  END IF;
END$$
DELIMITER ;

-- Trigger: After file delete (soft delete)
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_after_file_delete`
AFTER UPDATE ON `fm_files`
FOR EACH ROW
BEGIN
  IF NEW.is_deleted = 1 AND OLD.is_deleted = 0 AND NEW.is_folder = 0 THEN
    -- File was soft deleted
    UPDATE fm_user_storage_tracking
    SET
      total_files = total_files - 1,
      used_bytes = used_bytes - NEW.size,
      r2_uploaded_bytes = r2_uploaded_bytes - IF(NEW.r2_uploaded = 1, NEW.size, 0),
      local_only_bytes = local_only_bytes - IF(NEW.r2_uploaded = 0, NEW.size, 0),
      updated_at = NOW()
    WHERE user_id = NEW.user_id;
  END IF;
END$$
DELIMITER ;

-- =====================================================
-- Initial Data Population
-- =====================================================

-- Recalculate storage for all existing users
INSERT INTO fm_user_storage_tracking (user_id, created_at)
SELECT DISTINCT user_id, NOW()
FROM fm_files
WHERE user_id NOT IN (SELECT user_id FROM fm_user_storage_tracking)
ON DUPLICATE KEY UPDATE fm_user_storage_tracking.updated_at = NOW();

-- Update statistics for all users
UPDATE fm_user_storage_tracking ust
SET
  total_files = (
    SELECT COUNT(*) FROM fm_files
    WHERE user_id = ust.user_id AND is_deleted = 0 AND is_folder = 0
  ),
  total_folders = (
    SELECT COUNT(*) FROM fm_files
    WHERE user_id = ust.user_id AND is_deleted = 0 AND is_folder = 1
  ),
  used_bytes = (
    SELECT COALESCE(SUM(size), 0) FROM fm_files
    WHERE user_id = ust.user_id AND is_deleted = 0 AND is_folder = 0
  ),
  r2_uploaded_bytes = (
    SELECT COALESCE(SUM(size), 0) FROM fm_files
    WHERE user_id = ust.user_id AND is_deleted = 0 AND is_folder = 0 AND r2_uploaded = 1
  ),
  last_calculated_at = NOW(),
  updated_at = NOW();

UPDATE fm_user_storage_tracking
SET local_only_bytes = used_bytes - r2_uploaded_bytes;

-- =====================================================
-- End of Migration
-- =====================================================
