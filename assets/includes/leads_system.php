<?php
// cron-job.php - improved lead importer & deterministic batch-target distributor
// - Batch-safe: updates in-memory state while assigning to avoid repeating same user
// - Deterministic per-batch targets from normalized quotas (fair rounding + leftovers)
// - Honors phone override only if previous owner has remaining batch capacity
// - Excludes participating=0 and raw_weight<=0 users from assignment
// - Excludes admin (user_id=1) from assignment pool
// - Inserts leftover/skipped leads as unassigned (member=0) by default (configurable)
// - Logging to debug2.log
//
// Place this file where your app bootstrap (db and constants) are available.

// ini_set('display_errors', '1');
// error_reporting(E_ALL);
// date_default_timezone_set('Asia/Dhaka');

$show_import_status = false; // set false in production for silence

require_once ROOT_DIR . "assets/libraries/web-push/vendor/autoload.php";
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;

// ----------------- CONFIG -----------------
if (!defined('BALANCING_LOOKBACK_DAYS')) define('BALANCING_LOOKBACK_DAYS', 90);
if (!defined('PHONE_OVERRIDE_MAX_DAYS')) define('PHONE_OVERRIDE_MAX_DAYS', 30);
// Maximum number of leads a single user can receive in a single cron batch (hard cap)
// Set 0 to disable.
if (!defined('MAX_ASSIGN_PER_USER_PER_BATCH')) define('MAX_ASSIGN_PER_USER_PER_BATCH', 1);

// Distribution strategy: 'balanced' (recommended), 'round_robin', 'weighted_random'
if (!defined('DISTRIBUTION_STRATEGY')) define('DISTRIBUTION_STRATEGY', 'balanced');

// If true, when computed targets are zero we fall back to equal distribution (false recommended)
if (!defined('DISTRIBUTION_ALLOW_EQUAL_FALLBACK')) define('DISTRIBUTION_ALLOW_EQUAL_FALLBACK', false);

// If true, skipped leads (no capacity) will be inserted as member=0 assigned=0 so visible for manual handling.
// If false, skipped leads will be ignored (only logged). Default: true.
if (!defined('INSERT_SKIPPED_AS_UNASSIGNED')) define('INSERT_SKIPPED_AS_UNASSIGNED', false);

// Tie-breaker method for distributing leftover fractions: 'largest_fraction' (default) or 'join_date'
if (!defined('DISTRIBUTION_LEFTOVER_METHOD')) define('DISTRIBUTION_LEFTOVER_METHOD', 'largest_fraction');

// Don't auto-assign to admin by default
if (!defined('ASSIGN_TO_ADMIN_IF_NO_ONE')) define('ASSIGN_TO_ADMIN_IF_NO_ONE', false);

$vapidKeysFile = ROOT_DIR . '/vapid_keys.json';
if (!file_exists($vapidKeysFile)) {
    $vapid_keys = VAPID::createVapidKeys();
    file_put_contents($vapidKeysFile, json_encode($vapid_keys));
} else {
    $vapid_keys = json_decode(file_get_contents($vapidKeysFile), true);
}
$wo = $wo ?? [];
$wo['config']['vapid_public_key'] = $vapid_keys['publicKey'] ?? '';
$wo['config']['vapid_private_key'] = $vapid_keys['privateKey'] ?? '';

// ----------------- UTIL, NOTIFICATIONS & LOGGING -----------------

function logDebug($message) {
    $file = ROOT_DIR . '/debug2.log';
    file_put_contents($file, "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL, FILE_APPEND);
}

// Helpers for sanitization and mojibake fixing
if (!function_exists('looks_like_bengali')) {
    function looks_like_bengali($s) {
        // Returns true if string contains characters in Bengali Unicode block
        return (bool) preg_match('/\p{Bengali}/u', $s);
    }
}

if (!function_exists('try_fix_mojibake')) {
    /**
     * Try common recovery encodings for double-encoded text.
     * Returns best candidate (original if nothing better found).
     */
    function try_fix_mojibake(string $orig) : string {
        // if already contains Bengali, keep it
        if (looks_like_bengali($orig)) return $orig;

        $candidates = [];

        // 1) common windows-1252 -> UTF-8 fix
        $candidates[] = @mb_convert_encoding($orig, 'UTF-8', 'Windows-1252');

        // 2) ISO-8859-1 -> UTF-8
        $candidates[] = @mb_convert_encoding($orig, 'UTF-8', 'ISO-8859-1');

        // 3) utf8_decode (treat bytes as UTF-8 then decode to ISO-8859-1, then convert back)
        $decoded = @utf8_decode($orig);
        $candidates[] = @mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1');

        // 4) try reversing a common double-utf8 pattern: decode as UTF-8 then re-encode
        $candidates[] = @mb_convert_encoding($orig, 'ISO-8859-1', 'UTF-8');

        // 5) last resort: clean hex-like sequences (rare)
        $candidates[] = preg_replace_callback('/(?:C[0-9A-F]{2})+/i', function($m){
            $hex = preg_replace('/[^0-9A-F]/i','',$m[0]);
            // attempt convert hex string to bytes then to utf8
            $bytes = pack('H*', $hex);
            return @mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1') ?: $m[0];
        }, $orig);

        // Evaluate candidates - prefer one that contains Bengali characters
        foreach ($candidates as $cand) {
            if (!is_string($cand)) continue;
            if (looks_like_bengali($cand)) {
                // normalize whitespace and return
                $cand = trim($cand);
                return $cand;
            }
        }

        // If none have Bengali, pick the candidate with the fewest replacement chars (�) and most letters
        $best = $orig;
        $bestScore = score_string($orig);
        foreach ($candidates as $cand) {
            if (!is_string($cand) || $cand === '') continue;
            $s = score_string($cand);
            if ($s > $bestScore) { $bestScore = $s; $best = $cand; }
        }
        return trim($best);
    }
}

if (!function_exists('score_string')) {
    function score_string($s) {
        $s = (string)$s;
        $len = mb_strlen($s, 'UTF-8') ?: 1;
        $repl = preg_match_all('/\x{FFFD}/u', $s);
        $letters = preg_match_all('/\p{L}/u', $s);
        $score = ($letters * 2) - ($repl * 10) + (int)($len / 10);
        return $score;
    }
}

if (!function_exists('fix_double_encoded_utf8')) {
    function fix_double_encoded_utf8($s) {
        // If not a string or already valid UTF-8, return as-is (but still normalize)
        if ($s === null) return '';
        $s = (string)$s;
        // quick guard: if it's valid UTF-8 and contains multi-byte characters, assume OK
        if (mb_check_encoding($s, 'UTF-8')) {
            // But some mojibake sequences are valid UTF-8 (C3A0... etc). Detect those hex patterns:
            $hex = bin2hex($s);
            // pattern C3A0 is common for doubled E0 A6 .. sequences; if found, try latin1->utf8 conversion
            if (stripos($hex, 'c3a0') !== false || stripos($hex, 'c3a6') !== false || stripos($hex, 'c2a6') !== false) {
                // convert as if the bytes are latin1 to UTF-8
                $try = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
                if ($try && mb_check_encoding($try, 'UTF-8')) return $try;
            }
            return $s;
        } else {
            // not valid UTF-8 -> convert from latin1
            $conv = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
            if ($conv && mb_check_encoding($conv, 'UTF-8')) return $conv;
            // fallback attempt: replace invalid bytes
            return @mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
    }
}

if (!function_exists('sanitize_lead_field')) {
    function sanitize_lead_field($value, $maxlen = 300) {
        if ($value === null) return '';
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $value = (string)$value;
        }
        $value = trim($value);
        
        $value = fix_double_encoded_utf8($value);
        
        if ($value === '') return '';
        if (mb_check_encoding($value, 'UTF-8') && looks_like_bengali($value)) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u','', $value);
            return mb_substr(trim($value), 0, $maxlen, 'UTF-8');
        }
        $fixed = try_fix_mojibake($value);
        $fixed = html_entity_decode($fixed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $fixed = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u','', $fixed);
        if (!mb_check_encoding($fixed, 'UTF-8')) {
            $fixed2 = @mb_convert_encoding($fixed, 'UTF-8', 'auto');
            if ($fixed2 !== false && mb_check_encoding($fixed2, 'UTF-8')) $fixed = $fixed2;
        }
        $fixed = trim($fixed);
        if ($fixed === '') {
            $fallback = preg_replace('/[^\P{C}\n\r\t]+/u', '', $value);
            $fixed = trim($fallback);
        }
        if ($maxlen > 0 && mb_strlen($fixed, 'UTF-8') > $maxlen) {
            $fixed = mb_substr($fixed, 0, $maxlen, 'UTF-8');
        }
        return $fixed;
    }
}


function get_user_subscription($user_id) {
    $subscriptionFile = ROOT_DIR . '/subscriptions.json';
    if (!file_exists($subscriptionFile)) {
        throw new Exception("Subscription file not found: {$subscriptionFile}");
    }
    $data = json_decode(file_get_contents($subscriptionFile), true);
    return $data[$user_id] ?? [];
}

function sendWebNotification($user_id, $title, $message, $url = '', $image = '') {
    global $vapid_keys;
    try {
        $subscriptions = get_user_subscription($user_id);
        if (empty($subscriptions)) {
            logDebug("No subscriptions for user {$user_id}");
            return false;
        }
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => 'mailto:admin@civicgroupbd.com',
                'publicKey' => $vapid_keys['publicKey'],
                'privateKey' => $vapid_keys['privateKey'],
            ],
        ]);
        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'icon' => 'https://civicgroupbd.com/manage/assets/images/logo-icon-2.png',
            'badge' => 'https://civicgroupbd.com/manage/assets/images/logo-icon-2.png',
            'onclic_url' => $url ?: 'https://civicgroupbd.com/management',
            'image' => $image ?: null,
        ]);
        $success = 0;
        foreach ($subscriptions as $s) {
            try {
                $sub = Subscription::create([
                    'endpoint' => $s['endpoint'],
                    'publicKey' => $s['keys']['p256dh'],
                    'authToken' => $s['keys']['auth'],
                ]);
                $result = $webPush->sendOneNotification($sub, $payload);
                if ($result->isSuccess()) $success++;
            } catch (Exception $e) {
                logDebug("sendWebNotification inner: " . $e->getMessage());
            }
        }
        return $success > 0;
    } catch (Exception $e) {
        logDebug("sendWebNotification error: " . $e->getMessage());
        return false;
    }
}

if (!function_exists('notifyUser')) {
    function notifyUser($db, $user_id, $subject, $comment, $url) {
        if (intval($user_id) === 1) return true; // skip admin if desired
        if (intval($user_id) === 0) return true; // skip unassigned
        $ok = $db->insert(NOTIFICATION, [
            'subject' => $subject,
            'comment' => $comment,
            'type'    => 'leads',
            'url'     => $url,
            'user_id' => $user_id
        ]);
        if ($ok) {
            @sendWebNotification($user_id, $subject, $comment, $url);
            return true;
        }
        throw new Exception("Failed to insert notification for user {$user_id}: " . $db->getLastError());
    }
}

function logActivity($feature, $activityType, $details = null, $userId = false) {
    global $db, $wo;
    if (!$userId) $userId = $wo['user']['user_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $device = $_SERVER['HTTP_USER_AGENT'] ?? 'cron';
    $windows = ['login'=>300,'view'=>60,'create'=>5,'edit'=>5,'update'=>5,'delete'=>5,'message'=>10,'comment'=>30,'other'=>60,'error'=>300];
    $window = $windows[$activityType] ?? 5;
    $now = time();
    $start = $now - $window;
    $exists = $db->where('user_id', $userId)
                 ->where('feature', $feature)
                 ->where('activity_type', $activityType)
                 ->where('created_at', $start, '>=')
                 ->where('created_at', $now, '<=')
                 ->getOne('activity_logs');
    if (!$exists) {
        $db->insert('activity_logs', [
            'user_id' => $userId,
            'feature' => $feature,
            'activity_type' => $activityType,
            'details' => $details,
            'ip_address' => $ip,
            'device_info' => $device,
            'created_at' => $now
        ]);
        return true;
    }
    return false;
}

// ----------------- FACEBOOK SDK -----------------
include_once(ROOT_DIR . "assets/libraries/facebook-graph/vendor/autoload.php");
use JanuSoftware\Facebook\Facebook;
$fb_connect = new Facebook([
    'app_id' => '539681107316774',
    'app_secret' => '20d756d9f811dd41ba813368f88a4cbb',
    'default_graph_version' => 'v21.0',
]);

function read_fb_api_setup_data() {
    $file_path = ROOT_DIR . '/fb_api_setup.json';
    if (!file_exists($file_path)) return null;
    return json_decode(file_get_contents($file_path), true);
}

// ----------------- PROJECT & QUOTA HELPERS -----------------

function canonicalProjectKey(string $pid): string {
    $k = trim(strtolower($pid));
    $k = str_replace(['-', ' '], '_', $k);
    $pageIdMap = ['259547413906965'=>'hill_town','1932174893479181'=>'moon_hill'];
    if (isset($pageIdMap[$k])) return $pageIdMap[$k];
    $variants = [
        'hilltown'=>'hill_town','hill_town'=>'hill_town','hill-town'=>'hill_town',
        'moonhill'=>'moon_hill','moon_hill'=>'moon_hill','moon-hill'=>'moon_hill',
        'abedin'=>'abedin','civic_abedin'=>'abedin','ashridge'=>'ashridge','civic_ashridge'=>'ashridge'
    ];
    if (isset($variants[$k])) return $variants[$k];
    return preg_replace('/[^a-z0-9_]/','_', $k);
}

function balancingProjects(): array {
    return ['hill_town','moon_hill','ashridge','abedin'];
}

function loadAssignmentQuotas(string $pageId): array {
    global $db;
    switch ($pageId) {
        case 'hill_town':
            $rows = $db->where('project', $pageId)->orderby('user_id', 'DESC')->get('crm_assignment_rules', null, ['user_id','raw_weight','participating']);
            break;
        case 'moon_hill':
            $rows = $db->where('project', $pageId)->orderby('user_id', 'ASC')->get('crm_assignment_rules', null, ['user_id','raw_weight','participating']);
            break;
        case 'ashridge':
            $rows = $db->where('project', $pageId)->orderby('user_id', 'DESC')->get('crm_assignment_rules', null, ['user_id','raw_weight','participating']);
            break;
        case 'abedin':
            $rows = $db->where('project', $pageId)->orderby('user_id', 'ASC')->get('crm_assignment_rules', null, ['user_id','raw_weight','participating']);
            break;
        case '259547413906965':
            $rows = $db->where('project', 'hill_town')->orderby('user_id', 'DESC')->get('crm_assignment_rules', null, ['user_id','raw_weight','participating']);
            break;
        case '1932174893479181':
            $rows = $db->where('project', 'moon_hill')->orderby('user_id', 'ASC')->get('crm_assignment_rules', null, ['user_id','raw_weight','participating']);
            break;
        default:
            $rows = $db->where('project', $pageId)->orderby('user_id', 'ASC')->get('crm_assignment_rules', null, ['user_id','raw_weight','participating']);
    }
    $quotas = [];
    foreach ($rows as $r) {
        $uid = (int)$r->user_id;
        $participating = isset($r->participating) ? ((int)$r->participating) : 1;
        $raw = (int)$r->raw_weight;

        // explicitly skip users that opted-out
        if ($participating === 0) {
            logDebug("loadAssignmentQuotas: skipping user {$uid} (participating=0) for project {$pageId}");
            continue;
        }

        // skip zero/negative weight users (treat as non-participating)
        if ($raw <= 0) {
            logDebug("loadAssignmentQuotas: skipping user {$uid} (raw_weight={$raw}) for project {$pageId}");
            continue;
        }

        $quotas[$uid] = $raw;
    }
    return $quotas;
}

function loadNormalizedQuotas(string $project, array $eligibleUserIds = null): array {
    $raw = loadAssignmentQuotas($project);
    if (is_array($eligibleUserIds)) $raw = array_intersect_key($raw, array_flip($eligibleUserIds));
    $sum = array_sum($raw);
    $out = [];
    foreach ($raw as $uid => $w) $out[(int)$uid] = $sum > 0 ? (100.0 * $w / $sum) : 0.0;
    return $out;
}

function loadAllProjectQuotas(array $eligibleUnion = null): array {
    $result = [];
    foreach (balancingProjects() as $proj) $result[$proj] = loadNormalizedQuotas($proj, $eligibleUnion);
    return $result;
}

function loadPunishedUsers(): array {
    global $db;
    $rows = $db->get('crm_punished_users', null, ['user_id']);
    return array_column($rows, 'user_id');
}

function eligibleUsersForProject(string $project, array $projectQuotas): array {
    global $db;
    $ids = array_keys($projectQuotas);
    if (empty($ids)) return [];
    $punished = loadPunishedUsers();

    // Exclude admin (user_id = 1) from assignment pool
    $rows = $db->where('user_id', $ids, 'IN')
               ->where('user_id', 1, '!=')   // do not consider admin
               ->where('active','1')
               ->where('banned','0')
               ->get(T_USERS, null, ['user_id','leader_id','joining_date']);

    $eligible = [];
    foreach ($rows as $r) {
        if (!in_array((int)$r->user_id, $punished)) {
            $eligible[(int)$r->user_id] = [
                'leader_id' => (int)$r->leader_id,
                'joining_ts' => strtotime($r->joining_date . ' 00:00:00') ?: 0
            ];
        }
    }
    return $eligible;
}

function eligibleUnionAcrossProjects(): array {
    $all = [];
    foreach (balancingProjects() as $proj) {
        $raw = loadAssignmentQuotas($proj);
        $eligible = eligibleUsersForProject($proj, $raw);
        foreach ($eligible as $uid => $meta) $all[(int)$uid] = true;
    }
    return array_keys($all);
}

function windowStartFor(array $eligibleMeta): int {
    if (empty($eligibleMeta)) return strtotime("-".BALANCING_LOOKBACK_DAYS." days");
    $max = 0;
    foreach ($eligibleMeta as $meta) if (!empty($meta['joining_ts']) && $meta['joining_ts'] > $max) $max = $meta['joining_ts'];
    if ($max <= 0) return strtotime("-".BALANCING_LOOKBACK_DAYS." days");
    return (int)$max;
}

function actualCounts(array $userIds, array $projects, int $startTs): array {
    global $db;
    if (empty($userIds) || empty($projects)) return [];
    $rows = $db->where('member', $userIds, 'IN')->where('project', $projects, 'IN')->where('created', $startTs, '>=')->groupBy('member')->get('crm_leads', null, ['member','COUNT(*) AS cnt']);
    $out = array_fill_keys($userIds, 0);
    foreach ($rows as $r) $out[(int)$r->member] = (int)$r->cnt;
    return $out;
}

function actualCountsForProject(array $userIds, string $project, int $startTs): array {
    return actualCounts($userIds, [$project], $startTs);
}

function adjustForRecentDuplicates(array &$actual, array $userIds, array $projects, int $days = 15): void {
    global $db;
    if (empty($userIds) || empty($projects)) return;
    $since = strtotime("-{$days} days");
    $rows = $db->where('created', $since, '>=')->where('member', $userIds, 'IN')->where('project', $projects, 'IN')->get('crm_leads', null, ['member','project','phone']);
    $seen = [];
    foreach ($rows as $row) {
        $key = $row->member . ':' . preg_replace('/[^0-9]/','', (string)$row->phone);
        if (!isset($seen[$key])) { $seen[$key] = $row->project; continue; }
        if ($seen[$key] !== $row->project && isset($actual[(int)$row->member]) && $actual[(int)$row->member] > 0) $actual[(int)$row->member]--;
    }
}

function globalDeficits(array $eligibleMeta, array $allQuotas, int $startTs): array {
    $userIds = array_map('intval', array_keys($eligibleMeta));
    $projects = array_keys($allQuotas);
    $actualGlobal = actualCounts($userIds, $projects, $startTs);
    adjustForRecentDuplicates($actualGlobal, $userIds, $projects, 15);
    foreach ($actualGlobal as $uid => $v) if ($v < 0) $actualGlobal[$uid] = 0;
    $totals = [];
    foreach ($projects as $p) {
        $per = actualCounts($userIds, [$p], $startTs);
        $totals[$p] = array_sum($per);
    }
    $expected = array_fill_keys($userIds, 0.0);
    foreach ($projects as $p) {
        $qp = $allQuotas[$p] ?? [];
        $Tp = $totals[$p] ?? 0;
        if ($Tp <= 0) continue;
        foreach ($userIds as $u) {
            $q = $qp[$u] ?? 0.0;
            $expected[$u] += ($q * $Tp) / 100.0;
        }
    }
    $def = [];
    foreach ($userIds as $u) $def[$u] = ($expected[$u] ?? 0.0) - ($actualGlobal[$u] ?? 0);
    return ['actual_global' => $actualGlobal, 'expected' => $expected, 'deficits' => $def];
}

// ----------------- STATEFUL BATCH ASSIGNER -----------------

function prepareAssignmentState(string $project) {
    // snapshot all data once to make multiple picks deterministic and efficient
    $thisQuotasRaw = loadAssignmentQuotas($project);
    if (empty($thisQuotasRaw)) return null;
    $eligibleMeta = eligibleUsersForProject($project, $thisQuotasRaw);
    $eligibleIds = array_map('intval', array_keys($eligibleMeta));
    if (empty($eligibleIds)) return null;
    $eligibleUnion = eligibleUnionAcrossProjects();
    $allQuotas = loadAllProjectQuotas($eligibleUnion);
    $thisQuotas = $allQuotas[$project] ?? [];
    // restrict to eligible
    $thisQuotas = array_intersect_key($thisQuotas, array_flip($eligibleIds));
    $startTs = windowStartFor($eligibleMeta);
    $projects = array_keys($allQuotas);
    $actualGlobal = actualCounts($eligibleIds, $projects, $startTs);
    adjustForRecentDuplicates($actualGlobal, $eligibleIds, $projects, 15);
    foreach ($eligibleIds as $u) {
        $actualGlobal[$u] = $actualGlobal[$u] ?? 0;
    }
    $actualProject = actualCountsForProject($eligibleIds, $project, $startTs);
    foreach ($eligibleIds as $u) $actualProject[$u] = $actualProject[$u] ?? 0;
    $totals = [];
    foreach ($projects as $p) {
        $per = actualCounts($eligibleIds, [$p], $startTs);
        $totals[$p] = array_sum($per);
    }
    $expected = array_fill_keys($eligibleIds, 0.0);
    foreach ($projects as $p) {
        $qp = $allQuotas[$p] ?? [];
        $Tp = $totals[$p] ?? 0;
        if ($Tp <= 0) continue;
        foreach ($eligibleIds as $u) {
            $q = $qp[$u] ?? 0.0;
            $expected[$u] += ($q * $Tp) / 100.0;
        }
    }
    $deficits = [];
    foreach ($eligibleIds as $u) $deficits[$u] = ($expected[$u] ?? 0.0) - ($actualGlobal[$u] ?? 0);

    // batchAssigned: how many leads we've assigned this batch (for enforcing MAX_ASSIGN_PER_USER_PER_BATCH)
    $batchAssigned = array_fill_keys($eligibleIds, 0);

    return [
        'eligibleIds' => $eligibleIds,
        'eligibleMeta' => $eligibleMeta,
        'thisQuotas' => $thisQuotas,
        'allQuotas' => $allQuotas,
        'startTs' => $startTs,
        'actualProject' => $actualProject,
        'actualGlobal' => $actualGlobal,
        'expected' => $expected,
        'deficits' => $deficits,
        'totals' => $totals,
        'batchAssigned' => $batchAssigned,
    ];
}

/**
 * pickFromState: choose a user from prepared state using configured strategy.
 * Returns ['user_id'=>..., 'leader_id'=>...]
 * Note: kept for compatibility; returns user_id=0 when no candidate instead of admin fallback.
 */
function pickFromState(array &$state, string $project) {
    $strategy = DISTRIBUTION_STRATEGY;
    $eligible = $state['eligibleIds'];
    $thisQuotas = $state['thisQuotas'];
    $deficits = $state['deficits'];
    $actualProject = $state['actualProject'];
    $eligibleMeta = $state['eligibleMeta'];
    $batchAssigned = $state['batchAssigned'];

    // filter out users who reached per-batch cap (if enabled)
    $candidates = [];
    foreach ($eligible as $u) {
        if (MAX_ASSIGN_PER_USER_PER_BATCH > 0 && ($batchAssigned[$u] ?? 0) >= MAX_ASSIGN_PER_USER_PER_BATCH) continue;
        if (isset($thisQuotas[$u]) && $thisQuotas[$u] > 0.0) $candidates[] = $u;
    }

    if (empty($candidates)) {
        // Fallback: don't include admin. Return unassigned (0).
        return ['user_id' => 0, 'leader_id' => 0];
    }

    if ($strategy === 'round_robin') {
        usort($candidates, function($a,$b) use($batchAssigned,$eligibleMeta){
            $ca = $batchAssigned[$a] ?? 0; $cb = $batchAssigned[$b] ?? 0;
            if ($ca !== $cb) return $ca <=> $cb;
            $ja = $eligibleMeta[$a]['joining_ts'] ?? 0; $jb = $eligibleMeta[$b]['joining_ts'] ?? 0;
            return $ja <=> $jb;
        });
        $selected = $candidates[0];
    } elseif ($strategy === 'weighted_random') {
        $weights = [];
        $total = 0.0;
        foreach ($candidates as $u) {
            $w = max(0.0, $thisQuotas[$u] ?? 0.0);
            $weights[$u] = $w;
            $total += $w;
        }
        if ($total <= 0) {
            $selected = $candidates[array_rand($candidates)];
        } else {
            $r = mt_rand() / mt_getrandmax() * $total;
            $acc = 0.0; $selected = $candidates[0];
            foreach ($weights as $u => $w) {
                $acc += $w;
                if ($r <= $acc) { $selected = (int)$u; break; }
            }
        }
    } else {
        // balanced
        $projectExpectedTotal = array_sum($state['actualProject'] ?? []);
        $projectExpected = [];
        foreach ($state['eligibleIds'] as $u) {
            $q = $state['thisQuotas'][$u] ?? 0.0;
            $projectExpected[$u] = ($projectExpectedTotal * $q) / 100.0;
        }
        $projectDef = [];
        foreach ($state['eligibleIds'] as $u) $projectDef[$u] = ($projectExpected[$u] ?? 0.0) - ($state['actualProject'][$u] ?? 0);

        usort($candidates, function($a,$b) use($deficits,$projectDef,$actualProject,$eligibleMeta){
            $ga = $deficits[$a] ?? 0.0; $gb = $deficits[$b] ?? 0.0;
            if ($ga != $gb) return ($gb <=> $ga); // larger global deficit first
            $pa = $projectDef[$a] ?? 0.0; $pb = $projectDef[$b] ?? 0.0;
            if ($pa != $pb) return ($pb <=> $pa);
            $aa = $actualProject[$a] ?? 0; $ab = $actualProject[$b] ?? 0;
            if ($aa != $ab) return ($aa <=> $ab); // lower actual first
            $ja = $eligibleMeta[$a]['joining_ts'] ?? 0; $jb = $eligibleMeta[$b]['joining_ts'] ?? 0;
            return $ja <=> $jb;
        });

        $selected = $candidates[0];
        foreach ($candidates as $uid) {
            if (($deficits[$uid] ?? 0) > 0) { $selected = $uid; break; }
        }
    }

    $leaderId = $eligibleMeta[$selected]['leader_id'] ?? 0;
    return ['user_id' => intval($selected), 'leader_id' => intval($leaderId)];
}

function updateStateAfterAssign(array &$state, string $project, int $user) {
    // increment actuals
    $state['actualProject'][$user] = ($state['actualProject'][$user] ?? 0) + 1;
    $state['actualGlobal'][$user] = ($state['actualGlobal'][$user] ?? 0) + 1;
    // update batchAssigned
    $state['batchAssigned'][$user] = ($state['batchAssigned'][$user] ?? 0) + 1;
    // recompute deficit for this user (simple decrement by 1)
    $state['deficits'][$user] = ($state['expected'][$user] ?? 0.0) - ($state['actualGlobal'][$user] ?? 0);
}

// ----------------- BATCH TARGET COMPUTATION -----------------

/**
 * computeBatchTargets:
 * - $state: result from prepareAssignmentState($project)
 * - $totalLeads: number of leads in current group
 * Returns array user_id => target_count for this batch.
 */
function computeBatchTargets(array $state, int $totalLeads): array {
    $thisQuotas = $state['thisQuotas'] ?? [];
    $eligible = $state['eligibleIds'] ?? [];
    $maxPerUser = defined('MAX_ASSIGN_PER_USER_PER_BATCH') ? MAX_ASSIGN_PER_USER_PER_BATCH : 0;

    if (empty($thisQuotas) || empty($eligible) || $totalLeads <= 0) return [];

    // Only consider eligible users (intersection)
    $quotas = array_intersect_key($thisQuotas, array_flip($eligible));
    if (empty($quotas)) return [];

    // expected fractional targets
    $expected = [];
    foreach ($quotas as $uid => $pct) {
        $expected[$uid] = ($pct / 100.0) * $totalLeads;
    }

    // floor them first
    $targets = [];
    $fractionals = [];
    $assignedSum = 0;
    foreach ($expected as $uid => $val) {
        $floor = (int)floor($val);
        $targets[$uid] = $floor;
        $assignedSum += $floor;
        $fractionals[$uid] = $val - $floor;
    }

    // distribute leftover leads by largest fractional part or join_date tie-breaker
    $leftover = $totalLeads - $assignedSum;
    if ($leftover > 0) {
        if (DISTRIBUTION_LEFTOVER_METHOD === 'join_date' && !empty($state['eligibleMeta'])) {
            // sort fractionals, tie-breaker handled separately below
            arsort($fractionals);
            // for equal fractionals, prefer earlier join_ts
            $ordered = array_keys($fractionals);
            usort($ordered, function($a,$b) use($state,$fractionals){
                $fa = $fractionals[$a] ?? 0; $fb = $fractionals[$b] ?? 0;
                if ($fa !== $fb) return ($fb <=> $fa);
                $ja = $state['eligibleMeta'][$a]['joining_ts'] ?? 0;
                $jb = $state['eligibleMeta'][$b]['joining_ts'] ?? 0;
                return $ja <=> $jb;
            });
            foreach ($ordered as $uid) {
                if ($leftover <= 0) break;
                $targets[$uid] = ($targets[$uid] ?? 0) + 1;
                $leftover--;
            }
        } else {
            arsort($fractionals); // largest fraction first
            foreach ($fractionals as $uid => $frac) {
                if ($leftover <= 0) break;
                $targets[$uid] = ($targets[$uid] ?? 0) + 1;
                $leftover--;
            }
        }
    }

    // enforce MAX_ASSIGN_PER_USER_PER_BATCH cap if >0
    if ($maxPerUser > 0) {
        $capExceeded = 0;
        foreach ($targets as $uid => $t) {
            if ($t > $maxPerUser) {
                $capExceeded += ($t - $maxPerUser);
                $targets[$uid] = $maxPerUser;
            }
        }
        // redistribute freed-up leads to users under cap
        if ($capExceeded > 0) {
            $fillable = [];
            foreach ($targets as $uid => $t) {
                if ($t < $maxPerUser) $fillable[$uid] = $maxPerUser - $t;
            }
            while ($capExceeded > 0 && !empty($fillable)) {
                foreach ($fillable as $uid => $space) {
                    if ($capExceeded <= 0) break;
                    if ($space <= 0) { unset($fillable[$uid]); continue; }
                    $targets[$uid] += 1;
                    $fillable[$uid] -= 1;
                    $capExceeded -= 1;
                }
            }
        }
    }

    foreach ($targets as $uid => $t) if ($t <= 0) unset($targets[$uid]);

    return $targets;
}

// ----------------- BATCH ALLOCATION (MAIN) -----------------

/**
 * allocateLeadsBatch - allocate an array of incoming FB leads (for same form/page group)
 * $incomingLeads: array of lead objects as FB returns them
 * $leadsData: the form/page metadata decoded from FB for context
 */
function allocateLeadsBatch(array $incomingLeads, array $leadsData) {
    global $db, $show_import_status;

    // small utility to build sanitized 'additional' object
    $make_additional_json = function(array $additional_input, array $extra = []) {
        $clean = [];
        foreach ($additional_input as $k => $v) {
            $key = (string)$k;
            if (is_array($v) || is_object($v)) {
                $clean[$key] = sanitize_lead_field(json_encode($v, JSON_UNESCAPED_UNICODE), 2000);
            } else {
                $clean[$key] = sanitize_lead_field($v, 2000);
            }
        }
        foreach ($extra as $k => $v) {
            $clean[(string)$k] = sanitize_lead_field($v, 2000);
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    };

    // ---------------- Group leads by project ----------------
    $groups = [];
    foreach ($incomingLeads as $lead) {
        $proj = '';
        if (!empty($lead['field_data']) && is_array($lead['field_data'])) {
            foreach ($lead['field_data'] as $f) {
                if (($f['name'] ?? '') === 'project') {
                    $proj = $f['values'][0] ?? '';
                    break;
                }
            }
        }
        if (empty($proj)) {
            $pageId = $leadsData['page']['id'] ?? '';
            if ($pageId === '259547413906965') $proj = 'hill_town';
            elseif ($pageId === '1932174893479181') $proj = 'moon_hill';
            else $proj = '';
        }
        $proj = canonicalProjectKey($proj ?: ($leadsData['page']['id'] ?? ''));
        $groups[$proj][] = $lead;
    }

    // ---------------- Per-project allocation ----------------
    foreach ($groups as $proj => $leads) {
        if ($show_import_status) echo "--- ALLOCATING BATCH for project {$proj}: " . count($leads) . " leads ---\n";
        logDebug("Starting allocation for project {$proj} with " . count($leads) . " leads");

        $state = prepareAssignmentState($proj);
        if (empty($state)) {
            logDebug("No eligible users for project {$proj}; skipping assignment of " . count($leads) . " leads");
            if ($show_import_status) echo "No eligible users for {$proj}, skipping.\n";
            // Optionally insert as unassigned
            if (INSERT_SKIPPED_AS_UNASSIGNED) {
                foreach ($leads as $lead) {
                    $phone = null; $name = null; $created = strtotime($lead['created_time'] ?? 'now');
                    $additional = [];
                    if (!empty($lead['field_data'])) {
                        foreach ($lead['field_data'] as $f) {
                            $k = $f['name'] ?? null; $v = $f['values'][0] ?? null;
                            if (!$k) continue;
                            $additional[$k] = $v;
                            if (in_array($k, ['phone','phone_number'])) $phone = $v;
                            if (in_array($k, ['name','full_name'])) $name = $v;
                        }
                    }
                    if (empty($phone) || empty($name)) continue;
                    $phone_number = preg_replace('/[^0-9]/','', (string)$phone);
                    $threadId = $lead['id'] ?? null;
                    if (!$threadId) continue;
                    $threadIdClean = sanitize_lead_field((string)$threadId, 120);
                    $exists = $db->where('thread_id', $threadIdClean)->getOne(T_LEADS, ['lead_id']);
                    if ($exists) continue;

                    $data = [
                        'source' => 'Facebook',
                        'phone' => $phone_number,
                        'name' => sanitize_lead_field($name, 300),
                        'profession' => sanitize_lead_field($additional['job_title'] ?? '', 300),
                        'company' => sanitize_lead_field($additional['company'] ?? '', 300),
                        'email' => sanitize_lead_field($additional['email'] ?? 'N/A', 150),
                        'project' => sanitize_lead_field($proj, 120),
                        'additional' => $make_additional_json($additional, [
                            'form_name' => $leadsData['name'] ?? 'N/A',
                            'page_id' => $leadsData['page']['id'] ?? 'N/A',
                            'page_name' => $leadsData['page']['name'] ?? 'N/A',
                            'thread_id' => $threadIdClean,
                            'unassigned_reason' => 'no_eligible_users'
                        ]),
                        'created' => $created,
                        'given_date' => $created,
                        'thread_id' => $threadIdClean,
                        'assigned' => 0,
                        'member' => 0,
                        'page_id' => sanitize_lead_field($leadsData['page']['id'] ?? '0', 120),
                        'time' => time(),
                    ];
                    // print_r($data);
                    $db->insert(T_LEADS, $data);
                    logDebug("Inserted unassigned lead {$threadIdClean} for project {$proj} (no eligible users).");
                }
            }
            continue;
        }

        // compute batch targets
        $totalLeads = count($leads);
        $targets = computeBatchTargets($state, $totalLeads);
        $sumTargets = array_sum($targets);

        // fallback equal distribution if enabled
        if ($sumTargets <= 0 && DISTRIBUTION_ALLOW_EQUAL_FALLBACK) {
            $eligible = $state['eligibleIds'] ?? [];
            $n = count($eligible);
            if ($n > 0) {
                $base = intdiv($totalLeads, $n);
                $rem = $totalLeads - ($base * $n);
                foreach ($eligible as $uid) $targets[$uid] = $base;
                foreach ($eligible as $uid) {
                    if ($rem <= 0) break;
                    $targets[$uid] += 1; $rem--;
                }
                $sumTargets = array_sum($targets);
            }
        }

        if ($sumTargets <= 0) {
            logDebug("No targets computed for project {$proj}; skipping assignment of {$totalLeads} leads.");
            if ($show_import_status) echo "No targets for {$proj}, skipping {$totalLeads} leads.\n";
            if (INSERT_SKIPPED_AS_UNASSIGNED) {
                // insert as unassigned similarly to above (omitted here for brevity)
            }
            continue;
        }

        // Build pick list deterministically
        $deficits = $state['deficits'] ?? [];
        uksort($targets, function($a,$b) use($deficits,$state){
            $da = $deficits[$a] ?? 0; $db = $deficits[$b] ?? 0;
            if ($da !== $db) return ($db <=> $da);
            $ja = $state['eligibleMeta'][$a]['joining_ts'] ?? 0;
            $jb = $state['eligibleMeta'][$b]['joining_ts'] ?? 0;
            return $ja <=> $jb;
        });

        $pickList = [];
        foreach ($targets as $uid => $cnt) {
            for ($i = 0; $i < $cnt; $i++) $pickList[] = $uid;
        }
        $pickIndex = 0;

        // iterate leads and assign from pickList; honor phone override only when prev has capacity
        foreach ($leads as $lead) {
            $phone = null; $name = null; $created = strtotime($lead['created_time'] ?? 'now');
            $additional = [];
            if (!empty($lead['field_data']) && is_array($lead['field_data'])) {
                foreach ($lead['field_data'] as $f) {
                    $k = $f['name'] ?? null; $v = $f['values'][0] ?? null;
                    if (!$k) continue;
                    $additional[$k] = $v;
                    if (in_array($k, ['phone','phone_number'])) $phone = $v;
                    if (in_array($k, ['name','full_name'])) $name = $v;
                }
            }
            if (empty($phone) || empty($name)) {
                if ($show_import_status) echo "Skipping lead {$lead['id']}: missing name/phone.\n";
                continue;
            }
            $phone_number = preg_replace('/[^0-9]/','', (string)$phone);
            $threadId = $lead['id'] ?? null;
            if (!$threadId) { if ($show_import_status) echo "Skipping lead (no thread id)\n"; continue; }
            $threadIdClean = sanitize_lead_field((string)$threadId, 120);

            // check duplicate by thread_id
            $exists = $db->where('thread_id', $threadIdClean)->getOne(T_LEADS, ['lead_id']);
            if ($exists) { if ($show_import_status) echo "Lead {$threadIdClean} already exists, skipping.\n"; continue; }

            // phone override
            $selected_user = null;
            $prev = $db->where('phone', $phone_number)->where('member', '1', '!=')->getOne(T_LEADS, ['assigned','member','time']);
            if ($prev && ($prev->assigned > 0 || $prev->member > 0)) {
                $prev_member = (int)$prev->member;
                $prev_leader = (int)$prev->assigned;
                $prev_time = (int)($prev->time ?? 0);
                $recentThreshold = strtotime('-' . PHONE_OVERRIDE_MAX_DAYS . ' days');
                $thisQuotasRaw = loadAssignmentQuotas($proj);
                $eligiblePrev = eligibleUsersForProject($proj, $thisQuotasRaw);
                $hasQuota = isset($thisQuotasRaw[$prev_member]) && ((int)$thisQuotasRaw[$prev_member] > 0);
                $isRecent = ($prev_time >= $recentThreshold);
                $hasCapacity = in_array($prev_member, $pickList, true);
                if (isset($eligiblePrev[$prev_member]) && $hasQuota && $isRecent && $hasCapacity) {
                    $selected_user = ['user_id' => $prev_member, 'leader_id' => $prev_leader];
                    $idx = array_search($prev_member, $pickList);
                    if ($idx !== false) array_splice($pickList, $idx, 1);
                }
            }

            if ($selected_user === null) {
                if ($pickIndex >= count($pickList)) {
                    logDebug("No remaining target capacity for project {$proj}; skipping/inserting as unassigned lead {$threadIdClean}.");
                    if (INSERT_SKIPPED_AS_UNASSIGNED) {
                        $data = [
                            'source' => 'Facebook',
                            'phone' => $phone_number,
                            'name' => sanitize_lead_field($name, 300),
                            'profession' => sanitize_lead_field($additional['job_title'] ?? '', 300),
                            'company' => sanitize_lead_field($additional['company'] ?? '', 300),
                            'email' => sanitize_lead_field($additional['email'] ?? 'N/A', 150),
                            'project' => sanitize_lead_field($proj, 120),
                            'additional' => $make_additional_json($additional, [
                                'form_name' => $leadsData['name'] ?? 'N/A',
                                'page_id' => $leadsData['page']['id'] ?? 'N/A',
                                'page_name' => $leadsData['page']['name'] ?? 'N/A',
                                'thread_id' => $threadIdClean,
                                'unassigned_reason' => 'no_capacity_in_batch'
                            ]),
                            'created' => $created,
                            'given_date' => $created,
                            'thread_id' => $threadIdClean,
                            'assigned' => 0,
                            'member' => 0,
                            'page_id' => sanitize_lead_field($leadsData['page']['id'] ?? '0', 120),
                            'time' => time(),
                        ];
                        // print_r($data);
                        $db->insert(T_LEADS, $data);
                    }
                    if ($show_import_status) echo "No capacity left, handled lead {$threadIdClean} as unassigned/skip.\n";
                    continue;
                }
                $uid = $pickList[$pickIndex++];
                $leaderId = $state['eligibleMeta'][$uid]['leader_id'] ?? 0;
                $selected_user = ['user_id' => $uid, 'leader_id' => $leaderId];
                $pos2 = array_search($uid, $pickList);
                if ($pos2 !== false) array_splice($pickList, $pos2, 1);
            }

            // final sanitized data and insert
            $data = [
                'source' => 'Facebook',
                'phone' => $phone_number,
                'name' => sanitize_lead_field($name, 300),
                'profession' => sanitize_lead_field($additional['job_title'] ?? '', 300),
                'company' => sanitize_lead_field($additional['company'] ?? '', 300),
                'email' => sanitize_lead_field($additional['email'] ?? 'N/A', 150),
                'project' => sanitize_lead_field($proj, 120),
                'additional' => $make_additional_json($additional, [
                    'form_name' => $leadsData['name'] ?? 'N/A',
                    'page_id' => $leadsData['page']['id'] ?? 'N/A',
                    'page_name' => $leadsData['page']['name'] ?? 'N/A',
                    'thread_id' => $threadIdClean,
                ]),
                'created' => $created,
                'given_date' => $created,
                'thread_id' => $threadIdClean,
                'assigned' => intval($selected_user['leader_id'] ?? 0),
                'member' => intval($selected_user['user_id'] ?? 0),
                'page_id' => sanitize_lead_field($leadsData['page']['id'] ?? '0', 120),
                'time' => time(),
            ];

            $db->startTransaction();
            try {
                // print_r($data);
                $insert_id = $db->insert(T_LEADS, $data);
                if (!$insert_id) throw new Exception("Insert failed: " . $db->getLastError());

                $notification_user = ($data['member'] > 0 ? $data['member'] : ($data['assigned'] > 0 ? $data['assigned'] : 0));
                $leadUrl = "/management/leads?lead_id={$insert_id}";
                $subject = "New Lead: {$data['name']}";
                $comment = "You have a new lead from {$proj}";

                if ($notification_user > 0) notifyUser($db, $notification_user, $subject, $comment, $leadUrl);

                if ($notification_user > 0 && intval($notification_user) !== intval($data['assigned']) && intval($data['assigned']) > 0) {
                    $leaderId = intval($data['assigned']);
                    $nameRow = $db->where('user_id', $notification_user)->getOne(T_USERS, ['first_name','last_name']);
                    $commentLeader = ($nameRow ? ($nameRow->first_name . ' ' . $nameRow->last_name) : 'A user') . " has a new lead.";
                    notifyUser($db, $leaderId, $subject, $commentLeader, $leadUrl);
                }

                $db->commit();

                if (intval($data['member']) > 0) updateStateAfterAssign($state, $proj, intval($data['member'] ?? 0));

                if ($show_import_status) {
                    echo "Inserted lead {$insert_id}, assigned member {$data['member']} leader {$data['assigned']}\n";
                }

            } catch (Exception $e) {
                $db->rollback();
                logDebug("allocateLeadsBatch: failed to insert/notify lead {$threadIdClean}: " . $e->getMessage());
                if ($show_import_status) echo "Failed to insert lead {$threadIdClean}: " . $e->getMessage() . "\n";
            }
        } // end per-lead loop (batch)
    } // end per-project groups
}

// ----------------- PROCESS FACEBOOK CONFIG & RUN -----------------

$api_config = read_fb_api_setup_data();
if ($show_import_status) echo '<pre style="background:#f6f6f6;padding:10px;border-radius:6px;">';

if (empty($api_config) || empty($api_config['leads'])) {
    if ($show_import_status) echo "No FB API config or leads disabled.\n";
    logDebug("No FB API config or leads disabled.");
} else {
    foreach ($api_config['leads'] as $page_id => $cfg) {
        if (!isset($cfg['status']) || $cfg['status'] !== '1') continue;
        $pageCfg = $api_config['pages'][$page_id] ?? null;
        if (!$pageCfg) {
            logDebug("Missing page config for {$page_id}");
            continue;
        }
        $token = $pageCfg['access_token'] ?? null;
        if (!$token) { logDebug("No access token for page {$page_id}"); continue; }

        $requests = [];
        foreach ($cfg['form_id'] as $form_id) {
            // Use a conservative fields set for stability
            $fields = "created_time,leads_count,page,page_id,organic_leads_count,name,leads.limit(150){ad_name,created_time,field_data,form_id,id,platform},id,status";
            $requests[] = $fb_connect->request('GET', "/{$form_id}?fields={$fields}");
        }

        try {
            $responses = $fb_connect->sendBatchRequest($requests, $token)->getDecodedBody();
            if ($responses) {
                foreach ($responses as $response) {
                    $leadsData = json_decode($response['body'], true);
                    if (!empty($leadsData['leads']['data'])) {
                        $incoming = $leadsData['leads']['data'];
                        // allocate this form's leads as a batch (keeps group by project inside)
                        allocateLeadsBatch($incoming, $leadsData);
                    } else {
                        if ($show_import_status) echo "No leads for one form.\n";
                    }
                }
            }
        } catch (Exception $e) {
            logDebug("Facebook batch error for page {$page_id}: " . $e->getMessage());
            if ($show_import_status) echo "FB error for page {$page_id}: " . $e->getMessage() . "\n";
        }
    }
}

if ($show_import_status) echo '</pre>';
?>
