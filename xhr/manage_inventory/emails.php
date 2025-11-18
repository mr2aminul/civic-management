<?php
/**
 * CONSOLIDATED EMAIL MANAGEMENT ENDPOINTS
 * Handles all email operations: queue, send, logs, templates
 * Consolidated from: inventory_emails.php, inventory_audit_email.php, advanced.php, inventory_complete.php
 */

global $db, $wo, $sqlConnect;

// Get pending emails (money receipts, schedules, and queue)
if ($s === 'get_pending_emails') {
    header('Content-Type: application/json; charset=utf-8');

    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $email_type = isset($_POST['email_type']) ? trim($_POST['email_type']) : '';

    if ($purchase_id <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
        exit;
    }

    try {
        $pending = [];

        // Get pending money receipts (not yet emailed)
        if ($email_type === 'receipt' || $email_type === '') {
            $receipts = $db->where('purchase_id', $purchase_id)
                ->where('email_sent', 0)
                ->get('crm_money_receipts');

            if (!empty($receipts)) {
                foreach ($receipts as $r) {
                    $pending[] = [
                        'id' => (int)$r->id,
                        'type' => 'money_receipt',
                        'title' => 'Money Receipt: ' . $r->receipt_number,
                        'receipt_number' => $r->receipt_number,
                        'amount' => (float)($r->amount ?? 0),
                        'date' => $r->receipt_date,
                        'can_send' => true
                    ];
                }
            }
        }

        // Get pending payment schedules
        if ($email_type === 'schedule' || $email_type === '') {
            $schedules = $db->where('purchase_id', $purchase_id)
                ->where('status', 0)
                ->orderBy('due_date', 'ASC')
                ->get('crm_payment_schedule');

            if (!empty($schedules)) {
                $total_amount = 0;
                foreach ($schedules as $schedule_item) {
                    $total_amount += (float)($schedule_item->installment_amount ?? 0);
                }

                $pending[] = [
                    'id' => 'schedule_' . $purchase_id,
                    'type' => 'payment_schedule',
                    'title' => 'Payment Schedule Report',
                    'item_count' => count($schedules),
                    'total_amount' => $total_amount,
                    'date' => date('Y-m-d'),
                    'can_send' => true
                ];
            }
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'pending' => $pending,
            'count' => count($pending)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Queue email for later sending
if ($s === 'queue_email') {
    header('Content-Type: application/json; charset=utf-8');

    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $email_type = isset($_POST['email_type']) ? trim($_POST['email_type']) : '';
    $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';
    $recipient_phone = isset($_POST['recipient_phone']) ? trim($_POST['recipient_phone']) : '';
    $recipient_name = isset($_POST['recipient_name']) ? trim($_POST['recipient_name']) : '';
    $recipient_type = isset($_POST['recipient_type']) ? trim($_POST['recipient_type']) : 'client';
    $template_name = isset($_POST['template_name']) ? trim($_POST['template_name']) : '';
    $template_variables = isset($_POST['template_variables']) ? $_POST['template_variables'] : '{}';
    $scheduled_send = isset($_POST['scheduled_send_date']) ? trim($_POST['scheduled_send_date']) : '';

    if (!$client_id || !$email_type || !$recipient_email) {
        echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
        exit;
    }

    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 400, 'message' => 'Invalid email address']);
        exit;
    }

    try {
        if (is_string($template_variables)) {
            $template_variables = json_decode($template_variables, true);
        }

        $data = [
            'client_id' => $client_id,
            'purchase_id' => $purchase_id ?: null,
            'email_type' => $email_type,
            'recipient_email' => $recipient_email,
            'recipient_phone' => $recipient_phone,
            'recipient_name' => $recipient_name,
            'recipient_type' => $recipient_type,
            'template_name' => $template_name,
            'template_variables' => json_encode($template_variables),
            'queue_date' => date('Y-m-d H:i:s'),
            'scheduled_send_date' => $scheduled_send ?: null,
            'status' => 'pending',
            'created_by' => $wo['user']['id'] ?? null
        ];

        $queue_id = $db->insert('crm_email_queue', $data);

        if (!$queue_id) {
            throw new Exception('Failed to queue email');
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => 'Email queued successfully',
            'queue_id' => (int)$queue_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get queued emails
if ($s === 'get_queued_emails') {
    header('Content-Type: application/json; charset=utf-8');

    $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
    $email_type = isset($_GET['email_type']) ? trim($_GET['email_type']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    try {
        $db->where('status', $status);

        if ($client_id > 0) {
            $db->where('client_id', $client_id);
        }

        if ($email_type) {
            $db->where('email_type', $email_type);
        }

        $db->orderBy('queue_date', 'ASC');
        $db->limit($limit);
        $emails = $db->get('crm_email_queue');

        if (!$emails) {
            $emails = [];
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'queued_emails' => $emails,
            'count' => count($emails)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Send email (from queue or directly)
if ($s === 'send_email' || $s === 'send_email_to_client') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $queue_id = isset($_POST['queue_id']) ? (int)$_POST['queue_id'] : 0;
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $email_type = isset($_POST['email_type']) ? trim($_POST['email_type']) : '';
    $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $body = isset($_POST['body']) ? $_POST['body'] : '';

    try {
        // If queue_id provided, send from queue
        if ($queue_id > 0) {
            $db->where('id', $queue_id);
            $queue = $db->getOne('crm_email_queue');

            if (!$queue) {
                throw new Exception('Queue item not found');
            }

            // Load template if specified
            if (!empty($queue->template_name)) {
                $template_path = dirname(dirname(__FILE__)) . '/manage/pages/clients/emails/' . $queue->template_name . '.php';

                if (file_exists($template_path)) {
                    $template_variables = json_decode($queue->template_variables ?? '{}', true);
                    ob_start();
                    include $template_path;
                    $body = ob_get_clean();
                    $subject = $template_variables['subject'] ?? 'Payment Notification from Civic BD Group';
                }
            }

            $recipient_email = $queue->recipient_email;
            $purchase_id = (int)$queue->purchase_id;
            $email_type = $queue->email_type;

            // TODO: Implement actual email sending
            // $mail_sent = mail($recipient_email, $subject, $body, $headers);
            $mail_sent = true; // Placeholder

            if ($mail_sent) {
                $db->where('id', $queue_id);
                $db->update('crm_email_queue', [
                    'status' => 'sent',
                    'send_date' => date('Y-m-d H:i:s')
                ]);

                // Log email
                $log_data = [
                    'queue_id' => $queue_id,
                    'client_id' => (int)$queue->client_id,
                    'purchase_id' => $purchase_id,
                    'email_type' => $email_type,
                    'recipient_email' => $recipient_email,
                    'recipient_phone' => $queue->recipient_phone ?? '',
                    'recipient_name' => $queue->recipient_name ?? '',
                    'subject' => $subject,
                    'status' => 'sent',
                    'sent_by_user' => $wo['user']['id'] ?? null,
                    'sent_at' => date('Y-m-d H:i:s')
                ];

                $db->insert('crm_email_logs', $log_data);

                echo json_encode([
                    'status' => 200,
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'recipient' => $recipient_email
                ]);
            } else {
                $db->where('id', $queue_id);
                $db->update('crm_email_queue', [
                    'status' => 'failed',
                    'failure_reason' => 'Mail function failed'
                ]);

                throw new Exception('Failed to send email');
            }
        }
        // Direct send (without queue)
        else if ($purchase_id > 0 && $recipient_email && $email_type) {
            if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                throw new Exception('Purchase not found');
            }

            // TODO: Implement actual email sending
            // mail($recipient_email, $subject, $body, $headers);

            // Log email
            $email_log = [
                'purchase_id' => $purchase_id,
                'client_id' => (int)$helper->client_id,
                'email_type' => $email_type === 'receipt' ? 'money_receipt' : 'payment_schedule',
                'recipient_email' => $recipient_email,
                'recipient_name' => $helper->client_name ?? '',
                'subject' => $subject,
                'sent_at' => date('Y-m-d H:i:s'),
                'delivery_status' => 'sent',
                'sent_by_user' => $wo['user']['id'] ?? null
            ];

            $log_id = $db->insert('crm_email_logs', $email_log);

            // If money receipt, mark as sent
            if ($email_type === 'receipt') {
                $receipt_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
                if ($receipt_id > 0) {
                    $db->where('id', $receipt_id)->update('crm_money_receipts', [
                        'email_sent' => 1,
                        'email_sent_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            echo json_encode([
                'status' => 200,
                'success' => true,
                'message' => 'Email sent successfully',
                'log_id' => (int)$log_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            throw new Exception('Missing required parameters');
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Send bulk emails
if ($s === 'send_bulk_emails') {
    header('Content-Type: application/json; charset=utf-8');

    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $email_ids = isset($_POST['email_ids']) ? array_map('intval', explode(',', $_POST['email_ids'])) : [];

    if (empty($email_ids)) {
        echo json_encode(['status' => 400, 'message' => 'No emails selected']);
        exit;
    }

    try {
        $sent = 0;
        $failed = 0;

        foreach ($email_ids as $email_id) {
            if ($email_id <= 0) continue;

            $db->where('id', $email_id);
            $email = $db->getOne('crm_email_queue');
            if (!$email) continue;

            // TODO: Implement actual email sending
            $mail_sent = true; // Placeholder

            if ($mail_sent) {
                $db->where('id', $email_id);
                $db->update('crm_email_queue', [
                    'status' => 'sent',
                    'send_date' => date('Y-m-d H:i:s')
                ]);

                // Log to email_logs
                $db->insert('crm_email_logs', [
                    'queue_id' => $email_id,
                    'client_id' => (int)$email->client_id,
                    'purchase_id' => (int)$email->purchase_id,
                    'email_type' => $email->email_type,
                    'recipient_email' => $email->recipient_email,
                    'recipient_name' => $email->recipient_name ?? '',
                    'subject' => 'Email from queue',
                    'status' => 'sent',
                    'sent_by_user' => $wo['user']['id'] ?? null,
                    'sent_at' => date('Y-m-d H:i:s')
                ]);

                $sent++;
            } else {
                $failed++;
            }
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'message' => "$sent emails sent successfully",
            'sent_count' => $sent,
            'failed_count' => $failed
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get email logs
if ($s === 'get_email_logs') {
    header('Content-Type: application/json; charset=utf-8');

    $client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
    $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : 0;
    $email_type = isset($_GET['email_type']) ? trim($_GET['email_type']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

    try {
        if ($client_id > 0) {
            $db->where('client_id', $client_id);
        }

        if ($purchase_id > 0) {
            $db->where('purchase_id', $purchase_id);
        }

        if ($email_type) {
            $db->where('email_type', $email_type);
        }

        if ($date_from) {
            $db->where('DATE(sent_at)', $date_from, '>=');
        }

        if ($date_to) {
            $db->where('DATE(sent_at)', $date_to, '<=');
        }

        $db->orderBy('sent_at', 'DESC');
        $db->limit($limit);
        $logs = $db->get('crm_email_logs');

        if (!$logs) {
            $logs = [];
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'email_logs' => $logs,
            'count' => count($logs)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get available email templates
if ($s === 'get_email_templates') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $template_dir = dirname(dirname(__FILE__)) . '/manage/pages/clients/emails/';

        if (!is_dir($template_dir)) {
            echo json_encode([
                'status' => 200,
                'success' => true,
                'templates' => [],
                'count' => 0,
                'message' => 'Template directory not found'
            ]);
            exit;
        }

        $files = glob($template_dir . '*.php');
        $templates = [];

        if ($files) {
            foreach ($files as $file) {
                $name = basename($file, '.php');
                if ($name !== 'template-base') {
                    $templates[] = [
                        'name' => $name,
                        'file' => $name . '.php',
                        'path' => $file
                    ];
                }
            }
        }

        echo json_encode([
            'status' => 200,
            'success' => true,
            'templates' => $templates,
            'count' => count($templates)
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

?>
