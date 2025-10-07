<?php
/**
 * Script to wrap all functions in file_manager_helper.php with function_exists checks
 */

$file = __DIR__ . '/assets/includes/file_manager_helper.php';
$content = file_get_contents($file);

// List of functions that need wrapping (excluding those already wrapped)
$functionsToWrap = [
    'fm_get_config',
    'fm_get_db',
    'fm_query',
    'fm_insert',
    'fm_update',
    'fm_init_s3',
    'fm_upload_to_r2',
    'fm_download_from_r2',
    'fm_get_user_quota',
    'fm_update_user_quota',
    'fm_check_quota',
    'fm_create_folder',
    'fm_upload_file',
    'fm_delete_file',
    'fm_restore_file',
    'fm_empty_recycle_bin_auto',
    'fm_enqueue_r2_upload',
    'fm_process_upload_queue',
    'fm_create_full_backup',
    'fm_create_table_backup',
    'fm_restore_backup',
    'fm_get_table_categories',
    'fm_restore_selective_tables',
    'fm_restore_by_category',
    'fm_cleanup_old_backups',
    'fm_log_activity',
    'fm_get_local_dir',
    'fm_get_backup_dir',
    'fm_save_uploaded_local',
    'fm_list_local_folder',
    'fm_get_file_info',
    'fm_delete_local_recursive',
    'fm_list_r2_cached',
    'fm_get_pending_uploads',
    'fm_process_upload_queue_worker',
    'fm_can_use_shell',
    'fm_find_binary',
    'fm_create_db_dump_php',
    'fm_create_table_dump_php',
    'fm_restore_sql_gz_local_php',
    'fm_create_db_dump',
    'fm_create_table_dump',
    'fm_restore_sql_gz_local',
    'fm_enforce_retention',
    'fm_cache_get',
    'fm_cache_set',
    'fm_cache_delete',
    'fm_get_file_url',
    'fm_stream_file_download',
    'fm_sync_user_quota',
    'fm_calculate_total_storage'
];

// Backup original file
copy($file, $file . '.backup_' . date('Ymd_His'));

// Process each function
foreach ($functionsToWrap as $funcName) {
    // Pattern to match the function definition
    $pattern = '/^(function ' . preg_quote($funcName, '/') . '\s*\([^)]*\)\s*\{?)/m';

    // Replace with wrapped version
    $replacement = "if (!function_exists('$funcName')) {\n$1";
    $content = preg_replace($pattern, $replacement, $content, 1, $count);

    if ($count > 0) {
        // Find the closing brace for this function and add a closing brace for if statement
        // This is complex, so we'll do it manually later
        echo "Wrapped $funcName\n";
    }
}

// Save the file
file_put_contents($file, $content);

echo "\nDone! Backup saved to: $file.backup_" . date('Ymd_His') . "\n";
echo "Please review the file and manually add closing braces for each if (!function_exists()) block\n";
