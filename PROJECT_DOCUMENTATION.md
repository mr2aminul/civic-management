# File Manager & Backup System - Complete Documentation

## System Overview

This is a comprehensive file management and backup system with advanced features including file versioning, sharing, thumbnails, special folders, and R2 cloud storage integration.

## Database Schema

### Core Tables

#### fm_files
Main file storage table with user isolation and folder organization.
- User-specific files stored in `MyFiles/`
- Common folder files in `Common/`
- Special folder files in `Special/`
- R2 cloud storage tracking
- Version tracking
- Thumbnail generation status

#### fm_file_versions
Non-deletable version history for all files.
- Automatic version creation on file update
- Complete version metadata (size, checksum, R2 status)
- Protected from deletion
- User comments on versions

#### fm_folders
Hierarchical folder structure with user isolation.
- User folders with privacy controls
- R2 cloud storage status per folder
- Sort ordering

#### fm_thumbnails
Thumbnail storage for all file types.
- Multiple sizes (small, medium, large)
- R2 cloud storage integration
- Automatic generation

### Folder Types

#### fm_common_folders
Shared folders accessible to all users.
- Project Pictures
- Project Videos
- Project Documents
- Customizable icons and colors

#### fm_special_folders
Restricted access folders with permissions.
- HR Documents
- Finance
- Legal
- Requires explicit user permissions

#### fm_folder_access
Permission system for special and common folders.
- View, edit, admin levels
- Grant tracking

### Sharing System

#### fm_file_shares
Google Drive-style file sharing.
- Private shares (specific users)
- Link shares (anyone with token)
- Public shares
- View/edit/download permissions
- Password protection
- Expiration dates
- Download limits

### Backup & Recovery

#### fm_backups
Complete backup system with scheduled and manual backups.
- Local and R2 cloud storage
- Compression tracking
- Restoration support

#### fm_recycle_bin
30-day retention recycle bin.
- Original path preservation
- User-level restoration
- Admin-only permanent deletion
- Auto-delete after 30 days

### System

#### fm_system_settings
System-wide configuration.
- VPS storage limit (60GB)
- Collabora Online integration
- Recycle bin retention
- Feature toggles

#### fm_upload_queue
Background upload queue for R2 cloud storage.
- Automatic retry on failure
- Priority management
- Progress tracking

## Key Features

### 1. User Isolation
- Each user has private `MyFiles/` directory
- User-level permissions and access control
- Private files completely isolated

### 2. File Versioning
- Automatic version creation on file updates
- Non-deletable version history
- Complete metadata tracking
- Version comments

### 3. Folder System
- **User Folders**: Private to each user
- **Common Folders**: Accessible to all users
- **Special Folders**: Restricted access with permissions

### 4. File Sharing
- Share files with specific users
- Generate shareable links
- Password protection
- Expiration dates
- Download limits
- Permission levels (view, edit, download)

### 5. Cloud Storage (R2)
- Automatic background upload
- Status tracking per file
- Queue-based upload system
- Retry on failure

### 6. Thumbnails
- Automatic generation for images
- Multiple sizes
- Cloud storage support

### 7. Backup System
- Scheduled automatic backups
- Manual backup creation
- Local and cloud storage
- Full restoration support

### 8. Recycle Bin
- 30-day retention period
- User can restore own files
- Admin can permanently delete
- Auto-cleanup after 30 days

### 9. Storage Management
- Total VPS storage tracking (60GB limit)
- Per-user storage quotas
- Storage analytics

## File Structure

### PHP Backend
- `/xhr/file_manager.php` - API endpoint for file operations
- `/assets/includes/file_manager_helper.php` - Helper functions
- `/assets/includes/functions_*.php` - Core functions
- `/config.php` - MySql Database and system configuration

### Cron Jobs
- `/cron-backup.php` - Scheduled backups
- `/cron-cleanup.php` - Recycle bin cleanup
- `/cron-upload-queue.php` - R2 upload queue processor

### Frontend Pages
- `/manage/pages/file_manager/content.phtml` - Main file manager interface
- `/manage/pages/file_manager/file_preview_editor.phtml` - File preview and editing
- `/manage/pages/file_manager/advanced_preview.phtml` - Advanced preview features
- `/manage/pages/backup/content.phtml` - Backup management

### Database Migrations
- `/migrations/001_file_manager_and_backup_system.sql` - Core tables
- `/migrations/004_advanced_file_manager_features.sql` - Advanced features (Latest)

## Security Features

1. **User Isolation**: Files are completely isolated per user
2. **Permission System**: Granular access control for special folders
3. **Version Protection**: File versions cannot be deleted
4. **Admin Controls**: Only admins can permanently delete files
5. **Share Tokens**: Secure token-based file sharing
6. **Password Protection**: Optional password protection for shares
7. **Expiration**: Time-limited shares
8. **Download Limits**: Control number of downloads per share

## Storage System

### VPS Storage
- Total limit: 60GB
- Tracked in real-time
- Per-user quotas
- Automatic space calculations

### R2 Cloud Storage
- Background upload queue
- Automatic retry on failure
- Status tracking per file
- Cost optimization through queue system

## System Settings

Configurable through `fm_system_settings` table:

- `vps_total_storage_bytes`: Total VPS storage (60GB = 64424509440 bytes)
- `collabora_enabled`: Enable/disable Collabora Online
- `collabora_url`: Collabora Online server URL
- `recycle_retention_days`: Days to keep files in recycle bin (30 days)

## API Endpoints

File manager operations are handled through `/xhr/file_manager.php` with various actions:

- File upload/download
- Folder creation
- File/folder deletion
- File sharing
- Version management
- Thumbnail generation
- Backup operations
- Recycle bin operations

## Default Configuration

### Common Folders (Created on Installation)
1. Project Pictures - `Common/Project Pictures`
2. Project Videos - `Common/Project Videos`
3. Project Documents - `Common/Project Documents`

### Special Folders (Created on Installation)
1. HR Documents - `Special/HR Documents` (Restricted)
2. Finance - `Special/Finance` (Restricted)
3. Legal - `Special/Legal` (Restricted)

## Maintenance

### Automated Tasks
- Backup creation (scheduled via cron)
- R2 upload queue processing (scheduled via cron)
- Recycle bin cleanup (scheduled via cron - 30 days)

### Manual Operations
- Backup restoration
- Permanent file deletion (admin only)
- Permission management
- Storage quota adjustments

## Database Status

All 4 migrations have been successfully executed:
- ✅ Migration 001: Core file manager and backup system
- ✅ Migration 002: Enhanced features
- ✅ Migration 003: Complete enhancements
- ✅ Migration 004: Advanced features (Latest)

The database is fully configured with all tables, indexes, and default data.
