<?php
@ini_set('session.cookie_httponly', 1);
@ini_set('session.use_only_cookies', 1);

if (!version_compare(PHP_VERSION, '7.1.0', '>=')) {
    exit("Required PHP_VERSION >= 7.1.0 , Your PHP_VERSION is : " . PHP_VERSION . "\n");
}
if (!function_exists("mysqli_connect")) {
    exit("MySQLi is required to run the application, please contact your hosting to enable php mysqli.");
}

$reasons_array = array('Project Visit', 'Client Visit', 'Campaign', 'Bank Visit', 'Informed', 'Collection', 'Punch Mistake');

$wo['payment_banks'] = array('Islami Bank Ltd.', 'Dutch Bangla Bank Ltd.', 'City Bank Ltd.');
$wo['bank_branches'] = array('Gulshan', 'Banani', 'Banasree', 'Dhanmondi', 'Rangpur Terminal');

$bx_icons = [
    'bx bx-box',           // Boxed goods
    'bx bx-package',       // Packaged item
    'bx bx-basket',        // Shopping basket
    'bx bx-cart',          // Cart item
    'bx bx-briefcase',     // Business supplies
    'bx bx-book',          // Book or paper stock
    'bx bx-bulb',          // Light bulbs, electronics
    'bx bx-cube',          // General cube/boxed items
    'bx bx-desktop',       // Electronics
    'bx bx-dumbbell',      // Gym equipment
    'bx bx-food-menu',     // Groceries, packaged food
    'bx bx-football',      // Sports goods
    'bx bx-game',          // Entertainment stock
    'bx bx-gift',          // Gift items
    'bx bx-headphone',     // Electronics / audio
    'bx bx-home',          // Home-related stock
    'bx bx-laptop',        // Electronics
    'bx bx-mouse',         // Computer accessories
    'bx bx-notepad',       // Stationery
    'bx bx-paperclip',     // Office items
    'bx bx-paint',         // Hardware stock
    'bx bx-pen',           // Pens
    'bx bx-phone',         // Mobiles
    'bx bx-printer',       // Office supplies
    'bx bx-ruler',         // Measuring tools
    'bx bx-shape-circle',  // General parts or components
    'bx bx-shape-square',  // General parts
    'bx bx-shopping-bag',  // Groceries or retail
    'bx bx-speaker',       // Audio equipment
    'bx bx-store',         // General store goods
    'bx bx-tv',            // Electronics
    'bx bx-water',         // Bottled water or drinks
    'bx bx-wine',          // Wine/Alcohol
    'bx bx-bolt-circle',   // Hardware/tools
    'bx bx-cookie',        // Snacks
    'bx bx-capsule',       // Medical stock
    'bx bx-cake',          // Baked goods
    'bx bx-package',       // Generic packaging
    'bx bx-spray-can',     // Cleaning/paint supplies
];

$project_mapping = [
    'moon-hill' => 1,
    'hill-town' => 2,
    'civic-abedin' => 3,
    'civic-ashridge' => 4,
    'civic-mittika' => 5,
    'civic-autos' => 6,
];

// initialize android variable
$android = array();

$android['is_app'] = false;
$android['version'] = '';
$app_version_tag = ['?' => '', '&' => ''];

// Build query string parameters
$params = [];

if (!empty($_GET['app_version'])) {
    $version = strtok($_GET['app_version'], '?');
    $params[] = 'app_version=' . urlencode($version);
}

if (!empty($_GET['app_version2'])) {
    $version2 = strtok($_GET['app_version2'], '?');
    $params[] = 'app_version2=' . urlencode($version2);
}

// If any version parameters are found
if (!empty($params)) {
    $app_version_tag['?'] = '?' . implode('&', $params);
    $app_version_tag['&'] = '&' . implode('&', $params);
    $android['is_app'] = true;

    // Prioritize app_version2 if exists, otherwise fallback to app_version
    $android['version'] = $version ?? ($version2 ?? '');
}

date_default_timezone_set('Asia/Dhaka');
session_start();
@ini_set('gd.jpeg_ignore_warning', 1);
Global $domain_details;

/**
 * -----------------------
 * CLI vs Web bootstrap
 * -----------------------
 * Ensure ROOT_DIR is set and includes use absolute paths so the same init file
 * works from web and when executed via cron/CLI.
 */
define('IS_CLI', (php_sapi_name() === 'cli' || defined('STDIN')));

// Determine project root: assume this file is in assets/ (assets/init.php)
$calculated_root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

// Fallback: if your init.php is not inside assets/, adjust as needed.
// Example: if init.php is in project root, use __DIR__ instead.
$calculated_root = rtrim($calculated_root, '/\\');
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', $calculated_root);
}

// Provide DOCUMENT_ROOT for CLI contexts if other code expects it
if (IS_CLI && empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = ROOT_DIR;
}

// Helpful directory constants
if (!defined('ASSETS_DIR')) define('ASSETS_DIR', ROOT_DIR . '/assets');
if (!defined('INCLUDES_DIR')) define('INCLUDES_DIR', ASSETS_DIR . '/includes');
if (!defined('LIBS_DIR')) define('LIBS_DIR', ASSETS_DIR . '/libraries');
if (!defined('XHR_DIR')) define('XHR_DIR', ROOT_DIR . '/xhr');
if (!defined('HOLIDAY_FILE')) define('HOLIDAY_FILE', ROOT_DIR . '/data/holidays.json');
if (!defined('HOLIDAY_API_KEY')) define('HOLIDAY_API_KEY', 'kd2zb4lB910W7riVecBS5CZuLLpuVbuL');


/**
 * Error reporting preference:
 * - CLI: verbose for debugging
 * - Web : hide display_errors (adjust for your environment)
 */
if (IS_CLI) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // In production you may want display_errors = 0. Set to 1 temporarily to debug.
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
    ini_set('display_errors', '0');
}

    error_reporting(E_ALL);
    ini_set('display_errors', '1');
/**
 * Require core files using absolute paths. If any file is missing we show a clear message.
 * (This replaces fragile relative includes when run from different working directories.)
 */
$requires = [
    ASSETS_DIR . '/libraries/DB/vendor/joshcam/mysqli-database-class/MySQL-Maria.php',
    INCLUDES_DIR . '/cache.php',
    INCLUDES_DIR . '/functions_general.php',
    INCLUDES_DIR . '/tabels.php',
    INCLUDES_DIR . '/functions_one.php',
    INCLUDES_DIR . '/functions_two.php',
    INCLUDES_DIR . '/file_manager_helper.php',
    INCLUDES_DIR . '/functions_three.php',
    INCLUDES_DIR . '/crm_automation_cron.php',
    INCLUDES_DIR . '/crm_email_notifications.php',
    INCLUDES_DIR . '/crm_refund_functions.php',
    INCLUDES_DIR . '/crm_transfer_functions.php',
    INCLUDES_DIR . '/crm_payment_schedule_functions.php',
];

foreach ($requires as $req) {
    if (file_exists($req)) {
        require_once $req;
    } else {
        $msg = "Required file missing: {$req} (ROOT_DIR=" . ROOT_DIR . ")";
        if (IS_CLI) {
            fwrite(STDERR, $msg . PHP_EOL);
            exit(1);
        } else {
            // In web show minimal info and exit (avoid path exposure on public site in production)
            header('HTTP/1.1 500 Internal Server Error');
            exit($msg);
        }
    }
}

/*
 * --- End of init ---
 *
 * If you had additional initialization code after your original require_once lines,
 * it can continue from here.
 *
 * Note: If you previously had the relative require_once(...) lines already in the file,
 * this version uses absolute paths (safer). If you'd rather keep the relative requires
 * as a fallback (for compatibility with a particular environment), I can add a
 * fallback section that tries the relative includes first and then absolute.
 *
 * Let me know if:
 *  - your init.php is not located at assets/init.php (so I can adjust ROOT_DIR detection),
 *  - or you want to keep the original relative require_once lines as well (I can add them).
 */
