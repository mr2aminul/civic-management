# File Manager Folders & Storage Fix

## Issues Identified

1. **Common and Special folders showing empty** - No default folders created in the database
2. **Storage not decreasing on file delete** - Missing database triggers
3. **Global VPS usage showing 0%** - Storage tracking not properly initialized

## Solution

A comprehensive fix has been created that addresses all these issues.

## How to Run the Fix

### Step 1: Access the Fix Tool

Open your browser and navigate to:

```
https://civicgroupbd.com/run_fix_folders_storage.php
```

### Step 2: Review Current Status

The tool will show you:
- Current count of common and special folders
- Number of users with storage tracking
- Current global VPS usage

### Step 3: Run the Fix

Click the "▶️ Run Fix Now" button

The tool will:
1. **Create default common folders:**
   - Project Pictures
   - Project Videos
   - Project Documents
   - Templates

2. **Create default special folders:**
   - HR Documents (Restricted)
   - Financial Records (Restricted)
   - Legal Documents (Restricted)

3. **Recalculate storage for all users:**
   - Scans all files for each user
   - Updates storage tracking tables
   - Calculates R2 vs local storage

4. **Create database triggers:**
   - Automatic storage updates on file upload
   - Automatic storage updates on file delete
   - Handles file restoration from recycle bin

5. **Verify all fixes:**
   - Confirms folders were created
   - Confirms storage is accurate
   - Confirms triggers are active

### Step 4: Verify the Results

After the fix runs:

1. Go to the File Manager
2. Check the left sidebar - you should now see:
   - **All Files** section
   - **Recycle Bin** section
   - **Storage** section with usage bar
   - **My Storage** → My Files
   - **Cloud** → R2 Storage (if configured)
   - **Common Folders** (newly appeared!)
     - Project Pictures
     - Project Videos
     - Project Documents
     - Templates
   - **Special Folders** (newly appeared!)
     - HR Documents
     - Financial Records
     - Legal Documents

3. **Test file upload:**
   - Upload a file
   - Check that storage used increases

4. **Test file delete:**
   - Delete a file
   - Check that storage used decreases

5. **Check global storage (admin only):**
   - Global VPS Usage should show correct percentage
   - Should not be 0% anymore

## What Was Fixed

### Database Changes

1. **New Common Folders Created:**
   - 4 default common folders accessible to all users
   - Each with unique icon and color

2. **New Special Folders Created:**
   - 3 default restricted folders
   - Require explicit permission for access

3. **Database Triggers Created:**
   - `trg_fm_files_after_insert` - Updates storage when files are uploaded
   - `trg_fm_files_after_update` - Updates storage when files are deleted/restored

4. **Storage Recalculation:**
   - All user storage recalculated from actual files
   - Both `fm_user_quotas` and `fm_user_storage_tracking` tables updated
   - Global VPS storage statistics refreshed

### Behavior After Fix

✅ **Upload behavior:**
- File uploads automatically update storage tracking
- Both personal and global storage counters increase
- Trigger handles all storage calculations

✅ **Delete behavior:**
- File deletes automatically decrease storage tracking
- Storage used decreases immediately
- Trigger handles cleanup

✅ **Folder display:**
- Common folders appear in left sidebar for all users
- Special folders appear only for users with permission (admins see all)
- Folders are properly colored and have icons

✅ **Storage display:**
- Personal storage shows: X MB / 1 GB (Y%)
- Global VPS usage shows correct percentage
- Admin dashboard shows breakdown by user

## Cleanup

After verifying everything works, you can optionally delete these files:
- `run_fix_folders_storage.php` (the web-based fix tool)
- `fix_folders_and_storage.php` (CLI version, not used)
- `FIX_INSTRUCTIONS.md` (this file)

## Troubleshooting

### Common folders still not showing?

1. Clear browser cache and reload
2. Check that folders exist: Run this SQL query
   ```sql
   SELECT * FROM fm_common_folders WHERE is_active = 1;
   ```
3. Should return 4 rows

### Storage still not decreasing on delete?

1. Check triggers exist: Run this SQL query
   ```sql
   SHOW TRIGGERS WHERE `Table` = 'fm_files';
   ```
2. Should show 2 triggers
3. Try re-running the fix tool

### Global VPS usage still 0%?

1. Check that users have storage records:
   ```sql
   SELECT * FROM fm_user_storage_tracking WHERE used_bytes > 0;
   ```
2. Try re-running the fix tool

## Support

If issues persist after running the fix:
1. Check browser console for JavaScript errors
2. Check server error logs
3. Verify database tables exist and have data
4. Contact support with screenshots of the issue
