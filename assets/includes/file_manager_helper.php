<?php
/**
 * File Manager & Backup System Helper
 * Advanced features: Drive-like interface, R2 storage, quotas, permissions, recycle bin
 */

if (file_exists(LIBS_DIR . "/aws-sdk-php/vendor/autoload.php")) {
    require_once LIBS_DIR . "/aws-sdk-php/vendor/autoload.php";
}

if (!function_exists('Wo_Ajax_Requests_File')) {
    function Wo_Ajax_Requests_File(){
        Global $wo;
    	return $wo['config']['site_url'] . "/requests.php";
    }
}
// Load environment variables
if (!function_exists('fm_load_env')) {
    function fm_load_env($path = null) {
        $path = $path ?: __DIR__ . '/../../.env';
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if ($k === '') continue;
            if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) $v = substr($v, 1, -1);
            putenv("$k=$v"); $_ENV[$k] = $v;
        }
    }
}
fm_load_env();

// Load storage tracking functions
if (file_exists(__DIR__ . '/file_manager_storage.php')) {
    require_once __DIR__ . '/file_manager_storage.php';
}

// ============================================
// Configuration
// ============================================
function fm_get_config() {
    $autoUploadTypes = array_filter(array_map('trim', explode(',', getenv('AUTO_UPLOAD_TYPES') ?: 'sql,zip,xlsx,docx,pdf')));
    return [
        'local_storage' => getenv('LOCAL_STORAGE_DIR') ?: '/home/civicbd/civicgroup/storage',
        'backup_dir' => getenv('DB_BACKUP_LOCAL_DIR') ?: '/home/civicbd/civicgroup/backups',
        'r2_key' => getenv('R2_ACCESS_KEY_ID') ?: '',
        'r2_secret' => getenv('R2_SECRET_ACCESS_KEY') ?: '',
        'r2_bucket' => getenv('R2_BUCKET') ?: 'civic-management',
        'r2_endpoint' => getenv('R2_ENDPOINT') ?: '',
        'r2_domain' => getenv('R2_ENDPOINT_DOMAIN') ?: '',
        'default_quota' => (int)((float)(getenv('DEFAULT_USER_QUOTA_GB') ?: 1) * 1024 * 1024 * 1024),
        'auto_upload_types' => $autoUploadTypes,
        'auto_upload_exts' => $autoUploadTypes,
        'auto_upload_prefixes' => array_filter(array_map('trim', explode(',', getenv('AUTO_UPLOAD_PREFIXES') ?: 'db_,sys_'))),
        'recycle_retention_days' => (int)(getenv('RECYCLE_RETENTION_DAYS') ?: 30),
        'backup_retention_days' => (int)(getenv('BACKUP_RETENTION_DAYS') ?: 30),
    ];
}

// ============================================
// Database Helpers
// ============================================
function fm_get_db() {
    global $db, $sqlConnect, $_FM_DB_CONNECTION;

    // Check for JoshCam MysqliDb wrapper
    if (isset($db) && method_exists($db, 'where')) {
        return ['type' => 'joshcam', 'db' => $db];
    }

    // Check for global mysqli connection
    if (isset($sqlConnect) && $sqlConnect instanceof mysqli) {
        return ['type' => 'mysqli', 'db' => $sqlConnect];
    }

    // Check for cached connection
    if (isset($_FM_DB_CONNECTION) && $_FM_DB_CONNECTION instanceof mysqli) {
        return ['type' => 'mysqli', 'db' => $_FM_DB_CONNECTION];
    }

    // Return null if no connection is available
    return null;
}

function fm_query($sql, $params = []) {
    $conn = fm_get_db();
    if (!$conn) return false;

    if ($conn['type'] === 'mysqli') {
        $mysqli = $conn['db'];
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("fm_query: prepare failed: " . $mysqli->error . " SQL: " . $sql);
            return false;
        }

        if (!empty($params)) {
            $types = '';
            $values = [];
            foreach ($params as $p) {
                if (is_int($p)) $types .= 'i';
                else if (is_double($p)) $types .= 'd';
                else $types .= 's';
                $values[] = $p;
            }
            $stmt->bind_param($types, ...$values);
        }

        if (!$stmt->execute()) {
            error_log("fm_query: execute failed: " . $stmt->error . " SQL: " . $sql);
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $data;
        }

        $ret = ['affected_rows' => $stmt->affected_rows, 'insert_id' => $stmt->insert_id];
        $stmt->close();
        return $ret;
    }

    return false;
}


function fm_insert($table, $data) {
    $conn = fm_get_db();
    if (!$conn) return false;

    if ($conn['type'] === 'joshcam') {
        try {
            return $conn['db']->insert($table, $data);
        } catch (Exception $e) {
            return false;
        }
    }

    $keys = array_keys($data);
    $values = array_values($data);
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $sql = "INSERT INTO `$table` (`" . implode('`, `', $keys) . "`) VALUES ($placeholders)";
    $result = fm_query($sql, $values);

    if (is_array($result) && isset($result['insert_id'])) {
        return $result['insert_id'];
    }
    return $result;
}

function fm_update($table, $data, $where) {
    $conn = fm_get_db();
    if (!$conn) return false;

    if ($conn['type'] === 'joshcam') {
        try {
            foreach ($where as $k => $v) {
                $conn['db']->where($k, $v);
            }
            return $conn['db']->update($table, $data);
        } catch (Exception $e) {
            return false;
        }
    }

    $sets = [];
    $values = [];
    foreach ($data as $k => $v) {
        $sets[] = "`$k` = ?";
        $values[] = $v;
    }

    $wheres = [];
    foreach ($where as $k => $v) {
        $wheres[] = "`$k` = ?";
        $values[] = $v;
    }

    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $wheres);
    return fm_query($sql, $values);
}

// ============================================
// R2 Storage
// ============================================
function fm_init_s3() {
    $cfg = fm_get_config();
    if (empty($cfg['r2_key']) || empty($cfg['r2_secret']) || empty($cfg['r2_endpoint'])) return null;
    if (!class_exists('Aws\\S3\\S3Client')) return null;

    try {
        return new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => $cfg['r2_endpoint'],
            'use_path_style_endpoint' => false,
            'credentials' => [
                'key' => $cfg['r2_key'],
                'secret' => $cfg['r2_secret']
            ],
        ]);
    } catch (Exception $e) {
        return null;
    }
}

function fm_upload_to_r2($localPath, $remoteKey) {
    $cfg = fm_get_config();
    if (!file_exists($localPath)) return ['success' => false, 'message' => 'Local file not found'];

    $s3 = fm_init_s3();
    if (!$s3) return ['success' => false, 'message' => 'R2 not configured'];

    try {
        $result = $s3->putObject([
            'Bucket' => $cfg['r2_bucket'],
            'Key' => $remoteKey,
            'SourceFile' => $localPath,
            'ACL' => 'private'
        ]);

        $url = !empty($cfg['r2_domain']) ? rtrim($cfg['r2_domain'], '/') . '/' . ltrim($remoteKey, '/') : null;
        return ['success' => true, 'url' => $url, 'key' => $remoteKey];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function fm_download_from_r2($remoteKey, $localPath) {
    $cfg = fm_get_config();
    $s3 = fm_init_s3();
    if (!$s3) return ['success' => false, 'message' => 'R2 not configured'];

    try {
        $result = $s3->getObject([
            'Bucket' => $cfg['r2_bucket'],
            'Key' => $remoteKey,
            'SaveAs' => $localPath
        ]);
        return ['success' => true, 'path' => $localPath];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ============================================
// User Quotas
// Note: Main quota functions are now in file_manager_storage.php
// These wrappers are kept for backward compatibility
// ============================================

// Storage functions are loaded from file_manager_storage.php
// If that file is not loaded, define fallback functions
if (!function_exists('fm_get_user_quota')) {
    function fm_get_user_quota($userId) {
        global $db;
        $userId = (int)$userId;
        try {
            $db->where('user_id', $userId);
            $result = $db->getOne('fm_user_quotas', ['quota_bytes', 'used_bytes']);
            if ($result) {
                return [
                    'quota' => (int)$result->quota_bytes,
                    'used' => (int)$result->used_bytes,
                    'available' => (int)$result->quota_bytes - (int)$result->used_bytes
                ];
            }
        } catch (Exception $e) {
            error_log("fm_get_user_quota: Error - " . $e->getMessage());
        }
        $cfg = fm_get_config();
        return ['quota' => $cfg['default_quota'], 'used' => 0, 'available' => $cfg['default_quota']];
    }
}

if (!function_exists('fm_check_quota')) {
    function fm_check_quota($userId, $requiredBytes) {
        $quota = fm_get_user_quota($userId);
        return ($quota['used'] + $requiredBytes) <= $quota['quota'];
    }
}

// ============================================
// File Operations
// ============================================
function fm_create_folder($userId, $folderName, $parentId = null, $isGlobal = false) {
    $path = $folderName;
    if ($parentId) {
        $parent = fm_query("SELECT path FROM fm_files WHERE id = ?", [$parentId]);
        if (!empty($parent)) {
            $path = rtrim($parent[0]['path'], '/') . '/' . $folderName;
        }
    }

    $data = [
        'user_id' => $userId,
        'parent_folder_id' => $parentId,
        'filename' => $folderName,
        'original_filename' => $folderName,
        'path' => $path,
        'is_folder' => 1,
        'is_global' => $isGlobal ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s')
    ];

    return fm_insert('fm_files', $data);
}

function fm_upload_file($userId, $fileData, $parentId = null) {
    $cfg = fm_get_config();

    if (!fm_check_quota($userId, $fileData['size'])) {
        return ['success' => false, 'message' => 'Quota exceeded'];
    }

    @mkdir($cfg['local_storage'], 0755, true);

    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileData['name']);
    $destPath = $cfg['local_storage'] . '/' . uniqid('file_') . '_' . $safeName;

    if (isset($fileData['tmp_name']) && is_uploaded_file($fileData['tmp_name'])) {
        if (!move_uploaded_file($fileData['tmp_name'], $destPath)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    } else {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }

    $path = $safeName;
    if ($parentId) {
        $parent = fm_query("SELECT path FROM fm_files WHERE id = ?", [$parentId]);
        if (!empty($parent)) {
            $path = rtrim($parent[0]['path'], '/') . '/' . $safeName;
        }
    }

    $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $checksum = md5_file($destPath);

    $data = [
        'user_id' => $userId,
        'parent_folder_id' => $parentId,
        'filename' => basename($destPath),
        'original_filename' => $fileData['name'],
        'path' => $path,
        'file_type' => $ext,
        'mime_type' => $fileData['type'] ?? 'application/octet-stream',
        'size' => filesize($destPath),
        'checksum' => $checksum,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $fileId = fm_insert('fm_files', $data);

    if ($fileId) {
        fm_update_user_quota($userId, filesize($destPath));

        $shouldAutoUpload = in_array($ext, $cfg['auto_upload_types']);
        foreach ($cfg['auto_upload_prefixes'] as $prefix) {
            if (stripos($safeName, $prefix) === 0) $shouldAutoUpload = true;
        }

        if ($shouldAutoUpload) {
            $r2Key = 'files/' . date('Y/m/') . basename($destPath);
            fm_enqueue_r2_upload($destPath, $r2Key, $fileId);
        }

        return ['success' => true, 'file_id' => $fileId, 'path' => $destPath];
    }

    return ['success' => false, 'message' => 'Database insert failed'];
}

function fm_delete_file($fileId, $userId, $isAdmin = false) {
    $file = fm_query("SELECT * FROM fm_files WHERE id = ?", [$fileId]);
    if (empty($file)) return ['success' => false, 'message' => 'File not found'];

    $file = $file[0];

    if ($file['user_id'] != $userId && !$isAdmin && $file['is_global'] == 0) {
        return ['success' => false, 'message' => 'Permission denied'];
    }

    $cfg = fm_get_config();
    $autoDeleteAt = date('Y-m-d H:i:s', strtotime('+' . $cfg['recycle_retention_days'] . ' days'));

    fm_insert('fm_recycle_bin', [
        'file_id' => $fileId,
        'user_id' => $userId,
        'original_path' => $file['path'],
        'filename' => $file['original_filename'],
        'size' => $file['size'],
        'deleted_at' => date('Y-m-d H:i:s'),
        'auto_delete_at' => $autoDeleteAt
    ]);

    fm_update('fm_files', [
        'is_deleted' => 1,
        'deleted_at' => date('Y-m-d H:i:s'),
        'deleted_by' => $userId
    ], ['id' => $fileId]);

    return ['success' => true, 'message' => 'Moved to recycle bin'];
}

function fm_restore_file($recycleId, $userId, $isAdmin = false) {
    $recycle = fm_query("SELECT * FROM fm_recycle_bin WHERE id = ?", [$recycleId]);
    if (empty($recycle)) return ['success' => false, 'message' => 'Not found'];

    $recycle = $recycle[0];

    if ($recycle['user_id'] != $userId && !$isAdmin) {
        return ['success' => false, 'message' => 'Permission denied'];
    }

    fm_update('fm_files', [
        'is_deleted' => 0,
        'deleted_at' => null,
        'deleted_by' => null
    ], ['id' => $recycle['file_id']]);

    fm_update('fm_recycle_bin', [
        'restored_at' => date('Y-m-d H:i:s')
    ], ['id' => $recycleId]);

    return ['success' => true, 'message' => 'File restored'];
}

function fm_empty_recycle_bin_auto() {
    $expired = fm_query("SELECT * FROM fm_recycle_bin WHERE auto_delete_at <= NOW() AND restored_at IS NULL AND force_deleted_at IS NULL");

    $deleted = 0;
    foreach ($expired as $item) {
        $file = fm_query("SELECT * FROM fm_files WHERE id = ?", [$item['file_id']]);
        if (!empty($file)) {
            $file = $file[0];
            $cfg = fm_get_config();
            $fullPath = $cfg['local_storage'] . '/' . $file['filename'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            fm_update_user_quota($file['user_id'], -$file['size']);
        }

        fm_update('fm_recycle_bin', [
            'force_deleted_at' => date('Y-m-d H:i:s'),
            'force_deleted_by' => 0
        ], ['id' => $item['id']]);

        $deleted++;
    }

    return ['deleted' => $deleted];
}

// ============================================
// R2 Upload Queue
// ============================================
function fm_enqueue_r2_upload($localPath, $remoteKey, $fileId = null) {
    $result = fm_insert('fm_upload_queue', [
        'file_id' => $fileId,
        'local_path' => $localPath,
        'remote_key' => $remoteKey,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    return $result ? true : false;
}

function fm_process_upload_queue($limit = 20) {
    $queue = fm_query("SELECT * FROM fm_upload_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT ?", [$limit]);

    $processed = [];
    foreach ($queue as $job) {
        fm_update('fm_upload_queue', ['status' => 'processing'], ['id' => $job['id']]);

        if (!file_exists($job['local_path'])) {
            fm_update('fm_upload_queue', [
                'status' => 'error',
                'message' => 'Local file not found'
            ], ['id' => $job['id']]);
            $processed[] = ['id' => $job['id'], 'status' => 'error'];
            continue;
        }

        $result = fm_upload_to_r2($job['local_path'], $job['remote_key']);

        if ($result['success']) {
            fm_update('fm_upload_queue', [
                'status' => 'done',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $job['id']]);

            if ($job['file_id']) {
                fm_update('fm_files', [
                    'r2_key' => $job['remote_key'],
                    'r2_uploaded' => 1,
                    'r2_uploaded_at' => date('Y-m-d H:i:s')
                ], ['id' => $job['file_id']]);
            }

            $processed[] = ['id' => $job['id'], 'status' => 'done'];
        } else {
            fm_update('fm_upload_queue', [
                'status' => 'error',
                'message' => $result['message'],
                'retry_count' => $job['retry_count'] + 1
            ], ['id' => $job['id']]);
            $processed[] = ['id' => $job['id'], 'status' => 'error'];
        }
    }

    return $processed;
}

// ============================================
// Backup System
// ============================================
function fm_create_full_backup($createdBy = 0) {
    $cfg = fm_get_config();
    @mkdir($cfg['backup_dir'], 0755, true);

    $dbUser = getenv('DB_USER') ?: '';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    $dbName = getenv('DB_NAME') ?: '';
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';

    if (!$dbName) return ['success' => false, 'message' => 'DB_NAME not set'];

    $timestamp = date('Ymd_His');
    $filename = "db_backup_full_{$timestamp}.sql.gz";
    $filepath = $cfg['backup_dir'] . '/' . $filename;

    $logId = fm_insert('backup_logs', [
        'backup_type' => 'full',
        'filename' => $filename,
        'file_path' => $filepath,
        'status' => 'inprogress',
        'created_by' => $createdBy,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null'));
    if (!$mysqldump) $mysqldump = '/usr/bin/mysqldump';

    $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
    file_put_contents($tmpcnf, "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n");

    $cmd = sprintf(
        '%s --defaults-extra-file=%s --single-transaction --quick --lock-tables=false %s 2>&1 | gzip -c > %s',
        escapeshellcmd($mysqldump),
        escapeshellarg($tmpcnf),
        escapeshellarg($dbName),
        escapeshellarg($filepath)
    );

    exec($cmd, $output, $returnCode);
    @unlink($tmpcnf);

    if ($returnCode === 0 && file_exists($filepath)) {
        $size = filesize($filepath);
        $checksum = md5_file($filepath);

        fm_update('backup_logs', [
            'status' => 'completed',
            'size' => $size,
            'checksum' => $checksum,
            'completed_at' => date('Y-m-d H:i:s')
        ], ['id' => $logId]);

        $r2Key = 'backups/' . date('Y/m/') . $filename;
        fm_enqueue_r2_upload($filepath, $r2Key, null);

        return ['success' => true, 'filename' => $filename, 'size' => $size, 'log_id' => $logId];
    }

    fm_update('backup_logs', [
        'status' => 'failed',
        'error_message' => implode("\n", $output)
    ], ['id' => $logId]);

    return ['success' => false, 'message' => 'Backup failed', 'output' => $output];
}

function fm_create_table_backup($tableName, $createdBy = 0) {
    $cfg = fm_get_config();
    @mkdir($cfg['backup_dir'], 0755, true);

    $dbUser = getenv('DB_USER') ?: '';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    $dbName = getenv('DB_NAME') ?: '';
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';

    if (!$dbName || !$tableName) return ['success' => false, 'message' => 'Missing parameters'];

    $timestamp = date('Ymd_His');
    $filename = "db_backup_table_{$tableName}_{$timestamp}.sql.gz";
    $filepath = $cfg['backup_dir'] . '/' . $filename;

    $logId = fm_insert('backup_logs', [
        'backup_type' => 'table',
        'table_name' => $tableName,
        'filename' => $filename,
        'file_path' => $filepath,
        'status' => 'inprogress',
        'created_by' => $createdBy,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null')) ?: '/usr/bin/mysqldump';

    $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
    file_put_contents($tmpcnf, "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n");

    $cmd = sprintf(
        '%s --defaults-extra-file=%s --single-transaction %s %s 2>&1 | gzip -c > %s',
        escapeshellcmd($mysqldump),
        escapeshellarg($tmpcnf),
        escapeshellarg($dbName),
        escapeshellarg($tableName),
        escapeshellarg($filepath)
    );

    exec($cmd, $output, $returnCode);
    @unlink($tmpcnf);

    if ($returnCode === 0 && file_exists($filepath)) {
        $size = filesize($filepath);
        fm_update('backup_logs', [
            'status' => 'completed',
            'size' => $size,
            'completed_at' => date('Y-m-d H:i:s')
        ], ['id' => $logId]);

        return ['success' => true, 'filename' => $filename, 'size' => $size];
    }

    fm_update('backup_logs', [
        'status' => 'failed',
        'error_message' => implode("\n", $output)
    ], ['id' => $logId]);

    return ['success' => false, 'message' => 'Table backup failed'];
}

function fm_restore_backup($filename, $restoredBy, $targetDb = null) {
    $cfg = fm_get_config();
    $filepath = $cfg['backup_dir'] . '/' . basename($filename);

    if (!file_exists($filepath)) {
        return ['success' => false, 'message' => 'Backup file not found'];
    }

    $dbUser = getenv('DB_USER') ?: '';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    $dbName = $targetDb ?: (getenv('DB_NAME') ?: '');
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';

    $restoreId = fm_insert('restore_history', [
        'backup_filename' => $filename,
        'restored_from' => 'local',
        'target_database' => $dbName,
        'status' => 'inprogress',
        'restored_by' => $restoredBy,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $mysql = trim(shell_exec('which mysql 2>/dev/null')) ?: '/usr/bin/mysql';

    $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
    file_put_contents($tmpcnf, "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n");

    $cmd = sprintf(
        'gunzip -c %s | %s --defaults-extra-file=%s %s 2>&1',
        escapeshellarg($filepath),
        escapeshellcmd($mysql),
        escapeshellarg($tmpcnf),
        escapeshellarg($dbName)
    );

    exec($cmd, $output, $returnCode);
    @unlink($tmpcnf);

    if ($returnCode === 0) {
        fm_update('restore_history', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ], ['id' => $restoreId]);

        return ['success' => true, 'message' => 'Database restored successfully'];
    }

    fm_update('restore_history', [
        'status' => 'failed',
        'error_message' => implode("\n", $output)
    ], ['id' => $restoreId]);

    return ['success' => false, 'message' => 'Restore failed', 'output' => $output];
}

function fm_get_table_categories() {
    return [
        'Stock' => ['crm_stock', 'crm_stock_logs', 'crm_stock_adjustments', 'crm_stock_transfers'],
        'Leads' => ['crm_leads', 'crm_leads_assigned', 'crm_leads_remarks', 'crm_leads_history'],
        'Sales' => ['crm_sales', 'crm_sales_items', 'crm_sales_payments', 'crm_invoices'],
        'Purchases' => ['crm_purchases', 'crm_purchase_items', 'crm_purchase_payments'],
        'Customers' => ['crm_customers', 'crm_customer_contacts', 'crm_customer_addresses'],
        'Products' => ['crm_products', 'crm_product_variants', 'crm_product_categories'],
        'Users' => ['users', 'Wo_Users', 'user_sessions', 'user_permissions'],
        'FileManager' => ['fm_files', 'fm_user_quotas', 'fm_permissions', 'fm_recycle_bin', 'fm_upload_queue', 'fm_activity_log'],
        'Backups' => ['backup_logs', 'backup_schedules', 'restore_history']
    ];
}

if (!function_exists('fm_restore_selective_tables')) {
    function fm_restore_selective_tables($backupFilepath, $tables, $targetDb = null) {
        if (empty($tables) || !is_array($tables)) {
            return ['success' => false, 'message' => 'No tables specified'];
        }
    
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dbName = $targetDb ?: (getenv('DB_NAME') ?: '');
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    
        if (!file_exists($backupFilepath)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }
    
        $tempExtracted = tempnam(sys_get_temp_dir(), 'restore_') . '.sql';
    
        $isGzipped = preg_match('/\.gz$/i', $backupFilepath);
        if ($isGzipped) {
            exec(sprintf('gunzip -c %s > %s', escapeshellarg($backupFilepath), escapeshellarg($tempExtracted)), $output, $code);
            if ($code !== 0) {
                @unlink($tempExtracted);
                return ['success' => false, 'message' => 'Failed to extract backup file'];
            }
        } else {
            copy($backupFilepath, $tempExtracted);
        }
    
        $tempFiltered = tempnam(sys_get_temp_dir(), 'filtered_') . '.sql';
        $fp = fopen($tempExtracted, 'r');
        $fw = fopen($tempFiltered, 'w');
    
        if (!$fp || !$fw) {
            @unlink($tempExtracted);
            @unlink($tempFiltered);
            return ['success' => false, 'message' => 'Failed to process backup file'];
        }
    
        $inTargetTable = false;
        $currentTable = '';
        $buffer = '';
    
        while (($line = fgets($fp)) !== false) {
            if (preg_match('/^-- Table structure for table [`\'"]?(\w+)[`\'"]?/i', $line, $matches)) {
                $currentTable = $matches[1];
                $inTargetTable = in_array($currentTable, $tables);
                $buffer = $line;
            } elseif (preg_match('/^DROP TABLE IF EXISTS [`\'"]?(\w+)[`\'"]?/i', $line, $matches)) {
                $currentTable = $matches[1];
                $inTargetTable = in_array($currentTable, $tables);
                $buffer = $line;
            } elseif (preg_match('/^CREATE TABLE [`\'"]?(\w+)[`\'"]?/i', $line, $matches)) {
                $currentTable = $matches[1];
                $inTargetTable = in_array($currentTable, $tables);
                $buffer .= $line;
            } elseif (preg_match('/^INSERT INTO [`\'"]?(\w+)[`\'"]?/i', $line, $matches)) {
                $currentTable = $matches[1];
                $inTargetTable = in_array($currentTable, $tables);
                if ($inTargetTable) {
                    fwrite($fw, $buffer);
                    $buffer = '';
                    fwrite($fw, $line);
                }
            } else {
                if ($inTargetTable) {
                    if (!empty($buffer)) {
                        fwrite($fw, $buffer);
                        $buffer = '';
                    }
                    fwrite($fw, $line);
                } else {
                    $buffer .= $line;
                }
            }
        }
    
        fclose($fp);
        fclose($fw);
        @unlink($tempExtracted);
    
        $mysql = trim(shell_exec('which mysql 2>/dev/null')) ?: '/usr/bin/mysql';
        $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
        file_put_contents($tmpcnf, "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n");
    
        $cmd = sprintf(
            '%s --defaults-extra-file=%s %s < %s 2>&1',
            escapeshellcmd($mysql),
            escapeshellarg($tmpcnf),
            escapeshellarg($dbName),
            escapeshellarg($tempFiltered)
        );
    
        exec($cmd, $output, $returnCode);
        @unlink($tmpcnf);
        @unlink($tempFiltered);
    
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => 'Selective restore completed for ' . count($tables) . ' table(s)',
                'tables' => $tables
            ];
        }
    
        return [
            'success' => false,
            'message' => 'Restore failed',
            'output' => implode("\n", $output)
        ];
    }
}

if (!function_exists('fm_restore_by_category')) {
    function fm_restore_by_category($backupFilepath, $categories, $targetDb = null) {
        if (empty($categories) || !is_array($categories)) {
            return ['success' => false, 'message' => 'No categories specified'];
        }
    
        $tableCategories = fm_get_table_categories();
        $tables = [];
    
        foreach ($categories as $category) {
            if (isset($tableCategories[$category])) {
                $tables = array_merge($tables, $tableCategories[$category]);
            }
        }
    
        $tables = array_unique($tables);
    
        if (empty($tables)) {
            return ['success' => false, 'message' => 'No tables found for specified categories'];
        }
    
        return fm_restore_selective_tables($backupFilepath, $tables, $targetDb);
    }
}

if (!function_exists('fm_cleanup_old_backups')) {
    function fm_cleanup_old_backups() {
        $cfg = fm_get_config();
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-' . $cfg['backup_retention_days'] . ' days'));
    
        $oldBackups = fm_query("SELECT * FROM backup_logs WHERE created_at < ? AND status = 'completed'", [$cutoffDate]);
    
        $deleted = 0;
        foreach ($oldBackups as $backup) {
            if ($backup['file_path'] && file_exists($backup['file_path'])) {
                @unlink($backup['file_path']);
                $deleted++;
            }
        }
    
        return ['deleted' => $deleted];
    }
}

// ============================================
// Activity Logging
// ============================================
if (!function_exists('fm_log_activity')) {
    function fm_log_activity($userId, $fileId, $action, $details = []) {
        return fm_insert('fm_activity_log', [
            'user_id' => $userId,
            'file_id' => $fileId,
            'action' => $action,
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// ============================================
// Local File Operations
// ============================================
if (!function_exists('fm_get_local_dir')) {
    function fm_get_local_dir($userId = null) {
        $cfg = fm_get_config();
        $dir = $cfg['local_storage'];

        // If userId is provided and user is not admin, return user-specific directory
        if ($userId !== null && $userId > 0) {
            $isAdmin = (function_exists('Wo_IsAdmin') && Wo_IsAdmin());

            if (!$isAdmin) {
                $dir = $dir . '/storage/' . $userId;
            }
        }

        if (!file_exists($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('fm_get_backup_dir')) {
    function fm_get_backup_dir() {
        $cfg = fm_get_config();
        $dir = $cfg['backup_dir'];
        if (!file_exists($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('fm_save_uploaded_local')) {
    function fm_save_uploaded_local($fileData, $subdir = '', $userId = null) {
        $localDir = fm_get_local_dir($userId);
        if ($subdir !== '') {
            $localDir .= '/' . trim($subdir, '/');
            if (!file_exists($localDir)) {
                @mkdir($localDir, 0755, true);
            }
        }

        if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid file upload'];
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileData['name']);
        $uniqueName = uniqid('file_') . '_' . $safeName;
        $destPath = $localDir . '/' . $uniqueName;

        if (!move_uploaded_file($fileData['tmp_name'], $destPath)) {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }

        return ['success' => true, 'filename' => $uniqueName, 'path' => $destPath];
    }
}

if (!function_exists('fm_list_local_folder')) {
    function fm_list_local_folder($relativePath = '', $userId = null) {
        $baseDir = fm_get_local_dir($userId);
        $fullPath = $relativePath ? $baseDir . '/' . ltrim($relativePath, '/') : $baseDir;

        if (!is_dir($fullPath)) {
            return ['folders' => [], 'files' => []];
        }

        $folders = [];
        $files = [];

        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            // Skip .thumbnails folder
            if ($item === '.thumbnails') continue;

            $itemPath = $fullPath . '/' . $item;
            $relPath = $relativePath ? trim($relativePath, '/') . '/' . $item : $item;

            if (is_dir($itemPath)) {
                $folders[] = [
                    'name' => $item,
                    'path' => $relPath,
                    'mtime' => filemtime($itemPath)
                ];
            } else {
                $fileData = [
                    'name' => $item,
                    'path' => $relPath,
                    'size' => filesize($itemPath),
                    'mtime' => filemtime($itemPath),
                    'r2_uploaded' => 0,
                    'thumbnail' => ''
                ];

                // Check if file exists in database and has R2 status
                $dbFile = fm_query("SELECT id, r2_uploaded, r2_key, thumbnail_generated FROM fm_files WHERE filename = ? OR path = ? LIMIT 1", [$item, $relPath]);
                if (!empty($dbFile)) {
                    $fileData['id'] = $dbFile[0]['id'];
                    $fileData['r2_uploaded'] = (int)$dbFile[0]['r2_uploaded'];
                    $fileData['thumbnail_generated'] = (int)($dbFile[0]['thumbnail_generated'] ?? 0);

                    // Get thumbnail path if available
                    if ($fileData['thumbnail_generated'] == 1) {
                        $thumb = fm_get_file_thumbnail($dbFile[0]['id'], 'medium');
                        if ($thumb && !empty($thumb['thumbnail_path'])) {
                            $fileData['thumbnail'] = $thumb['thumbnail_path'];
                        }
                    }
                } else {
                    // File exists on disk but not in database - try to check R2 directly
                    $cfg = fm_get_config();
                    if (!empty($cfg['r2_key']) && !empty($cfg['r2_secret'])) {
                        $remoteKey = 'files/' . $relPath;
                        if (fm_check_r2_exists($remoteKey)) {
                            $fileData['r2_uploaded'] = 1;
                        }
                    }
                }

                $files[] = $fileData;
            }
        }

        return ['folders' => $folders, 'files' => $files];
    }
}

if (!function_exists('fm_get_file_info')) {
    function fm_get_file_info($relativePath) {
        $baseDir = fm_get_local_dir();
        $fullPath = $baseDir . '/' . ltrim($relativePath, '/');
    
        if (!file_exists($fullPath)) {
            return null;
        }
    
        return [
            'name' => basename($fullPath),
            'path' => $relativePath,
            'full_path' => $fullPath,
            'size' => filesize($fullPath),
            'mtime' => filemtime($fullPath),
            'is_dir' => is_dir($fullPath)
        ];
    }
}

if (!function_exists('fm_delete_local_recursive')) {
    function fm_delete_local_recursive($relativePath) {
        $baseDir = fm_get_local_dir();
        $fullPath = $baseDir . '/' . ltrim($relativePath, '/');
    
        if (!file_exists($fullPath)) {
            return false;
        }
    
        if (is_dir($fullPath)) {
            $items = scandir($fullPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $itemPath = $fullPath . '/' . $item;
                $itemRelPath = ltrim($relativePath, '/') . '/' . $item;
                fm_delete_local_recursive($itemRelPath);
            }
            return @rmdir($fullPath);
        } else {
            return @unlink($fullPath);
        }
    }
}

// ============================================
// R2 List with Caching
// ============================================
if (!function_exists('fm_list_r2_cached')) {
    function fm_list_r2_cached($prefix = '', $maxAge = 300) {
        $cacheKey = 'r2_list_' . md5($prefix);
        $cached = fm_cache_get($cacheKey);
        if ($cached !== null && (time() - $cached['time']) < $maxAge) {
            return $cached['data'];
        }
    
        $s3 = fm_init_s3();
        if (!$s3) {
            return [];
        }
    
        $cfg = fm_get_config();
        try {
            $result = $s3->listObjectsV2([
                'Bucket' => $cfg['r2_bucket'],
                'Prefix' => $prefix
            ]);
    
            $objects = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $obj) {
                    $objects[] = [
                        'key' => $obj['Key'],
                        'size' => $obj['Size'],
                        'modified' => $obj['LastModified']->format('Y-m-d H:i:s')
                    ];
                }
            }
    
            fm_cache_set($cacheKey, ['time' => time(), 'data' => $objects]);
            return $objects;
        } catch (Exception $e) {
            return [];
        }
    }
}

// ============================================
// Upload Queue Processing
// ============================================
if (!function_exists('fm_get_pending_uploads')) {
    function fm_get_pending_uploads($limit = 100) {
        return fm_query("SELECT * FROM fm_upload_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT ?", [$limit]) ?: [];
    }
}

if (!function_exists('fm_process_upload_queue_worker')) {
    function fm_process_upload_queue_worker($limit = 20) {
        return fm_process_upload_queue($limit);
    }
}

// ============================================
// Helpers
// ============================================
/**
 * Check if shell commands are usable on this host.
 * We do a safe, non-destructive test.
 */
if (!function_exists('fm_can_use_shell')) {
    function fm_can_use_shell()
    {
        if (!function_exists('shell_exec') || !is_callable('shell_exec')) {
            return false;
        }
        // Some hosts expose shell_exec but it's disabled. safe test:
        $test = @shell_exec('echo SHELL_OK 2>/dev/null');
        return (is_string($test) && strpos($test, 'SHELL_OK') !== false);
    }
}

/**
 * Try to find a binary using 'which' (if shell available) or fallback to common paths.
 */
if (!function_exists('fm_find_binary')) {
    function fm_find_binary($name, $common_paths = [])
    {
        if (fm_can_use_shell()) {
            $which = trim(@shell_exec("which " . escapeshellarg($name) . " 2>/dev/null"));
            if ($which !== '') return $which;
        }
        foreach ($common_paths as $p) {
            if (file_exists($p) && is_executable($p)) return $p;
        }
        return null;
    }
}

// ============================================
// PHP fallback: create DB dump without shell
// (streaming friendly, gzipped .sql.gz)
// ============================================
if (!function_exists('fm_create_db_dump_php')) {
    function fm_create_db_dump_php($prefix = 'db_backup')
    {
        global $db;
        $cfg = fm_get_config();
        $backupDir = rtrim($cfg['backup_dir'], '/');
        @mkdir($backupDir, 0755, true);
    
        // Prepare file names
        $timestamp = date('Ymd_His');
        $filename = "{$prefix}_{$timestamp}.sql.gz";
        $filepath = $backupDir . '/' . $filename;
    
        // Get mysqli
        $mysqli = null;
        global $sqlConnect;

        if (isset($db) && method_exists($db, 'mysqli')) {
            $mysqli = $db->mysqli();
        } elseif (isset($sqlConnect) && $sqlConnect instanceof mysqli) {
            $mysqli = $sqlConnect;
        }

        if (!isset($mysqli) || !$mysqli) {
            return ['success' => false, 'message' => 'No mysqli connection available for PHP fallback'];
        }
    
        // Temporary plain SQL file then gzip it streaming
        $tmpFile = tempnam(sys_get_temp_dir(), 'dbdump_') . '.sql';
        $fh = fopen($tmpFile, 'w');
        if (!$fh) {
            return ['success' => false, 'message' => 'Unable to create temporary file for dump'];
        }
    
        $dbName = (isset($db) && method_exists($db, 'getDbName')) ? $db->getDbName() : (getenv('DB_NAME') ?: '');
        if ($dbName) fwrite($fh, "-- Database: `{$dbName}`\n");
        fwrite($fh, "-- Dump time: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");
    
        // Get tables
        $tables = [];
        $res = $mysqli->query("SHOW TABLES");
        if ($res) {
            while ($row = $res->fetch_array()) {
                $tables[] = $row[0];
            }
            $res->free();
        }
    
        foreach ($tables as $table) {
            // Structure
            if ($r = $mysqli->query("SHOW CREATE TABLE `{$table}`")) {
                $row = $r->fetch_assoc();
                $create = isset($row['Create Table']) ? $row['Create Table'] : (array_values($row)[1] ?? '');
                fwrite($fh, "-- -----------------------------\n");
                fwrite($fh, "-- Table structure for `{$table}`\n");
                fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fh, $create . ";\n\n");
                $r->free();
            }
    
            // Data (stream)
            $res2 = $mysqli->query("SELECT * FROM `{$table}`", MYSQLI_USE_RESULT);
            if ($res2) {
                $firstRow = $res2->fetch_assoc();
                if ($firstRow !== null) {
                    $cols = array_keys($firstRow);
                    fwrite($fh, "-- Data for table `{$table}`\n");
                    fwrite($fh, "INSERT INTO `{$table}` (`" . implode("`,`", $cols) . "`) VALUES\n");
                    $vals = array_map(function ($v) use ($mysqli) {
                        if ($v === null) return 'NULL';
                        return "'" . $mysqli->real_escape_string($v) . "'";
                    }, array_values($firstRow));
                    fwrite($fh, "(" . implode(", ", $vals) . ")\n");
                    while ($row = $res2->fetch_assoc()) {
                        $vals = array_map(function ($v) use ($mysqli) {
                            if ($v === null) return 'NULL';
                            return "'" . $mysqli->real_escape_string($v) . "'";
                        }, array_values($row));
                        fwrite($fh, ",(" . implode(", ", $vals) . ")\n");
                    }
                    fwrite($fh, ";\n\n");
                }
                $res2->close();
            }
        }
    
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    
        // gzip the temp file to target path (stream)
        $in = fopen($tmpFile, 'rb');
        if (!$in) {
            @unlink($tmpFile);
            return ['success' => false, 'message' => 'Unable to read temp dump file'];
        }
        $out = gzopen($filepath, 'wb9');
        if (!$out) {
            fclose($in);
            @unlink($tmpFile);
            return ['success' => false, 'message' => 'Unable to create gzip backup file'];
        }
        while (!feof($in)) {
            $chunk = fread($in, 262144);
            gzwrite($out, $chunk);
        }
        fclose($in);
        gzclose($out);
        @unlink($tmpFile);
    
        if (file_exists($filepath)) {
            return ['success' => true, 'filename' => $filename, 'path' => $filepath, 'size' => filesize($filepath)];
        }
        return ['success' => false, 'message' => 'Failed to write PHP-mode backup file'];
    }
}

// ============================================
// PHP fallback: create single table dump
// ============================================
if (!function_exists('fm_create_table_dump_php')) {
    function fm_create_table_dump_php($tableName, $prefix = 'table_backup')
    {
        global $db;
        $cfg = fm_get_config();
        $backupDir = rtrim($cfg['backup_dir'], '/');
        @mkdir($backupDir, 0755, true);
    
        if (empty($tableName)) {
            return ['success' => false, 'message' => 'Table name is required'];
        }
    
        $mysqli = (isset($db) && method_exists($db, 'mysqli')) ? $db->mysqli() : null;
        if (!$mysqli) return ['success' => false, 'message' => 'No mysqli connection available'];
    
        $timestamp = date('Ymd_His');
        $filename = "{$prefix}_{$tableName}_{$timestamp}.sql.gz";
        $filepath = $backupDir . '/' . $filename;
    
        $tmpFile = tempnam(sys_get_temp_dir(), 'tabledump_') . '.sql';
        $fh = fopen($tmpFile, 'w');
        if (!$fh) return ['success' => false, 'message' => 'Unable to create temporary file for table dump'];
    
        fwrite($fh, "-- Table: `{$tableName}`\n");
        fwrite($fh, "-- Dump time: " . date('Y-m-d H:i:s') . "\n\n");
    
        // Structure
        $r = $mysqli->query("SHOW CREATE TABLE `{$tableName}`");
        if ($r) {
            $row = $r->fetch_assoc();
            $create = isset($row['Create Table']) ? $row['Create Table'] : (array_values($row)[1] ?? '');
            fwrite($fh, "DROP TABLE IF EXISTS `{$tableName}`;\n");
            fwrite($fh, $create . ";\n\n");
            $r->free();
        } else {
            fclose($fh);
            @unlink($tmpFile);
            return ['success' => false, 'message' => 'Table does not exist or cannot fetch structure'];
        }
    
        // Data
        $res2 = $mysqli->query("SELECT * FROM `{$tableName}`", MYSQLI_USE_RESULT);
        if ($res2) {
            $firstRow = $res2->fetch_assoc();
            if ($firstRow !== null) {
                $cols = array_keys($firstRow);
                fwrite($fh, "INSERT INTO `{$tableName}` (`" . implode("`,`", $cols) . "`) VALUES\n");
                $vals = array_map(function ($v) use ($mysqli) {
                    if ($v === null) return 'NULL';
                    return "'" . $mysqli->real_escape_string($v) . "'";
                }, array_values($firstRow));
                fwrite($fh, "(" . implode(", ", $vals) . ")\n");
                while ($row = $res2->fetch_assoc()) {
                    $vals = array_map(function ($v) use ($mysqli) {
                        if ($v === null) return 'NULL';
                        return "'" . $mysqli->real_escape_string($v) . "'";
                    }, array_values($row));
                    fwrite($fh, ",(" . implode(", ", $vals) . ")\n");
                }
                fwrite($fh, ";\n\n");
            }
            $res2->close();
        }
        fclose($fh);
    
        // gzip
        $in = fopen($tmpFile, 'rb');
        if (!$in) { @unlink($tmpFile); return ['success' => false, 'message' => 'Unable to read temp table dump file']; }
        $out = gzopen($filepath, 'wb9');
        if (!$out) { fclose($in); @unlink($tmpFile); return ['success' => false, 'message' => 'Unable to create gzip file']; }
        while (!feof($in)) {
            $chunk = fread($in, 262144);
            gzwrite($out, $chunk);
        }
        fclose($in);
        gzclose($out);
        @unlink($tmpFile);
    
        if (file_exists($filepath)) {
            return ['success' => true, 'filename' => $filename, 'path' => $filepath, 'size' => filesize($filepath)];
        }
        return ['success' => false, 'message' => 'Failed to create table backup'];
    }
}

// ============================================
// PHP fallback: restore from .sql.gz
// (uses mysqli multi_query in chunks)
// ============================================
if (!function_exists('fm_restore_sql_gz_local_php')) {
    function fm_restore_sql_gz_local_php($filepath, $targetDb = null)
    {
        global $db;
        if (!file_exists($filepath)) return ['success' => false, 'message' => 'Backup file not found'];
    
        $mysqli = (isset($db) && method_exists($db, 'mysqli')) ? $db->mysqli() : null;
        if (!$mysqli) return ['success' => false, 'message' => 'No mysqli connection available for PHP restore'];
    
        $dbName = $targetDb ?: (isset($db) && method_exists($db, 'getDbName') ? $db->getDbName() : (getenv('DB_NAME') ?: ''));
        if (!$dbName) return ['success' => false, 'message' => 'Target database not specified'];
    
        $gz = gzopen($filepath, 'rb');
        if (!$gz) return ['success' => false, 'message' => 'Unable to open gzip file'];
    
        $sql = '';
        $errors = [];
        while (!gzeof($gz)) {
            $chunk = gzread($gz, 262144);
            if ($chunk === false) break;
            $sql .= $chunk;
    
            if (strlen($sql) > 1024 * 1024) {
                $parts = explode(";\n", $sql);
                $sql = array_pop($parts);
                $toRun = implode(";\n", $parts);
                if (trim($toRun) !== '') {
                    $toRun = "USE `" . $mysqli->real_escape_string($dbName) . "`;\n" . $toRun . ";\n";
                    if (!$mysqli->multi_query($toRun)) {
                        $errors[] = $mysqli->error;
                        while ($mysqli->more_results() && $mysqli->next_result()) { /* consume */ }
                    } else {
                        do {
                            if ($res = $mysqli->store_result()) { $res->free(); }
                        } while ($mysqli->more_results() && $mysqli->next_result());
                    }
                }
            }
        }
        gzclose($gz);
    
        if (trim($sql) !== '') {
            $toRun = "USE `" . $mysqli->real_escape_string($dbName) . "`;\n" . $sql;
            if (substr(trim($toRun), -1) !== ';') $toRun .= ';';
            if (!$mysqli->multi_query($toRun)) {
                $errors[] = $mysqli->error;
            } else {
                do { if ($res = $mysqli->store_result()) { $res->free(); } } while ($mysqli->more_results() && $mysqli->next_result());
            }
        }
    
        if (empty($errors)) return ['success' => true, 'message' => 'Database restored successfully (PHP mode)'];
        return ['success' => false, 'message' => 'Restore completed with errors', 'errors' => $errors];
    }
}

// ============================================
// Main functions: attempt shell mode then fallback to PHP
// ============================================

if (!function_exists('fm_create_db_dump')) {
    function fm_create_db_dump($prefix = 'db_backup') {
        $cfg = fm_get_config();
        @mkdir($cfg['backup_dir'], 0755, true);
    
        // Shell path / binary detection
        $shell_ok = fm_can_use_shell();
        $mysqldump = fm_find_binary('mysqldump', ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump']);
    
        // Env creds (used for shell path)
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dbName = getenv('DB_NAME') ?: '';
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    
        // If shell available and mysqldump present AND DB_NAME configured -> shell route
        if ($shell_ok && $mysqldump && $dbName) {
            $timestamp = date('Ymd_His');
            $filename = "{$prefix}_{$timestamp}.sql.gz";
            $filepath = rtrim($cfg['backup_dir'], '/') . '/' . $filename;
    
            $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
            file_put_contents($tmpcnf, "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n");
    
            $cmd = sprintf(
                '%s --defaults-extra-file=%s --single-transaction --quick --lock-tables=false %s 2>&1 | gzip -c > %s',
                escapeshellcmd($mysqldump),
                escapeshellarg($tmpcnf),
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );
    
            $output = [];
            @exec($cmd, $output, $returnCode);
            @unlink($tmpcnf);
    
            if (isset($returnCode) && $returnCode === 0 && file_exists($filepath)) {
                return ['success' => true, 'filename' => $filename, 'path' => $filepath, 'size' => filesize($filepath), 'mode' => 'shell'];
            } else {
                // Fall through to PHP fallback (but capture shell message)
                $shellErr = implode("\n", (array)$output);
                $phpResult = fm_create_db_dump_php($prefix);
                if (isset($phpResult['success']) && $phpResult['success']) {
                    $phpResult['mode'] = 'php_fallback';
                    $phpResult['shell_error'] = $shellErr;
                } else {
                    $phpResult['mode'] = 'php_fallback';
                    $phpResult['shell_error'] = $shellErr;
                }
                return $phpResult;
            }
        }
    
        // Shell not available or mysqldump not found => PHP fallback
        return fm_create_db_dump_php($prefix);
    }
}

if (!function_exists('fm_restore_sql_gz_local')) {
    function fm_create_table_dump($tableName, $prefix = 'table_backup') {
        $cfg = fm_get_config();
        @mkdir($cfg['backup_dir'], 0755, true);
    
        $shell_ok = fm_can_use_shell();
        $mysqldump = fm_find_binary('mysqldump', ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump']);
    
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dbName = getenv('DB_NAME') ?: '';
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    
        if ($shell_ok && $mysqldump && $dbName && $tableName) {
            $timestamp = date('Ymd_His');
            $filename = "{$prefix}_{$tableName}_{$timestamp}.sql.gz";
            $filepath = rtrim($cfg['backup_dir'], '/') . '/' . $filename;
    
            $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
            file_put_contents($tmpcnf, "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n");
    
            $cmd = sprintf(
                '%s --defaults-extra-file=%s --single-transaction %s %s 2>&1 | gzip -c > %s',
                escapeshellcmd($mysqldump),
                escapeshellarg($tmpcnf),
                escapeshellarg($dbName),
                escapeshellarg($tableName),
                escapeshellarg($filepath)
            );
    
            $output = [];
            @exec($cmd, $output, $returnCode);
            @unlink($tmpcnf);
    
            if (isset($returnCode) && $returnCode === 0 && file_exists($filepath)) {
                return ['success' => true, 'filename' => $filename, 'path' => $filepath, 'size' => filesize($filepath), 'mode' => 'shell'];
            } else {
                // Fall back to PHP table dump
                $shellErr = implode("\n", (array)$output);
                $phpResult = fm_create_table_dump_php($tableName, $prefix);
                $phpResult['mode'] = 'php_fallback';
                $phpResult['shell_error'] = $shellErr;
                return $phpResult;
            }
        }
    
        return fm_create_table_dump_php($tableName, $prefix);
    }
}

if (!function_exists('fm_restore_sql_gz_local')) {
    function fm_restore_sql_gz_local($filepath, $targetDb = null) {
        $shell_ok = fm_can_use_shell();
        $mysql = fm_find_binary('mysql', ['/usr/bin/mysql', '/usr/local/bin/mysql']);
        $gunzip = fm_find_binary('gunzip', ['/bin/gunzip', '/usr/bin/gunzip', '/usr/bin/gzip']);
    
        // Shell path DB creds
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $dbName = $targetDb ?: (getenv('DB_NAME') ?: '');
    
        // If shell available and mysql binary exists and dbName present -> shell restore
        if ($shell_ok && $mysql && $dbName && file_exists($filepath)) {
            $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
            file_put_contents($tmpcnf, "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n");
    
            // Prefer gunzip if available, else use gzip -dc or gunzip -c
            $decompress_cmd = $gunzip ? escapeshellcmd($gunzip) . ' -c ' : 'gzip -dc';
    
            $cmd = sprintf(
                '%s %s | %s --defaults-extra-file=%s %s 2>&1',
                $decompress_cmd,
                escapeshellarg($filepath),
                escapeshellcmd($mysql),
                escapeshellarg($tmpcnf),
                escapeshellarg($dbName)
            );
    
            $output = [];
            @exec($cmd, $output, $returnCode);
            @unlink($tmpcnf);
    
            if (isset($returnCode) && $returnCode === 0) {
                return ['success' => true, 'message' => 'Database restored successfully', 'mode' => 'shell'];
            } else {
                // Fall back to PHP restore
                $shellErr = implode("\n", (array)$output);
                $phpResult = fm_restore_sql_gz_local_php($filepath, $targetDb);
                $phpResult['mode'] = 'php_fallback';
                $phpResult['shell_error'] = $shellErr;
                return $phpResult;
            }
        }
    
        // Otherwise use PHP restore
        return fm_restore_sql_gz_local_php($filepath, $targetDb);
    }
}

if (!function_exists('fm_restore_selective_tables')) {
    function fm_restore_selective_tables($filepath, $tableNames, $targetDb = null) {
        global $db;
        if (!file_exists($filepath)) return ['success' => false, 'message' => 'Backup file not found'];
    
        $mysqli = (isset($db) && method_exists($db, 'mysqli')) ? $db->mysqli() : null;
        if (!$mysqli) return ['success' => false, 'message' => 'No mysqli connection available'];
    
        $dbName = $targetDb ?: (isset($db) && method_exists($db, 'getDbName') ? $db->getDbName() : (getenv('DB_NAME') ?: ''));
        if (!$dbName) return ['success' => false, 'message' => 'Target database not specified'];
    
        $gz = gzopen($filepath, 'rb');
        if (!$gz) return ['success' => false, 'message' => 'Unable to open backup file'];
    
        $currentTable = null;
        $inTargetTable = false;
        $tableData = [];
        $buffer = '';
    
        while (!gzeof($gz)) {
            $line = gzgets($gz);
            if ($line === false) break;
    
            $buffer .= $line;
    
            if (preg_match('/^DROP TABLE IF EXISTS `([^`]+)`/', $line, $matches)) {
                $currentTable = $matches[1];
                $inTargetTable = in_array($currentTable, $tableNames);
                if ($inTargetTable) {
                    $tableData[$currentTable] = $line;
                }
            } elseif ($inTargetTable) {
                $tableData[$currentTable] .= $line;
            }
    
            if (strlen($buffer) > 1024 * 1024) {
                $buffer = '';
            }
        }
        gzclose($gz);
    
        $errors = [];
        foreach ($tableData as $table => $sql) {
            $sql = "USE `" . $mysqli->real_escape_string($dbName) . "`;\n" . $sql;
            if (!$mysqli->multi_query($sql)) {
                $errors[] = "Failed to restore table {$table}: " . $mysqli->error;
                while ($mysqli->more_results() && $mysqli->next_result()) {}
            } else {
                do {
                    if ($res = $mysqli->store_result()) { $res->free(); }
                } while ($mysqli->more_results() && $mysqli->next_result());
            }
        }
    
        if (empty($errors)) {
            return ['success' => true, 'message' => 'Selected tables restored successfully'];
        }
        return ['success' => false, 'message' => 'Restore completed with errors', 'errors' => $errors];
    }
}

// ============================================
// retention enforcement (unchanged behavior)
// ============================================
if (!function_exists('fm_enforce_retention')) {
    function fm_enforce_retention() {
        $cfg = fm_get_config();
        $backupDir = $cfg['backup_dir'];
        $retentionDays = isset($cfg['backup_retention_days']) ? (int)$cfg['backup_retention_days'] : 30;
        $cutoffTime = time() - ($retentionDays * 86400);
    
        $deleted = 0;
        if (is_dir($backupDir)) {
            $files = scandir($backupDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $filePath = $backupDir . '/' . $file;
                if (is_file($filePath) && filemtime($filePath) < $cutoffTime) {
                    if (@unlink($filePath)) {
                        $deleted++;
                    }
                }
            }
        }
    
        // attempt to clear recycle bin / other housekeeping
        if (function_exists('fm_empty_recycle_bin_auto')) {
            fm_empty_recycle_bin_auto();
        }
    
        return ['deleted_backups' => $deleted];
    }
}


// ============================================
// Simple Cache System
// ============================================
$_FM_CACHE = [];

if (!function_exists('fm_cache_get')) {
    function fm_cache_get($key) {
        global $_FM_CACHE;
        return $_FM_CACHE[$key] ?? null;
    }
}

if (!function_exists('fm_cache_set')) {
    function fm_cache_set($key, $value) {
        global $_FM_CACHE;
        $_FM_CACHE[$key] = $value;
    }
}

if (!function_exists('fm_cache_delete')) {
    function fm_cache_delete($key) {
        global $_FM_CACHE;
        unset($_FM_CACHE[$key]);
    }
}

// ============================================
// File URL Helper
// ============================================
if (!function_exists('fm_stream_file_download')) {
    function fm_get_file_url($relativePath) {
        $cfg = fm_get_config();
        $baseDir = fm_get_local_dir();
        $fullPath = $baseDir . '/' . ltrim($relativePath, '/');
    
        if (!file_exists($fullPath)) {
            return ['location' => 'none', 'url' => null];
        }
    
        $remoteKey = 'files/' . ltrim($relativePath, '/');
    
        $fileRecord = fm_query("SELECT r2_uploaded, r2_key FROM fm_files WHERE filename = ? LIMIT 1", [basename($relativePath)]);
    
        if (!empty($fileRecord) && $fileRecord[0]['r2_uploaded'] == 1 && !empty($fileRecord[0]['r2_key'])) {
            if (!empty($cfg['r2_domain'])) {
                $cdnUrl = rtrim($cfg['r2_domain'], '/') . '/' . ltrim($fileRecord[0]['r2_key'], '/');
                return ['location' => 'r2', 'url' => $cdnUrl];
            }
        }
    
        return ['location' => 'local', 'url' => null];
    }
}

if (!function_exists('fm_stream_file_download')) {
    function fm_stream_file_download($fullPath, $downloadName = '') {
        if (!file_exists($fullPath)) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
    
        $fileName = $downloadName ?: basename($fullPath);
    
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
    
        readfile($fullPath);
        exit;
    }
}

if (!function_exists('fm_sync_user_quota')) {
    function fm_sync_user_quota($userId) {
        $baseDir = fm_get_local_dir();
        $totalSize = 0;
    
        $files = fm_query("SELECT filename, size FROM fm_files WHERE user_id = ? AND is_deleted = 0", [$userId]);
        foreach ($files as $file) {
            $path = $baseDir . '/' . $file['filename'];
            if (file_exists($path)) {
                $totalSize += filesize($path);
            }
        }
    
        fm_update('fm_user_quotas', [
            'used_bytes' => $totalSize,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['user_id' => $userId]);
    
        return $totalSize;
    }
}

if (!function_exists('fm_calculate_total_storage')) {
    function fm_calculate_total_storage() {
        $baseDir = fm_get_local_dir();
        $totalSize = 0;
    
        if (is_dir($baseDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
    
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                }
            }
        }
    
        return $totalSize;
    }
}

if (!function_exists('fm_format_bytes')) {
    function fm_format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Calculate size
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// ============================================
// Error Logging
// ============================================
if (!function_exists('fm_log_error')) {
    function fm_log_error($message, $context = []) {
        $logDir = __DIR__ . '/../../logs';
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/file_manager_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] ERROR: {$message}{$contextStr}\n";

        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Also log to PHP error log if available
        if (function_exists('error_log')) {
            error_log("FM_ERROR: {$message}");
        }
    }
}

if (!function_exists('fm_log_info')) {
    function fm_log_info($message, $context = []) {
        $logDir = __DIR__ . '/../../logs';
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/file_manager_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] INFO: {$message}{$contextStr}\n";

        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('fm_log_debug')) {
    function fm_log_debug($message, $context = []) {
        if (getenv('FM_DEBUG') !== '1') return;

        $logDir = __DIR__ . '/../../logs';
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/file_manager_debug_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] DEBUG: {$message}{$contextStr}\n";

        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
// ============================================
// Thumbnail Generation
// ============================================
if (!function_exists('fm_generate_thumbnail')) {
    function fm_generate_thumbnail($sourceFile, $fileId, $size = 'medium') {
        if (!file_exists($sourceFile)) {
            return ['success' => false, 'error' => 'Source file not found'];
        }

        $ext = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

        if (!in_array($ext, $imageExts)) {
            return ['success' => false, 'error' => 'Not an image file'];
        }

        $sizes = [
            'small' => [100, 100],
            'medium' => [300, 300],
            'large' => [600, 600]
        ];

        if (!isset($sizes[$size])) {
            $size = 'medium';
        }

        list($maxWidth, $maxHeight) = $sizes[$size];

        $config = fm_get_config();
        $thumbDir = $config['local_storage'] . '/.thumbnails';

        if (!file_exists($thumbDir)) {
            @mkdir($thumbDir, 0755, true);
        }

        $thumbFilename = 'thumb_' . $fileId . '_' . $size . '.jpg';
        $thumbPath = $thumbDir . '/' . $thumbFilename;

        try {
            if (extension_loaded('gd')) {
                $image = null;

                switch ($ext) {
                    case 'jpg':
                    case 'jpeg':
                        $image = @imagecreatefromjpeg($sourceFile);
                        break;
                    case 'png':
                        $image = @imagecreatefrompng($sourceFile);
                        break;
                    case 'gif':
                        $image = @imagecreatefromgif($sourceFile);
                        break;
                    case 'webp':
                        if (function_exists('imagecreatefromwebp')) {
                            $image = @imagecreatefromwebp($sourceFile);
                        }
                        break;
                    case 'bmp':
                        if (function_exists('imagecreatefrombmp')) {
                            $image = @imagecreatefrombmp($sourceFile);
                        }
                        break;
                }

                if (!$image) {
                    return ['success' => false, 'error' => 'Could not create image resource'];
                }

                $width = imagesx($image);
                $height = imagesy($image);

                $ratio = min($maxWidth / $width, $maxHeight / $height);
                $newWidth = (int)($width * $ratio);
                $newHeight = (int)($height * $ratio);

                $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                imagejpeg($thumbnail, $thumbPath, 85);
                imagedestroy($image);
                imagedestroy($thumbnail);

                $thumbData = [
                    'file_id' => $fileId,
                    'thumbnail_path' => '.thumbnails/' . $thumbFilename,
                    'thumbnail_size' => $size,
                    'width' => $newWidth,
                    'height' => $newHeight,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $thumbId = fm_insert('fm_thumbnails', $thumbData);

                return [
                    'success' => true,
                    'thumbnail_id' => $thumbId,
                    'path' => $thumbData['thumbnail_path']
                ];
            }

            return ['success' => false, 'error' => 'GD extension not available'];
        } catch (Exception $e) {
            fm_log_error('Thumbnail generation failed', ['file' => $sourceFile, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// ============================================
// File Versioning
// ============================================
if (!function_exists('fm_create_file_version')) {
    function fm_create_file_version($fileId, $userId, $filePath, $comment = null) {
        $fileInfo = fm_query("SELECT * FROM fm_files WHERE id = ? LIMIT 1", [$fileId]);

        if (empty($fileInfo)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        $fileInfo = $fileInfo[0];
        $currentVersion = (int)($fileInfo['current_version'] ?? 1);
        $newVersion = $currentVersion + 1;

        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        $checksum = file_exists($filePath) ? md5_file($filePath) : null;

        $versionData = [
            'file_id' => $fileId,
            'user_id' => $userId,
            'version_number' => $newVersion,
            'filename' => basename($filePath),
            'path' => $filePath,
            'size' => $fileSize,
            'checksum' => $checksum,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
            'is_deletable' => 0
        ];

        $versionId = fm_insert('fm_file_versions', $versionData);

        fm_update('fm_files', [
            'current_version' => $newVersion,
            'version_count' => $newVersion
        ], ['id' => $fileId]);

        return [
            'success' => true,
            'version_id' => $versionId,
            'version_number' => $newVersion
        ];
    }
}

// ============================================
// Check Folder Access
// ============================================
if (!function_exists('fm_check_folder_access')) {
    function fm_check_folder_access($userId, $folderId, $folderType = 'special') {
        global $wo;

        if (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) {
            return true;
        }

        if (function_exists('Wo_IsModerator') && Wo_IsModerator()) {
            return true;
        }

        if ($folderType === 'common') {
            return true;
        }

        $access = fm_query(
            "SELECT id FROM fm_folder_access WHERE folder_id = ? AND folder_type = ? AND user_id = ? LIMIT 1",
            [$folderId, $folderType, $userId]
        );

        return !empty($access);
    }
}

// ============================================
// Get User's Accessible Folders
// ============================================
if (!function_exists('fm_get_user_folders')) {
    function fm_get_user_folders($userId, $isAdmin = false) {
        $folders = [
            'common' => [],
            'special' => [],
            'user' => []
        ];

        $folders['common'] = fm_query("SELECT * FROM fm_common_folders WHERE is_active = 1 ORDER BY sort_order ASC") ?: [];

        if ($isAdmin) {
            $folders['special'] = fm_query("SELECT * FROM fm_special_folders WHERE is_active = 1 ORDER BY sort_order ASC") ?: [];

            $allUsers = fm_query("SELECT DISTINCT user_id FROM fm_files WHERE user_id > 0");
            foreach ($allUsers as $user) {
                $folders['user'][] = [
                    'id' => $user['user_id'],
                    'type' => 'user'
                ];
            }
        } else {
            $folders['special'] = fm_query("
                SELECT sf.* FROM fm_special_folders sf
                INNER JOIN fm_folder_access fa ON fa.folder_id = sf.id AND fa.folder_type = 'special'
                WHERE sf.is_active = 1 AND fa.user_id = ?
                ORDER BY sf.sort_order ASC
            ", [$userId]) ?: [];
        }

        return $folders;
    }
}

// ============================================
// Move File to Recycle Bin
// ============================================
if (!function_exists('fm_move_to_recycle_bin')) {
    function fm_move_to_recycle_bin($fileId, $userId) {
        $file = fm_query("SELECT * FROM fm_files WHERE id = ? LIMIT 1", [$fileId]);

        if (empty($file)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        $file = $file[0];
        $config = fm_get_config();
        $retentionDays = $config['recycle_retention_days'];
        $autoDeleteAt = date('Y-m-d H:i:s', strtotime("+{$retentionDays} days"));

        $recycleData = [
            'file_id' => $fileId,
            'user_id' => $file['user_id'],
            'original_path' => $file['path'],
            'original_filename' => $file['original_filename'] ?? $file['filename'],
            'size' => $file['size'],
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $userId,
            'auto_delete_at' => $autoDeleteAt,
            'can_restore' => 1
        ];

        fm_insert('fm_recycle_bin', $recycleData);

        fm_update('fm_files', [
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $userId
        ], ['id' => $fileId]);

        fm_update_user_quota($file['user_id'], -$file['size']);

        return ['success' => true];
    }
}

// ============================================
// Calculate Total System Storage
// ============================================
if (!function_exists('fm_calculate_total_storage')) {
    function fm_calculate_total_storage() {
        $result = fm_query("SELECT SUM(used_bytes) as total FROM fm_user_quotas");

        if (empty($result)) {
            return 0;
        }

        return (int)$result[0]['total'];
    }
}

// ============================================
// Get File Thumbnail
// ============================================
if (!function_exists('fm_get_file_thumbnail')) {
    function fm_get_file_thumbnail($fileId, $size = 'medium') {
        $thumb = fm_query(
            "SELECT * FROM fm_thumbnails WHERE file_id = ? AND thumbnail_size = ? LIMIT 1",
            [$fileId, $size]
        );

        if (empty($thumb)) {
            return null;
        }

        return $thumb[0];
    }
}

// ============================================
// Check if File is on R2
// ============================================
if (!function_exists('fm_is_file_on_r2')) {
    function fm_is_file_on_r2($fileId) {
        $file = fm_query("SELECT r2_uploaded FROM fm_files WHERE id = ? LIMIT 1", [$fileId]);

        if (empty($file)) {
            return false;
        }

        return (int)$file[0]['r2_uploaded'] === 1;
    }
}

// ============================================
// Check if file exists on R2
// ============================================
if (!function_exists('fm_check_r2_exists')) {
    function fm_check_r2_exists($remoteKey) {
        $s3 = fm_init_s3();
        if (!$s3) return false;

        $cfg = fm_get_config();
        try {
            $s3->headObject([
                'Bucket' => $cfg['r2_bucket'],
                'Key' => $remoteKey
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// ============================================
// Sync R2 status for all files
// ============================================
if (!function_exists('fm_sync_all_r2_status')) {
    function fm_sync_all_r2_status($limit = 100) {
        $files = fm_query("SELECT id, path, filename FROM fm_files WHERE r2_uploaded = 0 AND is_deleted = 0 LIMIT ?", [$limit]);

        $updated = 0;
        foreach ($files as $file) {
            $remoteKey = 'files/' . ltrim($file['path'], '/');
            if (fm_check_r2_exists($remoteKey)) {
                fm_update('fm_files', [
                    'r2_uploaded' => 1,
                    'r2_key' => $remoteKey,
                    'r2_uploaded_at' => date('Y-m-d H:i:s')
                ], ['id' => $file['id']]);
                $updated++;
            }
        }

        return $updated;
    }
}

// ============================================
// Storage Management Functions
// ============================================

if (!function_exists('fm_create_user_storage_structure')) {
    function fm_create_user_storage_structure($userId) {
        $cfg = fm_get_config();
        $baseDir = $cfg['local_storage'];
        $userStoragePath = "Storage/{$userId}";
        $fullPath = $baseDir . '/' . $userStoragePath;

        if (!is_dir($fullPath)) {
            if (!@mkdir($fullPath, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create user storage directory'];
            }
        }

        $defaultSubfolders = ['Documents', 'Images', 'Videos', 'Downloads', 'Archives'];
        $setting = fm_query("SELECT setting_value FROM fm_system_settings WHERE setting_key = 'default_user_subfolders' LIMIT 1");

        if (!empty($setting) && !empty($setting[0]['setting_value'])) {
            $customFolders = json_decode($setting[0]['setting_value'], true);
            if (is_array($customFolders)) {
                $defaultSubfolders = $customFolders;
            }
        }

        foreach ($defaultSubfolders as $subfolder) {
            $subfolderPath = $fullPath . '/' . $subfolder;
            if (!is_dir($subfolderPath)) {
                @mkdir($subfolderPath, 0755, true);
            }

            fm_query(
                "INSERT IGNORE INTO fm_folder_structure (user_id, folder_name, folder_path, folder_type, is_default, created_at) VALUES (?, ?, ?, 'user', 1, NOW())",
                [$userId, $subfolder, "{$userStoragePath}/{$subfolder}"]
            );
        }

        fm_query(
            "INSERT IGNORE INTO fm_user_storage_tracking (user_id, created_at) VALUES (?, NOW())",
            [$userId]
        );

        return ['success' => true, 'path' => $userStoragePath];
    }
}

if (!function_exists('fm_get_user_storage_usage')) {
    function fm_get_user_storage_usage($userId) {
        $tableExists = fm_query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_NAME = 'fm_user_storage_tracking' AND TABLE_SCHEMA = DATABASE() LIMIT 1");
        if (empty($tableExists)) {
            $quota = fm_get_user_quota($userId);
            return [
                'user_id' => $userId,
                'used_bytes' => (int)($quota['used'] ?? 0),
                'quota_bytes' => (int)($quota['quota'] ?? fm_get_config()['default_quota']),
                'total_files' => 0,
                'total_folders' => 0,
                'r2_uploaded_bytes' => 0,
                'local_only_bytes' => 0,
                'usage_percentage' => 0,
                'used_formatted' => fm_format_bytes_enhanced($quota['used'] ?? 0),
                'quota_formatted' => fm_format_bytes_enhanced($quota['quota'] ?? fm_get_config()['default_quota']),
                'last_upload_at' => null
            ];
        }

        $result = fm_query(
            "SELECT * FROM fm_user_storage_tracking WHERE user_id = ? LIMIT 1",
            [$userId]
        );

        if (empty($result)) {
            fm_query(
                "INSERT INTO fm_user_storage_tracking (user_id, created_at) VALUES (?, NOW())",
                [$userId]
            );
            return [
                'user_id' => $userId,
                'used_bytes' => 0,
                'quota_bytes' => fm_get_config()['default_quota'],
                'total_files' => 0,
                'total_folders' => 0,
                'r2_uploaded_bytes' => 0,
                'local_only_bytes' => 0,
                'usage_percentage' => 0
            ];
        }

        $data = $result[0];
        $usagePercent = $data['quota_bytes'] > 0
            ? round(($data['used_bytes'] / $data['quota_bytes']) * 100, 2)
            : 0;

        return [
            'user_id' => $data['user_id'],
            'used_bytes' => (int)$data['used_bytes'],
            'quota_bytes' => (int)$data['quota_bytes'],
            'total_files' => (int)$data['total_files'],
            'total_folders' => (int)$data['total_folders'],
            'r2_uploaded_bytes' => (int)$data['r2_uploaded_bytes'],
            'local_only_bytes' => (int)$data['local_only_bytes'],
            'usage_percentage' => $usagePercent,
            'used_formatted' => fm_format_bytes_enhanced($data['used_bytes']),
            'quota_formatted' => fm_format_bytes_enhanced($data['quota_bytes']),
            'last_upload_at' => $data['last_upload_at'] ?? null
        ];
    }
}

if (!function_exists('fm_get_global_storage_usage')) {
    function fm_get_global_storage_usage() {
        $tableExists = fm_query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_NAME = 'fm_user_storage_tracking' AND TABLE_SCHEMA = DATABASE() LIMIT 1");
        if (empty($tableExists)) {
            $vpsSetting = fm_query("SELECT setting_value FROM fm_system_settings WHERE setting_key = 'vps_total_storage_bytes' LIMIT 1");
            $vpsTotal = !empty($vpsSetting) ? (int)$vpsSetting[0]['setting_value'] : 64424509440;

            $quotaStats = fm_query("SELECT COUNT(DISTINCT user_id) as total_users, SUM(used_bytes) as total_used FROM fm_user_quotas");
            $totalUsed = !empty($quotaStats) ? (int)($quotaStats[0]['total_used'] ?? 0) : 0;
            $totalUsers = !empty($quotaStats) ? (int)($quotaStats[0]['total_users'] ?? 0) : 0;

            $usagePercent = $vpsTotal > 0 ? round(($totalUsed / $vpsTotal) * 100, 2) : 0;

            return [
                'total_users' => $totalUsers,
                'total_files_count' => 0,
                'total_used_bytes' => $totalUsed,
                'vps_total_bytes' => $vpsTotal,
                'global_usage_percentage' => $usagePercent,
                'total_r2_bytes' => 0,
                'total_local_only_bytes' => $totalUsed,
                'used_formatted' => fm_format_bytes_enhanced($totalUsed),
                'quota_formatted' => fm_format_bytes_enhanced($vpsTotal),
                'available_bytes' => $vpsTotal - $totalUsed,
                'available_formatted' => fm_format_bytes_enhanced($vpsTotal - $totalUsed)
            ];
        }

        // Get VPS total storage limit
        $vpsSetting = fm_query("SELECT setting_value FROM fm_system_settings WHERE setting_key = 'vps_total_storage_bytes' LIMIT 1");
        $vpsTotal = !empty($vpsSetting) ? (int)$vpsSetting[0]['setting_value'] : 64424509440; // 60 GB default

        // Try to get from view first
        $result = fm_query("SELECT * FROM v_global_storage_summary LIMIT 1");

        // If view doesn't exist or is empty, calculate from tracking table or quotas table
        if (empty($result)) {
            // Try fm_user_storage_tracking first
            $stats = fm_query("
                SELECT
                    COUNT(DISTINCT user_id) as total_users,
                    COALESCE(SUM(used_bytes), 0) as total_used_bytes,
                    COALESCE(SUM(total_files), 0) as total_files_count,
                    COALESCE(SUM(r2_uploaded_bytes), 0) as total_r2_bytes,
                    COALESCE(SUM(local_only_bytes), 0) as total_local_only_bytes
                FROM fm_user_storage_tracking
            ");

            // Fallback to fm_user_quotas if tracking table is empty
            if (empty($stats) || (int)($stats[0]['total_users'] ?? 0) === 0) {
                $stats = fm_query("
                    SELECT
                        COUNT(DISTINCT user_id) as total_users,
                        COALESCE(SUM(used_bytes), 0) as total_used_bytes,
                        COALESCE(SUM(total_files), 0) as total_files_count,
                        COALESCE(SUM(r2_uploaded_bytes), 0) as total_r2_bytes,
                        COALESCE(SUM(local_only_bytes), 0) as total_local_only_bytes
                    FROM fm_user_quotas
                ");
            }

            if (empty($stats)) {
                return [
                    'total_users' => 0,
                    'total_files_count' => 0,
                    'total_used_bytes' => 0,
                    'vps_total_bytes' => $vpsTotal,
                    'global_usage_percentage' => 0,
                    'total_r2_bytes' => 0,
                    'total_local_only_bytes' => 0,
                    'used_formatted' => '0 B',
                    'quota_formatted' => fm_format_bytes_enhanced($vpsTotal),
                    'available_bytes' => $vpsTotal,
                    'available_formatted' => fm_format_bytes_enhanced($vpsTotal)
                ];
            }

            $data = $stats[0];
            $totalUsed = (int)($data['total_used_bytes'] ?? 0);
            $usagePercent = $vpsTotal > 0 ? round(($totalUsed / $vpsTotal) * 100, 2) : 0;

            return [
                'total_users' => (int)($data['total_users'] ?? 0),
                'total_files_count' => (int)($data['total_files_count'] ?? 0),
                'total_used_bytes' => $totalUsed,
                'vps_total_bytes' => $vpsTotal,
                'global_usage_percentage' => $usagePercent,
                'total_r2_bytes' => (int)($data['total_r2_bytes'] ?? 0),
                'total_local_only_bytes' => (int)($data['total_local_only_bytes'] ?? 0),
                'used_formatted' => fm_format_bytes_enhanced($totalUsed),
                'quota_formatted' => fm_format_bytes_enhanced($vpsTotal),
                'available_bytes' => max(0, $vpsTotal - $totalUsed),
                'available_formatted' => fm_format_bytes_enhanced(max(0, $vpsTotal - $totalUsed))
            ];
        }

        $data = $result[0];
        return [
            'total_users' => (int)$data['total_users'],
            'total_files_count' => (int)$data['total_files_count'],
            'total_used_bytes' => (int)$data['total_used_bytes'],
            'vps_total_bytes' => (int)$data['vps_total_bytes'],
            'global_usage_percentage' => (float)$data['global_usage_percentage'],
            'total_r2_bytes' => (int)$data['total_r2_bytes'],
            'total_local_only_bytes' => (int)$data['total_local_only_bytes'],
            'used_formatted' => fm_format_bytes_enhanced($data['total_used_bytes']),
            'quota_formatted' => fm_format_bytes_enhanced($data['vps_total_bytes']),
            'available_bytes' => (int)$data['vps_total_bytes'] - (int)$data['total_used_bytes'],
            'available_formatted' => fm_format_bytes_enhanced((int)$data['vps_total_bytes'] - (int)$data['total_used_bytes'])
        ];
    }
}

if (!function_exists('fm_get_all_users_storage_breakdown')) {
    function fm_get_all_users_storage_breakdown($limit = 50, $offset = 0) {
        $result = fm_query(
            "SELECT ust.*, u.username, u.email
             FROM fm_user_storage_tracking ust
             LEFT JOIN Wo_Users u ON ust.user_id = u.user_id
             WHERE ust.used_bytes > 0
             ORDER BY ust.used_bytes DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );

        if (empty($result)) {
            return [];
        }

        $breakdown = [];
        foreach ($result as $row) {
            $usagePercent = $row['quota_bytes'] > 0
                ? round(($row['used_bytes'] / $row['quota_bytes']) * 100, 2)
                : 0;

            $breakdown[] = [
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'] ?? 'Unknown',
                'email' => $row['email'] ?? '',
                'used_bytes' => (int)$row['used_bytes'],
                'quota_bytes' => (int)$row['quota_bytes'],
                'used_formatted' => fm_format_bytes_enhanced($row['used_bytes']),
                'quota_formatted' => fm_format_bytes_enhanced($row['quota_bytes']),
                'usage_percentage' => $usagePercent,
                'total_files' => (int)$row['total_files'],
                'total_folders' => (int)$row['total_folders'],
                'r2_uploaded_bytes' => (int)$row['r2_uploaded_bytes'],
                'local_only_bytes' => (int)$row['local_only_bytes'],
                'last_upload_at' => $row['last_upload_at']
            ];
        }

        return $breakdown;
    }
}

if (!function_exists('fm_calculate_directory_size')) {
    function fm_calculate_directory_size($directory, &$fileCount = 0, &$folderCount = 0) {
        $totalSize = 0;

        if (!is_dir($directory)) {
            return 0;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                // Skip .thumbnails directory
                if (strpos($file->getPathname(), '/.thumbnails') !== false) {
                    continue;
                }

                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                    $fileCount++;
                } elseif ($file->isDir()) {
                    $folderCount++;
                }
            }
        } catch (Exception $e) {
            fm_log_error('Error calculating directory size', ['directory' => $directory, 'error' => $e->getMessage()]);
        }

        return $totalSize;
    }
}

if (!function_exists('fm_update_storage_tracking')) {
    /**
     * Update storage tracking for a user
     * Now uses the efficient fm_recalculate_user_storage from file_manager_storage.php
     * Database triggers handle most updates automatically
     */
    function fm_update_storage_tracking($userId) {
        // Use the new efficient recalculation function
        if (function_exists('fm_recalculate_user_storage')) {
            return fm_recalculate_user_storage($userId);
        }

        // Fallback: Simple quota update if new functions not available
        global $db;
        try {
            $userId = (int)$userId;

            // Calculate totals from fm_files
            $db->where('user_id', $userId);
            $db->where('is_deleted', 0);
            $db->where('is_folder', 0);
            $files = $db->get('fm_files', null, ['size', 'r2_uploaded']);

            $totalSize = 0;
            $r2Size = 0;
            $fileCount = 0;

            if ($files) {
                foreach ($files as $file) {
                    $size = (int)$file->size;
                    $totalSize += $size;
                    if ($file->r2_uploaded == 1) {
                        $r2Size += $size;
                    }
                    $fileCount++;
                }
            }

            // Update fm_user_quotas
            $db->where('user_id', $userId);
            $exists = $db->getOne('fm_user_quotas', 'user_id');

            $data = [
                'used_bytes' => $totalSize,
                'total_files' => $fileCount,
                'r2_uploaded_bytes' => $r2Size,
                'local_only_bytes' => $totalSize - $r2Size,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($exists) {
                $db->where('user_id', $userId);
                return $db->update('fm_user_quotas', $data);
            } else {
                $data['user_id'] = $userId;
                $data['created_at'] = date('Y-m-d H:i:s');
                return $db->insert('fm_user_quotas', $data);
            }
        } catch (Exception $e) {
            error_log("fm_update_storage_tracking: Error - " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('fm_get_common_folders_with_stats')) {
    function fm_get_common_folders_with_stats() {
        // Try view first
        $result = fm_query("SELECT * FROM v_common_folders_summary ORDER BY folder_name ASC");

        // If view doesn't exist, fallback to direct query
        if (empty($result)) {
            $result = fm_query("
                SELECT cf.*,
                    COUNT(DISTINCT f.id) as actual_file_count,
                    COALESCE(SUM(f.size), 0) as actual_size_bytes
                FROM fm_common_folders cf
                LEFT JOIN fm_files f ON f.common_folder_id = cf.id AND f.is_deleted = 0 AND f.is_folder = 0
                WHERE cf.is_active = 1
                GROUP BY cf.id
                ORDER BY cf.sort_order ASC
            ");

            if (empty($result)) {
                return [];
            }
        }

        $folders = [];
        foreach ($result as $row) {
            $folders[] = [
                'id' => (int)$row['id'],
                'folder_name' => $row['folder_name'],
                'folder_key' => $row['folder_key'],
                'folder_path' => $row['folder_path'],
                'total_files' => (int)$row['actual_file_count'],
                'total_size_bytes' => (int)$row['actual_size_bytes'],
                'total_size_formatted' => fm_format_bytes_enhanced($row['actual_size_bytes']),
                'is_active' => (int)$row['is_active'] === 1
            ];
        }

        return $folders;
    }
}

if (!function_exists('fm_get_special_folders_with_stats')) {
    function fm_get_special_folders_with_stats($userId = null) {
        if ($userId === null) {
            // Admin view - try view first
            $result = fm_query("SELECT * FROM v_special_folders_summary ORDER BY folder_name ASC");

            // Fallback to direct query if view doesn't exist
            if (empty($result)) {
                $result = fm_query("
                    SELECT sf.*,
                        COUNT(DISTINCT f.id) as actual_file_count,
                        COALESCE(SUM(f.size), 0) as actual_size_bytes,
                        COUNT(DISTINCT fa.user_id) as total_users_with_access
                    FROM fm_special_folders sf
                    LEFT JOIN fm_files f ON f.special_folder_id = sf.id AND f.is_deleted = 0 AND f.is_folder = 0
                    LEFT JOIN fm_folder_access fa ON fa.folder_id = sf.id AND fa.folder_type = 'special'
                    WHERE sf.is_active = 1
                    GROUP BY sf.id
                    ORDER BY sf.sort_order ASC
                ");
            }
        } else {
            // User-specific view
            $result = fm_query(
                "SELECT sf.*, COUNT(DISTINCT f.id) as actual_file_count, COALESCE(SUM(f.size), 0) as actual_size_bytes
                 FROM fm_special_folders sf
                 INNER JOIN fm_folder_access fa ON fa.folder_id = sf.id AND fa.folder_type = 'special'
                 LEFT JOIN fm_files f ON f.special_folder_id = sf.id AND f.is_deleted = 0 AND f.is_folder = 0
                 WHERE sf.is_active = 1 AND fa.user_id = ?
                 GROUP BY sf.id
                 ORDER BY sf.sort_order ASC",
                [$userId]
            );
        }

        if (empty($result)) {
            return [];
        }

        $folders = [];
        foreach ($result as $row) {
            $folders[] = [
                'id' => (int)$row['id'],
                'folder_name' => $row['folder_name'],
                'folder_key' => $row['folder_key'],
                'folder_path' => $row['folder_path'],
                'total_files' => (int)$row['actual_file_count'],
                'total_size_bytes' => (int)$row['actual_size_bytes'],
                'total_size_formatted' => fm_format_bytes_enhanced($row['actual_size_bytes']),
                'requires_permission' => (int)($row['requires_permission'] ?? 1) === 1,
                'total_users_with_access' => (int)($row['total_users_with_access'] ?? 0),
                'is_active' => (int)$row['is_active'] === 1
            ];
        }

        return $folders;
    }
}

if (!function_exists('fm_check_folder_access')) {
    function fm_check_folder_access($userId, $folderId, $folderType = 'special') {
        global $wo;

        if (function_exists('Wo_IsAdmin') && Wo_IsAdmin()) {
            return true;
        }

        if ($folderType === 'common') {
            return true;
        }

        if ($folderType === 'special') {
            $result = fm_query(
                "SELECT id FROM fm_folder_access WHERE folder_id = ? AND folder_type = 'special' AND user_id = ? LIMIT 1",
                [$folderId, $userId]
            );
            return !empty($result);
        }

        return false;
    }
}

if (!function_exists('fm_format_bytes_enhanced')) {
    function fm_format_bytes_enhanced($bytes, $precision = 2) {
        if ($bytes <= 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = 1024;
        $exp = floor(log($bytes) / log($base));
        $exp = min($exp, count($units) - 1);

        return round($bytes / pow($base, $exp), $precision) . ' ' . $units[$exp];
    }
}

if (!function_exists('fm_get_folder_contents')) {
    function fm_get_folder_contents($userId, $folderType, $folderId = null, $path = '') {
        $isAdmin = function_exists('Wo_IsAdmin') && Wo_IsAdmin();

        if ($folderType === 'user') {
            // For non-admin users, use user-specific base directory
            // For admin, use storage/{userId}
            if ($isAdmin) {
                $userPath = "storage/{$userId}";
                if ($path) {
                    $userPath .= '/' . ltrim($path, '/');
                }
            } else {
                // Non-admin: just use the relative path since fm_get_local_dir handles user isolation
                $userPath = $path;
            }

            $result = fm_list_local_folder($userPath, $userId);
            return $result;
        }

        if ($folderType === 'common' && $folderId) {
            $folder = fm_query(
                "SELECT folder_path FROM fm_common_folders WHERE id = ? AND is_active = 1 LIMIT 1",
                [$folderId]
            );

            if (empty($folder)) {
                return ['folders' => [], 'files' => []];
            }

            $folderPath = $folder[0]['folder_path'];
            if ($path) {
                $folderPath .= '/' . ltrim($path, '/');
            }

            return fm_list_local_folder($folderPath);
        }

        if ($folderType === 'special' && $folderId) {
            if (!$isAdmin && !fm_check_folder_access($userId, $folderId, 'special')) {
                return ['folders' => [], 'files' => [], 'error' => 'Access denied'];
            }

            $folder = fm_query(
                "SELECT folder_path FROM fm_special_folders WHERE id = ? AND is_active = 1 LIMIT 1",
                [$folderId]
            );

            if (empty($folder)) {
                return ['folders' => [], 'files' => []];
            }

            $folderPath = $folder[0]['folder_path'];
            if ($path) {
                $folderPath .= '/' . ltrim($path, '/');
            }

            return fm_list_local_folder($folderPath);
        }

        return ['folders' => [], 'files' => []];
    }
}

if (!function_exists('fm_grant_special_folder_access')) {
    function fm_grant_special_folder_access($folderId, $userId, $permissionLevel = 'view', $grantedBy = 0) {
        $exists = fm_query(
            "SELECT id FROM fm_folder_access WHERE folder_id = ? AND folder_type = 'special' AND user_id = ? LIMIT 1",
            [$folderId, $userId]
        );

        if (!empty($exists)) {
            fm_update('fm_folder_access', [
                'permission_level' => $permissionLevel,
                'granted_by' => $grantedBy
            ], [
                'id' => $exists[0]['id']
            ]);
            return $exists[0]['id'];
        }

        return fm_insert('fm_folder_access', [
            'folder_id' => $folderId,
            'folder_type' => 'special',
            'user_id' => $userId,
            'permission_level' => $permissionLevel,
            'granted_by' => $grantedBy,
            'granted_at' => date('Y-m-d H:i:s')
        ]);
    }
}

if (!function_exists('fm_revoke_special_folder_access')) {
    function fm_revoke_special_folder_access($folderId, $userId) {
        return fm_query(
            "DELETE FROM fm_folder_access WHERE folder_id = ? AND folder_type = 'special' AND user_id = ?",
            [$folderId, $userId]
        );
    }
}
