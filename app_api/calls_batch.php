<?php
// calls_batch.php

// Enable error reporting for debugging purposes (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response type to JSON\header("Content-Type: application/json");

// Include common configuration and function files
require_once 'common.php';

// Define the log file for debugging (optional)
$logFile = 'data/calls_batch_debug.log';

// Helper function to write debug messages
function debugLog($message) {
    global $logFile;
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

debugLog("Batch import started.");

// Read raw POST body
$body = file_get_contents('php://input');
debugLog("Raw input: " . $body);

// Decode JSON array
$calls = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($calls)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
    exit;
}

function normalizeToInternational($number, $defaultCountryCode = '880') {
    $cleaned = preg_replace('/[^0-9]/', '', $number);
    $cleaned = ltrim($cleaned, '0');
    if (strpos($cleaned, $defaultCountryCode) === 0) {
        return $cleaned;
    }
    if (strpos($cleaned, '00') === 0) {
        $cleaned = substr($cleaned, 2);
    }
    return $defaultCountryCode . $cleaned;
}

function correctNumber($number) {
    $numberStr = (string)$number;
    if (strlen($numberStr) == 10 && substr($numberStr, 0, 1) == '1') {
        return '0' . $numberStr;
    } else {
        return $number;
    }
}

// Loop through each call record
$results = [];
// At the top: function debugLog() already exists

foreach ($calls as $idx => $callData) {
    debugLog("Processing record #{$idx}: " . json_encode($callData));

    // Validate required fields
    $required = ['user_id','number','status','duration','call_type','timestamp'];
    foreach ($required as $field) {
        if (!isset($callData[$field])) {
            debugLog("Missing field {$field} in record #{$idx}");
            continue 2; // skip this record
        }
    }

    // Log User ID
    debugLog("User ID: " . $callData['user_id']);

    // Raw timestamp log
    debugLog("Raw timestamp from GET: " . $callData['timestamp']);

    // Convert timestamp
    $raw = $callData['timestamp'];
    if (is_numeric($raw)) {
        $timestamp = intval($raw) - (6 * 3600);
    } else {
        $timestamp = strtotime($raw);
        if ($timestamp !== false) $timestamp -= (6 * 3600);
    }
    debugLog("Converted timestamp (UTC): " . $timestamp);

    // Log the full call data received (with converted timestamp)
    $callDataWithTimestamp = $callData;
    $callDataWithTimestamp['timestamp'] = $timestamp;
    debugLog("Call data received: " . json_encode($callDataWithTimestamp));

    $user_id = Wo_Secure($callData['user_id']);
    if (empty($user_id)) {
        debugLog("Empty user_id in record #{$idx}");
        continue;
    }

    // Build remark based on type and status
    $status = $callData['status'];
    $duration = intval($callData['duration']);
    $type = $callData['call_type'];

    switch ($type) {
        case 'outgoing':
            if ($status === 'unreachable')         $remark = 'Followup: Maybe Unreachable.';
            elseif ($status === 'missed_call')     $remark = 'Followup: Not Answered.';
            elseif ($status === 'answered')        $remark = 'Followup: Duration ' . gmdate('i\m\i\n s\s\e\c', $duration) . '.';
            else                                  $remark = 'Followup: call status is unclear.';
            break;
        case 'incoming':
        default:
            if ($status === 'unreachable')         $remark = 'Incoming: call could not be connected. The caller might try again.';
            elseif ($status === 'missed_call')     $remark = 'Incoming: You missed an incoming call.';
            elseif ($status === 'answered')        $remark = 'Incoming: Duration ' . gmdate('i\m\i\n s\s\e\c', $duration) . '.';
            else                                  $remark = 'Incoming call status is unclear.';
            break;
    }

    // Log generated remark
    debugLog("Generated remark: " . $remark);

    // Normalize phone numbers
    $normalized = normalizeToInternational($callData['number']);
    $localVersion = correctNumber('0' . substr($normalized, strlen('880')));
    $phone_array = [$normalized, $localVersion];

    // Log phone array
    debugLog("Phone array: " . json_encode($phone_array));

    // Lookup lead
    $lead = $db->where('phone', $phone_array, 'IN')
               ->where('(assigned = ? OR member = ?)', [$user_id, $user_id])
               ->orderBy('lead_id', 'DESC')
               ->getOne(T_LEADS);

    if (!$lead) {
        debugLog("No matching lead found for phone: " . json_encode($phone_array));
        continue;
    }

    // Avoid duplicate remarks within 2 minutes
    $timeWindow = 120;
    $startTime = $timestamp - $timeWindow;
    $exists = $db->where('lead_id', $lead->lead_id)
                 ->where('remarks', $remark)
                 ->where('time', $startTime, '>=')
                 ->getOne(T_REMARKS);

    if ($exists) {
        debugLog("Duplicate remark for lead_id {$lead->lead_id} in record #{$idx}");
        continue;
    }

    // Insert remark
    $ins = $db->insert(T_REMARKS, [
        'lead_id'   => $lead->lead_id,
        'remarks'   => $remark,
        'is_system' => 1,
        'time'      => $timestamp
    ]);

    if ($ins) {
        if ($type === 'outgoing' && $duration > 8) {
            $response_ts = $timestamp;
        } else {
            $response_ts = '0';
        }

        debugLog("Inserted remark for lead_id {$lead->lead_id} in record #{$idx}");
        // Update lead if needed
        if (empty($lead->remarks)) {
            $db->where('lead_id', $lead->lead_id)
               ->update(T_LEADS, ['status' => 4, 'remarks' => $remark, 'response' => $response_ts]);
               //
        }
    } else {
        debugLog("Failed to insert remark for record #{$idx}");
    }
}

// Return a summary response
echo json_encode(["status" => "success", "message" => "Batch processed."]);
debugLog("Batch import completed.");
