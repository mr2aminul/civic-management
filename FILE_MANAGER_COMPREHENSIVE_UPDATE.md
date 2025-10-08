# File Manager Comprehensive Update

## Overview
This document outlines the comprehensive update to the file manager system based on all requirements.

## Completed Tasks

### 1. Database Migration ✓
- Created `004_advanced_file_manager_features.sql`
- Added tables for versions, thumbnails, special/common folders, sharing, system settings
- Updated `fm_files` table with new columns
- Enhanced `fm_recycle_bin` table
- Added default common and special folders

## Requirements Implementation Plan

### 2. Document Preview/Edit with Collabora Online
**Implementation:**
- Add Collabora Online integration setting in `fm_system_settings`
- Create iframe-based document viewer for .docx, .xlsx, .pptx files
- Add edit mode with save functionality
- Version control: Save each edit as a new version

**Files to Update:**
- `manage/pages/file_manager/content.phtml` - Add Collabora viewer
- `xhr/file_manager.php` - Add Collabora endpoints
- System settings UI for Collabora URL configuration

### 3. Thumbnail Generation
**Implementation:**
- Generate thumbnails for images: PNG, JPG, JPEG, GIF
- Generate document preview thumbnails using Imagick/GD
- Store thumbnails in `fm_thumbnails` table
- Display thumbnails in grid view instead of icons

**Files to Update:**
- `assets/includes/file_manager_helper.php` - Add thumbnail generation functions
- `xhr/file_manager.php` - Add thumbnail generation on upload
- `manage/pages/file_manager/content.phtml` - Update UI to show thumbnails

### 4. R2 Availability Indicator
**Implementation:**
- Add R2 cloud icon overlay on files uploaded to R2
- Show upload status in file card
- Add context menu option to upload to R2

**Database:**
- Already tracked in `fm_files.r2_uploaded` column

### 5. Non-Deletable File Versioning
**Implementation:**
- Auto-create version on file update
- Store all versions in `fm_file_versions` table
- Versions marked as `is_deletable = 0`
- Auto-delete follows recycle bin rules (30 days)

**API Endpoints:**
- `list_file_versions` - Get all versions of a file
- `restore_file_version` - Restore to a specific version
- `download_file_version` - Download specific version

### 6. Complete Recycle Bin
**Implementation:**
- Move deleted files to recycle bin (30-day retention)
- Users can only restore their own files
- Admins can permanently delete or restore any file
- Auto-delete after 30 days via cron job

**API Endpoints:**
- `list_recycle_bin` - List deleted files
- `restore_from_recycle` - Restore file
- `permanent_delete` - Admin-only permanent deletion
- `clean_recycle_bin` - Admin-only cleanup

### 7. Storage Calculation
**Implementation:**
- Track user quota in `fm_user_quotas` table
- Calculate total storage used across all users
- Admin dashboard shows VPS total (60GB) vs used
- Display storage breakdown per user

**API Endpoints:**
- `get_user_quota` - Get user's storage usage
- `get_system_storage` - Admin: Get total VPS storage usage
- `sync_user_quota` - Recalculate user storage

### 8. Context Menu Updates
**Implementation:**
Remove "New File" from context menu.
Add new context menu structure:
```
Right-click on empty space:
- New Word File
- New txt File
- New Excel File
- Upload Files
- Upload Folder
- New Folder

Right-click on file:
- Preview
- Download
- Rename [NEW]
- Move [NEW]
- Share
- Upload to R2
- Versions
- Delete
```

### 9. User Isolation & Folder Structure
**Implementation:**
```
/Storage/
  ├── Common/
  │   ├── Project Pictures/
  │   ├── Project Videos/
  │   └── Project Documents/
  ├── Special/
  │   ├── HR Documents/ (restricted)
  │   ├── Finance/ (restricted)
  │   └── Legal/ (restricted)
  └── Users/
      ├── user1_folder/
      ├── user2_folder/
      └── user3_folder/
```

**Logic:**
- Non-admin users: See only their own folder + common folders + authorized special folders
- Admin/Moderator: See all folders including all user folders
- Files filtered by `user_id` unless admin

### 10. Common Folders
**Implementation:**
- Predefined common folders: Project Pictures, Project Videos, Project Documents
- All users can upload/view files
- Admin can create/manage common folders
- Deletion rules: Files go to recycle bin, admins can permanently delete

**Sidebar Items:**
```
Storage
├── All Files (user's root)
├── Common Folders
│   ├── Project Pictures
│   ├── Project Videos
│   └── Project Documents
├── Special Folders
│   ├── HR Documents
│   ├── Finance
│   └── Legal
└── User Folders (admin only)
    ├── User1 Folder
    ├── User2 Folder
    └── User3 Folder
```

### 11. Special Folders
**Implementation:**
- Admin-managed restricted folders
- Access granted per user via `fm_folder_access` table
- Users see only folders they have access to
- Admins see all folders

**API Endpoints:**
- `list_special_folders` - List folders user can access
- `manage_special_folder` - Admin: Create/update folder
- `grant_folder_access` - Admin: Grant user access
- `revoke_folder_access` - Admin: Revoke user access

### 12. File Sharing
**Implementation:**
- Share with specific users (private)
- Share via public link
- Set permissions: view, edit, download
- Set expiration date
- Password protect shares
- Track download count

**API Endpoints:**
- `create_file_share` - Create share link
- `list_file_shares` - List file shares
- `revoke_file_share` - Revoke share access
- `access_shared_file` - Public endpoint for share links

### 13. R2 Upload Organization
**Implementation:**
R2 folder structure:
```
r2://bucket/
  ├── files/
  │   ├── Common/
  │   ├── Special/
  │   └── Users/
  ├── thumbnails/
  ├── versions/
  └── backups/
```

## API Functions to Add

### In `xhr/file_manager.php`:
```php
// Already implemented:
- list_common_folders
- list_special_folders
- list_recycle_bin
- restore_from_recycle
- permanent_delete
- clean_recycle_bin
- get_user_quota
- get_system_storage
- create_file_share
- list_file_shares
- revoke_file_share
- list_file_versions
- rename
- move

// Need to add:
- generate_thumbnail
- create_document (Word, Excel, txt)
- restore_file_version
- download_file_version
- upload_folder
- manage_common_folder (admin)
```

## Helper Functions to Add

### In `assets/includes/file_manager_helper.php`:
```php
- fm_generate_thumbnail($filePath, $fileType)
- fm_create_file_version($fileId, $filePath, $comment)
- fm_get_folder_path($folderId, $folderType)
- fm_check_folder_access($userId, $folderId, $folderType)
- fm_move_to_recycle_bin($fileId, $userId)
- fm_calculate_folder_size($folderPath)
```

## Next Steps

1. Run migration: `004_advanced_file_manager_features.sql`
2. Update `file_manager_helper.php` with new functions
3. Update `xhr/file_manager.php` with new API endpoints
4. Update `manage/pages/file_manager/content.phtml` with new UI
5. Test all features

## Critical Notes

- All file operations MUST check user permissions
- Admins (Wo_IsAdmin() || Wo_IsModerator()) bypass restrictions
- Files are NEVER permanently deleted by users, only moved to recycle bin
- Versions are NEVER deletable, only auto-purged with recycle bin after 30 days
- Storage calculations must be accurate for quota enforcement
