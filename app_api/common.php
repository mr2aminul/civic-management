<?php
// common.php
// Global JSON header and sane defaults
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
date_default_timezone_set('UTC');

// Server configuration values (ms)
$server_config = [
    "SYNC_INTERVAL" => 9000,
    "LOCATION_SYNC_INTERVAL" => 600000,
    "LOCATION_TRACKING_ENABLED" => false,
    "NOTIFICATIONS_ENABLED" => true,
    "ALARMS_ENABLED" => true,
    "CALL_TRACKING_ENABLED" => true
];

// Domain details
$domain_details = ['domain' => 'civicgroupbd.com'];
$SITE_ORIGIN = 'https://'.$domain_details['domain'];

// Load required files (adjust to your project)
require_once __DIR__ . '/../assets/includes/tabels.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../assets/libraries/DB/vendor/autoload.php';

// Connect to MySQL
$sqlConnect = mysqli_connect($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name, 3306);
if (!$sqlConnect) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . mysqli_connect_error()]);
    exit;
}
mysqli_options($sqlConnect, MYSQLI_OPT_CONNECT_TIMEOUT, 30);

// Use MysqliDb
$db = new MysqliDb($sqlConnect);
$db->setPrefix(""); // adjust if you use prefixes
// Use object return type everywhere for consistency across endpoints
if (property_exists($db, 'returnType')) {
    $db->returnType = 'object';
}

// Utilities
function ensure_dir($path) {
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

function Wo_GetConfig() {
    global $sqlConnect;
    $data  = array();
    $query = mysqli_query($sqlConnect, "SELECT * FROM " . T_CONFIG);
    if (mysqli_num_rows($query)) {
        while ($fetched_data = mysqli_fetch_assoc($query)) {
            $data[$fetched_data['name']] = $fetched_data['value'];
        }
    }
    return $data;
}
$config    = Wo_GetConfig();

function json_flags() {
    return JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
}

// Remove HTML entities and risky content
function cleanString($string) {
    return preg_replace("/&#?[a-z0-9]+;/i", "", $string);
}

function Wo_Secure($string, $censored_words = 0, $br = true, $strip = 0, $clean = true) {
    global $sqlConnect;
    $string = trim((string)$string);
    if ($clean) $string = cleanString($string);
    $string = mysqli_real_escape_string($sqlConnect, $string);
    $string = htmlspecialchars($string, ENT_QUOTES);
    if ($br) {
        $string = str_replace(["\r\n","\n\r","\r","\n"], " <br>", $string);
    } else {
        $string = str_replace(["\r\n","\n\r","\r","\n"], "", $string);
    }
    $string = str_replace('&amp;#', '&#', $string);
    return $string;
}

// Read JSON body once
function read_json_body() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = file_get_contents("php://input");
    if (!$raw) { $cache = []; return $cache; }
    $data = json_decode($raw, true);
    $cache = (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
    return $cache;
}

// Unified param reader: JSON -> POST -> GET
function req_param($key, $default = null) {
    $json = read_json_body();
    if (array_key_exists($key, $json)) return $json[$key];
    if (isset($_POST[$key])) return $_POST[$key];
    if (isset($_GET[$key])) return $_GET[$key];
    return $default;
}

// Validate IP helper
function validate_ip($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    return true;
}

function get_ip_address() {
    foreach (['HTTP_X_FORWARDED','HTTP_X_CLUSTER_CLIENT_IP','HTTP_FORWARDED_FOR','HTTP_FORWARDED'] as $hdr) {
        if (!empty($_SERVER[$hdr])) {
            $val = explode(',', $_SERVER[$hdr])[0];
            if (validate_ip($val)) return $val;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function get_user_agent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

// URL helpers
function sanitize_url($url) {
    global $SITE_ORIGIN;
    $u = trim((string)$url);

    // Fix common malformed patterns
    $u = str_replace('httpshttps://', 'https://', $u);
    $u = str_replace('httphttp://', 'http://', $u);
    $u = str_replace('https//', 'https://', $u);
    $u = str_replace('http//', 'http://', $u);
    $u = str_replace('https:\\/\\/', 'https://', $u);
    $u = str_replace('http:\\/\\/', 'http://', $u);

    // Strip duplicated domain if appears twice
    $dupe = 'https://'.$GLOBALS['domain_details']['domain'].'https://'.$GLOBALS['domain_details']['domain'];
    $u = str_replace($dupe, 'https://'.$GLOBALS['domain_details']['domain'], $u);

    // If still relative, prepend origin
    $parts = parse_url($u);
    if (!$parts || empty($parts['scheme'])) {
        if (!isset($u[0]) || $u[0] !== '/') {
            $u = '/'.ltrim($u, '/');
        }
        $u = $SITE_ORIGIN . $u;
    }
    return $u;
}

function ensure_relative_path($url) {
    // Return path+query only
    $u = sanitize_url($url);
    $parts = parse_url($u);
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? ('?'.$parts['query']) : '';
    return $path.$query;
}

function bindUrlParameters($url, $params) {
    $base = sanitize_url($url);
    $parts = parse_url($base);
    $queryParams = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $queryParams);
    $queryParams = array_merge($queryParams, $params);
    $qs = http_build_query($queryParams);
    $rebuilt = ($parts['scheme'] ?? 'https').'://'
        . ($parts['host'] ?? $GLOBALS['domain_details']['domain'])
        . ($parts['path'] ?? '/')
        . ($qs ? ('?'.$qs) : '')
        . (isset($parts['fragment']) ? ('#'.$parts['fragment']) : '');
    return $rebuilt;
}
