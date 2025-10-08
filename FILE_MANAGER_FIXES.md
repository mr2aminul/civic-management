# File Manager Fixes - R2 Status & Thumbnails

## Issues Fixed

### 1. R2 Upload Status Not Showing Correctly

**Problem**: Files were being uploaded to R2 successfully, but the `r2_uploaded` flag in the database remained at 0, making it appear that files were not on R2.

**Root Cause**:
- Files uploaded directly to R2 via the cron job were not having their database records updated
- The `list_local_folder` function only checked the database, not R2 itself
- Files uploaded before the tracking system was in place had no R2 status

**Solutions Implemented**:

1. **Added R2 existence check function** (`fm_check_r2_exists`):
   - Uses S3 `headObject` to check if file exists on R2
   - Returns true/false without downloading the file

2. **Added bulk R2 status sync function** (`fm_sync_all_r2_status`):
   - Checks files marked as not uploaded (`r2_uploaded = 0`)
   - Verifies if they actually exist on R2
   - Updates database records accordingly
   - Processes in batches to avoid timeout

3. **Enhanced file listing**:
   - Now checks R2 directly if file not in database
   - Automatically updates status when mismatch detected
   - Returns accurate R2 status in API responses

4. **Added sync API endpoint** (`sync_r2_status`):
   - Manual sync trigger for admin users
   - Allows fixing status without waiting for cron

5. **Created cron script** (`cron-sync-r2-status.php`):
   - Can run daily/weekly to keep status in sync
   - Processes files in batches
   - Logs progress

**Files Modified**:
- `/xhr/file_manager.php` - Added sync_r2_status endpoint
- `/assets/includes/file_manager_helper.php` - Added helper functions
- `/cron-sync-r2-status.php` - New cron script

### 2. Thumbnail Generation During Upload

**Problem**: Thumbnails were not being generated automatically when images were uploaded.

**Solution Implemented**:

1. **Auto-generate thumbnails on upload**:
   - Added thumbnail generation in `upload_local` case
   - Checks if file is an image (jpg, jpeg, png, gif, webp, bmp)
   - Generates medium-sized thumbnail automatically
   - Updates `thumbnail_generated` flag

2. **Include thumbnail in file listing**:
   - Modified `fm_list_local_folder` to return thumbnail path
   - Thumbnail path included in API response
   - UI can now load thumbnails immediately without extra requests

3. **Optimized UI thumbnail loading**:
   - Checks if thumbnail path provided in file listing
   - Uses provided thumbnail path first (1 request instead of 2-3)
   - Falls back to generating if needed
   - Better performance and fewer API calls

**Files Modified**:
- `/xhr/file_manager.php` - Added thumbnail generation in upload
- `/assets/includes/file_manager_helper.php` - Enhanced file listing
- `/manage/pages/file_manager/content.phtml` - Optimized thumbnail loading

## API Changes

### New Endpoint: `sync_r2_status`

Syncs R2 upload status for files.

**Request**:
```
POST /requests.php?f=file_manager&s=sync_r2_status
{
  "limit": 100  // Optional, default 100
}
```

**Response**:
```json
{
  "status": 200,
  "updated": 45,
  "message": "Synced R2 status for 45 file(s)"
}
```

### Enhanced Response: `list_local_folder`

Now includes thumbnail information:

```json
{
  "status": 200,
  "folders": [...],
  "files": [
    {
      "name": "image.jpg",
      "path": "image.jpg",
      "size": 123456,
      "mtime": 1234567890,
      "r2_uploaded": 1,
      "id": 123,
      "thumbnail_generated": 1,
      "thumbnail": ".thumbnails/thumb_123_medium.jpg"
    }
  ]
}
```

## Database Functions Added

1. **fm_check_r2_exists($remoteKey)**: Check if file exists on R2
2. **fm_sync_all_r2_status($limit)**: Sync R2 status for multiple files
3. **fm_generate_thumbnail($sourceFile, $fileId, $size)**: Generate image thumbnail (already existed, now called during upload)

## Usage

### Manual R2 Status Sync

**Via API** (Admin only):
```javascript
$.post(API + '&s=sync_r2_status', { limit: 100 }, (res) => {
  console.log(res.message);
});
```

**Via Cron**:
```bash
php /path/to/project/cron-sync-r2-status.php
```

**Add to Crontab** (run daily at 3 AM):
```
0 3 * * * php /path/to/project/cron-sync-r2-status.php >> /path/to/logs/r2-sync.log 2>&1
```

### Thumbnail Generation

Thumbnails are now generated automatically on upload. For existing files without thumbnails:

```javascript
// Generate thumbnail for specific file
$.post(API + '&s=generate_thumbnail', {
  file_id: 123,
  size: 'medium'
}, (res) => {
  console.log(res.thumbnail.path);
});
```

## Performance Improvements

1. **Reduced API Calls**:
   - Thumbnail path now included in file listing (1 call instead of 3)
   - Faster page load times

2. **Batch Processing**:
   - R2 status sync processes files in batches
   - Prevents timeout on large file sets

3. **Smart Caching**:
   - Database queries optimized
   - R2 checks only when needed

## Testing

### Test R2 Status Sync

1. Upload a file to R2 manually
2. Check database: `SELECT * FROM fm_files WHERE r2_uploaded = 0`
3. Run sync: `php cron-sync-r2-status.php`
4. Verify status updated in database
5. Check UI shows R2 indicator

### Test Thumbnail Generation

1. Upload an image file
2. Check that thumbnail is generated immediately
3. Verify thumbnail appears in file listing
4. Check `.thumbnails/` directory for generated files

## Migration Notes

For existing installations:

1. **Sync R2 Status**: Run `cron-sync-r2-status.php` once to fix existing files
2. **Generate Missing Thumbnails**: Use batch thumbnail generation (can be added if needed)
3. **Add Cron Jobs**: Add R2 sync to daily cron schedule

## Configuration

Thumbnail settings (in `fm_generate_thumbnail`):
- Small: 100x100px
- Medium: 300x300px
- Large: 600x600px

Supported image formats:
- jpg, jpeg, png, gif, webp, bmp

## Future Enhancements

- [ ] Batch thumbnail generation for existing images
- [ ] Video thumbnail generation (first frame)
- [ ] PDF preview generation (first page)
- [ ] Thumbnail regeneration on file update
- [ ] Configurable thumbnail sizes
