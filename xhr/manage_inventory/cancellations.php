<?php
    // Cancellations
    if ($s == 'process_cancel_plot' || $s == 'process_cancel_purchase') {
        header('Content-Type: application/json; charset=utf-8');
        global $db, $wo;
    
        // Accept POST
        $purchase_id = isset($_POST['purchase_id']) ? (int) $_POST['purchase_id'] : 0;
        $reason = isset($_POST['reason']) ? trim((string) $_POST['reason']) : '';
        $fee_mode = isset($_POST['fee_mode']) ? trim((string) $_POST['fee_mode']) : 'none';
        $fee_value = isset($_POST['fee_value']) ? floatval($_POST['fee_value']) : 0.0;
        $initiate_refund = isset($_POST['initiate_refund']) ? (int) $_POST['initiate_refund'] : 0;
    
        // Basic validation
        if ($purchase_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
            exit();
        }
        if ($reason === '' || mb_strlen($reason) < 3) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'Cancellation reason required (min 3 chars)']);
            exit();
        }
    
        // whitelist fee modes
        $allowed_fee_modes = ['none', 'fixed', 'percent'];
        if (!in_array($fee_mode, $allowed_fee_modes, true)) $fee_mode = 'none';
    
        try {
            // Fetch helper/purchase
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                http_response_code(404);
                echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
                exit();
            }
    
            // booking id sanity
            $booking_id = isset($helper->booking_id) ? intval($helper->booking_id) : 0;
            if ($booking_id <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Related booking id missing']);
                exit();
            }
    
            // compute total paid (sum in SQL is more robust)
            $total_paid = 0.0;
            try {
                $row = $db->rawQueryOne("SELECT COALESCE(SUM(paid_amount),0) AS s FROM `crm_payment_schedule` WHERE purchase_id = ? AND status = 1", [$purchase_id]);
                if ($row && isset($row->s)) $total_paid = floatval($row->s);
            } catch (Exception $e) {
                // fallback: compute by iterating if rawQueryOne fails
                $paid_schedule = $db->where('purchase_id', $purchase_id)->where('status', 1)->get('crm_payment_schedule');
                foreach ($paid_schedule as $ps) {
                    $total_paid += floatval($ps->paid_amount ?? 0);
                }
            }
    
            // compute cancellation fee
            $cancellation_fee = 0.0;
            if ($fee_mode === 'fixed') {
                $cancellation_fee = max(0.0, floatval($fee_value));
            } elseif ($fee_mode === 'percent') {
                $pct = floatval($fee_value);
                $cancellation_fee = ($total_paid * $pct) / 100.0;
            }
            // clamp
            if ($cancellation_fee < 0) $cancellation_fee = 0.0;
            // refundable amount cannot be negative
            $refundable_amount = max(0.0, $total_paid - $cancellation_fee);
    
            // 3. Create Pending Change Request
            $change_data = [
                'reason' => $reason,
                'fee_mode' => $fee_mode,
                'fee_value' => $fee_value,
                'initiate_refund' => $initiate_refund,
                'cancellation_fee' => $cancellation_fee,
                'refundable_amount' => $refundable_amount,
                'total_paid' => $total_paid
            ];

            $pending_data = [
                'change_type' => 'cancel',
                'purchase_id' => $purchase_id,
                'client_id' => isset($helper->client_id) ? intval($helper->client_id) : 0,
                'requested_by' => $wo['user']['id'] ?? 0,
                'request_date' => date('Y-m-d H:i:s'),
                'request_reason' => $reason,
                'change_data_json' => json_encode($change_data, JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $pending_id = $db->insert('crm_pending_changes', $pending_data);

            if ($pending_id) {
                // Update purchase to show pending flag
                $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, ['has_pending_changes' => 1]);

                // Log activity
                if ($db->tableExists('crm_audit_trail')) {
                    $db->insert('crm_audit_trail', [
                        'client_id' => $pending_data['client_id'],
                        'purchase_id' => $purchase_id,
                        'action_category' => 'purchase',
                        'action_type' => 'cancel_requested',
                        'action_description' => "Cancellation requested. Reason: $reason",
                        'performed_by' => $wo['user']['id'] ?? 0,
                        'performed_at' => date('Y-m-d H:i:s'),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
                    ]);
                }

                http_response_code(200);
                echo json_encode([
                    'status' => 200,
                    'message' => 'Cancellation request submitted for approval',
                    'pending_change_id' => $pending_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 500, 'message' => 'Failed to submit cancellation request']);
            }
            exit();

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
            exit();
        }
    }

    // ------------------ CANCEL ALL INVENTORY (cancel booking + all helpers + clear file_num) ------------------
    if ($s === 'cancel_all_inventory') {
    
        // accept booking id via POST 'id' or 'booking_id', or GET fallback
        $booking_id = isset($_POST['id']) ? (int) $_POST['id']
                    : (isset($_POST['booking_id']) ? (int) $_POST['booking_id']
                    : (isset($_GET['id']) ? (int) $_GET['id']
                    : (isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0)));
    
        // accept cancel date via POST 'cancel_date' or 'date', or GET fallback
        $raw_date = isset($_POST['cancel_date']) ? trim((string) $_POST['cancel_date'])
                  : (isset($_POST['date']) ? trim((string) $_POST['date'])
                  : (isset($_GET['cancel_date']) ? trim((string) $_GET['cancel_date'])
                  : (isset($_GET['date']) ? trim((string) $_GET['date']) : '')));
    
        // Normalize cancel_date:
        // - $cancel_date_ts will be integer timestamp or null
        // - $cancel_date_short will be 'Y-m-d' string or null
        $cancel_date_ts = null;
        $cancel_date_short = null;
        if ($raw_date !== '') {
            // accept YYYY-MM-DD or general human formats (via strtotime)
            $d = DateTime::createFromFormat('Y-m-d', $raw_date);
            if ($d && $d->format('Y-m-d') === $raw_date) {
                $cancel_date_short = $raw_date;
                $cancel_date_ts = (int) $d->getTimestamp();
            } else {
                $ts = strtotime($raw_date);
                if ($ts !== false) {
                    $cancel_date_ts = (int) $ts;
                    $cancel_date_short = date('Y-m-d', $cancel_date_ts);
                } else {
                    // invalid date string — treat as not provided (optional: return 400 instead)
                    $cancel_date_ts = null;
                    $cancel_date_short = null;
                }
            }
        }
    
        if ($booking_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'Missing or invalid booking id.']);
            exit;
        }
    
        // fetch booking
        $booking = $db->where('id', $booking_id)->getOne(T_BOOKING);
        if (!$booking) {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Booking not found.']);
            exit;
        }
    
        // begin transaction
        $db->startTransaction();
    
        $now = time();
    
        // 1) update booking: set status = 4 (Cancelled) and clear file_num
        $bookingUpdate = [
            'status'   => 4,
            'file_num' => ''
        ];

        
        $okBooking = $db->where('id', $booking_id)->update(T_BOOKING, $bookingUpdate);
        if ($okBooking === false) {
            $db->rollback();
            http_response_code(500);
            echo json_encode(['status' => 500, 'message' => 'Failed to update booking status/file number.']);
            exit();
        }
    
        // 2) fetch all existing helpers for this booking (to collect IDs for response/logging)
        $helpersBefore = $db->where('booking_id', $booking_id)->get(T_BOOKING_HELPER);
        $helperIds = [];
        if ($helpersBefore && is_array($helpersBefore)) {
            foreach ($helpersBefore as $h) {
                $hid = isset($h->id) ? (int)$h->id : 0;
                if ($hid > 0) $helperIds[] = $hid;
            }
        }
    
        // 3) update all helpers for this booking -> set status = 4 (Cancelled) and update time
        $helperUpdate = [
            'status' => 4
        ];
        // include cancel_date_ts if provided (store numeric timestamp)
        if (!is_null($cancel_date_ts)) {
            $helperUpdate['cancel_date'] = $cancel_date_ts;
        }
    
        $okHelpers = $db->where('booking_id', $booking_id)->update(T_BOOKING_HELPER, $helperUpdate);
        if ($okHelpers === false) {
            $db->rollback();
            http_response_code(500);
            echo json_encode(['status' => 500, 'message' => 'Failed to update booking helpers.']);
            exit();
        }
    
        // commit transaction
        $db->commit();
    
        // optional logging
        $logUser = 'User #' . ($wo['user']['id'] ?? 'unknown');
        logActivity('booking', 'cancel_all', "{$logUser} cancelled booking #{$booking_id} and helpers [" . implode(',', $helperIds) . "]");
    
        // success response (return both timestamp and short date if available)
        echo json_encode([
            'status' => 200,
            'message' => 'Booking and all helpers cancelled successfully.',
            'booking_id' => $booking_id,
            'cancel_date' => $cancel_date_short,
            'cancel_date_ts' => $cancel_date_ts,
            'helpers_cancelled' => $helperIds,
            'booking_update_result' => $okBooking,
            'helpers_update_result' => $okHelpers
        ]);
        exit;
    }

    /**
     * Updated to properly load refund data from database
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
