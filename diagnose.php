<?php
/**
 * File Manager & Backup System Diagnostic Tool
 * This script tests all components and provides detailed error reporting
 */

// Force error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Output buffering off for immediate display
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);
ob_implicit_flush(true);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Manager Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .status-box { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .status-ok { background: #d4edda; border: 1px solid #c3e6cb; }
        .status-fail { background: #f8d7da; border: 1px solid #f5c6cb; }
        .status-warn { background: #fff3cd; border: 1px solid #ffeaa7; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
<h1>🔍 File Manager & Backup System Diagnostics</h1>
<p><strong>Started:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<?php

$diagnostics = [
    'php_config' => [],
    'environment' => [],
    'database' => [],
    'files' => [],
    'permissions' => [],
    'r2_storage' => [],
    'errors' => [],
    'recommendations' => []
];

// ============================================
// 1. PHP Configuration
// ============================================
echo '<div class="section">';
echo '<h2>1. PHP Configuration</h2>';

$phpVersion = phpversion();
$diagnostics['php_config']['version'] = $phpVersion;
echo "<p><strong>PHP Version:</strong> {$phpVersion} ";
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo '<span class="success">✓ OK</span>';
} else {
    echo '<span class="error">✗ PHP 7.4+ recommended</span>';
    $diagnostics['errors'][] = 'PHP version below 7.4';
}
echo '</p>';

echo '<p><strong>Error Reporting:</strong> ' . error_reporting() . ' <span class="info">(E_ALL = ' . E_ALL . ')</span></p>';
echo '<p><strong>Display Errors:</strong> ' . (ini_get('display_errors') ? 'ON' : 'OFF') . '</p>';
echo '<p><strong>Log Errors:</strong> ' . (ini_get('log_errors') ? 'ON' : 'OFF') . '</p>';
echo '<p><strong>Error Log:</strong> ' . (ini_get('error_log') ?: 'default') . '</p>';

// Check important PHP extensions
$required_extensions = ['mysqli', 'pdo', 'json', 'curl', 'mbstring', 'openssl', 'zip'];
echo '<h3>Required Extensions</h3><table>';
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '<span class="success">✓ Loaded</span>' : '<span class="error">✗ Missing</span>';
    echo "<tr><td>{$ext}</td><td>{$status}</td></tr>";
    $diagnostics['php_config']['extensions'][$ext] = $loaded;
    if (!$loaded && in_array($ext, ['mysqli', 'json'])) {
        $diagnostics['errors'][] = "Required extension missing: {$ext}";
    }
}
echo '</table>';

echo '</div>';

// ============================================
// 2. Environment Variables
// ============================================
echo '<div class="section">';
echo '<h2>2. Environment Variables</h2>';

$envFile = __DIR__ . '/.env';
echo "<p><strong>.env File:</strong> ";
if (file_exists($envFile)) {
    echo '<span class="success">✓ Found</span> (' . $envFile . ')';
    $diagnostics['environment']['env_file_exists'] = true;

    // Load .env
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $envVars = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k === '') continue;
        if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
            $v = substr($v, 1, -1);
        }
        $envVars[$k] = $v;
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }

    echo '<h3>Environment Variables</h3><table>';
    echo '<tr><th>Variable</th><th>Status</th></tr>';

    $required_vars = [
        'DB_HOST' => 'Database Host',
        'DB_USER' => 'Database User',
        'DB_PASSWORD' => 'Database Password',
        'DB_NAME' => 'Database Name',
        'LOCAL_STORAGE_DIR' => 'Local Storage Directory',
        'DB_BACKUP_LOCAL_DIR' => 'Backup Directory'
    ];

    foreach ($required_vars as $var => $label) {
        $value = getenv($var) ?: ($envVars[$var] ?? '');
        $isset = !empty($value);
        $status = $isset ? '<span class="success">✓ Set</span>' : '<span class="error">✗ Not Set</span>';
        $display = $isset ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : 'NOT SET';
        if (strpos($var, 'PASSWORD') !== false || strpos($var, 'SECRET') !== false) {
            $display = $isset ? '********' : 'NOT SET';
        }
        echo "<tr><td>{$label} ({$var})</td><td>{$status} <code>{$display}</code></td></tr>";
        $diagnostics['environment'][$var] = $isset;
        if (!$isset) {
            $diagnostics['errors'][] = "Missing environment variable: {$var}";
        }
    }

    $optional_vars = [
        'R2_ACCESS_KEY_ID' => 'R2 Access Key',
        'R2_SECRET_ACCESS_KEY' => 'R2 Secret Key',
        'R2_BUCKET' => 'R2 Bucket',
        'R2_ENDPOINT' => 'R2 Endpoint',
        'R2_ENDPOINT_DOMAIN' => 'R2 Domain'
    ];

    echo '<tr><td colspan="2"><strong>Optional (R2 Storage)</strong></td></tr>';
    foreach ($optional_vars as $var => $label) {
        $value = getenv($var) ?: ($envVars[$var] ?? '');
        $isset = !empty($value);
        $status = $isset ? '<span class="success">✓ Set</span>' : '<span class="warning">○ Not Set</span>';
        $display = $isset ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : 'NOT SET';
        if (strpos($var, 'SECRET') !== false) {
            $display = $isset ? '********' : 'NOT SET';
        }
        echo "<tr><td>{$label} ({$var})</td><td>{$status} <code>{$display}</code></td></tr>";
        $diagnostics['environment'][$var] = $isset;
    }

    echo '</table>';
} else {
    echo '<span class="error">✗ Not Found</span>';
    $diagnostics['environment']['env_file_exists'] = false;
    $diagnostics['errors'][] = '.env file not found';
}

echo '</div>';

// ============================================
// 3. Database Connection
// ============================================
echo '<div class="section">';
echo '<h2>3. Database Connection</h2>';

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: '';

echo "<p><strong>Host:</strong> {$db_host}</p>";
echo "<p><strong>Database:</strong> {$db_name}</p>";
echo "<p><strong>User:</strong> {$db_user}</p>";

try {
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($mysqli->connect_error) {
        throw new Exception($mysqli->connect_error);
    }

    echo '<div class="status-box status-ok">✓ Database connection successful</div>';
    $diagnostics['database']['connection'] = true;

    // Check tables
    echo '<h3>Database Tables</h3><table>';
    echo '<tr><th>Table</th><th>Rows</th><th>Status</th></tr>';

    $tables = [
        'fm_files', 'fm_user_quotas', 'fm_permissions',
        'fm_recycle_bin', 'fm_upload_queue', 'fm_activity_log',
        'backup_logs', 'backup_schedules', 'restore_history'
    ];

    foreach ($tables as $table) {
        $result = $mysqli->query("SELECT COUNT(*) as cnt FROM `{$table}`");
        if ($result) {
            $row = $result->fetch_assoc();
            $count = $row['cnt'];
            echo "<tr><td>{$table}</td><td>{$count}</td><td><span class='success'>✓ Exists</span></td></tr>";
            $diagnostics['database']['tables'][$table] = true;
        } else {
            echo "<tr><td>{$table}</td><td>-</td><td><span class='error'>✗ Missing</span></td></tr>";
            $diagnostics['database']['tables'][$table] = false;
            $diagnostics['errors'][] = "Database table missing: {$table}";
        }
    }
    echo '</table>';

    $mysqli->close();

} catch (Exception $e) {
    echo '<div class="status-box status-fail">✗ Database connection failed: ' . $e->getMessage() . '</div>';
    $diagnostics['database']['connection'] = false;
    $diagnostics['database']['error'] = $e->getMessage();
    $diagnostics['errors'][] = 'Database connection failed: ' . $e->getMessage();
}

echo '</div>';

// ============================================
// 4. File System
// ============================================
echo '<div class="section">';
echo '<h2>4. File System</h2>';

$local_storage = getenv('LOCAL_STORAGE_DIR') ?: '/home/civicbd/civicgroup/storage';
$backup_dir = getenv('DB_BACKUP_LOCAL_DIR') ?: '/home/civicbd/civicgroup/backups';

echo '<h3>Storage Directories</h3><table>';

$dirs = [
    'Local Storage' => $local_storage,
    'Backup Directory' => $backup_dir
];

foreach ($dirs as $label => $dir) {
    $exists = file_exists($dir);
    $writable = $exists && is_writable($dir);

    echo "<tr><td>{$label}</td><td><code>{$dir}</code></td><td>";
    if ($exists) {
        if ($writable) {
            echo '<span class="success">✓ Exists & Writable</span>';
            $diagnostics['files'][$label] = 'ok';
        } else {
            echo '<span class="warning">○ Exists but Not Writable</span>';
            $diagnostics['files'][$label] = 'not_writable';
            $diagnostics['errors'][] = "{$label} is not writable";
        }
    } else {
        echo '<span class="warning">○ Does Not Exist (will be created)</span>';
        $diagnostics['files'][$label] = 'missing';

        if (@mkdir($dir, 0755, true)) {
            echo ' → <span class="success">✓ Created successfully</span>';
            $diagnostics['files'][$label] = 'created';
        } else {
            echo ' → <span class="error">✗ Failed to create</span>';
            $diagnostics['errors'][] = "Failed to create directory: {$dir}";
        }
    }
    echo '</td></tr>';
}

echo '</table>';

// Check helper file
echo '<h3>Required Files</h3><table>';
$required_files = [
    'Helper' => __DIR__ . '/assets/includes/file_manager_helper.php',
    'XHR API' => __DIR__ . '/xhr/file_manager.php',
    'Config' => __DIR__ . '/config.php'
];

foreach ($required_files as $label => $file) {
    $exists = file_exists($file);
    $readable = $exists && is_readable($file);

    echo "<tr><td>{$label}</td><td><code>{$file}</code></td><td>";
    if ($exists && $readable) {
        echo '<span class="success">✓ OK</span>';
        $diagnostics['files']['required'][$label] = true;
    } else {
        echo '<span class="error">✗ Missing or Not Readable</span>';
        $diagnostics['files']['required'][$label] = false;
        $diagnostics['errors'][] = "Required file missing or not readable: {$label}";
    }
    echo '</td></tr>';
}

echo '</table>';

echo '</div>';

// ============================================
// 5. R2 Storage (if configured)
// ============================================
echo '<div class="section">';
echo '<h2>5. R2 Storage (Optional)</h2>';

$r2_key = getenv('R2_ACCESS_KEY_ID') ?: '';
$r2_secret = getenv('R2_SECRET_ACCESS_KEY') ?: '';
$r2_endpoint = getenv('R2_ENDPOINT') ?: '';
$r2_bucket = getenv('R2_BUCKET') ?: '';

if (!empty($r2_key) && !empty($r2_secret) && !empty($r2_endpoint)) {
    echo '<div class="status-box status-ok">✓ R2 credentials configured</div>';

    // Check AWS SDK
    $awsPath1 = __DIR__ . '/assets/libraries/aws-sdk-php/vendor/autoload.php';
    $awsPath2 = __DIR__ . '/vendor/autoload.php';

    if (file_exists($awsPath1) || file_exists($awsPath2)) {
        echo '<p><span class="success">✓ AWS SDK found</span></p>';
        $diagnostics['r2_storage']['sdk'] = true;

        // Try to initialize S3 client
        try {
            if (file_exists($awsPath1)) {
                require_once $awsPath1;
            } else {
                require_once $awsPath2;
            }

            if (class_exists('Aws\\S3\\S3Client')) {
                $s3 = new Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => 'auto',
                    'endpoint' => $r2_endpoint,
                    'use_path_style_endpoint' => false,
                    'credentials' => [
                        'key' => $r2_key,
                        'secret' => $r2_secret
                    ],
                ]);

                echo '<p><span class="success">✓ S3 Client initialized successfully</span></p>';
                $diagnostics['r2_storage']['client'] = true;

                // Test connection
                try {
                    $result = $s3->listObjectsV2([
                        'Bucket' => $r2_bucket,
                        'MaxKeys' => 1
                    ]);
                    echo '<p><span class="success">✓ R2 connection test successful</span></p>';
                    $diagnostics['r2_storage']['connection'] = true;
                } catch (Exception $e) {
                    echo '<p><span class="error">✗ R2 connection failed: ' . $e->getMessage() . '</span></p>';
                    $diagnostics['r2_storage']['connection'] = false;
                    $diagnostics['r2_storage']['error'] = $e->getMessage();
                }
            } else {
                echo '<p><span class="error">✗ AWS SDK S3Client class not found</span></p>';
                $diagnostics['r2_storage']['client'] = false;
            }
        } catch (Exception $e) {
            echo '<p><span class="error">✗ Error loading AWS SDK: ' . $e->getMessage() . '</span></p>';
            $diagnostics['r2_storage']['error'] = $e->getMessage();
        }
    } else {
        echo '<p><span class="warning">○ AWS SDK not found</span></p>';
        $diagnostics['r2_storage']['sdk'] = false;
        $diagnostics['recommendations'][] = 'Install AWS SDK for R2 storage functionality';
    }
} else {
    echo '<div class="status-box status-warn">○ R2 Storage not configured (optional feature)</div>';
    $diagnostics['r2_storage']['configured'] = false;
}

echo '</div>';

// ============================================
// 6. Summary & Recommendations
// ============================================
echo '<div class="section">';
echo '<h2>6. Summary</h2>';

$errorCount = count($diagnostics['errors']);
if ($errorCount === 0) {
    echo '<div class="status-box status-ok"><h3>✓ All Critical Systems OK</h3><p>Your file manager and backup system is properly configured.</p></div>';
} else {
    echo '<div class="status-box status-fail">';
    echo "<h3>✗ {$errorCount} Error(s) Found</h3>";
    echo '<ul>';
    foreach ($diagnostics['errors'] as $error) {
        echo "<li>{$error}</li>";
    }
    echo '</ul>';
    echo '</div>';
}

if (!empty($diagnostics['recommendations'])) {
    echo '<h3>Recommendations</h3>';
    echo '<ul>';
    foreach ($diagnostics['recommendations'] as $rec) {
        echo "<li>{$rec}</li>";
    }
    echo '</ul>';
}

echo '</div>';

// ============================================
// 7. Raw Diagnostics Data
// ============================================
echo '<div class="section">';
echo '<h2>7. Raw Diagnostics Data (JSON)</h2>';
echo '<pre>' . json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
echo '</div>';

?>

<div class="section">
    <h2>8. Quick Actions</h2>
    <p><a href="test_file_manager_complete.php">→ Run Complete File Manager Test</a></p>
    <p><a href="xhr/file_manager.php?s=ping">→ Test API Endpoint</a></p>
    <p><a href="manage/pages/file_manager/content.phtml">→ Open File Manager</a></p>
</div>

<p><strong>Completed:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
</body>
</html>
