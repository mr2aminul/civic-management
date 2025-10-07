<?php
/**
 * Cron Job: Process R2 Upload Queue
 * Run every 15 minutes: 
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__ . '/');
}

require_once ROOT_DIR . 'assets/init.php';

echo "[" . date('Y-m-d H:i:s') . "] Processing R2 upload queue...\n";

$processed = fm_process_upload_queue(50);

$done = 0;
$errors = 0;

foreach ($processed as $item) {
    if ($item['status'] === 'done') {
        $done++;
    } else {
        $errors++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Processed: $done successful, $errors failed\n";
echo "[" . date('Y-m-d H:i:s') . "] Upload queue processing completed\n";

exit(0);
