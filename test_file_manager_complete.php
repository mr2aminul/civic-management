<?php
/**
 * Comprehensive File Manager Test Script
 * Tests all functionality and reports issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include app initialization
require_once __DIR__ . '/assets/includes/app_start.php';
require_once __DIR__ . '/assets/includes/file_manager_helper.php';

echo "========================================\n";
echo "File Manager Comprehensive Test\n";
echo "========================================\n\n";

$allPassed = true;
$testResults = [];

function test($name, $callback) {
    global $allPassed, $testResults;
    echo "Testing: $name... ";
    try {
        $result = $callback();
        if ($result === true || $result === 'PASS') {
            echo "✓ PASS\n";
            $testResults[$name] = 'PASS';
            return true;
        } else {
            echo "✗ FAIL\n";
            if (is_string($result)) {
                echo "  Error: $result\n";
            }
            $testResults[$name] = 'FAIL: ' . (is_string($result) ? $result : 'Unknown error');
            $allPassed = false;
            return false;
        }
    } catch (Exception $e) {
        echo "✗ ERROR\n";
        echo "  Exception: " . $e->getMessage() . "\n";
        $testResults[$name] = 'ERROR: ' . $e->getMessage();
        $allPassed = false;
        return false;
    }
}

// Test 1: Database Connection
test('Database Connection', function() {
    global $sqlConnect;
    if (!$sqlConnect || !$sqlConnect instanceof mysqli) {
        return 'Database connection not available';
    }
    if (mysqli_ping($sqlConnect)) {
        return true;
    }
    return 'Database connection lost';
});

// Test 2: Required Tables Exist
test('Required Tables', function() {
    $tables = [
        'fm_files', 'fm_user_quotas', 'fm_permissions', 'fm_recycle_bin',
        'fm_upload_queue', 'backup_logs', 'backup_schedules',
        'restore_history', 'fm_activity_log'
    ];

    foreach ($tables as $table) {
        $result = fm_query("SHOW TABLES LIKE ?", [$table]);
        if (empty($result)) {
            return "Table missing: $table";
        }
    }
    return true;
});

// Test 3: Storage Directory Exists and Writable
test('Storage Directory', function() {
    $dir = fm_get_local_dir();
    if (!file_exists($dir)) {
        return "Directory does not exist: $dir";
    }
    if (!is_writable($dir)) {
        return "Directory not writable: $dir";
    }
    return true;
});

// Test 4: Backup Directory
test('Backup Directory', function() {
    $cfg = fm_get_config();
    $dir = $cfg['backup_dir'];
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!file_exists($dir)) {
        return "Directory does not exist: $dir";
    }
    if (!is_writable($dir)) {
        return "Directory not writable: $dir";
    }
    return true;
});

// Test 5: Database Query Functions
test('Database Query Functions', function() {
    $result = fm_query("SELECT 1 as test");
    if (empty($result) || !isset($result[0]['test']) || $result[0]['test'] != 1) {
        return 'Query function not working';
    }
    return true;
});

// Test 6: User Quota Functions
test('User Quota System', function() {
    // Test with user ID 1 (usually exists)
    $quota = fm_get_user_quota(1);
    if (!isset($quota['quota']) || !isset($quota['used'])) {
        return 'Quota function not returning correct structure';
    }
    if (!is_numeric($quota['quota']) || !is_numeric($quota['used'])) {
        return 'Quota values not numeric';
    }
    return true;
});

// Test 7: Storage Calculation
test('Storage Calculation', function() {
    $usage = fm_calculate_user_disk_usage(1);
    if (!is_numeric($usage)) {
        return 'Calculate disk usage not returning number';
    }
    return true;
});

// Test 8: Format Bytes Function
test('Format Bytes Helper', function() {
    if (!function_exists('fm_format_bytes')) {
        return 'fm_format_bytes function not defined';
    }

    $result = fm_format_bytes(1024);
    if ($result !== '1 KB') {
        return "Expected '1 KB', got '$result'";
    }

    $result = fm_format_bytes(1048576);
    if ($result !== '1 MB') {
        return "Expected '1 MB', got '$result'";
    }

    return true;
});

// Test 9: R2 Configuration
test('R2 Configuration', function() {
    $cfg = fm_get_config();
    $hasR2 = !empty($cfg['r2_key']) && !empty($cfg['r2_secret']) && !empty($cfg['r2_endpoint']);

    if (!$hasR2) {
        echo "\n  Note: R2 not configured (optional)\n";
        return true; // Not required
    }

    $s3 = fm_init_s3();
    if (!$s3) {
        return 'R2 credentials configured but S3 client failed to initialize';
    }

    return true;
});

// Test 10: Upload Queue Table
test('Upload Queue Table', function() {
    $result = fm_query("SELECT COUNT(*) as cnt FROM fm_upload_queue");
    if (!isset($result[0]['cnt'])) {
        return 'Failed to query upload queue table';
    }
    return true;
});

// Test 11: Enqueue Function
test('Enqueue Upload Function', function() {
    $localDir = fm_get_local_dir();
    $testFile = $localDir . '/test_' . uniqid() . '.txt';

    // Create test file
    file_put_contents($testFile, 'test content');

    // Try to enqueue
    $result = fm_enqueue_r2_upload($testFile, 'test/file.txt', null);

    // Clean up
    @unlink($testFile);

    if ($result === false) {
        return 'Enqueue function returned false';
    }

    // Verify it was queued
    $queued = fm_query(
        "SELECT id FROM fm_upload_queue WHERE local_path = ? LIMIT 1",
        [$testFile]
    );

    if (empty($queued)) {
        return 'File was not added to queue';
    }

    // Clean up queue entry
    fm_query("DELETE FROM fm_upload_queue WHERE id = ?", [$queued[0]['id']]);

    return true;
});

// Test 12: List Local Folder
test('List Local Folder', function() {
    $result = fm_list_local_folder('');
    if (!isset($result['folders']) || !isset($result['files'])) {
        return 'List function not returning correct structure';
    }
    if (!is_array($result['folders']) || !is_array($result['files'])) {
        return 'Folders and files not arrays';
    }
    return true;
});

// Test 13: File Upload Test (actual file)
test('File Upload (Disk Write)', function() {
    $localDir = fm_get_local_dir();
    $testContent = 'test file content ' . time();
    $testFileName = 'test_upload_' . uniqid() . '.txt';
    $testPath = $localDir . '/' . $testFileName;

    // Write test file
    if (!file_put_contents($testPath, $testContent)) {
        return 'Failed to write test file to disk';
    }

    // Verify file exists
    if (!file_exists($testPath)) {
        return 'Test file does not exist after write';
    }

    // Verify content
    $readContent = file_get_contents($testPath);
    if ($readContent !== $testContent) {
        @unlink($testPath);
        return 'File content mismatch';
    }

    // Clean up
    @unlink($testPath);

    return true;
});

// Test 14: Database Insert Test
test('Database Insert (fm_files)', function() {
    $testData = [
        'user_id' => 1,
        'filename' => 'test_' . uniqid() . '.txt',
        'original_filename' => 'test.txt',
        'path' => 'test.txt',
        'size' => 100,
        'is_folder' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $fileId = fm_insert('fm_files', $testData);

    if (!$fileId) {
        return 'Failed to insert file record';
    }

    // Verify insert
    $verify = fm_query("SELECT id FROM fm_files WHERE id = ?", [$fileId]);
    if (empty($verify)) {
        return 'Inserted record not found';
    }

    // Clean up
    fm_query("DELETE FROM fm_files WHERE id = ?", [$fileId]);

    return true;
});

// Test 15: Quota Update Test
test('Quota Update Function', function() {
    $userId = 1;

    // Get current quota
    $before = fm_get_user_quota($userId);

    // Add 1000 bytes
    fm_update_user_quota($userId, 1000);

    // Get new quota
    $after = fm_get_user_quota($userId);

    // Verify increase
    if ($after['used'] !== ($before['used'] + 1000)) {
        // Restore original
        fm_update_user_quota($userId, -1000);
        return 'Quota not updated correctly';
    }

    // Restore original
    fm_update_user_quota($userId, -1000);

    return true;
});

// Test 16: Backup Functions Available
test('Backup Functions', function() {
    if (!function_exists('fm_create_db_dump')) {
        return 'fm_create_db_dump function not defined';
    }
    if (!function_exists('fm_create_table_dump')) {
        return 'fm_create_table_dump function not defined';
    }
    if (!function_exists('fm_restore_sql_gz_local')) {
        return 'fm_restore_sql_gz_local function not defined';
    }
    return true;
});

// Test 17: Cache Functions
test('Cache Functions', function() {
    fm_cache_set('test_key', 'test_value');
    $value = fm_cache_get('test_key');
    if ($value !== 'test_value') {
        return 'Cache get/set not working';
    }
    fm_cache_delete('test_key');
    $value = fm_cache_get('test_key');
    if ($value !== null) {
        return 'Cache delete not working';
    }
    return true;
});

// Test 18: Activity Log Table
test('Activity Log Table', function() {
    $result = fm_query("SELECT COUNT(*) as cnt FROM fm_activity_log");
    if (!isset($result[0]['cnt'])) {
        return 'Failed to query activity log table';
    }
    return true;
});

// Test 19: Recycle Bin Table
test('Recycle Bin Table', function() {
    $result = fm_query("SELECT COUNT(*) as cnt FROM fm_recycle_bin");
    if (!isset($result[0]['cnt'])) {
        return 'Failed to query recycle bin table';
    }
    return true;
});

// Test 20: Total Storage Calculation
test('Total Storage Calculation', function() {
    $total = fm_calculate_total_storage();
    if (!is_numeric($total)) {
        return 'Total storage not returning number';
    }
    if ($total < 0) {
        return 'Total storage negative';
    }
    return true;
});

echo "\n========================================\n";
echo "Test Summary\n";
echo "========================================\n\n";

$passCount = 0;
$failCount = 0;

foreach ($testResults as $name => $result) {
    if ($result === 'PASS') {
        $passCount++;
    } else {
        $failCount++;
        echo "✗ $name: $result\n";
    }
}

echo "\nTotal Tests: " . count($testResults) . "\n";
echo "Passed: $passCount\n";
echo "Failed: $failCount\n\n";

if ($allPassed) {
    echo "✓ All tests passed!\n";
    echo "File manager is fully operational.\n\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    echo "Please review the errors above and fix them.\n\n";
    exit(1);
}
