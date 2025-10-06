# File Manager & Backup System - Setup Instructions

## Prerequisites

1. PHP 7.4 or higher
2. MySQL/MariaDB database
3. Composer (for AWS SDK installation)
4. Shell access (for mysqldump/mysql commands)

## Installation Steps

### 1. Install AWS SDK for PHP

The file manager requires AWS SDK for PHP to connect to Cloudflare R2 storage.

```bash
# Navigate to project root
cd /home/civicbd/civicgroup

# Create vendor directory for dependencies
mkdir -p vendor

# Install AWS SDK using Composer
composer require aws/aws-sdk-php

# OR if you want to install in a specific location:
mkdir -p assets/libraries/aws-sdk-php
cd assets/libraries/aws-sdk-php
composer require aws/aws-sdk-php
cd ../../..
```

### 2. Create Required Directories

```bash
# Create storage and backup directories
mkdir -p /home/civicbd/civicgroup/storage
mkdir -p /home/civicbd/civicgroup/backups

# Set proper permissions
chmod 755 /home/civicbd/civicgroup/storage
chmod 755 /home/civicbd/civicgroup/backups
chown -R www-data:www-data /home/civicbd/civicgroup/storage
chown -R www-data:www-data /home/civicbd/civicgroup/backups
```

### 3. Run Database Migration

Execute the migration SQL file to create all required tables:

```bash
mysql -u civicbd_civicgroup -p civicbd_civicgroup < migrations/001_file_manager_and_backup_system.sql
```

Or through phpMyAdmin:
1. Open phpMyAdmin
2. Select database: `civicbd_civicgroup`
3. Go to "SQL" tab
4. Copy/paste contents of `migrations/001_file_manager_and_backup_system.sql`
5. Click "Go"

### 4. Verify Configuration

The `.env` file should contain:

```
R2_ACCESS_KEY_ID=2c3443e44db2a753265134fbe0a65f67
R2_SECRET_ACCESS_KEY=d24621ce0729f68776d155a3ead711679c249baafffd5a5dc581ddcf185d786e
R2_BUCKET=civic-management
R2_ENDPOINT=https://90f483339efd91e1a8819e04ba6e31e6.r2.cloudflarestorage.com
R2_ENDPOINT_DOMAIN=https://cdn.civicgroubd.com

DB_HOST=127.0.0.1
DB_USER=civicbd_civicgroup
DB_PASSWORD=XPPG68M~!sKc
DB_NAME=civicbd_civicgroup

LOCAL_STORAGE_DIR=/home/civicbd/civicgroup/storage
DB_BACKUP_LOCAL_DIR=/home/civicbd/civicgroup/backups
DEFAULT_USER_QUOTA_GB=1
AUTO_UPLOAD_TYPES=sql,zip,xlsx,docx,pdf
AUTO_UPLOAD_PREFIXES=db_,sys_
RECYCLE_RETENTION_DAYS=30
BACKUP_RETENTION_DAYS=30
```

### 5. Test the System

Test the API endpoint:

```bash
# Test ping endpoint
curl "https://civicgroupbd.com/requests.php?f=file_manager&s=ping"

# Test get_env endpoint
curl "https://civicgroupbd.com/requests.php?f=file_manager&s=get_env"
```

Expected response for ping:
```json
{
  "status": 200,
  "r2_enabled": true,
  "local_dir": "/home/civicbd/civicgroup/storage"
}
```

### 6. Setup Cron Jobs (Optional)

For automated backups and cleanup:

```bash
# Edit crontab
crontab -e

# Add these lines:
# Run backup every 6 hours
0 */6 * * * php /home/civicbd/civicgroup/cron-backup.php

# Run upload queue processor every 5 minutes
*/5 * * * * php /home/civicbd/civicgroup/cron-upload-queue.php

# Run cleanup daily at 2 AM
0 2 * * * php /home/civicbd/civicgroup/cron-cleanup.php
```

## Troubleshooting

### Error: "AWS SDK not found"

Install AWS SDK using composer:
```bash
composer require aws/aws-sdk-php
```

### Error: "R2 connection failed"

1. Check R2 credentials in `.env`
2. Verify R2 endpoint is accessible:
   ```bash
   curl -I https://90f483339efd91e1a8819e04ba6e31e6.r2.cloudflarestorage.com
   ```

### Error: "Permission denied" on storage directory

```bash
chmod 755 /home/civicbd/civicgroup/storage
chown -R www-data:www-data /home/civicbd/civicgroup/storage
```

### Error: "Database tables not found"

Run the migration SQL file:
```bash
mysql -u civicbd_civicgroup -p civicbd_civicgroup < migrations/001_file_manager_and_backup_system.sql
```

## API Endpoints

### File Operations

- `?f=file_manager&s=ping` - Check system status
- `?f=file_manager&s=list_local_folder&path=` - List files/folders
- `?f=file_manager&s=upload_local` - Upload files (POST)
- `?f=file_manager&s=download_local&file=` - Download file
- `?f=file_manager&s=delete_local` - Delete file (POST)
- `?f=file_manager&s=create_folder` - Create folder (POST)

### Backup Operations (Admin Only)

- `?f=file_manager&s=create_full_backup` - Create full database backup
- `?f=file_manager&s=create_table_backup` - Create table backup (POST)
- `?f=file_manager&s=list_db_backups` - List all backups
- `?f=file_manager&s=restore_db_local` - Restore from local backup (POST)

### R2 Operations

- `?f=file_manager&s=list_r2&prefix=` - List R2 objects
- `?f=file_manager&s=upload_r2_from_local` - Upload to R2 (POST)
- `?f=file_manager&s=list_upload_queue` - List upload queue (Admin)
- `?f=file_manager&s=process_upload_queue` - Process queue (Admin)

## Security Notes

1. All endpoints require authentication (except ping)
2. Admin endpoints require admin privileges
3. File operations check user permissions
4. All uploads are scanned for malicious content
5. Backups are automatically encrypted in R2

## Support

For issues or questions, check:
1. PHP error logs: `/var/log/php-fpm/error.log`
2. Apache/Nginx logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
3. Application logs in the database
