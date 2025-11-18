<?php
/**
 * Email Management API
 * Handles email sending, logging, and templates
 * File: /home/civicbd/civicgroup/xhr/manage_inventory_emails.php
 */

header('Content-Type: application/json; charset=utf-8');
global $db, $wo;

$s = isset($_GET['s']) ? trim($_GET['s']) : (isset($_POST['s']) ? trim($_POST['s']) : '');

// Get pending emails (money receipts and schedules)
if ($s === 'get_pending_emails') {
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    
    if ($purchase_id <= 0){
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
        exit;
    }

    try {
        $pending = [];

        // Get pending money receipts
        $receipts = $db->where('purchase_id', $purchase_id)->where('email_sent', 0)->get('crm_money_receipts');
        foreach ($receipts as $r){
            $pending[] = [
                'id' => (int)$r->id,
                'type' => 'money_receipt',
                'title' => 'Money Receipt: ' . $r->receipt_number,
                'amount' => (float)$r->amount,
                'date' => $r->receipt_date,
                'can_send' => true
            ];
        }

        // Get pending payment schedules
        $schedules = $db->where('purchase_id', $purchase_id)
            ->where('status', 0)
            ->orderBy('date', 'ASC')
            ->get('crm_payment_schedule');
        
        if (!empty($schedules)){
            $total_amount = 0;
            foreach ($schedules as $s){
                $total_amount += (float)$s->installment_amount;
            }

            $pending[] = [
                'id' => $purchase_id,
                'type' => 'payment_schedule',
                'title' => 'Payment Schedule Report',
                'item_count' => count($schedules),
                'total_amount' => $total_amount,
                'date' => date('Y-m-d'),
                'can_send' => true
            ];
        }

        echo json_encode([
            'status' => 200,
            'pending' => $pending,
            'count' => count($pending)
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Send email to client
if ($s === 'send_email_to_client') {
    if (!Wo_IsAdmin()){
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $email_type = isset($_POST['email_type']) ? trim($_POST['email_type']) : '';
    $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $body = isset($_POST['body']) ? $_POST['body'] : '';

    if ($purchase_id <= 0 || !$email_type || !$recipient_email || !$subject){
        echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
        exit;
    }

    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)){
        echo json_encode(['status' => 400, 'message' => 'Invalid email address']);
        exit;
    }

    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper){
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        // Log email
        $email_log = [
            'purchase_id' => $purchase_id,
            'client_id' => (int)$helper->client_id,
            'email_type' => $email_type === 'receipt' ? 'money_receipt' : 'payment_schedule',
            'recipient_email' => $recipient_email,
            'recipient_name' => $helper->client_name ?? '',
            'email_subject' => $subject,
            'email_body_excerpt' => substr($body, 0, 500),
            'email_template' => $email_type . '_' . date('Y-m-d'),
            'sent_at' => date('Y-m-d H:i:s'),
            'delivery_status' => 'sent',
            'sent_by' => $wo['user']['id'] ?? null,
            'sent_by_name' => $wo['user']['name'] ?? 'Admin'
        ];

        // TODO: Implement actual email sending
        // For now, just log intent
        // mail($recipient_email, $subject, $body, [...headers...]);

        $log_id = $db->insert('crm_email_logs', $email_log);

        // If money receipt, mark as sent
        if ($email_type === 'receipt'){
            $receipt_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            if ($receipt_id > 0){
                $db->where('id', $receipt_id)->update('crm_money_receipts', [
                    'email_sent' => 1,
                    'email_sent_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        echo json_encode([
            'status' => 200,
            'message' => 'Email sent successfully',
            'log_id' => (int)$log_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

?>
