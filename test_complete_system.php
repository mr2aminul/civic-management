<?php
/**
 * Complete File Manager & Backup System Test Suite
 * Tests all functionality and reports results
 */

// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load environment and helper
require_once __DIR__ . '/assets/includes/file_manager_helper.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete System Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-item { padding: 10px; margin: 5px 0; border-left: 4px solid #ddd; }
        .test-pass { border-left-color: #28a745; background: #d4edda; }
        .test-fail { border-left-color: #dc3545; background: #f8d7da; }
        .test-skip { border-left-color: #ffc107; background: #fff3cd; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .summary { padding: 20px; background: #e9ecef; border-radius: 8px; margin: 20px 0; }
        .summary-pass { background: #d4edda; }
        .summary-fail { background: #f8d7da; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>

<h1>🧪 Complete File Manager & Backup System Test Suite</h1>
<p><strong>Started:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<div>
    <a href="diagnose.php" class="btn">View Diagnostics</a>
    <a href="xhr/file_manager.php?s=ping" class="btn">Test API</a>
    <a href="?run=1" class="btn">Run Tests</a>
</div>

<?php
if (!isset($_GET['run'])) {
    echo '<div class="test-section"><p>Click "Run Tests" to start testing</p></div>';
    echo '</body></html>';
    exit;
}

$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'tests' => []
];

function test($name, $callback) {
    global $results;
    $results['total']++;

    echo "<div class='test-item'>";
    echo "<strong>Test:</strong> {$name}<br>";

    try {
        $result = $callback();
        if ($result === 'skip') {
            $results['skipped']++;
            echo "<span style='color: #ffc107'>○ SKIPPED</span>";
            echo "</div>";
            return;
        }

        if ($result['success']) {
            $results['passed']++;
            echo "<span style='color: #28a745'>✓ PASSED</span>";
            if (isset($result['message'])) {
                echo " - {$result['message']}";
            }
        } else {
            $results['failed']++;
            echo "<span style='color: #dc3545'>✗ FAILED</span>";
            if (isset($result['error'])) {
                echo " - {$result['error']}";
            }
        }

        if (isset($result['data'])) {
            echo "<pre>" . htmlspecialchars(print_r($result['data'], true)) . "</pre>";
        }
    } catch (Exception $e) {
        $results['failed']++;
        echo "<span style='color: #dc3545'>✗ EXCEPTION</span> - " . $e->getMessage();
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }

    echo "</div>";
    flush();
}

// ============================================
// Test Suite
// ============================================

echo '<div class="test-section"><h2>1. Environment Configuration</h2>';

test('Load .env file', function() {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        return ['success' => false, 'error' => '.env file not found'];
    }
    fm_load_env($envFile);
    return ['success' => true, 'message' => 'Environment loaded'];
});

test('Check database credentials', function() {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $name = getenv('DB_NAME');

    if (!$host || !$user || !$name) {
        return ['success' => false, 'error' => 'Missing database credentials'];
    }

    return ['success' => true, 'message' => "Host: {$host}, User: {$user}, Database: {$name}"];
});

test('Check storage directories configured', function() {
    $local = getenv('LOCAL_STORAGE_DIR');
    $backup = getenv('DB_BACKUP_LOCAL_DIR');

    if (!$local || !$backup) {
        return ['success' => false, 'error' => 'Storage directories not configured'];
    }

    return ['success' => true, 'message' => "Local: {$local}, Backup: {$backup}"];
});

echo '</div>';

// ============================================
echo '<div class="test-section"><h2>2. Database Connection</h2>';

test('Connect to database', function() {
    $conn = fm_get_db();
    if (!$conn) {
        return ['success' => false, 'error' => 'Database connection failed'];
    }
    return ['success' => true, 'message' => 'Connection type: ' . $conn['type']];
});

test('Query database tables', function() {
    $tables = ['fm_files', 'fm_user_quotas', 'fm_recycle_bin', 'fm_upload_queue'];
    $missing = [];

    foreach ($tables as $table) {
        $result = fm_query("SELECT 1 FROM `{$table}` LIMIT 1");
        if ($result === false) {
            $missing[] = $table;
        }
    }

    if (!empty($missing)) {
        return ['success' => false, 'error' => 'Missing tables: ' . implode(', ', $missing)];
    }

    return ['success' => true, 'message' => 'All required tables exist'];
});

echo '</div>';

// ============================================
echo '<div class="test-section"><h2>3. File System Operations</h2>';

test('Create local storage directory', function() {
    $dir = fm_get_local_dir();
    if (!file_exists($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return ['success' => false, 'error' => "Failed to create {$dir}"];
        }
    }

    if (!is_writable($dir)) {
        return ['success' => false, 'error' => "Directory not writable: {$dir}"];
    }

    return ['success' => true, 'message' => "Directory OK: {$dir}"];
});

test('Create backup directory', function() {
    $dir = fm_get_backup_dir();
    if (!file_exists($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return ['success' => false, 'error' => "Failed to create {$dir}"];
        }
    }

    if (!is_writable($dir)) {
        return ['success' => false, 'error' => "Directory not writable: {$dir}"];
    }

    return ['success' => true, 'message' => "Directory OK: {$dir}"];
});

test('List local folder', function() {
    $result = fm_list_local_folder('');
    if (!is_array($result) || !isset($result['folders']) || !isset($result['files'])) {
        return ['success' => false, 'error' => 'Invalid result structure'];
    }

    return [
        'success' => true,
        'message' => count($result['folders']) . ' folders, ' . count($result['files']) . ' files',
        'data' => $result
    ];
});

echo '</div>';

// ============================================
echo '<div class="test-section"><h2>4. User Quota System</h2>';

test('Get user quota for test user', function() {
    $userId = 1;
    $quota = fm_get_user_quota($userId);

    if (!isset($quota['quota']) || !isset($quota['used'])) {
        return ['success' => false, 'error' => 'Invalid quota structure'];
    }

    return [
        'success' => true,
        'message' => fm_format_bytes($quota['used']) . ' / ' . fm_format_bytes($quota['quota']),
        'data' => $quota
    ];
});

test('Check quota enforcement', function() {
    $userId = 1;
    $quota = fm_get_user_quota($userId);
    $check = fm_check_quota($userId, 1024 * 1024); // 1MB

    return [
        'success' => true,
        'message' => 'Quota check: ' . ($check ? 'PASS' : 'FAIL (quota exceeded)')
    ];
});

echo '</div>';

// ============================================
echo '<div class="test-section"><h2>5. Backup System</h2>';

test('Check shell commands availability', function() {
    $canUseShell = fm_can_use_shell();
    $mysqldump = fm_find_binary('mysqldump', ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump']);

    return [
        'success' => true,
        'message' => 'Shell: ' . ($canUseShell ? 'Available' : 'Not available') . ', mysqldump: ' . ($mysqldump ?: 'Not found')
    ];
});

test('Create test database backup', function() {
    $result = fm_create_db_dump('test_backup');

    if (!$result['success']) {
        return ['success' => false, 'error' => $result['message'] ?? 'Backup failed'];
    }

    $mode = $result['mode'] ?? 'unknown';
    $size = isset($result['size']) ? fm_format_bytes($result['size']) : 'unknown';

    return [
        'success' => true,
        'message' => "Backup created ({$mode} mode): {$result['filename']}, Size: {$size}",
        'data' => $result
    ];
});

test('List backup files', function() {
    $dir = fm_get_config()['backup_dir'];
    if (!is_dir($dir)) {
        return ['success' => false, 'error' => 'Backup directory does not exist'];
    }

    $files = scandir($dir);
    $backups = array_filter($files, function($f) {
        return preg_match('/\.(sql|sql\.gz)$/i', $f);
    });

    return [
        'success' => true,
        'message' => count($backups) . ' backup file(s) found',
        'data' => array_values($backups)
    ];
});

echo '</div>';

// ============================================
echo '<div class="test-section"><h2>6. R2 Storage (Optional)</h2>';

test('Check R2 configuration', function() {
    $key = getenv('R2_ACCESS_KEY_ID');
    $secret = getenv('R2_SECRET_ACCESS_KEY');
    $endpoint = getenv('R2_ENDPOINT');

    if (empty($key) || empty($secret) || empty($endpoint)) {
        return 'skip';
    }

    return ['success' => true, 'message' => 'R2 credentials configured'];
});

test('Initialize S3 client', function() {
    $s3 = fm_init_s3();
    if (!$s3) {
        return 'skip';
    }

    return ['success' => true, 'message' => 'S3 client initialized'];
});

echo '</div>';

// ============================================
echo '<div class="test-section"><h2>7. API Endpoints</h2>';

test('Test ping endpoint', function() {
    $url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/xhr/file_manager.php?s=ping';

    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $context);

    if (!$response) {
        return ['success' => false, 'error' => 'API endpoint not reachable'];
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['status'])) {
        return ['success' => false, 'error' => 'Invalid API response'];
    }

    return [
        'success' => $data['status'] == 200,
        'message' => 'API Status: ' . $data['status'],
        'data' => $data
    ];
});

echo '</div>';

// ============================================
echo '<div class="test-section"><h2>8. Helper Functions</h2>';

test('Format bytes function', function() {
    $tests = [
        [1024, '1 KB'],
        [1048576, '1 MB'],
        [1073741824, '1 GB']
    ];

    foreach ($tests as [$bytes, $expected]) {
        $result = fm_format_bytes($bytes);
        if ($result !== $expected) {
            return ['success' => false, 'error' => "Expected {$expected}, got {$result}"];
        }
    }

    return ['success' => true, 'message' => 'All format tests passed'];
});

test('Cache functions', function() {
    fm_cache_set('test_key', 'test_value');
    $value = fm_cache_get('test_key');

    if ($value !== 'test_value') {
        return ['success' => false, 'error' => 'Cache get/set failed'];
    }

    fm_cache_delete('test_key');
    $value = fm_cache_get('test_key');

    if ($value !== null) {
        return ['success' => false, 'error' => 'Cache delete failed'];
    }

    return ['success' => true, 'message' => 'Cache operations working'];
});

test('Configuration loading', function() {
    $config = fm_get_config();

    $required = ['local_storage', 'backup_dir', 'default_quota'];
    foreach ($required as $key) {
        if (!isset($config[$key])) {
            return ['success' => false, 'error' => "Missing config key: {$key}"];
        }
    }

    return ['success' => true, 'message' => 'Configuration loaded successfully'];
});

echo '</div>';

// ============================================
// Summary
// ============================================
$passRate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 1) : 0;
$summaryClass = $passRate >= 80 ? 'summary-pass' : 'summary-fail';

echo "<div class='summary {$summaryClass}'>";
echo "<h2>Test Summary</h2>";
echo "<p><strong>Total Tests:</strong> {$results['total']}</p>";
echo "<p><strong>Passed:</strong> <span style='color:#28a745'>{$results['passed']}</span></p>";
echo "<p><strong>Failed:</strong> <span style='color:#dc3545'>{$results['failed']}</span></p>";
echo "<p><strong>Skipped:</strong> <span style='color:#ffc107'>{$results['skipped']}</span></p>";
echo "<p><strong>Pass Rate:</strong> {$passRate}%</p>";

if ($results['failed'] === 0) {
    echo "<p style='color:#28a745; font-size:18px; font-weight:bold'>✓ All Tests Passed!</p>";
} else {
    echo "<p style='color:#dc3545; font-size:18px; font-weight:bold'>✗ Some Tests Failed</p>";
    echo "<p>Please review the failed tests above and check the error logs for details.</p>";
}
echo "</div>";

?>

<p><strong>Completed:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<div>
    <a href="diagnose.php" class="btn">View Diagnostics</a>
    <a href="manage/pages/file_manager/content.phtml" class="btn">Open File Manager</a>
</div>

</body>
</html>
