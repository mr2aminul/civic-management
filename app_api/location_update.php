<?php
// location_update.php
require_once 'common.php';

// File to store location logs
$logFile = "data/location_log.txt";

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;

// Validate user session
$user_id = $user_id ?? '';
if (!$user_id) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "User not logged in"]);
    exit;
}

if (!$user_id || empty($data['latitude']) || empty($data['longitude'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid data"]);
    exit;
}

// Prepare log entry
$entry = date("Y-m-d H:i:s") . " | Lat: " . $data['latitude'] . " | Lng: " . $data['longitude'] . " | Device Model: " . $data['device_model'] . " | Device ID: " . $data['device_id'] . " | User: $user_id\n";

// Append to log file
file_put_contents($logFile, $entry, FILE_APPEND);

// Fetch recent locations (last 10 minutes)
$recentLocations = $db->where('user_id', $user_id)
                      ->where('device_id', $data['device_id'])
                      ->where('time', time() - 600, '>') // 600 seconds = 10 minutes
                      ->get(T_LOCATIONS);

// Check if any location is within 20 meters
$locationExists = false;
foreach ($recentLocations as $loc) {
    $distance = haversineGreatCircleDistance(
        $data['latitude'], $data['longitude'],
        $loc['lat'], $loc['lng']
    );
    if ($distance < 20) { // 20 meters threshold
        $locationExists = true;
        break;
    }
}

// If no similar recent location found, insert new location
if (!$locationExists) {
    $insert = $db->insert(T_LOCATIONS, [
        'user_id'       => $user_id,
        'lat'           => $data['latitude'],
        'lng'           => $data['longitude'],
        'device_model'  => $data['device_model'],
        'device_id'     => $data['device_id'],
        'time'          => time()
    ]);
}

// Respond with success
echo json_encode([
    "status" => "success",
    "message" => "Location logged",
    "server_config" => $server_config
]);

// Haversine formula to calculate distance between two coordinates in meters
function haversineGreatCircleDistance($lat1, $lon1, $lat2, $lon2, $earthRadius = 6371000) {
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $latDelta = $lat2 - $lat1;
    $lonDelta = $lon2 - $lon1;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius; // distance in meters
}
?>
