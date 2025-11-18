<?php

/*
 * CIVIC PAYMENT RESCHEDULE ENDPOINTS
 * Handles payment rescheduling and adjustments
 * Separated from: need_seperated_by_category.php
 */

// Get reschedule context for purchase
function get_reschedule_context() {
    global $db;
    
    if ($_GET['action'] === 'get_reschedule_context') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            $purchase = $db->getOne("wo_booking_helper b
                LEFT JOIN wo_booking w ON b.booking_id = w.id
                LEFT JOIN crm_customers c ON b.client_id = c.id",
                "WHERE b.id = $purchase_id");
            
            $schedule = $db->query("SELECT * FROM crm_payment_schedule 
                WHERE purchase_id = $purchase_id 
                ORDER BY installment_number ASC");
            
            $total_outstanding = array_sum(array_map(function($item) {
                return $item['status'] == 0 ? $item['installment_amount'] : 0;
            }, $schedule ?: []));
            
            echo json_encode([
                'status' => 'success',
                'purchase' => $purchase,
                'current_schedule' => $schedule ?: [],
                'total_outstanding' => $total_outstanding
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

// Preview payment reschedule
function preview_reschedule() {
    global $db;
    
    if ($_POST['action'] === 'preview_reschedule') {
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        $new_schedule = json_decode($_POST['new_schedule'] ?? '[]', true);
        
        if (!$purchase_id || empty($new_schedule)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid reschedule data']);
            exit;
        }
        
        try {
            $old_schedule = $db->query("SELECT * FROM crm_payment_schedule 
                WHERE purchase_id = $purchase_id 
                ORDER BY installment_number ASC");
            
            echo json_encode([
                'status' => 'success',
                'preview' => [
                    'old_schedule' => $old_schedule ?: [],
                    'new_schedule' => $new_schedule,
                    'changes' => calculate_schedule_changes($old_schedule, $new_schedule)
                ]
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

// Submit payment reschedule
function submit_payment_reschedule() {
    global $db, $wo;
    
    if ($_POST['action'] === 'submit_payment_reschedule') {
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        $new_schedule = json_decode($_POST['new_schedule'] ?? '[]', true);
        $reschedule_reason = $_POST['reason'] ?? '';
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$purchase_id || empty($new_schedule)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid reschedule data']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            // Get old schedule
            $old_schedule = $db->query("SELECT * FROM crm_payment_schedule 
                WHERE purchase_id = $purchase_id AND status = 0");
            
            // Mark old pending entries as rescheduled
            foreach ($old_schedule as $old) {
                $db->update("crm_payment_schedule", [
                    'status' => 5,  // Rescheduled status
                    'remarks' => "Rescheduled. Reason: $reschedule_reason"
                ], "WHERE id = {$old['id']}");
            }
            
            // Create new schedule entries
            foreach ($new_schedule as $index => $item) {
                $db->insert("crm_payment_schedule", [
                    'purchase_id' => $purchase_id,
                    'client_id' => $item['client_id'],
                    'installment_number' => $item['installment_number'],
                    'particular' => $item['particular'],
                    'type' => $item['type'],
                    'due_date' => $item['due_date'],
                    'installment_amount' => floatval($item['amount']),
                    'status' => 0,
                    'recalculated_due_to' => 'manual_reschedule',
                    'recalculation_date' => date('Y-m-d H:i:s'),
                    'change_reason' => $reschedule_reason,
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Create audit trail
            $db->insert("crm_audit_trail", [
                'purchase_id' => $purchase_id,
                'action' => 'reschedule_payment',
                'details' => json_encode([
                    'reason' => $reschedule_reason,
                    'old_installments' => count($old_schedule),
                    'new_installments' => count($new_schedule)
                ]),
                'performed_by' => $user_id,
                'performed_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Payment schedule rescheduled successfully'
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

// Get reschedule history
function get_reschedule_history() {
    global $db;
    
    if ($_GET['action'] === 'get_reschedule_history') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            $history = $db->query("SELECT * FROM crm_audit_trail 
                WHERE purchase_id = $purchase_id AND action = 'reschedule_payment'
                ORDER BY performed_at DESC");
            
            echo json_encode([
                'status' => 'success',
                'data' => $history ?: []
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

// Helper: Calculate differences between schedules
function calculate_schedule_changes($old_schedule, $new_schedule) {
    $changes = [
        'installments_added' => 0,
        'installments_removed' => 0,
        'amount_changed' => 0,
        'due_date_changes' => 0
    ];
    
    $old_count = count($old_schedule ?: []);
    $new_count = count($new_schedule);
    
    if ($new_count > $old_count) {
        $changes['installments_added'] = $new_count - $old_count;
    } elseif ($new_count < $old_count) {
        $changes['installments_removed'] = $old_count - $new_count;
    }
    
    return $changes;
}


?>
