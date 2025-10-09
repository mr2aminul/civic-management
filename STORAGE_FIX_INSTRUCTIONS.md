# Storage Tracking Fix Instructions

## Problem Summary

You were experiencing three main issues:

1. **Migration SQL Error**: Ambiguous column 'user_id' in the ON DUPLICATE KEY UPDATE clause
2. **Duplicate Entry Error**: When uploading files, getting "Duplicate entry '77' for key 'PRIMARY'" error
3. **Storage Not Showing**: Storage counters showing 0 B despite 2 GB of files uploaded

## What Was Fixed

### 1. Migration SQL (005_enhanced_folder_storage_management.sql)
**Fixed the ambiguous column error:**
```sql
-- Before (WRONG):
ON DUPLICATE KEY UPDATE user_id = user_id;

-- After (CORRECT):
ON DUPLICATE KEY UPDATE fm_user_storage_tracking.updated_at = NOW();
```

### 2. fm_insert Function (file_manager_helper.php)
**Added intelligent duplicate key handling:**
- When a duplicate entry error occurs on `fm_user_storage_tracking`, the function now automatically converts it to an UPDATE instead of failing
- This prevents the "Duplicate entry" error from blocking file uploads
- The function now handles concurrent uploads gracefully

### 3. Storage Tracking System
**Created tools to recalculate and fix storage:**
- SQL script for direct database fix
- PHP script with web interface for easy execution

## How to Fix Your Storage Tracking

You have **TWO options** to fix the storage tracking:

---

### Option 1: Run SQL Script Directly (Fastest)

1. Open phpMyAdmin or MySQL command line
2. Select your database
3. Run the SQL script located at: `fix_storage_tracking.sql`
4. This will:
   - Remove any duplicate entries
   - Recalculate all storage from actual files
   - Update both `fm_user_storage_tracking` and `fm_user_quotas` tables

**SQL Script Summary:**
```sql
-- The script will:
-- 1. Remove duplicates
-- 2. Calculate totals from fm_files table
-- 3. Insert or update storage tracking
-- 4. Show final statistics
```

---

### Option 2: Run PHP Script via Web (Easier)

1. Visit: `https://civicgroupbd.com/fix_storage_tracking.php`
2. The script will:
   - Check admin permissions (admin access required)
   - Process each user with files
   - Display real-time progress
   - Show final statistics

**Expected Output:**
```
Processing user ID: 77 ... OK (Files: 50, Size: 2.1 GB)
Processing user ID: 78 ... OK (Files: 25, Size: 500 MB)
...

Summary:
  Success: 10
  Errors: 0

Global Storage Statistics:
  Total Users: 10
  Total Files: 500
  Total Storage: 5.2 GB
  R2 Storage: 1.2 GB
  Local Storage: 4.0 GB
```

---

## After Running the Fix

Once you've run either fix option:

1. **Refresh the File Manager page** - Storage counters should now show correct values
2. **Upload a test file** - Should work without duplicate entry errors
3. **Check storage display** - Should show:
   - My Storage: X GB / 1 GB
   - Global VPS Usage: X GB / 60 GB
   - Correct percentage used

---

## How the System Works Now

### Automatic Tracking
After the fix, storage is tracked automatically:

1. **On File Upload**: Trigger `trg_after_file_insert` updates storage instantly
2. **On R2 Upload**: Trigger `trg_after_file_update` adjusts R2 vs local bytes
3. **On File Delete**: Trigger `trg_after_file_delete` reduces storage count

### Manual Recalculation
If counters ever get out of sync, you can:

**Via API:**
```javascript
fetch('requests.php?f=file_manager&s=update_storage_tracking', {
    method: 'POST'
})
```

**Via Database:**
```sql
CALL sp_update_user_storage_stats(USER_ID);
```

---

## Storage Table Structure

The `fm_user_storage_tracking` table now tracks:

| Column | Purpose |
|--------|---------|
| `user_id` | PRIMARY KEY - User identifier |
| `total_files` | Count of non-deleted files |
| `total_folders` | Count of folders |
| `used_bytes` | Total storage used |
| `quota_bytes` | User's storage limit |
| `r2_uploaded_bytes` | Files backed up to R2 cloud |
| `local_only_bytes` | Files only on local VPS |
| `last_upload_at` | Last file upload timestamp |
| `last_calculated_at` | Last recalculation timestamp |

---

## Troubleshooting

### Still Getting Duplicate Entry Errors?
1. Run the fix script again
2. Check error logs for details
3. Verify the migration was applied: `SHOW TABLES LIKE 'fm_user_storage_tracking'`

### Storage Still Shows 0 B?
1. Clear browser cache
2. Check if triggers are installed: `SHOW TRIGGERS LIKE 'fm_files'`
3. Run the fix script to recalculate

### Permission Issues?
- Ensure you're logged in as admin
- Check database user has CREATE TRIGGER permission
- Verify file permissions on fix script

---

## Technical Details

### Database Triggers
Three triggers maintain storage in real-time:

1. **trg_after_file_insert**: Increments counters on file upload
2. **trg_after_file_update**: Adjusts R2/local split on cloud upload
3. **trg_after_file_delete**: Decrements counters on file deletion

### Duplicate Key Handling
The `fm_insert` function now includes:
```php
if ($errorNo === 1062) {
    // Duplicate entry detected
    // Automatically convert INSERT to UPDATE
    // Continue operation without failure
}
```

### Storage Calculation Formula
```
used_bytes = SUM(file.size WHERE is_deleted = 0)
r2_uploaded_bytes = SUM(file.size WHERE r2_uploaded = 1)
local_only_bytes = used_bytes - r2_uploaded_bytes
usage_percentage = (used_bytes / quota_bytes) * 100
```

---

## Files Modified

1. `migrations/005_enhanced_folder_storage_management.sql` - Fixed ambiguous column
2. `assets/includes/file_manager_helper.php` - Enhanced fm_insert with duplicate handling
3. `fix_storage_tracking.php` - Web interface for fixing storage (NEW)
4. `fix_storage_tracking.sql` - Direct SQL fix script (NEW)
5. `STORAGE_FIX_INSTRUCTIONS.md` - This documentation (NEW)

---

## Next Steps

1. **Run the fix** using Option 1 or 2 above
2. **Test file upload** to verify no more duplicate errors
3. **Check storage display** to confirm correct counting
4. **Monitor for 24 hours** to ensure stability
5. **(Optional) Delete fix scripts** after confirming everything works

---

## Need Help?

If issues persist:
1. Check PHP error logs
2. Check MySQL error logs
3. Verify all migrations have been applied
4. Ensure database triggers are active
5. Contact support with error details

---

**Last Updated**: 2025-10-09
**Migration Version**: 005
**Status**: Ready to deploy
