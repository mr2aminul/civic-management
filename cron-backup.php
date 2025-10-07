<?php
/**
 * Cron Job: Automated Database Backup
 * Run every 6 hours
 * This script creates database backups, uploads them directly to R2, and maintains metadata cache
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__ . '/');
}

require_once ROOT_DIR . 'assets/init.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting automated backup...\n";

$result = fm_create_db_dump('db_backup');

if ($result['success']) {
    echo "[" . date('Y-m-d H:i:s') . "] Backup created successfully: " . $result['filename'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Size: " . round($result['size'] / 1024 / 1024, 2) . " MB\n";

    $remoteKey = 'backups/' . $result['filename'];

    echo "[" . date('Y-m-d H:i:s') . "] Uploading to R2...\n";
    $uploadResult = fm_upload_to_r2($result['path'], $remoteKey);

    if ($uploadResult['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] Uploaded to R2 successfully\n";

        $metadataFile = fm_get_backup_dir() . '/r2_backups_metadata.json';
        $backups = [];

        if (file_exists($metadataFile)) {
            $content = file_get_contents($metadataFile);
            $data = json_decode($content, true);
            if ($data && isset($data['backups'])) {
                $backups = $data['backups'];
            }
        }

        $backups[] = [
            'key' => $remoteKey,
            'name' => $result['filename'],
            'size' => $result['size'],
            'modified' => date('Y-m-d H:i:s')
        ];

        usort($backups, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });

        file_put_contents($metadataFile, json_encode([
            'updated_at' => date('Y-m-d H:i:s'),
            'backups' => $backups
        ], JSON_PRETTY_PRINT));

        echo "[" . date('Y-m-d H:i:s') . "] Metadata cache updated\n";

        $metadataRemoteKey = 'backups/r2_backups_metadata.json';
        $metadataUpload = fm_upload_to_r2($metadataFile, $metadataRemoteKey);
        if ($metadataUpload['success']) {
            echo "[" . date('Y-m-d H:i:s') . "] Metadata uploaded to R2\n";
        }

    } else {
        echo "[" . date('Y-m-d H:i:s') . "] R2 upload failed: " . ($uploadResult['message'] ?? 'Unknown error') . "\n";
        echo "[" . date('Y-m-d H:i:s') . "] Queueing for retry...\n";
        fm_enqueue_r2_upload($result['path'], $remoteKey);
    }
} else {
    echo "[" . date('Y-m-d H:i:s') . "] Backup FAILED: " . $result['message'] . "\n";
    error_log("Backup failed: " . json_encode($result));
}

echo "[" . date('Y-m-d H:i:s') . "] Enforcing retention policies...\n";
$cleanup = fm_enforce_retention();
echo "[" . date('Y-m-d H:i:s') . "] Deleted " . $cleanup['deleted_backups'] . " old backup(s)\n";

echo "[" . date('Y-m-d H:i:s') . "] Backup job completed\n";
exit(0);
