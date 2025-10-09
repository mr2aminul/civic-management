<?php
/**
 * Storage Sync Utility
 * This script recalculates storage usage for all users
 * Run this to fix any storage tracking inconsistencies
 */

require_once __DIR__ . '/assets/init.php';
require_once __DIR__ . '/assets/includes/file_manager_storage.php';

echo "=== File Manager Storage Sync Utility ===\n\n";

// Get all users with files
$users = fm_query("
    SELECT DISTINCT user_id
    FROM fm_files
    WHERE is_deleted = 0
    ORDER BY user_id
");

if (empty($users)) {
    echo "No users found with files.\n";
    exit(0);
}

$totalUsers = count($users);
$successCount = 0;
$failedCount = 0;

echo "Found {$totalUsers} users with files.\n";
echo "Starting storage recalculation...\n\n";

foreach ($users as $user) {
    $userId = (int)$user['user_id'];

    echo "Processing user {$userId}... ";

    if (fm_recalculate_user_storage($userId)) {
        $quota = fm_get_user_quota($userId);
        $successCount++;
        echo "✓ Success - Used: " . fm_format_bytes($quota['used']) . " / " . fm_format_bytes($quota['quota']) . "\n";
    } else {
        $failedCount++;
        echo "✗ Failed\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total users processed: {$totalUsers}\n";
echo "Successfully updated: {$successCount}\n";
echo "Failed: {$failedCount}\n";

// Show global statistics
echo "\n=== Global Storage Statistics ===\n";
$globalStats = fm_get_global_storage_stats();
echo "Total users: {$globalStats['total_users']}\n";
echo "Total files: {$globalStats['total_files']}\n";
echo "Total folders: {$globalStats['total_folders']}\n";
echo "Total used: " . fm_format_bytes($globalStats['total_used_bytes']) . "\n";
echo "VPS total: " . fm_format_bytes($globalStats['vps_total_bytes']) . "\n";
echo "VPS available: " . fm_format_bytes($globalStats['vps_available_bytes']) . "\n";
echo "VPS usage: {$globalStats['vps_usage_percent']}%\n";
echo "R2 uploaded: " . fm_format_bytes($globalStats['r2_uploaded_bytes']) . "\n";
echo "Local only: " . fm_format_bytes($globalStats['local_only_bytes']) . "\n";

echo "\nDone!\n";
