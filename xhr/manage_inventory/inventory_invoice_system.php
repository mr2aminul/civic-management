<?php
/**
 * Complete Invoice System API
 * Handles invoices, money receipts, overpayment credits, and payment schedule updates
 */

header('Content-Type: application/json');
session_start();


$user_id = $wo['user']['user_id'] ?? null;
$client_id = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
$purchase_id = intval($_POST['purchase_id'] ?? $_GET['purchase_id'] ?? 0);

if (!$user_id) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    switch ($action) {
        // ===================================
        // 1. CREATE INVOICE
        // ===================================
        case 'create_invoice':
            createInvoice($client_id, $purchase_id);
            break;

        // ===================================
        // 2. GET INVOICES FOR CLIENT/PURCHASE
        // ===================================
        case 'get_invoices':
            getInvoices($client_id, $purchase_id);
            break;

        // ===================================
        // 3. RECORD PAYMENT (CREATE MONEY RECEIPT + UPDATE SCHEDULE + HANDLE OVERPAYMENT)
        // ===================================
        case 'record_payment':
            recordPayment($client_id, $purchase_id, $user_id);
            break;

        // ===================================
        // 4. GET OVERPAYMENT CREDITS
        // ===================================
        case 'get_credits':
            getOverpaymentCredits($client_id, $purchase_id);
            break;

        // ===================================
        // 5. APPLY CREDIT TO SCHEDULE
        // ===================================
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
    global $pdo, $user_id;

    if (!$client_id || !$purchase_id) {
        throw new Exception('Client ID and Purchase ID required');
    }

    $payment_schedule_id = intval($_POST['payment_schedule_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? date('Y-m-d'));
    $invoice_type = trim($_POST['invoice_type'] ?? 'installment'); // booking_money, down_payment, installment

    if ($amount <= 0) {
        throw new Exception('Invalid amount');
    }

    // Generate invoice number
    $today = date('Y-m-d');
    $count = $pdo->query("SELECT COUNT(*) FROM crm_invoices WHERE DATE(created_at) = '$today'")->fetchColumn();
    $invoice_number = 'INV-' . date('Ym') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("
        INSERT INTO crm_invoices 
        (invoice_number, purchase_id, client_id, invoice_type, payment_schedule_id, description, 
         invoice_date, due_date, amount, paid_amount, remaining_amount, status, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'draft', ?, ?)
    ");

    $stmt->execute([
        $invoice_number, $purchase_id, $client_id, $invoice_type, 
        $payment_schedule_id ?: null, $description, $today, $due_date, 
        $amount, $amount, $user_id, $user_id
    ]);

    $invoice_id = $pdo->lastInsertId();

    // Log audit trail
    logAuditTrail($client_id, $purchase_id, 'invoice_create', 'invoice', 
        "Invoice created: $invoice_number for $" . number_format($amount, 2), 
        null, json_encode(['invoice_id' => $invoice_id, 'amount' => $amount]), 
        'crm_invoices', $user_id);

    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
}

// ========================================
// FUNCTION: Get Invoices
// ========================================
function getInvoices($client_id, $purchase_id) {
    global $pdo;

    $query = "SELECT * FROM crm_invoices WHERE client_id = ?";
    $params = [$client_id];

    if ($purchase_id > 0) {
        $query .= " AND purchase_id = ?";
        $params[] = $purchase_id;
    }

    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    global $pdo;

    $invoice_ids = $_POST['invoice_ids'] ?? ''; // Comma-separated or JSON
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $transaction_reference = trim($_POST['transaction_reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($amount_paid <= 0) {
        throw new Exception('Invalid payment amount');
    }

    $pdo->beginTransaction();

    try {
        // Generate receipt number
        $today = date('Y-m-d');
        $count = $pdo->query("SELECT COUNT(*) FROM crm_money_receipts WHERE DATE(created_at) = '$today'")->fetchColumn();
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
            
            $invoice = $pdo->query("SELECT * FROM crm_invoices WHERE id = $inv_id AND client_id = $client_id")->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) continue;

            $amount_to_pay = min($remaining_payment, $invoice['remaining_amount']);
            
            if ($amount_to_pay > 0) {
                $new_paid = $invoice['paid_amount'] + $amount_to_pay;
                $new_remaining = $invoice['remaining_amount'] - $amount_to_pay;
                $new_status = $new_remaining == 0 ? 'paid' : 'partial';

                $pdo->prepare("
                    UPDATE crm_invoices 
                    SET paid_amount = ?, remaining_amount = ?, status = ?, updated_by = ?
                    WHERE id = ?
                ")->execute([$new_paid, $new_remaining, $new_status, $user_id, $inv_id]);

                $invoices_paid[] = [
                    'invoice_id' => $inv_id,
                    'amount_applied' => $amount_to_pay,
                    'payment_date' => $payment_date
                ];

                $remaining_payment -= $amount_to_pay;
            }
        }

        // Create money receipt
        $receipt_stmt = $pdo->prepare("
            INSERT INTO crm_money_receipts 
            (receipt_number, purchase_id, client_id, receipt_date, payment_date, amount_paid, 
             payment_method, transaction_reference, invoices_paid, notes, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $receipt_stmt->execute([
            $receipt_number, $purchase_id, $client_id, $today, $payment_date, $amount_paid,
            $payment_method, $transaction_reference, json_encode($invoices_paid), $notes, $user_id, $user_id
        ]);

        $receipt_id = $pdo->lastInsertId();

        // Handle overpayment credit if any
        if ($remaining_payment > 0.01) {
            $credit_stmt = $pdo->prepare("
                INSERT INTO crm_overpayment_credits 
                (purchase_id, client_id, receipt_id, credit_amount, remaining_credit, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");

            $credit_stmt->execute([$purchase_id, $client_id, $receipt_id, $remaining_payment, $remaining_payment]);

            $credit_id = $pdo->lastInsertId();

            // Log credit created
            logAuditTrail($client_id, $purchase_id, 'payment', 'payment',
                "Overpayment credit created: $" . number_format($remaining_payment, 2),
                null, json_encode(['credit_id' => $credit_id, 'amount' => $remaining_payment]),
                'crm_overpayment_credits', $user_id);
        }

        // Update payment schedule status if linked
        foreach ($invoice_list as $inv_id) {
            $inv_id = is_array($inv_id) ? $inv_id['id'] : intval($inv_id);
            $schedule = $pdo->query("
                SELECT id FROM crm_payment_schedule 
                WHERE purchase_id = $purchase_id AND related_invoice_ids LIKE '%\"$inv_id\"%' LIMIT 1
            ")->fetch(PDO::FETCH_ASSOC);

            if ($schedule) {
                $pdo->prepare("
                    UPDATE crm_payment_schedule 
                    SET status = 1, paid_amount = ?, payment_date = NOW(), updated_by = ?
                    WHERE id = ?
                ")->execute([$amount_paid, $user_id, $schedule['id']]);
            }
        }

        // Log audit trail for payment
        logAuditTrail($client_id, $purchase_id, 'payment', 'payment',
            "Payment recorded: $" . number_format($amount_paid, 2) . " via " . $receipt_number,
            json_encode(['previous_paid' => 0]),
            json_encode(['receipt_id' => $receipt_id, 'receipt_number' => $receipt_number, 'amount_paid' => $amount_paid]),
            'crm_money_receipts', $user_id);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'receipt_id' => $receipt_id,
            'receipt_number' => $receipt_number,
            'overpayment_credit' => $remaining_payment
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// FUNCTION: Get Overpayment Credits
// ========================================
function getOverpaymentCredits($client_id, $purchase_id) {
    global $pdo;

    $query = "SELECT * FROM crm_overpayment_credits WHERE client_id = ? AND status = 'active'";
    $params = [$client_id];

    if ($purchase_id > 0) {
        $query .= " AND purchase_id = ?";
        $params[] = $purchase_id;
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_credit = array_reduce($credits, function($sum, $item) {
        return $sum + floatval($item['remaining_credit']);
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
    global $pdo;

    $credit_id = intval($_POST['credit_id'] ?? 0);
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    $amount_to_apply = floatval($_POST['amount_to_apply'] ?? 0);

    if ($credit_id <= 0 || $schedule_id <= 0 || $amount_to_apply <= 0) {
        throw new Exception('Invalid parameters');
    }

    $pdo->beginTransaction();

    try {
        // Get credit
        $credit = $pdo->query("SELECT * FROM crm_overpayment_credits WHERE id = $credit_id")->fetch(PDO::FETCH_ASSOC);
        if (!$credit) throw new Exception('Credit not found');
        if ($amount_to_apply > $credit['remaining_credit']) throw new Exception('Insufficient credit');

        // Get schedule
        $schedule = $pdo->query("SELECT * FROM crm_payment_schedule WHERE id = $schedule_id")->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) throw new Exception('Schedule not found');

        // Apply credit
        $new_remaining = floatval($credit['remaining_credit']) - $amount_to_apply;
        $applied_data = json_decode($credit['applied_to'] ?? '[]', true);
        $applied_data[] = [
            'schedule_id' => $schedule_id,
            'amount_applied' => $amount_to_apply,
            'applied_date' => date('Y-m-d')
        ];

        $pdo->prepare("
            UPDATE crm_overpayment_credits 
            SET remaining_credit = ?, applied_to = ?
            WHERE id = ?
        ")->execute([$new_remaining, json_encode($applied_data), $credit_id]);

        // Update schedule paid amount
        $new_paid_amount = floatval($schedule['paid_amount']) + $amount_to_apply;
        $new_status = $new_paid_amount >= $schedule['installment_amount'] ? 1 : 2;

        $pdo->prepare("
            UPDATE crm_payment_schedule 
            SET paid_amount = ?, overpayment_credit_used = overpayment_credit_used + ?, status = ?, updated_by = ?
            WHERE id = ?
        ")->execute([$new_paid_amount, $amount_to_apply, $new_status, $user_id, $schedule_id]);

        // Log audit trail
        logAuditTrail($schedule['client_id'], $schedule['purchase_id'], 'payment', 'payment',
            "Overpayment credit applied: $" . number_format($amount_to_apply, 2) . " to schedule #" . $schedule['installment_number'],
            json_encode(['previous_paid' => $schedule['paid_amount']]),
            json_encode(['new_paid' => $new_paid_amount]),
            'crm_overpayment_credits,crm_payment_schedule', $user_id);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Credit applied successfully',
            'remaining_credit' => $new_remaining
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ========================================
// HELPER: Log Audit Trail
// ========================================
function logAuditTrail($client_id, $purchase_id, $action_type, $action_category, $description, $before_value, $after_value, $affected_tables, $user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO crm_audit_trail 
        (client_id, purchase_id, action_type, action_category, description, before_value, after_value, 
         affected_tables, performed_by, ip_address, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
    ");

    $stmt->execute([
        $client_id, $purchase_id, $action_type, $action_category, $description,
        $before_value, $after_value, $affected_tables, $user_id,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
?>
