<?php

/*
 * CIVIC PURCHASE REGISTRATION & MANAGEMENT ENDPOINTS
 * Handles purchase creation and assignment
 * Separated from: need_seperated_by_category.php
 */

// Register new purchase
function register_purchase() {
    global $db, $wo;
    
    if ($_POST['action'] === 'register_purchase') {
        $client_id = intval($_POST['client_id'] ?? 0);
        $plot_id = intval($_POST['plot_id'] ?? 0);
        $booking_money = floatval($_POST['booking_money'] ?? 0);
        $down_payment = floatval($_POST['down_payment'] ?? 0);
        $installments_json = $_POST['installments'] ?? '[]';
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$client_id || !$plot_id) {
            echo json_encode(['status' => 'error', 'message' => 'Client and plot required']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            // Create purchase record
            $purchase_data = [
                'booking_id' => $plot_id,
                'client_id' => $client_id,
                'file_num' => date('Ymd') . uniqid(),
                'status' => '2',
                'time' => time(),
                'updated_at' => time(),
                'booking_money' => $booking_money,
                'down_payment' => $down_payment,
                'installment' => $installments_json
            ];
            
            $purchase_id = $db->insert("wo_booking_helper", $purchase_data);
            
            // Create payment schedule entries
            $schedule_entries = [];
            
            // Booking money
            if ($booking_money > 0) {
                $db->insert("crm_payment_schedule", [
                    'purchase_id' => $purchase_id,
                    'client_id' => $client_id,
                    'installment_number' => 0,
                    'particular' => 'Booking Money',
                    'type' => 'booking',
                    'due_date' => date('Y-m-d', strtotime('+7 days')),
                    'installment_amount' => $booking_money,
                    'status' => 0,
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Down payment
            if ($down_payment > 0) {
                $db->insert("crm_payment_schedule", [
                    'purchase_id' => $purchase_id,
                    'client_id' => $client_id,
                    'installment_number' => 0,
                    'particular' => 'Down Payment',
                    'type' => 'down',
                    'due_date' => date('Y-m-d', strtotime('+30 days')),
                    'installment_amount' => $down_payment,
                    'status' => 0,
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Regular installments
            $installments = json_decode($installments_json, true);
            if (!empty($installments) && is_array($installments)) {
                foreach ($installments as $index => $installment) {
                    $db->insert("crm_payment_schedule", [
                        'purchase_id' => $purchase_id,
                        'client_id' => $client_id,
                        'installment_number' => $index + 1,
                        'particular' => $installment['particular'] ?? "Installment " . ($index + 1),
                        'type' => 'installment',
                        'due_date' => $installment['due_date'] ?? date('Y-m-d', strtotime("+60 days")),
                        'installment_amount' => floatval($installment['amount'] ?? 0),
                        'status' => 0,
                        'created_by' => $user_id,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Create audit trail
            $db->insert("crm_audit_trail", [
                'purchase_id' => $purchase_id,
                'action' => 'register_purchase',
                'details' => json_encode([
                    'client_id' => $client_id,
                    'plot_id' => $plot_id,
                    'booking_money' => $booking_money,
                    'down_payment' => $down_payment
                ]),
                'performed_by' => $user_id,
                'performed_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Purchase registered successfully',
                'purchase_id' => $purchase_id
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

// Assign purchase to existing plot
function assign_purchase() {
    global $db, $wo;
    
    if ($_POST['action'] === 'assign_purchase') {
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        $plot_id = intval($_POST['plot_id'] ?? 0);
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$purchase_id || !$plot_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase and plot required']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            $db->update("wo_booking_helper", [
                'booking_id' => $plot_id,
                'updated_at' => time()
            ], "WHERE id = $purchase_id");
            
            $db->insert("crm_audit_trail", [
                'purchase_id' => $purchase_id,
                'action' => 'assign_plot',
                'details' => json_encode(['plot_id' => $plot_id]),
                'performed_by' => $user_id,
                'performed_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Plot assigned successfully'
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


?>
