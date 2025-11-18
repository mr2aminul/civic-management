<?php
/**
 * Merge Purchase Module - Consolidate Multiple Purchases into One
 * Handles: Payment schedule consolidation, credit transfer, overpayment handling, admin approval
 * Note: Uses global $db (MysqliDb), $wo, and $sqlConnect (raw mysqli)
 */

global $db, $wo, $sqlConnect;

// Get action from POST or GET
$s = isset($_GET['s']) ? trim($_GET['s']) : (isset($_POST['s']) ? trim($_POST['s']) : '');

$user_id = $wo['user']['user_id'] ?? null;

if (!$user_id && $s !== 'get_merge_requests') {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    switch ($s) {
        case 'create_merge_request':
            createMergeRequest();
            break;

        case 'get_merge_requests':
            getMergeRequests();
            break;

        case 'approve_merge':
            approveMerge($user_id);
            break;

        case 'reject_merge':
            rejectMerge($user_id);
            break;

        case 'execute_merge':
            executeMerge($user_id);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $s]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ========================================
// CREATE MERGE REQUEST
// ========================================
function createMergeRequest() {
    global $db, $wo;

    $user_id = $wo['user']['id'] ?? null;
    $source_purchase_id = intval($_POST['source_purchase_id'] ?? 0);
    $target_purchase_id = intval($_POST['target_purchase_id'] ?? 0);
    $client_id = intval($_POST['client_id'] ?? 0);
    $consolidate_schedule = intval($_POST['consolidate_schedule'] ?? 1);
    $transfer_paid_amount = intval($_POST['transfer_paid_amount'] ?? 1);
    $transfer_credits = intval($_POST['transfer_credits'] ?? 1);
    $reschedule_payments = intval($_POST['reschedule_payments'] ?? 1);
    $merge_reason = trim($_POST['merge_reason'] ?? '');

    if (!$source_purchase_id || !$target_purchase_id || !$client_id) {
        throw new Exception('Missing required fields');
    }

    if ($source_purchase_id == $target_purchase_id) {
        throw new Exception('Cannot merge purchase with itself');
    }

    // Validate purchases belong to same client
    $db->where('id', $source_purchase_id);
    $db->where('client_id', $client_id);
    $source = $db->getOne('wo_booking_helper');

    $db->where('id', $target_purchase_id);
    $db->where('client_id', $client_id);
    $target = $db->getOne('wo_booking_helper');

    if (!$source || !$target) {
        throw new Exception('Invalid purchase or client mismatch');
    }

    // Create merge request
    $merge_data = [
        'source_purchase_id' => $source_purchase_id,
        'target_purchase_id' => $target_purchase_id,
        'client_id' => $client_id,
        'consolidate_schedule' => $consolidate_schedule,
        'transfer_paid_amount' => $transfer_paid_amount,
        'transfer_credits' => $transfer_credits,
        'reschedule_payments' => $reschedule_payments,
        'merge_reason' => $merge_reason,
        'requested_by' => $user_id,
        'approval_status' => 'pending',
        'request_date' => date('Y-m-d H:i:s')
    ];

    $request_id = $db->insert('crm_merge_requests', $merge_data);

    if (!$request_id) {
        throw new Exception('Failed to create merge request');
    }

    // Log audit trail
    logAuditTrail($client_id, $source_purchase_id, 'merge', 'purchase',
        "Merge request created: Purchase #$source_purchase_id → #$target_purchase_id",
        null,
        json_encode([
            'merge_request_id' => $request_id,
            'source_purchase' => $source_purchase_id,
            'target_purchase' => $target_purchase_id,
            'reason' => $merge_reason
        ]),
        'crm_merge_requests', $user_id);

    echo json_encode([
        'success' => true,
        'message' => 'Merge request created - pending admin approval',
        'request_id' => $request_id
    ]);
}

// ========================================
// GET MERGE REQUESTS
// ========================================
function getMergeRequests() {
    global $db;

    $client_id = intval($_GET['client_id'] ?? 0);
    $status = trim($_GET['status'] ?? '');

    $db->join('wo_booking_helper sb', 'crm_merge_requests.source_purchase_id = sb.id', 'LEFT');
    $db->join('wo_booking_helper tb', 'crm_merge_requests.target_purchase_id = tb.id', 'LEFT');
    $db->join('crm_customers sc', 'crm_merge_requests.client_id = sc.id', 'LEFT');

    if ($client_id > 0) {
        $db->where('crm_merge_requests.client_id', $client_id);
    }

    if ($status) {
        $db->where('crm_merge_requests.approval_status', $status);
    }

    $db->orderBy('crm_merge_requests.request_date', 'DESC');

    $requests = $db->get('crm_merge_requests', null,
        'crm_merge_requests.*, sb.booking_id as source_booking_id, tb.booking_id as target_booking_id, sc.name as client_name');

    if (!$requests) {
        $requests = [];
    }

    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'count' => count($requests)
    ]);
}

// ========================================
// APPROVE MERGE REQUEST
// ========================================
function approveMerge($user_id) {
    global $db, $sqlConnect;

    $merge_request_id = intval($_POST['merge_request_id'] ?? 0);
    if (!$merge_request_id) throw new Exception('Merge request ID required');

    mysqli_begin_transaction($sqlConnect);

    try {
        $db->where('id', $merge_request_id);
        $request = $db->getOne('crm_merge_requests');

        if (!$request) throw new Exception('Merge request not found');

        // Update merge request
        $db->where('id', $merge_request_id);
        $db->update('crm_merge_requests', [
            'approval_status' => 'approved',
            'approved_by' => $user_id,
            'approval_date' => date('Y-m-d H:i:s')
        ]);

        // Log audit trail
        logAuditTrail($request->client_id, $request->source_purchase_id, 'approval', 'purchase',
            "Merge request #$merge_request_id approved",
            json_encode(['previous_status' => $request->approval_status]),
            json_encode(['new_status' => 'approved', 'approved_by' => $user_id]),
            'crm_merge_requests', $user_id);

        mysqli_commit($sqlConnect);

        echo json_encode([
            'success' => true,
            'message' => 'Merge request approved'
        ]);

    } catch (Exception $e) {
        mysqli_rollback($sqlConnect);
        throw $e;
    }
}

// ========================================
// REJECT MERGE REQUEST
// ========================================
function rejectMerge($user_id) {
    global $db;

    $merge_request_id = intval($_POST['merge_request_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (!$merge_request_id) throw new Exception('Merge request ID required');

    $db->where('id', $merge_request_id);
    $request = $db->getOne('crm_merge_requests');

    if (!$request) throw new Exception('Merge request not found');

    // Update merge request
    $db->where('id', $merge_request_id);
    $db->update('crm_merge_requests', [
        'approval_status' => 'rejected',
        'approved_by' => $user_id,
        'approval_date' => date('Y-m-d H:i:s'),
        'rejection_reason' => $rejection_reason
    ]);

    // Log audit trail
    logAuditTrail($request->client_id, $request->source_purchase_id, 'approval', 'purchase',
        "Merge request #$merge_request_id rejected",
        json_encode(['previous_status' => $request->approval_status]),
        json_encode(['new_status' => 'rejected', 'reason' => $rejection_reason]),
        'crm_merge_requests', $user_id);

    echo json_encode([
        'success' => true,
        'message' => 'Merge request rejected'
    ]);
}

// ========================================
// EXECUTE MERGE (After Approval)
// ========================================
function executeMerge($user_id) {
    global $db, $sqlConnect;

    $merge_request_id = intval($_POST['merge_request_id'] ?? 0);
    if (!$merge_request_id) throw new Exception('Merge request ID required');

    mysqli_begin_transaction($sqlConnect);

    try {
        $db->where('id', $merge_request_id);
        $request = $db->getOne('crm_merge_requests');

        if (!$request) throw new Exception('Merge request not found');
        if ($request->approval_status !== 'approved') throw new Exception('Merge not approved');

        $source_id = $request->source_purchase_id;
        $target_id = $request->target_purchase_id;
        $client_id = $request->client_id;

        $db->where('id', $source_id);
        $source = $db->getOne('wo_booking_helper');

        $db->where('id', $target_id);
        $target = $db->getOne('wo_booking_helper');

        if (!$source || !$target) throw new Exception('Purchase records not found');

        // 1. Transfer payment schedules
        if ($request->consolidate_schedule) {
            $db->where('purchase_id', $source_id);
            $db->update('crm_payment_schedule', [
                'purchase_id' => $target_id,
                'updated_by' => $user_id
            ]);
        }

        // 2. Transfer paid amounts & overpayment credits
        $source_paid = 0;
        if ($request->transfer_paid_amount || $request->transfer_credits) {
            // Get total paid for source
            $db->where('purchase_id', $source_id);
            $source_paid = floatval($db->getValue('crm_payment_schedule', 'SUM(paid_amount)') ?? 0);

            // Transfer credits (table may not exist)
            if ($request->transfer_credits) {
                try {
                    $db->where('purchase_id', $source_id);
                    $db->where('status', 'active');
                    $db->update('crm_overpayment_credits', [
                        'purchase_id' => $target_id,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $e) {
                    // Table may not exist, continue without it
                }
            }
        }

        // 3. Mark source purchase as merged (status 5)
        $db->where('id', $source_id);
        $db->update('wo_booking_helper', [
            'status' => '5',
            'updated_at' => time()
        ]);

        // 4. Mark merge as executed
        $audit_trail_id = logAuditTrail($client_id, $source_id, 'merge', 'purchase',
            "Purchase #$source_id merged into #$target_id. All schedules, payments, and credits transferred.",
            json_encode([
                'source_purchase' => $source_id,
                'source_booking' => $source->booking_id ?? null,
                'source_paid' => $source_paid
            ]),
            json_encode([
                'target_purchase' => $target_id,
                'target_booking' => $target->booking_id ?? null,
                'merge_consolidated' => true,
                'related_clients' => [$client_id, $client_id]
            ]),
            'wo_booking_helper,crm_payment_schedule,crm_overpayment_credits,crm_merge_requests', $user_id);

        $db->where('id', $merge_request_id);
        $db->update('crm_merge_requests', [
            'approval_status' => 'completed',
            'merge_executed_at' => date('Y-m-d H:i:s'),
            'audit_trail_id' => $audit_trail_id
        ]);

        // 5. Create money receipt for audit trail on target purchase
        $today = date('Y-m-d');

        $db->where("DATE(created_at)", $today, '=');
        $count = intval($db->getValue('crm_money_receipts', 'COUNT(*)') ?? 0);
        $receipt_number = 'MR-' . date('Ym') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $db->insert('crm_money_receipts', [
            'receipt_number' => $receipt_number,
            'purchase_id' => $target_id,
            'client_id' => $client_id,
            'receipt_date' => $today,
            'payment_date' => $today,
            'amount_paid' => 0,
            'payment_method' => 'system',
            'invoices_paid' => json_encode([]),
            'notes' => "System record for merged purchase #$source_id",
            'created_by' => $user_id,
            'status' => 'issued'
        ]);

        mysqli_commit($sqlConnect);

        echo json_encode([
            'success' => true,
            'message' => 'Merge executed successfully',
            'source_purchase' => $source_id,
            'target_purchase' => $target_id
        ]);

    } catch (Exception $e) {
        mysqli_rollback($sqlConnect);
        throw $e;
    }
}

// ========================================
// HELPER: Log Audit Trail
// ========================================
function logAuditTrail($client_id, $purchase_id, $action_type, $action_category, $description, $before_value, $after_value, $affected_tables, $user_id) {
    global $db;

    try {
        $audit_data = [
            'client_id' => $client_id,
            'purchase_id' => $purchase_id,
            'action_type' => $action_type,
            'action_category' => $action_category,
            'action_description' => $description,
            'before_values' => $before_value,
            'after_values' => $after_value,
            'performed_by' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'performed_at' => date('Y-m-d H:i:s')
        ];

        return $db->insert('crm_audit_trail', $audit_data);
    } catch (Exception $e) {
        error_log('Audit trail insert failed: ' . $e->getMessage());
        return null;
    }
}
?>
