# File Manager Fixes - Folder Structure & Storage Calculation

## Problems Fixed

### 1. Folder Structure Issue
**Problem:** The base directory wasn't properly isolated per user. Non-admin users should see `/storage/{user_id}` as their root, while admins should see `/storage/`.

**Solution:**
- Modified `fm_get_local_dir()` to accept an optional `$userId` parameter
- For non-admin users, it automatically returns `/storage/{user_id}` directory
- For admins (or when no userId provided), it returns the root storage directory
- All file operations now pass the userId to ensure proper directory isolation

**Changes in `file_manager_helper.php`:**
```php
function fm_get_local_dir($userId = null) {
    $cfg = fm_get_config();
    $dir = $cfg['local_storage'];

    // If userId is provided and user is not admin, return user-specific directory
    if ($userId !== null && $userId > 0) {
        $isAdmin = (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) ||
                  (function_exists('is_admin') && is_admin()) ||
                  (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

        if (!$isAdmin) {
            $dir = $dir . '/storage/' . $userId;
        }
    }

    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}
```

### 2. Storage Calculation Issue
**Problem:** Storage usage was showing 0 bytes even though files existed. The storage tracking wasn't calculating actual disk usage.

**Solution:**
- Created new function `fm_calculate_directory_size()` that recursively scans directories
- Modified `fm_update_storage_tracking()` to actually scan disk and calculate real usage
- Storage is now automatically recalculated when `get_init_data` is called
- Both user-specific and global storage now show accurate numbers

**New function in `file_manager_helper.php`:**
```php
function fm_calculate_directory_size($directory, &$fileCount = 0, &$folderCount = 0) {
    $totalSize = 0;

    if (!is_dir($directory)) {
        return 0;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            // Skip .thumbnails directory
            if (strpos($file->getPathname(), '/.thumbnails') !== false) {
                continue;
            }

            if ($file->isFile()) {
                $totalSize += $file->getSize();
                $fileCount++;
            } elseif ($file->isDir()) {
                $folderCount++;
            }
        }
    } catch (Exception $e) {
        fm_log_error('Error calculating directory size', ['directory' => $directory, 'error' => $e->getMessage()]);
    }

    return $totalSize;
}
```

**Modified `fm_update_storage_tracking()`:**
- Now scans actual directories on disk
- Calculates file counts, folder counts, and total bytes used
- Updates both `fm_user_storage_tracking` and `fm_user_quotas` tables
- Properly handles R2 uploaded vs local-only storage

### 3. Frontend Folder Display
**Problem:** Left sidebar wasn't showing folders because the API wasn't returning folder data correctly.

**Solution:**
- Modified `fm_get_folder_contents()` to properly handle user folder types
- For non-admin users accessing "user" folder type, uses empty path (since isolation is handled by `fm_get_local_dir`)
- For admin users, uses `storage/{userId}` as the path

### 4. File Operations Updated
All file operations now properly use the userId-aware directory structure:
- `list_local_folder` - passes userId
- `create_folder` - uses userId-specific base dir
- `upload_local` - uses userId-specific base dir
- `download_local` - checks both user dir and root dir (for admins)
- `delete_local` - uses userId-specific base dir
- `rename` - uses userId-specific base dir
- `move` - uses userId-specific base dir
- `save_file_content` - uses userId-specific base dir
- `create_new_file` - uses userId-specific base dir
- `get_file_url` - uses userId-specific base dir

### 5. Global Storage Calculation
**Problem:** Global storage was not calculating correctly.

**Solution:**
- Modified `fm_get_global_storage_usage()` to fallback to direct table aggregation
- Sums up all users' storage from `fm_user_storage_tracking` table
- Shows accurate total across all users

## Testing Recommendations

1. **Test as non-admin user:**
   - Login as regular user
   - Check that "My Files" shows up in sidebar
   - Upload files - should go to `/storage/{user_id}/`
   - Check storage shows actual usage (not 0 bytes)

2. **Test as admin:**
   - Login as admin
   - Should see root `/storage/` directory
   - Should see global VPS usage statistics
   - Can navigate to other users' storage via `/storage/{user_id}/`

3. **Test storage calculation:**
   - Refresh page - storage should auto-update
   - Upload new files - storage should increase
   - Delete files - storage should decrease
   - Global usage (admin only) should show sum of all users

## Files Modified

1. `/tmp/cc-agent/58283362/project/assets/includes/file_manager_helper.php`
   - Modified `fm_get_local_dir()` - added userId parameter
   - Modified `fm_list_local_folder()` - added userId parameter
   - Modified `fm_get_folder_contents()` - fixed user folder path logic
   - Added `fm_calculate_directory_size()` - new function
   - Modified `fm_update_storage_tracking()` - calculate actual disk usage
   - Modified `fm_get_global_storage_usage()` - fallback to table aggregation

2. `/tmp/cc-agent/58283362/project/xhr/file_manager.php`
   - Updated all file operation endpoints to pass userId to `fm_get_local_dir()`
   - Added auto storage update in `get_init_data` endpoint
   - Updated `download_local` to handle both user-specific and root directories

## Directory Structure

After these fixes, the storage structure works as follows:

```
/storage/
├── storage/
│   ├── 1/           # User ID 1's files
│   │   ├── file1.txt
│   │   └── subfolder/
│   ├── 2/           # User ID 2's files
│   │   └── file2.txt
│   └── 3/           # User ID 3's files
├── common/          # Common folders (if configured)
└── special/         # Special folders (if configured)
```

**For non-admin users:**
- Base directory: `/storage/storage/{user_id}/`
- All operations are isolated to their directory
- Cannot access other users' files

**For admin users:**
- Base directory: `/storage/`
- Can access all directories
- Can navigate to any user's storage

## Important Notes

1. The storage tracking is updated automatically when the page loads (`get_init_data`)
2. Storage calculation skips the `.thumbnails` directory to avoid double-counting
3. Admin users see both their personal storage and global VPS usage
4. File paths in the database should be relative to the user's storage directory
5. All file operations respect the user isolation model
