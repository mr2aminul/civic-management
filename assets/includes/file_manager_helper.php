<?php
/**
 * File Manager & Backup System Helper
 * Advanced features: Drive-like interface, R2 storage, quotas, permissions, recycle bin
 */

if (file_exists(__DIR__ . "/../libraries/aws-sdk-php/vendor/autoload.php")) {
    require_once __DIR__ . "/../libraries/aws-sdk-php/vendor/autoload.php";
} elseif (file_exists(__DIR__ . "/../../vendor/autoload.php")) {
    require_once __DIR__ . "/../../vendor/autoload.php";
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
    global $db, $sqlConnect;
    if (isset($db) && method_exists($db, 'where')) {
        return ['type' => 'joshcam', 'db' => $db];
    }
    if (isset($sqlConnect) && $sqlConnect instanceof mysqli) {
        return ['type' => 'mysqli', 'db' => $sqlConnect];
    }
    return null;
}

function fm_query($sql, $params = []) {
    $conn = fm_get_db();
    if (!$conn) return false;

    if ($conn['type'] === 'mysqli') {
        $mysqli = $conn['db'];
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) return false;

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

        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return ['affected_rows' => $stmt->affected_rows, 'insert_id' => $stmt->insert_id];
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
    return fm_query($sql, $values);
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
// ============================================
function fm_get_user_quota($userId) {
    $result = fm_query("SELECT quota_bytes, used_bytes FROM fm_user_quotas WHERE user_id = ?", [$userId]);
    if (!empty($result)) {
        return ['quota' => (int)$result[0]['quota_bytes'], 'used' => (int)$result[0]['used_bytes']];
    }

    $cfg = fm_get_config();
    fm_insert('fm_user_quotas', [
        'user_id' => $userId,
        'quota_bytes' => $cfg['default_quota'],
        'used_bytes' => 0,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return ['quota' => $cfg['default_quota'], 'used' => 0];
}

function fm_update_user_quota($userId, $deltaBytes) {
    $result = fm_query("SELECT used_bytes FROM fm_user_quotas WHERE user_id = ?", [$userId]);

    if (empty($result)) {
        $cfg = fm_get_config();
        $newUsed = max(0, $deltaBytes);
        fm_insert('fm_user_quotas', [
            'user_id' => $userId,
            'quota_bytes' => $cfg['default_quota'],
            'used_bytes' => $newUsed,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    }

    $newUsed = max(0, (int)$result[0]['used_bytes'] + $deltaBytes);
    return fm_update('fm_user_quotas', [
        'used_bytes' => $newUsed,
        'updated_at' => date('Y-m-d H:i:s')
    ], ['user_id' => $userId]);
}

function fm_check_quota($userId, $requiredBytes) {
    $quota = fm_get_user_quota($userId);
    return ($quota['used'] + $requiredBytes) <= $quota['quota'];
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
    return fm_insert('fm_upload_queue', [
        'file_id' => $fileId,
        'local_path' => $localPath,
        'remote_key' => $remoteKey,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
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

// ============================================
// Activity Logging
// ============================================
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

// ============================================
// Local File Operations
// ============================================
function fm_get_local_dir() {
    $cfg = fm_get_config();
    $dir = $cfg['local_storage'];
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function fm_get_backup_dir() {
    $cfg = fm_get_config();
    $dir = $cfg['backup_dir'];
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function fm_save_uploaded_local($fileData, $subdir = '') {
    $localDir = fm_get_local_dir();
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

function fm_list_local_folder($relativePath = '') {
    $baseDir = fm_get_local_dir();
    $fullPath = $relativePath ? $baseDir . '/' . ltrim($relativePath, '/') : $baseDir;

    if (!is_dir($fullPath)) {
        return ['folders' => [], 'files' => []];
    }

    $folders = [];
    $files = [];

    $items = scandir($fullPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $fullPath . '/' . $item;
        $relPath = $relativePath ? trim($relativePath, '/') . '/' . $item : $item;

        if (is_dir($itemPath)) {
            $folders[] = [
                'name' => $item,
                'path' => $relPath,
                'mtime' => filemtime($itemPath)
            ];
        } else {
            $files[] = [
                'name' => $item,
                'path' => $relPath,
                'size' => filesize($itemPath),
                'mtime' => filemtime($itemPath)
            ];
        }
    }

    return ['folders' => $folders, 'files' => $files];
}

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

// ============================================
// R2 List with Caching
// ============================================
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

// ============================================
// Upload Queue Processing
// ============================================
function fm_get_pending_uploads($limit = 100) {
    return fm_query("SELECT * FROM fm_upload_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT ?", [$limit]) ?: [];
}

function fm_process_upload_queue_worker($limit = 20) {
    return fm_process_upload_queue($limit);
}

// ============================================
// Helpers
// ============================================
/**
 * Check if shell commands are usable on this host.
 * We do a safe, non-destructive test.
 */
function fm_can_use_shell()
{
    if (!function_exists('shell_exec') || !is_callable('shell_exec')) {
        return false;
    }
    // Some hosts expose shell_exec but it's disabled. safe test:
    $test = @shell_exec('echo SHELL_OK 2>/dev/null');
    return (is_string($test) && strpos($test, 'SHELL_OK') !== false);
}

/**
 * Try to find a binary using 'which' (if shell available) or fallback to common paths.
 */
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

// ============================================
// PHP fallback: create DB dump without shell
// (streaming friendly, gzipped .sql.gz)
// ============================================
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
    if (isset($db) && method_exists($db, 'mysqli')) {
        $mysqli = $db->mysqli();
    } elseif (function_exists('mysqli_connect')) {
        $mysqli = mysqli_connect(getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', getenv('DB_NAME') ?: '');
    }

    if (!$mysqli) {
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

// ============================================
// PHP fallback: create single table dump
// ============================================
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

// ============================================
// PHP fallback: restore from .sql.gz
// (uses mysqli multi_query in chunks)
// ============================================
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

// ============================================
// Main functions: attempt shell mode then fallback to PHP
// ============================================

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

// ============================================
// retention enforcement (unchanged behavior)
// ============================================
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


// ============================================
// Simple Cache System
// ============================================
$_FM_CACHE = [];

function fm_cache_get($key) {
    global $_FM_CACHE;
    return $_FM_CACHE[$key] ?? null;
}

function fm_cache_set($key, $value) {
    global $_FM_CACHE;
    $_FM_CACHE[$key] = $value;
}

function fm_cache_delete($key) {
    global $_FM_CACHE;
    unset($_FM_CACHE[$key]);
}

// ============================================
// File URL Helper
// ============================================
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

