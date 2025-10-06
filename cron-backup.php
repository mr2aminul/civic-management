<?php
/**
 * Cron Job: Automated Database Backup
 * Run every 6 hours: 0 */6 * * *
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__ . '/');
}

require_once ROOT_DIR . 'assets/includes/file_manager_helper.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting automated backup...\n";

$result = fm_create_full_backup(0);

if ($result['success']) {
    echo "[" . date('Y-m-d H:i:s') . "] Backup created successfully: " . $result['filename'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Size: " . round($result['size'] / 1024 / 1024, 2) . " MB\n";
    echo "[" . date('Y-m-d H:i:s') . "] Queued for R2 upload\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Backup FAILED: " . $result['message'] . "\n";
    error_log("Backup failed: " . json_encode($result));
}

echo "[" . date('Y-m-d H:i:s') . "] Cleaning up old backups...\n";
$cleanup = fm_cleanup_old_backups();
echo "[" . date('Y-m-d H:i:s') . "] Deleted " . $cleanup['deleted'] . " old backup(s)\n";

echo "[" . date('Y-m-d H:i:s') . "] Backup job completed\n";
exit(0);
