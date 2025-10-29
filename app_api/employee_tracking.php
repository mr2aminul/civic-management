<?php
/**
 * Employee Tracking API Endpoint
 * Handles location, call logs, app usage, and activity tracking
 * 
 * Endpoints:
 * POST /employee_tracking.php?action=sync_location
 * POST /employee_tracking.php?action=sync_call_logs
 * POST /employee_tracking.php?action=sync_app_usage
 * GET /employee_tracking.php?action=get_employee_activity&user_id=X&date=YYYY-MM-DD
 * GET /employee_tracking.php?action=get_location_history&user_id=X&limit=100
 */

header('Content-Type: application/json');
require_once 'common.php';

// Define the log file for debugging (optional)
$logFile = 'data/employee_tracking.log';

// Helper function to write debug messages
function debugLog($message) {
    global $logFile;
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

debugLog("Batch import started.");

$data = json_decode(file_get_contents('php://input'), true);
debugLog($data);


$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
    exit;
}

try {
    switch ($action) {
        case 'sync_location':
            syncLocationData($user_id);
            break;
            
        case 'sync_call_logs':
            syncCallLogs($user_id);
            break;
            
        case 'sync_app_usage':
            syncAppUsage($user_id);
            break;
            
        case 'get_employee_activity':
            getEmployeeActivity($user_id);
            break;
            
        case 'get_location_history':
            getLocationHistory($user_id);
            break;
            
        case 'get_call_history':
            getCallHistory($user_id);
            break;
            
        case 'get_app_usage_stats':
            getAppUsageStats($user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Sync location data from mobile app
 */
function syncLocationData($user_id) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['locations']) || !is_array($data['locations'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'locations array is required']);
        return;
    }
    
    $synced_count = 0;
    $failed_count = 0;
    
    foreach ($data['locations'] as $location) {
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;
        $accuracy = $location['accuracy'] ?? null;
        $timestamp = $location['timestamp'] ?? time() * 1000;
        
        if ($latitude === null || $longitude === null) {
            $failed_count++;
            continue;
        }
        
        $query = "INSERT INTO employee_location_history 
                  (user_id, latitude, longitude, accuracy, timestamp, created_at) 
                  VALUES (?, ?, ?, ?, FROM_UNIXTIME(?/1000), NOW())
                  ON DUPLICATE KEY UPDATE 
                  accuracy = VALUES(accuracy), 
                  updated_at = NOW()";
        
        $stmt = $conn->prepare($query);
        if ($stmt->execute([$user_id, $latitude, $longitude, $accuracy, $timestamp])) {
            $synced_count++;
        } else {
            $failed_count++;
        }
        $stmt->close();
    }
    
    echo json_encode([
        'status' => 'success',
        'synced_count' => $synced_count,
        'failed_count' => $failed_count,
        'message' => "Synced $synced_count locations"
    ]);
}

/**
 * Sync call logs from mobile app
 */
function syncCallLogs($user_id) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['call_logs']) || !is_array($data['call_logs'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'call_logs array is required']);
        return;
    }
    
    $synced_count = 0;
    
    foreach ($data['call_logs'] as $call) {
        $phone_number = $call['phone_number'] ?? null;
        $call_type = $call['call_type'] ?? 0; // 1=incoming, 2=outgoing, 3=missed
        $duration = $call['duration'] ?? 0;
        $contact_name = $call['contact_name'] ?? null;
        $timestamp = $call['timestamp'] ?? time() * 1000;
        
        if (!$phone_number) continue;
        
        $query = "INSERT INTO employee_call_logs 
                  (user_id, phone_number, call_type, duration, contact_name, timestamp, created_at) 
                  VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?/1000), NOW())";
        
        $stmt = $conn->prepare($query);
        if ($stmt->execute([$user_id, $phone_number, $call_type, $duration, $contact_name, $timestamp])) {
            $synced_count++;
        }
        $stmt->close();
    }
    
    echo json_encode([
        'status' => 'success',
        'synced_count' => $synced_count,
        'message' => "Synced $synced_count call logs"
    ]);
}

/**
 * Sync app usage data from mobile app
 */
function syncAppUsage($user_id) {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['app_usage']) || !is_array($data['app_usage'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'app_usage array is required']);
        return;
    }
    
    $synced_count = 0;
    
    foreach ($data['app_usage'] as $app) {
        $package_name = $app['package_name'] ?? null;
        $app_name = $app['app_name'] ?? null;
        $usage_time = $app['usage_time'] ?? 0;
        $last_used = $app['last_used'] ?? time() * 1000;
        
        if (!$package_name) continue;
        
        $query = "INSERT INTO employee_app_usage 
                  (user_id, package_name, app_name, usage_time, last_used, created_at) 
                  VALUES (?, ?, ?, ?, FROM_UNIXTIME(?/1000), NOW())
                  ON DUPLICATE KEY UPDATE 
                  usage_time = usage_time + VALUES(usage_time),
                  last_used = VALUES(last_used),
                  updated_at = NOW()";
        
        $stmt = $conn->prepare($query);
        if ($stmt->execute([$user_id, $package_name, $app_name, $usage_time, $last_used])) {
            $synced_count++;
        }
        $stmt->close();
    }
    
    echo json_encode([
        'status' => 'success',
        'synced_count' => $synced_count,
        'message' => "Synced $synced_count app usage records"
    ]);
}

/**
 * Get employee activity summary for a specific date
 */
function getEmployeeActivity($user_id) {
    global $conn;
    
    $date = $_GET['date'] ?? date('Y-m-d');
    $start_time = strtotime($date . ' 00:00:00');
    $end_time = strtotime($date . ' 23:59:59');
    
    $activity = [
        'date' => $date,
        'location_points' => 0,
        'call_count' => 0,
        'total_call_duration' => 0,
        'apps_used' => 0,
        'location_history' => [],
        'call_summary' => [],
        'app_summary' => []
    ];
    
    // Get location points
    $query = "SELECT COUNT(*) as count FROM employee_location_history 
              WHERE user_id = ? AND timestamp BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $start_time, $end_time]);
    $result = $stmt->get_result();
    $activity['location_points'] = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    // Get call summary
    $query = "SELECT call_type, COUNT(*) as count, SUM(duration) as total_duration 
              FROM employee_call_logs 
              WHERE user_id = ? AND timestamp BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)
              GROUP BY call_type";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $start_time, $end_time]);
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activity['call_summary'][] = $row;
        $activity['call_count'] += $row['count'];
        $activity['total_call_duration'] += $row['total_duration'];
    }
    $stmt->close();
    
    // Get app usage summary
    $query = "SELECT app_name, package_name, SUM(usage_time) as total_usage 
              FROM employee_app_usage 
              WHERE user_id = ? AND created_at BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)
              GROUP BY package_name
              ORDER BY total_usage DESC
              LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $start_time, $end_time]);
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activity['app_summary'][] = $row;
        $activity['apps_used']++;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'data' => $activity
    ]);
}

/**
 * Get location history for employee
 */
function getLocationHistory($user_id) {
    global $conn;
    
    $limit = $_GET['limit'] ?? 100;
    $days = $_GET['days'] ?? 7;
    $start_date = date('Y-m-d H:i:s', strtotime("-$days days"));
    
    $query = "SELECT id, latitude, longitude, accuracy, timestamp 
              FROM employee_location_history 
              WHERE user_id = ? AND timestamp >= ?
              ORDER BY timestamp DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssi', $user_id, $start_date, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'count' => count($locations),
        'data' => $locations
    ]);
}

/**
 * Get call history for employee
 */
function getCallHistory($user_id) {
    global $conn;
    
    $limit = $_GET['limit'] ?? 50;
    $days = $_GET['days'] ?? 7;
    $start_date = date('Y-m-d H:i:s', strtotime("-$days days"));
    
    $query = "SELECT id, phone_number, call_type, duration, contact_name, timestamp 
              FROM employee_call_logs 
              WHERE user_id = ? AND timestamp >= ?
              ORDER BY timestamp DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssi', $user_id, $start_date, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $calls = [];
    while ($row = $result->fetch_assoc()) {
        $calls[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'count' => count($calls),
        'data' => $calls
    ]);
}

/**
 * Get app usage statistics for employee
 */
function getAppUsageStats($user_id) {
    global $conn;
    
    $limit = $_GET['limit'] ?? 20;
    $days = $_GET['days'] ?? 7;
    $start_date = date('Y-m-d H:i:s', strtotime("-$days days"));
    
    $query = "SELECT package_name, app_name, SUM(usage_time) as total_usage, MAX(last_used) as last_used 
              FROM employee_app_usage 
              WHERE user_id = ? AND created_at >= ?
              GROUP BY package_name
              ORDER BY total_usage DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssi', $user_id, $start_date, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $apps = [];
    while ($row = $result->fetch_assoc()) {
        $apps[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'count' => count($apps),
        'data' => $apps
    ]);
}
?>
