<?php
/**
 * Phase 3: XHR Endpoints for Payment Schedules, Transfers & Refunds
 * Phase 5: Email Integration
 * 
 * All endpoints follow the pattern:
 * POST /xhr/manage_schedule_endpoints.php?f=manage_schedule&s={endpoint_name}
 * 
 * Response Format:
 * {
 *   "status": 200|400|404|500,
 *   "message": "Human readable message",
 *   "data": {...}
 * }
 */
 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===============================
// 🔐 CONFIGURATION & SECURITY
// ===============================
date_default_timezone_set("Asia/Dhaka");
header("Content-type: application/json; charset=utf-8");


if ($s == "lockout_check") {
    echo json_encode([
        "status" => $is_lockout ? 400 : 200,
        "message" => $is_lockout ? "Session Timeout!" : "Session still alive!"
    ]);
    exit;
}

if ($f == "manage_schedule_endpoints") {
    // Check user permissions
    if (!(Wo_IsAdmin() || Wo_IsModerator() || check_permission("manage-clients") || check_permission("clients"))) {
        echo json_encode([
            'status' => 404,
            'message' => "You don't have permission"
        ]);
        exit;
    }

    // ========================================
    // PAYMENT SCHEDULE ENDPOINTS
    // ========================================

    /**
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_payment_schedule
     * Fetch payment schedule for a booking
     */
    if ($s === 'get_payment_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;

        if (!$purchase_id) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id is required'
            ]);
            exit;
        }

        try {
            // Get booking helper
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode([
                    'status' => 404,
                    'message' => 'Booking not found'
                ]);
                exit;
            }

            // Get payment schedule using function from Phase 2
            $schedule = get_payment_schedule_rows($purchase_id);
            $summary = get_payment_schedule_summary($purchase_id);

            echo json_encode([
                'status' => 200,
                'booking_helper' => [
                    'id' => $helper->id,
                    'client_id' => $helper->client_id,
                    'per_katha' => $helper->per_katha,
                    'booking_money' => $helper->booking_money,
                    'down_payment' => $helper->down_payment
                ],
                'payment_schedule' => $schedule ?: [],
                'summary' => $summary ?: []
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=recalculate_schedule
     * Recalculate payment schedule after plot/rate changes
     */
    if ($s === 'recalculate_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $new_per_katha = isset($_POST['new_per_katha']) ? (float)$_POST['new_per_katha'] : 0;

        if (!$purchase_id || !$new_per_katha) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id and new_per_katha are required'
            ]);
            exit;
        }

        try {
            // Get current helper and booking
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            $booking = $db->where('id', $helper->booking_id)->getOne(T_BOOKING);
            if (!$booking) {
                echo json_encode(['status' => 404, 'message' => 'Plot not found']);
                exit;
            }

            // Calculate old vs new totals
            $old_total = (float)$helper->per_katha * (float)$booking->katha;
            $new_total = $new_per_katha * (float)$booking->katha;
            $difference = $new_total - $old_total;

            echo json_encode([
                'status' => 200,
                'message' => 'Schedule recalculated',
                'old_rate' => $helper->per_katha,
                'new_rate' => $new_per_katha,
                'old_total' => $old_total,
                'new_total' => $new_total,
                'rate_difference' => $difference,
                'katha' => $booking->katha
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=update_payment_status
     * Mark an installment as paid
     */
    if ($s === 'update_payment_status') {
        $payment_schedule_id = isset($_POST['payment_schedule_id']) ? (int)$_POST['payment_schedule_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Bank Transfer';
        $receipt_no = isset($_POST['receipt_no']) ? trim($_POST['receipt_no']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

        if (!$payment_schedule_id || !$amount) {
            echo json_encode([
                'status' => 400,
                'message' => 'payment_schedule_id and amount are required'
            ]);
            exit;
        }

        try {
            // Use function from Phase 2: add_payment_schedule_payment
            $result = add_payment_schedule_payment(
                $payment_schedule_id,
                $amount,
                $payment_date,
                $payment_method,
                $receipt_no,
                $remarks,
                $wo['user']['id'] ?? null
            );

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=send_schedule_email
     * Send payment schedule via email to client
     */
    if ($s === 'send_schedule_email') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            // Get client info
            $client = GetCustomerById($helper->client_id);
            if (!$client) {
                echo json_encode(['status' => 404, 'message' => 'Client not found']);
                exit;
            }

            if (!$recipient_email) {
                $recipient_email = $client['email'] ?? '';
            }

            if (!$recipient_email || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'status' => 400,
                    'message' => 'Valid email address is required'
                ]);
                exit;
            }

            $result = send_crm_email('payment_schedule', $client['id'], $purchase_id);

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=toggle_auto_email
     * Toggle automatic email notifications
     */
    if ($s === 'toggle_auto_email') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $schedule = $db->where('purchase_id', $purchase_id)->getOne('crm_payment_schedule');
            
            if (!$schedule) {
                echo json_encode(['status' => 404, 'message' => 'Payment schedule not found']);
                exit;
            }

            $db->where('id', $schedule['id'])->update('crm_payment_schedule', [
                'auto_email_enabled' => $enabled,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            logActivity('payment_schedule', 'auto_email_' . ($enabled ? 'enabled' : 'disabled'), 
                "Auto email " . ($enabled ? 'enabled' : 'disabled') . " for booking {$purchase_id}");

            echo json_encode([
                'status' => 200,
                'message' => 'Auto email ' . ($enabled ? 'enabled' : 'disabled') . ' successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ========================================
    // TRANSFER ENDPOINTS
    // ========================================

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=initiate_transfer
     * Initiate name or plot transfer
     */
    if ($s === 'initiate_transfer') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $transfer_type = isset($_POST['transfer_type']) ? trim($_POST['transfer_type']) : '';
        $to_client_id = isset($_POST['to_client_id']) ? (int)$_POST['to_client_id'] : 0;
        $new_purchase_id = isset($_POST['new_purchase_id']) ? (int)$_POST['new_purchase_id'] : 0;
        $new_rate = isset($_POST['new_rate']) ? (float)$_POST['new_rate'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if (!$purchase_id || !$transfer_type || !$reason) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id, transfer_type, and reason are required'
            ]);
            exit;
        }

        try {
            $result = [];

            if ($transfer_type === 'name_transfer') {
                if (!$to_client_id) {
                    echo json_encode(['status' => 400, 'message' => 'to_client_id required for name transfer']);
                    exit;
                }

                // Use function from Phase 2
                $result = initiate_name_transfer(
                    $purchase_id,
                    0, // from_client_id - will be fetched in function
                    $to_client_id,
                    ['reason' => $reason],
                    $wo['user']['id'] ?? null
                );
            } elseif ($transfer_type === 'plot_transfer') {
                if (!$new_purchase_id) {
                    echo json_encode(['status' => 400, 'message' => 'new_purchase_id required for plot transfer']);
                    exit;
                }

                // Use function from Phase 2
                $result = initiate_plot_transfer(
                    $purchase_id,
                    $new_purchase_id,
                    $new_rate ?: null,
                    $reason,
                    [],
                    $wo['user']['id'] ?? null
                );
            } else {
                echo json_encode(['status' => 400, 'message' => 'Invalid transfer_type']);
                exit;
            }

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_transfer_history
     * Get transfer history for a booking
     */
    if ($s === 'get_transfer_history') {
        $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : (isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0);
        $transfer_type = isset($_GET['type']) ? trim($_GET['type']) : '';

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $filters = [];
            if ($transfer_type) {
                $filters['transfer_type'] = $transfer_type;
            }

            // Use function from Phase 2
            $history = get_transfer_history($purchase_id, $filters);

            echo json_encode([
                'status' => 200,
                'transfers' => $history ?: [],
                'count' => count($history ?: [])
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_pending_transfers
     * Admin view of pending transfers
     */
    if ($s === 'get_pending_transfers') {
        try {
            // Use function from Phase 2
            $pending = get_pending_transfers(['approval_status' => 0]);

            echo json_encode([
                'status' => 200,
                'transfers' => $pending ?: [],
                'count' => count($pending ?: [])
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=approve_transfer
     * Admin approves a transfer with email notification
     */
    if ($s === 'approve_transfer') {
        $transfer_id = isset($_POST['transfer_id']) ? (int)$_POST['transfer_id'] : 0;

        if (!$transfer_id) {
            echo json_encode(['status' => 400, 'message' => 'transfer_id is required']);
            exit;
        }

        try {
            $transfer = $db->where('id', $transfer_id)->getOne('crm_transfer_history');
            if (!$transfer) {
                echo json_encode(['status' => 404, 'message' => 'Transfer not found']);
                exit;
            }

            // Use function from Phase 2
            $result = approve_name_transfer($transfer_id, $wo['user']['id'] ?? null);

            if ($result['status'] !== 200) {
                $result = approve_plot_transfer($transfer_id, $wo['user']['id'] ?? null);
            }

            if ($result['status'] === 200) {
                $client = GetCustomerById($transfer['from_client_id']);
                if ($client) {
                    send_crm_email('transfer', $client['id'], $transfer['purchase_id'], [
                        'transfer_type' => $transfer['transfer_type'],
                        'transfer_data' => [
                            'status' => 'approved',
                            'approval_date' => date('Y-m-d')
                        ]
                    ]);
                }
            }

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=reject_transfer
     * Admin rejects a transfer
     */
    if ($s === 'reject_transfer') {
        $transfer_id = isset($_POST['transfer_id']) ? (int)$_POST['transfer_id'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'No reason provided';

        if (!$transfer_id) {
            echo json_encode(['status' => 400, 'message' => 'transfer_id is required']);
            exit;
        }

        try {
            // Use function from Phase 2
            $result = reject_transfer($transfer_id, $reason, $wo['user']['id'] ?? null);

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ========================================
    // REFUND ENDPOINTS
    // ========================================

    /**
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=calculate_refund
     * Calculate refund breakdown
     */
    if ($s === 'calculate_refund') {
        $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : (isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0);
        $deduction_percent = isset($_GET['deduction_percent']) ? (float)$_GET['deduction_percent'] : (isset($_POST['deduction_percent']) ? (float)$_POST['deduction_percent'] : 10);

        if (!$purchase_id) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id is required'
            ]);
            exit;
        }

        try {
            // Use function from Phase 2
            $result = calculate_refund($purchase_id, $deduction_percent);

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=create_refund_schedule
     * Create refund installments
     */
    if ($s === 'create_refund_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $deduction_percent = isset($_POST['deduction_percent']) ? (float)$_POST['deduction_percent'] : 10;
        $num_installments = isset($_POST['num_installments']) ? (int)$_POST['num_installments'] : 1;
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : date('Y-m-d');

        if (!$purchase_id) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id is required'
            ]);
            exit;
        }

        try {
            // Use function from Phase 2
            $result = create_refund_schedule(
                $purchase_id,
                $deduction_percent,
                $num_installments,
                $start_date,
                $wo['user']['id'] ?? null
            );

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=add_refund_payment
     * Record refund payment
     */
    if ($s === 'add_refund_payment') {
        $refund_schedule_id = isset($_POST['refund_schedule_id']) ? (int)$_POST['refund_schedule_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Bank Transfer';
        $receipt_no = isset($_POST['receipt_no']) ? trim($_POST['receipt_no']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

        if (!$refund_schedule_id || !$amount) {
            echo json_encode([
                'status' => 400,
                'message' => 'refund_schedule_id and amount are required'
            ]);
            exit;
        }

        try {
            // Use function from Phase 2
            $result = add_refund_payment(
                $refund_schedule_id,
                $amount,
                $payment_date,
                $payment_method,
                $receipt_no,
                $remarks,
                $wo['user']['id'] ?? null
            );

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_refund_status
     * View refund details and status
     */
    if ($s === 'get_refund_status') {
        $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : (isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0);

        if (!$purchase_id) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id is required'
            ]);
            exit;
        }

        try {
            // Use function from Phase 2
            $schedule = get_refund_schedule($purchase_id);
            $summary = get_refund_schedule_summary($purchase_id);

            echo json_encode([
                'status' => 200,
                'refund_schedule' => $schedule ?: [],
                'summary' => $summary ?: []
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=cancel_refund
     * Cancel pending refund schedule
     */
    if ($s === 'cancel_refund') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'No reason provided';

        if (!$purchase_id) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id is required'
            ]);
            exit;
        }

        try {
            // Use function from Phase 2
            $result = cancel_refund_schedule($purchase_id, $reason, $wo['user']['id'] ?? null);

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ========================================
    // DEFAULT RESPONSE
    // ========================================
    echo json_encode([
        'status' => 404,
        'message' => 'Endpoint not found'
    ]);
    exit;
}
?>
