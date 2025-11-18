<?php
// manage_inventory.php
 // * note: don't use $pdo, we have $db with $db = new MysqliDb($sqlConnect); and raw connection with $sqlConnect   = $wo["sqlConnect"] = mysqli_connect($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name, 3306);

// make globals available to included files
global $db, $wo, $road_array, $project_mapping;

error_reporting(E_ALL);
ini_set('display_errors', 1);
$a = isset($_GET['action']) ? Wo_Secure($_GET['action']) : '';

if ($f == 'manage_inventory') {
    // Globals
    global $db, $wo, $road_array, $project_mapping;

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Determine requested secondary action ($s) from GET/POST reliably
    $s = null;
    if (isset($_GET['s']))        $s = Wo_Secure($_GET['s']);
    elseif (isset($_POST['s']))   $s = Wo_Secure($_POST['s']);
    
    // keep legacy 'action' param if used by front-end
    if (isset($_GET['action']))  $action = Wo_Secure($_GET['action']);
    if (isset($_POST['action'])) $action = Wo_Secure($_POST['action']);

    $modulesDir = __DIR__ . '/manage_inventory/';

    // Default response in case no included module echoes/exits
    $data = [
        'status' => 200,
        'message' => 'No module produced output.',
        'requested_f' => $f,
        'requested_s' => $s,
        'loaded_modules' => []
    ];

    if (!is_dir($modulesDir)) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => "Modules directory not found: {$modulesDir}"]);
        exit();
    }

    $baseReal = realpath($modulesDir);
    if ($baseReal === false) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => "Cannot resolve modules directory realpath."]);
        exit();
    }

    // Non-recursive loader: include every php file directly under the folder.
    $files = glob($modulesDir . '*.php');
    if ($files === false) $files = [];
    foreach ($files as $filePath) {
        // Security: skip non-files / hidden files
        $filename = basename($filePath);
        if (strpos($filename, '.') === 0) continue; // skip dotfiles
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'php') continue;

        $real = realpath($filePath);
        if ($real === false) continue;
        // Make sure file is inside the modules dir
        if (strpos($real, $baseReal) !== 0) continue;

        // Include file. The file should contain internal `if ($s === '...') { ... }` checks.
        include_once $real;

        // For debugging show which module file was included
        $data['loaded_modules'][] = $filename;
    }

    // If included modules didn't echo/exit, return default (or modules can set $data themselves)
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
