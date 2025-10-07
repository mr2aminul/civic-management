<?php
/**
 * Quick Fix Script
 * Automatically fixes common file manager issues
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Fix Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .fix-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; }
        .btn-danger { background: #dc3545; }
        .btn:hover { opacity: 0.8; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>

<h1>🔧 Quick Fix Tool</h1>
<p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<?php
if (!isset($_GET['action'])) {
    ?>
    <div class="fix-section">
        <h2>Available Fixes</h2>
        <p>Select a fix to apply:</p>
        <ul>
            <li><a href="?action=directories" class="btn">Create Required Directories</a></li>
            <li><a href="?action=permissions" class="btn">Fix File Permissions</a></li>
            <li><a href="?action=env_check" class="btn">Check Environment Variables</a></li>
            <li><a href="?action=db_tables" class="btn">Check Database Tables</a></li>
            <li><a href="?action=logs" class="btn">Initialize Log System</a></li>
            <li><a href="?action=test_db" class="btn">Test Database Connection</a></li>
            <li><a href="?action=clear_cache" class="btn">Clear All Caches</a></li>
            <li><a href="?action=fix_all" class="btn btn-danger">Run All Fixes</a></li>
        </ul>
    </div>

    <div class="fix-section">
        <h2>Quick Links</h2>
        <p>
            <a href="diagnose.php" class="btn">Run Diagnostics</a>
            <a href="test_complete_system.php" class="btn">Run Tests</a>
            <a href="FILE_MANAGER_SETUP_GUIDE.md" class="btn">View Guide</a>
        </p>
    </div>
    <?php
    exit;
}

require_once __DIR__ . '/assets/includes/file_manager_helper.php';

$action = $_GET['action'];
$fixes_applied = [];
$errors = [];

function apply_fix($name, $callback) {
    global $fixes_applied, $errors;

    echo "<div class='fix-section'>";
    echo "<h3>{$name}</h3>";

    try {
        $result = $callback();
        if ($result['success']) {
            echo "<p class='success'>✓ {$result['message']}</p>";
            $fixes_applied[] = $name;
        } else {
            echo "<p class='error'>✗ {$result['error']}</p>";
            $errors[] = $name . ': ' . $result['error'];
        }

        if (isset($result['details'])) {
            echo "<pre>" . htmlspecialchars(print_r($result['details'], true)) . "</pre>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Exception: {$e->getMessage()}</p>";
        $errors[] = $name . ': ' . $e->getMessage();
    }

    echo "</div>";
}

// ============================================
// Fix Actions
// ============================================

if ($action === 'directories' || $action === 'fix_all') {
    apply_fix('Create Required Directories', function() {
        $dirs = [
            getenv('LOCAL_STORAGE_DIR') ?: '/home/civicbd/civicgroup/storage',
            getenv('DB_BACKUP_LOCAL_DIR') ?: '/home/civicbd/civicgroup/backups',
            __DIR__ . '/logs'
        ];

        $created = [];
        $failed = [];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (@mkdir($dir, 0755, true)) {
                    $created[] = $dir;
                } else {
                    $failed[] = $dir;
                }
            } else {
                $created[] = $dir . ' (already exists)';
            }
        }

        if (!empty($failed)) {
            return [
                'success' => false,
                'error' => 'Failed to create: ' . implode(', ', $failed),
                'details' => ['created' => $created, 'failed' => $failed]
            ];
        }

        return [
            'success' => true,
            'message' => 'All directories created successfully',
            'details' => $created
        ];
    });
}

if ($action === 'permissions' || $action === 'fix_all') {
    apply_fix('Fix File Permissions', function() {
        $dirs = [
            getenv('LOCAL_STORAGE_DIR') ?: '/home/civicbd/civicgroup/storage',
            getenv('DB_BACKUP_LOCAL_DIR') ?: '/home/civicbd/civicgroup/backups',
            __DIR__ . '/logs'
        ];

        $fixed = [];
        $failed = [];

        foreach ($dirs as $dir) {
            if (file_exists($dir)) {
                if (@chmod($dir, 0755)) {
                    $fixed[] = $dir;
                } else {
                    $failed[] = $dir . ' (use: chmod 755 ' . $dir . ')';
                }
            }
        }

        if (!empty($failed)) {
            return [
                'success' => false,
                'error' => 'Some permissions could not be set automatically',
                'details' => ['fixed' => $fixed, 'manual_fix_needed' => $failed]
            ];
        }

        return [
            'success' => true,
            'message' => 'Permissions set successfully',
            'details' => $fixed
        ];
    });
}

if ($action === 'env_check' || $action === 'fix_all') {
    apply_fix('Check Environment Variables', function() {
        $required = [
            'DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME',
            'LOCAL_STORAGE_DIR', 'DB_BACKUP_LOCAL_DIR'
        ];

        $missing = [];
        $present = [];

        foreach ($required as $var) {
            $value = getenv($var);
            if (empty($value)) {
                $missing[] = $var;
            } else {
                $present[] = $var;
            }
        }

        if (!empty($missing)) {
            return [
                'success' => false,
                'error' => 'Missing environment variables: ' . implode(', ', $missing),
                'details' => ['missing' => $missing, 'present' => $present]
            ];
        }

        return [
            'success' => true,
            'message' => 'All required environment variables are set',
            'details' => $present
        ];
    });
}

if ($action === 'db_tables' || $action === 'fix_all') {
    apply_fix('Check Database Tables', function() {
        $conn = fm_get_db();
        if (!$conn) {
            return ['success' => false, 'error' => 'Database connection failed'];
        }

        $required_tables = [
            'fm_files', 'fm_user_quotas', 'fm_permissions',
            'fm_recycle_bin', 'fm_upload_queue', 'fm_activity_log',
            'backup_logs', 'restore_history'
        ];

        $exists = [];
        $missing = [];

        foreach ($required_tables as $table) {
            $result = fm_query("SELECT 1 FROM `{$table}` LIMIT 1");
            if ($result !== false) {
                $exists[] = $table;
            } else {
                $missing[] = $table;
            }
        }

        if (!empty($missing)) {
            return [
                'success' => false,
                'error' => 'Missing tables: ' . implode(', ', $missing),
                'details' => [
                    'existing' => $exists,
                    'missing' => $missing,
                    'solution' => 'Run: mysql -u user -p database < migrations/001_file_manager_and_backup_system.sql'
                ]
            ];
        }

        return [
            'success' => true,
            'message' => 'All required database tables exist',
            'details' => $exists
        ];
    });
}

if ($action === 'logs' || $action === 'fix_all') {
    apply_fix('Initialize Log System', function() {
        $logDir = __DIR__ . '/logs';

        if (!file_exists($logDir)) {
            if (!@mkdir($logDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create logs directory'];
            }
        }

        // Create test log entry
        $testLog = $logDir . '/test_' . date('Y-m-d') . '.log';
        $testContent = "[" . date('Y-m-d H:i:s') . "] Test log entry\n";

        if (@file_put_contents($testLog, $testContent) === false) {
            return ['success' => false, 'error' => 'Log directory not writable'];
        }

        // Clean up test log
        @unlink($testLog);

        return [
            'success' => true,
            'message' => 'Log system initialized and writable',
            'details' => ['log_directory' => $logDir]
        ];
    });
}

if ($action === 'test_db') {
    apply_fix('Test Database Connection', function() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');
        $name = getenv('DB_NAME');

        if (!$user || !$name) {
            return ['success' => false, 'error' => 'Database credentials not configured'];
        }

        try {
            $mysqli = new mysqli($host, $user, $pass, $name);

            if ($mysqli->connect_error) {
                return [
                    'success' => false,
                    'error' => 'Connection failed: ' . $mysqli->connect_error,
                    'details' => [
                        'host' => $host,
                        'user' => $user,
                        'database' => $name,
                        'error_code' => $mysqli->connect_errno
                    ]
                ];
            }

            $mysqli->set_charset('utf8mb4');

            // Test query
            $result = $mysqli->query("SELECT VERSION() as version");
            $row = $result->fetch_assoc();
            $version = $row['version'];

            $mysqli->close();

            return [
                'success' => true,
                'message' => 'Database connection successful',
                'details' => [
                    'host' => $host,
                    'database' => $name,
                    'mysql_version' => $version
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    });
}

if ($action === 'clear_cache') {
    apply_fix('Clear All Caches', function() {
        global $_FM_CACHE;
        $_FM_CACHE = [];

        $cleared = [];

        // Clear metadata files
        $backupDir = fm_get_backup_dir();
        $metadataFile = $backupDir . '/r2_backups_metadata.json';
        if (file_exists($metadataFile)) {
            @unlink($metadataFile);
            $cleared[] = 'R2 metadata cache';
        }

        // Clear any other cache files
        $cacheFiles = glob($backupDir . '/*_cache.json');
        foreach ($cacheFiles as $file) {
            @unlink($file);
            $cleared[] = basename($file);
        }

        return [
            'success' => true,
            'message' => 'Cache cleared successfully',
            'details' => !empty($cleared) ? $cleared : ['No cache files found']
        ];
    });
}

// ============================================
// Summary
// ============================================
echo "<div class='fix-section'>";
echo "<h2>Summary</h2>";

if (empty($errors)) {
    echo "<p class='success'>✓ All fixes applied successfully!</p>";
    if (!empty($fixes_applied)) {
        echo "<p><strong>Applied fixes:</strong></p><ul>";
        foreach ($fixes_applied as $fix) {
            echo "<li>{$fix}</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p class='error'>✗ Some fixes failed</p>";
    echo "<p><strong>Errors:</strong></p><ul>";
    foreach ($errors as $error) {
        echo "<li>{$error}</li>";
    }
    echo "</ul>";

    if (!empty($fixes_applied)) {
        echo "<p><strong>Successfully applied:</strong></p><ul>";
        foreach ($fixes_applied as $fix) {
            echo "<li>{$fix}</li>";
        }
        echo "</ul>";
    }
}

echo "</div>";

?>

<div class="fix-section">
    <h2>Next Steps</h2>
    <p>
        <a href="diagnose.php" class="btn">Run Diagnostics</a>
        <a href="test_complete_system.php" class="btn">Run Tests</a>
        <a href="?" class="btn">Back to Fixes</a>
    </p>
</div>

<p><strong>Completed:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

</body>
</html>
