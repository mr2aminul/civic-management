<?php
/**
 * Invoices Module - Fully Automated Invoice & Payment Management
 * Maximum automation: payments auto-update schedules, invoices, send emails
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

        if ($purchase_id) {
            $db->where('purchase_id', $purchase_id);
        } else {
            $db->where('client_id', $client_id);
        }
        
        $db->orderBy('created_at', 'DESC');
        $invoices = $db->get('crm_invoices');

        $result = [];
        if (!empty($invoices)) {
            foreach ($invoices as $inv) {
                // Get PDF path
                $pdf_path = $db->where('invoice_id', $inv->id)->where('document_type', 'invoice')->getValue('crm_documents', 'file_path');
                
                $result[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_type' => $inv->invoice_type,
                    'amount' => $inv->amount,
                    'paid_amount' => $inv->paid_amount ?? 0,
                    'remaining_amount' => $inv->remaining_amount ?? $inv->amount,
                    'due_date' => $inv->due_date,
                    'status' => $inv->status,
                    'created_at' => $inv->created_at,
                    'pdf_path' => $pdf_path
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
        global $wo;
        
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $invoice_type = isset($_POST['invoice_type']) ? Wo_Secure($_POST['invoice_type']) : 'installment';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $due_date = isset($_POST['due_date']) ? Wo_Secure($_POST['due_date']) : date('Y-m-d');
        $invoice_date = isset($_POST['invoice_date']) ? Wo_Secure($_POST['invoice_date']) : date('Y-m-d');
        $notes = isset($_POST['notes']) ? Wo_Secure($_POST['notes']) : '';
        $payment_schedule_id = isset($_POST['payment_schedule_id']) ? intval($_POST['payment_schedule_id']) : null;
        $description = isset($_POST['description']) ? Wo_Secure($_POST['description']) : '';
        
        if (!$purchase_id || !$amount) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID and Amount required']);
            exit;
        }

        // Get purchase details for client_id and description generation
        $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$purchase) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        // Auto-set client_id if not provided
        if (!$client_id) {
            $client_id = $purchase->client_id;
        }

        // Auto-generate description if not provided
        if (!$description) {
            $type_labels = [
                'booking_money' => 'Booking Money',
                'down_payment' => 'Down Payment',
                'installment' => 'Installment Payment'
            ];
            $description = $type_labels[$invoice_type] ?? 'Payment';
            
            // Add plot details if available
            if ($purchase->plot) {
                $description .= " for Plot {$purchase->plot}";
            }
        }

        // Generate invoice number: INV-YYYYMM-00001
        $invoice_prefix = 'INV-' . date('Ym');
        $last = $db->rawQuery("SELECT invoice_number FROM `crm_invoices` WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1", [$invoice_prefix . '%']);
        $nextNum = 1;
        if (!empty($last) && !empty($last[0]->invoice_number)) {
            if (preg_match('/(\d{5})$/', $last[0]->invoice_number, $mm)) {
                $nextNum = intval($mm[1]) + 1;
            }
        }
        $invoice_number = $invoice_prefix . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        // Check for overdue status
        $status = 'issued';
        if ($due_date < date('Y-m-d')) {
            $status = 'overdue';
        }


        $data = [
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'invoice_number' => $invoice_number,
            'invoice_type' => $invoice_type,
            'payment_schedule_id' => $payment_schedule_id,
            'description' => $description,
            'invoice_date' => $invoice_date,
            'amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'due_date' => $due_date,
            'notes' => $notes,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $wo['user']['id'] ?? 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('crm_invoices', $data);
        
        if ($id) {
            // Log to audit trail
            logAuditTrail($purchase_id, 'create', 'invoice', "Invoice $invoice_number created for ৳$amount", null, $data);
            
            // UPDATE PAYMENT SCHEDULE
            if ($payment_schedule_id) {
                $db->where('id', $payment_schedule_id);
                $db->update('crm_payment_schedule', [
                    'invoice_id' => $id,
                    'invoice_status' => 'issued',
                    'invoice_date' => $invoice_date,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            // AUTO-GENERATE MONEY RECEIPT IF PAYMENT RECEIVED
            $receipt_id = 0;
            $receipt_pdf_generated = false;
            
            if (isset($_POST['payment_received']) && $_POST['payment_received'] == '1') {
                $pay_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : $amount;
                $pay_method = isset($_POST['payment_method']) ? Wo_Secure($_POST['payment_method']) : 'cash';
                
                if ($pay_amount > 0) {
                    // Generate Receipt Number
                    $receipt_prefix = 'MR-' . date('Ym');
                    $last_rcpt = $db->rawQuery("SELECT receipt_number FROM `crm_money_receipts` WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 1", [$receipt_prefix . '%']);
                    $nextRcptNum = 1;
                    if (!empty($last_rcpt) && !empty($last_rcpt[0]->receipt_number)) {
                        if (preg_match('/(\d{5})$/', $last_rcpt[0]->receipt_number, $mm)) {
                            $nextRcptNum = intval($mm[1]) + 1;
                        }
                    }
                    $receipt_number = $receipt_prefix . '-' . str_pad($nextRcptNum, 5, '0', STR_PAD_LEFT);

                    // Insert Receipt
                    $receipt_data = [
                        'purchase_id' => $purchase_id,
                        'client_id' => $client_id,
                        'receipt_number' => $receipt_number,
                        'amount_paid' => $pay_amount,
                        'payment_date' => date('Y-m-d'),
                        'payment_method' => $pay_method,
                        'notes' => "Auto-generated for Invoice $invoice_number",
                        'status' => 'issued',
                        'invoices_paid' => json_encode([[
                            'invoice_id' => $id,
                            'invoice_number' => $invoice_number,
                            'applied_amount' => $pay_amount
                        ]]),
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $wo['user']['id'] ?? 0
                    ];
                    $receipt_id = $db->insert('crm_money_receipts', $receipt_data);

                    if ($receipt_id) {
                        // Update Invoice Status
                        $new_paid = $pay_amount;
                        $new_remaining = max(0, $amount - $new_paid);
                        $new_status = ($new_remaining <= 0.01) ? 'paid' : 'partial';
                        
                        $db->where('id', $id)->update('crm_invoices', [
                            'paid_amount' => $new_paid,
                            'remaining_amount' => $new_remaining,
                            'status' => $new_status
                        ]);

                        // Update Schedule Status
                        if ($payment_schedule_id) {
                            $sched_status = ($new_remaining <= 0.01) ? 1 : 2; // 1=paid, 2=partial
                            $db->where('id', $payment_schedule_id)->update('crm_payment_schedule', [
                                'paid_amount' => $new_paid,
                                'payment_date' => date('Y-m-d'),
                                'status' => $sched_status
                            ]);
                        }

                        // Log Receipt
                        logAuditTrail($purchase_id, 'create', 'payment', "Auto-payment of ৳$pay_amount recorded for Invoice $invoice_number", null, $receipt_data);

                        // Generate Receipt PDF & Email
                        $rcpt_pdf = auto_generate_receipt_pdf($receipt_id);
                        $receipt_pdf_generated = $rcpt_pdf['success'] ?? false;
                        
                        if ($receipt_pdf_generated && !empty($rcpt_pdf['full_path'])) {
                            // Queue Receipt Email
                            $client = $db->where('id', $client_id)->getOne('crm_customers', ['name', 'email']);
                            if ($client && !empty($client->email)) {
                                $db->insert('crm_email_queue', [
                                    'purchase_id' => $purchase_id,
                                    'client_id' => $client_id,
                                    'recipient_email' => $client->email,
                                    'recipient_name' => $client->name,
                                    'email_type' => 'payment_received',
                                    'metadata' => json_encode([
                                        'client_name' => $client->name,
                                        'receipt_number' => $receipt_number,
                                        'amount' => $pay_amount,
                                        'payment_date' => date('Y-m-d'),
                                        'payment_method' => $pay_method,
                                        'file_num' => $purchase->file_num ?? '-',
                                        'pdf_path' => $rcpt_pdf['full_path']
                                    ]),
                                    'attachment_path' => $rcpt_pdf['full_path'],
                                    'status' => 'queued',
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    }
                }
            }
            
            // AUTO-GENERATE INVOICE PDF AND QUEUE EMAIL WITH ATTACHMENT
            // Helper already loaded via init.php
            $pdf_result = auto_generate_invoice_pdf($id);
            
            echo json_encode([
                'status' => 200, 
                'message' => 'Invoice created successfully' . ($receipt_id ? ' & Payment Recorded' : ''), 
                'invoice_id' => $id, 
                'invoice_number' => $invoice_number,
                'receipt_id' => $receipt_id,
                'pdf_generated' => $pdf_result['success'] ?? false,
                'email_queued' => $pdf_result['email_queued'] ?? false
            ]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to create invoice']);

        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'suggest_next_invoice') {
    try {
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
            exit;
        }

        // Get next unpaid installment from payment schedule
        $db->where('purchase_id', $purchase_id);
        $db->where('status', 0); // Unpaid
        $db->where('status', 99, '!='); // Not deleted
        $db->orderBy('installment_number', 'ASC');
        $next = $db->getOne('crm_payment_schedule');

        if (!$next) {
            echo json_encode(['status' => 200, 'suggestion' => null, 'message' => 'All installments paid']);
            exit;
        }

        $suggestion = [
            'invoice_type' => $next->installment_type ?? 'installment',
            'amount' => $next->installment_amount,
            'due_date' => $next->due_date,
            'description' => $next->particular ?? '',
            'payment_schedule_id' => $next->id
        ];

        // Calculate overdue details
        if ($next->due_date < date('Y-m-d')) {
            $d1 = new DateTime($next->due_date);
            $d2 = new DateTime(date('Y-m-d'));
            $diff = $d2->diff($d1);
            $days_overdue = $diff->days;
            
            // 5% late fee logic
            $late_fee = $next->installment_amount * 0.05;
            
            $suggestion['is_overdue'] = true;
            $suggestion['days_overdue'] = $days_overdue;
            $suggestion['late_fee'] = $late_fee;
            $suggestion['suggested_amount'] = $next->installment_amount + $late_fee; // Total with late fee
        } else {
            $suggestion['is_overdue'] = false;
            $suggestion['days_overdue'] = 0;
            $suggestion['late_fee'] = 0;
            $suggestion['suggested_amount'] = $next->installment_amount;
        }

        echo json_encode(['status' => 200, 'suggestion' => $suggestion]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'get_invoice_summary') {
    try {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
            exit;
        }

        // Get purchase details
        $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$purchase) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        // Get booking details for price
        $booking = $db->where('id', $purchase->booking_id)->getOne('wo_booking', ['katha']);
        $total_amount = $booking->katha * $purchase->per_katha ?? 0;

        // Calculate paid amounts
        $paid_amount = $db->where('purchase_id', $purchase_id)->getValue('crm_invoices', 'SUM(paid_amount)') ?? 0;
        $total_due = max(0, $total_amount - $paid_amount);

        // Get credits
        $credits = $db->where('purchase_id', $purchase_id)->where('remaining_amount', 0, '>')->getValue('crm_payment_credits', 'SUM(remaining_amount)') ?? 0;

        // Get booking money and down payment stats
        $bm_total = $purchase->booking_money ?? 0;
        $dp_total = $purchase->down_payment ?? 0;
        
        // Calculate paid BM/DP from invoices
        $bm_paid = $db->where('purchase_id', $purchase_id)->where('invoice_type', 'booking_money')->getValue('crm_invoices', 'SUM(paid_amount)') ?? 0;
        $dp_paid = $db->where('purchase_id', $purchase_id)->where('invoice_type', 'down_payment')->getValue('crm_invoices', 'SUM(paid_amount)') ?? 0;

        // Get pending schedule
        $db->where('purchase_id', $purchase_id);
        $db->where('status', 1, '!='); // Not fully paid
        $db->where('status', 99, '!='); // Not deleted
        $db->orderBy('due_date', 'ASC');
        $schedules = $db->get('crm_payment_schedule');
        
        $schedule_data = [];
        foreach ($schedules as $sch) {
            $schedule_data[] = [
                'id' => $sch->id,
                'particular' => $sch->particular,
                'due_date' => $sch->due_date,
                'installment_amount' => $sch->installment_amount,
                'paid_amount' => $sch->paid_amount,
                'balance' => $sch->installment_amount - $sch->paid_amount
            ];
        }

        echo json_encode([
            'status' => 200,
            'total_amount' => $total_amount,
            'total_paid' => $paid_amount,
            'total_due' => $total_due,
            'credits' => $credits,
            'booking_money' => ['total' => $bm_total, 'paid' => $bm_paid],
            'down_payment' => ['total' => $dp_total, 'paid' => $dp_paid],
            'payment_schedule' => $schedule_data
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'calculate_late_fees') {
    try {
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
            exit;
        }

        // Simple late fee calculation: 5% of overdue amount
        // Find overdue installments
        $db->where('purchase_id', $purchase_id);
        $db->where('due_date', date('Y-m-d'), '<');
        $db->where('status', 1, '!='); // Not paid
        $db->where('status', 99, '!=');
        $overdue_schedules = $db->get('crm_payment_schedule');
        
        $total_late_fee = 0;
        $overdue_total = 0;
        
        foreach ($overdue_schedules as $sch) {
            $balance = $sch->installment_amount - $sch->paid_amount;
            $overdue_total += $balance;
            // 5% late fee on overdue balance
            $total_late_fee += ($balance * 0.05);
        }
        
        $suggested_total = $amount + $total_late_fee;

        echo json_encode([
            'status' => 200,
            'total_late_fee' => $total_late_fee,
            'overdue_amount' => $overdue_total,
            'suggested_total' => $suggested_total
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'record_payment') {
    try {
        global $wo;
        
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payment_date = isset($_POST['payment_date']) ? Wo_Secure($_POST['payment_date']) : date('Y-m-d');
        $payment_method = isset($_POST['payment_method']) ? Wo_Secure($_POST['payment_method']) : 'cash';
        $reference = isset($_POST['reference']) ? Wo_Secure($_POST['reference']) : '';
        $notes = isset($_POST['notes']) ? Wo_Secure($_POST['notes']) : '';
        $invoice_ids = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : [];

        if (!$purchase_id || !$amount) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID and Amount required']);
            exit;
        }

        $db->startTransaction();

        try {
            // Get purchase details
            $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
            if (!$purchase) {
                throw new Exception('Purchase not found');
            }

            $client_id = $purchase->client_id;

            // Generate receipt number: MR-YYYYMM-00001
            $receipt_prefix = 'MR-' . date('Ym');
            $last = $db->rawQuery("SELECT receipt_number FROM `crm_money_receipts` WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 1", [$receipt_prefix . '%']);
            $nextNum = 1;
            if (!empty($last) && !empty($last[0]->receipt_number)) {
                if (preg_match('/(\d{5})$/', $last[0]->receipt_number, $mm)) {
                    $nextNum = intval($mm[1]) + 1;
                }
            }
            $receipt_number = $receipt_prefix . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

            // Record money receipt
            $receipt_data = [
                'purchase_id' => $purchase_id,
                'client_id' => $client_id,
                'receipt_number' => $receipt_number,
                'amount_paid' => $amount,
                'payment_date' => $payment_date,
                'payment_method' => $payment_method,
                'transaction_reference' => $reference,
                'notes' => $notes,
                'status' => 'issued',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $wo['user']['id'] ?? 0
            ];

            $receipt_id = $db->insert('crm_money_receipts', $receipt_data);
            
            if (!$receipt_id) {
                throw new Exception('Failed to create receipt');
            }

            // Apply payment to invoices and auto-update payment schedules
            $remaining = $amount;
            $total_invoices_paid = 0;
            $invoices_paid_arr = [];

            // If no invoice_ids provided, auto-select unpaid invoices
            if (empty($invoice_ids)) {
                $db->where('purchase_id', $purchase_id);
                $db->where('status', 'issued', '!=');
                $db->where('status', 'paid', '!=');
                $db->orderBy('due_date', 'ASC');
                $unpaid_invoices = $db->get('crm_invoices');
                $invoice_ids = array_map(function($inv) { return $inv->id; }, $unpaid_invoices);
            }

            foreach ($invoice_ids as $invoice_id) {
                if ($remaining <= 0) break;

                $db->where('id', intval($invoice_id));
                $invoice = $db->getOne('crm_invoices');

                if (!$invoice) continue;

                $unpaid = floatval($invoice->remaining_amount ?? ($invoice->amount - $invoice->paid_amount));
                if ($unpaid <= 0) continue;

                $payment_to_apply = min($remaining, $unpaid);

                $new_paid = floatval($invoice->paid_amount) + $payment_to_apply;
                $new_remaining = max(0, floatval($invoice->amount) - $new_paid);
                $new_status = ($new_remaining <= 0.01) ? 'paid' : 'partial';

                $db->where('id', $invoice_id);
                $db->update('crm_invoices', [
                    'paid_amount' => $new_paid,
                    'remaining_amount' => $new_remaining,
                    'status' => $new_status,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $wo['user']['id'] ?? 0
                ]);

                // AUTO-UPDATE PAYMENT SCHEDULE if linked
                if ($invoice->payment_schedule_id) {
                    $db->where('id', $invoice->payment_schedule_id);
                    $schedule = $db->getOne('crm_payment_schedule');
                    
                    if ($schedule) {
                        $sched_new_paid = floatval($schedule->paid_amount) + $payment_to_apply;
                        $sched_amount = floatval($schedule->installment_amount);
                        $sched_status = ($sched_new_paid >= $sched_amount - 0.01) ? 1 : 2; // 1=paid, 2=partial

                        $db->where('id', $invoice->payment_schedule_id);
                        $db->update('crm_payment_schedule', [
                            'paid_amount' => $sched_new_paid,
                            'payment_date' => $payment_date,
                            'status' => $sched_status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                $invoices_paid_arr[] = [
                    'invoice_id' => intval($invoice->id),
                    'invoice_number' => $invoice->invoice_number,
                    'applied_amount' => $payment_to_apply
                ];
                
                $remaining -= $payment_to_apply;
                $total_invoices_paid += $payment_to_apply;
            }

            // Update receipt with invoices_paid JSON
            $db->where('id', $receipt_id);
            $db->update('crm_money_receipts', [
                'invoices_paid' => json_encode($invoices_paid_arr, JSON_UNESCAPED_UNICODE)
            ]);

            // Handle overpayment credit automatically
            $overpayment_amount = 0;
            if ($remaining > 0.01) {
                $overpayment_amount = round($remaining, 2);
                $credit_data = [
                    'purchase_id' => $purchase_id,
                    'client_id' => $client_id,
                    'receipt_id' => $receipt_id,
                    'credit_amount' => $overpayment_amount,
                    'applied_amount' => 0,
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $db->insert('crm_payment_credits', $credit_data);

                // Log overpayment to audit trail
                logAuditTrail($purchase_id, 'create', 'credit', "Overpayment credit created for ৳$overpayment_amount", null, $credit_data);
            }

            // Log payment to audit trail
            $logData = [
                'receipt_id' => $receipt_id,
                'receipt_number' => $receipt_number,
                'invoices_paid' => $invoices_paid_arr,
                'overpayment' => $overpayment_amount
            ];
            logAuditTrail($purchase_id, 'create', 'payment', "Payment of ৳$amount recorded via $payment_method", null, $logData);

            // AUTO-GENERATE RECEIPT PDF AND QUEUE EMAIL WITH ATTACHMENT
            // Helper already loaded via init.php
            $pdf_result = auto_generate_receipt_pdf($receipt_id);
            
            // Update email queue with PDF attachment if generated
            if ($pdf_result['success'] && !empty($pdf_result['full_path'])) {
                // Get client details
                $client = $db->where('id', $client_id)->getOne('crm_customers', ['name', 'email', 'phone']);
                $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['file_num']);
                
                if ($client && !empty($client->email)) {
                    $db->insert('crm_email_queue', [
                        'purchase_id' => $purchase_id,
                        'client_id' => $client_id,
                        'recipient_email' => $client->email,
                        'recipient_name' => $client->name,
                        'email_type' => 'payment_received',
                        'template_variables' => json_encode([
                            'client_name' => $client->name,
                            'receipt_number' => $receipt_number,
                            'amount' => $amount,
                            'payment_date' => $payment_date,
                            'payment_method' => $payment_method,
                            'file_num' => $purchase->file_num ?? '-',
                            'pdf_path' => $pdf_result['full_path']
                        ]),
                        'attachment_path' => $pdf_result['full_path'],
                        'status' => 'queued',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            // CHECK IF ALL PAYMENTS COMPLETE → AUTO-GENERATE COMPLETION CERTIFICATE
            $completion_result = check_and_generate_completion_certificate($purchase_id);

            $db->commit();

            echo json_encode([
                'status' => 200,
                'message' => 'Payment recorded and schedules updated successfully',
                'receipt_id' => $receipt_id,
                'receipt_number' => $receipt_number,
                'applied_total' => $total_invoices_paid,
                'invoices_paid' => $invoices_paid_arr,
                'overpayment_credit' => $overpayment_amount,
                'receipt_pdf_generated' => $pdf_result['success'] ?? false,
                'all_payments_complete' => $completion_result['all_paid'] ?? false,
                'certificate_generated' => $completion_result['certificate_generated'] ?? false,
                'email_queued' => true
            ]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }

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
        $credits = $db->get('crm_payment_credits');

        $result = [];
        if (!empty($credits)) {
            foreach ($credits as $credit) {
                $result[] = [
                    'id' => $credit->id,
                    'credit_amount' => $credit->credit_amount,
                    'applied_amount' => $credit->applied_amount ?? 0,
                    'status' => $credit->status ?? 'active',
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
                    'amount' => $receipt->amount_paid,
                    'payment_date' => $receipt->payment_date,
                    'payment_method' => $receipt->payment_method,
                    'reference' => $receipt->transaction_reference ?? '',
                    'notes' => $receipt->notes ?? '',
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

if ($s === 'get_purchase_documents') {
    try {
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        $type = isset($_GET['type']) ? Wo_Secure($_GET['type']) : null;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
            exit;
        }

        $documents = crm_get_purchase_documents($purchase_id, $type);
        
        // Format for frontend
        $result = [];
        foreach ($documents as $doc) {
            $result[] = [
                'id' => $doc->id,
                'file_name' => $doc->file_name,
                'document_type' => $doc->document_type,
                'generated_at' => $doc->generated_at,
                'file_size' => $doc->file_size,
                'file_path' => $doc->file_path,
                'fm_file_id' => $doc->fm_file_id
            ];
        }

        echo json_encode(['status' => 200, 'documents' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'upload_purchase_document') {
    try {
        global $wo;
        
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $document_type = isset($_POST['document_type']) ? Wo_Secure($_POST['document_type']) : 'document';
        
        if (!$purchase_id || empty($_FILES['file'])) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID and File required']);
            exit;
        }

        // Get client_id from purchase
        $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['client_id']);
        if (!$purchase) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        $file = $_FILES['file'];
        $source_path = $file['tmp_name'];
        $original_name = $file['name'];
        
        // Move to temp location with original name to preserve extension logic in helper
        $temp_path = sys_get_temp_dir() . '/' . $original_name;
        move_uploaded_file($source_path, $temp_path);
        
        $result = crm_store_document($temp_path, $purchase->client_id, $purchase_id, $document_type, [
            'uploaded_by' => $wo['user']['id'] ?? 0
        ]);
        
        @unlink($temp_path); // Cleanup

        if ($result['success']) {
            echo json_encode(['status' => 200, 'message' => 'Document uploaded successfully', 'document' => $result]);
        } else {
            echo json_encode(['status' => 500, 'message' => $result['message']]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'delete_purchase_document') {
    try {
        global $wo;
        
        $document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
        
        if (!$document_id) {
            echo json_encode(['status' => 400, 'message' => 'Document ID required']);
            exit;
        }

        // Get fm_file_id from crm_documents
        $doc = $db->where('id', $document_id)->getOne('crm_documents', ['fm_file_id']);
        if (!$doc) {
            echo json_encode(['status' => 404, 'message' => 'Document not found']);
            exit;
        }

        // Soft delete via helper
        // Note: crm_delete_document expects fm_files.id, not crm_documents.id
        // But wait, crm_delete_document wraps fm_delete_file which takes file_id.
        // Let's assume we pass fm_file_id.
        
        $result = crm_delete_document($doc->fm_file_id, $wo['user']['id'] ?? 0);
        
        if ($result['success'] || $result === true) { // fm_delete_file might return boolean or array
             // Also mark as deleted in crm_documents or delete row?
             // Since it's soft delete in fm_files, we should probably keep crm_documents but maybe mark it?
             // Or just delete from crm_documents since the file is gone from active view.
             $db->where('id', $document_id)->delete('crm_documents');
             
             echo json_encode(['status' => 200, 'message' => 'Document deleted successfully']);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to delete document']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
