# File Manager & Backup System - Complete Implementation Guide

## Overview

This is a comprehensive file manager and backup system with Google Drive-like features including:

### File Manager Features:
- **User Quotas**: Each user has allocated storage (default 1GB, configurable)
- **Folder Structure**: Hierarchical folder organization
- **Global Folders**: Shared folders visible to all users
- **File Upload/Download**: Multi-file support with drag & drop
- **R2 Storage**: Automatic upload to Cloudflare R2 for offsite backup
- **Recycle Bin**: 30-day retention with automatic cleanup
- **File Permissions**: Share files with specific users or globally
- **Activity Logging**: Track all file operations
- **Online Editing**: Edit text files, spreadsheets directly in browser

### Backup System Features:
- **Automated Backups**: Full database backup every 6 hours
- **Table-wise Backups**: Backup individual tables
- **30-day Retention**: Automatic cleanup of old backups
- **R2 Upload**: All backups uploaded to R2 automatically
- **Restore System**: Restore full database or individual tables
- **Backup History**: Complete log of all backups and restores

## Installation Steps

### Step 1: Run Database Migration

Execute the migration SQL file to create all necessary tables:

```bash
mysql -u your_user -p your_database < migrations/001_file_manager_and_backup_system.sql
```

Or via phpMyAdmin: Import the file `migrations/001_file_manager_and_backup_system.sql`

### Step 2: Configure Environment Variables

Add these to your `.env` file (they're already present):

```env
# Storage Directories
LOCAL_STORAGE_DIR=/home/civicbd/civicgroup/storage
DB_BACKUP_LOCAL_DIR=/home/civicbd/civicgroup/backups

# R2 Configuration
R2_ACCESS_KEY_ID=2c3443e44db2a753265134fbe0a65f67
R2_SECRET_ACCESS_KEY=d24621ce0729f68776d155a3ead711679c249baafffd5a5dc581ddcf185d786e
R2_BUCKET=civic-management
R2_ENDPOINT=https://90f483339efd91e1a8819e04ba6e31e6.r2.cloudflarestorage.com
R2_ENDPOINT_DOMAIN=https://cdn.civicgroubd.com

# Quotas & Settings
DEFAULT_USER_QUOTA_GB=1
AUTO_UPLOAD_TYPES=sql,zip,xlsx,docx,pdf
AUTO_UPLOAD_PREFIXES=db_,sys_
RECYCLE_RETENTION_DAYS=30
BACKUP_RETENTION_DAYS=30

# Database Connection (for backups)
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_NAME=your_db_name
DB_HOST=127.0.0.1
```

### Step 3: Set Up Cron Job for Automated Backups

Add this to your crontab (`crontab -e`):

```cron
# Run backup every 6 hours
0 */6 * * * /usr/bin/php /path/to/your/project/cron-backup.php >> /var/log/backup-cron.log 2>&1

# Process R2 upload queue every 15 minutes
*/15 * * * * /usr/bin/php /path/to/your/project/cron-upload-queue.php >> /var/log/upload-queue.log 2>&1

# Clean recycle bin daily
0 2 * * * /usr/bin/php /path/to/your/project/cron-cleanup.php >> /var/log/cleanup.log 2>&1
```

### Step 4: Create Directory Structure

```bash
mkdir -p /home/civicbd/civicgroup/storage
mkdir -p /home/civicbd/civicgroup/backups
chmod 755 /home/civicbd/civicgroup/storage
chmod 755 /home/civicbd/civicgroup/backups
chown www-data:www-data /home/civicbd/civicgroup/storage
chown www-data:www-data /home/civicbd/civicgroup/backups
```

## Database Schema

### Tables Created:

1. **fm_files** - Stores all files and folders
2. **fm_user_quotas** - User storage quotas and usage
3. **fm_permissions** - File/folder sharing permissions
4. **fm_recycle_bin** - Deleted files with 30-day retention
5. **fm_upload_queue** - Queue for R2 uploads
6. **backup_logs** - Backup operation history
7. **backup_schedules** - Automated backup schedules
8. **restore_history** - Database restore history
9. **fm_activity_log** - User activity tracking

## API Endpoints

All endpoints are accessible via: `xhr/file_manager.php`

### File Manager Endpoints:

| Endpoint | Method | Description | Admin Only |
|----------|--------|-------------|------------|
| `list_files` | GET | List files in a folder | No |
| `create_folder` | POST | Create new folder | No |
| `upload_file` | POST | Upload file(s) | No |
| `download_file` | GET | Download a file | No |
| `delete_file` | POST | Move file to recycle bin | No |
| `restore_file` | POST | Restore from recycle bin | No |
| `get_quota` | GET | Get user quota info | No |
| `share_file` | POST | Share file with users | No |
| `list_recycle_bin` | GET | List deleted files | No |
| `force_delete` | POST | Permanently delete file | Yes |

### Backup Endpoints:

| Endpoint | Method | Description | Admin Only |
|----------|--------|-------------|------------|
| `create_full_backup` | POST | Create full DB backup | Yes |
| `create_table_backup` | POST | Backup specific table | Yes |
| `list_backups` | GET | List all backups | Yes |
| `download_backup` | GET | Download backup file | Yes |
| `restore_backup` | POST | Restore from backup | Yes |
| `upload_to_r2` | POST | Manual R2 upload | Yes |
| `process_upload_queue` | POST | Process R2 queue | Yes |

## Features Detailed

### 1. User Quotas

Each user has:
- Default quota: 1GB (configurable via `DEFAULT_USER_QUOTA_GB`)
- Automatic quota tracking on upload/delete
- Quota exceeded prevention
- Admin can adjust user quotas in database

### 2. Recycle Bin

- Files moved to recycle bin on delete (not permanently deleted)
- 30-day retention period (configurable via `RECYCLE_RETENTION_DAYS`)
- Auto-cleanup via cron job
- Users can restore their own files
- Admins can force-delete files immediately
- Quota is freed only after permanent deletion

### 3. Global Folders

- Created by default: "Shared Documents", "Company Resources", "Templates"
- Visible to all users
- Any user can upload to global folders
- Admins can create more global folders

### 4. R2 Storage Integration

- Automatic upload for configured file types (sql, zip, xlsx, docx, pdf)
- Automatic upload for files with configured prefixes (db_, sys_)
- Queue-based upload system (non-blocking)
- Upload queue processed by cron every 15 minutes
- File availability indicator showing R2 status

### 5. File Editing

Supports inline editing for:
- **Text files**: .txt, .log, .php, .js, .css, .html, .json, .md, .sql
- **Spreadsheets**: .xlsx, .xls, .csv (view and edit)
- **Images**: Preview with viewer
- **PDFs**: Inline preview

### 6. Automated Backups

- Full database backup every 6 hours
- Automatic upload to R2
- 30-day retention with auto-cleanup
- Backup logs with status tracking
- Email notifications on failure (optional)

### 7. Table-wise Backup & Restore

- Backup individual tables
- Restore individual tables without affecting other data
- Useful for selective data recovery
- Test restores to verify backup integrity

## Usage Examples

### For Regular Users:

1. **Upload Files**:
   - Navigate to File Manager
   - Drag & drop files or click Upload button
   - Files are automatically organized

2. **Create Folders**:
   - Click "Create Folder" button
   - Enter folder name
   - Folder is created in current location

3. **Delete Files**:
   - Right-click file → Delete
   - File moves to Recycle Bin
   - Restore within 30 days if needed

4. **Check Quota**:
   - Quota displayed in sidebar
   - Shows used / total storage

### For Administrators:

1. **Create Backup**:
   - Go to Backup Manager
   - Click "Create Full Backup"
   - Backup auto-uploads to R2

2. **Table Backup**:
   - Select "Table Backup"
   - Choose table from list
   - Backup specific table

3. **Restore Database**:
   - View backup list
   - Click "Restore" on desired backup
   - Confirm restoration
   - Database is restored

4. **Manage User Quotas**:
   - Edit `fm_user_quotas` table
   - Update `quota_bytes` for specific user
   - Changes take effect immediately

## Security Considerations

1. **File Upload Validation**:
   - All filenames are sanitized
   - File types are checked
   - Size limits enforced via quotas

2. **Permission Checks**:
   - Users can only access their own files
   - Global folders accessible to all
   - Admin-only operations protected

3. **R2 Security**:
   - All R2 uploads use private ACL
   - Access via signed URLs only
   - Credentials stored in environment variables

4. **Database Backups**:
   - Credentials stored securely in temp files
   - Temp files deleted after use
   - Backups compressed with gzip

## Troubleshooting

### Backups Not Creating:
- Check `mysqldump` is installed: `which mysqldump`
- Verify database credentials in `.env`
- Check backup directory permissions
- Review backup logs in database

### R2 Uploads Failing:
- Verify R2 credentials in `.env`
- Check S3 SDK is installed: `composer require aws/aws-sdk-php`
- Test R2 connectivity
- Review upload queue table for errors

### Quota Not Updating:
- Check `fm_user_quotas` table exists
- Verify triggers are working
- Manually recalculate: Run quota sync script

### Cron Jobs Not Running:
- Verify cron is enabled: `systemctl status cron`
- Check cron logs: `/var/log/syslog`
- Ensure PHP CLI is accessible
- Verify script paths are correct

## Advanced Configuration

### Adjust Backup Frequency:

Edit `backup_schedules` table:
```sql
UPDATE backup_schedules
SET frequency_hours = 12,
    next_run_at = DATE_ADD(NOW(), INTERVAL 12 HOUR)
WHERE schedule_name = 'Auto Full Backup';
```

### Change User Default Quota:

```env
DEFAULT_USER_QUOTA_GB=5
```

Or per-user:
```sql
UPDATE fm_user_quotas SET quota_bytes = 5368709120 WHERE user_id = 123;
```

### Add Auto-upload File Types:

```env
AUTO_UPLOAD_TYPES=sql,zip,xlsx,docx,pdf,mp4,mov
```

## Maintenance Tasks

### Weekly:
- Review backup logs for failures
- Check disk space on backup directory
- Verify R2 storage costs

### Monthly:
- Review and archive old backups
- Audit user quota usage
- Clean up orphaned files

### Quarterly:
- Test database restore procedure
- Review and update retention policies
- Security audit of file permissions

## API Integration Examples

### Create Backup via API:

```php
<?php
require_once 'assets/includes/file_manager_helper.php';

$result = fm_create_full_backup($userId);

if ($result['success']) {
    echo "Backup created: " . $result['filename'];
} else {
    echo "Backup failed: " . $result['message'];
}
?>
```

### Upload File via API:

```php
<?php
require_once 'assets/includes/file_manager_helper.php';

$result = fm_upload_file($userId, $_FILES['file'], $parentFolderId);

if ($result['success']) {
    echo "File uploaded with ID: " . $result['file_id'];
} else {
    echo "Upload failed: " . $result['message'];
}
?>
```

### Process Upload Queue:

```php
<?php
require_once 'assets/includes/file_manager_helper.php';

$processed = fm_process_upload_queue(20);
echo "Processed " . count($processed) . " items";
?>
```

## Support & Updates

For issues or enhancements, review:
- Migration file: `migrations/001_file_manager_and_backup_system.sql`
- Helper functions: `assets/includes/file_manager_helper.php`
- API endpoints: `xhr/file_manager.php`
- Backup logs table: `backup_logs`

## License

Copyright © 2025 Civic Group BD. All rights reserved.
