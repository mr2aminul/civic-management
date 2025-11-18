<?php

/*
 * CIVIC CANCELLATION OPERATIONS ENDPOINTS
 * Handles cancellations and refunds
 * Separated from: need_seperated_by_category.php
 */

// Process plot cancellation
function process_cancel_plot() {
    global $db, $sqlConnect, $wo;
    
    if ($_POST['action'] === 'process_cancel_plot') {
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        $cancel_reason = $_POST['cancel_reason'] ?? '';
        $deduction_percentage = floatval($_POST['deduction_percentage'] ?? 10);
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        if ($deduction_percentage < 0 || $deduction_percentage > 100) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid deduction percentage']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            // Update purchase status to cancelled
            $db->update("wo_booking_helper", [
                'status' => '4',
                'cancel_date' => time(),
                'updated_at' => time()
            ], "WHERE id = $purchase_id");
            
            // Get all payment schedule entries
            $schedule = $db->query("SELECT * FROM crm_payment_schedule WHERE purchase_id = $purchase_id AND status != 4");
            
            // Calculate total paid amount
            $total_paid = 0;
            foreach ($schedule as $item) {
                if ($item['status'] == 1) {
                    $total_paid += $item['paid_amount'];
                }
                
                // Mark all as cancelled
                $db->update("crm_payment_schedule", [
                    'status' => 4,
                    'remarks' => "Plot cancelled. Reason: $cancel_reason"
                ], "WHERE id = {$item['id']}");
            }
            
            // Create refund schedule
            $deduction_amount = round(($total_paid * $deduction_percentage) / 100, 2);
            $refundable_amount = $total_paid - $deduction_amount;
            
            $db->insert("crm_refund_schedule", [
                'purchase_id' => $purchase_id,
                'client_id' => $db->getOne("wo_booking_helper", "WHERE id = $purchase_id")->client_id,
                'refund_initiation_date' => date('Y-m-d'),
                'total_paid_amount' => $total_paid,
                'deduction_percentage' => $deduction_percentage,
                'deduction_amount' => $deduction_amount,
                'refundable_amount' => $refundable_amount,
                'installment_number' => 1,
                'installment_amount' => $refundable_amount,
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 0,
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Create audit trail
            $db->insert("crm_audit_trail", [
                'purchase_id' => $purchase_id,
                'action' => 'cancel_plot',
                'details' => json_encode([
                    'reason' => $cancel_reason,
                    'total_paid' => $total_paid,
                    'deduction_percentage' => $deduction_percentage,
                    'refundable_amount' => $refundable_amount
                ]),
                'performed_by' => $user_id,
                'performed_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Plot cancelled successfully',
                'refund_info' => [
                    'total_paid' => $total_paid,
                    'deduction_amount' => $deduction_amount,
                    'refundable_amount' => $refundable_amount
                ]
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

// Cancel all inventory (bulk cancellation)
function cancel_all_inventory() {
    global $db, $sqlConnect, $wo;
    
    if ($_POST['action'] === 'cancel_all_inventory') {
        $purchase_ids = json_decode($_POST['purchase_ids'] ?? '[]', true);
        $cancel_reason = $_POST['cancel_reason'] ?? '';
        $deduction_percentage = floatval($_POST['deduction_percentage'] ?? 10);
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (empty($purchase_ids) || !is_array($purchase_ids)) {
            echo json_encode(['status' => 'error', 'message' => 'No purchases selected']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            $total_refunded = 0;
            $cancelled_count = 0;
            
            foreach ($purchase_ids as $pid) {
                $pid = intval($pid);
                
                // Update purchase status
                $db->update("wo_booking_helper", [
                    'status' => '4',
                    'cancel_date' => time(),
                    'updated_at' => time()
                ], "WHERE id = $pid");
                
                // Get total paid
                $schedule = $db->query("SELECT SUM(paid_amount) as total FROM crm_payment_schedule WHERE purchase_id = $pid AND status = 1");
                $total_paid = $schedule[0]['total'] ?? 0;
                
                // Mark as cancelled
                $db->update("crm_payment_schedule", [
                    'status' => 4,
                    'remarks' => "Bulk cancellation. Reason: $cancel_reason"
                ], "WHERE purchase_id = $pid AND status != 4");
                
                // Create refund
                $deduction_amount = round(($total_paid * $deduction_percentage) / 100, 2);
                $refundable_amount = $total_paid - $deduction_amount;
                
                $db->insert("crm_refund_schedule", [
                    'purchase_id' => $pid,
                    'client_id' => $db->getOne("wo_booking_helper", "WHERE id = $pid")->client_id,
                    'refund_initiation_date' => date('Y-m-d'),
                    'total_paid_amount' => $total_paid,
                    'deduction_percentage' => $deduction_percentage,
                    'deduction_amount' => $deduction_amount,
                    'refundable_amount' => $refundable_amount,
                    'installment_number' => 1,
                    'installment_amount' => $refundable_amount,
                    'due_date' => date('Y-m-d', strtotime('+30 days')),
                    'status' => 0,
                    'created_by' => $user_id
                ]);
                
                $total_refunded += $refundable_amount;
                $cancelled_count++;
            }
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => "Cancelled $cancelled_count purchases",
                'total_refunded' => $total_refunded
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

// Call functions
process_cancel_plot();
cancel_all_inventory();

?>
