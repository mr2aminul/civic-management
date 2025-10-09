# File Manager Issues - Solution Summary

## Issues Identified

Based on your API response and screenshot, three critical issues were found:

### 1. Common and Special Folders Not Showing
**API Response:**
```json
{
    "common_folders": [],
    "special_folders": []
}
```

**Root Cause:** The database tables `fm_common_folders` and `fm_special_folders` exist but contain no data. No default folders were created during setup.

### 2. Storage Not Decreasing on File Delete
**Observed Behavior:**
- File upload increases storage used ✓
- File delete does NOT decrease storage used ✗

**Root Cause:** Missing database triggers to automatically update storage tracking when files are deleted.

### 3. Global VPS Usage Showing 0%
**API Response:**
```json
{
    "global_storage": {
        "total_users": 0,
        "total_files_count": 0,
        "total_used_bytes": 0,
        "vps_total_bytes": 64424509440,
        "global_usage_percentage": 0
    }
}
```

**Root Cause:** Storage tracking tables not properly populated, and calculation function returning default/empty values.

## Solution Provided

### Files Created

1. **`run_fix_folders_storage.php`** (MAIN FIX TOOL)
   - Web-based tool with admin authentication
   - Visual progress indicator
   - Comprehensive fix for all issues
   - **Usage:** Open in browser at `https://civicgroupbd.com/run_fix_folders_storage.php`

2. **`fix_folders_and_storage.php`** (CLI VERSION)
   - Command-line version of the fix
   - Same functionality as web version
   - Not used (PHP CLI not available in environment)

3. **`FIX_INSTRUCTIONS.md`**
   - Detailed step-by-step instructions
   - Troubleshooting guide
   - Expected results after fix

4. **`QUICK_FIX_GUIDE.txt`**
   - Quick reference card
   - Visual diagram of expected sidebar
   - Testing checklist

5. **`SOLUTION_SUMMARY.md`** (this file)
   - Complete technical analysis
   - Solution architecture
   - Implementation details

## What the Fix Does

### Step 1: Create Default Common Folders
Creates 4 folders accessible to all users:
- 🖼️ **Project Pictures** (`project_pictures`)
- 🎥 **Project Videos** (`project_videos`)
- 📄 **Project Documents** (`project_documents`)
- 📋 **Templates** (`templates`)

Each folder has:
- Unique key for identification
- Bootstrap icon
- Color coding
- Description
- Sort order

### Step 2: Create Default Special Folders
Creates 3 restricted folders (require permissions):
- 🔒 **HR Documents** (`hr_documents`)
- 💰 **Financial Records** (`financial_records`)
- ⚖️ **Legal Documents** (`legal_documents`)

Special folders:
- Only visible to users with explicit access
- Admins can see all special folders
- Each has permission level: view, edit, or admin

### Step 3: Recalculate User Storage
For each user with files:
- Scans all non-deleted files in `fm_files` table
- Calculates total size
- Counts files and folders
- Separates R2 uploaded vs local-only storage
- Updates both `fm_user_quotas` and `fm_user_storage_tracking` tables

### Step 4: Create Database Triggers

**Insert Trigger (`trg_fm_files_after_insert`):**
```sql
AFTER INSERT ON fm_files
→ Increases used_bytes by file size
→ Increments total_files counter
→ Updates r2_uploaded_bytes if file is in R2
→ Updates local_only_bytes if file is local only
→ Sets last_upload_at to current time
```

**Update Trigger (`trg_fm_files_after_update`):**
```sql
AFTER UPDATE ON fm_files
→ When is_deleted changes from 0 to 1 (delete):
  - Decreases used_bytes by file size
  - Decrements total_files counter
  - Updates R2/local counters

→ When is_deleted changes from 1 to 0 (restore):
  - Increases used_bytes by file size
  - Increments total_files counter
  - Updates R2/local counters
```

### Step 5: Verify Fixes
Confirms:
- Folders created and active
- Storage recalculated for all users
- Triggers installed and working
- Global VPS usage calculated correctly

## Expected Results

### Before Fix
```
Left Sidebar:
├─ All Files
├─ Recycle Bin
├─ Storage (97.3 MB / 1.0 GB)
├─ My Storage → My Files
└─ Cloud → R2 Storage

API Response:
{
  "common_folders": [],           ← EMPTY
  "special_folders": [],          ← EMPTY
  "global_storage": {
    "total_users": 0,             ← WRONG
    "total_used_bytes": 0,        ← WRONG
    "global_usage_percentage": 0  ← WRONG
  }
}
```

### After Fix
```
Left Sidebar:
├─ All Files
├─ Recycle Bin
├─ Storage (97.3 MB / 1.0 GB) ████░░░░░░ 9.3%
├─ My Storage
│  └─ My Files
├─ Cloud
│  └─ R2 Storage
├─ Common Folders                ← NEW!
│  ├─ Project Pictures
│  ├─ Project Videos
│  ├─ Project Documents
│  └─ Templates
├─ Special Folders               ← NEW!
│  ├─ HR Documents
│  ├─ Financial Records
│  └─ Legal Documents
└─ Global VPS Usage (admin)
   60 GB total ██████░░░░ 15%    ← CORRECT!

API Response:
{
  "common_folders": [             ← POPULATED
    {"id": 1, "folder_name": "Project Pictures", ...},
    {"id": 2, "folder_name": "Project Videos", ...},
    ...
  ],
  "special_folders": [            ← POPULATED
    {"id": 1, "folder_name": "HR Documents", ...},
    ...
  ],
  "storage_usage": {
    "used_bytes": 89460398,
    "quota_bytes": 1073741824,
    "total_files": 123,           ← CORRECT
    "total_folders": 15           ← CORRECT
  },
  "global_storage": {             ← ALL CORRECT
    "total_users": 5,
    "total_files_count": 456,
    "total_used_bytes": 450000000,
    "vps_total_bytes": 64424509440,
    "global_usage_percentage": 15.2
  }
}
```

## Technical Details

### Database Tables Affected

1. **`fm_common_folders`**
   - Stores common folder definitions
   - Fields: id, folder_name, folder_key, folder_path, icon, color, etc.

2. **`fm_special_folders`**
   - Stores special folder definitions
   - Same structure as common folders plus permission requirements

3. **`fm_user_quotas`**
   - User storage quota tracking
   - Fields: user_id, quota_bytes, used_bytes, total_files, etc.

4. **`fm_user_storage_tracking`**
   - Enhanced storage tracking
   - Additional fields for R2 vs local breakdown

5. **`fm_files`**
   - Core file metadata table
   - Triggers monitor INSERT and UPDATE operations

### Functions Used

- `fm_query()` - Database query wrapper
- `fm_insert()` - Insert with parameter binding
- `fm_recalculate_user_storage()` - Complete storage recalculation
- `fm_get_global_storage_stats()` - Global VPS usage calculation
- `fm_format_bytes()` - Human-readable file sizes

### Security Considerations

✅ **Admin-only access** - Fix tool checks `Wo_IsAdmin()`
✅ **SQL injection protection** - Parameterized queries throughout
✅ **Trigger safety** - Uses `GREATEST(0, value)` to prevent negative values
✅ **Permission checks** - Special folders respect existing access control

## How to Use

### Simple 4-Step Process

1. **Open the fix tool**
   ```
   https://civicgroupbd.com/run_fix_folders_storage.php
   ```

2. **Review current status**
   - See what's missing
   - Check current storage values

3. **Click "Run Fix Now"**
   - Watch progress in real-time
   - All fixes applied automatically

4. **Verify results**
   - Refresh file manager
   - Check folders appear in sidebar
   - Test upload/delete to verify storage tracking

### Testing Checklist

- [ ] Common folders visible in left sidebar (4 folders)
- [ ] Special folders visible in left sidebar (3 folders for admin)
- [ ] Upload a file → Storage increases
- [ ] Delete a file → Storage decreases
- [ ] Global VPS usage shows correct percentage (admin only)
- [ ] Storage tracking updates in real-time

## Cleanup

After verifying everything works, you can delete:
- `run_fix_folders_storage.php`
- `fix_folders_and_storage.php`
- `FIX_INSTRUCTIONS.md`
- `QUICK_FIX_GUIDE.txt`
- `SOLUTION_SUMMARY.md`

These are one-time fix files and are no longer needed after successful execution.

## Support

If issues persist:
1. Check browser console for JavaScript errors
2. Verify database triggers: `SHOW TRIGGERS WHERE Table = 'fm_files';`
3. Check folder data: `SELECT * FROM fm_common_folders WHERE is_active = 1;`
4. Re-run the fix tool (safe to run multiple times)

---

**Created:** 2025-10-09
**Version:** 1.0
**Status:** Ready to deploy
