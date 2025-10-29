<?php
// xhr/file_manager.php
// AJAX API for file manager & backups

if (!defined('FM_XHR_API')) define('FM_XHR_API', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Basic auth integration
function _fm_is_logged() {
    if (function_exists('Wo_IsLogged')) return Wo_IsLogged();
    if (function_exists('is_logged_in')) return is_logged_in();
    return isset($_SESSION['user_id']);
}

function _fm_is_admin() {
    if (function_exists('Wo_IsAdmin')) return Wo_IsAdmin();
    if (function_exists('is_admin')) return is_admin();
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function _fm_user_id() {
    if (function_exists('Wo_UserId')) return (int)Wo_UserId();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

// Enable detailed error reporting for debugging
if (getenv('FM_DEBUG') === '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Include helper
$helper = __DIR__ . '/../assets/includes/file_manager_helper.php';
if (!file_exists($helper)) {
    $error = 'Helper file not found: ' . $helper;
    error_log($error);
    echo json_encode(['status' => 500, 'error' => $error]);
    exit;
}
require_once $helper;

// Log API call
fm_log_debug('API Call', [
    'action' => $_GET['s'] ?? $_POST['s'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Get action
$action = $_GET['s'] ?? $_POST['s'] ?? '';

try {
    switch ($action) {

        // Ping - Test connectivity and configuration
        case 'ping':
            $r2 = fm_init_s3();
            echo json_encode([
                'status' => 200,
                'r2_enabled' => !empty($r2),
                'local_dir' => fm_get_local_dir()
            ]);
            exit;

        // List local folder contents
        case 'list_local_folder':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $path = trim($_GET['path'] ?? '');
            $result = fm_list_local_folder($path);

            echo json_encode([
                'status' => 200,
                'folders' => $result['folders'],
                'files' => $result['files']
            ]);
            exit;

        // Create new folder
        case 'create_folder':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $path = trim($_POST['path'] ?? '');
            if (empty($path)) {
                echo json_encode(['status' => 400, 'error' => 'Missing path parameter']);
                exit;
            }

            $fullPath = fm_get_local_dir() . '/' . ltrim($path, '/');

            if (file_exists($fullPath)) {
                echo json_encode(['status' => 409, 'error' => 'Folder already exists']);
                exit;
            }

            if (!@mkdir($fullPath, 0755, true)) {
                echo json_encode(['status' => 500, 'error' => 'Failed to create folder']);
                exit;
            }

            echo json_encode(['status' => 200, 'path' => trim($path, '/')]);
            exit;

        // Upload files to local storage
        case 'upload_local':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            if (empty($_FILES)) {
                echo json_encode(['status' => 400, 'error' => 'No files uploaded']);
                exit;
            }

            $subdir = trim($_POST['subdir'] ?? '');
            $userId = _fm_user_id();
            $filesArray = [];

            // Handle multiple file upload formats
            if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
                for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                    $filesArray[] = [
                        'name' => $_FILES['files']['name'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'error' => $_FILES['files']['error'][$i],
                        'size' => $_FILES['files']['size'][$i]
                    ];
                }
            } elseif (isset($_FILES['file'])) {
                $filesArray[] = $_FILES['file'];
            } else {
                foreach ($_FILES as $file) {
                    $filesArray[] = $file;
                }
            }

            $results = [];
            $config = fm_get_config();

            foreach ($filesArray as $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $results[] = [
                        'success' => false,
                        'error' => 'Upload error',
                        'original' => $file['name']
                    ];
                    continue;
                }

                $saveResult = fm_save_uploaded_local($file, $subdir);

                if ($saveResult['success']) {
                    $relativePath = ltrim(($subdir !== '' ? trim($subdir, '/') . '/' : '') . $saveResult['filename'], '/');
                    $fileSize = filesize($saveResult['path']);

                    // Track file in database
                    $fileData = [
                        'user_id' => $userId,
                        'filename' => $saveResult['filename'],
                        'original_filename' => $file['name'],
                        'path' => $relativePath,
                        'file_type' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
                        'mime_type' => $file['type'] ?? 'application/octet-stream',
                        'size' => $fileSize,
                        'is_folder' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $fileId = fm_insert('fm_files', $fileData);

                    // Update user quota
                    fm_update_user_quota($userId, $fileSize);

                    $results[] = [
                        'success' => true,
                        'path' => $relativePath,
                        'size' => $fileSize,
                        'file_id' => $fileId
                    ];

                    // Auto-upload logic
                    $extension = strtolower(pathinfo($saveResult['filename'], PATHINFO_EXTENSION));
                    $shouldAutoUpload = in_array($extension, $config['auto_upload_exts']);

                    foreach ($config['auto_upload_prefixes'] as $prefix) {
                        if (!empty($prefix) && stripos($saveResult['filename'], $prefix) === 0) {
                            $shouldAutoUpload = true;
                            break;
                        }
                    }

                    $remoteKey = 'files/' . $relativePath;

                    if ($shouldAutoUpload) {
                        if (fm_init_s3()) {
                            $uploadResult = fm_upload_to_r2($saveResult['path'], $remoteKey);
                            if ($uploadResult['success'] && $fileId) {
                                fm_update('fm_files', [
                                    'r2_key' => $remoteKey,
                                    'r2_uploaded' => 1,
                                    'r2_uploaded_at' => date('Y-m-d H:i:s')
                                ], ['id' => $fileId]);
                            } else {
                                fm_enqueue_r2_upload($saveResult['path'], $remoteKey, $fileId);
                            }
                        } else {
                            fm_enqueue_r2_upload($saveResult['path'], $remoteKey, $fileId);
                        }
                    } elseif ($fileSize > 20 * 1024 * 1024) {
                        // Files over 20MB get queued
                        fm_enqueue_r2_upload($saveResult['path'], $remoteKey, $fileId);
                    }
                } else {
                    $results[] = [
                        'success' => false,
                        'error' => $saveResult['message']
                    ];
                }
            }

            // Clear cache
            fm_cache_delete('list_local_' . ($subdir ?: 'root'));

            echo json_encode(['status' => 200, 'results' => $results]);
            exit;

        // Download file from local storage
        case 'download_local':
            if (!_fm_is_logged()) {
                header('HTTP/1.1 403 Forbidden');
                echo 'Login required';
                exit;
            }

            $file = $_GET['file'] ?? $_POST['file'] ?? '';
            if (empty($file)) {
                header('HTTP/1.1 400 Bad Request');
                echo 'Missing file parameter';
                exit;
            }

            $baseDir = fm_get_local_dir();
            $backupDir = fm_get_backup_dir();

            $fullPath = $baseDir . '/' . ltrim($file, '/');

            if (!file_exists($fullPath) || !is_file($fullPath)) {
                $fullPath = $backupDir . '/' . basename($file);
            }

            if (!file_exists($fullPath) || !is_file($fullPath)) {
                header('HTTP/1.1 404 Not Found');
                echo 'File not found: ' . $file;
                exit;
            }

            $baseDirReal = realpath($baseDir);
            $backupDirReal = realpath($backupDir);
            $fullPathReal = realpath($fullPath);

            if (strpos($fullPathReal, $baseDirReal) !== 0 && strpos($fullPathReal, $backupDirReal) !== 0) {
                header('HTTP/1.1 403 Forbidden');
                echo 'Access denied';
                exit;
            }

            fm_stream_file_download($fullPath, basename($file));
            exit;

        // Delete file or folder (supports batch delete)
        case 'delete_local':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $userId = _fm_user_id();
            $paths = [];
            if (isset($_POST['paths']) && is_array($_POST['paths'])) {
                $paths = $_POST['paths'];
            } elseif (!empty($_POST['path'])) {
                $paths = [$_POST['path']];
            } elseif (!empty($_POST['file'])) {
                $paths = [$_POST['file']];
            }

            if (empty($paths)) {
                echo json_encode(['status' => 400, 'error' => 'Missing path parameter']);
                exit;
            }

            $results = [];
            $allSuccess = true;
            foreach ($paths as $path) {
                // Get file info before deletion to update quota
                $baseDir = fm_get_local_dir();
                $fullPath = $baseDir . '/' . ltrim($path, '/');
                $fileSize = 0;

                if (file_exists($fullPath) && is_file($fullPath)) {
                    $fileSize = filesize($fullPath);

                    // Mark as deleted in database
                    $fileRecord = fm_query(
                        "SELECT id, user_id, size FROM fm_files WHERE path = ? OR filename = ? LIMIT 1",
                        [$path, basename($path)]
                    );

                    if (!empty($fileRecord)) {
                        fm_update('fm_files', [
                            'is_deleted' => 1,
                            'deleted_at' => date('Y-m-d H:i:s'),
                            'deleted_by' => $userId
                        ], ['id' => $fileRecord[0]['id']]);

                        // Update user quota (subtract deleted file size)
                        fm_update_user_quota($fileRecord[0]['user_id'], -$fileRecord[0]['size']);
                    }
                }

                $success = fm_delete_local_recursive($path);
                $results[] = ['path' => $path, 'success' => $success];
                if (!$success) $allSuccess = false;
            }

            echo json_encode([
                'status' => $allSuccess ? 200 : 207,
                'message' => $allSuccess ? 'Deleted successfully' : 'Some deletes failed',
                'results' => $results
            ]);
            exit;

        // Upload local file to R2
        case 'upload_r2_from_local':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $path = $_POST['path'] ?? $_GET['path'] ?? ($_POST['file'] ?? '');
            $mode = $_POST['mode'] ?? 'enqueue';

            if (empty($path)) {
                echo json_encode(['status' => 400, 'error' => 'Missing path parameter']);
                exit;
            }

            $backupDir = fm_get_backup_dir();
            $localDir = fm_get_local_dir();

            $fullPath = $localDir . '/' . ltrim($path, '/');

            if (!file_exists($fullPath)) {
                $fullPath = $backupDir . '/' . basename($path);
            }

            if (!file_exists($fullPath)) {
                echo json_encode(['status' => 404, 'error' => 'File not found']);
                exit;
            }

            $isBackup = strpos(realpath($fullPath), realpath($backupDir)) === 0;
            $remoteKey = $isBackup ? 'backups/' . basename($path) : 'files/' . ltrim($path, '/');

            if ($mode === 'immediate') {
                $result = fm_upload_to_r2($fullPath, $remoteKey);
                if ($result['success']) {
                    echo json_encode([
                        'status' => 200,
                        'url' => $result['url'] ?? null
                    ]);
                } else {
                    echo json_encode([
                        'status' => 500,
                        'error' => $result['message'] ?? 'Upload failed'
                    ]);
                }
            } else {
                if (fm_enqueue_r2_upload($fullPath, $remoteKey)) {
                    echo json_encode(['status' => 200, 'message' => 'Upload queued']);
                } else {
                    echo json_encode(['status' => 500, 'error' => 'Failed to queue upload']);
                }
            }
            exit;

        // List R2 objects
        case 'list_r2':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $prefix = $_GET['prefix'] ?? '';
            $objects = fm_list_r2_cached($prefix);

            echo json_encode(['status' => 200, 'objects' => $objects]);
            exit;

        // List upload queue
        case 'list_upload_queue':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $queue = fm_get_pending_uploads(200);

            echo json_encode(['status' => 200, 'queue' => $queue]);
            exit;

        // Process upload queue manually
        case 'process_upload_queue':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $limit = (int)($_POST['limit'] ?? 20);
            $processed = fm_process_upload_queue_worker($limit);

            echo json_encode(['status' => 200, 'processed' => $processed]);
            exit;

        // Create full database backup
        case 'create_full_backup':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $result = fm_create_db_dump('db_backup');

            if (!$result['success']) {
                echo json_encode([
                    'status' => 500,
                    'error' => $result['message'] ?? 'Backup failed'
                ]);
                exit;
            }

            $remoteKey = 'backups/' . $result['filename'];
            $enqueued = fm_enqueue_r2_upload($result['path'], $remoteKey);

            if (!$enqueued) {
                echo json_encode([
                    'status' => 500,
                    'error' => 'Failed to queue upload',
                    'filename' => $result['filename']
                ]);
            } else {
                echo json_encode([
                    'status' => 200,
                    'filename' => $result['filename'],
                    'path' => $result['path']
                ]);
            }
            exit;

        // Create table backup
        case 'create_table_backup':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $table = trim($_POST['table'] ?? '');
            if (empty($table)) {
                echo json_encode(['status' => 400, 'error' => 'Missing table parameter']);
                exit;
            }

            $result = fm_create_table_dump($table, 'table_backup');

            if (!$result['success']) {
                echo json_encode([
                    'status' => 500,
                    'error' => $result['message'] ?? 'Backup failed'
                ]);
            } else {
                echo json_encode([
                    'status' => 200,
                    'filename' => $result['filename'],
                    'path' => $result['path']
                ]);
            }
            exit;

        // List database backups
        case 'list_db_backups':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $searchQuery = trim($_GET['q'] ?? '');
            $dir = fm_get_config()['backup_dir'];
            $backups = [];

            if (is_dir($dir)) {
                $iterator = new DirectoryIterator($dir);
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $name = $file->getFilename();

                        if (!empty($searchQuery) && stripos($name, $searchQuery) === false) {
                            continue;
                        }

                        if (!preg_match('/\.(sql|sql\.gz)$/i', $name)) {
                            continue;
                        }

                        $backups[] = [
                            'name' => $name,
                            'size' => $file->getSize(),
                            'mtime' => $file->getMTime(),
                            'path' => $dir . '/' . $name
                        ];
                    }
                }

                usort($backups, function($a, $b) {
                    return $b['mtime'] - $a['mtime'];
                });
            }

            echo json_encode(['status' => 200, 'files' => $backups]);
            exit;

        case 'list_r2_backups':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $forceSync = isset($_GET['force_sync']) && $_GET['force_sync'] === '1';
            $metadataFile = fm_get_backup_dir() . '/r2_backups_metadata.json';
            $backups = [];

            if (!$forceSync && file_exists($metadataFile)) {
                $content = file_get_contents($metadataFile);
                $data = json_decode($content, true);
                if ($data && isset($data['backups'])) {
                    $backups = $data['backups'];
                }
            }

            if ($forceSync || empty($backups)) {
                $s3 = fm_init_s3();
                if ($s3) {
                    try {
                        $cfg = fm_get_config();
                        $result = $s3->listObjectsV2([
                            'Bucket' => $cfg['r2_bucket'],
                            'Prefix' => 'backups/'
                        ]);

                        if (isset($result['Contents'])) {
                            foreach ($result['Contents'] as $obj) {
                                if (preg_match('/\.(sql|sql\.gz)$/i', $obj['Key'])) {
                                    $backups[] = [
                                        'key' => $obj['Key'],
                                        'name' => basename($obj['Key']),
                                        'size' => $obj['Size'],
                                        'modified' => $obj['LastModified']->format('Y-m-d H:i:s')
                                    ];
                                }
                            }
                        }

                        usort($backups, function($a, $b) {
                            return strtotime($b['modified']) - strtotime($a['modified']);
                        });

                        file_put_contents($metadataFile, json_encode([
                            'updated_at' => date('Y-m-d H:i:s'),
                            'backups' => $backups
                        ], JSON_PRETTY_PRINT));
                    } catch (Exception $e) {
                    }
                }
            }

            echo json_encode(['status' => 200, 'backups' => $backups]);
            exit;

        case 'sync_r2_backups':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $s3 = fm_init_s3();
            if (!$s3) {
                echo json_encode(['status' => 500, 'error' => 'R2 not configured']);
                exit;
            }

            try {
                $cfg = fm_get_config();
                $result = $s3->listObjectsV2([
                    'Bucket' => $cfg['r2_bucket'],
                    'Prefix' => 'backups/'
                ]);

                $backups = [];
                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $obj) {
                        if (preg_match('/\.(sql|sql\.gz)$/i', $obj['Key'])) {
                            $backups[] = [
                                'key' => $obj['Key'],
                                'name' => basename($obj['Key']),
                                'size' => $obj['Size'],
                                'modified' => $obj['LastModified']->format('Y-m-d H:i:s')
                            ];
                        }
                    }
                }

                usort($backups, function($a, $b) {
                    return strtotime($b['modified']) - strtotime($a['modified']);
                });

                $metadataFile = fm_get_backup_dir() . '/r2_backups_metadata.json';
                file_put_contents($metadataFile, json_encode([
                    'updated_at' => date('Y-m-d H:i:s'),
                    'backups' => $backups
                ], JSON_PRETTY_PRINT));

                echo json_encode(['status' => 200, 'message' => 'Synced successfully', 'count' => count($backups)]);
            } catch (Exception $e) {
                echo json_encode(['status' => 500, 'error' => $e->getMessage()]);
            }
            exit;

        // Restore database from local backup
        case 'restore_db_local':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $file = $_POST['file'] ?? '';
            $confirmToken = $_POST['confirm_token'] ?? '';
            $targetDb = $_POST['target_db'] ?? '';
            $mode = $_POST['mode'] ?? 'full';
            $tables = isset($_POST['tables']) ? (is_array($_POST['tables']) ? $_POST['tables'] : explode(',', $_POST['tables'])) : [];
            $categories = isset($_POST['categories']) ? (is_array($_POST['categories']) ? $_POST['categories'] : explode(',', $_POST['categories'])) : [];

            if (empty($file)) {
                echo json_encode(['status' => 400, 'error' => 'Missing file parameter']);
                exit;
            }

            if ($confirmToken !== 'RESTORE_NOW') {
                echo json_encode([
                    'status' => 409,
                    'error' => 'Confirmation required: send confirm_token=RESTORE_NOW'
                ]);
                exit;
            }

            $fullPath = fm_get_config()['backup_dir'] . '/' . basename($file);

            if (!file_exists($fullPath)) {
                echo json_encode(['status' => 404, 'error' => 'Backup file not found']);
                exit;
            }

            $snapshot = fm_create_db_dump('pre_restore_snapshot');

            if ($mode === 'category' && !empty($categories)) {
                $result = fm_restore_by_category($fullPath, $categories, $targetDb);
            } elseif ($mode === 'selective' && !empty($tables)) {
                $result = fm_restore_selective_tables($fullPath, $tables, $targetDb);
            } else {
                $result = fm_restore_sql_gz_local($fullPath, $targetDb);
            }

            if ($result['success']) {
                echo json_encode(['status' => 200, 'message' => $result['message']]);
            } else {
                echo json_encode(['status' => 500, 'error' => $result['message']]);
            }
            exit;

        case 'get_table_categories':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $categories = fm_get_table_categories();
            echo json_encode(['status' => 200, 'categories' => $categories]);
            exit;

        case 'save_file_content':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $path = $_POST['path'] ?? '';
            $content = $_POST['content'] ?? '';

            if (empty($path)) {
                echo json_encode(['status' => 400, 'error' => 'Missing path parameter']);
                exit;
            }

            $baseDir = fm_get_local_dir();
            $fullPath = $baseDir . '/' . ltrim($path, '/');

            if (!file_exists($fullPath)) {
                echo json_encode(['status' => 404, 'error' => 'File not found']);
                exit;
            }

            $baseDirReal = realpath($baseDir);
            $fullPathReal = realpath($fullPath);

            if (strpos($fullPathReal, $baseDirReal) !== 0) {
                echo json_encode(['status' => 403, 'error' => 'Access denied']);
                exit;
            }

            if (file_put_contents($fullPath, $content) !== false) {
                $userId = _fm_user_id();
                fm_log_activity($userId, null, 'edit', ['path' => $path, 'size' => strlen($content)]);
                echo json_encode(['status' => 200, 'message' => 'File saved successfully']);
            } else {
                echo json_encode(['status' => 500, 'error' => 'Failed to save file']);
            }
            exit;

        // Restore database from R2 backup
        case 'restore_db_r2':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $r2Key = $_POST['r2_key'] ?? '';
            $confirmToken = $_POST['confirm_token'] ?? '';
            $targetDb = $_POST['target_db'] ?? '';

            if (empty($r2Key)) {
                echo json_encode(['status' => 400, 'error' => 'Missing r2_key parameter']);
                exit;
            }

            if ($confirmToken !== 'RESTORE_NOW') {
                echo json_encode([
                    'status' => 409,
                    'error' => 'Confirmation required: send confirm_token=RESTORE_NOW'
                ]);
                exit;
            }

            $s3 = fm_init_s3();
            if (!$s3) {
                echo json_encode(['status' => 500, 'error' => 'R2 not configured']);
                exit;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'r2_restore_');

            try {
                $config = fm_get_config();
                $s3->getObject([
                    'Bucket' => $config['r2_bucket'],
                    'Key' => $r2Key,
                    'SaveAs' => $tempFile
                ]);

                $result = fm_restore_sql_gz_local($tempFile, $targetDb);
                @unlink($tempFile);

                if ($result['success']) {
                    echo json_encode(['status' => 200, 'message' => $result['message']]);
                } else {
                    echo json_encode(['status' => 500, 'error' => $result['message']]);
                }
            } catch (Exception $e) {
                @unlink($tempFile);
                echo json_encode(['status' => 500, 'error' => $e->getMessage()]);
            }
            exit;

        // Get environment configuration
        case 'get_env':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $envVars = [
                'R2_ACCESS_KEY_ID' => getenv('R2_ACCESS_KEY_ID') ?: '',
                'R2_SECRET_ACCESS_KEY' => getenv('R2_SECRET_ACCESS_KEY') ?: '',
                'R2_BUCKET' => getenv('R2_BUCKET') ?: '',
                'R2_ENDPOINT' => getenv('R2_ENDPOINT') ?: '',
                'R2_ENDPOINT_DOMAIN' => getenv('R2_ENDPOINT_DOMAIN') ?: '',
                'LOCAL_STORAGE_DIR' => getenv('LOCAL_STORAGE_DIR') ?: '',
                'DB_BACKUP_LOCAL_DIR' => getenv('DB_BACKUP_LOCAL_DIR') ?: '',
                'DEFAULT_USER_QUOTA_GB' => getenv('DEFAULT_USER_QUOTA_GB') ?: '1',
                'AUTO_UPLOAD_TYPES' => getenv('AUTO_UPLOAD_TYPES') ?: 'sql,zip,xlsx,docx,pdf',
                'AUTO_UPLOAD_PREFIXES' => getenv('AUTO_UPLOAD_PREFIXES') ?: 'db_,sys_',
                'RECYCLE_RETENTION_DAYS' => getenv('RECYCLE_RETENTION_DAYS') ?: '30',
                'BACKUP_RETENTION_DAYS' => getenv('BACKUP_RETENTION_DAYS') ?: '30',
                'AUTO_BACKUP_ENABLED' => getenv('AUTO_BACKUP_ENABLED') ?: '0'
            ];

            echo json_encode([
                'status' => 200,
                'env' => $envVars,
                'r2_configured' => !empty($envVars['R2_ACCESS_KEY_ID']) && !empty($envVars['R2_SECRET_ACCESS_KEY']),
                'local_storage' => fm_get_local_dir()
            ]);
            exit;

        // Save environment configuration
        case 'save_env':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $envPath = __DIR__ . '/../.env';
            if (!file_exists($envPath)) {
                echo json_encode(['status' => 500, 'error' => '.env file not found']);
                exit;
            }

            $allowedKeys = [
                'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET', 'R2_ENDPOINT',
                'R2_ENDPOINT_DOMAIN', 'LOCAL_STORAGE_DIR', 'DB_BACKUP_LOCAL_DIR',
                'DEFAULT_USER_QUOTA_GB', 'AUTO_UPLOAD_TYPES', 'AUTO_UPLOAD_PREFIXES',
                'RECYCLE_RETENTION_DAYS', 'BACKUP_RETENTION_DAYS', 'AUTO_BACKUP_ENABLED'
            ];

            $envContent = file_get_contents($envPath);
            $lines = explode("\n", $envContent);
            $updated = [];
            $existingKeys = [];

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed) || strpos($trimmed, '#') === 0) {
                    $updated[] = $line;
                    continue;
                }

                if (strpos($trimmed, '=') === false) {
                    $updated[] = $line;
                    continue;
                }

                list($key) = explode('=', $trimmed, 2);
                $key = trim($key);

                if (in_array($key, $allowedKeys) && isset($_POST[$key])) {
                    $value = $_POST[$key];
                    if (strpos($value, ' ') !== false || strpos($value, '#') !== false) {
                        $value = '"' . str_replace('"', '\\"', $value) . '"';
                    }
                    $updated[] = $key . '=' . $value;
                    $existingKeys[] = $key;
                } else {
                    $updated[] = $line;
                }
            }

            foreach ($allowedKeys as $key) {
                if (isset($_POST[$key]) && !in_array($key, $existingKeys)) {
                    $value = $_POST[$key];
                    if (strpos($value, ' ') !== false || strpos($value, '#') !== false) {
                        $value = '"' . str_replace('"', '\\"', $value) . '"';
                    }
                    $updated[] = $key . '=' . $value;
                }
            }

            if (file_put_contents($envPath, implode("\n", $updated))) {
                fm_load_env($envPath);
                echo json_encode(['status' => 200, 'message' => 'Configuration saved successfully']);
            } else {
                echo json_encode(['status' => 500, 'error' => 'Failed to write .env file']);
            }
            exit;

        // Automated backup run (can be triggered by cron)
        case 'auto_backup_run':
            $token = $_GET['token'] ?? $_POST['token'] ?? '';
            $secret = getenv('AUTO_BACKUP_SECRET') ?: '';

            if (!(_fm_is_admin() || (!empty($secret) && $token === $secret))) {
                echo json_encode(['status' => 403, 'error' => 'Admin access or valid token required']);
                exit;
            }

            $dumpResult = fm_create_db_dump('db_backup');
            $enqueued = false;

            if ($dumpResult['success']) {
                $remoteKey = 'backups/' . $dumpResult['filename'];
                $enqueued = fm_enqueue_r2_upload($dumpResult['path'], $remoteKey);
            }

            $retention = fm_enforce_retention();

            echo json_encode([
                'status' => 200,
                'dump' => $dumpResult,
                'enqueued' => $enqueued,
                'rotation' => $retention
            ]);
            exit;

        // Enforce retention policies
        case 'enforce_retention':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $result = fm_enforce_retention();

            echo json_encode(['status' => 200, 'result' => $result]);
            exit;

        // Get user quota information
        case 'get_user_quota':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : _fm_user_id();
            $quota = fm_get_user_quota($userId);

            echo json_encode([
                'status' => 200,
                'quota' => $quota['quota'],
                'used' => $quota['used'],
                'quota_formatted' => fm_format_bytes($quota['quota']),
                'used_formatted' => fm_format_bytes($quota['used']),
                'percentage' => $quota['quota'] > 0 ? round(($quota['used'] / $quota['quota']) * 100, 2) : 0
            ]);
            exit;

        // Sync user quota (recalculate from disk)
        case 'sync_user_quota':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $userId = _fm_user_id();
            $actualUsage = fm_sync_user_quota($userId);
            $quota = fm_get_user_quota($userId);

            echo json_encode([
                'status' => 200,
                'message' => 'Quota synchronized',
                'quota' => $quota['quota'],
                'used' => $quota['used'],
                'quota_formatted' => fm_format_bytes($quota['quota']),
                'used_formatted' => fm_format_bytes($quota['used'])
            ]);
            exit;

        // Get total storage usage (admin only)
        case 'get_total_storage':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $totalStorage = fm_calculate_total_storage();

            echo json_encode([
                'status' => 200,
                'total_bytes' => $totalStorage,
                'total_formatted' => fm_format_bytes($totalStorage)
            ]);
            exit;

        // Get file URL (checks if file is in R2, returns CDN URL or local path indicator)
        case 'get_file_url':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $path = $_GET['path'] ?? $_POST['path'] ?? '';
            if (empty($path)) {
                echo json_encode(['status' => 400, 'error' => 'Missing path parameter']);
                exit;
            }

            $baseDir = fm_get_local_dir();
            $fullPath = $baseDir . '/' . ltrim($path, '/');

            if (!file_exists($fullPath)) {
                echo json_encode(['status' => 404, 'error' => 'File not found']);
                exit;
            }

            $result = fm_get_file_url($path);
            echo json_encode([
                'status' => 200,
                'location' => $result['location'],
                'url' => $result['url'],
                'size' => filesize($fullPath)
            ]);
            exit;

        default:
            echo json_encode(['status' => 404, 'error' => 'Unknown action: ' . $action]);
            exit;
    }
} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];

    fm_log_error('API Exception', $errorDetails);

    $response = [
        'status' => 500,
        'error' => 'Server error: ' . $e->getMessage()
    ];

    // Include debug info if FM_DEBUG is enabled
    if (getenv('FM_DEBUG') === '1') {
        $response['debug'] = $errorDetails;
    }

    echo json_encode($response);
    exit;
}

if (!function_exists('fm_format_bytes')) {
    function fm_format_bytes($bytes, $precision = 2) {
        if ($bytes <= 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = 1024;
        $exp = floor(log($bytes) / log($base));
        $exp = min($exp, count($units) - 1);

        return round($bytes / pow($base, $exp), $precision) . ' ' . $units[$exp];
    }
}
