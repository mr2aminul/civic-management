<?php
/**
 * Email Queue & Management Module
 * Handles: email queueing, sending, template selection, email logs
 */

header('Content-Type: application/json; charset=utf-8');

if ($s === 'get_pending_emails') {
    try {
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        $email_type = isset($_GET['email_type']) ? Wo_Secure($_GET['email_type']) : '';
        $status = isset($_GET['status']) ? Wo_Secure($_GET['status']) : '';

        if (!$purchase_id && !$client_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID or Client ID required']);
            exit;
        }

        $where = [];
        if ($purchase_id) $where['purchase_id'] = $purchase_id;
        if ($email_type) $where['email_type'] = $email_type;
        if ($status) $where['status'] = $status;

        $db->orderBy('queue_date', 'DESC');
        $emails = $db->where($where)->get('crm_email_queue', null, [
            'id', 'recipient_email', 'recipient_name', 'email_type', 'status', 
            'queue_date', 'scheduled_send_date', 'retry_count'
        ]);

        $result = [];
        if (!empty($emails)) {
            foreach ($emails as $email) {
                $result[] = [
                    'id' => $email->id,
                    'recipient_email' => $email->recipient_email,
                    'recipient_name' => $email->recipient_name,
                    'email_type' => $email->email_type,
                    'status' => $email->status,
                    'queue_date' => $email->queue_date,
                    'scheduled_send_date' => $email->scheduled_send_date,
                    'retry_count' => $email->retry_count
                ];
            }
        }

        echo json_encode(['status' => 200, 'emails' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'queue_email') {
    try {
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $recipient_email = isset($_POST['recipient_email']) ? Wo_Secure($_POST['recipient_email']) : '';
        $recipient_name = isset($_POST['recipient_name']) ? Wo_Secure($_POST['recipient_name']) : '';
        $email_type = isset($_POST['email_type']) ? Wo_Secure($_POST['email_type']) : '';
        $subject = isset($_POST['subject']) ? Wo_Secure($_POST['subject']) : '';
        $body = isset($_POST['body']) ? $_POST['body'] : ''; // Allow HTML in body
        $scheduled_date = isset($_POST['scheduled_date']) ? Wo_Secure($_POST['scheduled_date']) : null;

        if (!$purchase_id || !$recipient_email || !$email_type) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID, Email, and Type required']);
            exit;
        }

        // Validate email format
        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid email format']);
            exit;
        }

        $data = [
            'purchase_id' => $purchase_id,
            'recipient_email' => $recipient_email,
            'recipient_name' => $recipient_name,
            'email_type' => $email_type,
            'subject' => $subject,
            'body' => $body,
            'status' => $scheduled_date ? 'scheduled' : 'pending',
            'scheduled_send_date' => $scheduled_date,
            'queue_date' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('crm_email_queue', $data);

        if ($id) {
            echo json_encode(['status' => 200, 'message' => 'Email queued successfully', 'queue_id' => $id]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to queue email']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'send_email') {
    try {
        $email_id = isset($_POST['email_id']) ? intval($_POST['email_id']) : 0;
        
        if (!$email_id) {
            echo json_encode(['status' => 400, 'message' => 'Email ID required']);
            exit;
        }

        $db->where('id', $email_id);
        $email = $db->getOne('crm_email_queue');

        if (!$email) {
            echo json_encode(['status' => 404, 'message' => 'Email not found']);
            exit;
        }

        // Send email using PHP mail
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@civicgroup.com" . "\r\n";

        $success = mail(
            $email->recipient_email,
            $email->subject,
            $email->body,
            $headers
        );

        if ($success) {
            // Update email status
            $db->where('id', $email_id);
            $db->update('crm_email_queue', [
                'status' => 'sent',
                'sent_date' => date('Y-m-d H:i:s')
            ]);

            // Log to email logs
            $db->insert('crm_email_logs', [
                'purchase_id' => $email->purchase_id,
                'recipient_email' => $email->recipient_email,
                'email_type' => $email->email_type,
                'status' => 'sent',
                'sent_date' => date('Y-m-d H:i:s')
            ]);

            echo json_encode(['status' => 200, 'message' => 'Email sent successfully']);
        } else {
            // Update retry count and mark as failed
            $retry_count = intval($email->retry_count ?? 0) + 1;
            $db->where('id', $email_id);
            $db->update('crm_email_queue', [
                'status' => $retry_count < 3 ? 'pending' : 'failed',
                'retry_count' => $retry_count,
                'last_error' => 'Failed to send via mail()'
            ]);

            echo json_encode(['status' => 500, 'message' => 'Failed to send email']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'send_all_pending_emails') {
    try {
        $db->where('status', 'pending');
        $db->where('scheduled_send_date', 'IS NULL', '');
        $pending_emails = $db->get('crm_email_queue');

        $sent_count = 0;
        $failed_count = 0;

        if (!empty($pending_emails)) {
            foreach ($pending_emails as $email) {
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
                $headers .= "From: noreply@civicgroup.com" . "\r\n";

                $success = mail(
                    $email->recipient_email,
                    $email->subject,
                    $email->body,
                    $headers
                );

                if ($success) {
                    $db->where('id', $email->id);
                    $db->update('crm_email_queue', [
                        'status' => 'sent',
                        'sent_date' => date('Y-m-d H:i:s')
                    ]);
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            }
        }

        echo json_encode([
            'status' => 200,
            'message' => "Sent $sent_count emails, $failed_count failed",
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s === 'delete_queued_email') {
    try {
        $email_id = isset($_POST['email_id']) ? intval($_POST['email_id']) : 0;
        
        if (!$email_id) {
            echo json_encode(['status' => 400, 'message' => 'Email ID required']);
            exit;
        }

        $db->where('id', $email_id);
        $result = $db->delete('crm_email_queue');

        if ($result) {
            echo json_encode(['status' => 200, 'message' => 'Email deleted successfully']);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to delete email']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
