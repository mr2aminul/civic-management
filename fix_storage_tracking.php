<?php
// Fix storage tracking issues
// Run this script once to fix the duplicate entry problem and recalculate storage

require_once __DIR__ . '/assets/init.php';
require_once __DIR__ . '/assets/includes/file_manager_helper.php';

// Check if admin
if (!function_exists('Wo_IsAdmin') || !Wo_IsAdmin()) {
    die('Admin access required');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Storage Tracking</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .info { color: #0ff; }
        pre { background: #000; padding: 10px; border: 1px solid #333; }
    </style>
</head>
<body>
<h1>Storage Tracking Fix</h1>
<pre>
<?php

echo "Starting storage tracking fix...\n\n";

// Check if table exists
$tableExists = fm_query("SHOW TABLES LIKE 'fm_user_storage_tracking'");
if (empty($tableExists)) {
    echo "ERROR: Table fm_user_storage_tracking does not exist. Please run migration 005 first.\n";
    exit(1);
}

// Get all users who have uploaded files
$users = fm_query("SELECT DISTINCT user_id FROM fm_files ORDER BY user_id ASC");

if (empty($users)) {
    echo "No users with files found.\n";
    exit(0);
}

echo "Found " . count($users) . " users with files.\n\n";

$successCount = 0;
$errorCount = 0;

foreach ($users as $user) {
    $userId = (int)$user['user_id'];
    echo "Processing user ID: $userId ... ";

    try {
        // Calculate totals from fm_files
        $stats = fm_query("
            SELECT
                COUNT(*) as total_files,
                COALESCE(SUM(size), 0) as total_bytes,
                COALESCE(SUM(CASE WHEN r2_uploaded = 1 THEN size ELSE 0 END), 0) as r2_bytes
            FROM fm_files
            WHERE user_id = ? AND is_deleted = 0 AND is_folder = 0
        ", [$userId]);

        if (empty($stats)) {
            echo "SKIP (no data)\n";
            continue;
        }

        $totalFiles = (int)$stats[0]['total_files'];
        $totalBytes = (int)$stats[0]['total_bytes'];
        $r2Bytes = (int)$stats[0]['r2_bytes'];
        $localBytes = $totalBytes - $r2Bytes;

        // Count folders
        $folderStats = fm_query("
            SELECT COUNT(*) as total_folders
            FROM fm_files
            WHERE user_id = ? AND is_deleted = 0 AND is_folder = 1
        ", [$userId]);
        $totalFolders = !empty($folderStats) ? (int)$folderStats[0]['total_folders'] : 0;

        // Get quota
        $quota = fm_get_user_quota($userId);
        $quotaBytes = isset($quota['quota']) ? (int)$quota['quota'] : 1073741824; // 1GB default

        // Use raw SQL with INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO fm_user_storage_tracking
                (user_id, total_files, total_folders, used_bytes, quota_bytes,
                 r2_uploaded_bytes, local_only_bytes, last_calculated_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    total_files = VALUES(total_files),
                    total_folders = VALUES(total_folders),
                    used_bytes = VALUES(used_bytes),
                    quota_bytes = VALUES(quota_bytes),
                    r2_uploaded_bytes = VALUES(r2_uploaded_bytes),
                    local_only_bytes = VALUES(local_only_bytes),
                    last_calculated_at = NOW(),
                    updated_at = NOW()";

        $result = fm_query($sql, [$userId, $totalFiles, $totalFolders, $totalBytes, $quotaBytes, $r2Bytes, $localBytes]);

        if ($result !== false) {
            echo "OK (Files: $totalFiles, Size: " . fm_format_bytes_enhanced($totalBytes) . ")\n";
            $successCount++;

            // Also update fm_user_quotas
            fm_query("
                INSERT INTO fm_user_quotas (user_id, used_bytes, quota_bytes, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    used_bytes = VALUES(used_bytes),
                    updated_at = NOW()
            ", [$userId, $totalBytes, $quotaBytes]);
        } else {
            echo "ERROR\n";
            $errorCount++;
        }

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n";
echo "========================================\n";
echo "Summary:\n";
echo "  Success: $successCount\n";
echo "  Errors: $errorCount\n";
echo "========================================\n";

// Display global statistics
echo "\nGlobal Storage Statistics:\n";
$globalStats = fm_query("
    SELECT
        COUNT(DISTINCT user_id) as total_users,
        SUM(total_files) as total_files,
        SUM(used_bytes) as total_bytes,
        SUM(r2_uploaded_bytes) as r2_bytes,
        SUM(local_only_bytes) as local_bytes
    FROM fm_user_storage_tracking
");

if (!empty($globalStats)) {
    $stats = $globalStats[0];
    echo "  Total Users: " . $stats['total_users'] . "\n";
    echo "  Total Files: " . $stats['total_files'] . "\n";
    echo "  Total Storage: " . fm_format_bytes_enhanced($stats['total_bytes']) . "\n";
    echo "  R2 Storage: " . fm_format_bytes_enhanced($stats['r2_bytes']) . "\n";
    echo "  Local Storage: " . fm_format_bytes_enhanced($stats['local_bytes']) . "\n";
}

echo "\nDone!\n";

function fm_format_bytes_enhanced($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = 1024;
    $exp = floor(log($bytes) / log($base));
    $exp = min($exp, count($units) - 1);

    return round($bytes / pow($base, $exp), $precision) . ' ' . $units[$exp];
}

?>
</pre>
<p><a href="manage/pages/file_manager/content.phtml" style="color: #0ff;">← Back to File Manager</a></p>
</body>
</html>
