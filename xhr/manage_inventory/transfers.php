<?php

/*
 * CIVIC TRANSFER OPERATIONS ENDPOINTS
 * Handles plot and owner name transfers
 * Separated from: need_seperated_by_category.php
 */

// Get transfer data for client
function get_transfer_data() {
    global $db, $sqlConnect;
    
    if ($_GET['action'] === 'get_transfer_data') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            // Get current purchase info
            $purchase = $db->getOne("wo_booking_helper b
                LEFT JOIN wo_booking w ON b.booking_id = w.id
                LEFT JOIN crm_customers c ON b.client_id = c.id",
                "WHERE b.id = $purchase_id");
            
            // Get available plots in same project
            $available_plots = $db->query("SELECT w.*, 
                CASE WHEN b.id IS NOT NULL THEN 'sold' ELSE 'available' END as availability
            FROM wo_booking w
            LEFT JOIN wo_booking_helper b ON w.id = b.booking_id AND b.status = '2'
            WHERE w.project = ? AND w.block = ? AND w.status IN (0, 1)
            ORDER BY w.plot ASC", 
            [$purchase->project, $purchase->block]);
            
            echo json_encode([
                'status' => 'success',
                'current_purchase' => $purchase,
                'available_plots' => $available_plots ?: []
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

// Transfer plot to new plot
function transfer_purchase() {
    global $db, $sqlConnect, $wo;
    
    if ($_POST['action'] === 'transfer_purchase') {
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        $new_booking_id = intval($_POST['new_booking_id'] ?? 0);
        $transfer_reason = $_POST['transfer_reason'] ?? '';
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$purchase_id || !$new_booking_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid transfer data']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            // Get old and new plot details
            $old_purchase = $db->getOne("wo_booking_helper", "WHERE id = $purchase_id");
            $old_plot = $db->getOne("wo_booking", "WHERE id = {$old_purchase->booking_id}");
            $new_plot = $db->getOne("wo_booking", "WHERE id = $new_booking_id");
            
            // Update purchase with new plot
            $db->update("wo_booking_helper", [
                'booking_id' => $new_booking_id,
                'updated_at' => time()
            ], "WHERE id = $purchase_id");
            
            // Create transfer history record
            $db->insert("crm_transfer_history", [
                'purchase_id' => $purchase_id,
                'client_id' => $old_purchase->client_id,
                'old_booking_id' => $old_purchase->booking_id,
                'new_booking_id' => $new_booking_id,
                'old_plot_details' => json_encode([
                    'project' => $old_plot->project,
                    'block' => $old_plot->block,
                    'plot' => $old_plot->plot,
                    'katha' => $old_plot->katha
                ]),
                'new_plot_details' => json_encode([
                    'project' => $new_plot->project,
                    'block' => $new_plot->block,
                    'plot' => $new_plot->plot,
                    'katha' => $new_plot->katha
                ]),
                'transfer_reason' => $transfer_reason,
                'transfer_date' => date('Y-m-d'),
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Recalculate payment schedule if katha changed
            if ($old_plot->katha != $new_plot->katha) {
                recalculate_payment_schedule_for_transfer($purchase_id, $old_plot->katha, $new_plot->katha, $user_id);
            }
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Transfer successful',
                'new_plot' => $new_plot
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

// Transfer owner name
function process_name_transfer() {
    global $db, $sqlConnect, $wo;
    
    if ($_POST['action'] === 'process_name_transfer') {
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        $new_client_id = intval($_POST['new_client_id'] ?? 0);
        $transfer_reason = $_POST['transfer_reason'] ?? '';
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$purchase_id || !$new_client_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid transfer data']);
            exit;
        }
        
        try {
            $db->startTransaction();
            
            $old_client = $db->getOne("crm_customers", "WHERE id = (SELECT client_id FROM wo_booking_helper WHERE id = $purchase_id)");
            $new_client = $db->getOne("crm_customers", "WHERE id = $new_client_id");
            
            // Update purchase with new client
            $db->update("wo_booking_helper", [
                'client_id' => $new_client_id,
                'updated_at' => time()
            ], "WHERE id = $purchase_id");
            
            // Update payment schedule with new client
            $db->update("crm_payment_schedule", [
                'client_id' => $new_client_id
            ], "WHERE purchase_id = $purchase_id");
            
            // Create transfer history record
            $db->insert("crm_transfer_history", [
                'purchase_id' => $purchase_id,
                'old_client_id' => $old_client->id,
                'new_client_id' => $new_client_id,
                'old_client_name' => $old_client->name,
                'new_client_name' => $new_client->name,
                'transfer_type' => 'name',
                'transfer_reason' => $transfer_reason,
                'transfer_date' => date('Y-m-d'),
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Name transfer successful',
                'new_client' => $new_client
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

// Process plot transfer
function process_plot_transfer() {
    global $db, $sqlConnect, $wo;
    
    if ($_POST['action'] === 'process_plot_transfer') {
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        $new_plot_id = intval($_POST['new_plot_id'] ?? 0);
        $transfer_reason = $_POST['transfer_reason'] ?? '';
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$purchase_id || !$new_plot_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid plot data']);
            exit;
        }
        
        // Use transfer_purchase as it handles plot transfers
        $_POST['new_booking_id'] = $new_plot_id;
        transfer_purchase();
    }
}

// Get transfer history for purchase
function get_transfer_history() {
    global $db;
    
    if ($_GET['action'] === 'get_transfer_history') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            $history = $db->query("SELECT * FROM crm_transfer_history 
                WHERE purchase_id = $purchase_id 
                ORDER BY created_at DESC");
            
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

// Helper: Recalculate payment schedule for transfer
function recalculate_payment_schedule_for_transfer($purchase_id, $old_katha, $new_katha, $user_id) {
    global $db;
    
    $schedule = $db->query("SELECT * FROM crm_payment_schedule WHERE purchase_id = $purchase_id");
    
    if ($schedule) {
        $ratio = $new_katha / $old_katha;
        
        foreach ($schedule as $item) {
            $old_amount = $item['installment_amount'];
            $new_amount = round($old_amount * $ratio, 2);
            
            $db->update("crm_payment_schedule", [
                'installment_amount' => $new_amount,
                'previous_amount' => $old_amount,
                'recalculated_due_to' => 'plot_change',
                'recalculation_date' => date('Y-m-d H:i:s'),
                'change_reason' => "Katha changed from $old_katha to $new_katha",
                'updated_by' => $user_id
            ], "WHERE id = {$item['id']}");
        }
    }
}


?>
