<?php
/**
 * File Manager Installation and Fix Script
 * Run this once to create all necessary database tables and fix issues
 */

// Include the app initialization
require_once __DIR__ . '/assets/includes/app_start.php';

echo "====================================\n";
echo "File Manager Installation Script\n";
echo "====================================\n\n";

// Check database connection
if (!isset($sqlConnect) || !$sqlConnect) {
    die("ERROR: Database connection not available!\n");
}

echo "✓ Database connection OK\n";

// Read migration file
$migrationFile = __DIR__ . '/migrations/001_file_manager_and_backup_system.sql';
if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found at: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

echo "\nExecuting migration...\n";

$successCount = 0;
$errorCount = 0;
$errors = [];

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }

    if (mysqli_query($sqlConnect, $statement)) {
        $successCount++;
        // Extract table name for logging
        if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
            echo "  ✓ Created table: {$matches[1]}\n";
        } elseif (preg_match('/INSERT INTO.*?`?(\w+)`?/i', $statement, $matches)) {
            echo "  ✓ Inserted data into: {$matches[1]}\n";
        }
    } else {
        $error = mysqli_error($sqlConnect);
        // Ignore "already exists" errors
        if (stripos($error, 'already exists') === false && stripos($error, 'Duplicate') === false) {
            $errorCount++;
            $errors[] = $error;
            echo "  ✗ Error: $error\n";
        } else {
            echo "  - Table already exists (skipped)\n";
        }
    }
}

echo "\nMigration completed: $successCount successful, $errorCount errors\n";

if (!empty($errors)) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

// Verify tables exist
echo "\n====================================\n";
echo "Verifying tables...\n";
echo "====================================\n\n";

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

$allTablesExist = true;
foreach ($tables as $table) {
    $result = mysqli_query($sqlConnect, "SHOW TABLES LIKE '$table'");
    if ($result && mysqli_num_rows($result) > 0) {
        echo "  ✓ Table exists: $table\n";
    } else {
        echo "  ✗ Table missing: $table\n";
        $allTablesExist = false;
    }
}

if ($allTablesExist) {
    echo "\n✓ All required tables exist!\n";
} else {
    echo "\n✗ Some tables are missing. Please check the errors above.\n";
}

// Check and create storage directories
echo "\n====================================\n";
echo "Checking storage directories...\n";
echo "====================================\n\n";

$localDir = getenv('LOCAL_STORAGE_DIR') ?: '/home/civicbd/civicgroup/storage';
$backupDir = getenv('DB_BACKUP_LOCAL_DIR') ?: '/home/civicbd/civicgroup/backups';

echo "Local storage: $localDir\n";
echo "Backup storage: $backupDir\n\n";

foreach ([$localDir, $backupDir] as $dir) {
    if (!file_exists($dir)) {
        if (@mkdir($dir, 0755, true)) {
            echo "  ✓ Created directory: $dir\n";
        } else {
            echo "  ✗ Failed to create directory: $dir (check permissions)\n";
        }
    } else {
        echo "  ✓ Directory exists: $dir\n";
        if (is_writable($dir)) {
            echo "    ✓ Directory is writable\n";
        } else {
            echo "    ✗ Directory is NOT writable (check permissions)\n";
        }
    }
}

// Test database functions
echo "\n====================================\n";
echo "Testing database functions...\n";
echo "====================================\n\n";

// Test query function
$testQuery = mysqli_query($sqlConnect, "SELECT COUNT(*) as cnt FROM fm_files");
if ($testQuery) {
    $row = mysqli_fetch_assoc($testQuery);
    echo "  ✓ Database queries working (files count: {$row['cnt']})\n";
} else {
    echo "  ✗ Database query failed: " . mysqli_error($sqlConnect) . "\n";
}

// Test quota system
$testQuery = mysqli_query($sqlConnect, "SELECT COUNT(*) as cnt FROM fm_user_quotas");
if ($testQuery) {
    $row = mysqli_fetch_assoc($testQuery);
    echo "  ✓ Quota system working (quotas count: {$row['cnt']})\n";
} else {
    echo "  ✗ Quota system failed: " . mysqli_error($sqlConnect) . "\n";
}

// Test upload queue
$testQuery = mysqli_query($sqlConnect, "SELECT COUNT(*) as cnt FROM fm_upload_queue");
if ($testQuery) {
    $row = mysqli_fetch_assoc($testQuery);
    echo "  ✓ Upload queue working (queue count: {$row['cnt']})\n";
} else {
    echo "  ✗ Upload queue failed: " . mysqli_error($sqlConnect) . "\n";
}

echo "\n====================================\n";
echo "Installation Complete!\n";
echo "====================================\n\n";

echo "Next steps:\n";
echo "1. Access the file manager at: /manage/file_manager\n";
echo "2. Configure R2 settings in .env file\n";
echo "3. Test file upload functionality\n";
echo "4. Configure cron jobs for automatic backups\n\n";

echo "For help, see: FILE_MANAGER_AND_BACKUP_SYSTEM.md\n\n";
