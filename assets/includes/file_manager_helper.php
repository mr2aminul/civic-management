<?php
// assets/includes/file_manager_helper.php
// File manager & backup helper - full-featured (R2, queue, db dumps, rotate, cache, quotas)
require_once "assets/libraries/aws-sdk-php/vendor/autoload.php";

// Load .env (if exists) into environment variables
if (!function_exists('fm_load_env')) {
    function fm_load_env($path = null) {
        $path = $path ?: __DIR__ . '/../../.env';
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line,'=') === false) continue;
            [$k,$v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if ($k === '') continue;
            if ((substr($v,0,1)==='"' && substr($v,-1)==='"') || (substr($v,0,1)==="'" && substr($v,-1)==="'")) $v = substr($v,1,-1);
            putenv("$k=$v"); $_ENV[$k] = $v;
        }
    }
}
fm_load_env();

// ---------------- CONFIG ----------------
if (!function_exists('fm_get_config')) {
    function fm_get_config(): array {
        // defaults & env overrides (use env or explicit values)
        $cfg = [];
        $cfg['local_dir'] = getenv('BACKUP_LOCAL_DIR') ?: '/home/civicbd/civicgroup/backups';
        $cfg['r2_key'] = getenv('R2_ACCESS_KEY_ID') ?: getenv('R2_ACCESS_KEY') ?: '2c3443e44db2a753265134fbe0a65f67';
        $cfg['r2_secret'] = getenv('R2_SECRET_ACCESS_KEY') ?: getenv('R2_SECRET_KEY') ?: 'd24621ce0729f68776d155a3ead711679c249baafffd5a5dc581ddcf185d786e';
        $cfg['r2_bucket'] = getenv('R2_BUCKET') ?: 'civic-management';
        $cfg['r2_endpoint'] = getenv('R2_ENDPOINT') ?: 'https://90f483339efd91e1a8819e04ba6e31e6.r2.cloudflarestorage.com';
        $cfg['default_user_quota'] = (int)((float)(getenv('DEFAULT_USER_QUOTA_GB') ?: 1) * 1024 * 1024 * 1024);
        $cfg['auto_upload_exts'] = array_filter(array_map('trim', explode(',', getenv('AUTO_UPLOAD_TYPES') ?: 'sql,zip,xlsx,docx,pdf')));
        $cfg['auto_upload_prefixes'] = array_filter(array_map('trim', explode(',', getenv('AUTO_UPLOAD_PREFIXES') ?: 'db_,sys_')));
        $cfg['retention_days'] = (int)(getenv('DB_BACKUP_RETENTION_DAYS') ?: 30);
        $cfg['cache_ttl'] = (int)(getenv('FM_CACHE_TTL') ?: 300);
        $cfg['queue_json'] = rtrim($cfg['local_dir'], '/') . '/.fm_upload_queue.json';
        $cfg['cache_dir'] = rtrim($cfg['local_dir'], '/') . '/.cache';
        return $cfg;
    }
}

// ---------------- helpers ----------------
if (!function_exists('fm_ensure_dirs')) {
    function fm_ensure_dirs() {
        $cfg = fm_get_config();
        @mkdir($cfg['local_dir'], 0755, true);
        @mkdir($cfg['cache_dir'], 0755, true);
    }
}
fm_ensure_dirs();

if (!function_exists('fm_safe_basename')) {
    function fm_safe_basename($name) {
        $name = preg_replace('#[\\\\/]+#','_', $name);
        $name = preg_replace('/[^A-Za-z0-9_\-\. ]/','_', $name);
        $name = preg_replace('/\s+/','_', $name);
        return trim($name, '_');
    }
}

if (!function_exists('fm_get_local_dir')) {
    function fm_get_local_dir(): string { $cfg = fm_get_config(); return rtrim($cfg['local_dir'], '/'); }
}

if (!function_exists('fm_cache_get')) {
    function fm_cache_get($key) {
        $cfg = fm_get_config();
        $fname = $cfg['cache_dir'].'/'.preg_replace('/[^A-Za-z0-9_\-]/','_',$key).'.json';
        if (!file_exists($fname)) return null;
        if ($cfg['cache_ttl'] > 0 && filemtime($fname) < time() - $cfg['cache_ttl']) { @unlink($fname); return null; }
        $c = json_decode(@file_get_contents($fname), true);
        return $c;
    }
}
if (!function_exists('fm_cache_set')) {
    function fm_cache_set($key, $val) { $cfg = fm_get_config(); $fname = $cfg['cache_dir'].'/'.preg_replace('/[^A-Za-z0-9_\-]/','_',$key).'.json'; @file_put_contents($fname, json_encode($val)); }
}
if (!function_exists('fm_cache_delete')) {
    function fm_cache_delete($key) { $cfg = fm_get_config(); $fname = $cfg['cache_dir'].'/'.preg_replace('/[^A-Za-z0-9_\-]/','_',$key).'.json'; if (file_exists($fname)) @unlink($fname); }
}

// ---------------- S3 / R2 ----------------
// Use AWS SDK if available; fallback to rclone if installed (best-effort)
if (!function_exists('fm_init_s3')) {
    function fm_init_s3() {
        $cfg = fm_get_config();
        if (empty($cfg['r2_key']) || empty($cfg['r2_secret']) || empty($cfg['r2_bucket']) || empty($cfg['r2_endpoint'])) return null;
        if (!class_exists('Aws\\S3\\S3Client')) return null;
        try {
            $s3 = new Aws\S3\S3Client([
                'version'=>'latest',
                'region'=>getenv('R2_REGION') ?: 'auto',
                'endpoint'=>$cfg['r2_endpoint'],
                'use_path_style_endpoint' => false,
                'credentials'=>['key'=>$cfg['r2_key'],'secret'=>$cfg['r2_secret']],
            ]);
            return $s3;
        } catch (Exception $e) { return null; }
    }
}

if (!function_exists('fm_upload_to_r2')) {
    function fm_upload_to_r2($localPath, $remoteKey) {
        $cfg = fm_get_config();
        if (!file_exists($localPath)) return ['success'=>false,'message'=>'local not found'];
        $s3 = fm_init_s3();
        if ($s3) {
            try {
                $params = ['Bucket'=>$cfg['r2_bucket'],'Key'=>$remoteKey,'SourceFile'=>$localPath,'ACL'=>'private'];
                $result = $s3->putObject($params);
                fm_cache_delete('r2_'.str_replace(['/','\\'],'_',$remoteKey));
                fm_log_backup_event('r2_upload', $remoteKey, filesize($localPath));
                return ['success'=>true,'url'=>method_exists($s3,'getObjectUrl') ? $s3->getObjectUrl($cfg['r2_bucket'],$remoteKey) : null];
            } catch (Exception $e) { return ['success'=>false,'message'=>$e->getMessage()]; }
        }
        // fallback: try rclone if configured via RCLONE_REMOTE env
        $rclone = getenv('RCLONE_PATH') ?: '/usr/bin/rclone';
        $remoteName = getenv('RCLONE_REMOTE_NAME') ?: '';
        if (is_executable($rclone) && $remoteName) {
            $cmd = escapeshellcmd($rclone).' copyto '.escapeshellarg($localPath).' '.escapeshellarg($remoteName.':'.$remoteKey).' 2>&1';
            exec($cmd, $out, $ret);
            if ($ret === 0) return ['success'=>true,'url'=>null];
            return ['success'=>false,'message'=>'rclone failed: '.implode("\n",$out)];
        }
        return ['success'=>false,'message'=>'No S3 client and rclone not configured'];
    }
}

// ---------------- Queue (DB-backed preferred, JSON fallback) ----------------
// Recommended table (create it if possible):
/*
CREATE TABLE fm_upload_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  local_path VARCHAR(1024) NOT NULL,
  remote_key VARCHAR(1024) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  message TEXT,
  created_at DATETIME,
  updated_at DATETIME,
  UNIQUE KEY (local_path, remote_key(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

if (!function_exists('fm_enqueue_r2_upload')) {
    function fm_enqueue_r2_upload($localPath, $remoteKey) {
        global $db;
        $now = date('Y-m-d H:i:s');
        // try DB (joshcam $db)
        if (isset($db) && method_exists($db,'insert')) {
            try {
                $db->insert('fm_upload_queue', ['local_path'=>$localPath,'remote_key'=>$remoteKey,'status'=>'pending','message'=>'','created_at'=>$now,'updated_at'=>$now]);
                return true;
            } catch (Exception $e) { /* continue fallback */ }
        }
        // try PDO (if DB_DSN present)
        if (function_exists('fm_get_pdo')) {
            $pdo = fm_get_pdo();
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO fm_upload_queue (local_path,remote_key,status,message,created_at,updated_at) VALUES (:lp,:rk,'pending','',:c,:u)");
                    $stmt->execute([':lp'=>$localPath,':rk'=>$remoteKey,':c'=>$now,':u'=>$now]);
                    return true;
                } catch (Exception $e) { /* fallback json */ }
            }
        }
        // JSON fallback queue
        $cfg = fm_get_config();
        $file = $cfg['queue_json'];
        $q = [];
        if (file_exists($file)) $q = json_decode(file_get_contents($file), true) ?: [];
        $q[] = ['id'=>uniqid('q',true),'local_path'=>$localPath,'remote_key'=>$remoteKey,'status'=>'pending','message'=>'','created_at'=>$now,'updated_at'=>$now];
        @file_put_contents($file, json_encode($q));
        return true;
    }
}

if (!function_exists('fm_get_pending_uploads')) {
    function fm_get_pending_uploads($limit = 20) {
        global $db;
        if (isset($db) && method_exists($db,'where')) {
            try { return $db->where('status','pending')->orderBy('created_at','ASC')->get('fm_upload_queue', $limit); } catch (Exception $e) {}
        }
        $pdo = fm_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM fm_upload_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT :lim");
                $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }
        $cfg = fm_get_config(); $file = $cfg['queue_json'];
        if (!file_exists($file)) return [];
        $q = json_decode(file_get_contents($file), true) ?: [];
        $out = [];
        foreach ($q as $it) if ($it['status'] === 'pending') $out[] = $it;
        return array_slice($out, 0, $limit);
    }
}

if (!function_exists('fm_mark_upload_status')) {
    function fm_mark_upload_status($id, $status, $message = '') {
        global $db;
        $now = date('Y-m-d H:i:s');
        if (isset($db) && method_exists($db,'where')) {
            try { $db->where('id', $id); return (bool)$db->update('fm_upload_queue', ['status'=>$status,'message'=>$message,'updated_at'=>$now]); } catch (Exception $e) {}
        }
        $pdo = fm_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE fm_upload_queue SET status=:s, message=:m, updated_at=:u WHERE id = :id");
                return $stmt->execute([':s'=>$status,':m'=>$message,':u'=>$now,':id'=>$id]);
            } catch (Exception $e) {}
        }
        // json fallback
        $cfg = fm_get_config(); $file = $cfg['queue_json'];
        if (!file_exists($file)) return false;
        $q = json_decode(file_get_contents($file), true) ?: [];
        foreach ($q as &$it) {
            if ((isset($it['id']) && $it['id'] == $id) || (isset($it['id']) && (string)$it['id'] === (string)$id)) {
                $it['status'] = $status; $it['message'] = $message; $it['updated_at'] = $now;
            }
        }
        @file_put_contents($file, json_encode($q));
        return true;
    }
}

// Worker function
if (!function_exists('fm_process_upload_queue_worker')) {
    function fm_process_upload_queue_worker($limit = 10) {
        $jobs = fm_get_pending_uploads($limit);
        $processed = [];
        foreach ($jobs as $job) {
            $id = $job['id'] ?? (isset($job['local_path']) ? md5($job['local_path'].$job['remote_key']) : uniqid('q',true));
            fm_mark_upload_status($id, 'processing', '');
            $local = $job['local_path']; $remote = $job['remote_key'];
            if (!file_exists($local)) { fm_mark_upload_status($id,'error','local not found'); $processed[] = ['id'=>$id,'status'=>'local_missing']; continue; }
            $r = fm_upload_to_r2($local, $remote);
            if ($r['success']) { fm_mark_upload_status($id,'done',''); $processed[] = ['id'=>$id,'status'=>'done','url'=>$r['url'] ?? null]; }
            else { fm_mark_upload_status($id,'error',$r['message'] ?? 'upload failed'); $processed[] = ['id'=>$id,'status'=>'error','error'=>$r['message'] ?? 'upload failed']; }
        }
        return $processed;
    }
}

// ---------------- DB dump & restore ----------------
if (!function_exists('fm_create_db_dump')) {
    function fm_create_db_dump($label = 'db_backup') {
        if (!is_executable('/usr/bin/mysqldump') && !is_executable('/usr/local/bin/mysqldump')) {
            $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null'));
        } else {
            $mysqldump = is_executable('/usr/bin/mysqldump') ? '/usr/bin/mysqldump' : '/usr/local/bin/mysqldump';
        }
        if (!$mysqldump) $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null'));
        if (!$mysqldump) return ['success'=>false,'message'=>'mysqldump not found'];

        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dbName = getenv('DB_NAME') ?: '';
        if (!$dbName) return ['success'=>false,'message'=>'DB_NAME not provided'];

        fm_ensure_dirs();
        $ts = date('Ymd_His');
        $filename = fm_safe_basename($label . '_' . $ts . '.sql.gz');
        $path = fm_get_local_dir() . '/' . $filename;
        $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
        $cnf = "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\n";
        file_put_contents($tmpcnf, $cnf);
        $cmd = sprintf('%s --defaults-extra-file=%s %s 2>&1 | gzip -c > %s', escapeshellcmd($mysqldump), escapeshellarg($tmpcnf), escapeshellarg($dbName), escapeshellarg($path));
        exec($cmd, $out, $ret); @unlink($tmpcnf);
        if ($ret === 0 && file_exists($path)) { fm_log_backup_event('db_full', $filename, filesize($path)); return ['success'=>true,'path'=>$path,'filename'=>$filename]; }
        return ['success'=>false,'message'=>'mysqldump failed: '.implode("\n",$out)];
    }
}

if (!function_exists('fm_create_table_dump')) {
    function fm_create_table_dump($table, $label = 'table_backup') {
        if (!$table) return ['success'=>false,'message'=>'missing table'];
        $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null'));
        if (!$mysqldump) return ['success'=>false,'message'=>'mysqldump not found'];
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dbName = getenv('DB_NAME') ?: '';
        if (!$dbName) return ['success'=>false,'message'=>'DB_NAME not provided'];
        fm_ensure_dirs();
        $ts = date('Ymd_His');
        $filename = fm_safe_basename($label . '_' . $table . '_' . $ts . '.sql.gz');
        $path = fm_get_local_dir() . '/' . $filename;
        $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
        $cnf = "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\n";
        file_put_contents($tmpcnf, $cnf);
        $cmd = sprintf('%s --defaults-extra-file=%s %s %s 2>&1 | gzip -c > %s', escapeshellcmd($mysqldump), escapeshellarg($tmpcnf), escapeshellarg($dbName), escapeshellarg($table), escapeshellarg($path));
        exec($cmd, $out, $ret); @unlink($tmpcnf);
        if ($ret === 0 && file_exists($path)) { fm_log_backup_event('db_table', $filename, filesize($path)); return ['success'=>true,'path'=>$path,'filename'=>$filename]; }
        return ['success'=>false,'message'=>'mysqldump failed: '.implode("\n",$out)];
    }
}

if (!function_exists('fm_restore_sql_gz_local')) {
    function fm_restore_sql_gz_local($filePath, $targetDb = '') {
        if (!file_exists($filePath)) return ['success'=>false,'message'=>'file not found'];
        $mysql = trim(shell_exec('which mysql 2>/dev/null'));
        $gunzip = trim(shell_exec('which gunzip 2>/dev/null')) ?: 'gzip -d -c';
        $dbUser = getenv('DB_USER') ?: '';
        $dbPass = getenv('DB_PASSWORD') ?: '';
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $dbName = $targetDb ?: (getenv('DB_NAME') ?: '');
        if (!$dbName) return ['success'=>false,'message'=>'target DB not specified'];
        if ($mysql) {
            $tmpcnf = tempnam(sys_get_temp_dir(), 'mycnf');
            $cnf = "[client]\nuser={$dbUser}\npassword=\"{$dbPass}\"\nhost={$dbHost}\n";
            file_put_contents($tmpcnf, $cnf);
            $cmd = sprintf('%s -c %s | %s --defaults-extra-file=%s %s 2>&1', escapeshellcmd($gunzip), escapeshellarg($filePath), escapeshellcmd($mysql), escapeshellarg($tmpcnf), escapeshellarg($dbName));
            exec($cmd, $out, $ret); @unlink($tmpcnf);
            if ($ret === 0) return ['success'=>true,'message'=>'restore completed'];
            return ['success'=>false,'message'=>'restore failed: '.implode("\n",$out)];
        } else {
            // fallback to php gzread + pdo (risky for large files)
            $contents = @gzfile($filePath);
            if ($contents === false) return ['success'=>false,'message'=>'cannot read gz'];
            $sql = implode('', $contents);
            $pdo = fm_get_pdo();
            if (!$pdo) return ['success'=>false,'message'=>'PDO not available'];
            try { $pdo->exec($sql); return ['success'=>true,'message'=>'restore completed (pdo)']; } catch (Exception $e) { return ['success'=>false,'message'=>$e->getMessage()]; }
        }
    }
}

// ---------------- rotation ----------------
if (!function_exists('fm_rotate_local_db_backups')) {
    function fm_rotate_local_db_backups() {
        $cfg = fm_get_config();
        $dir = fm_get_local_dir();
        $deleted = 0; $errors = [];
        $it = new DirectoryIterator($dir);
        foreach ($it as $file) {
            if ($file->isFile()) {
                $name = $file->getFilename();
                if (!preg_match('/\.(sql|sql\.gz)$/i', $name)) continue;
                if ($file->getMTime() < time() - ($cfg['retention_days'] * 86400)) {
                    if (@unlink($file->getPathname())) $deleted++; else $errors[] = $file->getPathname();
                }
            }
        }
        return ['deleted'=>$deleted,'errors'=>$errors];
    }
}

if (!function_exists('fm_enforce_retention')) {
    function fm_enforce_retention() { return fm_rotate_local_db_backups(); }
}

// ---------------- Local FS operations ----------------
if (!function_exists('fm_list_local_folder')) {
    function fm_list_local_folder($rel = '') {
        $base = fm_get_local_dir();
        $full = ($rel === '') ? $base : $base . '/' . ltrim($rel, '/');
        $folders = []; $files = [];
        if (!is_dir($full)) return ['folders'=>[], 'files'=>[]];
        $it = new DirectoryIterator($full);
        foreach ($it as $item) {
            if ($item->isDot()) continue;
            $pathRel = ($rel === '') ? $item->getFilename() : rtrim($rel,'/') . '/' . $item->getFilename();
            if ($item->isDir()) $folders[] = ['name'=>$item->getFilename(),'path'=>$pathRel,'mtime'=>$item->getMTime()];
            else $files[] = ['name'=>$item->getFilename(),'path'=>$pathRel,'size'=>$item->getSize(),'mtime'=>$item->getMTime()];
        }
        usort($folders, function($a,$b){ return strcasecmp($a['name'],$b['name']); });
        usort($files, function($a,$b){ return $b['mtime'] - $a['mtime']; });
        return ['folders'=>$folders,'files'=>$files];
    }
}

if (!function_exists('fm_save_uploaded_local')) {
    function fm_save_uploaded_local($file, $subdir = '') {
        $base = fm_get_local_dir();
        $sub = trim($subdir, '/');
        $destDir = $sub ? ($base . '/' . $sub) : $base;
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        $name = fm_safe_basename($file['name'] ?? basename($file['tmp_name']));
        $target = $destDir . '/' . $name;
        $orig = pathinfo($name, PATHINFO_FILENAME); $ext = pathinfo($name, PATHINFO_EXTENSION);
        $i = 1;
        while (file_exists($target)) {
            $name = $orig . '_' . $i . ($ext ? '.' . $ext : '');
            $target = $destDir . '/' . $name; $i++;
        }
        $moved = false;
        if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) $moved = move_uploaded_file($file['tmp_name'], $target);
        else $moved = copy($file['tmp_name'], $target);
        if (!$moved) return ['success'=>false,'message'=>'save failed'];
        return ['success'=>true,'path'=>$target,'filename'=>basename($target)];
    }
}

if (!function_exists('fm_get_file_info')) {
    function fm_get_file_info($relPath) {
        $full = fm_get_local_dir() . '/' . ltrim($relPath,'/');
        if (!file_exists($full)) return [];
        return ['name'=>basename($relPath),'path'=>ltrim($relPath,'/'),'full_path'=>$full,'size'=>is_file($full)?filesize($full):0,'mtime'=>filemtime($full),'is_folder'=>is_dir($full)?1:0];
    }
}

if (!function_exists('fm_delete_local_recursive')) {
    function fm_delete_local_recursive($relPath) {
        $full = fm_get_local_dir() . '/' . ltrim($relPath,'/');
        if (!file_exists($full)) return false;
        if (is_file($full)) return @unlink($full);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        $ok = true;
        foreach ($it as $file) {
            if ($file->isDir()) $ok = $ok && @rmdir($file->getRealPath());
            else $ok = $ok && @unlink($file->getRealPath());
        }
        $ok = $ok && @rmdir($full);
        return $ok;
    }
}

// ---------------- Quota (fm_users table recommended) ----------------
/*
CREATE TABLE fm_users (
  user_id INT PRIMARY KEY,
  quota BIGINT NOT NULL,
  used BIGINT NOT NULL DEFAULT 0,
  updated_at DATETIME
);
*/
if (!function_exists('fm_get_pdo')) {
    function fm_get_pdo() {
        static $pdo=null;
        if ($pdo) return $pdo;
        $dsn = getenv('DB_DSN') ?: null;
        $user = getenv('DB_USER') ?: null;
        $pass = getenv('DB_PASSWORD') ?: null;
        if (!$dsn) return null;
        try { $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); return $pdo; } catch (Exception $e) { return null; }
    }
}

if (!function_exists('fm_get_user_quota')) {
    function fm_get_user_quota($uid) {
        $cfg = fm_get_config();
        $pdo = fm_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT quota, used FROM fm_users WHERE user_id = :uid");
                $stmt->execute([':uid'=>$uid]); $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($r) return ['quota'=>(int)$r['quota'],'used'=>(int)$r['used']];
                $stmt2 = $pdo->prepare("INSERT INTO fm_users (user_id, quota, used, updated_at) VALUES (:uid, :quota, 0, :u)");
                $stmt2->execute([':uid'=>$uid, ':quota'=>$cfg['default_user_quota'], ':u'=>date('Y-m-d H:i:s')]);
                return ['quota'=>$cfg['default_user_quota'],'used'=>0];
            } catch (Exception $e) {}
        }
        return ['quota'=>$cfg['default_user_quota'],'used'=>0];
    }
}
if (!function_exists('fm_update_user_used')) {
    function fm_update_user_used($uid, $delta) {
        $pdo = fm_get_pdo(); if (!$pdo) return false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT used FROM fm_users WHERE user_id = :uid FOR UPDATE");
            $stmt->execute([':uid'=>$uid]); $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$r) {
                $quota = fm_get_config()['default_user_quota'];
                $used = max(0, $delta);
                $stmt2 = $pdo->prepare("INSERT INTO fm_users (user_id, quota, used, updated_at) VALUES (:uid, :q, :u, :t)");
                $stmt2->execute([':uid'=>$uid,':q'=>$quota,':u'=>$used,':t'=>date('Y-m-d H:i:s')]);
                $pdo->commit(); return true;
            }
            $new = max(0, (int)$r['used'] + (int)$delta);
            $stmt2 = $pdo->prepare("UPDATE fm_users SET used=:used, updated_at=:t WHERE user_id = :uid");
            $stmt2->execute([':used'=>$new,':t'=>date('Y-m-d H:i:s'),':uid'=>$uid]);
            $pdo->commit(); return true;
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return false; }
    }
}

// ---------------- logging ----------------
if (!function_exists('fm_log_backup_event')) {
    function fm_log_backup_event($type, $filename, $size = 0, $user_id = 0) {
        $pdo = fm_get_pdo();
        $now = date('Y-m-d H:i:s');
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO backups (type, filename, size, user_id, created_at) VALUES (:t,:f,:s,:u,:c)");
                $stmt->execute([':t'=>$type,':f'=>$filename,':s'=>$size,':u'=>$user_id,':c'=>$now]); return true;
            } catch (Exception $e) {}
        }
        // fallback to file log
        $f = fm_get_local_dir() . '/.fm_helper.log';
        $line = "[$now] $type $filename $size uid:$user_id\n";
        @file_put_contents($f, $line, FILE_APPEND);
        return true;
    }
}

/* End of file_manager_helper.php */
