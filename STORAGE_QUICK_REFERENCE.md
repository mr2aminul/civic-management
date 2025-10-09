# Storage Tracking - Quick Reference

## Installation

```bash
# 1. Backup database
mysqldump -u user -p database > backup.sql

# 2. Run migration
php run_storage_migration.php

# Done! Triggers handle everything automatically
```

## Common Tasks

### Check User Quota
```php
$quota = fm_get_user_quota($userId);
echo fm_format_bytes($quota['used']) . " / " . fm_format_bytes($quota['quota']);
```

### Before File Upload
```php
if (!fm_check_quota($userId, $fileSize)) {
    die("Quota exceeded!");
}
```

### Set User Quota (Admin)
```php
fm_set_user_quota($userId, 5 * 1024 * 1024 * 1024); // 5 GB
```

### Global Stats (Admin)
```php
$stats = fm_get_global_storage_stats();
echo "VPS Usage: {$stats['vps_usage_percent']}%";
```

### Top Users (Admin)
```php
$users = fm_get_user_storage_list(10, 0);
foreach ($users['users'] as $user) {
    echo "User {$user['user_id']}: " . fm_format_bytes($user['used_bytes']) . "\n";
}
```

### Manual Recalculation (if needed)
```php
fm_recalculate_user_storage($userId);        // One user
fm_recalculate_all_storage();                // All users
```

## SQL Commands

### Check Triggers
```sql
SHOW TRIGGERS LIKE 'fm_files';
```

### Recalculate All Storage
```sql
CALL sp_recalculate_all_storage();
```

### View Top Users
```sql
SELECT user_id, used_bytes, quota_bytes, total_files
FROM fm_user_quotas
ORDER BY used_bytes DESC
LIMIT 10;
```

### Global Statistics
```sql
SELECT
  COUNT(*) as users,
  SUM(used_bytes) as total_used,
  SUM(total_files) as files
FROM fm_user_quotas;
```

## Troubleshooting

### Storage Not Updating?
```sql
CALL sp_recalculate_all_storage();
```

### Check Error Logs
```bash
tail -f /var/log/php-error.log
```

### Verify Tables Exist
```sql
SHOW TABLES LIKE 'fm_%';
```

## Important Files

- `migrations/000_file_manager_complete_clean.sql` - Database migration
- `assets/includes/file_manager_storage.php` - Storage functions
- `run_storage_migration.php` - Automated migration script
- `STORAGE_MIGRATION_GUIDE.md` - Full documentation
- `STORAGE_FIX_COMPLETE.md` - Complete overview

## Key Features

✅ Automatic updates via database triggers
✅ No manual tracking needed
✅ Bug-free and efficient
✅ Single source of truth (fm_user_quotas)
✅ Backward compatible
✅ Well-documented

## Need Help?

1. Read `STORAGE_MIGRATION_GUIDE.md` for detailed instructions
2. Read `STORAGE_FIX_COMPLETE.md` for complete overview
3. Check error logs for specific issues
4. Verify triggers are installed: `SHOW TRIGGERS`
