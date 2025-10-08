# Storage Management & Folder Structure Guide

## Overview

The File Manager now implements a comprehensive folder management system with structured storage tracking. This system provides:

1. **User Storage**: Personal storage folders for each user (`/Storage/{user_id}/`)
2. **Common Folders**: Shared folders accessible to all users
3. **Special Folders**: Restricted folders with explicit access control
4. **Storage Tracking**: Detailed usage metrics per user and globally

## Folder Structure

### 1. User Folders (`/Storage/{user_id}/`)

Each user has a dedicated storage area with organized subfolders:

```
/Storage/{user_id}/
├── Documents/
├── Images/
├── Videos/
├── Downloads/
└── Archives/
```

**Features:**
- Automatically created on first upload (if enabled in settings)
- Only the user can access their own storage
- Admins can view/manage all user folders
- Customizable default subfolders via system settings

**Access Control:**
- Regular users: Read/write access to their own folder only
- Admins/Moderators: Full access to all user folders

### 2. Common Folders

Folders accessible to all users, created and managed by admins.

**Default Common Folders:**
- Project Pictures
- Project Videos
- Project Documents

**Features:**
- All users can view and upload files
- Can be configured as read-only (admin-only uploads)
- Support for file size limits and extension restrictions
- Track total files and storage usage per folder

**Admin Controls:**
- Create/edit/delete common folders
- Set permissions (read-only mode)
- Configure allowed file types and size limits

### 3. Special Folders

Restricted folders requiring explicit user access permissions.

**Default Special Folders:**
- HR Documents
- Finance
- Legal

**Features:**
- Require explicit access grants
- Support for permission levels (view, edit, admin)
- Auto-assign to specific user roles (optional)
- Track total files, storage, and user access count

**Permission Levels:**
- **View**: Can browse and download files only
- **Edit**: Can upload, edit, and manage files
- **Admin**: Full control including access management

## Storage Tracking

### Per-User Metrics

For each user, the system tracks:

- Total files count
- Total folders count
- Used storage (bytes)
- Storage quota (bytes)
- R2 uploaded storage (files stored in cloud)
- Local-only storage
- Usage percentage
- Last upload timestamp
- Last calculation timestamp

**Example Response:**
```json
{
  "user_id": 123,
  "used_bytes": 524288000,
  "quota_bytes": 1073741824,
  "used_formatted": "500 MB",
  "quota_formatted": "1 GB",
  "usage_percentage": 46.88,
  "total_files": 45,
  "total_folders": 8,
  "r2_uploaded_bytes": 314572800,
  "local_only_bytes": 209715200,
  "last_upload_at": "2025-10-08 10:30:00"
}
```

### Global Storage (Admin Only)

Admins can view VPS-wide storage metrics:

- Total active users
- Total files across all users
- Total storage used (bytes)
- VPS total capacity (default: 60 GB)
- Global usage percentage
- Total R2 storage
- Total local-only storage
- Available storage remaining

**Example Response:**
```json
{
  "total_users": 25,
  "total_files_count": 1234,
  "total_used_bytes": 32212254720,
  "vps_total_bytes": 64424509440,
  "used_formatted": "30 GB",
  "quota_formatted": "60 GB",
  "global_usage_percentage": 50.0,
  "available_bytes": 32212254720,
  "available_formatted": "30 GB"
}
```

### Per-User Breakdown (Admin Only)

Admins can view top users by storage consumption:

```json
[
  {
    "user_id": 5,
    "username": "john_doe",
    "email": "john@example.com",
    "used_bytes": 5368709120,
    "quota_bytes": 10737418240,
    "used_formatted": "5 GB",
    "quota_formatted": "10 GB",
    "usage_percentage": 50.0,
    "total_files": 250,
    "total_folders": 15,
    "last_upload_at": "2025-10-08 09:15:00"
  }
]
```

## API Endpoints

### Storage Management

#### Get User Storage Usage
```
GET /xhr/file_manager.php?f=file_manager&s=get_user_storage[&user_id={id}]
```
Returns detailed storage usage for the specified user (defaults to current user).

#### Get Global Storage Summary
```
GET /xhr/file_manager.php?f=file_manager&s=get_global_storage
```
Returns VPS-wide storage metrics and top users (admin only).

#### Create User Storage Structure
```
POST /xhr/file_manager.php?f=file_manager&s=create_user_storage
```
Creates default folder structure for the current user.

#### Update Storage Tracking
```
POST /xhr/file_manager.php?f=file_manager&s=update_storage_tracking[&user_id={id}]
```
Recalculates storage usage for the specified user.

### Folder Management

#### Get Folder Contents
```
GET /xhr/file_manager.php?f=file_manager&s=get_folder_contents
  &folder_type={user|common|special}
  [&folder_id={id}]
  [&path={relative_path}]
```
Returns files and folders within the specified folder.

#### Get Common Folders with Stats
```
GET /xhr/file_manager.php?f=file_manager&s=get_common_folders_stats
```
Returns all common folders with file counts and storage usage.

#### Get Special Folders with Stats
```
GET /xhr/file_manager.php?f=file_manager&s=get_special_folders_stats
```
Returns special folders accessible to current user (or all if admin).

### Access Control (Admin Only)

#### Grant Special Folder Access
```
POST /xhr/file_manager.php?f=file_manager&s=grant_folder_access
  &folder_id={id}
  &user_id={id}
  &permission_level={view|edit|admin}
```
Grants a user access to a special folder.

#### Revoke Special Folder Access
```
POST /xhr/file_manager.php?f=file_manager&s=revoke_folder_access
  &folder_id={id}
  &user_id={id}
```
Removes a user's access to a special folder.

## R2 Storage Integration

The system maintains identical folder structures on Cloudflare R2:

### Local Structure
```
/home/civicbd/civicgroup/storage/
├── Storage/
│   ├── 1/
│   │   ├── Documents/
│   │   ├── Images/
│   │   └── ...
│   └── 2/
├── Common/
│   ├── Project Pictures/
│   ├── Project Videos/
│   └── Project Documents/
└── Special/
    ├── HR Documents/
    ├── Finance/
    └── Legal/
```

### R2 Structure
```
files/
├── Storage/
│   ├── 1/
│   │   ├── Documents/
│   │   └── ...
│   └── 2/
├── Common/
│   └── ...
└── Special/
    └── ...
```

## Automatic Storage Tracking

The system automatically tracks storage usage using database triggers:

### On File Upload
- Increments user's file count
- Adds file size to user's total storage
- Updates R2/local storage breakdown
- Updates last upload timestamp

### On File Delete (Soft Delete)
- Decrements user's file count
- Subtracts file size from user's total storage
- Adjusts R2/local storage breakdown

### On R2 Upload Status Change
- Moves storage allocation from local to R2
- Maintains accurate breakdown

## Usage Examples

### For Regular Users

**View Personal Storage:**
```javascript
$.get(API + '&s=get_user_storage', (res) => {
  console.log(`Using ${res.storage.used_formatted} of ${res.storage.quota_formatted}`);
  console.log(`${res.storage.usage_percentage}% used`);
});
```

**Access My Files:**
Click "My Files" in the sidebar to browse `/Storage/{your_user_id}/`

**Upload to Common Folder:**
1. Click on a common folder (e.g., "Project Pictures")
2. Use the upload button to add files

### For Administrators

**View Global Storage:**
```javascript
$.get(API + '&s=get_global_storage', (res) => {
  console.log(`Global Usage: ${res.global_storage.used_formatted} / ${res.global_storage.quota_formatted}`);
  console.log(`${res.global_storage.global_usage_percentage}% full`);

  // View top users
  res.user_breakdown.forEach(user => {
    console.log(`${user.username}: ${user.used_formatted}`);
  });
});
```

**Grant Special Folder Access:**
```javascript
$.post(API + '&s=grant_folder_access', {
  folder_id: 1,  // HR Documents
  user_id: 123,
  permission_level: 'view'
}, (res) => {
  console.log('Access granted');
});
```

**Revoke Special Folder Access:**
```javascript
$.post(API + '&s=revoke_folder_access', {
  folder_id: 1,
  user_id: 123
}, (res) => {
  console.log('Access revoked');
});
```

## Configuration

### System Settings

Settings can be configured in the `fm_system_settings` table:

| Setting Key | Default Value | Description |
|------------|---------------|-------------|
| `vps_total_storage_bytes` | 64424509440 (60 GB) | Total VPS storage capacity |
| `storage_alert_threshold_percent` | 85 | Alert when storage reaches this % |
| `auto_create_user_folders` | 1 | Auto-create user storage on first upload |
| `default_user_subfolders` | `["Documents", "Images", "Videos", "Downloads", "Archives"]` | Default subfolders for user storage |

### Per-User Quota

Default quota is set via environment variable:
```env
DEFAULT_USER_QUOTA_GB=1
```

Admins can adjust individual user quotas by updating the `fm_user_storage_tracking` table.

## Database Schema

### Key Tables

- `fm_user_storage_tracking`: Per-user storage metrics and quotas
- `fm_folder_structure`: Hierarchical folder organization
- `fm_common_folders`: Common folders configuration
- `fm_special_folders`: Special folders configuration
- `fm_folder_access`: Access control for special folders
- `fm_files`: File metadata (enhanced with storage type fields)

### Views

- `v_user_storage_summary`: Per-user storage summary
- `v_global_storage_summary`: VPS-wide storage metrics
- `v_common_folders_summary`: Common folders with stats
- `v_special_folders_summary`: Special folders with stats

### Stored Procedures

- `sp_create_user_storage_structure(user_id)`: Create user folder structure
- `sp_update_user_storage_stats(user_id)`: Recalculate user storage
- `sp_update_folder_stats(folder_id, folder_type)`: Update folder statistics

## Best Practices

### For Users

1. **Organize Your Files**: Use the provided subfolders (Documents, Images, etc.) to keep files organized
2. **Monitor Usage**: Keep an eye on your storage quota to avoid hitting limits
3. **Use R2 Storage**: Large files are automatically queued for R2 upload to free local space

### For Administrators

1. **Regular Monitoring**: Check global storage usage regularly to prevent running out of space
2. **Quota Management**: Adjust user quotas based on actual needs and available capacity
3. **Access Control**: Carefully manage special folder access to maintain security
4. **Storage Cleanup**: Implement retention policies and clean up old backups regularly
5. **R2 Sync**: Ensure R2 upload queue is processing smoothly to offload storage

## Troubleshooting

### Storage Not Updating

Run manual recalculation:
```sql
CALL sp_update_user_storage_stats({user_id});
```

### User Folders Not Created

Manually create structure:
```javascript
$.post(API + '&s=create_user_storage', (res) => {
  console.log('Storage created:', res.storage_path);
});
```

### Access Denied to Special Folder

Check access grants:
```sql
SELECT * FROM fm_folder_access
WHERE folder_id = {folder_id} AND user_id = {user_id};
```

## Migration

To apply the enhanced storage management system:

```bash
# Run the migration
mysql -u {user} -p {database} < migrations/005_enhanced_folder_storage_management.sql
```

This will:
- Create new tables for enhanced tracking
- Add database views for easy querying
- Set up triggers for automatic tracking
- Migrate existing data to new structure
- Create default folder structure
