<?php
/**
 * CRM Payment Schedule Functions
 * Manages payment installment schedules for client purchases
 *
 * Phase 2 - Step 2.1: Payment Schedule Functions
 */



// ===============================
//  CREATE PAYMENT SCHEDULE
// ===============================

/**
 * Create payment schedule entries for a booking
 *
 * @param int $purchase_id The booking helper ID
 * @param int $client_id The client ID
 * @param array $installments Array of installment data
 * @param int $created_by User ID who created the schedule
 * @return array Result with status and message
 */
function create_payment_schedule($purchase_id, $client_id, $installments, $created_by = null) {
    global $db;

    // Validation
    if (empty($purchase_id) || empty($client_id) || empty($installments)) {
        return ['status' => 400, 'message' => 'Missing required parameters'];
    }

    if (!is_array($installments)) {
        return ['status' => 400, 'message' => 'Installments must be an array'];
    }

    // Start transaction
    try {
        $db->startTransaction();

        $inserted_count = 0;

        foreach ($installments as $index => $installment) {
            $data = [
                'purchase_id' => (int)$purchase_id,
                'client_id' => (int)$client_id,
                'installment_number' => isset($installment['installment_number']) ? (int)$installment['installment_number'] : ($index + 1),
                'particular' => isset($installment['particular']) ? trim($installment['particular']) : null,
                'due_date' => isset($installment['date']) ? $installment['date'] : (isset($installment['due_date']) ? $installment['due_date'] : null),
                'amount' => isset($installment['amount']) ? (float)$installment['amount'] : 0.00,
                'paid_amount' => isset($installment['paid_amount']) ? (float)$installment['paid_amount'] : 0.00,
                'payment_date' => isset($installment['payment_date']) ? $installment['payment_date'] : null,
                'payment_method' => isset($installment['payment_method']) ? trim($installment['payment_method']) : null,
                'money_receipt_no' => isset($installment['money_receipt_no']) ? trim($installment['money_receipt_no']) : null,
                'remarks' => isset($installment['remarks']) ? trim($installment['remarks']) : null,
                'status' => isset($installment['status']) ? (int)$installment['status'] : 0,
                'is_adjustment' => isset($installment['is_adjustment']) ? (int)$installment['is_adjustment'] : 0,
                'created_by' => $created_by ? (int)$created_by : null
            ];

            $result = $db->insert('crm_payment_schedule', $data);

            if ($result) {
                $inserted_count++;
            }
        }

        $db->commit();

        return [
            'status' => 200,
            'message' => "Created {$inserted_count} payment schedule entries",
            'count' => $inserted_count
        ];

    } catch (Exception $e) {
        $db->rollback();
        return ['status' => 500, 'message' => 'Failed to create schedule: ' . $e->getMessage()];
    }
}

// ===============================
//  READ PAYMENT SCHEDULE
// ===============================

/**
 * Get payment schedule for a booking
 *
 * @param int $purchase_id The booking helper ID
 * @param array $filters Optional filters (status, date_from, date_to)
 * @return array Payment schedule entries
 */
function get_payment_schedule($purchase_id, $filters = []) {
    global $db;

    if (empty($purchase_id)) {
        return [];
    }

    $db->where('purchase_id', (int)$purchase_id);

    // Apply filters
    if (isset($filters['status']) && $filters['status'] !== '') {
        $db->where('status', (int)$filters['status']);
    }

    if (isset($filters['date_from'])) {
        $db->where('due_date', $filters['date_from'], '>=');
    }

    if (isset($filters['date_to'])) {
        $db->where('due_date', $filters['date_to'], '<=');
    }

    $db->orderBy('installment_number', 'ASC');

    return $db->get('crm_payment_schedule') ?: [];
}

/**
 * Get single payment schedule entry by ID
 *
 * @param int $schedule_id The schedule entry ID
 * @return object|null Payment schedule entry or null
 */
function get_payment_schedule_entry($schedule_id) {
    global $db;

    if (empty($schedule_id)) {
        return null;
    }

    return $db->where('id', (int)$schedule_id)->getOne('crm_payment_schedule');
}

/**
 * Get payment schedule summary for a booking
 *
 * @param int $purchase_id The booking helper ID
 * @return array Summary data (total, paid, due, pending_count, overdue_count)
 */
function get_payment_schedule_summary($purchase_id) {
    global $db;

    if (empty($purchase_id)) {
        return null;
    }

    $schedule = get_payment_schedule($purchase_id);

    if (empty($schedule)) {
        return null;
    }

    $total_amount = 0;
    $total_paid = 0;
    $pending_count = 0;
    $overdue_count = 0;
    $today = date('Y-m-d');

    foreach ($schedule as $entry) {
        $total_amount += (float)$entry->amount;
        $total_paid += (float)$entry->paid_amount;

        if ($entry->status == 0) {
            $pending_count++;
            if ($entry->due_date && $entry->due_date < $today) {
                $overdue_count++;
            }
        }
    }

    return [
        'total_amount' => $total_amount,
        'total_paid' => $total_paid,
        'total_due' => $total_amount - $total_paid,
        'pending_count' => $pending_count,
        'overdue_count' => $overdue_count,
        'total_entries' => count($schedule)
    ];
}

// ===============================
//  UPDATE PAYMENT SCHEDULE
// ===============================

/**
 * Update a payment schedule entry
 *
 * @param int $schedule_id The schedule entry ID
 * @param array $data Update data
 * @param int $updated_by User ID who updated
 * @return array Result with status and message
 */
function update_payment_schedule_entry($schedule_id, $data, $updated_by = null) {
    global $db;

    if (empty($schedule_id)) {
        return ['status' => 400, 'message' => 'Schedule ID required'];
    }

    // Check if entry exists
    $entry = get_payment_schedule_entry($schedule_id);
    if (!$entry) {
        return ['status' => 404, 'message' => 'Schedule entry not found'];
    }

    // Prepare update data
    $update_data = [];

    $allowed_fields = [
        'particular', 'due_date', 'amount', 'paid_amount', 'payment_date',
        'payment_method', 'money_receipt_no', 'remarks', 'status', 'is_adjustment'
    ];

    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = $data[$field];
        }
    }

    if ($updated_by) {
        $update_data['updated_by'] = (int)$updated_by;
    }

    if (empty($update_data)) {
        return ['status' => 400, 'message' => 'No valid update data provided'];
    }

    try {
        $result = $db->where('id', (int)$schedule_id)->update('crm_payment_schedule', $update_data);

        if ($result) {
            return ['status' => 200, 'message' => 'Schedule entry updated successfully'];
        } else {
            return ['status' => 500, 'message' => 'Failed to update schedule entry'];
        }
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

/**
 * Mark payment as paid
 *
 * @param int $schedule_id The schedule entry ID
 * @param float $amount Amount paid
 * @param string $payment_date Payment date (Y-m-d)
 * @param string $payment_method Payment method
 * @param string $receipt_no Receipt number
 * @param int $updated_by User ID
 * @return array Result with status and message
 */
function mark_payment_as_paid($schedule_id, $amount, $payment_date = null, $payment_method = null, $receipt_no = null, $updated_by = null) {
    global $db;

    $entry = get_payment_schedule_entry($schedule_id);
    if (!$entry) {
        return ['status' => 404, 'message' => 'Schedule entry not found'];
    }

    $paid_amount = (float)$entry->paid_amount + (float)$amount;
    $installment_amount = (float)$entry->amount;

    // Determine status
    $status = 0; // pending
    if ($paid_amount >= $installment_amount) {
        $status = 1; // paid
    } elseif ($paid_amount > 0) {
        $status = 2; // partial
    }

    $update_data = [
        'paid_amount' => $paid_amount,
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

    return update_payment_schedule_entry($schedule_id, $update_data, $updated_by);
}

// ===============================
//  UPDATE OVERDUE STATUS
// ===============================

/**
 * Update overdue status for all pending payments
 * Called by cron job daily
 *
 * @return array Result with count of updated entries
 */
function update_overdue_payment_status() {
    global $db;

    $today = date('Y-m-d');

    try {
        // Get all pending/partial payments with due date in the past
        $db->where('status', [0, 2], 'IN');
        $db->where('due_date', $today, '<');

        $result = $db->update('crm_payment_schedule', ['status' => 3]);

        $affected = $db->count;

        return [
            'status' => 200,
            'message' => "Updated {$affected} overdue payments",
            'count' => $affected
        ];
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Failed to update overdue status: ' . $e->getMessage()];
    }
}

// ===============================
//  CALCULATE SCHEDULE
// ===============================

/**
 * Calculate payment schedule based on plot changes
 *
 * @param float $total_price Total price of the plot
 * @param float $booking_money Booking money paid
 * @param float $down_payment Down payment amount
 * @param int $num_installments Number of installments
 * @param string $start_date Start date for installments (Y-m-d)
 * @param string $frequency Frequency (monthly, quarterly, yearly)
 * @return array Calculated installment schedule
 */
function calculate_payment_schedule($total_price, $booking_money = 0, $down_payment = 0, $num_installments = 12, $start_date = null, $frequency = 'monthly') {
    $total_price = (float)$total_price;
    $booking_money = (float)$booking_money;
    $down_payment = (float)$down_payment;
    $num_installments = (int)$num_installments;

    // Calculate remaining amount after booking and down payment
    $remaining = $total_price - $booking_money - $down_payment;

    if ($remaining <= 0) {
        return [];
    }

    // Calculate installment amount
    $installment_amount = round($remaining / $num_installments, 2);

    // Handle rounding difference in last installment
    $last_installment = $remaining - ($installment_amount * ($num_installments - 1));

    if (!$start_date) {
        $start_date = date('Y-m-d');
    }

    $schedule = [];
    $current_date = new DateTime($start_date);

    // Frequency mapping
    $interval_map = [
        'monthly' => 'P1M',
        'quarterly' => 'P3M',
        'yearly' => 'P1Y',
        'weekly' => 'P1W'
    ];

    $interval = new DateInterval($interval_map[$frequency] ?? 'P1M');

    for ($i = 1; $i <= $num_installments; $i++) {
        $amount = ($i == $num_installments) ? $last_installment : $installment_amount;

        $schedule[] = [
            'installment_number' => $i,
            'particular' => get_ordinal($i) . ' Installment',
            'due_date' => $current_date->format('Y-m-d'),
            'amount' => $amount,
            'paid_amount' => 0.00,
            'status' => 0
        ];

        $current_date->add($interval);
    }

    return $schedule;
}

/**
 * Helper function to get ordinal suffix (1st, 2nd, 3rd, etc.)
 */
function get_ordinal($number) {
    $suffix = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return $number . 'th';
    } else {
        return $number . $suffix[$number % 10];
    }
}

// ===============================
//  MIGRATION HELPER
// ===============================

/**
 * Migrate serialized installment data to payment schedule table
 *
 * @param int $purchase_id The booking helper ID
 * @return array Result with status and message
 */
function migrate_installment_to_schedule($purchase_id) {
    global $db;

    if (empty($purchase_id)) {
        return ['status' => 400, 'message' => 'Booking helper ID required'];
    }

    // Get booking helper record
    $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);

    if (!$helper) {
        return ['status' => 404, 'message' => 'Booking helper not found'];
    }

    // Check if already migrated
    $existing = get_payment_schedule($purchase_id);
    if (!empty($existing)) {
        return ['status' => 400, 'message' => 'Payment schedule already exists'];
    }

    // Parse serialized or JSON installment data
    $installment_data = null;

    if (!empty($helper->installment)) {
        $decoded = @json_decode($helper->installment, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $installment_data = $decoded;
        } else {
            $unserialized = @unserialize($helper->installment);
            if ($unserialized !== false && is_array($unserialized)) {
                $installment_data = $unserialized;
            }
        }
    }

    if (empty($installment_data)) {
        return ['status' => 400, 'message' => 'No installment data to migrate'];
    }

    // Create payment schedule
    return create_payment_schedule(
        $purchase_id,
        $helper->client_id,
        $installment_data,
        null // system migration
    );
}

/**
 * Bulk migrate all serialized installments to payment schedule
 *
 * @return array Result with counts
 */
function bulk_migrate_installments() {
    global $db;

    // Get all booking helpers with installment data but no schedule
    $sql = "
        SELECT bh.id, bh.client_id, bh.installment
        FROM " . T_BOOKING_HELPER . " bh
        LEFT JOIN crm_payment_schedule ps ON ps.purchase_id = bh.id
        WHERE bh.installment IS NOT NULL
        AND bh.installment != ''
        AND ps.id IS NULL
    ";

    $helpers = $db->rawQuery($sql);

    if (empty($helpers)) {
        return ['status' => 200, 'message' => 'No records to migrate', 'migrated' => 0];
    }

    $migrated = 0;
    $failed = 0;

    foreach ($helpers as $helper) {
        $result = migrate_installment_to_schedule($helper->id);
        if ($result['status'] == 200) {
            $migrated++;
        } else {
            $failed++;
        }
    }

    return [
        'status' => 200,
        'message' => "Migration complete: {$migrated} migrated, {$failed} failed",
        'migrated' => $migrated,
        'failed' => $failed
    ];
}
