<?php
/**
 * CRM Refund Functions
 * Manages refund processing with deductions and installment tracking
 *
 * Phase 2 - Step 2.3: Refund Functions
 */

// ===============================
//  CALCULATE REFUND
// ===============================

/**
 * Calculate refund amount with deduction
 *
 * @param int $purchase_id The booking helper ID
 * @param float $deduction_percentage Deduction percentage (5-25%)
 * @return array Result with refund calculation details
 */
if (!function_exists('log_crm_audit')) {
    function log_crm_audit($entity_type, $action, $record_id = null, $data = null, $user_id = null, $table_name = null) {
        // Keep this function lightweight and defensive.
        global $db, $wo;

        try {
            // Resolve db and user
            if (!isset($db)) return false;
            $user_id = $user_id ?? (isset($wo['user']['id']) ? (int)$wo['user']['id'] : null);

            $payload = [
                'entity_type' => (string)$entity_type,
                'action'      => (string)$action,
                'description' => null,
                'table_name'  => $table_name ? (string)$table_name : null,
                'record_id'   => $record_id === null ? null : (int)$record_id,
                'old_values'  => null,
                'new_values'  => null,
                'user_id'     => $user_id,
                'ip_address'  => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                'created_at'  => date('Y-m-d H:i:s')
            ];

            if (is_array($data) && count($data) > 0) {
                // Heuristic: put entire array into new_values. If caller wants old_values, pass structure: ['old'=>..., 'new'=>...]
                if (isset($data['old']) || isset($data['new'])) {
                    $payload['old_values'] = isset($data['old']) ? json_encode($data['old'], JSON_UNESCAPED_UNICODE) : null;
                    $payload['new_values'] = isset($data['new']) ? json_encode($data['new'], JSON_UNESCAPED_UNICODE) : null;
                    // optional description
                    if (isset($data['desc'])) $payload['description'] = (string)$data['desc'];
                } else {
                    $payload['new_values'] = json_encode($data, JSON_UNESCAPED_UNICODE);
                }
            } elseif (is_string($data) && $data !== '') {
                $payload['description'] = $data;
            }

            // Build insert array (match crm_audit_log columns)
            $insert = [
                'entity_type' => $payload['entity_type'],
                'action'      => $payload['action'],
                'description' => $payload['description'],
                'table_name'  => $payload['table_name'],
                'record_id'   => $payload['record_id'],
                'old_values'  => $payload['old_values'],
                'new_values'  => $payload['new_values'],
                'user_id'     => $payload['user_id'],
                'ip_address'  => $payload['ip_address'],
                // created_at column exists and has default CURRENT_TIMESTAMP; include explicit value for clarity
                'created_at'  => $payload['created_at']
            ];

            // Use your MysqliDb instance to insert
            $res = $db->insert('crm_audit_log', $insert);

            return $res ? (int)$res : false;
        } catch (Exception $ex) {
            // swallow errors so audit logging never fatals the request
            @error_log("log_crm_audit error: " . $ex->getMessage());
            return false;
        }
    }
}

function calculate_refund($purchase_id, $deduction_percentage = 10.00) {
    global $db;

    // Validation
    if (empty($purchase_id)) {
        return ['status' => 400, 'message' => 'Booking helper ID required'];
    }

    if ($deduction_percentage < 5 || $deduction_percentage > 25) {
        return ['status' => 400, 'message' => 'Deduction percentage must be between 5% and 25%'];
    }

    // Get booking helper
    $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
    if (!$helper) {
        return ['status' => 404, 'message' => 'Booking not found'];
    }

    // Calculate total paid from payment schedule
    $total_paid = 0;
    $payments = $db->where('purchase_id', (int)$purchase_id)
                   ->where('status', 1) // paid only
                   ->get('crm_payment_schedule');

    if ($payments) {
        foreach ($payments as $payment) {
            $total_paid += (float)$payment->paid_amount;
        }
    }

    // Add booking money and down payment
    $total_paid += (float)($helper->booking_money ?? 0);
    $total_paid += (float)($helper->down_payment ?? 0);

    // Calculate refund
    $deduction_amount = $total_paid * ($deduction_percentage / 100);
    $refundable_amount = $total_paid - $deduction_amount;

    return [
        'status' => 200,
        'total_paid' => $total_paid,
        'deduction_percentage' => $deduction_percentage,
        'deduction_amount' => $deduction_amount,
        'refundable_amount' => $refundable_amount,
        'purchase_id' => $purchase_id,
        'client_id' => $helper->client_id
    ];
}

// ===============================
//  CREATE REFUND SCHEDULE
// ===============================

/**
 * Create refund schedule with installments
 *
 * @param int $purchase_id The booking helper ID
 * @param float $deduction_percentage Deduction percentage (5-25%)
 * @param int $num_installments Number of refund installments
 * @param string $start_date Start date for refunds (Y-m-d)
 * @param int $created_by User ID who created
 * @return array Result with status and message
 */
function create_refund_schedule($purchase_id, $deduction_percentage, $num_installments = 1, $start_date = null, $created_by = null) {
    global $db;

    // Validation
    if (empty($purchase_id)) {
        return ['status' => 400, 'message' => 'Booking helper ID required'];
    }

    if ($num_installments < 1) {
        return ['status' => 400, 'message' => 'Number of installments must be at least 1'];
    }

    // Calculate refund
    $calc = calculate_refund($purchase_id, $deduction_percentage);
    if ($calc['status'] !== 200) {
        return $calc;
    }

    // Check for existing refund schedule
    $existing = $db->where('purchase_id', (int)$purchase_id)
                   ->where('status', [0, 1, 2], 'IN') // pending, paid, partial
                   ->getOne('crm_refund_schedule');

    if ($existing) {
        return ['status' => 400, 'message' => 'Refund schedule already exists for this booking'];
    }

    if (!$start_date) {
        $start_date = date('Y-m-d');
    }

    // Calculate installment amount
    $installment_amount = round($calc['refundable_amount'] / $num_installments, 2);
    $last_installment = $calc['refundable_amount'] - ($installment_amount * ($num_installments - 1));

    try {
        $db->startTransaction();

        $inserted_count = 0;

        for ($i = 1; $i <= $num_installments; $i++) {
            $amount = ($i == $num_installments) ? $last_installment : $installment_amount;
            $due_date = date('Y-m-d', strtotime($start_date . " +{$i} month"));

            $data = [
                'purchase_id' => (int)$purchase_id,
                'client_id' => (int)$calc['client_id'],
                'refund_initiation_date' => date('Y-m-d'),
                'total_paid_amount' => $calc['total_paid'],
                'deduction_percentage' => $calc['deduction_percentage'],
                'deduction_amount' => $calc['deduction_amount'],
                'refundable_amount' => $calc['refundable_amount'],
                'installment_number' => $i,
                'installment_amount' => $amount,
                'due_date' => $due_date,
                'paid_amount' => 0.00,
                'status' => 0, // pending
                'created_by' => $created_by ? (int)$created_by : null
            ];

            $result = $db->insert('crm_refund_schedule', $data);

            if ($result) {
                $inserted_count++;
            }
        }

        $db->commit();

        return [
            'status' => 200,
            'message' => "Created refund schedule with {$inserted_count} installments",
            'count' => $inserted_count,
            'refundable_amount' => $calc['refundable_amount'],
            'deduction_amount' => $calc['deduction_amount']
        ];

    } catch (Exception $e) {
        $db->rollback();
        return ['status' => 500, 'message' => 'Failed to create refund schedule: ' . $e->getMessage()];
    }
}

// ===============================
//  GET REFUND SCHEDULE
// ===============================

/**
 * Get refund schedule for a booking
 *
 * @param int $purchase_id The booking helper ID
 * @param array $filters Optional filters (status)
 * @return array Refund schedule entries
 */
function get_refund_schedule($purchase_id, $filters = []) {
    global $db;

    if (empty($purchase_id)) {
        return [];
    }

    $db->where('purchase_id', (int)$purchase_id);

    if (isset($filters['status']) && $filters['status'] !== '') {
        $db->where('status', (int)$filters['status']);
    }

    $db->orderBy('installment_number', 'ASC');

    return $db->get('crm_refund_schedule') ?: [];
}

/**
 * Get refund schedule summary
 *
 * @param int $purchase_id The booking helper ID
 * @return array|null Summary data
 */
function get_refund_schedule_summary($purchase_id) {
    global $db;

    if (empty($purchase_id)) {
        return null;
    }

    $schedule = get_refund_schedule($purchase_id);

    if (empty($schedule)) {
        return null;
    }

    $first = $schedule[0];
    $total_refundable = (float)$first->refundable_amount;
    $total_refunded = 0;
    $pending_count = 0;

    foreach ($schedule as $entry) {
        $total_refunded += (float)$entry->paid_amount;
        if ($entry->status == 0) {
            $pending_count++;
        }
    }

    return [
        'total_paid_amount' => (float)$first->total_paid_amount,
        'deduction_percentage' => (float)$first->deduction_percentage,
        'deduction_amount' => (float)$first->deduction_amount,
        'refundable_amount' => $total_refundable,
        'total_refunded' => $total_refunded,
        'remaining' => $total_refundable - $total_refunded,
        'pending_count' => $pending_count,
        'total_installments' => count($schedule),
        'refund_initiation_date' => $first->refund_initiation_date
    ];
}

// ===============================
//  UPDATE REFUND PAYMENT
// ===============================

/**
 * Record a refund payment
 *
 * @param int $refund_schedule_id The refund schedule entry ID
 * @param float $amount Amount refunded
 * @param string $payment_date Payment date (Y-m-d)
 * @param string $payment_method Payment method
 * @param string $receipt_no Receipt number
 * @param string $remarks Additional remarks
 * @param int $updated_by User ID
 * @return array Result with status and message
 */
function add_refund_payment($refund_schedule_id, $amount, $payment_date = null, $payment_method = null, $receipt_no = null, $remarks = null, $updated_by = null) {
    global $db;

    if (empty($refund_schedule_id)) {
        return ['status' => 400, 'message' => 'Refund schedule ID required'];
    }

    if (!is_numeric($amount) || $amount <= 0) {
        return ['status' => 400, 'message' => 'Invalid refund amount'];
    }

    // Get refund schedule entry
    $entry = $db->where('id', (int)$refund_schedule_id)->getOne('crm_refund_schedule');
    if (!$entry) {
        return ['status' => 404, 'message' => 'Refund schedule entry not found'];
    }

    // Calculate new paid amount
    $current_paid = (float)$entry->paid_amount;
    $installment_amount = (float)$entry->installment_amount;
    $new_paid = $current_paid + (float)$amount;

    // Determine status
    $status = 0; // pending
    if ($new_paid >= $installment_amount) {
        $status = 1; // paid
        $new_paid = $installment_amount; // cap at installment amount
    } elseif ($new_paid > 0) {
        $status = 2; // partial
    }

    // Prepare update data
    $update_data = [
        'paid_amount' => $new_paid,
        'status' => $status
    ];

    if ($payment_date) {
        $update_data['payment_date'] = $payment_date;
    }

    if ($payment_method) {
        $update_data['payment_method'] = $payment_method;
    }

    if ($receipt_no) {
        $update_data['money_receipt_no'] = $receipt_no;
    }

    if ($remarks) {
        $update_data['remarks'] = $remarks;
    }

    if ($updated_by) {
        $update_data['updated_by'] = (int)$updated_by;
    }

    try {
        $result = $db->where('id', (int)$refund_schedule_id)
                     ->update('crm_refund_schedule', $update_data);

        if ($result) {
            return [
                'status' => 200,
                'message' => 'Refund payment recorded successfully',
                'new_paid_amount' => $new_paid,
                'payment_status' => $status
            ];
        } else {
            return ['status' => 500, 'message' => 'Failed to record refund payment'];
        }
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

// ===============================
//  CANCEL REFUND
// ===============================

/**
 * Cancel a refund schedule
 *
 * @param int $purchase_id The booking helper ID
 * @param string $reason Cancellation reason
 * @param int $updated_by User ID
 * @return array Result with status and message
 */
function cancel_refund_schedule($purchase_id, $reason = null, $updated_by = null) {
    global $db;

    if (empty($purchase_id)) {
        return ['status' => 400, 'message' => 'Booking helper ID required'];
    }

    // Get pending refund entries
    $entries = $db->where('purchase_id', (int)$purchase_id)
                  ->where('status', [0, 2], 'IN') // pending or partial
                  ->get('crm_refund_schedule');

    if (empty($entries)) {
        return ['status' => 404, 'message' => 'No pending refund entries found'];
    }

    try {
        $db->startTransaction();

        $cancelled_count = 0;

        foreach ($entries as $entry) {
            $update_data = [
                'status' => 3, // cancelled
                'remarks' => $reason
            ];

            if ($updated_by) {
                $update_data['updated_by'] = (int)$updated_by;
            }

            $result = $db->where('id', (int)$entry->id)
                         ->update('crm_refund_schedule', $update_data);

            if ($result) {
                $cancelled_count++;
            }
        }

        $db->commit();

        return [
            'status' => 200,
            'message' => "Cancelled {$cancelled_count} refund installments",
            'count' => $cancelled_count
        ];

    } catch (Exception $e) {
        $db->rollback();
        return ['status' => 500, 'message' => 'Failed to cancel refund: ' . $e->getMessage()];
    }
}

// ===============================
//  GET PENDING REFUNDS
// ===============================

/**
 * Get all pending refunds (admin view)
 *
 * @param array $filters Optional filters
 * @return array Pending refund entries
 */
function get_pending_refunds($filters = []) {
    global $db;

    $db->where('status', 0); // pending only

    if (isset($filters['client_id']) && $filters['client_id'] !== '') {
        $db->where('client_id', (int)$filters['client_id']);
    }

    if (isset($filters['due_before']) && $filters['due_before'] !== '') {
        $db->where('due_date', $filters['due_before'], '<=');
    }

    $db->orderBy('due_date', 'ASC');

    return $db->get('crm_refund_schedule') ?: [];
}

/**
 * Get overdue refunds
 *
 * @return array Overdue refund entries
 */
function get_overdue_refunds() {
    global $db;

    $today = date('Y-m-d');

    $db->where('status', [0, 2], 'IN'); // pending or partial
    $db->where('due_date', $today, '<');
    $db->orderBy('due_date', 'ASC');

    return $db->get('crm_refund_schedule') ?: [];
}

/**
 * Calculate refund amount with deduction
 * @param int $purchase_id The booking helper ID
 * @param float $deduction_percentage Deduction percentage (5-25%)
 * @return array Result with refund calculation details
 */
function calculate_refund_v2($purchase_id, $deduction_percentage = 10.00) {
    global $db;

    if (empty($purchase_id)) {
        return ['status' => 400, 'message' => 'Booking helper ID required'];
    }

    if ($deduction_percentage < 5 || $deduction_percentage > 25) {
        return ['status' => 400, 'message' => 'Deduction percentage must be between 5% and 25%'];
    }

    $helper = $db->where('id', (int)$purchase_id)->getOne('wo_booking_helper');
    if (!$helper) {
        return ['status' => 404, 'message' => 'Booking not found'];
    }

    // Calculate total paid from payment schedule
    $total_paid = 0;
    $payments = $db->where('purchase_id', (int)$purchase_id)
                   ->where('status', 99, '!=')
                   ->get('crm_payment_schedule');

    if ($payments) {
        foreach ($payments as $payment) {
            $total_paid += (float)$payment->paid_amount;
        }
    }

    // Add booking money and down payment
    $total_paid += (float)($helper->booking_money ?? 0);
    $total_paid += (float)($helper->down_payment ?? 0);

    // Calculate refund
    $deduction_amount = $total_paid * ($deduction_percentage / 100);
    $refundable_amount = $total_paid - $deduction_amount;

    return [
        'status' => 200,
        'total_paid' => $total_paid,
        'deduction_percentage' => $deduction_percentage,
        'deduction_amount' => $deduction_amount,
        'refundable_amount' => $refundable_amount,
        'purchase_id' => $purchase_id,
        'client_id' => $helper->client_id
    ];
}

// ===============================
// INITIATE REFUND
// ===============================

/**
 * Initiate refund request for a booking
 * @param int $purchase_id The booking helper ID
 * @param float $deduction_percentage Deduction percentage (5-25%)
 * @param int $created_by User ID
 * @return array Result with status and message
 */
function initiate_refund_v2($purchase_id, $deduction_percentage = 10.00, $created_by = null) {
    global $db;

    if (empty($purchase_id)) {
        return ['status' => 400, 'message' => 'Booking helper ID required'];
    }

    // Calculate refund
    $calc = calculate_refund_v2($purchase_id, $deduction_percentage);
    if ($calc['status'] !== 200) {
        return $calc;
    }

    // Check if refund already exists
    $existing = $db->where('purchase_id', (int)$purchase_id)
                   ->where('status', [0, 1], 'IN')
                   ->getOne('crm_refund_transactions');

    if ($existing) {
        return ['status' => 400, 'message' => 'Refund already initiated for this booking'];
    }

    // Log audit
    log_crm_audit('refund', 'refund_initiated', $purchase_id,
        ['deduction_percentage' => $deduction_percentage, 'refundable_amount' => $calc['refundable_amount']],
        $created_by
    );

    return [
        'status' => 200,
        'message' => 'Refund calculation ready. Add refund transactions below.',
        'calculation' => $calc
    ];
}

// ===============================
// ADD REFUND TRANSACTION
// ===============================

/**
 * Add a single refund transaction (flexible custom amount and date)
 * @param int $purchase_id The booking helper ID
 * @param float $transaction_amount Custom refund amount for this transaction
 * @param string $transaction_date Date of transaction (Y-m-d)
 * @param float $deduction_percentage Initial deduction percentage
 * @param string $payment_method Payment method
 * @param string $receipt_no Receipt/reference number
 * @param string $remarks Transaction notes
 * @param int $created_by User ID
 * @return array Result with status and message
 */
function add_refund_transaction($purchase_id, $transaction_amount, $transaction_date = null, $deduction_percentage = 10.00, $payment_method = 'Bank Transfer', $receipt_no = null, $remarks = null, $created_by = null) {
    global $db;

    if (empty($purchase_id) || !is_numeric($transaction_amount) || $transaction_amount <= 0) {
        return ['status' => 400, 'message' => 'purchase_id and valid transaction_amount required'];
    }

    if (!$transaction_date) {
        $transaction_date = date('Y-m-d');
    }

    $helper = $db->where('id', (int)$purchase_id)->getOne('wo_booking_helper');
    if (!$helper) {
        return ['status' => 404, 'message' => 'Booking not found'];
    }

    // Calculate refund info
    $calc = calculate_refund_v2($purchase_id, $deduction_percentage);
    if ($calc['status'] !== 200) {
        return $calc;
    }

    // Check total refunded so far
    $already_refunded = $db->where('purchase_id', (int)$purchase_id)->where('status', 1)->get('crm_refund_transactions', 'sum(transaction_amount)') ?: 0;

    $can_refund = (float)$calc['refundable_amount'] - (float)$already_refunded;

    if ($transaction_amount > $can_refund) {
        return [
            'status' => 400,
            'message' => "Transaction amount (৳{$transaction_amount}) exceeds remaining refundable amount (৳{$can_refund})",
            'can_refund' => $can_refund,
            'already_refunded' => $already_refunded,
            'refundable_amount' => $calc['refundable_amount']
        ];
    }

    try {
        $data = [
            'purchase_id' => (int)$purchase_id,
            'client_id' => (int)$calc['client_id'],
            'refund_request_date' => date('Y-m-d'),
            'total_paid_amount' => $calc['total_paid'],
            'deduction_percentage' => $deduction_percentage,
            'deduction_amount' => $calc['deduction_amount'],
            'refundable_amount' => $calc['refundable_amount'],
            'transaction_date' => $transaction_date,
            'transaction_amount' => (float)$transaction_amount,
            'payment_method' => $payment_method,
            'money_receipt_no' => $receipt_no ? trim($receipt_no) : null,
            'remarks' => $remarks ? trim($remarks) : null,
            'status' => 1, // completed
            'created_by' => $created_by ? (int)$created_by : null
        ];

        $result = $db->insert('crm_refund_transactions', $data);

        if ($result) {
            // Log audit
            log_crm_audit('refund', 'refund_transaction_added', $purchase_id, ['transaction_amount' => $transaction_amount, 'transaction_date' => $transaction_date, 'payment_method' => $payment_method], $created_by);

            return [
                'status' => 200,
                'message' => "Refund transaction of ৳{$transaction_amount} recorded successfully",
                'transaction_id' => $result,
                'remaining_refund' => $can_refund - $transaction_amount
            ];
        } else {
            return ['status' => 500, 'message' => 'Failed to record refund transaction'];
        }
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ===============================
// GET REFUND SUMMARY
// ===============================

/**
 * Get refund summary for a purchase
 * @param int $purchase_id The booking helper ID
 * @return array|null Refund summary with all transactions
 */
function get_refund_summary_v2($purchase_id) {
    global $db;

    if (empty($purchase_id)) {
        return null;
    }

    // Get all refund transactions
    $transactions = $db->where('purchase_id', (int)$purchase_id)
                       ->where('status', 99, '!=')
                       ->orderBy('transaction_date', 'ASC')
                       ->get('crm_refund_transactions') ?: [];

    if (empty($transactions)) {
        return null;
    }

    $first = $transactions[0];
    $total_refunded = 0;
    $pending_count = 0;

    foreach ($transactions as $trans) {
        if ($trans->status == 1) {
            $total_refunded += (float)$trans->transaction_amount;
        } elseif ($trans->status == 0) {
            $pending_count++;
        }
    }

    return [
        'total_paid_amount' => (float)$first->total_paid_amount,
        'deduction_percentage' => (float)$first->deduction_percentage,
        'deduction_amount' => (float)$first->deduction_amount,
        'refundable_amount' => (float)$first->refundable_amount,
        'total_refunded' => $total_refunded,
        'remaining' => (float)$first->refundable_amount - $total_refunded,
        'transaction_count' => count($transactions),
        'pending_count' => $pending_count,
        'refund_initiation_date' => $first->refund_request_date
    ];
}

// ===============================
// GET REFUND TRANSACTIONS
// ===============================

/**
 * Get all refund transactions for a purchase
 * @param int $purchase_id The booking helper ID
 * @return array Refund transactions
 */
function get_refund_transactions($purchase_id) {
    global $db;

    if (empty($purchase_id)) {
        return [];
    }

    return $db->where('purchase_id', (int)$purchase_id)
              ->where('status', 99, '!=')
              ->orderBy('transaction_date', 'ASC')
              ->get('crm_refund_transactions') ?: [];
}

// ===============================
// CANCEL REFUND TRANSACTION
// ===============================

/**
 * Cancel a refund transaction (archive, not delete)
 * @param int $transaction_id Transaction ID
 * @param string $reason Cancellation reason
 * @param int $updated_by User ID
 * @return array Result with status and message
 */
function cancel_refund_transaction($transaction_id, $reason = null, $updated_by = null) {
    global $db;

    if (empty($transaction_id)) {
        return ['status' => 400, 'message' => 'Transaction ID required'];
    }

    $transaction = $db->where('id', (int)$transaction_id)->getOne('crm_refund_transactions');
    if (!$transaction) {
        return ['status' => 404, 'message' => 'Transaction not found'];
    }

    try {
        $update_data = [
            'status' => 3, // cancelled
            'remarks' => $reason ? "CANCELLED: {$reason}" : 'CANCELLED',
            'updated_by' => $updated_by ? (int)$updated_by : null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = $db->where('id', (int)$transaction_id)
                     ->update('crm_refund_transactions', $update_data);

        if ($result) {
            log_crm_audit('refund', 'refund_transaction_cancelled', $transaction_id,
                ['reason' => $reason],
                $updated_by
            );

            return [
                'status' => 200,
                'message' => 'Refund transaction cancelled'
            ];
        } else {
            return ['status' => 500, 'message' => 'Failed to cancel transaction'];
        }
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ===============================
// GET PENDING REFUNDS (ADMIN)
// ===============================

/**
 * Get all pending refunds for admin dashboard
 * @param array $filters Optional filters
 * @return array Pending refund entries
 */
function get_pending_refunds_v2($filters = []) {
    global $db;

    $db->where('status', 0); // pending only

    if (isset($filters['client_id']) && $filters['client_id'] !== '') {
        $db->where('client_id', (int)$filters['client_id']);
    }

    $db->orderBy('transaction_date', 'ASC');

    return $db->get('crm_refund_transactions') ?: [];
}
