<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once 'common.php';

function getNotifications($logged_user) {
    global $db;
    if (!$logged_user) return [];

    // Fetch viewed notification IDs
    $viewed_ids = (clone $db)->where('user_id', $logged_user)->getValue('notifications_views', 'notif_id', null);
    $viewed_ids = is_array($viewed_ids) ? $viewed_ids : [];

    $db->where('(user_id = ? OR user_id = ?)', [$logged_user, 999]);
    $db->where('created_at', date('Y-m-d H:i:s', strtotime('-30 days')), '>=');


    if ($viewed_ids) {
        $db->where('id', $viewed_ids, 'NOT IN');
    }

    return array_map(function ($notif) {
        return [
            "id" => $notif->id,
            "type" => $notif->type,
            "title" => $notif->subject,
            "description" => $notif->comment,
            "url" => bindUrlParameters('https://civicgroupbd.com' . $notif->url, ['notif_id' => $notif->id]),
        ];
    }, $db->orderBy('id', 'DESC')->get('notifications', 10) ?? []);
}

// Validate user session
$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "User not logged in"]);
    exit;
}
// print_r(getNotifications(1));
// Alarms (timestamps in milliseconds)
if ($user_id == '1') {
    $alarms = [
        [
            "id" => 54,
            "title" => "Test Client Visit",
            "description" => "Client: Test Client Reminder\nLocation: Office",
            "url" => "https://civicgroupbd.com/management/leads",
            "time" => 1742204473000, // Convert to milliseconds
            "status" => "active"
        ]
    ];
} else {
    $alarms = [];
}

// Get latest version update data
function getLatestVersionData() {
    // Hard-coded values for demonstration.
    // In a real-world scenario, you might query a database or read from a config file.
    $latestVersion = "3.9";
    $apkUrl = "https://civicgroupbd.com/app_api/3.9.apk";

    return [
        "latest_version" => $latestVersion,
        "apk_url" => $apkUrl
    ];
}
$latestVersionData = getLatestVersionData();

// Output JSON response
echo json_encode([
    "status" => "success",
    "notifications" => getNotifications($user_id),
    "alarms" => $alarms,
    "server_config" => $server_config,
    "latest_version" => $latestVersionData['latest_version'],
    "apk_url"        => $latestVersionData['apk_url']
]);
