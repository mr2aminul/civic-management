<?php
/**
 * Payment Schedule Recalculation Functions
 * 
 * Handles recalculation of payment schedules when:
 * - Plot is changed
 * - Per katha rate is adjusted
 * - Booking status changes
 */

// ==================================================
// RECALCULATE SCHEDULE - PLOT CHANGE
// ==================================================

/**
 * Recalculate payment schedule after plot transfer
 * 
 * @param int $purchase_id The booking helper ID
 * @param float $new_per_katha New per katha rate
 * @param float $new_katha New katha size
 * @param int $created_by User ID
 * @return array Result with status and new schedule
 */
function recalculate_schedule_on_plot_change($purchase_id, $new_per_katha, $new_katha, $created_by = null) {
    global $db;
    
    if (!$purchase_id || !is_numeric($new_per_katha) || !is_numeric($new_katha)) {
        return ['status' => 400, 'message' => 'Invalid parameters'];
    }
    
    try {
        $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            return ['status' => 404, 'message' => 'Purchase not found'];
        }
        
        $booking = $db->where('id', (int)$helper->booking_id)->getOne(T_BOOKING);
        if (!$booking) {
            return ['status' => 404, 'message' => 'Booking not found'];
        }
        
        // Get existing schedule to preserve structure
        $existing_schedule = $db->where('purchase_id', $purchase_id)
                                ->where('status', 99, '!=')
                                ->where('type', 'installment')
                                ->orderBy('due_date', 'ASC')
                                ->get('crm_payment_schedule');
        
        // Calculate new totals
        $new_per_katha = (float)$new_per_katha;
        $new_katha = (float)$new_katha;
        $new_total = $new_per_katha * $new_katha;
        
        // Booking and down payment amounts (preserved)
        $booking_money = (float)($helper->booking_money ?? 0);
        $down_payment = (float)($helper->down_payment ?? 0);
        
        // New remaining for installments
        $remaining = $new_total - $booking_money - $down_payment;
        
        if (empty($existing_schedule)) {
            // No existing schedule, cannot recalculate
            return ['status' => 400, 'message' => 'No existing schedule to recalculate'];
        }
        
        // Preserve booking and down payment rows
        $booking_row = null;
        $down_row = null;
        $installment_rows = [];
        
        foreach ($existing_schedule as $row) {
            $type = strtolower(trim($row->type));
            if ($type === 'booking' || (strpos(strtolower($row->particular), 'booking') !== false && strpos(strtolower($row->particular), 'down') === false)) {
                $booking_row = $row;
            } elseif ($type === 'down' || strpos(strtolower($row->particular), 'down') !== false) {
                $down_row = $row;
            } else {
                $installment_rows[] = $row;
            }
        }
        
        // Calculate per-installment amount (distribute evenly)
        $num_installments = count($installment_rows);
        if ($num_installments === 0) {
            return ['status' => 400, 'message' => 'No installments to recalculate'];
        }
        
        $per_installment = round($remaining / $num_installments, 2);
        $last_installment = $remaining - ($per_installment * ($num_installments - 1));
        
        // Prepare recalculated schedule
        $recalculated = [];
        
        // Add booking row (unchanged amount, just update)
        if ($booking_row) {
            $recalculated[] = [
                'particular' => $booking_row->particular,
                'type' => 'booking',
                'due_date' => $booking_row->due_date,
                'installment_amount' => (float)$booking_row->installment_amount,
                'paid_amount' => (float)$booking_row->paid_amount,
                'payment_date' => $booking_row->payment_date,
                'payment_method' => $booking_row->payment_method,
                'money_receipt_no' => $booking_row->money_receipt_no,
                'remarks' => $booking_row->remarks,
                'status' => (int)$booking_row->status
            ];
        }
        
        // Add down payment row (unchanged amount, just update)
        if ($down_row) {
            $recalculated[] = [
                'particular' => $down_row->particular,
                'type' => 'down',
                'due_date' => $down_row->due_date,
                'installment_amount' => (float)$down_row->installment_amount,
                'paid_amount' => (float)$down_row->paid_amount,
                'payment_date' => $down_row->payment_date,
                'payment_method' => $down_row->payment_method,
                'money_receipt_no' => $down_row->money_receipt_no,
                'remarks' => $down_row->remarks,
                'status' => (int)$down_row->status
            ];
        }
        
        // Add recalculated installments
        $start_date = $helper->installment_start_date ?? date('Y-m-d');
        foreach ($installment_rows as $idx => $row) {
            $installment_num = (int)($row->installment_number ?? ($idx + 1));
            $amount = ($idx === count($installment_rows) - 1) ? $last_installment : $per_installment;
            
            $due_date = date('Y-m-d', strtotime($start_date . " +{$installment_num} month"));
            
            $recalculated[] = [
                'installment_number' => $installment_num,
                'particular' => $row->particular,
                'type' => 'installment',
                'due_date' => $due_date,
                'installment_amount' => $amount,
                'paid_amount' => 0,
                'payment_date' => null,
                'payment_method' => null,
                'money_receipt_no' => null,
                'remarks' => 'Recalculated due to plot transfer. Old amount: ' . number_format((float)$row->installment_amount, 2),
                'status' => 0
            ];
        }
        
        // Save recalculated schedule
        $save_result = save_payment_schedule($purchase_id, $recalculated, $created_by);
        
        if ($save_result['status'] === 200) {
            return [
                'status' => 200,
                'message' => 'Schedule recalculated successfully',
                'new_total' => $new_total,
                'new_per_katha' => $new_per_katha,
                'new_katha' => $new_katha,
                'per_installment' => $per_installment,
                'schedule' => $recalculated
            ];
        } else {
            return $save_result;
        }
        
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==================================================
// RECALCULATE SCHEDULE - RATE CHANGE
// ==================================================

/**
 * Recalculate schedule when per katha rate changes
 * 
 * @param int $purchase_id The booking helper ID
 * @param float $new_per_katha New per katha rate
 * @param int $created_by User ID
 * @return array Result with status
 */
function recalculate_schedule_on_rate_change($purchase_id, $new_per_katha, $created_by = null) {
    global $db;
    
    if (!$purchase_id || !is_numeric($new_per_katha)) {
        return ['status' => 400, 'message' => 'Invalid parameters'];
    }
    
    try {
        $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            return ['status' => 404, 'message' => 'Purchase not found'];
        }
        
        $booking = $db->where('id', (int)$helper->booking_id)->getOne(T_BOOKING);
        if (!$booking) {
            return ['status' => 404, 'message' => 'Booking not found'];
        }
        
        $katha = (float)($booking->katha ?? 0);
        
        // Use existing plot's katha, just update rate
        return recalculate_schedule_on_plot_change($purchase_id, $new_per_katha, $katha, $created_by);
        
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==================================================
// SAVE PAYMENT SCHEDULE
// ==================================================

/**
 * Save recalculated payment schedule to database
 * 
 * @param int $purchase_id The booking helper ID
 * @param array $schedule Array of schedule rows
 * @param int $created_by User ID
 * @return array Result with status
 */
function save_payment_schedule($purchase_id, $schedule, $created_by = null) {
    global $db;
    
    if (!is_array($schedule) || empty($schedule)) {
        return ['status' => 400, 'message' => 'No schedule data to save'];
    }
    
    try {
        $db->startTransaction();
        
        // Archive old schedule
        $db->where('purchase_id', $purchase_id)->update('crm_payment_schedule', [
            'status' => 99,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $created_by
        ]);
        
        // Insert new schedule rows
        $inserted = 0;
        foreach ($schedule as $row) {
            $insert_data = [
                'purchase_id' => $purchase_id,
                'client_id' => isset($row['client_id']) ? $row['client_id'] : null,
                'installment_number' => isset($row['installment_number']) ? (int)$row['installment_number'] : 0,
                'particular' => $row['particular'] ?? '',
                'type' => $row['type'] ?? 'installment',
                'due_date' => $row['due_date'] ?? date('Y-m-d'),
                'installment_amount' => (float)($row['installment_amount'] ?? 0),
                'paid_amount' => (float)($row['paid_amount'] ?? 0),
                'payment_date' => $row['payment_date'] ?? null,
                'payment_method' => $row['payment_method'] ?? null,
                'money_receipt_no' => $row['money_receipt_no'] ?? null,
                'remarks' => $row['remarks'] ?? null,
                'status' => (int)($row['status'] ?? 0),
                'is_adjustment' => 0,
                'recalculated_due_to' => 'plot_change',
                'recalculation_date' => date('Y-m-d H:i:s'),
                'created_by' => $created_by,
                'updated_by' => $created_by,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($db->insert('crm_payment_schedule', $insert_data)) {
                $inserted++;
            }
        }
        
        $db->commit();
        
        if (function_exists('log_crm_audit')) {
            log_crm_audit('payment_schedule', 'recalculated', $purchase_id, [
                'rows_inserted' => $inserted,
                'total_rows' => count($schedule)
            ], $created_by);
        }
        
        return [
            'status' => 200,
            'message' => "Saved {$inserted} schedule rows",
            'inserted' => $inserted
        ];
        
    } catch (Exception $e) {
        if (method_exists($db, 'rollback')) $db->rollback();
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==================================================
// GET SCHEDULE DIFF PREVIEW
// ==================================================

/**
 * Preview how schedule will change before applying
 * 
 * @param int $purchase_id The booking helper ID
 * @param float $new_per_katha New per katha rate (optional)
 * @param float $new_katha New katha size (optional)
 * @return array Diff showing old vs new schedule
 */
function get_schedule_diff_preview($purchase_id, $new_per_katha = null, $new_katha = null) {
    global $db;
    
    try {
        $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            return ['status' => 404, 'message' => 'Purchase not found'];
        }
        
        $booking = $db->where('id', (int)$helper->booking_id)->getOne(T_BOOKING);
        if (!$booking) {
            return ['status' => 404, 'message' => 'Booking not found'];
        }
        
        $old_per_katha = (float)($helper->per_katha ?? 0);
        $old_katha = (float)($booking->katha ?? 0);
        $new_per_katha = $new_per_katha !== null ? (float)$new_per_katha : $old_per_katha;
        $new_katha = $new_katha !== null ? (float)$new_katha : $old_katha;
        
        $old_total = $old_per_katha * $old_katha;
        $new_total = $new_per_katha * $new_katha;
        $adjustment = $new_total - $old_total;
        
        // Get current schedule
        $current_schedule = $db->where('purchase_id', $purchase_id)
                               ->where('status', 99, '!=')
                               ->orderBy('due_date', 'ASC')
                               ->get('crm_payment_schedule');
        
        $booking_money = (float)($helper->booking_money ?? 0);
        $down_payment = (float)($helper->down_payment ?? 0);
        $current_remaining = $old_total - $booking_money - $down_payment;
        $new_remaining = $new_total - $booking_money - $down_payment;
        
        $impact = $new_remaining - $current_remaining;
        
        return [
            'status' => 200,
            'old_per_katha' => $old_per_katha,
            'new_per_katha' => $new_per_katha,
            'old_katha' => $old_katha,
            'new_katha' => $new_katha,
            'old_total' => $old_total,
            'new_total' => $new_total,
            'total_adjustment' => $adjustment,
            'old_remaining' => $current_remaining,
            'new_remaining' => $new_remaining,
            'installment_impact' => $impact,
            'action' => $adjustment > 0 ? 'EXTRA_CHARGE' : ($adjustment < 0 ? 'REFUND' : 'NO_CHANGE'),
            'current_schedule_count' => count($current_schedule ?: [])
        ];
        
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}
