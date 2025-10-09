<?php
/**
 * Storage Tracking Migration Script
 * Runs the clean migration and recalculates all storage
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "===========================================\n";
echo "File Manager Storage Migration\n";
echo "===========================================\n\n";

// Load configuration
require_once __DIR__ . '/config.php';

if (!isset($sqlConnect)) {
    die("ERROR: Database connection not available. Check config.php\n");
}

echo "✓ Database connection established\n";

// Read migration file
$migrationFile = __DIR__ . '/migrations/000_file_manager_complete_clean.sql';
if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found: {$migrationFile}\n");
}

echo "✓ Migration file found\n";

$sql = file_get_contents($migrationFile);
if (!$sql) {
    die("ERROR: Could not read migration file\n");
}

echo "✓ Migration file loaded\n\n";

// Execute migration
echo "Executing migration...\n";
echo "This may take a few minutes...\n\n";

// Split SQL by semicolons but preserve DELIMITER blocks
$statements = [];
$currentStatement = '';
$inDelimiter = false;
$customDelimiter = ';';

$lines = explode("\n", $sql);
foreach ($lines as $line) {
    $trimmed = trim($line);

    // Handle DELIMITER changes
    if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
        if ($currentStatement) {
            $statements[] = $currentStatement;
            $currentStatement = '';
        }
        $customDelimiter = trim($matches[1]);
        $inDelimiter = ($customDelimiter !== ';');
        continue;
    }

    // Skip comments and empty lines
    if (empty($trimmed) || $trimmed[0] === '#' || substr($trimmed, 0, 2) === '--') {
        continue;
    }

    $currentStatement .= $line . "\n";

    // Check for statement end
    if ($inDelimiter) {
        if (strpos($line, $customDelimiter) !== false) {
            $statements[] = $currentStatement;
            $currentStatement = '';
        }
    } else {
        if (substr($trimmed, -1) === ';') {
            $statements[] = $currentStatement;
            $currentStatement = '';
        }
    }
}

if ($currentStatement) {
    $statements[] = $currentStatement;
}

$totalStatements = count($statements);
$executed = 0;
$failed = 0;
$errors = [];

foreach ($statements as $index => $statement) {
    $statement = trim($statement);
    if (empty($statement)) continue;

    // Remove custom delimiter if present
    if (substr($statement, -2) === '$$') {
        $statement = substr($statement, 0, -2);
    }

    try {
        if (mysqli_query($sqlConnect, $statement)) {
            $executed++;
            if ($executed % 5 === 0) {
                echo ".";
            }
        } else {
            $error = mysqli_error($sqlConnect);
            // Ignore "already exists" errors
            if (stripos($error, 'already exists') === false &&
                stripos($error, 'duplicate') === false) {
                $failed++;
                $errors[] = [
                    'statement' => substr($statement, 0, 100) . '...',
                    'error' => $error
                ];
            } else {
                $executed++;
            }
        }
    } catch (Exception $e) {
        $failed++;
        $errors[] = [
            'statement' => substr($statement, 0, 100) . '...',
            'error' => $e->getMessage()
        ];
    }
}

echo "\n\n";
echo "Migration execution completed:\n";
echo "  Executed: {$executed}\n";
echo "  Failed: {$failed}\n\n";

if (count($errors) > 0) {
    echo "Errors encountered:\n";
    foreach (array_slice($errors, 0, 5) as $error) {
        echo "  - " . substr($error['statement'], 0, 60) . "...\n";
        echo "    Error: " . $error['error'] . "\n";
    }
    if (count($errors) > 5) {
        echo "  ... and " . (count($errors) - 5) . " more errors\n";
    }
    echo "\n";
}

// Recalculate storage for all users
echo "Recalculating storage for all users...\n";

require_once __DIR__ . '/assets/includes/file_manager_storage.php';

try {
    $result = mysqli_query($sqlConnect, "CALL sp_recalculate_all_storage()");
    if ($result) {
        echo "✓ Storage recalculation completed via stored procedure\n";
    } else {
        echo "Note: Stored procedure not available, using PHP function...\n";
        $result = fm_recalculate_all_storage();
        if ($result['success']) {
            echo "✓ Recalculated storage for {$result['updated']} users\n";
            if ($result['failed'] > 0) {
                echo "  Warning: {$result['failed']} users failed\n";
            }
        } else {
            echo "Error: {$result['message']}\n";
        }
    }
} catch (Exception $e) {
    echo "Error during recalculation: " . $e->getMessage() . "\n";
}

echo "\n";

// Show summary statistics
echo "===========================================\n";
echo "Migration Summary\n";
echo "===========================================\n\n";

try {
    $stats = fm_get_global_storage_stats();

    echo "Total Users: {$stats['total_users']}\n";
    echo "Total Files: {$stats['total_files']}\n";
    echo "Total Folders: {$stats['total_folders']}\n";
    echo "Total Storage Used: " . fm_format_bytes($stats['total_used_bytes']) . "\n";
    echo "R2 Storage: " . fm_format_bytes($stats['r2_uploaded_bytes']) . "\n";
    echo "Local Storage: " . fm_format_bytes($stats['local_only_bytes']) . "\n";
    echo "VPS Total: " . fm_format_bytes($stats['vps_total_bytes']) . "\n";
    echo "VPS Available: " . fm_format_bytes($stats['vps_available_bytes']) . "\n";
    echo "VPS Usage: {$stats['vps_usage_percent']}%\n\n";

    // Show top 5 users
    echo "Top 5 Storage Users:\n";
    $topUsers = fm_get_user_storage_list(5, 0);
    if ($topUsers['success'] && count($topUsers['users']) > 0) {
        foreach ($topUsers['users'] as $user) {
            echo "  User {$user['user_id']}: " .
                 fm_format_bytes($user['used_bytes']) .
                 " ({$user['usage_percent']}% of quota)\n";
        }
    } else {
        echo "  No users found\n";
    }

} catch (Exception $e) {
    echo "Could not retrieve statistics: " . $e->getMessage() . "\n";
}

echo "\n===========================================\n";
echo "Migration completed successfully!\n";
echo "===========================================\n\n";

echo "Next steps:\n";
echo "1. Test file uploads and verify storage tracking\n";
echo "2. Check the admin dashboard for storage statistics\n";
echo "3. Review the STORAGE_MIGRATION_GUIDE.md for more information\n";
echo "4. Monitor logs for any errors\n\n";

echo "Optional: Remove old table (after verification):\n";
echo "  DROP TABLE IF EXISTS fm_user_storage_tracking;\n\n";
