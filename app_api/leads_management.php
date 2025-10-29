<?php
/**
 * Leads Management API Endpoint
 * Handles lead notifications, follow-ups, and lead bubble system
 * 
 * Endpoints:
 * GET /leads_management.php?action=get_leads&user_id=X
 * POST /leads_management.php?action=update_lead_status
 * POST /leads_management.php?action=log_followup
 * GET /leads_management.php?action=get_lead_details&lead_id=X
 */

header('Content-Type: application/json');
require_once 'common.php';

// Define the log file for debugging (optional)
$logFile = 'data/leads_management.log';

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

try {
    switch ($action) {
        case 'get_leads':
            getLeads($user_id);
            break;
            
        case 'get_lead_details':
            getLeadDetails();
            break;
            
        case 'update_lead_status':
            updateLeadStatus();
            break;
            
        case 'log_followup':
            logFollowup();
            break;
            
        case 'get_pending_followups':
            getPendingFollowups($user_id);
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
 * Get all leads for an employee
 */
function getLeads($user_id) {
    global $conn;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
        return;
    }
    
    $query = "SELECT id, lead_id, lead_name, lead_phone, lead_email, lead_status, 
                     last_followup, next_followup, created_at 
              FROM leads 
              WHERE assigned_to = ? 
              ORDER BY last_followup ASC, created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leads = [];
    while ($row = $result->fetch_assoc()) {
        $leads[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'count' => count($leads),
        'data' => $leads
    ]);
}

/**
 * Get detailed information about a specific lead
 */
function getLeadDetails() {
    global $conn;
    
    $lead_id = $_GET['lead_id'] ?? $_POST['lead_id'] ?? null;
    
    if (!$lead_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'lead_id is required']);
        return;
    }
    
    $query = "SELECT * FROM leads WHERE lead_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $lead_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lead = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lead) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Lead not found']);
        return;
    }
    
    // Get follow-up history
    $query = "SELECT id, followup_date, notes, status, created_at 
              FROM lead_followups 
              WHERE lead_id = ? 
              ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $lead_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $followups = [];
    while ($row = $result->fetch_assoc()) {
        $followups[] = $row;
    }
    $stmt->close();
    
    $lead['followup_history'] = $followups;
    
    echo json_encode([
        'status' => 'success',
        'data' => $lead
    ]);
}

/**
 * Update lead status
 */
function updateLeadStatus() {
    global $conn;
    
    $lead_id = $_POST['lead_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $user_id = $_POST['user_id'] ?? null;
    
    if (!$lead_id || !$status || !$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'lead_id, status, and user_id are required']);
        return;
    }
    
    $query = "UPDATE leads SET lead_status = ?, updated_at = NOW() 
              WHERE lead_id = ? AND assigned_to = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sss', $status, $lead_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Lead status updated'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update lead status']);
    }
    $stmt->close();
}

/**
 * Log a follow-up for a lead
 */
function logFollowup() {
    global $conn;
    
    $lead_id = $_POST['lead_id'] ?? null;
    $user_id = $_POST['user_id'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $followup_date = $_POST['followup_date'] ?? null;
    
    if (!$lead_id || !$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'lead_id and user_id are required']);
        return;
    }
    
    $query = "INSERT INTO lead_followups (lead_id, user_id, notes, followup_date, status, created_at) 
              VALUES (?, ?, ?, ?, 'completed', NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $lead_id, $user_id, $notes, $followup_date);
    
    if ($stmt->execute()) {
        // Update lead's last_followup
        $update_query = "UPDATE leads SET last_followup = NOW() WHERE lead_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('s', $lead_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Follow-up logged successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to log follow-up']);
    }
    $stmt->close();
}

/**
 * Get pending follow-ups for an employee
 */
function getPendingFollowups($user_id) {
    global $conn;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
        return;
    }
    
    $query = "SELECT l.id, l.lead_id, l.lead_name, l.lead_phone, l.next_followup, 
                     lf.notes, lf.followup_date
              FROM leads l
              LEFT JOIN lead_followups lf ON l.lead_id = lf.lead_id
              WHERE l.assigned_to = ? AND l.next_followup <= NOW()
              ORDER BY l.next_followup ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $followups = [];
    while ($row = $result->fetch_assoc()) {
        $followups[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'count' => count($followups),
        'data' => $followups
    ]);
}
?>
