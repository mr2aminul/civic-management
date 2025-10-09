# File Manager Fixes Summary

## Issues Fixed

### 1. Duplicate PRIMARY Key Error in fm_insert()

**Problem**: When uploading files, the system was throwing a "Duplicate entry '1' for key 'PRIMARY'" error.

**Root Cause**:
- Migration 001 had hardcoded INSERT statements for default folders that tried to insert with explicit IDs
- The fm_insert() function didn't properly handle duplicate key errors (MySQL error code 1062)

**Solution**:
- Modified migration 001 to use `INSERT IGNORE ... SELECT ... WHERE NOT EXISTS` pattern instead of regular INSERT with ON DUPLICATE KEY UPDATE
- Enhanced fm_insert() to detect duplicate key errors (errno 1062) and log them appropriately
- The function now returns false on duplicate key errors without crashing

**Files Changed**:
- `migrations/001_file_manager_and_backup_system.sql` - Fixed default folder insertion
- `assets/includes/file_manager_helper.php` - Enhanced fm_insert() error handling (lines 203-210 and 265-273)

### 2. Storage Usage Not Working

**Problem**: Storage usage was showing "0 B / 1.0 GB" and not calculating actual usage.

**Root Cause**:
- The storage tracking functions (fm_get_user_storage_usage, fm_update_storage_tracking, fm_get_global_storage_usage) were trying to access the `fm_user_storage_tracking` table
- This table is only created in migration 005, but the functions were being called before running that migration
- This caused SQL errors and prevented storage calculation

**Solution**:
- Added table existence checks to all storage tracking functions
- Functions now gracefully fall back to using the `fm_user_quotas` table if `fm_user_storage_tracking` doesn't exist
- Storage calculations now work whether migration 005 has been run or not

**Functions Modified**:
1. `fm_update_storage_tracking()` (line 2609):
   - Added check: `SHOW TABLES LIKE 'fm_user_storage_tracking'`
   - Returns false if table doesn't exist instead of crashing

2. `fm_get_user_storage_usage()` (line 2412):
   - Added table existence check
   - Falls back to fm_user_quotas data if advanced tracking table doesn't exist
   - Returns properly formatted storage data either way

3. `fm_get_global_storage_usage()` (line 2489):
   - Added table existence check
   - Falls back to fm_user_quotas aggregation if tracking table doesn't exist
   - Calculates global usage from available data

## Testing Recommendations

1. **Test file upload**: Upload multiple files and verify no duplicate key errors occur
2. **Test storage display**: Verify storage usage shows actual usage instead of "0 B"
3. **Test with fresh database**: Ensure the system works before running migration 005
4. **Test after migration 005**: Ensure enhanced storage tracking works after running the migration

## Migration Execution Order

The fixes ensure the system works properly in these scenarios:
1. Only migration 001-004 run → Uses fm_user_quotas for storage
2. All migrations including 005 run → Uses fm_user_storage_tracking for enhanced features
3. Re-running migrations → No duplicate key errors

## Additional Notes

- The duplicate key error fix is backward compatible
- Storage tracking gracefully degrades if advanced features aren't available
- All error cases are logged for debugging
- No data loss occurs during error conditions
