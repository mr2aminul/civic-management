<?php
/**
 * CRM Transfer Functions
 * Manages name and plot transfers with approval workflow
 *
 * Phase 2 - Step 2.2: Transfer Functions
 */

// ===============================
//  NAME TRANSFER FUNCTIONS
// ===============================

/**
 * Initiate name transfer (change client while keeping plot and history)
 *
 * @param int $purchase_id The booking helper ID
 * @param int $from_client_id Original client ID
 * @param int $to_client_id New client ID
 * @param array $details Additional transfer details
 * @param int $created_by User ID who initiated
 * @return array Result with status and message
 */
function initiate_name_transfer($purchase_id, $from_client_id, $to_client_id, $details = [], $created_by = null) {
    global $db;

    // Validation
    if (empty($purchase_id) || empty($from_client_id) || empty($to_client_id)) {
        return ['status' => 400, 'message' => 'Missing required parameters'];
    }

    if ($from_client_id == $to_client_id) {
        return ['status' => 400, 'message' => 'Cannot transfer to same client'];
    }

    // Check if booking helper exists
    $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
    if (!$helper) {
        return ['status' => 404, 'message' => 'Booking not found'];
    }

    // Verify from_client matches
    if ($helper->client_id != $from_client_id) {
        return ['status' => 400, 'message' => 'Client mismatch'];
    }

    // Check if clients exist
    $from_client = $db->where('id', (int)$from_client_id)->getOne(T_CUSTOMERS);
    $to_client = $db->where('id', (int)$to_client_id)->getOne(T_CUSTOMERS);

    if (!$from_client || !$to_client) {
        return ['status' => 404, 'message' => 'Client not found'];
    }

    // Check for pending transfers
    $pending = $db->where('purchase_id', (int)$purchase_id)
                  ->where('approval_status', 0)
                  ->getOne('crm_transfer_history');

    if ($pending) {
        return ['status' => 400, 'message' => 'Pending transfer already exists'];
    }

    try {
        $db->startTransaction();

        // Create transfer record
        $transfer_data = [
            'purchase_id' => (int)$purchase_id,
            'transfer_type' => 'name_transfer',
            'from_client_id' => (int)$from_client_id,
            'to_client_id' => (int)$to_client_id,
            'transfer_date' => date('Y-m-d'),
            'approval_status' => 0, // pending
            'name_transfer_details' => !empty($details) ? json_encode($details) : null,
            'remarks' => isset($details['remarks']) ? $details['remarks'] : null,
            'created_by' => $created_by ? (int)$created_by : null
        ];

        $transfer_id = $db->insert('crm_transfer_history', $transfer_data);

        if (!$transfer_id) {
            $db->rollback();
            return ['status' => 500, 'message' => 'Failed to create transfer request'];
        }

        $db->commit();

        return [
            'status' => 200,
            'message' => 'Name transfer request created successfully',
            'transfer_id' => $transfer_id
        ];

    } catch (Exception $e) {
        $db->rollback();
        return ['status' => 500, 'message' => 'Transfer failed: ' . $e->getMessage()];
    }
}

/**
 * Approve name transfer
 *
 * @param int $transfer_id Transfer history ID
 * @param int $approved_by User ID who approved
 * @return array Result with status and message
 */
function approve_name_transfer($transfer_id, $approved_by = null) {
    global $db;

    if (empty($transfer_id)) {
        return ['status' => 400, 'message' => 'Transfer ID required'];
    }

    // Get transfer record
    $transfer = $db->where('id', (int)$transfer_id)->getOne('crm_transfer_history');

    if (!$transfer) {
        return ['status' => 404, 'message' => 'Transfer not found'];
    }

    if ($transfer->transfer_type !== 'name_transfer') {
        return ['status' => 400, 'message' => 'Not a name transfer'];
    }

    if ($transfer->approval_status != 0) {
        return ['status' => 400, 'message' => 'Transfer already processed'];
    }

    try {
        $db->startTransaction();

        // Update booking helper client_id
        $update_result = $db->where('id', (int)$transfer->purchase_id)
                            ->update(T_BOOKING_HELPER, ['client_id' => (int)$transfer->to_client_id]);

        if (!$update_result) {
            $db->rollback();
            return ['status' => 500, 'message' => 'Failed to update booking'];
        }

        // Update transfer status
        $transfer_update = [
            'approval_status' => 1, // approved
            'approval_date' => date('Y-m-d'),
            'approved_by' => $approved_by ? (int)$approved_by : null,
            'updated_by' => $approved_by ? (int)$approved_by : null
        ];

        $db->where('id', (int)$transfer_id)->update('crm_transfer_history', $transfer_update);

        $db->commit();

        return [
            'status' => 200,
            'message' => 'Name transfer approved successfully'
        ];

    } catch (Exception $e) {
        $db->rollback();
        return ['status' => 500, 'message' => 'Approval failed: ' . $e->getMessage()];
    }
}

// ===============================
//  PLOT TRANSFER FUNCTIONS
// ===============================

/**
 * Initiate plot transfer with rate adjustment
 *
 * @param int $purchase_id The booking helper ID
 * @param int $new_purchase_id New plot purchase ID
 * @param float $new_rate New rate per katha
 * @param string $rate_adjustment_reason Reason for rate change
 * @param array $details Additional transfer details
 * @param int $created_by User ID who initiated
 * @return array Result with status and message
 */
function initiate_plot_transfer($purchase_id, $new_purchase_id, $new_rate = null, $rate_adjustment_reason = null, $details = [], $created_by = null) {
    global $db;

    // Validation
    if (empty($purchase_id) || empty($new_purchase_id)) {
        return ['status' => 400, 'message' => 'Missing required parameters'];
    }

    // Get current booking helper
    $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
    if (!$helper) {
        return ['status' => 404, 'message' => 'Booking not found'];
    }

    // Get current and new plot details
    $current_plot = $db->where('id', (int)$helper->booking_id)->getOne(T_BOOKING);
    $new_plot = $db->where('id', (int)$new_purchase_id)->getOne(T_BOOKING);

    if (!$current_plot || !$new_plot) {
        return ['status' => 404, 'message' => 'Plot not found'];
    }

    // Check if new plot is available
    if ($new_plot->status == 2) {
        return ['status' => 400, 'message' => 'New plot is not available'];
    }

    // Check for pending transfers
    $pending = $db->where('purchase_id', (int)$purchase_id)
                  ->where('approval_status', 0)
                  ->getOne('crm_transfer_history');

    if ($pending) {
        return ['status' => 400, 'message' => 'Pending transfer already exists'];
    }

    // Calculate rate adjustment
    $old_rate = (float)$helper->per_katha;
    $new_rate = $new_rate !== null ? (float)$new_rate : $old_rate;

    $old_katha = (float)($current_plot->katha ?? 0);
    $new_katha = (float)($new_plot->katha ?? 0);

    $old_total = $old_rate * $old_katha;
    $new_total = $new_rate * $new_katha;
    $rate_adjustment_amount = $new_total - $old_total;

    try {
        $db->startTransaction();

        // Create transfer record
        $transfer_data = [
            'purchase_id' => (int)$purchase_id,
            'transfer_type' => 'plot_transfer',
            'from_client_id' => (int)$helper->client_id,
            'to_client_id' => (int)$helper->client_id, // same client
            'transfer_date' => date('Y-m-d'),
            'approval_status' => 0, // pending
            'plot_transfer_rate_old' => $old_rate,
            'plot_transfer_rate_new' => $new_rate,
            'rate_adjustment_reason' => $rate_adjustment_reason,
            'rate_adjustment_amount' => $rate_adjustment_amount,
            'plot_transfer_details' => json_encode([
                'old_plot_id' => (int)$helper->booking_id,
                'new_plot_id' => (int)$new_purchase_id,
                'old_katha' => $old_katha,
                'new_katha' => $new_katha,
                'old_total' => $old_total,
                'new_total' => $new_total
            ]),
            'remarks' => isset($details['remarks']) ? $details['remarks'] : null,
            'created_by' => $created_by ? (int)$created_by : null
        ];

        $transfer_id = $db->insert('crm_transfer_history', $transfer_data);

        if (!$transfer_id) {
            $db->rollback();
            return ['status' => 500, 'message' => 'Failed to create transfer request'];
        }

        $db->commit();

        return [
            'status' => 200,
            'message' => 'Plot transfer request created successfully',
            'transfer_id' => $transfer_id,
            'rate_adjustment_amount' => $rate_adjustment_amount
        ];

    } catch (Exception $e) {
        $db->rollback();
        return ['status' => 500, 'message' => 'Transfer failed: ' . $e->getMessage()];
    }
}

/**
 * Approve plot transfer
 *
 * @param int $transfer_id Transfer history ID
 * @param int $approved_by User ID who approved
 * @return array Result with status and message
 */
function approve_plot_transfer($transfer_id, $approved_by = null) {
    global $db;

    if (empty($transfer_id)) {
        return ['status' => 400, 'message' => 'Transfer ID required'];
    }

    // Get transfer record
    $transfer = $db->where('id', (int)$transfer_id)->getOne('crm_transfer_history');

    if (!$transfer) {
        return ['status' => 404, 'message' => 'Transfer not found'];
    }

    if ($transfer->transfer_type !== 'plot_transfer') {
        return ['status' => 400, 'message' => 'Not a plot transfer'];
    }

    if ($transfer->approval_status != 0) {
        return ['status' => 400, 'message' => 'Transfer already processed'];
    }

    // Parse plot details
    $plot_details = json_decode($transfer->plot_transfer_details, true);
    if (!$plot_details || !isset($plot_details['new_plot_id'])) {
        return ['status' => 400, 'message' => 'Invalid plot transfer details'];
    }

    $new_plot_id = (int)$plot_details['new_plot_id'];
    $old_plot_id = (int)$plot_details['old_plot_id'];

    try {
        $db->startTransaction();

        // Update booking helper with new plot and rate
        $update_data = [
            'booking_id' => $new_plot_id,
            'per_katha' => (float)$transfer->plot_transfer_rate_new
        ];

        $update_result = $db->where('id', (int)$transfer->purchase_id)
                            ->update(T_BOOKING_HELPER, $update_data);

        if (!$update_result) {
            $db->rollback();
            return ['status' => 500, 'message' => 'Failed to update booking'];
        }

        // Reset old plot status to available
        $db->where('id', $old_plot_id)->update(T_BOOKING, [
            'status' => 1,
            'file_num' => null
        ]);

        // Mark new plot as sold
        $db->where('id', $new_plot_id)->update(T_BOOKING, [
            'status' => 2
        ]);

        // Update transfer status
        $transfer_update = [
            'approval_status' => 1, // approved
            'approval_date' => date('Y-m-d'),
            'approved_by' => $approved_by ? (int)$approved_by : null,
            'updated_by' => $approved_by ? (int)$approved_by : null
        ];

        $db->where('id', (int)$transfer_id)->update('crm_transfer_history', $transfer_update);

        $db->commit();

        return [
            'status' => 200,
            'message' => 'Plot transfer approved successfully'
        ];

    } catch (Exception $e) {
        $db->rollback();
        return ['status' => 500, 'message' => 'Approval failed: ' . $e->getMessage()];
    }
}

// ===============================
//  REJECT TRANSFER
// ===============================

/**
 * Reject transfer request
 *
 * @param int $transfer_id Transfer history ID
 * @param string $rejection_reason Reason for rejection
 * @param int $approved_by User ID who rejected
 * @return array Result with status and message
 */
function reject_transfer($transfer_id, $rejection_reason = null, $approved_by = null) {
    global $db;

    if (empty($transfer_id)) {
        return ['status' => 400, 'message' => 'Transfer ID required'];
    }

    // Get transfer record
    $transfer = $db->where('id', (int)$transfer_id)->getOne('crm_transfer_history');

    if (!$transfer) {
        return ['status' => 404, 'message' => 'Transfer not found'];
    }

    if ($transfer->approval_status != 0) {
        return ['status' => 400, 'message' => 'Transfer already processed'];
    }

    try {
        // Update transfer status
        $update_data = [
            'approval_status' => 2, // rejected
            'approval_date' => date('Y-m-d'),
            'approved_by' => $approved_by ? (int)$approved_by : null,
            'rejection_reason' => $rejection_reason,
            'updated_by' => $approved_by ? (int)$approved_by : null
        ];

        $result = $db->where('id', (int)$transfer_id)
                     ->update('crm_transfer_history', $update_data);

        if ($result) {
            return [
                'status' => 200,
                'message' => 'Transfer rejected successfully'
            ];
        } else {
            return ['status' => 500, 'message' => 'Failed to reject transfer'];
        }

    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Rejection failed: ' . $e->getMessage()];
    }
}

// ===============================
//  GET TRANSFER HISTORY
// ===============================

/**
 * Get transfer history for a booking
 *
 * @param int $purchase_id The booking helper ID
 * @param array $filters Optional filters
 * @return array Transfer history records
 */
function get_transfer_history($purchase_id, $filters = []) {
    global $db;

    if (empty($purchase_id)) {
        return [];
    }

    $db->where('purchase_id', (int)$purchase_id);

    if (isset($filters['transfer_type']) && $filters['transfer_type'] !== '') {
        $db->where('transfer_type', $filters['transfer_type']);
    }

    if (isset($filters['approval_status']) && $filters['approval_status'] !== '') {
        $db->where('approval_status', (int)$filters['approval_status']);
    }

    $db->orderBy('transfer_date', 'DESC');

    return $db->get('crm_transfer_history') ?: [];
}

/**
 * Get pending transfers (admin view)
 *
 * @param array $filters Optional filters
 * @return array Pending transfer records
 */
function get_pending_transfers($filters = []) {
    global $db;

    $db->where('approval_status', 0);

    if (isset($filters['transfer_type']) && $filters['transfer_type'] !== '') {
        $db->where('transfer_type', $filters['transfer_type']);
    }

    $db->orderBy('transfer_date', 'ASC');

    return $db->get('crm_transfer_history') ?: [];
}
