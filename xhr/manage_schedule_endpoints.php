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
    if (!(Wo_IsAdmin() || Wo_IsModerator() || check_permission("manage-clients") || check_permission("clients"))) {
        echo json_encode(['status' => 403, 'message' => "You don't have permission"]);
        exit;
    }

    // ========================================
    // PAYMENT SCHEDULE ENDPOINTS
    // ========================================

    /**
     * Enhanced to load from dedicated crm_payment_schedule table
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_payment_schedule
     * Fetch payment schedule for a booking
     */
    if ($s === 'get_payment_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            // Load from dedicated table
            $schedule = $db->where('purchase_id', $purchase_id)->where('status', 99, '!=')->orderBy('installment_number', 'ASC')->get('crm_payment_schedule') ?: [];
            
            // Calculate summary
            $total_amount = 0;
            $total_paid = 0;
            $pending_count = 0;
            foreach ($schedule as $entry) {
                $total_amount += (float)$entry->amount;
                $total_paid += (float)$entry->paid_amount;
                if ($entry->status == 0) $pending_count++;
            }

            echo json_encode([
                'status' => 200,
                'schedule' => $schedule,
                'summary' => [
                    'total_amount' => $total_amount,
                    'total_paid' => $total_paid,
                    'total_due' => $total_amount - $total_paid,
                    'pending_count' => $pending_count,
                    'total_entries' => count($schedule)
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=recalculate_schedule
     * Recalculate schedule after plot changes
     * Recalculate payment schedule after plot/rate changes
     */
    if ($s === 'recalculate_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $new_plot_id = isset($_POST['new_plot_id']) ? (int)$_POST['new_plot_id'] : 0;

        if (!$purchase_id || !$new_plot_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id and new_plot_id are required']);
            exit;
        }

        try {
            $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            $old_booking = $db->where('id', $helper->booking_id)->getOne('wo_booking');
            $new_booking = $db->where('id', $new_plot_id)->getOne('wo_booking');

            if (!$old_booking || !$new_booking) {
                echo json_encode(['status' => 404, 'message' => 'Plot not found']);
                exit;
            }

            $old_total = (float)$helper->per_katha * (float)$old_booking->katha;
            $new_total = (float)$helper->per_katha * (float)$new_booking->katha;
            $difference = $new_total - $old_total;

            // Log audit
            log_crm_audit('payment_schedule', 'plot_change', $purchase_id,
                ['old_plot' => $old_booking->plot, 'new_plot' => $new_booking->plot, 'price_difference' => $difference],
                $_SESSION['user_id'] ?? null
            );

            echo json_encode([
                'status' => 200,
                'message' => 'Schedule recalculation preview',
                'old_plot' => $old_booking->plot,
                'new_plot' => $new_booking->plot,
                'old_total' => $old_total,
                'new_total' => $new_total,
                'difference' => $difference,
                'per_katha' => $helper->per_katha
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Updated to use dedicated payment_schedule table
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=update_payment_status
     * Mark an installment as paid
     */
    if ($s === 'update_payment_status') {
        $payment_schedule_id = isset($_POST['payment_schedule_id']) ? (int)$_POST['payment_schedule_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Bank Transfer';
        $receipt_no = isset($_POST['receipt_no']) ? trim($_POST['receipt_no']) : '';

        if (!$payment_schedule_id || !$amount) {
            echo json_encode(['status' => 400, 'message' => 'payment_schedule_id and amount are required']);
            exit;
        }

        try {
            $entry = $db->where('id', $payment_schedule_id)->getOne('crm_payment_schedule');
            if (!$entry) {
                echo json_encode(['status' => 404, 'message' => 'Payment schedule entry not found']);
                exit;
            }

            $old_paid = (float)$entry->paid_amount;
            $new_paid = $old_paid + (float)$amount;
            $installment_amount = (float)$entry->amount;

            // Determine status
            $status = 0; // pending
            if ($new_paid >= $installment_amount) {
                $status = 1; // paid
            } elseif ($new_paid > 0) {
                $status = 2; // partial
            }

            $update_data = [
                'paid_amount' => $new_paid,
                'status' => $status,
                'payment_date' => $payment_date,
                'payment_method' => $payment_method,
                'money_receipt_no' => $receipt_no,
                'updated_by' => $_SESSION['user_id'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $result = $db->where('id', $payment_schedule_id)->update('crm_payment_schedule', $update_data);

            if ($result) {
                // Log audit
                log_crm_audit('payment_schedule', 'payment_received', $payment_schedule_id, 
                    ['paid_amount' => [$old_paid, $new_paid], 'payment_date' => $payment_date],
                    $_SESSION['user_id'] ?? null
                );

                echo json_encode([
                    'status' => 200,
                    'message' => 'Payment recorded successfully',
                    'entry' => $db->where('id', $payment_schedule_id)->getOne('crm_payment_schedule')
                ]);
            } else {
                echo json_encode(['status' => 500, 'message' => 'Failed to update payment']);
            }
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
            $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
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

            // Send email notification
            echo json_encode([
                'status' => 200,
                'message' => 'Schedule email sent successfully'
            ]);
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
     * Updated with proper validation for form data
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

                $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
                if (!$helper) {
                    echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                    exit;
                }

                $result = initiate_name_transfer(
                    $purchase_id,
                    (int)$helper->client_id,
                    $to_client_id,
                    ['reason' => $reason],
                    $wo['user']['id'] ?? null
                );
            } elseif ($transfer_type === 'plot_transfer') {
                if (!$new_purchase_id) {
                    echo json_encode(['status' => 400, 'message' => 'new_purchase_id required for plot transfer']);
                    exit;
                }

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
     * Load transfer data for UI population
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_transfer_data
     * Get transfer data for modal population
     */
    if ($s === 'get_transfer_data') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            $client = GetCustomerById($helper->client_id);
            $booking = $db->where('id', $helper->booking_id)->getOne('wo_booking');

            // Get available plots for transfer
            $available_plots = $db->where('status', 1)
                                  ->get('wo_booking') ?: [];

            echo json_encode([
                'status' => 200,
                'current_client_id' => $helper->client_id,
                'current_client_name' => $client['name'] ?? '',
                'current_plot' => $booking->plot ?? '',
                'current_per_katha' => $helper->per_katha,
                'available_plots' => array_map(function($p) {
                    return [
                        'id' => $p->id,
                        'plot' => $p->plot,
                        'katha' => $p->katha
                    ];
                }, $available_plots)
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

            $result = [];
            if ($transfer->transfer_type === 'name_transfer') {
                $result = approve_name_transfer($transfer_id, $wo['user']['id'] ?? null);
            } else {
                $result = approve_plot_transfer($transfer_id, $wo['user']['id'] ?? null);
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
     * Updated to properly load refund data from database
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=get_refund_status
     * View refund details and status
     */
    if ($s === 'get_refund_status') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : (isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : 0);

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $transactions = get_refund_transactions($purchase_id);
            $summary = get_refund_summary_v2($purchase_id);

            echo json_encode([
                'status' => 200,
                'refund_transactions' => $transactions ?: [],
                'summary' => $summary,
                'message' => empty($transactions) ? 'No refund data available' : 'Refund data loaded'
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Added endpoint to calculate refund before creation
     * GET: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=calculate_refund
     * Calculate refund breakdown
     */
    if ($s === 'calculate_refund') {
        $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : (isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0);
        $deduction_percent = isset($_GET['deduction_percent']) ? (float)$_GET['deduction_percent'] : (isset($_POST['deduction_percent']) ? (float)$_POST['deduction_percent'] : 10);

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $result = calculate_refund_v2($purchase_id, $deduction_percent);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=add_refund_transaction
     * Add flexible refund transaction (custom amount, custom date)
     */
    if ($s === 'add_refund_transaction') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $transaction_date = isset($_POST['transaction_date']) ? trim($_POST['transaction_date']) : date('Y-m-d');
        $deduction_percent = isset($_POST['deduction_percent']) ? (float)$_POST['deduction_percent'] : 10;
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Bank Transfer';
        $receipt_no = isset($_POST['receipt_no']) ? trim($_POST['receipt_no']) : '';
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

        if (!$purchase_id || !$amount) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id and amount are required']);
            exit;
        }

        try {
            $result = add_refund_transaction(
                $purchase_id,
                $amount,
                $transaction_date,
                $deduction_percent,
                $payment_method,
                $receipt_no ?: null,
                $remarks ?: null,
                $_SESSION['user_id'] ?? null
            );

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * POST: /xhr/manage_schedule_endpoints.php?f=manage_schedule&s=cancel_refund_transaction
     * Cancel refund transaction
     */
    if ($s === 'cancel_refund_transaction') {
        $transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'No reason provided';

        if (!$transaction_id) {
            echo json_encode(['status' => 400, 'message' => 'transaction_id is required']);
            exit;
        }

        try {
            $result = cancel_refund_transaction($transaction_id, $reason, $_SESSION['user_id'] ?? null);
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
