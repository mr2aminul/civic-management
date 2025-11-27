<?php
/**
 * manage_inventory.php sub-file : invoices.php
 * Invoices Module - Complete Invoice & Payment Management
 * Handles: invoice creation, payment recording, credit management
 */

header('Content-Type: application/json; charset=utf-8');

// place inside manage_inventory.php (or the invoices sub-file) where $s === 'get_invoices' is handled

// GET / POST handler inside manage_inventory.php (or invoices sub-file)
// Replace existing get_invoices block with this implementation.
// It follows the style of `search_clients` for summary calculation (booking_money, down_payment, paid totals, credits).
// ------------ Improved get_invoices, create_invoice, record_payment handlers -------------
header('Content-Type: application/json; charset=utf-8');

if ($s === 'get_invoices') {
    try {
        global $db;
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        $client_id   = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

        if (!$purchase_id && !$client_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID or Client ID required']);
            exit;
        }

        // Build where
        if ($purchase_id) {
            $db->where('purchase_id', $purchase_id);
        } else {
            $db->where('client_id', $client_id);
        }
        $db->orderBy('invoice_date', 'DESC');
        $invoices = $db->get('crm_invoices');

        $rows = [];
        $total_amount = 0.0;
        $total_paid = 0.0;
        $outstanding = 0.0;
        $pending_count = 0;
        $overdue_count = 0;
        $overpayment = 0.0;

        if (!empty($invoices)) {
            foreach ($invoices as $inv) {
                $remaining = floatval($inv->remaining_amount ?? (floatval($inv->amount) - floatval($inv->paid_amount)));
                $rows[] = [
                    'id' => intval($inv->id),
                    'purchase_id' => intval($inv->purchase_id),
                    'client_id' => intval($inv->client_id),
                    'invoice_number' => $inv->invoice_number,
                    'invoice_type' => $inv->invoice_type,
                    'invoice_date' => $inv->invoice_date,
                    'due_date' => $inv->due_date,
                    'amount' => (float)$inv->amount,
                    'paid_amount' => (float)$inv->paid_amount,
                    'remaining_amount' => $remaining,
                    'status' => $inv->status,
                    'notes' => $inv->notes,
                    'created_at' => $inv->created_at,
                ];

                $total_amount += floatval($inv->amount);
                $total_paid += floatval($inv->paid_amount);
                if ($remaining > 0) $outstanding += $remaining;
                if (in_array($inv->status, ['draft','issued'])) $pending_count++;
                if ($inv->status === 'overdue') $overdue_count++;
                // Overpayment not per-invoice here; will be reported from credits separately
            }
        }

        // fetch credits for purchase (sum remaining)
        $credits = [];
        if ($purchase_id) {
            $db->where('purchase_id', $purchase_id);
            $db->orderBy('created_at', 'DESC');
            $creditRows = $db->get('crm_payment_credits');
            if (!empty($creditRows)) {
                foreach ($creditRows as $c) {
                    $remaining_credit = floatval($c->remaining_amount ?? ($c->credit_amount - $c->applied_amount));
                    $credits[] = [
                        'id' => intval($c->id),
                        'credit_amount' => (float)$c->credit_amount,
                        'applied_amount' => (float)$c->applied_amount,
                        'remaining_amount' => $remaining_credit,
                        'source_invoice_id' => intval($c->source_invoice_id),
                        'created_at' => $c->created_at
                    ];
                    $overpayment += $remaining_credit;
                }
            }
        }

        $summary = [
            'total_amount' => $total_amount,
            'total_paid' => $total_paid,
            'outstanding' => $outstanding,
            'pending_count' => $pending_count,
            'overdue_count' => $overdue_count,
            'overpayment' => $overpayment,
        ];

        echo json_encode(['status' => 200, 'invoices' => $rows, 'summary' => $summary, 'credits' => $credits]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: '.$e->getMessage()]);
        exit;
    }
}

if ($s === 'get_invoice_summary') {
    global $db;
    $client_id   = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;

    if (!$client_id || !$purchase_id) {
        echo json_encode([]);
        exit;
    }

    // Fetch purchase and plot details
    $db->where('id', $purchase_id);
    $purchase = $db->getOne('wo_booking_helper');

    if (!$purchase) {
        echo json_encode([]);
        exit;
    }

    $db->where('id', $purchase->booking_id);
    $plot_details = $db->getOne('wo_booking');

    $total_amount = floatval(($purchase->per_katha * $plot_details->katha) + $purchase->booking_money + $purchase->down_payment);
    $booking_paid = floatval($purchase->booking_money_paid ?? 0);
    $down_paid    = floatval($purchase->down_payment_paid ?? 0);
    $total_paid   = $booking_paid + $down_paid;

    // Payment schedule
    $db->where('purchase_id', $purchase_id);
    $db->orderBy('installment_number','ASC');
    $schedules = $db->get('crm_payment_schedule');

    $payment_schedule = [];
    foreach ($schedules as $sch) {
        $balance = floatval($sch->installment_amount) - floatval($sch->paid_amount);
        $payment_schedule[] = [
            'id' => $sch->id,
            'particular' => $sch->particular ?? 'Installment '.$sch->installment_number,
            'due_date' => $sch->due_date,
            'installment_amount' => floatval($sch->installment_amount),
            'paid_amount' => floatval($sch->paid_amount),
            'balance' => max(0, $balance)
        ];
    }

    $response = [
        'total_amount'   => $total_amount,
        'booking_money'  => ['paid'=>$booking_paid, 'total'=>$purchase->booking_money],
        'down_payment'   => ['paid'=>$down_paid, 'total'=>$purchase->down_payment],
        'total_paid'     => $total_paid,
        'total_due'      => max(0,$total_amount-$total_paid),
        'credits'        => floatval($purchase->credits ?? 0),
        'payment_schedule'=> $payment_schedule
    ];

    echo json_encode([$response]);
    exit;
}

if ($s === 'create_invoice') {
    global $db;

    $purchase_id   = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    $invoice_type  = isset($_POST['invoice_type']) ? Wo_Secure($_POST['invoice_type']) : 'installment';
    $invoice_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    $invoice_date  = isset($_POST['invoice_date']) ? Wo_Secure($_POST['invoice_date']) : date('Y-m-d');
    $due_date      = isset($_POST['due_date']) ? Wo_Secure($_POST['due_date']) : date('Y-m-d');

    if (!$purchase_id || $invoice_amount <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID and positive Amount required']);
        exit;
    }

    // --- Find client from purchase ---
    $db->where('id', $purchase_id);
    $ph = $db->getOne('wo_booking_helper');
    if (!$ph) {
        echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
        exit;
    }
    $client_id = intval($ph->client_id);
    $interest_percent = floatval($ph->interest_percent ?? 3);

    // --- Generate invoice number (INV-YYYYMM-00001) ---
    $invoice_prefix = 'INV-' . date('Ym');
    $last = $db->rawQuery("SELECT invoice_number FROM crm_invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1", [$invoice_prefix . '%']);
    $nextNum = 1;
    if (!empty($last) && !empty($last[0]->invoice_number)) {
        if (preg_match('/(\d{5})$/', $last[0]->invoice_number, $mm)) {
            $nextNum = intval($mm[1]) + 1;
        }
    }
    $invoice_number = $invoice_prefix . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

    // --- Create invoice record ---
    $invoice_data = [
        'invoice_number' => $invoice_number,
        'purchase_id'    => $purchase_id,
        'client_id'      => $client_id,
        'invoice_type'   => $invoice_type,
        'invoice_date'   => $invoice_date,
        'due_date'       => $due_date,
        'amount'         => $invoice_amount,
        'paid_amount'    => $invoice_amount,
        'remaining_amount'=> 0.00,
        'status'         => 'paid',
        'created_at'     => date('Y-m-d H:i:s')
    ];

    $invoice_id = $db->insert('crm_invoices', $invoice_data);
    if (!$invoice_id) {
        echo json_encode(['status' => 500, 'message' => 'Failed to create invoice']);
        exit;
    }

    // --- Fetch unpaid/partial payment schedules for this purchase ---
    $db->where('purchase_id', $purchase_id);
    $db->where('status', '0'); // unpaid (0) or partial (2)
    $db->orderBy('installment_number', 'ASC');
    $schedules = $db->get('crm_payment_schedule');

    $remaining_payment = $invoice_amount;

    foreach ($schedules as $sch) {
        $balance = floatval($sch->installment_amount) - floatval($sch->paid_amount);
        if ($balance <= 0) continue;

        $pay = min($remaining_payment, $balance);
        $newPaid = floatval($sch->paid_amount) + $pay;
        $balanceAfterPay = floatval($sch->installment_amount) - $newPaid;

        // Overdue calculation
        $interest = 0.0;
        $overdueDays = 0;
        if (strtotime($sch->due_date) < strtotime($invoice_date) && $balanceAfterPay > 0) {
            $overdueDays = floor((strtotime($invoice_date) - strtotime($sch->due_date)) / 86400);
            $interest = round($balanceAfterPay * $interest_percent / 100, 2);
        }

        $status = ($newPaid >= $sch->installment_amount) ? 1 : 2; // 1=paid, 2=partial

        $db->where('id', $sch->id);
        $db->update('crm_payment_schedule', [
            'paid_amount'       => $newPaid,
            'payment_date'      => $invoice_date,
            'status'            => $status,
            'late_fee_amount'   => $interest,
            'days_overdue'      => $overdueDays,
            'money_receipt_no'  => $invoice_number,
            'invoice_id'        => $invoice_id
        ]);

        $remaining_payment -= $pay;
        if ($remaining_payment <= 0) break;
    }

    // --- Store overpayment credit if any ---
    if ($remaining_payment > 0) {
        $db->insert('crm_overpayment_credits', [
            'purchase_id' => $purchase_id,
            'client_id'   => $client_id,
            'amount'      => $remaining_payment,
            'invoice_id'  => $invoice_id,
            'created_at'  => date('Y-m-d H:i:s')
        ]);
    }

    // Audit Trail
    if ($db->tableExists('crm_audit_trail')) {
        $db->insert('crm_audit_trail', [
            'user_id' => $wo['user_id'] ?? 0,
            'action' => 'invoice_created',
            'details' => json_encode([
                'invoice_id' => $invoice_id,
                'invoice_number' => $invoice_number,
                'purchase_id' => $purchase_id,
                'amount' => $invoice_amount
            ]),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Queue email notification
    if ($db->tableExists('crm_email_queue')) {
        // Get client email
        $client = $db->where('id', $client_id)->getOne(T_CUSTOMERS, ['email', 'name']);
        if ($client && !empty($client['email'])) {
            $db->insert('crm_email_queue', [
                'purchase_id' => $purchase_id,
                'client_id' => $client_id,
                'recipient_email' => $client['email'],
                'recipient_name' => $client['name'],
                'email_type' => 'invoice_created',
                'subject' => "New Invoice $invoice_number - ৳" . number_format($invoice_amount, 2),
                'body' => "Dear {$client['name']},<br><br>A new invoice has been generated for your purchase.<br><br>Invoice Number: $invoice_number<br>Amount: ৳" . number_format($invoice_amount, 2) . "<br>Due Date: $due_date<br><br>Thank you.",
                'status' => 'pending',
                'queue_date' => date('Y-m-d H:i:s')
            ]);
        }
    }

    echo json_encode([
        'status' => 200,
        'message' => 'Invoice created and payment schedule updated',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
    exit;
}



if ($s === 'record_payment') {
    try {
        global $db;
        // POST inputs
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
        $payment_date = isset($_POST['payment_date']) ? Wo_Secure($_POST['payment_date']) : date('Y-m-d');
        $payment_method = isset($_POST['payment_method']) ? Wo_Secure($_POST['payment_method']) : 'cash';
        $reference = isset($_POST['reference']) ? Wo_Secure($_POST['reference']) : '';
        $notes = isset($_POST['notes']) ? Wo_Secure($_POST['notes']) : '';
        // invoice_ids can be array or single string
        $invoice_ids = [];
        if (isset($_POST['invoice_ids']) && is_array($_POST['invoice_ids'])) {
            $invoice_ids = array_map('intval', $_POST['invoice_ids']);
        } elseif (isset($_POST['invoice_ids'])) {
            // support invoice_ids[]=1 or invoice_ids=1
            if (is_string($_POST['invoice_ids'])) {
                // maybe comma separated
                $invoice_ids = array_filter(array_map('intval', explode(',', $_POST['invoice_ids'])));
            } else {
                $invoice_ids = [intval($_POST['invoice_ids'])];
            }
        }

        if (!$purchase_id || $amount <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Purchase and positive Amount required']);
            exit;
        }

        // get client id for purchase
        $db->where('id', $purchase_id);
        $ph = $db->getOne('wo_booking_helper');
        if (!$ph) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        $client_id = intval($ph->client_id);

        // prepare invoice list to apply to
        if (empty($invoice_ids)) {
            // fetch unpaid invoices for this purchase (oldest first)
            $db->where('purchase_id', $purchase_id);
            $db->where('remaining_amount', 0, '>');
            $db->orWhere('paid_amount', 0); // ensure open invoices
            $db->orderBy('invoice_date', 'ASC');
            $cands = $db->get('crm_invoices', null, 'id, amount, paid_amount, remaining_amount, payment_schedule_id');
            $invoice_ids = [];
            if (!empty($cands)) {
                foreach ($cands as $ci) $invoice_ids[] = intval($ci->id);
            }
        }

        if (empty($invoice_ids)) {
            // nothing to apply to - create credit if allowed
            // create receipt + credit
        }

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

        // Insert money receipt (use fields aligned with schema)
        $invoices_paid_arr = []; // will push {invoice_id, applied_amount}
        $receipt_data = [
            'receipt_number' => $receipt_number,
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'receipt_date' => $payment_date,
            'payment_date' => $payment_date,
            'amount_paid' => $amount,
            'payment_method' => $payment_method,
            'transaction_reference' => $reference,
            'invoices_paid' => json_encode([], JSON_UNESCAPED_UNICODE),
            'notes' => $notes,
            'status' => 'issued',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $receipt_id = $db->insert('crm_money_receipts', $receipt_data);
        if (!$receipt_id) {
            echo json_encode(['status' => 500, 'message' => 'Failed to create receipt']);
            exit;
        }

        $remaining = $amount;
        $applied_total = 0;

        foreach ($invoice_ids as $iid) {
            if ($remaining <= 0) break;

            $db->where('id', $iid);
            $inv = $db->getOne('crm_invoices');
            if (!$inv) continue;

            $inv_remaining = floatval($inv->remaining_amount ?? (floatval($inv->amount) - floatval($inv->paid_amount)));
            if ($inv_remaining <= 0) continue;

            $apply = min($remaining, $inv_remaining);
            $new_paid = round(floatval($inv->paid_amount) + $apply, 2);
            $new_remaining = round(max(0, floatval($inv->amount) - $new_paid), 2);
            $new_status = ($new_remaining <= 0.001) ? 'paid' : 'partial';

            // update invoice
            $db->where('id', $inv->id);
            $db->update('crm_invoices', [
                'paid_amount' => $new_paid,
                'remaining_amount' => $new_remaining,
                'status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // if invoice linked to payment schedule, update schedule row(s)
            if (!empty($inv->payment_schedule_id)) {
                // Add to schedule paid_amount and set payment_date/status appropriately.
                // Note: schedule may be an installment row; if you want to split among many schedules,
                // extend this logic. Here we update that schedule row.
                $db->where('id', intval($inv->payment_schedule_id));
                $sched = $db->getOne('crm_payment_schedule');
                if ($sched) {
                    $sched_new_paid = round(floatval($sched->paid_amount) + $apply, 2);
                    $sched_status = ($sched_new_paid >= floatval($sched->installment_amount) - 0.001) ? 1 : 2; // 1=paid,2=partial
                    $db->where('id', $sched->id);
                    $db->update('crm_payment_schedule', [
                        'paid_amount' => $sched_new_paid,
                        'payment_date' => $payment_date,
                        'status' => $sched_status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            $invoices_paid_arr[] = ['invoice_id' => intval($inv->id), 'applied_amount' => $apply];
            $remaining -= $apply;
            $applied_total += $apply;
        }

        // update receipts.invoices_paid JSON
        $db->where('id', $receipt_id);
        $db->update('crm_money_receipts', ['invoices_paid' => json_encode($invoices_paid_arr, JSON_UNESCAPED_UNICODE)]);

        // Handle overpayment / create payment credit record if remaining > 0
        $overpayment_amount = 0;
        if ($remaining > 0.01) {
            $overpayment_amount = round($remaining, 2);
            $credit_data = [
                'purchase_id' => $purchase_id,
                'client_id' => $client_id,
                'credit_amount' => $overpayment_amount,
                'applied_amount' => 0.00,
                'source_invoice_id' => null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $db->insert('crm_payment_credits', $credit_data);
            logAuditTrail($purchase_id, 'create', 'credit', "Overpayment credit created for ৳$overpayment_amount", null, $credit_data);
        }

        // Log payment
        $logData = [
            'receipt_id' => $receipt_id,
            'receipt_number' => $receipt_number,
            'applied' => $invoices_paid_arr,
            'overpayment' => $overpayment_amount
        ];
        logAuditTrail($purchase_id, 'create', 'payment', "Payment of ৳$amount recorded via $payment_method", null, $logData);

        echo json_encode([
            'status' => 200,
            'message' => 'Payment recorded successfully',
            'receipt_id' => $receipt_id,
            'receipt_number' => $receipt_number,
            'applied_total' => $applied_total,
            'applied_breakdown' => $invoices_paid_arr,
            'overpayment_credit' => $overpayment_amount
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: '.$e->getMessage()]);
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
        'action_description' => $description,
        'before_values' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
        'after_values' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
        'performed_by' => $user_id,
        'performed_at' => date('Y-m-d H:i:s'),
        'ip_address' => $ip_address,
    ];
    
    return $db->insert('crm_audit_trail', $data);
}