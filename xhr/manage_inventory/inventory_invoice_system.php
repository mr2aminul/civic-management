<?php
/**
 * Complete Invoice System API
 * Handles invoices, money receipts, overpayment credits, and payment schedule updates
 * Note: Uses global $db (MysqliDb) and $wo
 */

header('Content-Type: application/json');
global $db, $wo, $sqlConnect;

$s = isset($_GET['s']) ? trim($_GET['s']) : (isset($_POST['s']) ? trim($_POST['s']) : '');
$user_id = $wo['user']['user_id'] ?? null;
$client_id = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
$purchase_id = intval($_POST['purchase_id'] ?? $_GET['purchase_id'] ?? 0);

if (!$user_id) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    switch ($s) {
        case 'create_invoice':
            createInvoice($client_id, $purchase_id);
            break;

        case 'get_invoices':
            getInvoices($client_id, $purchase_id);
            break;

        case 'record_payment':
            recordPayment($client_id, $purchase_id, $user_id);
            break;

        case 'get_credits':
            getOverpaymentCredits($client_id, $purchase_id);
            break;

        case 'apply_credit':
            applyCredit($user_id);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ========================================
// FUNCTION: Create Invoice
// ========================================
function createInvoice($client_id, $purchase_id) {
    global $db, $user_id, $wo;

    if (!$client_id || !$purchase_id) {
        throw new Exception('Client ID and Purchase ID required');
    }

    $payment_schedule_id = intval($_POST['payment_schedule_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? date('Y-m-d'));
    $invoice_type = trim($_POST['invoice_type'] ?? 'installment');

    if ($amount <= 0) {
        throw new Exception('Invalid amount');
    }

    // Generate invoice number
    $today = date('Y-m-d');
    $count = (int)$db->where('DATE(created_at)', $today, '=')->getValue('crm_invoices', 'COUNT(*) as cnt');
    $invoice_number = 'INV-' . date('Ym') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

    $invoice_data = [
        'invoice_number' => $invoice_number,
        'purchase_id' => $purchase_id,
        'client_id' => $client_id,
        'invoice_type' => $invoice_type,
        'payment_schedule_id' => $payment_schedule_id ?: null,
        'description' => $description,
        'invoice_date' => $today,
        'due_date' => $due_date,
        'amount' => $amount,
        'paid_amount' => 0,
        'remaining_amount' => $amount,
        'status' => 'draft',
        'created_by' => $wo['user']['id'] ?? null,
        'updated_by' => $wo['user']['id'] ?? null
    ];

    $invoice_id = $db->insert('crm_invoices', $invoice_data);

    // Log audit trail
    logAuditTrail($client_id, $purchase_id, 'invoice_create', 'invoice',
        "Invoice created: $invoice_number for $" . number_format($amount, 2),
        null, json_encode(['invoice_id' => $invoice_id, 'amount' => $amount]),
        'crm_invoices', $wo['user']['id'] ?? null);

    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice_id' => (int)$invoice_id,
        'invoice_number' => $invoice_number
    ]);
}

// ========================================
// FUNCTION: Get Invoices
// ========================================
function getInvoices($client_id, $purchase_id) {
    global $db;

    $db->where('client_id', $client_id);

    if ($purchase_id > 0) {
        $db->where('purchase_id', $purchase_id);
    }

    $db->orderBy('created_at', 'DESC');
    $invoices = $db->get('crm_invoices');

    if (!$invoices) {
        $invoices = [];
    }

    echo json_encode([
        'success' => true,
        'invoices' => $invoices,
        'count' => count($invoices)
    ]);
}

// ========================================
// FUNCTION: Record Payment (Handle Overpayment)
// ========================================
function recordPayment($client_id, $purchase_id, $user_id) {
    global $db, $wo, $sqlConnect;

    $invoice_ids = $_POST['invoice_ids'] ?? '';
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $transaction_reference = trim($_POST['transaction_reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($amount_paid <= 0) {
        throw new Exception('Invalid payment amount');
    }

    // Start transaction using raw mysqli
    mysqli_begin_transaction($sqlConnect);

    try {
        // Generate receipt number
        $today = date('Y-m-d');
        $count = (int)$db->where('DATE(created_at)', $today, '=')->getValue('crm_money_receipts', 'COUNT(*) as cnt');
        $receipt_number = 'MR-' . date('Ym') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        // Parse invoice IDs
        $invoice_list = [];
        if (strpos($invoice_ids, '[') === 0) {
            $invoice_list = json_decode($invoice_ids, true);
        } else {
            $invoice_list = array_map('intval', explode(',', $invoice_ids));
        }

        $remaining_payment = $amount_paid;
        $invoices_paid = [];

        // Apply payment to invoices
        foreach ($invoice_list as $inv_id) {
            $inv_id = is_array($inv_id) ? $inv_id['id'] : intval($inv_id);

            $db->where('id', $inv_id);
            $db->where('client_id', $client_id);
            $invoice = $db->getOne('crm_invoices');

            if (!$invoice) continue;

            $amount_to_pay = min($remaining_payment, floatval($invoice->remaining_amount));

            if ($amount_to_pay > 0) {
                $new_paid = floatval($invoice->paid_amount) + $amount_to_pay;
                $new_remaining = floatval($invoice->remaining_amount) - $amount_to_pay;
                $new_status = $new_remaining == 0 ? 'paid' : 'partial';

                $db->where('id', $inv_id);
                $db->update('crm_invoices', [
                    'paid_amount' => $new_paid,
                    'remaining_amount' => $new_remaining,
                    'status' => $new_status,
                    'updated_by' => $wo['user']['id'] ?? null
                ]);

                $invoices_paid[] = [
                    'invoice_id' => $inv_id,
                    'amount_applied' => $amount_to_pay,
                    'payment_date' => $payment_date
                ];

                $remaining_payment -= $amount_to_pay;
            }
        }

        // Create money receipt
        $receipt_data = [
            'receipt_number' => $receipt_number,
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'receipt_date' => $today,
            'payment_date' => $payment_date,
            'amount_paid' => $amount_paid,
            'payment_method' => $payment_method,
            'transaction_reference' => $transaction_reference,
            'invoices_paid' => json_encode($invoices_paid),
            'notes' => $notes,
            'created_by' => $wo['user']['id'] ?? null,
            'updated_by' => $wo['user']['id'] ?? null
        ];

        $receipt_id = $db->insert('crm_money_receipts', $receipt_data);

        // Handle overpayment credit if any
        if ($remaining_payment > 0.01) {
            // Check if table exists
            try {
                $credit_data = [
                    'purchase_id' => $purchase_id,
                    'client_id' => $client_id,
                    'receipt_id' => $receipt_id,
                    'credit_amount' => $remaining_payment,
                    'remaining_credit' => $remaining_payment,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $credit_id = $db->insert('crm_overpayment_credits', $credit_data);

                // Log credit created
                logAuditTrail($client_id, $purchase_id, 'payment', 'payment',
                    "Overpayment credit created: $" . number_format($remaining_payment, 2),
                    null, json_encode(['credit_id' => $credit_id, 'amount' => $remaining_payment]),
                    'crm_overpayment_credits', $wo['user']['id'] ?? null);
            } catch (Exception $e) {
                // Table may not exist, continue without credit
            }
        }

        // Update payment schedule status if linked
        foreach ($invoice_list as $inv_id) {
            $inv_id = is_array($inv_id) ? $inv_id['id'] : intval($inv_id);

            $db->where('purchase_id', $purchase_id);
            $db->where('related_invoice_ids', '%"' . $inv_id . '"%', 'LIKE');
            $schedule = $db->getOne('crm_payment_schedule');

            if ($schedule) {
                $db->where('id', $schedule->id);
                $db->update('crm_payment_schedule', [
                    'status' => 1,
                    'paid_amount' => $amount_paid,
                    'payment_date' => date('Y-m-d H:i:s'),
                    'updated_by' => $wo['user']['id'] ?? null
                ]);
            }
        }

        // Log audit trail for payment
        logAuditTrail($client_id, $purchase_id, 'payment', 'payment',
            "Payment recorded: $" . number_format($amount_paid, 2) . " via " . $receipt_number,
            json_encode(['previous_paid' => 0]),
            json_encode(['receipt_id' => $receipt_id, 'receipt_number' => $receipt_number, 'amount_paid' => $amount_paid]),
            'crm_money_receipts', $wo['user']['id'] ?? null);

        mysqli_commit($sqlConnect);

        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'receipt_id' => (int)$receipt_id,
            'receipt_number' => $receipt_number,
            'overpayment_credit' => $remaining_payment
        ]);

    } catch (Exception $e) {
        mysqli_rollback($sqlConnect);
        throw $e;
    }
}

// ========================================
// FUNCTION: Get Overpayment Credits
// ========================================
function getOverpaymentCredits($client_id, $purchase_id) {
    global $db;

    $db->where('client_id', $client_id);
    $db->where('status', 'active');

    if ($purchase_id > 0) {
        $db->where('purchase_id', $purchase_id);
    }

    $db->orderBy('created_at', 'DESC');
    $credits = $db->get('crm_overpayment_credits');

    if (!$credits) {
        $credits = [];
    }

    $total_credit = array_reduce($credits, function($sum, $item) {
        return $sum + floatval($item->remaining_credit ?? 0);
    }, 0);

    echo json_encode([
        'success' => true,
        'credits' => $credits,
        'total_available_credit' => $total_credit
    ]);
}

// ========================================
// FUNCTION: Apply Credit to Payment Schedule
// ========================================
function applyCredit($user_id) {
    global $db, $wo, $sqlConnect;

    $credit_id = intval($_POST['credit_id'] ?? 0);
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    $amount_to_apply = floatval($_POST['amount_to_apply'] ?? 0);

    if ($credit_id <= 0 || $schedule_id <= 0 || $amount_to_apply <= 0) {
        throw new Exception('Invalid parameters');
    }

    mysqli_begin_transaction($sqlConnect);

    try {
        // Get credit
        $db->where('id', $credit_id);
        $credit = $db->getOne('crm_overpayment_credits');

        if (!$credit) throw new Exception('Credit not found');
        if ($amount_to_apply > floatval($credit->remaining_credit)) throw new Exception('Insufficient credit');

        // Get schedule
        $db->where('id', $schedule_id);
        $schedule = $db->getOne('crm_payment_schedule');

        if (!$schedule) throw new Exception('Schedule not found');

        // Apply credit
        $new_remaining = floatval($credit->remaining_credit) - $amount_to_apply;
        $applied_data = json_decode($credit->applied_to ?? '[]', true);
        $applied_data[] = [
            'schedule_id' => $schedule_id,
            'amount_applied' => $amount_to_apply,
            'applied_date' => date('Y-m-d')
        ];

        $db->where('id', $credit_id);
        $db->update('crm_overpayment_credits', [
            'remaining_credit' => $new_remaining,
            'applied_to' => json_encode($applied_data)
        ]);

        // Update schedule paid amount
        $new_paid_amount = floatval($schedule->paid_amount ?? 0) + $amount_to_apply;
        $new_status = $new_paid_amount >= floatval($schedule->installment_amount) ? 1 : 2;

        $db->where('id', $schedule_id);
        $db->update('crm_payment_schedule', [
            'paid_amount' => $new_paid_amount,
            'status' => $new_status,
            'updated_by' => $wo['user']['id'] ?? null
        ]);

        // Log audit trail
        logAuditTrail($schedule->client_id, $schedule->purchase_id, 'payment', 'payment',
            "Overpayment credit applied: $" . number_format($amount_to_apply, 2) . " to schedule #" . $schedule->installment_number,
            json_encode(['previous_paid' => $schedule->paid_amount]),
            json_encode(['new_paid' => $new_paid_amount]),
            'crm_overpayment_credits,crm_payment_schedule', $wo['user']['id'] ?? null);

        mysqli_commit($sqlConnect);

        echo json_encode([
            'success' => true,
            'message' => 'Credit applied successfully',
            'remaining_credit' => $new_remaining
        ]);

    } catch (Exception $e) {
        mysqli_rollback($sqlConnect);
        throw $e;
    }
}

// ========================================
// HELPER: Log Audit Trail
// ========================================
function logAuditTrail($client_id, $purchase_id, $action_type, $action_category, $description, $before_value, $after_value, $affected_tables, $user_id) {
    global $db;

    try {
        $audit_data = [
            'client_id' => $client_id,
            'purchase_id' => $purchase_id,
            'action_type' => $action_type,
            'action_category' => $action_category,
            'description' => $description,
            'before_value' => $before_value,
            'after_value' => $after_value,
            'affected_tables' => $affected_tables,
            'performed_by' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'status' => 'completed'
        ];

        $db->insert('crm_audit_trail', $audit_data);
    } catch (Exception $e) {
        // Silently fail if audit logging fails
        error_log('Audit trail insert failed: ' . $e->getMessage());
    }
}
?>
