<?php
/**
 * Cron Job: Sync R2 Upload Status
 * Run daily or weekly to sync R2 status for files that may have been uploaded but not marked
 * Usage: php cron-sync-r2-status.php
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__ . '/');
}

require_once ROOT_DIR . 'assets/init.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting R2 status sync...\n";

$limit = 100;
$totalUpdated = 0;

do {
    $updated = fm_sync_all_r2_status($limit);
    $totalUpdated += $updated;
    echo "[" . date('Y-m-d H:i:s') . "] Synced batch: {$updated} files\n";

    if ($updated > 0) {
        sleep(2);
    }
} while ($updated >= $limit);

echo "[" . date('Y-m-d H:i:s') . "] R2 status sync completed. Total updated: {$totalUpdated}\n";

exit(0);
