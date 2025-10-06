<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>File Manager System Diagnostics</h2>";

echo "<h3>1. Environment Variables</h3>";
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo "✓ .env file exists<br>";
    require_once __DIR__ . '/assets/includes/file_manager_helper.php';

    $requiredVars = [
        'R2_ACCESS_KEY_ID',
        'R2_SECRET_ACCESS_KEY',
        'R2_BUCKET',
        'R2_ENDPOINT',
        'DB_HOST',
        'DB_USER',
        'DB_NAME',
        'LOCAL_STORAGE_DIR'
    ];

    foreach ($requiredVars as $var) {
        $val = getenv($var);
        if ($val) {
            if (strpos($var, 'SECRET') !== false || strpos($var, 'PASSWORD') !== false) {
                echo "✓ $var: " . substr($val, 0, 4) . "..." . substr($val, -4) . "<br>";
            } else {
                echo "✓ $var: $val<br>";
            }
        } else {
            echo "✗ $var: NOT SET<br>";
        }
    }
} else {
    echo "✗ .env file NOT found<br>";
}

echo "<h3>2. Required Directories</h3>";
$cfg = fm_get_config();
$dirs = [
    'Local Storage' => $cfg['local_storage'],
    'Backup Directory' => $cfg['backup_dir']
];

foreach ($dirs as $name => $path) {
    if (file_exists($path)) {
        if (is_writable($path)) {
            echo "✓ $name: $path (writable)<br>";
        } else {
            echo "⚠ $name: $path (NOT writable)<br>";
        }
    } else {
        echo "✗ $name: $path (does NOT exist)<br>";
        @mkdir($path, 0755, true);
        if (file_exists($path)) {
            echo "&nbsp;&nbsp;→ Created successfully<br>";
        }
    }
}

echo "<h3>3. AWS SDK</h3>";
if (class_exists('Aws\\S3\\S3Client')) {
    echo "✓ AWS SDK is installed<br>";
} else {
    echo "✗ AWS SDK NOT found<br>";
    echo "&nbsp;&nbsp;→ Run: composer require aws/aws-sdk-php<br>";
}

echo "<h3>4. R2 Connection</h3>";
$s3 = fm_init_s3();
if ($s3) {
    echo "✓ R2 client initialized<br>";
    try {
        $result = $s3->listObjectsV2([
            'Bucket' => $cfg['r2_bucket'],
            'MaxKeys' => 1
        ]);
        echo "✓ Successfully connected to R2 bucket: {$cfg['r2_bucket']}<br>";
    } catch (Exception $e) {
        echo "✗ R2 connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ R2 client initialization failed<br>";
    echo "&nbsp;&nbsp;→ Check R2 credentials in .env<br>";
}

echo "<h3>5. Database Connection</h3>";
require_once __DIR__ . '/assets/init.php';

$conn = fm_get_db();
if ($conn) {
    echo "✓ Database connection established<br>";
    echo "&nbsp;&nbsp;Type: {$conn['type']}<br>";
} else {
    echo "✗ Database connection failed<br>";
}

echo "<h3>6. Required Database Tables</h3>";
$tables = [
    'fm_files',
    'fm_user_quotas',
    'fm_permissions',
    'fm_recycle_bin',
    'fm_upload_queue',
    'backup_logs',
    'backup_schedules',
    'restore_history',
    'fm_activity_log'
];

foreach ($tables as $table) {
    $result = fm_query("SHOW TABLES LIKE '$table'");
    if (!empty($result)) {
        echo "✓ Table: $table<br>";
    } else {
        echo "✗ Table: $table (missing)<br>";
    }
}

echo "<h3>7. File Manager API Endpoints</h3>";
$endpoints = [
    'ping' => '/requests.php?f=file_manager&s=ping',
    'get_env' => '/requests.php?f=file_manager&s=get_env',
    'list_local_folder' => '/requests.php?f=file_manager&s=list_local_folder'
];

foreach ($endpoints as $name => $path) {
    echo "• $name: <a href='$path' target='_blank'>$path</a><br>";
}

echo "<h3>8. System Requirements</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "MySQL Extension: " . (extension_loaded('mysqli') ? '✓' : '✗') . "<br>";
echo "cURL Extension: " . (extension_loaded('curl') ? '✓' : '✗') . "<br>";
echo "OpenSSL Extension: " . (extension_loaded('openssl') ? '✓' : '✗') . "<br>";
echo "Fileinfo Extension: " . (extension_loaded('fileinfo') ? '✓' : '✗') . "<br>";

echo "<h3>9. Recommendations</h3>";
if (!class_exists('Aws\\S3\\S3Client')) {
    echo "• Install AWS SDK: <code>composer require aws/aws-sdk-php</code><br>";
}

$missingTables = [];
foreach ($tables as $table) {
    $result = fm_query("SHOW TABLES LIKE '$table'");
    if (empty($result)) {
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "• Run migration: <code>mysql -u {$cfg['DB_USER']} -p {$cfg['DB_NAME']} &lt; migrations/001_file_manager_and_backup_system.sql</code><br>";
}

if (!is_writable($cfg['local_storage'])) {
    echo "• Fix storage permissions: <code>chmod 755 {$cfg['local_storage']} && chown -R www-data:www-data {$cfg['local_storage']}</code><br>";
}

echo "<hr>";
echo "<p><strong>Done!</strong> Check the results above.</p>";
?>
