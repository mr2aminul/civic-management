<?php
// calls.php

// Enable error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include common configuration and function files
require_once 'common.php';

// Define the log file for debugging
$logFile = 'data/calls_debug.log';

// Helper function to write debug messages
function debugLog($message, $override = false) {
    global $logFile;
    if ($override) {
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    } else {
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}

// Validate user session using the provided user_id parameter
$user_id = $_GET['user_id'] ?? '';
debugLog("User ID: " . $user_id);

// Collect the incoming call data from GET parameters.
if (isset($_GET['timestamp'])) {
    $rawTimestamp = $_GET['timestamp'];
    debugLog("Raw timestamp from GET: " . $rawTimestamp);
    if (is_numeric($rawTimestamp)) {
        $timestamp = $rawTimestamp - 21600;
    } else {
        $timestamp = strtotime($rawTimestamp) - 21600;
    }
} else {
    $timestamp = time();
}
debugLog("Converted timestamp (UTC): " . $timestamp);

$callData = [
    'timestamp' => $timestamp,
    'user_id'   => $user_id,
    'number'    => $_GET['number'] ?? '',
    'status'    => $_GET['status'] ?? '',
    'duration'  => $_GET['duration'] ?? 0,
    'call_type' => $_GET['call_type'] ?? ''
];

debugLog("Call data received: " . json_encode($callData), true);

if (!$user_id) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "User not logged in"]);
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

// Determine remark
if ($callData['call_type'] == 'outgoing') {
    if ($callData['status'] == 'unreachable') {
        $remark = 'Followup: Maybe Unreachable.';
    } else if ($callData['status'] == 'missed_call') {
        $remark = 'Followup: Not Answered.';
    } else if ($callData['status'] == 'answered') {
        $minutes = floor($callData['duration'] / 60);
        $seconds = $callData['duration'] % 60;
        $formattedDuration = $minutes > 0 ? sprintf('%dmin %02dsec', $minutes, $seconds) : sprintf('%dsec', $seconds);
        $remark = 'Followup: Duration ' . $formattedDuration . '.';
    } else {
        $remark = 'Followup: call status is unclear.';
    }
} else {
    if ($callData['status'] == 'unreachable') {
        $remark = 'Incoming: call could not be connected. The caller might try again.';
    } else if ($callData['status'] == 'missed_call') {
        $remark = 'Incoming: You missed an incoming call clients.';
    } else if ($callData['status'] == 'answered') {
        $minutes = floor($callData['duration'] / 60);
        $seconds = $callData['duration'] % 60;
        $formattedDuration = $minutes > 0 ? sprintf('%dmin %02dsec', $minutes, $seconds) : sprintf('%dsec', $seconds);
        $remark = 'Incoming: Duration ' . $formattedDuration . '.';
    } else {
        $remark = 'Incoming call status is unclear.';
    }
}
debugLog("Generated remark: " . $remark);

if (!empty($remark)) {
    $normalized = normalizeToInternational($callData['number']);
    $localVersion = correctNumber(str_replace($defaultCountryCode = '880', '0', $normalized));

    $phone_array = [$normalized, $localVersion];
    debugLog("Phone array: " . json_encode($phone_array));

    $lead_data = $db->where('phone', $phone_array, 'IN')
                    ->where('(assigned = ? OR member = ?)', [$callData['user_id'], $callData['user_id']])
                    ->orderBy('lead_id', 'DESC')
                    ->getOne(T_LEADS);

    if ($lead_data) {
        debugLog("Lead data found: " . json_encode($lead_data));
        $timeWindow = 120;
        $startTime = $callData['timestamp'] - $timeWindow;
        debugLog("Time window for duplicate check: $timeWindow seconds. Start time: $startTime");

        $existingRemark = $db->where('lead_id', $lead_data->lead_id)
                             ->where('remarks', $remark)
                             ->where('time', $startTime, '>=')
                             ->getOne(T_REMARKS);

        debugLog("Duplicate check result: " . json_encode($existingRemark));

        if (!$existingRemark) {
            debugLog("No duplicate found. Inserting remark.");
            $insert = $db->insert(T_REMARKS, [
                'lead_id'   => $lead_data->lead_id,
                'remarks'   => $remark,
                'is_system' => 1,
                'time'      => $callData['timestamp']
            ]);

            if ($insert) {
                if (empty($lead_data->remarks)) {
                    debugLog("Remark inserted successfully. Updating lead record.");
                    $db->where('lead_id', $lead_data->lead_id)
                       ->update(T_LEADS, [
                           'status'  => 4,
                           'remarks' => $remark
                       ]);
                } else {
                    debugLog("User already writed the lead remark.");
                }
            } else {
                debugLog("Remark insertion failed.");
            }
        } else {
            debugLog("Duplicate detected for lead_id: {$lead_data->lead_id}. Remark insertion skipped.");
        }
    } else {
        debugLog("No matching lead found for phone: " . json_encode($phone_array));
    }
}

$response = ["status" => "success", "message" => "Call data received and logged"];
echo json_encode($response);
debugLog("Response sent: " . json_encode($response));