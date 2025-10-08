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

        // Sync R2 status for files
        case 'sync_r2_status':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $limit = (int)($_POST['limit'] ?? 100);
            $updated = fm_sync_all_r2_status($limit);

            echo json_encode([
                'status' => 200,
                'updated' => $updated,
                'message' => "Synced R2 status for {$updated} file(s)"
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

                    // Generate thumbnail for images
                    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                    $extension = strtolower(pathinfo($saveResult['filename'], PATHINFO_EXTENSION));
                    if ($fileId && in_array($extension, $imageExts)) {
                        $thumbResult = fm_generate_thumbnail($saveResult['path'], $fileId, 'medium');
                        if ($thumbResult['success']) {
                            fm_update('fm_files', ['thumbnail_generated' => 1], ['id' => $fileId]);
                        }
                    }

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
                $backupDir = fm_get_backup_dir();
                $result = $s3->listObjectsV2([
                    'Bucket' => $cfg['r2_bucket'],
                    'Prefix' => 'backups/'
                ]);

                $backups = [];
                $downloaded = 0;
                $skipped = 0;
                $errors = [];

                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $obj) {
                        if (preg_match('/\.(sql|sql\.gz)$/i', $obj['Key'])) {
                            $fileName = basename($obj['Key']);
                            $localPath = $backupDir . '/' . $fileName;
                            $needsDownload = false;

                            // Check if file exists locally
                            if (!file_exists($localPath)) {
                                $needsDownload = true;
                            } else {
                                // Check if file size matches
                                $localSize = filesize($localPath);
                                if ($localSize !== $obj['Size']) {
                                    $needsDownload = true;
                                }
                            }

                            // Download file if needed
                            if ($needsDownload) {
                                try {
                                    $s3->getObject([
                                        'Bucket' => $cfg['r2_bucket'],
                                        'Key' => $obj['Key'],
                                        'SaveAs' => $localPath
                                    ]);
                                    $downloaded++;
                                    fm_log_info('R2 Sync: Downloaded backup file', [
                                        'file' => $fileName,
                                        'size' => $obj['Size']
                                    ]);
                                } catch (Exception $e) {
                                    $errors[] = "Failed to download {$fileName}: " . $e->getMessage();
                                    fm_log_error('R2 Sync: Download failed', [
                                        'file' => $fileName,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            } else {
                                $skipped++;
                            }

                            $backups[] = [
                                'key' => $obj['Key'],
                                'name' => $fileName,
                                'size' => $obj['Size'],
                                'modified' => $obj['LastModified']->format('Y-m-d H:i:s'),
                                'local_exists' => file_exists($localPath),
                                'local_size' => file_exists($localPath) ? filesize($localPath) : 0
                            ];
                        }
                    }
                }

                usort($backups, function($a, $b) {
                    return strtotime($b['modified']) - strtotime($a['modified']);
                });

                $metadataFile = $backupDir . '/r2_backups_metadata.json';
                file_put_contents($metadataFile, json_encode([
                    'updated_at' => date('Y-m-d H:i:s'),
                    'backups' => $backups
                ], JSON_PRETTY_PRINT));

                $response = [
                    'status' => 200,
                    'message' => 'Synced successfully',
                    'count' => count($backups),
                    'downloaded' => $downloaded,
                    'skipped' => $skipped
                ];

                if (!empty($errors)) {
                    $response['errors'] = $errors;
                    $response['partial'] = true;
                }

                echo json_encode($response);
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

        // Get initialization data (combines multiple requests)
        case 'get_init_data':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $userId = _fm_user_id();
            $isAdmin = _fm_is_admin();

            // Get common folders
            $commonFolders = fm_query("SELECT * FROM fm_common_folders WHERE is_active = 1 ORDER BY sort_order ASC");

            // Get special folders
            if ($isAdmin) {
                $specialFolders = fm_query("SELECT * FROM fm_special_folders WHERE is_active = 1 ORDER BY sort_order ASC");
            } else {
                $specialFolders = fm_query("
                    SELECT sf.* FROM fm_special_folders sf
                    INNER JOIN fm_folder_access fa ON fa.folder_id = sf.id AND fa.folder_type = 'special'
                    WHERE sf.is_active = 1 AND fa.user_id = ?
                    ORDER BY sf.sort_order ASC
                ", [$userId]);
            }

            // Get user quota
            $quota = fm_get_user_quota($userId);

            // Check R2 status
            $r2 = fm_init_s3();
            $r2Enabled = !empty($r2);

            echo json_encode([
                'status' => 200,
                'common_folders' => $commonFolders ?: [],
                'special_folders' => $specialFolders ?: [],
                'quota' => [
                    'quota' => $quota['quota'],
                    'used' => $quota['used'],
                    'quota_formatted' => fm_format_bytes($quota['quota']),
                    'used_formatted' => fm_format_bytes($quota['used']),
                    'percentage' => $quota['quota'] > 0 ? round(($quota['used'] / $quota['quota']) * 100, 2) : 0
                ],
                'r2_enabled' => $r2Enabled,
                'r2_status' => $r2Enabled ? 'Connected' : 'Not Configured'
            ]);
            exit;

        // Get all common folders
        case 'list_common_folders':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $folders = fm_query("SELECT * FROM fm_common_folders WHERE is_active = 1 ORDER BY sort_order ASC");
            echo json_encode(['status' => 200, 'folders' => $folders ?: []]);
            exit;

        // Get all special folders (with access check)
        case 'list_special_folders':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $userId = _fm_user_id();
            $isAdmin = _fm_is_admin();

            if ($isAdmin) {
                $folders = fm_query("SELECT * FROM fm_special_folders WHERE is_active = 1 ORDER BY sort_order ASC");
            } else {
                $folders = fm_query("
                    SELECT sf.* FROM fm_special_folders sf
                    INNER JOIN fm_folder_access fa ON fa.folder_id = sf.id AND fa.folder_type = 'special'
                    WHERE sf.is_active = 1 AND fa.user_id = ?
                    ORDER BY sf.sort_order ASC
                ", [$userId]);
            }

            echo json_encode(['status' => 200, 'folders' => $folders ?: []]);
            exit;

        // Get recycle bin items
        case 'list_recycle_bin':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $userId = _fm_user_id();
            $isAdmin = _fm_is_admin();

            if ($isAdmin) {
                $items = fm_query("
                    SELECT rb.*, f.mime_type, f.file_type
                    FROM fm_recycle_bin rb
                    LEFT JOIN fm_files f ON rb.file_id = f.id
                    WHERE rb.restored_at IS NULL AND rb.force_deleted_at IS NULL
                    ORDER BY rb.deleted_at DESC
                ");
            } else {
                $items = fm_query("
                    SELECT rb.*, f.mime_type, f.file_type
                    FROM fm_recycle_bin rb
                    LEFT JOIN fm_files f ON rb.file_id = f.id
                    WHERE rb.user_id = ? AND rb.restored_at IS NULL AND rb.force_deleted_at IS NULL
                    ORDER BY rb.deleted_at DESC
                ", [$userId]);
            }

            echo json_encode(['status' => 200, 'items' => $items ?: []]);
            exit;

        // Restore file from recycle bin
        case 'restore_from_recycle':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $recycleId = (int)($_POST['recycle_id'] ?? 0);
            if (!$recycleId) {
                echo json_encode(['status' => 400, 'error' => 'Missing recycle_id']);
                exit;
            }

            $userId = _fm_user_id();
            $isAdmin = _fm_is_admin();

            $item = fm_query("SELECT * FROM fm_recycle_bin WHERE id = ? LIMIT 1", [$recycleId]);
            if (empty($item)) {
                echo json_encode(['status' => 404, 'error' => 'Recycle item not found']);
                exit;
            }

            $item = $item[0];
            if (!$isAdmin && $item['user_id'] != $userId) {
                echo json_encode(['status' => 403, 'error' => 'Access denied']);
                exit;
            }

            if ($item['can_restore'] != 1) {
                echo json_encode(['status' => 400, 'error' => 'This item cannot be restored']);
                exit;
            }

            // Restore file in database
            fm_update('fm_files', [
                'is_deleted' => 0,
                'deleted_at' => null,
                'deleted_by' => null
            ], ['id' => $item['file_id']]);

            // Mark as restored in recycle bin
            fm_update('fm_recycle_bin', [
                'restored_at' => date('Y-m-d H:i:s')
            ], ['id' => $recycleId]);

            // Update user quota
            fm_update_user_quota($item['user_id'], $item['size']);

            echo json_encode(['status' => 200, 'message' => 'File restored successfully']);
            exit;

        // Permanently delete from recycle bin (admin only)
        case 'permanent_delete':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $recycleId = (int)($_POST['recycle_id'] ?? 0);
            if (!$recycleId) {
                echo json_encode(['status' => 400, 'error' => 'Missing recycle_id']);
                exit;
            }

            $item = fm_query("SELECT * FROM fm_recycle_bin WHERE id = ? LIMIT 1", [$recycleId]);
            if (empty($item)) {
                echo json_encode(['status' => 404, 'error' => 'Recycle item not found']);
                exit;
            }

            $item = $item[0];
            $userId = _fm_user_id();

            // Mark as permanently deleted
            fm_update('fm_recycle_bin', [
                'force_deleted_at' => date('Y-m-d H:i:s'),
                'force_deleted_by' => $userId,
                'can_restore' => 0
            ], ['id' => $recycleId]);

            // Delete physical file if exists
            $baseDir = fm_get_local_dir();
            $fullPath = $baseDir . '/' . ltrim($item['original_path'], '/');
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            echo json_encode(['status' => 200, 'message' => 'File permanently deleted']);
            exit;

        // Clean recycle bin (admin only)
        case 'clean_recycle_bin':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $userId = _fm_user_id();
            $now = date('Y-m-d H:i:s');

            // Get expired items
            $expiredItems = fm_query("
                SELECT * FROM fm_recycle_bin
                WHERE auto_delete_at <= ? AND restored_at IS NULL AND force_deleted_at IS NULL
            ", [$now]);

            $deletedCount = 0;
            $baseDir = fm_get_local_dir();

            foreach ($expiredItems as $item) {
                // Mark as permanently deleted
                fm_update('fm_recycle_bin', [
                    'force_deleted_at' => $now,
                    'force_deleted_by' => $userId,
                    'can_restore' => 0
                ], ['id' => $item['id']]);

                // Delete physical file
                $fullPath = $baseDir . '/' . ltrim($item['original_path'], '/');
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }

                $deletedCount++;
            }

            echo json_encode([
                'status' => 200,
                'message' => "Cleaned {$deletedCount} expired items",
                'deleted_count' => $deletedCount
            ]);
            exit;

        // Get file versions
        case 'list_file_versions':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $fileId = (int)($_GET['file_id'] ?? 0);
            if (!$fileId) {
                echo json_encode(['status' => 400, 'error' => 'Missing file_id']);
                exit;
            }

            $versions = fm_query("
                SELECT * FROM fm_file_versions
                WHERE file_id = ?
                ORDER BY version_number DESC
            ", [$fileId]);

            echo json_encode(['status' => 200, 'versions' => $versions ?: []]);
            exit;

        // Create file share
        case 'create_file_share':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $fileId = (int)($_POST['file_id'] ?? 0);
            $sharedWith = isset($_POST['shared_with']) ? (int)$_POST['shared_with'] : null;
            $shareType = $_POST['share_type'] ?? 'private';
            $permission = $_POST['permission'] ?? 'view';
            $expiresAt = $_POST['expires_at'] ?? null;

            if (!$fileId) {
                echo json_encode(['status' => 400, 'error' => 'Missing file_id']);
                exit;
            }

            $userId = _fm_user_id();
            $shareToken = bin2hex(random_bytes(32));

            $shareData = [
                'file_id' => $fileId,
                'shared_by' => $userId,
                'shared_with' => $sharedWith,
                'share_type' => $shareType,
                'permission' => $permission,
                'share_token' => $shareToken,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $shareId = fm_insert('fm_file_shares', $shareData);

            echo json_encode([
                'status' => 200,
                'share_id' => $shareId,
                'share_token' => $shareToken,
                'share_url' => 'share.php?token=' . $shareToken
            ]);
            exit;

        // List file shares
        case 'list_file_shares':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $fileId = (int)($_GET['file_id'] ?? 0);
            if (!$fileId) {
                echo json_encode(['status' => 400, 'error' => 'Missing file_id']);
                exit;
            }

            $userId = _fm_user_id();
            $shares = fm_query("
                SELECT fs.*, u.username as shared_with_username
                FROM fm_file_shares fs
                LEFT JOIN Wo_Users u ON fs.shared_with = u.user_id
                WHERE fs.file_id = ? AND fs.shared_by = ? AND fs.is_active = 1
                ORDER BY fs.created_at DESC
            ", [$fileId, $userId]);

            echo json_encode(['status' => 200, 'shares' => $shares ?: []]);
            exit;

        // Revoke file share
        case 'revoke_file_share':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $shareId = (int)($_POST['share_id'] ?? 0);
            if (!$shareId) {
                echo json_encode(['status' => 400, 'error' => 'Missing share_id']);
                exit;
            }

            $userId = _fm_user_id();
            fm_update('fm_file_shares', ['is_active' => 0], ['id' => $shareId, 'shared_by' => $userId]);

            echo json_encode(['status' => 200, 'message' => 'Share revoked successfully']);
            exit;

        // Get total system storage (admin only)
        case 'get_system_storage':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            // Get VPS total storage limit
            $vpsSetting = fm_query("SELECT setting_value FROM fm_system_settings WHERE setting_key = 'vps_total_storage_bytes' LIMIT 1");
            $vpsTotal = !empty($vpsSetting) ? (int)$vpsSetting[0]['setting_value'] : 64424509440; // 60 GB default

            // Calculate total used by all users
            $usedResult = fm_query("SELECT SUM(used_bytes) as total_used FROM fm_user_quotas");
            $totalUsed = !empty($usedResult) ? (int)$usedResult[0]['total_used'] : 0;

            // Get per-user breakdown
            $userBreakdown = fm_query("
                SELECT uq.user_id, uq.used_bytes, u.username
                FROM fm_user_quotas uq
                LEFT JOIN Wo_Users u ON uq.user_id = u.user_id
                WHERE uq.used_bytes > 0
                ORDER BY uq.used_bytes DESC
                LIMIT 50
            ");

            echo json_encode([
                'status' => 200,
                'vps_total_bytes' => $vpsTotal,
                'total_used_bytes' => $totalUsed,
                'vps_total_formatted' => fm_format_bytes($vpsTotal),
                'total_used_formatted' => fm_format_bytes($totalUsed),
                'percentage_used' => $vpsTotal > 0 ? round(($totalUsed / $vpsTotal) * 100, 2) : 0,
                'user_breakdown' => $userBreakdown ?: []
            ]);
            exit;

        // Manage special folders (admin only)
        case 'manage_special_folder':
            if (!_fm_is_admin()) {
                echo json_encode(['status' => 403, 'error' => 'Admin access required']);
                exit;
            }

            $subAction = $_POST['sub_action'] ?? '';

            if ($subAction === 'create') {
                $folderData = [
                    'folder_name' => $_POST['folder_name'] ?? '',
                    'folder_key' => $_POST['folder_key'] ?? '',
                    'folder_path' => $_POST['folder_path'] ?? '',
                    'folder_icon' => $_POST['folder_icon'] ?? 'bi-folder-lock',
                    'folder_color' => $_POST['folder_color'] ?? '#ef4444',
                    'description' => $_POST['description'] ?? '',
                    'sort_order' => (int)($_POST['sort_order'] ?? 0),
                    'created_by' => _fm_user_id(),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $folderId = fm_insert('fm_special_folders', $folderData);
                echo json_encode(['status' => 200, 'folder_id' => $folderId]);
            } elseif ($subAction === 'grant_access') {
                $accessData = [
                    'folder_id' => (int)$_POST['folder_id'],
                    'folder_type' => 'special',
                    'user_id' => (int)$_POST['user_id'],
                    'permission_level' => $_POST['permission_level'] ?? 'view',
                    'granted_by' => _fm_user_id(),
                    'granted_at' => date('Y-m-d H:i:s')
                ];

                fm_insert('fm_folder_access', $accessData);
                echo json_encode(['status' => 200, 'message' => 'Access granted']);
            } elseif ($subAction === 'revoke_access') {
                $folderId = (int)$_POST['folder_id'];
                $userId = (int)$_POST['user_id'];

                fm_query("DELETE FROM fm_folder_access WHERE folder_id = ? AND user_id = ? AND folder_type = 'special'", [$folderId, $userId]);
                echo json_encode(['status' => 200, 'message' => 'Access revoked']);
            }
            exit;

        // Rename file or folder
        case 'rename':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $oldPath = $_POST['old_path'] ?? '';
            $newName = $_POST['new_name'] ?? '';

            if (empty($oldPath) || empty($newName)) {
                echo json_encode(['status' => 400, 'error' => 'Missing parameters']);
                exit;
            }

            $baseDir = fm_get_local_dir();
            $oldFullPath = $baseDir . '/' . ltrim($oldPath, '/');

            $pathParts = explode('/', $oldPath);
            array_pop($pathParts);
            $pathParts[] = $newName;
            $newPath = implode('/', $pathParts);
            $newFullPath = $baseDir . '/' . ltrim($newPath, '/');

            if (!file_exists($oldFullPath)) {
                echo json_encode(['status' => 404, 'error' => 'File not found']);
                exit;
            }

            if (file_exists($newFullPath)) {
                echo json_encode(['status' => 409, 'error' => 'A file with this name already exists']);
                exit;
            }

            if (@rename($oldFullPath, $newFullPath)) {
                // Update database
                fm_update('fm_files', [
                    'filename' => $newName,
                    'path' => $newPath,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['path' => $oldPath]);

                echo json_encode(['status' => 200, 'new_path' => $newPath]);
            } else {
                echo json_encode(['status' => 500, 'error' => 'Failed to rename']);
            }
            exit;

        // Move file or folder
        case 'move':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $sourcePath = $_POST['source_path'] ?? '';
            $targetPath = $_POST['target_path'] ?? '';

            if (empty($sourcePath) || empty($targetPath)) {
                echo json_encode(['status' => 400, 'error' => 'Missing parameters']);
                exit;
            }

            $baseDir = fm_get_local_dir();
            $sourceFullPath = $baseDir . '/' . ltrim($sourcePath, '/');
            $targetFullPath = $baseDir . '/' . ltrim($targetPath, '/');

            if (!file_exists($sourceFullPath)) {
                echo json_encode(['status' => 404, 'error' => 'Source file not found']);
                exit;
            }

            if (!is_dir(dirname($targetFullPath))) {
                @mkdir(dirname($targetFullPath), 0755, true);
            }

            if (@rename($sourceFullPath, $targetFullPath)) {
                // Update database
                fm_update('fm_files', [
                    'path' => $targetPath,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['path' => $sourcePath]);

                echo json_encode(['status' => 200, 'new_path' => $targetPath]);
            } else {
                echo json_encode(['status' => 500, 'error' => 'Failed to move']);
            }
            exit;

        // Create new file
        case 'create_new_file':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $path = $_POST['path'] ?? '';
            $type = $_POST['type'] ?? 'text';
            $content = $_POST['content'] ?? '';

            if (empty($path)) {
                echo json_encode(['status' => 400, 'error' => 'Missing path']);
                exit;
            }

            $baseDir = fm_get_local_dir();
            $fullPath = $baseDir . '/' . ltrim($path, '/');

            if (!is_dir(dirname($fullPath))) {
                @mkdir(dirname($fullPath), 0755, true);
            }

            if (file_exists($fullPath)) {
                echo json_encode(['status' => 409, 'error' => 'File already exists']);
                exit;
            }

            $success = false;
            if ($type === 'text') {
                $success = file_put_contents($fullPath, $content) !== false;
            } elseif ($type === 'docx' || $type === 'xlsx') {
                $success = touch($fullPath);
            }

            if ($success) {
                $userId = _fm_user_id();
                $fileSize = filesize($fullPath);

                fm_insert('fm_files', [
                    'user_id' => $userId,
                    'filename' => basename($fullPath),
                    'original_filename' => basename($path),
                    'path' => ltrim($path, '/'),
                    'file_type' => pathinfo($path, PATHINFO_EXTENSION),
                    'mime_type' => 'application/octet-stream',
                    'size' => $fileSize,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                fm_update_user_quota($userId, $fileSize);
                echo json_encode(['status' => 200, 'path' => $path]);
            } else {
                echo json_encode(['status' => 500, 'error' => 'Failed to create file']);
            }
            exit;

        // Generate thumbnail
        case 'generate_thumbnail':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $fileId = (int)($_POST['file_id'] ?? 0);
            $size = $_POST['size'] ?? 'medium';

            if (!$fileId) {
                echo json_encode(['status' => 400, 'error' => 'Missing file_id']);
                exit;
            }

            $file = fm_query("SELECT * FROM fm_files WHERE id = ? LIMIT 1", [$fileId]);
            if (empty($file)) {
                echo json_encode(['status' => 404, 'error' => 'File not found']);
                exit;
            }

            $baseDir = fm_get_local_dir();
            $fileRecord = $file[0];

            $filePath = $baseDir . '/' . $fileRecord['filename'];
            if (!file_exists($filePath) && !empty($fileRecord['path'])) {
                $filePath = $baseDir . '/' . ltrim($fileRecord['path'], '/');
            }

            if (!file_exists($filePath)) {
                echo json_encode(['status' => 404, 'error' => 'Physical file not found: ' . $fileRecord['filename']]);
                exit;
            }

            $result = fm_generate_thumbnail($filePath, $fileId, $size);

            if ($result['success']) {
                fm_update('fm_files', ['thumbnail_generated' => 1], ['id' => $fileId]);
                echo json_encode(['status' => 200, 'thumbnail' => $result]);
            } else {
                echo json_encode(['status' => 500, 'error' => $result['error'] ?? 'Unknown error']);
            }
            exit;

        // Get file thumbnail
        case 'get_thumbnail':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $fileId = (int)($_GET['file_id'] ?? 0);
            $size = $_GET['size'] ?? 'medium';

            if (!$fileId) {
                echo json_encode(['status' => 400, 'error' => 'Missing file_id']);
                exit;
            }

            $thumb = fm_get_file_thumbnail($fileId, $size);

            if ($thumb) {
                echo json_encode(['status' => 200, 'thumbnail' => $thumb]);
            } else {
                echo json_encode(['status' => 404, 'error' => 'Thumbnail not found']);
            }
            exit;

        // Create file version
        case 'create_version':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $fileId = (int)($_POST['file_id'] ?? 0);
            $comment = $_POST['comment'] ?? null;

            if (!$fileId) {
                echo json_encode(['status' => 400, 'error' => 'Missing file_id']);
                exit;
            }

            $userId = _fm_user_id();
            $file = fm_query("SELECT * FROM fm_files WHERE id = ? LIMIT 1", [$fileId]);

            if (empty($file)) {
                echo json_encode(['status' => 404, 'error' => 'File not found']);
                exit;
            }

            $baseDir = fm_get_local_dir();
            $filePath = $baseDir . '/' . $file[0]['filename'];

            $result = fm_create_file_version($fileId, $userId, $filePath, $comment);

            if ($result['success']) {
                echo json_encode(['status' => 200, 'version' => $result]);
            } else {
                echo json_encode(['status' => 500, 'error' => $result['error']]);
            }
            exit;

        // Collabora Online: Get document info
        case 'collabora_info':
            if (!_fm_is_logged()) {
                echo json_encode(['status' => 403, 'error' => 'Login required']);
                exit;
            }

            $fileId = (int)($_GET['file_id'] ?? 0);
            if (!$fileId) {
                echo json_encode(['status' => 400, 'error' => 'Missing file_id']);
                exit;
            }

            $settings = fm_query("SELECT setting_value FROM fm_system_settings WHERE setting_key IN ('collabora_enabled', 'collabora_url')");
            $collaboraEnabled = false;
            $collaboraUrl = '';

            foreach ($settings as $setting) {
                if ($setting['setting_key'] === 'collabora_enabled') {
                    $collaboraEnabled = (int)$setting['setting_value'] === 1;
                }
                if ($setting['setting_key'] === 'collabora_url') {
                    $collaboraUrl = $setting['setting_value'];
                }
            }

            if (!$collaboraEnabled || empty($collaboraUrl)) {
                echo json_encode(['status' => 503, 'error' => 'Collabora Online not configured']);
                exit;
            }

            $file = fm_query("SELECT * FROM fm_files WHERE id = ? LIMIT 1", [$fileId]);
            if (empty($file)) {
                echo json_encode(['status' => 404, 'error' => 'File not found']);
                exit;
            }

            echo json_encode([
                'status' => 200,
                'collabora_url' => $collaboraUrl,
                'file_url' => Wo_Ajax_Requests_File() . '?f=file_manager&s=download_local&file=' . urlencode($file[0]['path']),
                'file_name' => $file[0]['original_filename']
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