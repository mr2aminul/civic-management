<?php
/**
 * Email Endpoints
 * Handles payment schedule emails and composed email sending
 */

header('Content-Type: application/json; charset=utf-8');

// Email Schedule Endpoint
if ($s === 'email_schedule') {
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    $recipient_email = isset($_POST['recipient_email']) ? Wo_Secure($_POST['recipient_email']) : '';
    
    if (!$purchase_id) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
        exit;
    }

    try {
        // Fetch purchase details
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        $booking = $db->where('id', $helper->booking_id)->getOne('wo_booking');
        $client = $db->where('id', $helper->client_id)->getOne(T_CUSTOMERS);
        
        // Use provided email or client's email
        if (empty($recipient_email)) {
            $recipient_email = $client->email ?? '';
        }
        
        if (empty($recipient_email)) {
            echo json_encode(['status' => 400, 'message' => 'No email address available']);
            exit;
        }
        
        // Validate email
        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid email address']);
            exit;
        }
        
        // Get payment schedule
        $schedule = [];
        $total_amount = 0;
        $total_paid = 0;
        $total_due = 0;
        
        if ($db->tableExists('crm_payment_schedule')) {
            $db->where('purchase_id', $purchase_id);
            $db->where('status', '99', '!='); // Exclude deleted/rescheduled
            $db->orderBy('installment_number', 'ASC');
            $schedule_rows = $db->get('crm_payment_schedule');
            
            foreach ($schedule_rows as $row) {
                $amount = floatval($row->installment_amount);
                $paid = floatval($row->paid_amount);
                $due = $amount - $paid;
                
                $total_amount += $amount;
                $total_paid += $paid;
                $total_due += $due;
                
                $schedule[] = [
                    'no' => $row->installment_number,
                    'particular' => $row->particular ?? 'Installment ' . $row->installment_number,
                    'due_date' => date('d M Y', strtotime($row->due_date)),
                    'amount' => number_format($amount, 2),
                    'paid' => number_format($paid, 2),
                    'due' => number_format($due, 2),
                    'status' => $row->status == 1 ? 'Paid' : ($row->status == 2 ? 'Partial' : 'Unpaid')
                ];
            }
        }
        
        // Build email body (HTML table)
        $email_body = "Dear {$client->name},<br><br>";
        $email_body .= "Please find your payment schedule for File: <strong>{$helper->file_num}</strong><br>";
        $email_body .= "Project: <strong>{$booking->project}</strong>, Plot: <strong>{$booking->plot}</strong><br><br>";
        
        $email_body .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse; width:100%; font-family:Arial,sans-serif;">';
        $email_body .= '<thead style="background-color:#f0f0f0;">';
        $email_body .= '<tr>';
        $email_body .= '<th>No.</th><th>Particular</th><th>Due Date</th><th>Amount</th><th>Paid</th><th>Due</th><th>Status</th>';
        $email_body .= '</tr>';
        $email_body .= '</thead>';
        $email_body .= '<tbody>';
        
        foreach ($schedule as $item) {
            $email_body .= '<tr>';
            $email_body .= "<td>{$item['no']}</td>";
            $email_body .= "<td>{$item['particular']}</td>";
            $email_body .= "<td>{$item['due_date']}</td>";
            $email_body .= "<td>৳{$item['amount']}</td>";
            $email_body .= "<td>৳{$item['paid']}</td>";
            $email_body .= "<td>৳{$item['due']}</td>";
            $email_body .= "<td>{$item['status']}</td>";
            $email_body .= '</tr>';
        }
        
        $email_body .= '<tr style="font-weight:bold; background-color:#f9f9f9;">';
        $email_body .= '<td colspan="3">Total</td>';
        $email_body .= '<td>৳' . number_format($total_amount, 2) . '</td>';
        $email_body .= '<td>৳' . number_format($total_paid, 2) . '</td>';
        $email_body .= '<td>৳' . number_format($total_due, 2) . '</td>';
        $email_body .= '<td></td>';
        $email_body .= '</tr>';
        $email_body .= '</tbody>';
        $email_body .= '</table>';
        
        $email_body .= '<br>Thank you,<br>Civic Group BD';
        
        // Queue the email
        $tableExists = $db->tableExists('crm_email_queue');
        
        if (!$tableExists) {
            echo json_encode(['status' => 500, 'message' => 'Email queue table does not exist']);
            exit;
        }
        
        try {
            $db->insert('crm_email_queue', [
                'purchase_id' => $purchase_id,
                'client_id' => $helper->client_id,
                'recipient_email' => $recipient_email,
                'recipient_name' => $client->name,
                'email_type' => 'payment_schedule',
                'subject' => "Payment Schedule - File: {$helper->file_num}",
                'body' => $email_body,
                'status' => 'pending',
                'queue_date' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'status' => 200,
                'message' => 'Payment schedule queued for email to ' . $recipient_email
            ]);
        } catch (Exception $insertError) {
            echo json_encode(['status' => 500, 'message' => 'Failed to queue email: ' . $insertError->getMessage()]);
        }
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Send Composed Email Endpoint
if ($s === 'send_composed_email') {
    $to = isset($_POST['to']) ? Wo_Secure($_POST['to']) : '';
    $subject = isset($_POST['subject']) ? Wo_Secure($_POST['subject']) : '';
    $body = isset($_POST['body']) ? $_POST['body'] : '';
    $cc = isset($_POST['cc']) ? Wo_Secure($_POST['cc']) : '';
    $bcc = isset($_POST['bcc']) ? Wo_Secure($_POST['bcc']) : '';
    
    if (empty($to) || empty($subject) || empty($body)) {
        echo json_encode(['status' => 400, 'message' => 'Missing required fields (to, subject, body)']);
        exit;
    }
    
    // Validate email(s)
    $to_emails = array_map('trim', explode(',', $to));
    foreach ($to_emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid email address: ' . $email]);
            exit;
        }
    }
    
    try {
        // Queue the email
        $emailData = [
            'recipient_email' => $to,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
            'queue_date' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Add CC and BCC if provided
        if (!empty($cc)) {
            $emailData['cc'] = $cc;
        }
        if (!empty($bcc)) {
            $emailData['bcc'] = $bcc;
        }
        
        // Check if email queue table exists
        $tableExists = $db->tableExists('crm_email_queue');
        if (!$tableExists) {
            echo json_encode(['status' => 500, 'message' => 'Email queue table does not exist']);
            exit;
        }
        
        $insertId = $db->insert('crm_email_queue', $emailData);
        
        if ($insertId) {
            echo json_encode(['status' => 200, 'message' => 'Email queued successfully', 'email_id' => $insertId]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to queue email']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
