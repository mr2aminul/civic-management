<?php

/*
 * CIVIC PENDING CHANGES ENDPOINTS
 * Handles approval workflow for modifications
 * Separated from: need_seperated_by_category.php
 */

// Get pending changes
function get_pending_changes() {
    global $db;
    
    if ($_GET['action'] === 'get_pending_changes') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        try {
            $query = "SELECT * FROM crm_pending_changes WHERE status = 0";
            $params = [];
            
            if ($purchase_id) {
                $query .= " AND purchase_id = ?";
                $params[] = $purchase_id;
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $pending = $db->query($query, $params);
            
            echo json_encode([
                'status' => 'success',
                'data' => $pending ?: []
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Approve pending change
function approve_pending_change() {
    global $db, $wo;
    
    if ($_POST['action'] === 'approve_pending_change') {
        $change_id = intval($_POST['change_id'] ?? 0);
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        $approval_notes = $_POST['approval_notes'] ?? '';
        
        if (!$change_id) {
            echo json_encode(['status' => 'error', 'message' => 'Change ID required']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            $change = $db->getOne("crm_pending_changes", "WHERE id = $change_id");
            
            if (!$change) {
                echo json_encode(['status' => 'error', 'message' => 'Change not found']);
                exit;
            }
            
            $change_data = json_decode($change['change_data'], true);
            
            // Apply the change based on type
            if ($change['change_type'] === 'schedule_modification') {
                $db->update("crm_payment_schedule", $change_data, "WHERE id = {$change['reference_id']}");
            } elseif ($change['change_type'] === 'payment_record') {
                $db->update("crm_payment_schedule", $change_data, "WHERE id = {$change['reference_id']}");
            }
            
            // Mark change as approved
            $db->update("crm_pending_changes", [
                'status' => 1,
                'approved_by' => $user_id,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $approval_notes
            ], "WHERE id = $change_id");
            
            // Create audit trail
            $db->insert("crm_audit_trail", [
                'purchase_id' => $change['purchase_id'],
                'action' => 'approve_change',
                'details' => json_encode([
                    'change_type' => $change['change_type'],
                    'approval_notes' => $approval_notes
                ]),
                'performed_by' => $user_id,
                'performed_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Change approved successfully'
            ]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Deny pending change
function deny_pending_change() {
    global $db, $wo;
    
    if ($_POST['action'] === 'deny_pending_change') {
        $change_id = intval($_POST['change_id'] ?? 0);
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        $denial_reason = $_POST['denial_reason'] ?? '';
        
        if (!$change_id) {
            echo json_encode(['status' => 'error', 'message' => 'Change ID required']);
            exit;
        }
        
        try {
            $change = $db->getOne("crm_pending_changes", "WHERE id = $change_id");
            
            $db->update("crm_pending_changes", [
                'status' => 2,  // Denied
                'denied_by' => $user_id,
                'denied_at' => date('Y-m-d H:i:s'),
                'denial_reason' => $denial_reason
            ], "WHERE id = $change_id");
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Change denied'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Get purchase pending status
function get_purchase_pending_status() {
    global $db;
    
    if ($_GET['action'] === 'get_purchase_pending_status') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            $pending = $db->query("SELECT * FROM crm_pending_changes 
                WHERE purchase_id = $purchase_id AND status = 0");
            
            echo json_encode([
                'status' => 'success',
                'has_pending' => !empty($pending),
                'pending_count' => count($pending ?: []),
                'pending_changes' => $pending ?: []
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}


?>
