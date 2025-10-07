# File Manager Critical Fixes - October 2025

## Issues Fixed

### 1. Missing Function Definition - fm_stream_file_download()
**Error**: Fatal error: Call to undefined function fm_stream_file_download()

**Fix**: Added the missing function definition to `assets/includes/file_manager_helper.php`:
- Function properly handles file streaming for downloads
- Sets appropriate headers for file transfer
- Supports custom download filenames
- Includes proper error handling for missing files

### 2. Database Backup Download Path Issue
**Error**: File not found when downloading database backups from /backups/ folder

**Fix**: Updated `xhr/file_manager.php` download_local case:
- Now checks both storage directory and backup directory
- Properly validates file paths against both allowed directories
- Supports downloading both regular files and database backups

### 3. Upload to R2 Queue Error
**Error**: Failed to queue upload (ArgumentCountError in prepared statement)

**Fix**: Fixed database query parameter binding in upload queue functionality

### 4. R2 Metadata Caching for Database Backups
**Implementation**: Created new endpoint `list_r2_backups`:
- Caches R2 backup list in local JSON file
- Reduces R2 API calls and costs
- Auto-refreshes from R2 when cache doesn't exist
- Stores metadata in `/backups/r2_backups_metadata.json`

### 5. Selective Table Restore
**Implementation**: Added `fm_restore_selective_tables()` function:
- Allows restoring specific tables from full backup
- Parses SQL dump and extracts only requested tables
- Supports both shell and PHP fallback modes
- Updated restore API to accept `mode=selective` and `tables` parameter

### 6. Google Drive-style Upload Progress
**Implementation**: Complete upload progress system:
- Bottom-right popup showing upload progress
- Individual file progress bars
- Success/error states with visual indicators
- Real-time progress tracking using XHR events
- Minimize/clear/close controls
- Responsive design for mobile

### 7. Notification System
**Implementation**: Toast-style notifications:
- Success notifications (green)
- Error notifications (red)
- Auto-dismiss after 5 seconds
- Manual close button
- Stacked notifications support
- Smooth animations
- Functions: `pos5_success_noti(message)` and `pos4_error_noti(message)`

### 8. Direct R2 Upload in Backup Cron
**Updated**: `cron-backup.php`:
- Database backups now upload directly to R2
- Updates local metadata cache after upload
- Uploads metadata JSON to R2 as well
- Maintains consistency between local and remote
- Falls back to queue on upload failure

## API Changes

### New Endpoints

#### `list_r2_backups`
Lists database backups from R2 with caching
```
GET ?f=file_manager&s=list_r2_backups
Response: { status: 200, backups: [...] }
```

### Updated Endpoints

#### `restore_db_local`
Now supports selective table restore
```
POST ?f=file_manager&s=restore_db_local
Parameters:
  - file: backup filename
  - confirm_token: "RESTORE_NOW"
  - mode: "full" or "selective"
  - tables: array of table names (for selective mode)
  - target_db: optional target database
```

#### `download_local`
Now handles both storage and backup directories
```
GET ?f=file_manager&s=download_local&file={filename}
```

## File Changes

### Modified Files
1. `assets/includes/file_manager_helper.php`
   - Added `fm_stream_file_download()`
   - Added `fm_sync_user_quota()`
   - Added `fm_calculate_total_storage()`
   - Added `fm_restore_selective_tables()`

2. `xhr/file_manager.php`
   - Fixed download_local to support backup directory
   - Added list_r2_backups endpoint
   - Updated restore_db_local for selective restore
   - Removed duplicate fm_stream_file_download definition

3. `cron-backup.php`
   - Complete rewrite to support direct R2 upload
   - Metadata cache management
   - Upload metadata file to R2

4. `manage/pages/file_manager/content.phtml`
   - Added upload progress popup UI
   - Added notification system UI
   - Implemented upload queue with progress tracking
   - Added notification functions
   - Updated all operations to use notifications

## CSS Classes Added

### Upload Progress Popup
- `.fm-upload-popup` - Main popup container
- `.fm-upload-popup-header` - Popup header
- `.fm-upload-popup-body` - Scrollable body
- `.fm-upload-item` - Individual upload item
- `.fm-upload-item.completed` - Completed upload
- `.fm-upload-item.error` - Failed upload

### Notifications
- `.fm-notification` - Notification container
- `.fm-notification.success` - Success notification
- `.fm-notification.error` - Error notification

## Testing Recommendations

1. Test file uploads with progress tracking
2. Test database backup downloads
3. Test selective table restore
4. Test R2 metadata caching
5. Test notification system
6. Test mobile responsiveness
7. Verify backup cron job functionality

## Future Enhancements

Suggested improvements for future development:
1. Online editors for Excel, Word, and other documents
2. File preview for more file types
3. Batch operations (move, copy)
4. File versioning
5. Collaborative features
6. Advanced search with filters
7. Thumbnail generation for images
8. Video preview and playback
