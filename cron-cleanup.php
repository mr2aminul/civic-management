<?php
/**
 * Cron Job: Cleanup Tasks
 * Run daily at 2 AM: 0 2 * * *
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__ . '/');
}

require_once ROOT_DIR . 'assets/includes/file_manager_helper.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting cleanup tasks...\n";

echo "[" . date('Y-m-d H:i:s') . "] Cleaning recycle bin (auto-delete expired items)...\n";
$recycleCleanup = fm_empty_recycle_bin_auto();
echo "[" . date('Y-m-d H:i:s') . "] Permanently deleted " . $recycleCleanup['deleted'] . " file(s) from recycle bin\n";

echo "[" . date('Y-m-d H:i:s') . "] Cleaning old backups (beyond retention period)...\n";
$backupCleanup = fm_cleanup_old_backups();
echo "[" . date('Y-m-d H:i:s') . "] Deleted " . $backupCleanup['deleted'] . " old backup(s)\n";

echo "[" . date('Y-m-d H:i:s') . "] Cleanup tasks completed\n";

exit(0);
