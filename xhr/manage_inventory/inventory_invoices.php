<?php
/**
 * Invoice System API
 * Handles all invoice operations with payment schedule sync
 * File: /home/civicbd/civicgroup/xhr/manage_inventory_invoices.php
 */

header('Content-Type: application/json; charset=utf-8');
global $db, $wo;


// Get all invoices for a purchase
if ($s === 'get_invoices_for_purchase') {
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    
    if ($purchase_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
        exit;
    }
    
    try {
        $invoices = $db->where('purchase_id', $purchase_id)->orderBy('invoice_date', 'DESC')->get('crm_invoices');
        
        $result = [];
        if (!empty($invoices)) {
            foreach ($invoices as $inv) {
                $installment_ids = $inv->installment_ids ? json_decode($inv->installment_ids, true) : [];
                
                $result[] = [
                    'id' => (int)$inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $inv->invoice_date,
                    'due_date' => $inv->due_date,
                    'total_amount' => (float)$inv->total_amount,
                    'paid_amount' => (float)$inv->paid_amount,
                    'balance_amount' => (float)$inv->balance_amount,
                    'status' => $inv->status,
                    'money_receipt_no' => $inv->money_receipt_no,
                    'receipt_generated_date' => $inv->receipt_generated_date,
                    'installment_count' => count($installment_ids),
                    'installment_ids' => $installment_ids,
                    'notes' => $inv->notes,
                    'created_by' => $inv->created_by,
                    'created_at' => $inv->created_at
                ];
            }
        }
        
        echo json_encode(['status' => 200, 'invoices' => $result, 'count' => count($result)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Create new invoice from selected installments
if ($s === 'create_invoice_from_installments') {
    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $installment_ids = isset($_POST['installment_ids']) ? json_decode($_POST['installment_ids'], true) : [];
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+30 days'));
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
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
                $total_amount += (float)$item->installment_amount;
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
            'invoice_date' => date('Y-m-d'),
            'due_date' => $due_date,
            'total_amount' => $total_amount,
            'paid_amount' => 0,
            'balance_amount' => $total_amount,
            'installment_ids' => json_encode($installment_ids),
            'status' => 'draft',
            'created_by' => $wo['user']['id'] ?? null,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $invoice_id = $db->insert('crm_invoices', $invoice_data);
        
        // Update schedule items to reference this invoice
        foreach ($installment_ids as $inst_id) {
            $db->where('id', (int)$inst_id)->update('crm_payment_schedule', [
                'invoice_id' => $invoice_id
            ]);
        }
        
        echo json_encode([
            'status' => 200,
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

// Update invoice status and sync with payment schedule
if ($s === 'update_invoice_status') {
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
        
        echo json_encode([
            'status' => 200,
            'message' => 'Invoice status updated',
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Record payment on invoice and sync with schedule
if ($s === 'record_invoice_payment') {
    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }
    
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $payment_amount = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0;
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    
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
        
        // Update invoice
        $new_paid = (float)$invoice->paid_amount + $payment_amount;
        $new_balance = (float)$invoice->total_amount - $new_paid;
        $new_status = 'paid';
        
        if ($new_paid < $invoice->total_amount) {
            $new_status = 'partial';
        }
        
        $db->where('id', $invoice_id)->update('crm_invoices', [
            'paid_amount' => $new_paid,
            'balance_amount' => max(0, $new_balance),
            'status' => $new_status,
            'updated_by' => $wo['user']['id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Sync with payment schedule - mark related installments
        $installment_ids = $invoice->installment_ids ? json_decode($invoice->installment_ids, true) : [];
        
        if (!empty($installment_ids)) {
            // Get payment proportion for this invoice
            $payment_proportion = $payment_amount / (float)$invoice->total_amount;
            
            foreach ($installment_ids as $inst_id) {
                $schedule = $db->where('id', (int)$inst_id)->getOne('crm_payment_schedule');
                if ($schedule) {
                    $inst_payment = (float)$schedule->installment_amount * $payment_proportion;
                    $current_paid = (float)$schedule->paid_amount + $inst_payment;
                    
                    $db->where('id', (int)$inst_id)->update('crm_payment_schedule', [
                        'paid_amount' => $current_paid,
                        'payment_date' => $payment_date,
                        'payment_method' => $payment_method,
                        'status' => ($current_paid >= $schedule->installment_amount) ? 1 : 2
                    ]);
                }
            }
        }
        
        echo json_encode([
            'status' => 200,
            'message' => 'Payment recorded successfully',
            'new_paid_amount' => $new_paid,
            'new_balance' => max(0, $new_balance),
            'invoice_status' => $new_status
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Generate money receipt from invoice
if ($s === 'generate_money_receipt') {
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
        if (!$invoice || (float)$invoice->paid_amount <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invoice has no payments to receipt']);
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
            'amount' => (float)$invoice->paid_amount,
            'payment_method' => $payment_method,
            'invoice_id' => $invoice_id,
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
            'message' => 'Money receipt generated',
            'receipt_id' => (int)$receipt_id,
            'receipt_number' => $receipt_number
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

?>
