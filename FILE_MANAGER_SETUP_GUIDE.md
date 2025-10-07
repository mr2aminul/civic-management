# File Manager & Backup System - Complete Setup Guide

## 🚀 Quick Start

### Step 1: Run Diagnostics

Visit the diagnostic tool to check your system configuration:

```
http://your-domain.com/diagnose.php
```

This will check:
- PHP configuration and extensions
- Environment variables
- Database connection
- File system permissions
- R2 storage (if configured)

### Step 2: Check Environment Variables

Ensure your `.env` file contains the following variables:

```env
# Database Configuration
DB_HOST=localhost
DB_USER=your_database_user
DB_PASSWORD=your_database_password
DB_NAME=your_database_name

# Local Storage Configuration
LOCAL_STORAGE_DIR=/path/to/storage
DB_BACKUP_LOCAL_DIR=/path/to/backups

# User Quota Configuration
DEFAULT_USER_QUOTA_GB=1

# Auto Upload Configuration
AUTO_UPLOAD_TYPES=sql,zip,xlsx,docx,pdf
AUTO_UPLOAD_PREFIXES=db_,sys_

# Retention Configuration
RECYCLE_RETENTION_DAYS=30
BACKUP_RETENTION_DAYS=30

# R2 Storage Configuration (Optional)
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_ENDPOINT_DOMAIN=

# Debug Mode (1 = enabled, 0 = disabled)
FM_DEBUG=0
```

### Step 3: Run Database Migration

Execute the SQL migration file to create all required tables:

```bash
mysql -u your_user -p your_database < migrations/001_file_manager_and_backup_system.sql
```

Or use the installation script:

```
http://your-domain.com/install_file_manager.php
```

### Step 4: Test the System

Run the complete test suite:

```
http://your-domain.com/test_complete_system.php
```

### Step 5: Access File Manager

Navigate to the file manager interface:

```
http://your-domain.com/manage/pages/file_manager/content.phtml
```

## 🔧 Troubleshooting

### Error Display Not Working

If `ini_set('display_errors', 1)` is not showing errors:

1. **Enable Debug Mode** in `.env`:
   ```env
   FM_DEBUG=1
   ```

2. **Check Error Logs**:
   - Application logs: `logs/file_manager_YYYY-MM-DD.log`
   - PHP error log: Check your server's error log location
   - Debug logs: `logs/file_manager_debug_YYYY-MM-DD.log`

3. **Server Configuration**: Some hosting providers disable `ini_set()` for security. Check:
   ```php
   <?php
   echo "display_errors: " . ini_get('display_errors');
   echo "\nerror_reporting: " . error_reporting();
   echo "\nlog_errors: " . ini_get('log_errors');
   echo "\nerror_log: " . ini_get('error_log');
   ?>
   ```

4. **Check PHP Configuration**:
   - Look for `php.ini` settings that might override runtime configuration
   - Check `.htaccess` or `user.ini` files
   - Verify hosting control panel settings

### Database Connection Issues

If database connection fails:

1. **Verify Credentials**:
   ```bash
   mysql -h localhost -u your_user -p
   ```

2. **Check `.env` File**: Ensure credentials match `config.php`

3. **Test Connection**:
   ```php
   <?php
   $mysqli = new mysqli('localhost', 'user', 'password', 'database');
   if ($mysqli->connect_error) {
       die('Connection failed: ' . $mysqli->connect_error);
   }
   echo 'Connected successfully';
   ?>
   ```

### File Permission Issues

If you encounter permission errors:

```bash
# Set proper ownership
sudo chown -R www-data:www-data /path/to/storage
sudo chown -R www-data:www-data /path/to/backups

# Set proper permissions
chmod -R 755 /path/to/storage
chmod -R 755 /path/to/backups
```

### Missing Tables

If database tables are missing:

```bash
# Re-run migration
mysql -u your_user -p your_database < migrations/001_file_manager_and_backup_system.sql
```

Or use the diagnostic tool to identify missing tables.

## 📊 Features

### File Management
- ✅ Upload files with drag & drop support
- ✅ Create folders and organize files
- ✅ Download files
- ✅ Delete files (moves to recycle bin)
- ✅ File preview and editing
- ✅ User quotas with real-time tracking
- ✅ File permissions and sharing
- ✅ Activity logging

### Backup System
- ✅ Full database backups
- ✅ Table-specific backups
- ✅ Selective restore by tables or categories
- ✅ Automatic backup retention
- ✅ Backup verification with checksums
- ✅ Restore history tracking
- ✅ Shell & PHP fallback modes

### R2 Storage (Optional)
- ✅ Automatic upload to R2 for specific file types
- ✅ Upload queue with retry logic
- ✅ CDN URL generation
- ✅ Batch upload processing
- ✅ Seamless local/cloud file management

### Recycle Bin
- ✅ 30-day retention (configurable)
- ✅ Restore deleted files
- ✅ Automatic cleanup
- ✅ Manual force delete

## 🔐 Security Features

- ✅ User authentication integration
- ✅ Permission-based access control
- ✅ Path traversal protection
- ✅ Secure file uploads
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ CSRF protection

## 📡 API Endpoints

All API endpoints are in `xhr/file_manager.php?s=ACTION`:

### File Operations
- `list_local_folder` - List files and folders
- `upload_local` - Upload files
- `download_local` - Download file
- `delete_local` - Delete file (to recycle bin)
- `create_folder` - Create new folder
- `save_file_content` - Edit file content

### Backup Operations
- `create_full_backup` - Create full database backup
- `create_table_backup` - Backup specific table
- `list_db_backups` - List local backups
- `list_r2_backups` - List R2 backups
- `restore_db_local` - Restore from local backup
- `restore_db_r2` - Restore from R2 backup

### User Management
- `get_user_quota` - Get user quota info
- `sync_user_quota` - Recalculate user quota
- `get_total_storage` - Get total storage usage (admin)

### R2 Operations
- `upload_r2_from_local` - Upload file to R2
- `list_r2` - List R2 objects
- `list_upload_queue` - View upload queue
- `process_upload_queue` - Process pending uploads

### System
- `ping` - Test API connectivity
- `get_env` - Get environment config (admin)
- `save_env` - Save environment config (admin)
- `auto_backup_run` - Automated backup (cron)
- `enforce_retention` - Enforce retention policies

## 🤖 Automated Tasks (Cron Jobs)

### Daily Backup (Recommended)
```bash
# Add to crontab
0 2 * * * php /path/to/project/cron-backup.php
```

### Upload Queue Processing
```bash
# Process every 5 minutes
*/5 * * * * php /path/to/project/cron-upload-queue.php
```

### Cleanup Old Files
```bash
# Run daily at 3 AM
0 3 * * * php /path/to/project/cron-cleanup.php
```

## 📝 Logging

The system provides comprehensive logging:

### Error Logs
Location: `logs/file_manager_YYYY-MM-DD.log`

Captures:
- Database errors
- File operation failures
- API exceptions
- Configuration issues

### Debug Logs
Location: `logs/file_manager_debug_YYYY-MM-DD.log`

Enabled when `FM_DEBUG=1` in `.env`

Captures:
- API calls
- Function execution
- Performance metrics
- Detailed stack traces

### Activity Logs
Stored in database table: `fm_activity_log`

Tracks:
- User actions
- File operations
- Upload/download activity
- Backup/restore operations

## 🎯 Performance Optimization

### Recommended Settings

```env
# Enable caching
FM_CACHE_ENABLED=1

# Large file handling
upload_max_filesize=100M
post_max_size=100M
max_execution_time=300
memory_limit=256M
```

### Database Indexes

All critical tables have proper indexes for:
- User ID lookups
- File path searches
- R2 upload status
- Recycle bin cleanup

### File Storage

- Local files stored with unique IDs to prevent conflicts
- R2 uploads organized by date: `files/YYYY/MM/filename`
- Backups organized: `backups/YYYY/MM/filename`

## 🆘 Support & Debugging

### Enable Debug Mode

```env
FM_DEBUG=1
```

This enables:
- Detailed error messages in API responses
- Debug log files
- Stack traces
- SQL query logging

### Check System Status

```php
// Check configuration
$config = fm_get_config();
print_r($config);

// Check database connection
$conn = fm_get_db();
var_dump($conn);

// Check R2 status
$s3 = fm_init_s3();
var_dump($s3);
```

### Common Issues

1. **"Helper file not found"**
   - Check file path in `xhr/file_manager.php`
   - Verify file exists at `assets/includes/file_manager_helper.php`

2. **"Database connection failed"**
   - Verify credentials in `.env`
   - Test MySQL connection manually
   - Check MySQL user permissions

3. **"Directory not writable"**
   - Set proper permissions: `chmod 755 /path/to/dir`
   - Set proper ownership: `chown www-data:www-data /path/to/dir`

4. **"R2 upload failed"**
   - Verify R2 credentials
   - Check AWS SDK installation
   - Test R2 endpoint connectivity

## 📦 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Extensions: mysqli, json, curl, mbstring, openssl, zip
- Apache/Nginx with mod_rewrite
- AWS SDK for PHP (optional, for R2 storage)

## 🔄 Upgrade Guide

When upgrading:

1. Backup your database
2. Backup your `.env` file
3. Run new migration files
4. Clear cache
5. Test thoroughly

## 📞 Need Help?

1. Run `diagnose.php` for system check
2. Run `test_complete_system.php` for comprehensive tests
3. Check logs in `logs/` directory
4. Enable debug mode for detailed errors
5. Review this documentation

---

**Version:** 2.0.0
**Last Updated:** 2025-10-07
