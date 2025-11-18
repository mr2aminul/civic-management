<?php
/**
 * CONSOLIDATED INVOICE MANAGEMENT ENDPOINTS
 * Handles all invoice operations, payment recording, money receipts, and overpayment credits
 * Consolidated from: advanced.php, inventory_invoices.php, inventory_invoice_system.php, inventory_complete.php
 */

global $db, $wo, $sqlConnect;

// Get all invoices for a purchase (with optional filters)
if ($s === 'get_invoices' || $s === 'get_invoices_for_purchase' || $s === 'get_invoices_list') {
    header('Content-Type: application/json; charset=utf-8');

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $from_date = isset($_POST['from_date']) ? $_POST['from_date'] : '';
    $to_date = isset($_POST['to_date']) ? $_POST['to_date'] : '';
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';

    if ($purchase_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
        exit;
    }

    try {
        $db->where('purchase_id', $purchase_id);

        if ($status !== '') {
            $db->where('status', $status);
        }

        if ($from_date) {
            $db->where('invoice_date', $from_date, '>=');
        }

        if ($to_date) {
            $db->where('invoice_date', $to_date, '<=');
        }

        if ($search) {
            $db->where('(invoice_number LIKE ? OR money_receipt_no LIKE ?)',
                ['%' . $search . '%', '%' . $search . '%'], 'OR');
        }

        $db->orderBy('invoice_date', 'DESC');
        $invoices = $db->get('crm_invoices');

        $result = [];
        $summary = [
            'total_amount' => 0,
            'total_paid' => 0,
            'total_balance' => 0,
            'pending_count' => 0,
            'overdue_count' => 0
        ];

        if (!empty($invoices)) {
            foreach ($invoices as $inv) {
                $installment_ids = $inv->installment_ids ? json_decode($inv->installment_ids, true) : [];
                $amount = floatval($inv->total_amount ?? 0);
                $paid = floatval($inv->paid_amount ?? 0);
                $balance = floatval($inv->balance_amount ?? 0);

                $result[] = [
                    'id' => (int)$inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $inv->invoice_date,
                    'due_date' => $inv->due_date,
                    'total_amount' => $amount,
                    'paid_amount' => $paid,
                    'balance_amount' => $balance,
                    'status' => $inv->status,
                    'money_receipt_no' => $inv->money_receipt_no,
                    'receipt_generated_date' => $inv->receipt_generated_date,
                    'installment_count' => count($installment_ids),
                    'installment_ids' => $installment_ids,
                    'notes' => $inv->notes,
                    'created_by' => $inv->created_by,
                    'created_at' => $inv->created_at
                ];

                $summary['total_amount'] += $amount;
                $summary['total_paid'] += $paid;
                $summary['total_balance'] += $balance;

                if (in_array($inv->status, ['draft', 'issued', 'partial'])) {
                    $summary['pending_count']++;
                }

                if (in_array($inv->status, ['draft', 'issued']) &&
                    strtotime($inv->due_date ?? 'now') < time()) {
                    $summary['overdue_count']++;
                }
            }
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'invoices' => $result,
            'count' => count($result),
            'summary' => $summary
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Create new invoice from selected installments
if ($s === 'create_invoice' || $s === 'create_invoice_from_installments') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $installment_ids = isset($_POST['installment_ids']) ? json_decode($_POST['installment_ids'], true) : [];
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+30 days'));
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $invoice_type = isset($_POST['invoice_type']) ? trim($_POST['invoice_type']) : 'installment';

    if ($purchase_id <= 0 || empty($installment_ids)) {
        echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        // Calculate total from selected installments
        $total_amount = 0;
        $schedule_items = $db->where('purchase_id', $purchase_id)->get('crm_payment_schedule');

        foreach ($schedule_items as $item) {
            if (in_array((int)$item->id, $installment_ids)) {
                $total_amount += floatval($item->installment_amount ?? 0);
            }
        }

        if ($total_amount <= 0) {
            echo json_encode(['status' => 400, 'message' => 'No valid installments selected']);
            exit;
        }

        // Generate unique invoice number
        $existing_count = (int)$db->where('purchase_id', $purchase_id)->getValue('crm_invoices', 'COUNT(*) as cnt');
        $invoice_number = 'INV-' . $purchase_id . '-' . str_pad(($existing_count + 1), 3, '0', STR_PAD_LEFT);

        // Insert invoice
        $invoice_data = [
            'purchase_id' => $purchase_id,
            'client_id' => (int)$helper->client_id,
            'invoice_number' => $invoice_number,
            'invoice_type' => $invoice_type,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $due_date,
            'total_amount' => $total_amount,
            'paid_amount' => 0,
            'balance_amount' => $total_amount,
            'installment_ids' => json_encode($installment_ids),
            'status' => 'draft',
            'notes' => $notes,
            'created_by' => $wo['user']['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $invoice_id = $db->insert('crm_invoices', $invoice_data);

        // Update schedule items to reference this invoice
        foreach ($installment_ids as $inst_id) {
            $db->where('id', (int)$inst_id)->update('crm_payment_schedule', [
                'invoice_id' => $invoice_id
            ]);
        }

        // Log audit
        $db->insert('crm_audit_trail', [
            'purchase_id' => $purchase_id,
            'client_id' => (int)$helper->client_id,
            'audit_category' => 'invoice',
            'action_type' => 'invoice_created',
            'action_description' => 'Invoice created: ' . $invoice_number,
            'new_value' => json_encode($invoice_data),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $wo['user']['id'] ?? null,
            'created_by_name' => $wo['user']['name'] ?? 'Admin'
        ]);

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => 'Invoice created successfully',
            'invoice_id' => (int)$invoice_id,
            'invoice_number' => $invoice_number,
            'total_amount' => $total_amount
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Update invoice status
if ($s === 'update_invoice_status') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($invoice_id <= 0 || !in_array($new_status, ['draft', 'issued', 'partial', 'paid', 'cancelled', 'overdue'])) {
        echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        $invoice = $db->where('id', $invoice_id)->getOne('crm_invoices');
        if (!$invoice) {
            echo json_encode(['status' => 404, 'message' => 'Invoice not found']);
            exit;
        }

        $old_status = $invoice->status;

        // Update invoice
        $db->where('id', $invoice_id)->update('crm_invoices', [
            'status' => $new_status,
            'updated_by' => $wo['user']['id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Log audit
        $db->insert('crm_audit_trail', [
            'purchase_id' => (int)$invoice->purchase_id,
            'client_id' => (int)$invoice->client_id,
            'audit_category' => 'invoice',
            'action_type' => 'status_changed',
            'action_description' => "Invoice status changed from $old_status to $new_status",
            'old_value' => json_encode(['status' => $old_status]),
            'new_value' => json_encode(['status' => $new_status]),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $wo['user']['id'] ?? null,
            'created_by_name' => $wo['user']['name'] ?? 'Admin'
        ]);

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => 'Invoice status updated',
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Record payment on invoice with overpayment handling and schedule sync
if ($s === 'record_invoice_payment' || $s === 'record_payment') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $payment_amount = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0;
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Cash';
    $money_receipt_no = isset($_POST['money_receipt_no']) ? trim($_POST['money_receipt_no']) : '';

    if ($invoice_id <= 0 || $payment_amount <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid payment amount']);
        exit;
    }

    try {
        $invoice = $db->where('id', $invoice_id)->getOne('crm_invoices');
        if (!$invoice) {
            echo json_encode(['status' => 404, 'message' => 'Invoice not found']);
            exit;
        }

        $invoice_amount = floatval($invoice->total_amount ?? 0);
        $current_paid = floatval($invoice->paid_amount ?? 0);
        $new_paid = $current_paid + $payment_amount;
        $new_balance = max(0, $invoice_amount - $new_paid);
        $overpayment_amount = max(0, $new_paid - $invoice_amount);

        // Determine status
        if ($new_paid >= $invoice_amount) {
            $new_status = 'paid';
        } else if ($new_paid > 0) {
            $new_status = 'partial';
        } else {
            $new_status = 'draft';
        }

        // Update invoice
        $db->where('id', $invoice_id)->update('crm_invoices', [
            'paid_amount' => $new_paid,
            'balance_amount' => $new_balance,
            'status' => $new_status,
            'payment_date' => $payment_date,
            'payment_method' => $payment_method,
            'money_receipt_no' => $money_receipt_no,
            'updated_by' => $wo['user']['id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Sync with payment schedule - mark related installments
        $installment_ids = $invoice->installment_ids ? json_decode($invoice->installment_ids, true) : [];

        if (!empty($installment_ids)) {
            // Get payment proportion for this invoice
            $payment_proportion = $payment_amount / $invoice_amount;

            foreach ($installment_ids as $inst_id) {
                $schedule = $db->where('id', (int)$inst_id)->getOne('crm_payment_schedule');
                if ($schedule) {
                    $inst_payment = floatval($schedule->installment_amount ?? 0) * $payment_proportion;
                    $schedule_paid = floatval($schedule->paid_amount ?? 0) + $inst_payment;

                    $db->where('id', (int)$inst_id)->update('crm_payment_schedule', [
                        'paid_amount' => $schedule_paid,
                        'payment_date' => $payment_date,
                        'payment_method' => $payment_method,
                        'money_receipt_no' => $money_receipt_no,
                        'overpayment_amount' => ($overpayment_amount > 0) ? ($inst_payment * $overpayment_amount / $payment_amount) : 0,
                        'status' => ($schedule_paid >= floatval($schedule->installment_amount ?? 0)) ? 1 : 2,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }

        // Handle overpayment credit if exists
        if ($overpayment_amount > 0.01) {
            try {
                $credit_data = [
                    'purchase_id' => (int)$invoice->purchase_id,
                    'client_id' => (int)$invoice->client_id,
                    'credit_amount' => $overpayment_amount,
                    'applied_amount' => 0,
                    'source_invoice_id' => $invoice_id,
                    'reason' => 'Overpayment from invoice ' . $invoice->invoice_number,
                    'created_by' => $wo['user']['id'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $db->insert('crm_payment_credits', $credit_data);
            } catch (Exception $e) {
                // Table may not exist, continue without credit
            }
        }

        // Log audit
        $db->insert('crm_audit_trail', [
            'purchase_id' => (int)$invoice->purchase_id,
            'client_id' => (int)$invoice->client_id,
            'audit_category' => 'invoice',
            'action_type' => 'payment_recorded',
            'action_description' => "Payment recorded: " . number_format($payment_amount, 2) . " on invoice " . $invoice->invoice_number,
            'old_value' => json_encode(['paid_amount' => $current_paid, 'status' => $invoice->status]),
            'new_value' => json_encode(['paid_amount' => $new_paid, 'status' => $new_status]),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $wo['user']['id'] ?? null,
            'created_by_name' => $wo['user']['name'] ?? 'Admin'
        ]);

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => 'Payment recorded successfully',
            'new_paid_amount' => $new_paid,
            'new_balance' => $new_balance,
            'invoice_status' => $new_status,
            'overpayment_amount' => $overpayment_amount,
            'overpayment_distributed' => $overpayment_amount > 0
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Generate money receipt from invoice
if ($s === 'generate_money_receipt' || $s === 'auto_generate_receipt') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Cash';

    if ($invoice_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid invoice ID']);
        exit;
    }

    try {
        $invoice = $db->where('id', $invoice_id)->getOne('crm_invoices');
        if (!$invoice || floatval($invoice->paid_amount ?? 0) <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invoice has no payments to receipt']);
            exit;
        }

        // Check if receipt already exists
        if (!empty($invoice->money_receipt_no)) {
            echo json_encode([
                'status' => 200,
                'message' => 'Receipt already exists',
                'receipt_number' => $invoice->money_receipt_no
            ]);
            exit;
        }

        // Generate receipt number
        $receipt_count = (int)$db->getValue('crm_money_receipts', 'COUNT(*) as cnt');
        $receipt_number = 'MR-' . date('Ymd') . '-' . str_pad(($receipt_count + 1), 4, '0', STR_PAD_LEFT);

        // Create money receipt
        $receipt_data = [
            'receipt_number' => $receipt_number,
            'purchase_id' => (int)$invoice->purchase_id,
            'client_id' => (int)$invoice->client_id,
            'receipt_date' => date('Y-m-d'),
            'payment_date' => $invoice->payment_date ?? date('Y-m-d'),
            'amount_paid' => floatval($invoice->paid_amount ?? 0),
            'payment_method' => $payment_method,
            'invoices_paid' => json_encode([['invoice_id' => $invoice_id, 'amount_applied' => floatval($invoice->paid_amount ?? 0)]]),
            'status' => 'issued',
            'created_by' => $wo['user']['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $receipt_id = $db->insert('crm_money_receipts', $receipt_data);

        // Update invoice
        $db->where('id', $invoice_id)->update('crm_invoices', [
            'money_receipt_no' => $receipt_number,
            'receipt_generated_date' => date('Y-m-d H:i:s')
        ]);

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => 'Money receipt generated',
            'receipt_id' => (int)$receipt_id,
            'receipt_number' => $receipt_number
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get overpayment credits for a purchase
if ($s === 'get_credits' || $s === 'get_overpayment_credits') {
    header('Content-Type: application/json; charset=utf-8');

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

    if ($purchase_id <= 0 && $client_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID or Client ID required']);
        exit;
    }

    try {
        if ($client_id > 0) {
            $db->where('client_id', $client_id);
        }

        if ($purchase_id > 0) {
            $db->where('purchase_id', $purchase_id);
        }

        $db->orderBy('created_at', 'DESC');
        $credits = $db->get('crm_payment_credits');

        if (!$credits) {
            $credits = [];
        }

        $total_credit = 0;
        $total_available = 0;

        foreach ($credits as $credit) {
            $total_credit += floatval($credit->credit_amount ?? 0);
            $remaining = floatval($credit->credit_amount ?? 0) - floatval($credit->applied_amount ?? 0);
            $total_available += $remaining;
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'credits' => $credits,
            'count' => count($credits),
            'total_credit' => $total_credit,
            'total_available' => $total_available
        ]);
    } catch (Exception $e) {
        // Table may not exist
        echo json_encode([
            'status' => 200,
            'success' => true,
            'credits' => [],
            'count' => 0,
            'total_credit' => 0,
            'total_available' => 0,
            'note' => 'Credits table not available'
        ]);
    }
    exit;
}

// Apply credit to installment
if ($s === 'apply_credit') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $credit_id = isset($_POST['credit_id']) ? (int)$_POST['credit_id'] : 0;
    $installment_id = isset($_POST['installment_id']) ? (int)$_POST['installment_id'] : 0;
    $amount_to_apply = isset($_POST['amount_to_apply']) ? (float)$_POST['amount_to_apply'] : 0;

    if ($credit_id <= 0 || $installment_id <= 0 || $amount_to_apply <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
        exit;
    }

    try {
        mysqli_begin_transaction($sqlConnect);

        // Get credit
        $credit = $db->where('id', $credit_id)->getOne('crm_payment_credits');
        if (!$credit) {
            throw new Exception('Credit not found');
        }

        $remaining = floatval($credit->credit_amount ?? 0) - floatval($credit->applied_amount ?? 0);
        if ($amount_to_apply > $remaining) {
            throw new Exception('Insufficient credit balance');
        }

        // Get installment
        $schedule = $db->where('id', $installment_id)->getOne('crm_payment_schedule');
        if (!$schedule) {
            throw new Exception('Installment not found');
        }

        // Update credit
        $new_applied = floatval($credit->applied_amount ?? 0) + $amount_to_apply;
        $db->where('id', $credit_id)->update('crm_payment_credits', [
            'applied_amount' => $new_applied,
            'updated_by' => $wo['user']['id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Create application record
        $db->insert('crm_payment_credit_applications', [
            'purchase_id' => (int)$schedule->purchase_id,
            'client_id' => (int)$schedule->client_id,
            'credit_id' => $credit_id,
            'installment_id' => $installment_id,
            'applied_amount' => $amount_to_apply,
            'applied_by' => $wo['user']['id'] ?? null,
            'applied_at' => date('Y-m-d H:i:s'),
            'remarks' => 'Credit applied to installment #' . $schedule->installment_number
        ]);

        // Update installment
        $schedule_paid = floatval($schedule->paid_amount ?? 0) + $amount_to_apply;
        $db->where('id', $installment_id)->update('crm_payment_schedule', [
            'paid_amount' => $schedule_paid,
            'status' => ($schedule_paid >= floatval($schedule->installment_amount ?? 0)) ? 1 : 2,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        mysqli_commit($sqlConnect);

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => 'Credit applied successfully',
            'remaining_credit' => ($remaining - $amount_to_apply)
        ]);
    } catch (Exception $e) {
        mysqli_rollback($sqlConnect);
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

?>
