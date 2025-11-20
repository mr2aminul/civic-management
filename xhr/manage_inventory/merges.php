<?php
/**
 * Purchase Merge Module - Merge Management with Admin Approval
 * Handles: merge requests, approval workflow, merge execution
 */

header('Content-Type: application/json; charset=utf-8');

if ($s === 'create_merge_request') {
    try {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $source_purchase_id = isset($_POST['source_purchase_id']) ? intval($_POST['source_purchase_id']) : 0;
        $target_purchase_id = isset($_POST['target_purchase_id']) ? intval($_POST['target_purchase_id']) : 0;

        if (!$client_id || !$source_purchase_id || !$target_purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'All fields required']);
            exit;
        }

        // Verify both purchases belong to same client
        $db->where('id', $source_purchase_id);
        $source = $db->getOne(T_BOOKING_HELPER);
        
        $db->where('id', $target_purchase_id);
        $target = $db->getOne(T_BOOKING_HELPER);

        if (!$source || !$target || $source->customer_id != $client_id || $target->customer_id != $client_id) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase ownership']);
            exit;
        }

        $data = [
            'client_id' => $client_id,
            'source_purchase_id' => $source_purchase_id,
            'target_purchase_id' => $target_purchase_id,
            'approval_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('crm_merge_requests', $data);

        if ($id) {
            echo json_encode(['status' => 200, 'message' => 'Merge request created', 'merge_id' => $id]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to create merge request']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'get_merge_requests') {
    try {
        $approval_status = isset($_GET['status']) ? Wo_Secure($_GET['status']) : '';
        
        $where = [];
        if ($approval_status) $where['approval_status'] = $approval_status;

        $db->orderBy('created_at', 'DESC');
        $merges = $db->where($where)->get('crm_merge_requests');

        $result = [];
        if (!empty($merges)) {
            foreach ($merges as $merge) {
                $result[] = [
                    'id' => $merge->id,
                    'client_id' => $merge->client_id,
                    'source_purchase_id' => $merge->source_purchase_id,
                    'target_purchase_id' => $merge->target_purchase_id,
                    'approval_status' => $merge->approval_status,
                    'created_at' => $merge->created_at
                ];
            }
        }

        echo json_encode(['status' => 200, 'merges' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'approve_merge') {
    try {
        $merge_id = isset($_POST['merge_id']) ? intval($_POST['merge_id']) : 0;
        
        if (!$merge_id) {
            echo json_encode(['status' => 400, 'message' => 'Merge ID required']);
            exit;
        }

        $db->where('id', $merge_id);
        $merge = $db->getOne('crm_merge_requests');

        if (!$merge) {
            echo json_encode(['status' => 404, 'message' => 'Merge request not found']);
            exit;
        }

        if ($merge->approval_status !== 'pending') {
            echo json_encode(['status' => 400, 'message' => 'Merge already ' . $merge->approval_status]);
            exit;
        }

        // Begin transaction to ensure data integrity
        $sqlConnect->begin_transaction();

        try {
            // Update merge status
            $db->where('id', $merge_id);
            $db->update('crm_merge_requests', [
                'approval_status' => 'approved',
                'approved_by' => $wo['user_id'] ?? 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // Transfer all invoices from source to target
            $db->where('purchase_id', $merge->source_purchase_id);
            $db->update('crm_invoices', ['purchase_id' => $merge->target_purchase_id]);

            // Transfer all receipts from source to target
            $db->where('purchase_id', $merge->source_purchase_id);
            $db->update('crm_money_receipts', ['purchase_id' => $merge->target_purchase_id]);

            // Transfer all credits from source to target
            $db->where('purchase_id', $merge->source_purchase_id);
            $db->update('crm_overpayment_credits', ['purchase_id' => $merge->target_purchase_id]);

            // Mark source purchase as merged
            $db->where('id', $merge->source_purchase_id);
            $db->update(T_BOOKING_HELPER, ['status' => 5]); // status 5 = merged

            // Log to audit trail
            $audit_data = [
                'purchase_id' => $merge->target_purchase_id,
                'action_type' => 'merge',
                'action_category' => 'purchase',
                'description' => "Purchase {$merge->source_purchase_id} merged into {$merge->target_purchase_id}",
                'before_value' => json_encode(['status' => 1], JSON_UNESCAPED_UNICODE),
                'after_value' => json_encode(['status' => 5], JSON_UNESCAPED_UNICODE),
                'performed_by' => $wo['user_id'] ?? 0,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $db->insert('crm_audit_trail', $audit_data);

            $sqlConnect->commit();

            echo json_encode(['status' => 200, 'message' => 'Merge approved and executed successfully']);
        } catch (Exception $e) {
            $sqlConnect->rollback();
            throw $e;
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'reject_merge') {
    try {
        $merge_id = isset($_POST['merge_id']) ? intval($_POST['merge_id']) : 0;
        $reason = isset($_POST['reason']) ? Wo_Secure($_POST['reason']) : 'No reason provided';
        
        if (!$merge_id) {
            echo json_encode(['status' => 400, 'message' => 'Merge ID required']);
            exit;
        }

        $db->where('id', $merge_id);
        $result = $db->update('crm_merge_requests', [
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $wo['user_id'] ?? 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($result) {
            echo json_encode(['status' => 200, 'message' => 'Merge request rejected']);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to reject merge']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
