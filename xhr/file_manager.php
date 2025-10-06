<?php
/*
remove this and rebuild this 

*/
// xhr/file_manager.php
// AJAX API for file manager & backups

if (!defined('FM_XHR_API')) define('FM_XHR_API', true);
header('Content-Type: application/json; charset=utf-8');

// Basic auth shims - integrated with your app if available
function _fm_is_logged() { if (function_exists('Wo_IsLogged')) return Wo_IsLogged(); if (function_exists('is_logged_in')) return is_logged_in(); return true; }
function _fm_is_admin() { if (function_exists('Wo_IsAdmin')) return Wo_IsAdmin(); if (function_exists('is_admin')) return is_admin(); return false; }
function _fm_user_id() { if (function_exists('Wo_UserId')) return (int)Wo_UserId(); return isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0; }

// include helper
$helper = __DIR__ . '/../assets/includes/file_manager_helper.php';
if (!file_exists($helper)) {
    echo json_encode(['status'=>500,'error'=>'helper missing: '.$helper]); exit;
}
require_once $helper;

// get action
$s = $_GET['s'] ?? $_POST['s'] ?? '';

try {
    switch ($s) {

        // ping
        case 'ping':
            $r2 = function_exists('fm_init_s3') ? fm_init_s3() : null;
            echo json_encode(['status'=>200,'r2_enabled'=> (bool)$r2, 'local_dir'=>fm_get_local_dir()]); exit;

        // list local folder
        case 'list_local_folder':
            if (!_fm_is_logged()) { echo json_encode(['status'=>403,'error'=>'login required']); exit; }
            $path = trim($_GET['path'] ?? '');
            $res = fm_list_local_folder($path);
            echo json_encode(['status'=>200,'folders'=>$res['folders'],'files'=>$res['files']]); exit;

        // create folder
        case 'create_folder':
            if (!_fm_is_logged()) { echo json_encode(['status'=>403,'error'=>'login required']); exit; }
            $path = trim($_POST['path'] ?? '');
            if ($path === '') { echo json_encode(['status'=>400,'error'=>'missing path']); exit; }
            $full = fm_get_local_dir() . '/' . ltrim($path,'/');
            if (file_exists($full)) { echo json_encode(['status'=>409,'error'=>'exists']); exit; }
            if (!@mkdir($full,0755,true)) { echo json_encode(['status'=>500,'error'=>'mkdir failed']); exit; }
            echo json_encode(['status'=>200,'path'=>trim($path,'/')]); exit;

        // upload_local
        case 'upload_local':
            if (!_fm_is_logged()) { echo json_encode(['status'=>403,'error'=>'login required']); exit; }
            if (empty($_FILES)) { echo json_encode(['status'=>400,'error'=>'no files']); exit; }
            $subdir = trim($_POST['subdir'] ?? '');
            $user_id = _fm_user_id();
            $filesArr = [];
            if (isset($_FILES['files'])) {
                for ($i=0;$i<count($_FILES['files']['name']);$i++) {
                    $filesArr[] = ['name'=>$_FILES['files']['name'][$i],'tmp_name'=>$_FILES['files']['tmp_name'][$i],'error'=>$_FILES['files']['error'][$i],'size'=>$_FILES['files']['size'][$i]];
                }
            } elseif (isset($_FILES['file'])) $filesArr[] = $_FILES['file'];
            else foreach ($_FILES as $f) $filesArr[] = $f;
            $results=[];
            $cfg = fm_get_config();
            foreach ($filesArr as $f) {
                if ($f['error'] !== UPLOAD_ERR_OK) { $results[] = ['success'=>false,'error'=>'upload error','orig'=>$f['name']]; continue; }
                $save = fm_save_uploaded_local($f, $subdir);
                if ($save['success']) {
                    $rel = ltrim(($subdir !== '' ? trim($subdir,'/').'/' : '') . $save['filename'],'/');
                    $results[] = ['success'=>true,'path'=>$rel,'size'=>filesize($save['path'])];
                    // auto enqueue/upload for large files or matching types
                    $ext = strtolower(pathinfo($save['filename'], PATHINFO_EXTENSION));
                    $doImmediate = in_array($ext, $cfg['auto_upload_exts']);
                    foreach ($cfg['auto_upload_prefixes'] as $p) if ($p !== '' && stripos($save['filename'],$p) === 0) $doImmediate = true;
                    $remote = 'files/'.$rel;
                    if ($doImmediate) {
                        if (fm_init_s3()) {
                            $r = fm_upload_to_r2($save['path'], $remote);
                            if (!$r['success']) fm_enqueue_r2_upload($save['path'],$remote);
                        } else fm_enqueue_r2_upload($save['path'],$remote);
                    } else {
                        // large-file rule: if size > 20MB, enqueue automatically
                        if (filesize($save['path']) > 20*1024*1024) fm_enqueue_r2_upload($save['path'],$remote);
                    }
                } else $results[] = ['success'=>false,'error'=>$save['message']];
            }
            fm_cache_delete('list_local_' . ($subdir ?: 'root'));
            echo json_encode(['status'=>200,'results'=>$results]); exit;

        // download_local (stream)
        case 'download_local':
            if (!_fm_is_logged()) { header('HTTP/1.1 403 Forbidden'); exit; }
            $file = $_GET['file'] ?? $_POST['file'] ?? '';
            if (!$file) { header('HTTP/1.1 400 Bad Request'); exit; }
            $file = urldecode($file);
            $info = fm_get_file_info($file);
            if (empty($info)) { header('HTTP/1.1 404 Not Found'); exit; }
            header_remove();
            fm_stream_file_download($info['full_path'], $info['name']);
            exit;

        // delete_local
        case 'delete_local':
            if (!_fm_is_logged()) { echo json_encode(['status'=>403,'error'=>'login required']); exit; }
            $path = $_POST['path'] ?? $_POST['file'] ?? '';
            if (!$path) { echo json_encode(['status'=>400,'error'=>'missing path']); exit; }
            $ok = fm_delete_local_recursive($path);
            if ($ok) { echo json_encode(['status'=>200,'message'=>'deleted']); } else echo json_encode(['status'=>500,'error'=>'delete failed']);
            exit;

        // upload_r2_from_local
        case 'upload_r2_from_local':
            if (!_fm_is_logged()) { echo json_encode(['status'=>403,'error'=>'login required']); exit; }
            $path = $_POST['path'] ?? $_GET['path'] ?? ($_POST['file'] ?? '');
            $mode = $_POST['mode'] ?? 'enqueue';
            if (!$path) { echo json_encode(['status'=>400,'error'=>'missing path']); exit; }
            $full = fm_get_local_dir() . '/' . ltrim($path,'/');
            if (!file_exists($full)) { echo json_encode(['status'=>404,'error'=>'not found']); exit; }
            $remote = 'files/' . ltrim($path,'/');
            if ($mode === 'immediate') {
                $r = fm_upload_to_r2($full, $remote);
                if ($r['success']) echo json_encode(['status'=>200,'url'=>$r['url'] ?? null]); else echo json_encode(['status'=>500,'error'=>$r['message'] ?? 'upload failed']);
            } else {
                if (fm_enqueue_r2_upload($full,$remote)) echo json_encode(['status'=>200,'message'=>'enqueued']);
                else echo json_encode(['status'=>500,'error'=>'enqueue failed']);
            }
            exit;

        // list_r2
        case 'list_r2':
            if (!_fm_is_logged()) { echo json_encode(['status'=>403,'error'=>'login required']); exit; }
            $prefix = $_GET['prefix'] ?? '';
            $res = fm_list_r2_cached($prefix);
            echo json_encode(['status'=>200,'objects'=>$res]); exit;

        // list_upload_queue
        case 'list_upload_queue':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $q = fm_get_pending_uploads(200);
            echo json_encode(['status'=>200,'queue'=>$q]); exit;

        // process_upload_queue (manual)
        case 'process_upload_queue':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $limit = (int)($_POST['limit'] ?? 20);
            $processed = fm_process_upload_queue_worker($limit);
            echo json_encode(['status'=>200,'processed'=>$processed]); exit;

        // create_full_backup
        case 'create_full_backup':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $res = fm_create_db_dump('db_backup');
            if (!$res['success']) { echo json_encode(['status'=>500,'error'=>$res['message'] ?? 'dump failed']); exit; }
            $remote = 'backups/'.$res['filename'];
            $enq = fm_enqueue_r2_upload($res['path'], $remote);
            if (!$enq) echo json_encode(['status'=>500,'error'=>'enqueue failed','filename'=>$res['filename']]); else echo json_encode(['status'=>200,'filename'=>$res['filename'],'path'=>$res['path']]);
            exit;

        // create_table_backup
        case 'create_table_backup':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $table = trim($_POST['table'] ?? '');
            if (!$table) { echo json_encode(['status'=>400,'error'=>'missing table']); exit; }
            $res = fm_create_table_dump($table,'table_backup');
            if (!$res['success']) echo json_encode(['status'=>500,'error'=>$res['message'] ?? 'dump failed']); else echo json_encode(['status'=>200,'filename'=>$res['filename'],'path'=>$res['path']]);
            exit;

        // list_db_backups
        case 'list_db_backups':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $q = trim($_GET['q'] ?? '');
            $dir = fm_get_local_dir();
            $out = [];
            if (is_dir($dir)) {
                $it = new DirectoryIterator($dir);
                foreach ($it as $file) {
                    if ($file->isFile()) {
                        $name = $file->getFilename();
                        if ($q && stripos($name,$q) === false) continue;
                        if (!preg_match('/\.(sql|sql\.gz)$/i', $name)) continue;
                        $out[] = ['name'=>$name,'size'=>$file->getSize(),'mtime'=>$file->getMTime(),'path'=>$dir.'/'.$name];
                    }
                }
                usort($out, function($a,$b){ return $b['mtime'] - $a['mtime']; });
            }
            echo json_encode(['status'=>200,'files'=>$out]); exit;

        // restore_db_local
        case 'restore_db_local':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $file = $_POST['file'] ?? '';
            $token = $_POST['confirm_token'] ?? '';
            $target = $_POST['target_db'] ?? '';
            if (!$file) { echo json_encode(['status'=>400,'error'=>'missing file']); exit; }
            if ($token !== 'RESTORE_NOW') { echo json_encode(['status'=>409,'error'=>'confirm_token required: send RESTORE_NOW']); exit; }
            $full = fm_get_local_dir() . '/' . ltrim($file,'/');
            if (!file_exists($full)) { echo json_encode(['status'=>404,'error'=>'file not found']); exit; }
            $snap = fm_create_db_dump('pre_restore_snapshot');
            $res = fm_restore_sql_gz_local($full, $target);
            if ($res['success']) echo json_encode(['status'=>200,'message'=>$res['message']]); else echo json_encode(['status'=>500,'error'=>$res['message']]);
            exit;

        // restore_db_r2
        case 'restore_db_r2':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $key = $_POST['r2_key'] ?? '';
            $token = $_POST['confirm_token'] ?? '';
            $target = $_POST['target_db'] ?? '';
            if (!$key) { echo json_encode(['status'=>400,'error'=>'missing r2_key']); exit; }
            if ($token !== 'RESTORE_NOW') { echo json_encode(['status'=>409,'error'=>'confirm_token required: send RESTORE_NOW']); exit; }
            $s3 = fm_init_s3();
            if (!$s3) { echo json_encode(['status'=>500,'error'=>'R2 not configured']); exit; }
            $tmp = tempnam(sys_get_temp_dir(), 'r2');
            try {
                $cfg = fm_get_config();
                $s3->getObject(['Bucket'=>$cfg['r2_bucket'],'Key'=>$key,'SaveAs'=>$tmp]);
                $res = fm_restore_sql_gz_local($tmp, $target);
                @unlink($tmp);
                if ($res['success']) echo json_encode(['status'=>200,'message'=>$res['message']]); else echo json_encode(['status'=>500,'error'=>$res['message']]);
            } catch (Exception $e) { echo json_encode(['status'=>500,'error'=>$e->getMessage()]); }
            exit;

        // auto_backup_run (for cron) - uses token or admin
        case 'auto_backup_run':
            // token from env AUTO_BACKUP_SECRET (set in .env)
            $token = $_GET['token'] ?? $_POST['token'] ?? '';
            $secret = getenv('AUTO_BACKUP_SECRET') ?: '';
            if (!(_fm_is_admin() || ($secret !== '' && $token === $secret))) { echo json_encode(['status'=>403,'error'=>'admin or valid token required']); exit; }
            // full db dump + optional per-table dumps
            $r1 = fm_create_db_dump('db_backup');
            $enqueued = false;
            if ($r1['success']) {
                $remote = 'backups/'.$r1['filename'];
                $enqueued = fm_enqueue_r2_upload($r1['path'],$remote);
            }
            // rotate old backups
            $rot = fm_enforce_retention();
            echo json_encode(['status'=>200,'dump'=>$r1,'enqueued'=>$enqueued,'rotation'=>$rot]); exit;

        // enforce_retention
        case 'enforce_retention':
            if (!_fm_is_admin()) { echo json_encode(['status'=>403,'error'=>'admin only']); exit; }
            $res = fm_enforce_retention();
            echo json_encode(['status'=>200,'result'=>$res]); exit;

        // get user quota
        case 'get_user_quota':
            if (!_fm_is_logged()) { echo json_encode(['status'=>403,'error'=>'login required']); exit; }
            $uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : _fm_user_id();
            $q = fm_get_user_quota($uid);
            echo json_encode(['status'=>200,'quota'=>$q['quota'],'used'=>$q['used']]); exit;

        default:
            echo json_encode(['status'=>404,'error'=>'unknown action']);
            exit;
    }
} catch (Exception $e) {
    echo json_encode(['status'=>500,'error'=>$e->getMessage()]);
    exit;
}

// helper: streaming download
if (!function_exists('fm_stream_file_download')) {
    function fm_stream_file_download($fullPath, $downloadName = '') {
        if (!file_exists($fullPath)) { header("HTTP/1.1 404 Not Found"); exit; }
        $dn = $downloadName ?: basename($fullPath);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($dn).'"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath); exit;
    }
}