<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__ . '/');
}

if (!defined('IS_CRON')) {
    define('IS_CRON', true);
}

define('RUNNING_FROM_CRON', true);
if (!defined('RUNNING_FROM_CRON')) {
    if (php_sapi_name() === 'cli' || (defined('IS_CRON') && IS_CRON)) define('RUNNING_FROM_CRON', true);
    else define('RUNNING_FROM_CRON', false);
}

require_once('assets/init.php');
$domain_details['domain'] = 'civicgroupbd.com';
// require_once ROOT_DIR . 'config.php';
// require_once ROOT_DIR . "assets/libraries/DB/vendor/autoload.php";

// // Connect to SQL Server
// $sqlConnect = mysqli_connect($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name, 3306);
// $sqlConnect->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10000);


// $db        = new MysqliDb($sqlConnect);

// ensure DB connection uses utf8mb4 (defensive)
try {
    if (method_exists($db, 'rawQuery')) {
        $db->rawQuery("SET NAMES utf8mb4");
        $db->rawQuery("SET CHARACTER SET utf8mb4");
        $db->rawQuery("SET collation_connection = 'utf8mb4_unicode_ci'");
    } else {
        // try best-effort fallback if $db is raw mysqli
        if (isset($db->mysqli) && method_exists($db->mysqli, 'set_charset')) {
            @$db->mysqli->set_charset('utf8mb4');
        }
    }
} catch (Exception $e) {
    // ignore - best effort only
}

// function Wo_Secure($string, $censored_words = 0, $br = true, $strip = 0,$cleanString = true) {
//     global $sqlConnect;
//     $string = trim($string);
//     if ($cleanString) {
//         $string = cleanString($string);
//     }
//     $string = mysqli_real_escape_string($sqlConnect, $string);
//     $string = htmlspecialchars($string, ENT_QUOTES);
//     if ($br == true) {
//         $string = str_replace('\r\n', " <br>", $string);
//         $string = str_replace('\n\r', " <br>", $string);
//         $string = str_replace('\r', " <br>", $string);
//         $string = str_replace('\n', " <br>", $string);
//     } else {
//         $string = str_replace('\r\n', "", $string);
//         $string = str_replace('\n\r', "", $string);
//         $string = str_replace('\r', "", $string);
//         $string = str_replace('\n', "", $string);
//     }
//     if ($strip == 1) {
//         $string = stripslashes($string);
//     }
//     $string = str_replace('&amp;#', '&#', $string);
//     if ($censored_words == 1) {
//         global $config;
//         $censored_words = @explode(",", $config['censored_words']);
//         foreach ($censored_words as $censored_word) {
//             $censored_word = trim($censored_word);
//             $string        = str_replace($censored_word, '****', $string);
//         }
//     }
//     return $string;
// }

// function cleanString($string) {
//     return $string = preg_replace("/&#?[a-z0-9]+;/i", "", $string);
// }

// function Wo_GetConfig() {
//     global $sqlConnect;
//     $data  = array();
//     $query = mysqli_query($sqlConnect, "SELECT * FROM " . T_CONFIG);
//     if (mysqli_num_rows($query)) {
//         while ($fetched_data = mysqli_fetch_assoc($query)) {
//             $data[$fetched_data['name']] = $fetched_data['value'];
//         }
//     }
//     return $data;
// }

// $config    = Wo_GetConfig();
// $wo = ['config' => $config, 'user' => ['user_id' => 0]];

// function Wo_SaveConfig($update_name, $value) {
//     global $sqlConnect;
    
//     $update_name = Wo_Secure($update_name);
//     $value       = mysqli_real_escape_string($sqlConnect, $value);
//     $query_one   = " UPDATE " . T_CONFIG . " SET `value` = '{$value}' WHERE `name` = '{$update_name}'";
//     $query       = mysqli_query($sqlConnect, $query_one);
//     if ($query) {
//         return true;
//     } else {
//         return false;
//     }
// }



if (!function_exists("zk_Update_dbAttendance_from_machine")) {
    require_once(ROOT_DIR . 'assets/includes/zk_functions.php');
}

// // Load functions_general for SMS functions
// if (!function_exists("sms_send")) {
//     require_once(ROOT_DIR . 'assets/includes/functions_general.php');
// }

// ========== CRM AUTOMATION CRON JOBS ==========
echo "<pre>";
echo "\n========== CRM Automation Started ==========\n";

// 1. Process Email Queue
echo "\n[Email Queue] Processing...\n";
if (file_exists(ROOT_DIR . 'cron/process_email_queue.php')) {
    require_once(ROOT_DIR . 'cron/process_email_queue.php');
} else {
    echo "[Email Queue] File not found\n";
}

// 2. Process SMS Queue  
echo "\n[SMS Queue] Processing...\n";
if (file_exists(ROOT_DIR . 'cron/process_sms_queue.php')) {
    require_once(ROOT_DIR . 'cron/process_sms_queue.php');
} else {
    echo "[SMS Queue] File not found\n";
}

// 3. Update SMS Delivery Reports (existing function)
echo "\n[SMS Reports] Updating delivery status...\n";
if (function_exists('sms_update_report')) {
    sms_update_report();
    echo "[SMS Reports] Updated\n";
} else {
    echo "[SMS Reports] Function not found\n";
}

// 4. Send Payment Reminders  
echo "\n[Reminders] Checking for scheduled reminders...\n";
if (file_exists(ROOT_DIR . 'cron/send_payment_reminders.php')) {
    require_once(ROOT_DIR . 'cron/send_payment_reminders.php');
} else {
    echo "[Reminders] File not found (to be implemented)\n";
}

// 5. Release Expired Plot Holds
echo "\n[Plot Holds] Releasing expired holds...\n";
if (file_exists(ROOT_DIR . 'cron/release_expired_holds.php')) {
    require_once(ROOT_DIR . 'cron/release_expired_holds.php');
} else {
    echo "[Plot Holds] File not found (to be implemented)\n";
}

// 6. Cleanup Recycle Bin (30 days old)
echo "\n[Recycle Bin] Cleaning up old files...\n";
if (file_exists(ROOT_DIR . 'cron/cleanup_recycle_bin.php')) {
    require_once(ROOT_DIR . 'cron/cleanup_recycle_bin.php');
} else {
    echo "[Recycle Bin] File not found (to be implemented)\n";
}

echo "\n========== CRM Automation Complete ==========\n\n";
echo "</pre>";

// ========== EXISTING SYSTEMS ==========
include(ROOT_DIR . 'assets/includes/leads_system.php');
// Ensure the function exists before calling it
if (function_exists('zk_Update_dbAttendance_from_machine')) {
    zk_Update_dbAttendance_from_machine();
} else {
    echo "Error: Function zk_Update_dbAttendance_from_machine() not found.\n";
}



mysqli_close($sqlConnect);
exit();