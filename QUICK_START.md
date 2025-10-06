# Quick Start Guide - File Manager & Backup System

## Critical Issues Fixed

### ✓ Fixed Missing Functions
All functions called by `xhr/file_manager.php` are now implemented in `file_manager_helper.php`

### ✓ Fixed Missing Endpoint
The `get_env` endpoint is now available at `?f=file_manager&s=get_env`

### ✓ Fixed Configuration
Complete `.env` file with all required settings

### ✓ Fixed AWS SDK Loading
Flexible loading with fallback paths

## Immediate Next Steps

### Step 1: Install AWS SDK (REQUIRED)

```bash
cd /home/civicbd/civicgroup
composer require aws/aws-sdk-php
```

**Without this, R2 storage will not work!**

### Step 2: Run Database Migration (REQUIRED)

```bash
mysql -u civicbd_civicgroup -pXPPG68M~!sKc civicbd_civicgroup < migrations/001_file_manager_and_backup_system.sql
```

This creates 9 tables needed for the file manager system.

### Step 3: Create Directories (REQUIRED)

```bash
mkdir -p /home/civicbd/civicgroup/storage
mkdir -p /home/civicbd/civicgroup/backups
chmod 755 /home/civicbd/civicgroup/storage /home/civicbd/civicgroup/backups
chown -R www-data:www-data /home/civicbd/civicgroup/storage /home/civicbd/civicgroup/backups
```

### Step 4: Test the System

Visit: `https://civicgroupbd.com/test-file-manager.php`

This will show you a complete diagnostic report.

## Quick Test Commands

```bash
# Test ping
curl "https://civicgroupbd.com/requests.php?f=file_manager&s=ping"

# Expected:
# {"status":200,"r2_enabled":true,"local_dir":"/home/civicbd/civicgroup/storage"}

# Test get_env
curl "https://civicgroupbd.com/requests.php?f=file_manager&s=get_env"

# Expected:
# {"status":200,"r2_configured":true,"local_storage":"/home/civicbd/civicgroup/storage"}
```

## What Was Wrong?

### 1. Missing Functions (FIXED)
The XHR endpoint was calling functions that didn't exist. Added 15+ missing functions.

### 2. Missing Endpoint (FIXED)
Frontend was calling `get_env` but it wasn't defined. Added it.

### 3. Incomplete .env (FIXED)
Configuration was missing R2 credentials and DB settings. Added everything.

### 4. AWS SDK Not Found (NEEDS ACTION)
Install with: `composer require aws/aws-sdk-php`

### 5. Database Tables Missing (NEEDS ACTION)
Run: `mysql -u civicbd_civicgroup -pXPPG68M~!sKc civicbd_civicgroup < migrations/001_file_manager_and_backup_system.sql`

## Error Messages You Might See

### "helper missing"
**Fixed!** The helper file is now complete.

### "unknown action"
**Fixed!** Added missing `get_env` action.

### "R2 not configured"
**Action Required:** Install AWS SDK with composer.

### "Database table not found"
**Action Required:** Run the migration SQL file.

### "Permission denied" on storage
**Action Required:** Run `chmod 755` and `chown` commands above.

## Full Documentation

- **SETUP_INSTRUCTIONS.md** - Complete installation guide
- **FIXES_APPLIED.md** - Detailed list of all fixes
- **test-file-manager.php** - Diagnostic tool

## Support

1. Run diagnostic first: `https://civicgroupbd.com/test-file-manager.php`
2. Check what's marked with ✗ (failed)
3. Follow the recommendations shown
4. Re-run diagnostic to verify fixes

## All API Endpoints

### File Operations (User)
- `ping` - System status
- `get_env` - Configuration check
- `list_local_folder` - Browse files
- `upload_local` - Upload files
- `download_local` - Download files
- `delete_local` - Delete files
- `create_folder` - Create folders

### Backup Operations (Admin)
- `create_full_backup` - Full DB backup
- `create_table_backup` - Single table backup
- `list_db_backups` - List backups
- `restore_db_local` - Restore backup

### R2 Operations
- `list_r2` - List R2 files
- `upload_r2_from_local` - Upload to R2
- `list_upload_queue` - View queue
- `process_upload_queue` - Process uploads

## Security Notes

- All endpoints require login (except ping)
- Admin endpoints require admin role
- File operations check permissions
- Database credentials are in .env (not in code)
- R2 credentials are encrypted in transit

---

**Questions?** Check the diagnostic tool first!
