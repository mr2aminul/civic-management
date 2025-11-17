<?php
/**
 * Transfer & Refund Operation Handlers for manage_inventory.php
 * 
 * New operations:
 * - process_name_transfer: Transfer ownership to another client
 * - process_plot_transfer: Transfer to different plot with rate adjustment
 * - process_cancel_plot: Cancel plot and optionally initiate refund
 * - get_refund_details: Get refund calculation and existing transactions
 * - initiate_refund: Start refund process
 * - add_refund_transaction: Add individual refund transaction
 */

global $db, $wo;

// ==================================================
// PROCESS NAME TRANSFER
// ==================================================
if ($s === 'process_name_transfer') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $new_owner_id = isset($_POST['new_owner_id']) ? (int)$_POST['new_owner_id'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $fee = isset($_POST['fee']) ? (float)$_POST['fee'] : 0;
    $transfer_date = isset($_POST['transfer_date']) ? trim($_POST['transfer_date']) : date('Y-m-d');
    
    if ($purchase_id <= 0 || $new_owner_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        $old_client_id = (int)$helper->client_id;
        $old_client = GetCustomerById($old_client_id);
        $new_client = GetCustomerById($new_owner_id);
        
        if (!$new_client) {
            echo json_encode(['status' => 404, 'message' => 'New client not found']);
            exit;
        }
        
        $db->startTransaction();
        
        // Create transfer record
        $transfer_data = [
            'purchase_id' => $purchase_id,
            'client_id' => $old_client_id,
            'transfer_type' => 'name_transfer',
            'from_client_id' => $old_client_id,
            'to_client_id' => $new_owner_id,
            'transfer_date' => $transfer_date,
            'approval_status' => 1,
            'approval_date' => date('Y-m-d'),
            'approved_by' => isset($wo['user']['id']) ? (int)$wo['user']['id'] : null,
            'transfer_fee_mode' => $fee > 0 ? 'fixed' : 'free',
            'transfer_fee_value' => $fee,
            'transfer_fee_calculated' => $fee,
            'transfer_fee_status' => 'collected',
            'remarks' => $remarks,
            'status' => 1,
            'created_by' => isset($wo['user']['id']) ? (int)$wo['user']['id'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $transfer_id = $db->insert('crm_transfer_history', $transfer_data);
        
        if (!$transfer_id) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Failed to create transfer record']);
            exit;
        }
        
        // Update booking helper with new client
        $update_result = $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
            'client_id' => (string)$new_owner_id,
            'updated_at' => time()
        ]);
        
        if (!$update_result) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Failed to update purchase']);
            exit;
        }
        
        // Log audit
        if (function_exists('log_crm_audit')) {
            log_crm_audit('transfer', 'name_transfer_completed', $purchase_id, [
                'from_client_id' => $old_client_id,
                'from_client_name' => $old_client['name'] ?? 'Unknown',
                'to_client_id' => $new_owner_id,
                'to_client_name' => $new_client['name'] ?? 'Unknown',
                'fee' => $fee
            ], isset($wo['user']['id']) ? $wo['user']['id'] : null);
        }
        
        $db->commit();
        
        echo json_encode([
            'status' => 200,
            'message' => 'Name transfer completed successfully',
            'transfer_id' => $transfer_id
        ]);
        
    } catch (Exception $e) {
        if (method_exists($db, 'rollback')) $db->rollback();
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==================================================
// PROCESS PLOT TRANSFER
// ==================================================
if ($s === 'process_plot_transfer') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $new_plot_id = isset($_POST['new_plot_id']) ? (int)$_POST['new_plot_id'] : 0;
    $custom_rate = isset($_POST['custom_rate']) && $_POST['custom_rate'] !== '' ? (float)$_POST['custom_rate'] : null;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $fee = isset($_POST['fee']) ? (float)$_POST['fee'] : 0;
    $transfer_date = isset($_POST['transfer_date']) ? trim($_POST['transfer_date']) : date('Y-m-d');
    
    if ($purchase_id <= 0 || $new_plot_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        $old_plot = $db->where('id', (int)$helper->booking_id)->getOne(T_BOOKING);
        $new_plot = $db->where('id', $new_plot_id)->getOne(T_BOOKING);
        
        if (!$old_plot || !$new_plot) {
            echo json_encode(['status' => 404, 'message' => 'Plot not found']);
            exit;
        }
        
        $old_rate = (float)($helper->per_katha ?? 0);
        $new_rate = $custom_rate !== null ? $custom_rate : $old_rate;
        $old_katha = (float)($old_plot->katha ?? 0);
        $new_katha = (float)($new_plot->katha ?? 0);
        $old_total = $old_rate * $old_katha;
        $new_total = $new_rate * $new_katha;
        $rate_adjustment = $new_total - $old_total;
        
        $db->startTransaction();
        
        // Create transfer record
        $transfer_data = [
            'purchase_id' => $purchase_id,
            'client_id' => (int)$helper->client_id,
            'transfer_type' => 'plot_transfer',
            'from_client_id' => (int)$helper->client_id,
            'to_client_id' => (int)$helper->client_id,
            'transfer_date' => $transfer_date,
            'approval_status' => 1,
            'approval_date' => date('Y-m-d'),
            'plot_transfer_rate_old' => $old_rate,
            'plot_transfer_rate_new' => $new_rate,
            'rate_adjustment_amount' => $rate_adjustment,
            'plot_transfer_details' => json_encode([
                'old_plot_id' => (int)$helper->booking_id,
                'new_plot_id' => $new_plot_id,
                'old_katha' => $old_katha,
                'new_katha' => $new_katha,
                'old_total' => $old_total,
                'new_total' => $new_total
            ]),
            'transfer_fee_mode' => $fee > 0 ? 'fixed' : 'free',
            'transfer_fee_value' => $fee,
            'transfer_fee_calculated' => $fee,
            'transfer_fee_status' => 'collected',
            'remarks' => $remarks,
            'status' => 1,
            'approved_by' => isset($wo['user']['id']) ? (int)$wo['user']['id'] : null,
            'created_by' => isset($wo['user']['id']) ? (int)$wo['user']['id'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $transfer_id = $db->insert('crm_transfer_history', $transfer_data);
        
        if (!$transfer_id) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Failed to create transfer record']);
            exit;
        }
        
        // Update helper with new plot and rate
        $helper_update = $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
            'booking_id' => $new_plot_id,
            'per_katha' => $new_rate,
            'updated_at' => time()
        ]);
        
        if (!$helper_update) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Failed to update purchase']);
            exit;
        }
        
        // Update plot statuses
        $db->where('id', (int)$helper->booking_id)->update(T_BOOKING, ['status' => 1]); // Old plot: available
        $db->where('id', $new_plot_id)->update(T_BOOKING, ['status' => 2]); // New plot: sold
        
        // Mark old schedule as recalculated
        $db->where('purchase_id', $purchase_id)->update('crm_payment_schedule', [
            'recalculated_due_to' => 'plot_change',
            'recalculation_date' => date('Y-m-d H:i:s'),
            'transfer_affected_by' => $transfer_id
        ]);
        
        // Log audit
        if (function_exists('log_crm_audit')) {
            log_crm_audit('transfer', 'plot_transfer_completed', $purchase_id, [
                'old_plot_id' => (int)$helper->booking_id,
                'new_plot_id' => $new_plot_id,
                'rate_adjustment' => $rate_adjustment,
                'fee' => $fee
            ], isset($wo['user']['id']) ? $wo['user']['id'] : null);
        }
        
        $db->commit();
        
        echo json_encode([
            'status' => 200,
            'message' => 'Plot transfer completed successfully',
            'transfer_id' => $transfer_id,
            'rate_adjustment' => $rate_adjustment
        ]);
        
    } catch (Exception $e) {
        if (method_exists($db, 'rollback')) $db->rollback();
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==================================================
// PROCESS CANCEL PLOT
// ==================================================
if ($s === 'process_cancel_plot') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $cancel_type = isset($_POST['cancel_type']) ? trim($_POST['cancel_type']) : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $fee = isset($_POST['fee']) ? (float)$_POST['fee'] : 0;
    $initiate_refund = isset($_POST['initiate_refund']) ? (int)$_POST['initiate_refund'] : 0;
    $cancel_date = isset($_POST['cancel_date']) ? trim($_POST['cancel_date']) : date('Y-m-d');
    
    if ($purchase_id <= 0 || empty($cancel_type)) {
        echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        $db->startTransaction();
        
        // Create transfer record (for audit trail)
        $transfer_data = [
            'purchase_id' => $purchase_id,
            'client_id' => (int)$helper->client_id,
            'transfer_type' => 'cancel_plot',
            'from_client_id' => (int)$helper->client_id,
            'to_client_id' => (int)$helper->client_id,
            'transfer_date' => $cancel_date,
            'approval_status' => 1,
            'approval_date' => date('Y-m-d'),
            'transfer_fee_mode' => $fee > 0 ? 'fixed' : 'free',
            'transfer_fee_value' => $fee,
            'transfer_fee_calculated' => $fee,
            'transfer_fee_status' => 'collected',
            'remarks' => "Cancel type: {$cancel_type}. {$remarks}",
            'status' => 1,
            'created_by' => isset($wo['user']['id']) ? (int)$wo['user']['id'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $transfer_id = $db->insert('crm_transfer_history', $transfer_data);
        
        if (!$transfer_id) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Failed to create cancellation record']);
            exit;
        }
        
        // Update helper status to cancelled
        $helper_update = $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
            'status' => '0', // cancelled
            'cancel_date' => strtotime($cancel_date),
            'updated_at' => time()
        ]);
        
        if (!$helper_update) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Failed to update purchase']);
            exit;
        }
        
        // Update plot to available
        $plot = $db->where('id', (int)$helper->booking_id)->getOne(T_BOOKING);
        if ($plot) {
            $db->where('id', (int)$helper->booking_id)->update(T_BOOKING, ['status' => 1]);
        }
        
        $response_data = [
            'status' => 200,
            'message' => 'Plot cancelled successfully',
            'transfer_id' => $transfer_id
        ];
        
        // Initiate refund if requested
        if ($initiate_refund == 1) {
            $calc = calculate_refund_v2($purchase_id, 10.00);
            if ($calc['status'] === 200) {
                $response_data['refund_initiated'] = true;
                $response_data['refund_calculation'] = $calc;
            }
        }
        
        // Log audit
        if (function_exists('log_crm_audit')) {
            log_crm_audit('transfer', 'cancel_plot_completed', $purchase_id, [
                'cancel_type' => $cancel_type,
                'fee' => $fee,
                'initiate_refund' => $initiate_refund
            ], isset($wo['user']['id']) ? $wo['user']['id'] : null);
        }
        
        $db->commit();
        echo json_encode($response_data);
        
    } catch (Exception $e) {
        if (method_exists($db, 'rollback')) $db->rollback();
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==================================================
// GET REFUND DETAILS
// ==================================================
if ($s === 'get_refund_details') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : (isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0);
    
    if (!$purchase_id) {
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
        exit;
    }
    
    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        // Calculate total paid
        $total_paid = 0;
        $payments = $db->where('purchase_id', $purchase_id)->where('status', 99, '!=')->get('crm_payment_schedule');
        if ($payments) {
            foreach ($payments as $p) {
                $total_paid += (float)$p->paid_amount;
            }
        }
        $total_paid += (float)($helper->booking_money ?? 0);
        $total_paid += (float)($helper->down_payment ?? 0);
        
        // Get existing refund transactions
        $transactions = $db->where('purchase_id', $purchase_id)->where('status', 99, '!=')->orderBy('transaction_date', 'ASC')->get('crm_refund_transactions');
        
        $tx_array = [];
        if ($transactions) {
            foreach ($transactions as $tx) {
                $tx_array[] = [
                    'id' => $tx->id,
                    'transaction_date' => $tx->transaction_date,
                    'transaction_amount' => (float)$tx->transaction_amount,
                    'payment_method' => $tx->payment_method,
                    'money_receipt_no' => $tx->money_receipt_no,
                    'remarks' => $tx->remarks,
                    'status' => (int)$tx->status
                ];
            }
        }
        
        echo json_encode([
            'status' => 200,
            'purchase_id' => $purchase_id,
            'total_paid' => $total_paid,
            'deduction_percentage' => 10.00,
            'transactions' => $tx_array
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==================================================
// INITIATE REFUND
// ==================================================
if ($s === 'initiate_refund') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $deduction_pct = isset($_POST['deduction_percentage']) ? (float)$_POST['deduction_percentage'] : 10.00;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    if ($purchase_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
        exit;
    }
    
    if ($deduction_pct < 5 || $deduction_pct > 25) {
        echo json_encode(['status' => 400, 'message' => 'Deduction percentage must be between 5-25%']);
        exit;
    }
    
    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        // Calculate refund
        $calc = calculate_refund_v2($purchase_id, $deduction_pct);
        if ($calc['status'] !== 200) {
            echo json_encode($calc);
            exit;
        }
        
        // Log audit
        if (function_exists('log_crm_audit')) {
            log_crm_audit('refund', 'refund_initiated', $purchase_id, [
                'deduction_percentage' => $deduction_pct,
                'refundable_amount' => $calc['refundable_amount'],
                'reason' => $remarks
            ], isset($wo['user']['id']) ? $wo['user']['id'] : null);
        }
        
        echo json_encode([
            'status' => 200,
            'message' => 'Refund calculation ready',
            'calculation' => $calc
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ==================================================
// ADD REFUND TRANSACTION
// ==================================================
if ($s === 'add_refund_transaction') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $transaction_date = isset($_POST['transaction_date']) ? trim($_POST['transaction_date']) : date('Y-m-d');
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Bank Transfer';
    $receipt_no = isset($_POST['receipt_no']) ? trim($_POST['receipt_no']) : null;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
    
    if ($purchase_id <= 0 || $amount <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        // Use the function from crm_refund_functions.php
        $result = add_refund_transaction($purchase_id, $amount, $transaction_date, 10.00, $payment_method, $receipt_no, $remarks, isset($wo['user']['id']) ? $wo['user']['id'] : null);
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
