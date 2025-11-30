<?php
/**
 * CRM Payment Schedule Functions
 * Manages payment installment schedules for client purchases
 * UPDATED: Now uses new schema columns (installment_amount, installment_type)
 */

// ===============================
//  CREATE PAYMENT SCHEDULE
// ===============================

/**
 * Create payment schedule entries for a booking
 */
function create_payment_schedule($purchase_id, $client_id, $installments, $created_by = null) {
    global $db;

    if (empty($purchase_id) || empty($client_id) || empty($installments)) {
        return ['status' => 400, 'message' => 'Missing required parameters'];
    }

    if (!is_array($installments)) {
        return ['status' => 400, 'message' => 'Installments must be an array'];
    }

    try {
        $db->startTransaction();

        $inserted_count = 0;

        foreach ($installments as $index => $installment) {
            $data = [
                'purchase_id' => (int)$purchase_id,
                'client_id' => (int)$client_id,
                'installment_number' => isset($installment['installment_number']) ? (int)$installment['installment_number'] : ($index + 1),
                'particular' => isset($installment['particular']) ? trim($installment['particular']) : null,
                'installment_type' => isset($installment['installment_type']) ? trim($installment['installment_type']) : 'installment',
                'due_date' => isset($installment['date']) ? $installment['date'] : (isset($installment['due_date']) ? $installment['due_date'] : null),
                'installment_amount' => isset($installment['amount']) ? (float)$installment['amount'] : 0.00,
                'paid_amount' => isset($installment['paid_amount']) ? (float)$installment['paid_amount'] : 0.00,
                'payment_date' => isset($installment['payment_date']) ? $installment['payment_date'] : null,
                'payment_method' => isset($installment['payment_method']) ? trim($installment['payment_method']) : null,
                'money_receipt_no' => isset($installment['money_receipt_no']) ? trim($installment['money_receipt_no']) : null,
                'remarks' => isset($installment['remarks']) ? trim($installment['remarks']) : null,
                'status' => isset($installment['status']) ? (int)$installment['status'] : 0,
                'is_adjustment' => isset($installment['is_adjustment']) ? (int)$installment['is_adjustment'] : 0,
                'created_by' => $created_by ? (int)$created_by : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $result = $db->insert('crm_payment_schedule', $data);

            if ($result) {
                $inserted_count++;
            }
        }

        $db->commit();

        // Log to audit trail
        if (function_exists('auditLog')) {
            auditLog('create', 'payment_schedule', "Created {$inserted_count} payment schedule entries", null, [
                'purchase_id' => $purchase_id,
                'count' => $inserted_count
            ]);
        }

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

function get_payment_schedule($purchase_id, $filters = []) {
    global $db;

    if (empty($purchase_id)) {
        return [];
    }

    $db->where('purchase_id', (int)$purchase_id);
    $db->where('status', 99, '!='); // Exclude deleted/rescheduled

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

function get_payment_schedule_entry($schedule_id) {
    global $db;

    if (empty($schedule_id)) {
        return null;
    }

    return $db->where('id', (int)$schedule_id)->getOne('crm_payment_schedule');
}

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
        $total_amount += (float)$entry->installment_amount;
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

function update_payment_schedule_entry($schedule_id, $data, $updated_by = null) {
    global $db;

    if (empty($schedule_id)) {
        return ['status' => 400, 'message' => 'Schedule ID required'];
    }

    $entry = get_payment_schedule_entry($schedule_id);
    if (!$entry) {
        return ['status' => 404, 'message' => 'Schedule entry not found'];
    }

    $update_data = [];

    $allowed_fields = [
        'particular', 'due_date', 'installment_amount', 'paid_amount', 'payment_date',
        'payment_method', 'money_receipt_no', 'remarks', 'status', 'is_adjustment', 'installment_type'
    ];

    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = $data[$field];
        }
    }

    if ($updated_by) {
        $update_data['updated_by'] = (int)$updated_by;
    }

    $update_data['updated_at'] = date('Y-m-d H:i:s');

    if (empty($update_data)) {
        return ['status' => 400, 'message' => 'No valid update data provided'];
    }

    try {
        $result = $db->where('id', (int)$schedule_id)->update('crm_payment_schedule', $update_data);

        if ($result) {
            // Log to audit trail
            if (function_exists('auditLog')) {
                auditLog('update', 'payment_schedule', "Updated schedule entry #{$schedule_id}", $entry, $update_data);
            }

            return ['status' => 200, 'message' => 'Schedule entry updated successfully'];
        } else {
            return ['status' => 500, 'message' => 'Failed to update schedule entry'];
        }
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

function mark_payment_as_paid($schedule_id, $amount, $payment_date = null, $payment_method = null, $receipt_no = null, $updated_by = null) {
    global $db;

    $entry = get_payment_schedule_entry($schedule_id);
    if (!$entry) {
        return ['status' => 404, 'message' => 'Schedule entry not found'];
    }

    $paid_amount = (float)$entry->paid_amount + (float)$amount;
    $installment_amount = (float)$entry->installment_amount;

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
//  HELPER: Ordinal suffix
// ===============================

function get_ordinal($number) {
    $suffix = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return $number . 'th';
    } else {
        return $number . $suffix[$number % 10];
    }
}
// Helper function to log audit trail
function logAuditTrail($purchase_id, $action, $category, $description, $before = null, $after = null) {
    global $db, $wo;
    
    $user_id = $wo['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Fetch client_id from purchase
    $client_id = 0;
    if ($purchase_id) {
        $ph = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['client_id']);
        if ($ph) {
            $client_id = intval($ph->client_id);
        }
    }
    
    $data = [
        'client_id' => $client_id,
        'purchase_id' => $purchase_id,
        'action_type' => $action,
        'action_category' => $category,
        'action_description' => $description,
        'before_values' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
        'after_values' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
        'performed_by' => $user_id,
        'performed_at' => date('Y-m-d H:i:s'),
        'ip_address' => $ip_address,
    ];
    
    return $db->insert('crm_audit_trail', $data);
}