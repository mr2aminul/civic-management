<?php
/**
 * Merge Purchase Module - Consolidate Multiple Purchases into One
 * Handles: Payment schedule consolidation, credit transfer, overpayment handling, admin approval
 */


$user_id = $wo['user']['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    switch ($action) {
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
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ========================================
// CREATE MERGE REQUEST
// ========================================
function createMergeRequest() {
    global $pdo, $user_id;

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
    $source = $pdo->query("SELECT * FROM wo_booking_helper WHERE id = $source_purchase_id AND client_id = '$client_id'")->fetch();
    $target = $pdo->query("SELECT * FROM wo_booking_helper WHERE id = $target_purchase_id AND client_id = '$client_id'")->fetch();

    if (!$source || !$target) {
        throw new Exception('Invalid purchase or client mismatch');
    }

    // Create merge request
    $stmt = $pdo->prepare("
        INSERT INTO crm_merge_requests 
        (source_purchase_id, target_purchase_id, client_id, consolidate_schedule, 
         transfer_paid_amount, transfer_credits, reschedule_payments, merge_reason, 
         requested_by, approval_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->execute([
        $source_purchase_id, $target_purchase_id, $client_id, $consolidate_schedule,
        $transfer_paid_amount, $transfer_credits, $reschedule_payments, $merge_reason, $user_id
    ]);

    $request_id = $pdo->lastInsertId();

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
    global $pdo;

    $client_id = intval($_GET['client_id'] ?? 0);
    $status = trim($_GET['status'] ?? '');

    $query = "SELECT mr.*, 
              sb.booking_id as source_booking_id, tb.booking_id as target_booking_id,
              sc.name as source_client_name, tc.name as target_client_name,
              u1.name as requested_by_name, u2.name as approved_by_name
              FROM crm_merge_requests mr
              LEFT JOIN wo_booking_helper sb ON mr.source_purchase_id = sb.id
              LEFT JOIN wo_booking_helper tb ON mr.target_purchase_id = tb.id
              LEFT JOIN crm_customers sc ON mr.client_id = sc.id
              LEFT JOIN crm_customers tc ON mr.client_id = tc.id
              LEFT JOIN crm_users u1 ON mr.requested_by = u1.id
              LEFT JOIN crm_users u2 ON mr.approved_by = u2.id
              WHERE 1=1";
    $params = [];

    if ($client_id > 0) {
        $query .= " AND mr.client_id = ?";
        $params[] = $client_id;
    }

    if ($status) {
        $query .= " AND mr.approval_status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY mr.request_date DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    global $pdo;

    $merge_request_id = intval($_POST['merge_request_id'] ?? 0);
    if (!$merge_request_id) throw new Exception('Merge request ID required');

    $pdo->beginTransaction();

    try {
        $request = $pdo->query("SELECT * FROM crm_merge_requests WHERE id = $merge_request_id")->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new Exception('Merge request not found');

        $pdo->prepare("
            UPDATE crm_merge_requests 
            SET approval_status = 'approved', approved_by = ?, approval_date = NOW()
            WHERE id = ?
        ")->execute([$user_id, $merge_request_id]);

        // Log audit trail
        logAuditTrail($request['client_id'], $request['source_purchase_id'], 'approval', 'purchase',
            "Merge request #$merge_request_id approved",
            json_encode(['previous_status' => $request['approval_status']]),
            json_encode(['new_status' => 'approved', 'approved_by' => $user_id]),
            'crm_merge_requests', $user_id);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Merge request approved'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// REJECT MERGE REQUEST
// ========================================
function rejectMerge($user_id) {
    global $pdo;

    $merge_request_id = intval($_POST['merge_request_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (!$merge_request_id) throw new Exception('Merge request ID required');

    $request = $pdo->query("SELECT * FROM crm_merge_requests WHERE id = $merge_request_id")->fetch(PDO::FETCH_ASSOC);
    if (!$request) throw new Exception('Merge request not found');

    $pdo->prepare("
        UPDATE crm_merge_requests 
        SET approval_status = 'rejected', approved_by = ?, approval_date = NOW(), rejection_reason = ?
        WHERE id = ?
    ")->execute([$user_id, $rejection_reason, $merge_request_id]);

    // Log audit trail
    logAuditTrail($request['client_id'], $request['source_purchase_id'], 'approval', 'purchase',
        "Merge request #$merge_request_id rejected",
        json_encode(['previous_status' => $request['approval_status']]),
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
    global $pdo;

    $merge_request_id = intval($_POST['merge_request_id'] ?? 0);
    if (!$merge_request_id) throw new Exception('Merge request ID required');

    $pdo->beginTransaction();

    try {
        $request = $pdo->query("SELECT * FROM crm_merge_requests WHERE id = $merge_request_id")->fetch(PDO::FETCH_ASSOC);
        if (!$request) throw new Exception('Merge request not found');
        if ($request['approval_status'] !== 'approved') throw new Exception('Merge not approved');

        $source_id = $request['source_purchase_id'];
        $target_id = $request['target_purchase_id'];
        $client_id = $request['client_id'];

        $source = $pdo->query("SELECT * FROM wo_booking_helper WHERE id = $source_id")->fetch(PDO::FETCH_ASSOC);
        $target = $pdo->query("SELECT * FROM wo_booking_helper WHERE id = $target_id")->fetch(PDO::FETCH_ASSOC);

        // 1. Transfer payment schedules
        if ($request['consolidate_schedule']) {
            $pdo->prepare("
                UPDATE crm_payment_schedule 
                SET purchase_id = ?, updated_by = ?
                WHERE purchase_id = ?
            ")->execute([$target_id, $user_id, $source_id]);
        }

        // 2. Transfer paid amounts & overpayment credits
        if ($request['transfer_paid_amount'] || $request['transfer_credits']) {
            // Get total paid for source
            $source_paid = $pdo->query("
                SELECT SUM(paid_amount) as total FROM crm_payment_schedule WHERE purchase_id = $source_id
            ")->fetch()['total'] ?? 0;

            // Transfer credits
            if ($request['transfer_credits']) {
                $pdo->prepare("
                    UPDATE crm_overpayment_credits 
                    SET purchase_id = ?, updated_at = NOW()
                    WHERE purchase_id = ? AND status = 'active'
                ")->execute([$target_id, $source_id]);
            }
        }

        // 3. Mark source purchase as merged
        $pdo->prepare("
            UPDATE wo_booking_helper 
            SET status = '5', updated_at = ?
            WHERE id = ?
        ")->execute([time(), $source_id]);

        // 4. Mark merge as executed
        $audit_trail_id = logAuditTrail($client_id, $source_id, 'merge', 'purchase',
            "Purchase #$source_id merged into #$target_id. All schedules, payments, and credits transferred.",
            json_encode([
                'source_purchase' => $source_id,
                'source_booking' => $source['booking_id'],
                'source_paid' => $source_paid
            ]),
            json_encode([
                'target_purchase' => $target_id,
                'target_booking' => $target['booking_id'],
                'merge_consolidated' => true,
                'related_clients' => [$client_id, $client_id]
            ]),
            'wo_booking_helper,crm_payment_schedule,crm_overpayment_credits,crm_merge_requests', $user_id);

        $pdo->prepare("
            UPDATE crm_merge_requests 
            SET approval_status = 'completed', merge_executed_at = NOW(), audit_trail_id = ?
            WHERE id = ?
        ")->execute([$audit_trail_id, $merge_request_id]);

        // 5. Create money receipt for audit trail on target purchase
        $today = date('Y-m-d');
        $count = $pdo->query("SELECT COUNT(*) FROM crm_money_receipts WHERE DATE(created_at) = '$today'")->fetchColumn();
        $receipt_number = 'MR-' . date('Ym') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $pdo->prepare("
            INSERT INTO crm_money_receipts 
            (receipt_number, purchase_id, client_id, receipt_date, payment_date, amount_paid, 
             payment_method, invoices_paid, notes, created_by, status)
            VALUES (?, ?, ?, ?, ?, 0, 'system', ?, ?, ?, 'issued')
        ")->execute([
            $receipt_number, $target_id, $client_id, $today, $today,
            json_encode([]),
            "System record for merged purchase #$source_id",
            $user_id
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Merge executed successfully',
            'source_purchase' => $source_id,
            'target_purchase' => $target_id
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// HELPER: Log Audit Trail
// ========================================
function logAuditTrail($client_id, $purchase_id, $action_type, $action_category, $description, $before_value, $after_value, $affected_tables, $user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO crm_audit_trail 
        (client_id, purchase_id, action_type, action_category, description, before_value, after_value, 
         affected_tables, performed_by, ip_address, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
    ");

    $stmt->execute([
        $client_id, $purchase_id, $action_type, $action_category, $description,
        $before_value, $after_value, $affected_tables, $user_id,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    return $pdo->lastInsertId();
}
?>
