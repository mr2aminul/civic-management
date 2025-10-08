# Advanced File Manager - Implementation Guide

## Overview
This document outlines the complete implementation of the advanced file manager features including document preview/editing, thumbnails, versioning, folder access control, and recycle bin management.

---

## 🎯 Implemented Features

### 1. **Document Preview & Editing (Collabora Online)**
- ✅ Real-time document editing for Word, Excel, PowerPoint, TXT files
- ✅ Version control integration
- ✅ Collaborative editing support (requires Collabora Online server)

### 2. **File Thumbnails & R2 Storage Indicators**
- ✅ Automatic thumbnail generation for images (JPG, PNG, GIF, WEBP)
- ✅ Thumbnail sizes: small (100x100), medium (300x300), large (600x600)
- ✅ Visual indicator badge for files stored on Cloudflare R2
- ✅ Thumbnail caching system

### 3. **File Versioning**
- ✅ Automatic version creation on file updates
- ✅ Non-deletable version history
- ✅ 30-day auto-cleanup of old versions
- ✅ Version comments and metadata

### 4. **Recycle Bin System**
- ✅ Files move to recycle bin instead of permanent deletion
- ✅ 30-day retention period with auto-delete
- ✅ User-specific restore permissions
- ✅ Admin-only permanent deletion rights

### 5. **Storage Management**
- ✅ Per-user storage quotas
- ✅ Total VPS storage tracking (60 GB shared limit)
- ✅ Admin dashboard shows total system usage
- ✅ Regular users see only their own usage

### 6. **Enhanced Context Menu**
- ✅ New file creation options:
  - 📝 New Word File (.docx)
  - 📄 New TXT File (.txt)
  - 📊 New Excel File (.xlsx)
- ✅ Upload Files (single/multiple)
- ✅ Upload Folder (with webkitdirectory support)
- ✅ Fixed Rename and Move operations
- ✅ Context-aware menu items

### 7. **Folder Structure & Access Control**
- ✅ **Common Folders**: Accessible to all users
  - Project Pictures
  - Project Videos
  - Project Documents
- ✅ **Special Folders**: Restricted access (admin-managed)
  - HR Documents
  - Finance
  - Legal
- ✅ **User Folders**: Personal storage (`/Storage/{UserID}/`)
- ✅ Admin can view/manage all folders

### 8. **File Access Control**
- ✅ User-based file isolation using `$wo['user']['user_id']`
- ✅ Admin/Moderator checks: `Wo_IsAdmin()` and `Wo_IsModerator()`
- ✅ Folder-level permissions system
- ✅ Share token system for file sharing

---

## 📊 Database Schema

### New Tables Created

#### `fm_file_versions`
```sql
- id (PK)
- file_id (FK to fm_files)
- user_id
- version_number
- filename, path, size, checksum
- r2_key, r2_uploaded
- created_at, comment
- is_deletable (always 0 - versions are protected)
```

#### `fm_thumbnails`
```sql
- id (PK)
- file_id (FK to fm_files)
- thumbnail_path
- thumbnail_size (small/medium/large)
- width, height
- r2_key, r2_uploaded
- created_at
```

#### `fm_common_folders`
```sql
- id (PK)
- folder_name, folder_key, folder_path
- folder_icon, folder_color
- description
- is_active, sort_order
- created_by, created_at, updated_at
```

#### `fm_special_folders`
```sql
- id (PK)
- folder_name, folder_key, folder_path
- folder_icon, folder_color
- description
- requires_permission
- is_active, sort_order
- created_by, created_at, updated_at
```

#### `fm_folder_access`
```sql
- id (PK)
- folder_id, folder_type (special/common)
- user_id
- permission_level (view/edit/admin)
- granted_by, granted_at
```

#### `fm_file_shares`
```sql
- id (PK)
- file_id, shared_by, shared_with
- share_type (private/link/public)
- permission (view/edit/download)
- share_token
- expires_at, password_hash
- max_downloads, download_count
- is_active, created_at, last_accessed_at
```

#### `fm_system_settings`
```sql
- id (PK)
- setting_key, setting_value
- setting_type, description
- updated_at
```

### Enhanced Existing Tables

**`fm_files`** - Added columns:
- `folder_type` (user/common/special)
- `thumbnail_generated` (boolean)
- `version_count`, `current_version`
- `special_folder_id`, `common_folder_id`

**`fm_recycle_bin`** - Added column:
- `can_restore` (boolean, default 1)

---

## 🔧 Installation Steps

### Step 1: Run Database Migration
```bash
# The migration file is located at:
# /migrations/004_advanced_file_manager_features.sql

# Run via PHP:
php run_migration.php

# Or via MySQL CLI:
mysql -u username -p database_name < migrations/004_advanced_file_manager_features.sql
```

### Step 2: Configure Environment Variables
Add to your `.env` file:
```env
# Storage Configuration
LOCAL_STORAGE_DIR=/home/civicbd/civicgroup/storage
DB_BACKUP_LOCAL_DIR=/home/civicbd/civicgroup/backups

# User Quotas
DEFAULT_USER_QUOTA_GB=1

# File Retention
RECYCLE_RETENTION_DAYS=30
BACKUP_RETENTION_DAYS=30

# Cloudflare R2 (optional)
R2_ACCESS_KEY_ID=your_key_here
R2_SECRET_ACCESS_KEY=your_secret_here
R2_BUCKET=civic-management
R2_ENDPOINT=https://your-account.r2.cloudflarestorage.com
R2_ENDPOINT_DOMAIN=https://your-cdn-domain.com

# Collabora Online (optional)
COLLABORA_ENABLED=0
COLLABORA_URL=https://collabora.example.com
```

### Step 3: Set Directory Permissions
```bash
# Create storage directories
mkdir -p /home/civicbd/civicgroup/storage
mkdir -p /home/civicbd/civicgroup/backups
mkdir -p /home/civicbd/civicgroup/storage/.thumbnails

# Set permissions
chmod 755 /home/civicbd/civicgroup/storage
chmod 755 /home/civicbd/civicgroup/backups
chown -R www-data:www-data /home/civicbd/civicgroup/storage
```

### Step 4: Configure PHP GD Library
```bash
# Ensure GD is installed for thumbnail generation
php -m | grep gd

# If not installed:
sudo apt-get install php-gd
sudo systemctl restart apache2  # or php-fpm
```

### Step 5: (Optional) Install Collabora Online
```bash
# Docker installation (recommended)
docker pull collabora/code
docker run -t -d -p 9980:9980 \
  -e "domain=your-domain\\.com" \
  -e "username=admin" \
  -e "password=your-password" \
  --name collabora collabora/code

# Update system settings via admin panel:
# collabora_enabled = 1
# collabora_url = https://your-domain.com:9980
```

---

## 🚀 API Endpoints

### File Operations
- `create_new_file` - Create Word/TXT/Excel files
- `rename` - Rename files/folders
- `move` - Move files between folders
- `delete_local` - Move to recycle bin
- `permanent_delete` - Admin-only permanent deletion

### Thumbnails
- `generate_thumbnail` - Generate thumbnail for image
- `get_thumbnail` - Retrieve existing thumbnail

### Versioning
- `create_version` - Create new file version
- `list_file_versions` - Get version history

### Folders
- `list_common_folders` - Get all common folders
- `list_special_folders` - Get accessible special folders
- `manage_special_folder` - Admin: create/manage special folders

### Recycle Bin
- `list_recycle_bin` - Get deleted files
- `restore_from_recycle` - Restore deleted file
- `clean_recycle_bin` - Admin: cleanup expired items

### Storage
- `get_user_quota` - Get user storage info
- `get_system_storage` - Admin: get total VPS usage
- `sync_user_quota` - Recalculate storage from disk

### Collabora Online
- `collabora_info` - Get document editing URL and settings

---

## 🎨 User Interface Updates

### Context Menu
Right-click on empty space:
- New Word File
- New TXT File
- New Excel File
- New Folder
- Upload Files
- Upload Folder

Right-click on file:
- Preview
- Download
- Rename ✅ (Fixed)
- Move ✅ (Fixed)
- Upload to R2
- Delete

### Sidebar Structure
```
📁 All Files
🕒 Recent
🖼️ Images
📄 Documents
🗑️ Recycle Bin

STORAGE
📊 Storage Used: X GB / Y GB

CLOUD
☁️ R2 Storage: Connected

COMMON FOLDERS (auto-loaded)
🖼️ Project Pictures
🎥 Project Videos
📄 Project Documents

SPECIAL FOLDERS (admin-managed, permission-based)
💼 HR Documents
💰 Finance
🛡️ Legal
```

---

## 🔐 Security & Permissions

### User Roles
1. **Regular Users**:
   - Access own files only
   - View common folders
   - View granted special folders
   - Delete to recycle bin only
   - Restore own files within 30 days

2. **Moderators** (`Wo_IsModerator()`):
   - Same as regular users
   - Can view all special folders
   - Cannot permanently delete

3. **Admins** (`Wo_IsAdmin()`):
   - Full system access
   - View/manage all files and folders
   - Create/manage special folders
   - Grant folder permissions
   - Permanently delete files
   - Clean recycle bin globally
   - View total system storage

### Access Control Flow
```php
// Example: Check file access
$userId = $wo['user']['user_id'];
$isAdmin = Wo_IsAdmin();
$isModerator = Wo_IsModerator();

// User can access file if:
// 1. They own it
// 2. It's in a common folder
// 3. They have permission to the special folder
// 4. They are admin/moderator
```

---

## 📝 Usage Examples

### Create New Text File
```javascript
// Frontend call
$.post(API + '&s=create_new_file', {
    path: 'Documents/mynotes.txt',
    type: 'text',
    content: 'Hello World'
}, function(res) {
    console.log('File created:', res);
});
```

### Generate Thumbnail
```javascript
$.post(API + '&s=generate_thumbnail', {
    file_id: 123,
    size: 'medium'
}, function(res) {
    console.log('Thumbnail:', res.thumbnail);
});
```

### Grant Special Folder Access (Admin)
```javascript
$.post(API + '&s=manage_special_folder', {
    sub_action: 'grant_access',
    folder_id: 1,
    user_id: 456,
    permission_level: 'view'
}, function(res) {
    console.log('Access granted');
});
```

---

## 🐛 Troubleshooting

### Thumbnails Not Generating
1. Check PHP GD extension: `php -m | grep gd`
2. Verify storage directory permissions
3. Check PHP memory limit (increase if needed)
4. Enable debug logging in .env: `FM_DEBUG=1`

### Collabora Not Working
1. Verify Collabora server is running
2. Check firewall allows port 9980
3. Update system settings table
4. Test Collabora URL directly in browser

### Storage Quota Issues
1. Run quota sync: `?f=file_manager&s=sync_user_quota`
2. Check VPS disk space: `df -h`
3. Verify database quota records

### File Access Denied
1. Check user permissions table
2. Verify `$wo['user']['user_id']` is set
3. Check folder access grants
4. Review error logs

---

## 🔄 Maintenance Tasks

### Daily
- Auto-delete expired recycle bin items (cron job)
- Process R2 upload queue

### Weekly
- Clean up old file versions (>30 days)
- Verify storage quota accuracy

### Monthly
- Review system storage usage
- Audit special folder permissions
- Clean up orphaned thumbnails

---

## 📞 Support & Configuration

### Admin Panel Access
Navigate to: `/manage/file_manager?tab=settings`

Available settings:
- R2 credentials configuration
- Collabora Online URL
- Storage quotas
- Retention policies
- Auto-backup settings

---

## ✅ Testing Checklist

- [ ] User can create new Word/TXT/Excel files
- [ ] Thumbnails generate for images
- [ ] R2 badge shows for cloud-stored files
- [ ] Rename works correctly
- [ ] Move works correctly
- [ ] Delete moves to recycle bin
- [ ] Users can restore own files
- [ ] Admin can permanently delete
- [ ] Common folders visible to all
- [ ] Special folders require permission
- [ ] Storage quotas enforced
- [ ] Version history tracked
- [ ] Collabora integration (if enabled)

---

## 🎉 Conclusion

The advanced file manager system is now fully implemented with all requested features. The system provides:

1. ✅ Document preview & editing (Collabora)
2. ✅ Thumbnails & R2 indicators
3. ✅ File versioning with auto-cleanup
4. ✅ Complete recycle bin system
5. ✅ Storage quota management
6. ✅ Enhanced context menus
7. ✅ Folder access control
8. ✅ User/Common/Special folder structure

All features respect user permissions and maintain data security through proper access control checks.
