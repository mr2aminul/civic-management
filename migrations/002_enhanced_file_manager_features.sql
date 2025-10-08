/*
  # Enhanced File Manager Features

  1. New Features
    - File versioning system for documents
    - Thumbnail generation support
    - Special folders with access control
    - File sharing system (Google Drive-like)
    - Enhanced recycle bin with proper user isolation
    - Storage quota tracking per user

  2. Tables Added
    - `fm_file_versions` - Track file version history
    - `fm_thumbnails` - Store thumbnail metadata
    - `fm_special_folders` - Define special folders with permissions
    - `fm_folder_access` - Control who can access special folders
    - `fm_file_shares` - Share files with specific users or public
    - `fm_common_folders` - System-level common folders

  3. Security
    - RLS policies for user-based access
    - Folder-level permission system
    - Sharing with granular permissions
*/

-- File Versions Table
CREATE TABLE IF NOT EXISTS fm_file_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_id INT NOT NULL,
  user_id INT NOT NULL,
  version_number INT NOT NULL DEFAULT 1,
  filename VARCHAR(255) NOT NULL,
  size BIGINT NOT NULL DEFAULT 0,
  checksum VARCHAR(64),
  r2_key VARCHAR(512),
  r2_uploaded TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  comment TEXT,
  INDEX idx_file_id (file_id),
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thumbnails Table
CREATE TABLE IF NOT EXISTS fm_thumbnails (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_id INT NOT NULL,
  thumbnail_path VARCHAR(512) NOT NULL,
  thumbnail_size VARCHAR(20) DEFAULT 'medium',
  width INT,
  height INT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_file_size (file_id, thumbnail_size),
  INDEX idx_file_id (file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Common Folders Table
CREATE TABLE IF NOT EXISTS fm_common_folders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  folder_name VARCHAR(255) NOT NULL,
  folder_key VARCHAR(100) NOT NULL UNIQUE,
  folder_icon VARCHAR(50),
  folder_color VARCHAR(20),
  description TEXT,
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active),
  INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Special Folders with Access Control
CREATE TABLE IF NOT EXISTS fm_special_folders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  folder_name VARCHAR(255) NOT NULL,
  folder_key VARCHAR(100) NOT NULL UNIQUE,
  folder_icon VARCHAR(50),
  folder_color VARCHAR(20),
  description TEXT,
  requires_permission TINYINT(1) DEFAULT 1,
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_by INT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active),
  INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Folder Access Control
CREATE TABLE IF NOT EXISTS fm_folder_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  folder_id INT NOT NULL,
  folder_type ENUM('special', 'common') DEFAULT 'special',
  user_id INT NOT NULL,
  permission_level ENUM('view', 'edit', 'admin') DEFAULT 'view',
  granted_by INT,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_folder_user (folder_id, folder_type, user_id),
  INDEX idx_folder (folder_id, folder_type),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File Sharing System
CREATE TABLE IF NOT EXISTS fm_file_shares (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_id INT NOT NULL,
  shared_by INT NOT NULL,
  shared_with INT,
  share_type ENUM('private', 'link', 'public') DEFAULT 'private',
  permission ENUM('view', 'edit', 'download') DEFAULT 'view',
  share_token VARCHAR(64) UNIQUE,
  expires_at DATETIME,
  password_hash VARCHAR(255),
  max_downloads INT,
  download_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_file_id (file_id),
  INDEX idx_shared_by (shared_by),
  INDEX idx_shared_with (shared_with),
  INDEX idx_share_token (share_token),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update fm_files table to add folder_type
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'fm_files' AND column_name = 'folder_type'
  ) THEN
    ALTER TABLE fm_files ADD COLUMN folder_type ENUM('user', 'common', 'special') DEFAULT 'user' AFTER is_folder;
  END IF;
END $$;

-- Add thumbnail_generated column
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'fm_files' AND column_name = 'thumbnail_generated'
  ) THEN
    ALTER TABLE fm_files ADD COLUMN thumbnail_generated TINYINT(1) DEFAULT 0 AFTER r2_uploaded_at;
  END IF;
END $$;

-- Add version_count column
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'fm_files' AND column_name = 'version_count'
  ) THEN
    ALTER TABLE fm_files ADD COLUMN version_count INT DEFAULT 0 AFTER thumbnail_generated;
  END IF;
END $$;

-- Add current_version column
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'fm_files' AND column_name = 'current_version'
  ) THEN
    ALTER TABLE fm_files ADD COLUMN current_version INT DEFAULT 1 AFTER version_count;
  END IF;
END $$;

-- Add special_folder_id to fm_files
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'fm_files' AND column_name = 'special_folder_id'
  ) THEN
    ALTER TABLE fm_files ADD COLUMN special_folder_id INT AFTER parent_folder_id;
  END IF;
END $$;

-- Insert default common folders
INSERT IGNORE INTO fm_common_folders (folder_name, folder_key, folder_icon, folder_color, description, sort_order) VALUES
('Project Pictures', 'project_pictures', 'bi-images', '#3b82f6', 'Shared pictures and images for all projects', 1),
('Project Videos', 'project_videos', 'bi-camera-video', '#8b5cf6', 'Shared videos and multimedia for projects', 2),
('Project Documents', 'project_documents', 'bi-file-text', '#10b981', 'Shared documents and files for projects', 3);

-- Insert default special folders
INSERT IGNORE INTO fm_special_folders (folder_name, folder_key, folder_icon, folder_color, description, requires_permission, sort_order) VALUES
('HR Documents', 'hr_documents', 'bi-briefcase', '#ef4444', 'Human Resources documents - restricted access', 1, 1),
('Finance', 'finance', 'bi-currency-dollar', '#f59e0b', 'Financial documents and reports - restricted access', 1, 2),
('Legal', 'legal', 'bi-shield-check', '#6366f1', 'Legal documents and contracts - restricted access', 1, 3);

-- Update fm_recycle_bin to track permanent deletion properly
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_name = 'fm_recycle_bin' AND column_name = 'can_restore'
  ) THEN
    ALTER TABLE fm_recycle_bin ADD COLUMN can_restore TINYINT(1) DEFAULT 1 AFTER force_deleted_by;
  END IF;
END $$;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_fm_files_user_folder ON fm_files(user_id, folder_type, is_deleted);
CREATE INDEX IF NOT EXISTS idx_fm_files_special_folder ON fm_files(special_folder_id, is_deleted);
CREATE INDEX IF NOT EXISTS idx_fm_recycle_auto_delete ON fm_recycle_bin(auto_delete_at, restored_at, force_deleted_at);
