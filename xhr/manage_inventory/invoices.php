<?php
/**
 * Invoices Module - Complete Invoice & Payment Management
 * Handles: invoice creation, payment recording, credit management
 */

header('Content-Type: application/json; charset=utf-8');

if ($s === 'get_invoices') {
    try {
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        
        if (!$purchase_id && !$client_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID or Client ID required']);
            exit;
        }

        $where = $purchase_id ? ['purchase_id' => $purchase_id] : ['client_id' => $client_id];
        
        $db->orderBy('created_at', 'DESC');
        $invoices = $db->get('crm_invoices', null, [
            'id', 'purchase_id', 'invoice_number', 'invoice_type', 'amount', 'paid_amount', 'due_date', 'status', 'created_at'
        ]);

        $result = [];
        if (!empty($invoices)) {
            foreach ($invoices as $inv) {
                $result[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_type' => $inv->invoice_type,
                    'amount' => $inv->amount,
                    'paid_amount' => $inv->paid_amount,
                    'due_date' => $inv->due_date,
                    'status' => $inv->status,
                    'created_at' => $inv->created_at
                ];
            }
        }

        echo json_encode(['status' => 200, 'invoices' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'get_invoice_detail') {
    try {
        $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
        
        if (!$invoice_id) {
            echo json_encode(['status' => 400, 'message' => 'Invoice ID required']);
            exit;
        }

        $db->where('id', $invoice_id);
        $invoice = $db->getOne('crm_invoices');

        if (!$invoice) {
            echo json_encode(['status' => 404, 'message' => 'Invoice not found']);
            exit;
        }

        echo json_encode(['status' => 200, 'invoice' => (array)$invoice]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'create_invoice') {
    try {
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $invoice_type = isset($_POST['invoice_type']) ? Wo_Secure($_POST['invoice_type']) : 'installment';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $due_date = isset($_POST['due_date']) ? Wo_Secure($_POST['due_date']) : date('Y-m-d');
        
        if (!$purchase_id || !$amount) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID and Amount required']);
            exit;
        }

        // Generate invoice number: INV-YYYYMM-00001
        $invoice_prefix = 'INV-' . date('Ym');
        $db->where('invoice_number', $invoice_prefix . '%', 'LIKE');
        $lastInvoice = $db->getOne('crm_invoices', 'MAX(id) as max_id');
        $nextNum = 1;
        if ($lastInvoice && $lastInvoice->max_id) {
            $nextNum = intval(substr($lastInvoice->max_id, -5)) + 1;
        }
        $invoice_number = $invoice_prefix . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $data = [
            'purchase_id' => $purchase_id,
            'invoice_number' => $invoice_number,
            'invoice_type' => $invoice_type,
            'amount' => $amount,
            'paid_amount' => 0,
            'due_date' => $due_date,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('crm_invoices', $data);
        
        if ($id) {
            // Log to audit trail
            logAuditTrail($purchase_id, 'create', 'invoice', "Invoice $invoice_number created for ৳$amount", null, $data);
            echo json_encode(['status' => 200, 'message' => 'Invoice created', 'invoice_id' => $id, 'invoice_number' => $invoice_number]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to create invoice']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'record_payment') {
    try {
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? Wo_Secure($_POST['payment_date']) : date('Y-m-d');
        $payment_method = isset($_POST['payment_method']) ? Wo_Secure($_POST['payment_method']) : 'cash';
        $reference = isset($_POST['reference']) ? Wo_Secure($_POST['reference']) : '';
        $notes = isset($_POST['notes']) ? Wo_Secure($_POST['notes']) : '';
        $invoice_ids = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : [];

        if (!$purchase_id || !$amount || empty($invoice_ids)) {
            echo json_encode(['status' => 400, 'message' => 'Purchase, Amount, and Invoice IDs required']);
            exit;
        }

        // Generate receipt number: MR-YYYYMM-00001
        $receipt_prefix = 'MR-' . date('Ym');
        $db->where('receipt_number', $receipt_prefix . '%', 'LIKE');
        $lastReceipt = $db->getOne('crm_money_receipts', 'MAX(id) as max_id');
        $nextNum = 1;
        if ($lastReceipt && $lastReceipt->max_id) {
            $nextNum = intval(substr($lastReceipt->max_id, -5)) + 1;
        }
        $receipt_number = $receipt_prefix . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        // Record money receipt
        $receipt_data = [
            'purchase_id' => $purchase_id,
            'receipt_number' => $receipt_number,
            'amount' => $amount,
            'payment_date' => $payment_date,
            'payment_method' => $payment_method,
            'reference' => $reference,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $receipt_id = $db->insert('crm_money_receipts', $receipt_data);
        
        if (!$receipt_id) {
            echo json_encode(['status' => 500, 'message' => 'Failed to create receipt']);
            exit;
        }

        // Apply payment to invoices
        $remaining = $amount;
        $total_invoices_paid = 0;

        foreach ($invoice_ids as $invoice_id) {
            if ($remaining <= 0) break;

            $db->where('id', intval($invoice_id));
            $invoice = $db->getOne('crm_invoices');

            if (!$invoice) continue;

            $unpaid = floatval($invoice->amount) - floatval($invoice->paid_amount);
            $payment_to_apply = min($remaining, $unpaid);

            $new_paid = floatval($invoice->paid_amount) + $payment_to_apply;
            $new_status = ($new_paid >= floatval($invoice->amount)) ? 'paid' : 'partial';

            $db->where('id', $invoice_id);
            $db->update('crm_invoices', [
                'paid_amount' => $new_paid,
                'status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $remaining -= $payment_to_apply;
            $total_invoices_paid += $payment_to_apply;
        }

        // Handle overpayment credit
        if ($remaining > 0.01) {
            $credit_data = [
                'purchase_id' => $purchase_id,
                'receipt_id' => $receipt_id,
                'credit_amount' => $remaining,
                'remaining_credit' => $remaining,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $db->insert('crm_overpayment_credits', $credit_data);

            // Log overpayment to audit trail
            logAuditTrail($purchase_id, 'create', 'credit', "Overpayment credit created for ৳$remaining", null, $credit_data);
        }

        // Log payment to audit trail
        logAuditTrail($purchase_id, 'create', 'payment', "Payment of ৳$amount recorded via $payment_method", null, $receipt_data);

        echo json_encode([
            'status' => 200,
            'message' => 'Payment recorded successfully',
            'receipt_id' => $receipt_id,
            'receipt_number' => $receipt_number,
            'overpayment_credit' => $remaining > 0 ? $remaining : 0
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'get_credits') {
    try {
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
            exit;
        }

        $db->where('purchase_id', $purchase_id);
        $db->orderBy('created_at', 'DESC');
        $credits = $db->get('crm_overpayment_credits');

        $result = [];
        if (!empty($credits)) {
            foreach ($credits as $credit) {
                $result[] = [
                    'id' => $credit->id,
                    'receipt_number' => $credit->receipt_id ? $db->where('id', $credit->receipt_id)->getOne('crm_money_receipts', 'receipt_number')->receipt_number : 'N/A',
                    'credit_amount' => $credit->credit_amount,
                    'remaining_credit' => $credit->remaining_credit,
                    'status' => $credit->status,
                    'applied_to' => $credit->applied_to ?? 'None',
                    'created_at' => $credit->created_at
                ];
            }
        }

        echo json_encode(['status' => 200, 'credits' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'get_receipts') {
    try {
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
            exit;
        }

        $db->where('purchase_id', $purchase_id);
        $db->orderBy('payment_date', 'DESC');
        $receipts = $db->get('crm_money_receipts');

        $result = [];
        if (!empty($receipts)) {
            foreach ($receipts as $receipt) {
                $result[] = [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'amount' => $receipt->amount,
                    'payment_date' => $receipt->payment_date,
                    'payment_method' => $receipt->payment_method,
                    'reference' => $receipt->reference,
                    'notes' => $receipt->notes,
                    'created_at' => $receipt->created_at
                ];
            }
        }

        echo json_encode(['status' => 200, 'receipts' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

function logAuditTrail($purchase_id, $action, $category, $description, $before = null, $after = null) {
    global $db, $wo;
    
    $user_id = $wo['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $data = [
        'purchase_id' => $purchase_id,
        'action_type' => $action,
        'action_category' => $category,
        'description' => $description,
        'before_value' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
        'after_value' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
        'performed_by' => $user_id,
        'ip_address' => $ip_address,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    return $db->insert('crm_audit_trail', $data);
}
