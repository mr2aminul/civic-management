<?php
/**
 * Reschedule Payment Endpoints
 * Three endpoints for payment schedule rescheduling
 */

// 1. GET RESCHHED ULE CONTEXT - Get current schedule information
if ($s === 'get_reschedule_context') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    
    if (!$purchase_id) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
        exit;
    }
    
    try {
        // Get payment schedule from crm_payment_schedule
        $db->where('purchase_id', $purchase_id);
        $db->where('status', '99', '!='); // Exclude deleted
        $db->orderBy('installment_number', 'ASC');
        $schedule = $db->get('crm_payment_schedule');
        
        $total_amount = 0;
        $total_paid = 0;
        $unpaid_rows = 0;
        $last_paid_date = null;
        $current_monthly = 0;
        
        foreach ($schedule as $item) {
            $amount = floatval($item->installment_amount);
            $paid = floatval($item->paid_amount);
            
            $total_amount += $amount;
            $total_paid += $paid;
            
            if ($item->status == 0) { // Unpaid
                $unpaid_rows++;
                if ($current_monthly == 0 && $item->installment_type == 'installment') {
                    $current_monthly = $amount;
                }
            }
            
            if (!empty($item->payment_date) && $item->status == 1) {
                if (!$last_paid_date || strtotime($item->payment_date) > strtotime($last_paid_date)) {
                    $last_paid_date = $item->payment_date;
                }
            }
        }
        
        echo json_encode([
            'status' => 200,
            'context' => [
                'total_amount' => $total_amount,
                'total_paid' => $total_paid,
                'remaining_balance' => $total_amount - $total_paid,
                'last_paid_date' => $last_paid_date ? date('d M Y', strtotime($last_paid_date)) : null,
                'current_monthly_amount' => $current_monthly,
                'unpaid_rows' => $unpaid_rows
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// 2. PREVIEW RESCHEDULE - Show what the new schedule would look like
if ($s === 'preview_reschedule') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    $monthly_amount = isset($_POST['monthly_amount']) ? floatval($_POST['monthly_amount']) : 0;
    $adjustment_mode = isset($_POST['adjustment_mode']) ? $_POST['adjustment_mode'] : 'last_installment';
    
    if (!$purchase_id || !$monthly_amount) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID and monthly amount required']);
        exit;
    }
    
    try {
        // Get current unpaid schedule
        $db->where('purchase_id', $purchase_id);
        $db->where('status', 0); // Unpaid only
        $db->where('status', '99', '!='); // Exclude deleted
        $db->orderBy('installment_number', 'ASC');
        $unpaid_schedule = $db->get('crm_payment_schedule');
        
        $remaining_balance = 0;
        $last_installment_number = 0;
        
        foreach ($unpaid_schedule as $item) {
            $amount = floatval($item->installment_amount);
            $paid = floatval($item->paid_amount);
            $remaining_balance += ($amount - $paid);
            $last_installment_number = max($last_installment_number, intval($item->installment_number));
        }
        
        // Calculate new schedule
        $new_installment_count = ceil($remaining_balance / $monthly_amount);
        $new_schedule = [];
        $running_total = 0;
        $first_new_number = $last_installment_number + 1;
        
        for ($i = 0; $i < $new_installment_count; $i++) {
            $installment_number = $first_new_number + $i;
            $is_last = ($i == $new_installment_count - 1);
            
            // Calculate amount (last one gets the remainder)
            if ($is_last) {
                $amount = $remaining_balance - $running_total;
            } else {
                $amount = $monthly_amount;
            }
            
            $running_total += $amount;
            
            // Generate ordinal suffix (1st, 2nd, 3rd, 4th...)
            $ordinal = getOrdinalSuffix($installment_number);
            
            $new_schedule[] = [
                'installment_number' => $installment_number,
                'particular' => $ordinal . ' Installment (R)',
                'type' => 'installment',
                'due_date' => date('Y-m-d', strtotime('+' . ($i + 1) . ' month')),
                'amount' => $amount,
                'is_new' => true
            ];
        }
        
        echo json_encode([
            'status' => 200,
            'preview' => [                'before' => [
                    'monthly_amount' => 0, // Will be calculated from first unpaid
                    'remaining_balance' => $remaining_balance,
                    'unpaid_rows' => count($unpaid_schedule)
                ],
                'after' => [
                    'monthly_amount' => $monthly_amount,
                    'remaining_balance' => 0,
                    'new_installment_count' => $new_installment_count,
                    'adjustment_mode' => $adjustment_mode,
                    'new_schedule' => $new_schedule
                ]
            ],
            'requires_approval' => false // Set to true if approval workflow is needed
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// 3. SUBMIT PAYMENT RESCHEDULE - Actually reschedule the payments
if ($s === 'submit_payment_reschedule') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    $monthly_amount = isset($_POST['monthly_amount']) ? floatval($_POST['monthly_amount']) : 0;
    $adjustment_mode = isset($_POST['adjustment_mode']) ? $_POST['adjustment_mode'] : 'last_installment';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    if (!$purchase_id || !$monthly_amount) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID and monthly amount required']);
        exit;
    }
    
    try {
        global $wo;
        
        // Mark old unpaid installments as deleted (status = 99)
        $db->where('purchase_id', $purchase_id);
        $db->where('status', 0); // Unpaid only
        $affected = $db->update('crm_payment_schedule', [
            'status' => 99, // Mark as deleted
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Get remaining balance and highest installment number
        $db->where('purchase_id', $purchase_id);
        $db->where('status', '99', '!=');
        $db->orderBy('installment_number', 'DESC');
        $all_schedule = $db->get('crm_payment_schedule', 1);
        
        $remaining_balance = 0;
        $last_installment_number = 0;
        
        // Calculate remaining from marked-deleted items
        $db->where('purchase_id', $purchase_id);
        $db->where('status', 99);
        $marked_deleted = $db->get('crm_payment_schedule');
        
        foreach ($marked_deleted as $item) {
            $remaining_balance += (floatval($item->installment_amount) - floatval($item->paid_amount));
        }
        
        if (!empty($all_schedule)) {
            $last_installment_number = intval($all_schedule[0]->installment_number);
        }
        
        // Create new installments
        $new_installment_count = ceil($remaining_balance / $monthly_amount);
        $running_total = 0;
        $first_new_number = $last_installment_number + 1;
        $inserted = 0;
        
        for ($i = 0; $i < $new_installment_count; $i++) {
            $installment_number = $first_new_number + $i;
            $is_last = ($i == $new_installment_count - 1);
            
            // Calculate amount
            if ($is_last) {
                $amount = $remaining_balance - $running_total;
            } else {
                $amount = $monthly_amount;
            }
            
            $running_total += $amount;
            
            // Generate ordinal (1st, 2nd, 3rd...)
            $ordinal = getOrdinalSuffix($installment_number);
            
            $result = $db->insert('crm_payment_schedule', [
                'purchase_id' => $purchase_id,
                'installment_number' => $installment_number,
                'particular' => $ordinal . ' Installment (R)',
                'installment_type' => 'installment',
                'due_date' => date('Y-m-d', strtotime('+' . ($i + 1) . ' month')),
                'installment_amount' => $amount,
                'paid_amount' => 0,
                'status' => 0, // Unpaid
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($result) $inserted++;
        }

        // Update parent helper with new configuration
        $db->where('id', $purchase_id)->update('wo_booking_helper', [
            'monthly_amount' => $monthly_amount,
            'installment_count' => $new_installment_count,
            'updated_at' => time()
        ]);
        
        // Audit trail
        if ($db->tableExists('crm_audit_trail')) {
            $db->insert('crm_audit_trail', [
                'user_id' => $wo['user_id'] ?? 0,
                'action' => 'payment_reschedule',
                'details' => json_encode([
                    'purchase_id' => $purchase_id,
                    'new_monthly' => $monthly_amount,
                    'new_count' => $new_installment_count,
                    'reason' => $reason
                ]),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo json_encode([
            'status' => 200,
            'message' => 'Payment schedule rescheduled successfully',
            'new_installments' => $inserted,
            'removed_installments' => $affected,
            'requires_approval' => false
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}