<?php
/**
 * Email Queue & Management Module
 * Handles: email queueing, sending, template selection, email logs
 */

// allow long-running worker
set_time_limit(0);
ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');
    // Emails
    if ($s == 'get_pending_emails' || $s == 'get_emails') {
    try {
        // Check both GET and POST for parameters
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : (isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0);
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : (isset($_GET['client_id']) ? intval($_GET['client_id']) : 0);
        $email_type = isset($_POST['email_type']) ? Wo_Secure($_POST['email_type']) : (isset($_GET['email_type']) ? Wo_Secure($_GET['email_type']) : '');
        $status = isset($_POST['status']) ? Wo_Secure($_POST['status']) : (isset($_GET['status']) ? Wo_Secure($_GET['status']) : '');

        // Build where conditions - all filters are optional
        if ($purchase_id) {
            $db->where('purchase_id', $purchase_id);
        }
        if ($client_id) {
            $db->where('client_id', $client_id);
        }
        if ($email_type) {
            $db->where('email_type', $email_type);
        }

        // Status filtering
        if (!empty($status)) {

            if ($status === 'scheduled' || $status === 'pending') {
                // queue-type statuses
                $db->where('status', ['queued', 'scheduled', 'failed'], 'IN');
            }
            elseif ($status === 'send') {
                // send only
                $db->where('status', 'send');
            }
            else {
                // exact match for any other status
                $db->where('status', $status);
            }
        }
        
        $db->orderBy('queue_date', 'DESC');
        $emails = $db->get('crm_email_queue', null, [
            'id', 'recipient_email', 'recipient_name', 'email_type', 'status', 
            'queue_date', 'scheduled_send_date', 'retry_count', 'template_variables'
        ]);

        $result = [];
        if (!empty($emails)) {
            foreach ($emails as $email) {
                $subject = '';
                $body = '';
                if (!empty($email->template_variables)) {
                    $vars = json_decode($email->template_variables, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $subject = $vars['subject'] ?? '';
                        $body = $vars['body'] ?? ($vars['message'] ?? '');
                    }
                }

                $result[] = [
                    'id' => $email->id,
                    'recipient_email' => $email->recipient_email,
                    'recipient_name' => $email->recipient_name,
                    'email_type' => $email->email_type,
                    'status' => $email->status,
                    'queue_date' => $email->queue_date,
                    'scheduled_send_date' => $email->scheduled_send_date,
                    'retry_count' => $email->retry_count,
                    'subject' => $subject,
                    'message_body' => $body
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
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        
        // Handle frontend payload mapping
        $recipient_type = isset($_POST['recipient_type']) ? Wo_Secure($_POST['recipient_type']) : 'custom';
        $custom_recipient = isset($_POST['custom_recipient']) ? Wo_Secure($_POST['custom_recipient']) : '';
        $subject = isset($_POST['subject']) ? Wo_Secure($_POST['subject']) : '';
        $body = isset($_POST['body']) ? $_POST['body'] : (isset($_POST['message']) ? $_POST['message'] : '');
        $scheduled_date = isset($_POST['scheduled_date']) ? Wo_Secure($_POST['scheduled_date']) : null;

        $recipient_email = '';
        $recipient_name = '';
        
        // Fetch recipient email based on type
        if ($recipient_type === 'current_client' || $recipient_type === 'select_client') {
            if ($client_id > 0) {
                $client = $db->where('id', $client_id)->getOne('crm_customers', ['email', 'name']);
                if ($client) {
                    $recipient_email = $client->email;
                    $recipient_name = trim($client->name);
                }
            }
        } elseif ($recipient_type === 'current_purchase' || $recipient_type === 'select_purchase') {
            if ($purchase_id > 0) {
                // Get client_id from purchase if not provided
                if ($client_id == 0) {
                    $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['client_id']);
                    if ($purchase) {
                        $client_id = $purchase->client_id;
                    }
                }
                // Get client email
                if ($client_id > 0) {
                    $client = $db->where('id', $client_id)->getOne('crm_customers', ['email', 'name']);
                    if ($client) {
                        $recipient_email = $client->email;
                        $recipient_name = trim($client->name);
                    }
                }
            }
        } elseif ($recipient_type === 'custom') {
            $recipient_email = $custom_recipient;
        }
        // Validation
        if (!$recipient_email) {
             echo json_encode(['status' => 400, 'message' => 'Recipient Email required or could not be found']);
             exit;
        }

        // If purchase_id provided, get client_id (this block is now redundant due to new logic above, but keeping for safety if client_id is still 0)
        if ($purchase_id > 0 && $client_id == 0) {
            $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['client_id']);
            if ($purchase) {
                $client_id = $purchase->client_id;
            }
        }

        // Validate email format
        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid email format']);
            exit;
        }

        $data = [
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'recipient_email' => $recipient_email,
            'recipient_name' => $recipient_name,
            'email_type' => $recipient_type,
            'recipient_type' => $recipient_type,
            'template_name' => 'custom',
            'template_variables' => json_encode([
                'subject' => $subject,
                'body' => $body,
                'message' => $body,
                'client_name' => $recipient_name
            ]),
            'status' => $scheduled_date ? 'scheduled' : 'queued',
            'scheduled_send_date' => $scheduled_date,
            'queue_date' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('crm_email_queue', $data);

        if ($id) {
            echo json_encode(['status' => 200, 'message' => 'Email queued successfully', 'queue_id' => $id]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to queue email: ' . $db->getLastError()]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// --- project root (expects this file in <project_root>/cron or <project_root>/scripts) ---
$project_root = dirname(__DIR__);

// Composer autoload (PHPMailer via composer)
require_once './assets/libraries/PHPMailer-Master/vendor/autoload.php';
// echo $autoload_candidates;

if ($s === 'send_email') {
    try {
        global $db, $wo;

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

        // prepare template (falls back to inline/default if prepareEmailTemplate exists)
        $template = [];
        if (function_exists('prepareEmailTemplate')) {
            $metadata = [];
            if (!empty($email->template_variables)) {
                $decoded = json_decode($email->template_variables, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $metadata = $decoded;
            } elseif (!empty($email->metadata)) {
                $decoded = json_decode($email->metadata, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $metadata = $decoded;
            }

            $template = prepareEmailTemplate($email->email_type, $metadata, dirname(__DIR__));
        }

        // fallback to fields from queue
        $subject = $template['subject'] ?? ($email->subject ?? '');
        $htmlBody = $template['html_body'] ?? ($email->body ?? '');
        $textBody = $template['text_body'] ?? strip_tags($htmlBody);
        $attachments = $template['attachments'] ?? [];

        // PHPMailer setup
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        
        try {
            $mail->CharSet = 'UTF-8';
            $send_method = $wo['config']['smtp_or_mail'] ?? 'smtp';

            if ($send_method === 'mail') {
                $mail->isMail();
            } else {
                $mail->isSMTP();
                $mail->Host = $wo['config']['smtp_host'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->SMTPKeepAlive = false;
                $mail->Username = $wo['config']['smtp_username'] ?? '';
                $encrypted_pass = $wo['config']['smtp_password'] ?? '';
                if (!empty($encrypted_pass)) {
                    // keep same decryption approach used in worker; adjust key if your project uses another
                    $mail->Password = @openssl_decrypt($encrypted_pass, "AES-128-ECB", "mysecretkey1234");
                } else {
                    $mail->Password = '';
                }
                $mail->SMTPSecure = $wo['config']['smtp_encryption'] ?? 'tls';
                $mail->Port = (int)($wo['config']['smtp_port'] ?? 587);
                $mail->Timeout = 15;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            $fromEmail = $wo['config']['smtp_from_email'] ?? 'noreply@civicgroupbd.com';
            $fromName  = $wo['config']['smtp_from_name'] ?? 'Civic Group BD';
            $mail->setFrom($fromEmail, $fromName);

            // recipient guard
            if (empty($email->recipient_email) || !filter_var($email->recipient_email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid recipient email: ' . ($email->recipient_email ?? 'NULL'));
            }
            $mail->addAddress($email->recipient_email, $email->recipient_name ?? '');

            // CC from template
            if (!empty($template['cc']) && is_array($template['cc'])) {
                foreach ($template['cc'] as $cc) {
                    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) $mail->addCC($cc);
                }
            }

            // content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            // attachments
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $att) {
                    $path = $att['path'] ?? '';
                    $name = $att['name'] ?? null;
                    $resolved = null;
                    if (!empty($path) && file_exists($path)) {
                        $resolved = $path;
                    } else {
                        $candidate = dirname(__DIR__) . '/' . ltrim($path, '/');
                        if (!empty($path) && file_exists($candidate)) $resolved = $candidate;
                    }
                    if ($resolved) {
                        $mail->addAttachment($resolved, $name ?? basename($resolved));
                    }
                }
            }

            // send
            $mail->send();

            // mark sent
            $db->where('id', $email_id);
            $db->update('crm_email_queue', [
                'status'    => 'sent',
                'send_date' => date('Y-m-d H:i:s'),
                'failure_reason' => null
            ]);

            $db->insert('crm_email_logs', [
                'queue_id'        => $email->id,
                'client_id'       => $email->client_id ?? null,
                'purchase_id'     => $email->purchase_id ?? null,
                'email_type'      => $email->email_type ?? '',
                'recipient_email' => $email->recipient_email,
                'recipient_name'  => $email->recipient_name ?? '',
                'subject'         => $subject,
                'sent_at'         => date('Y-m-d H:i:s'),
                'status'          => 'sent',
                'sent_by_system'  => 1,
                'attachments'     => json_encode($attachments)
            ]);

            echo json_encode(['status' => 200, 'message' => 'Email sent successfully']);
            exit;

        } catch (PHPMailerException $e) {
            // PHPMailer error
            $err = $e->getMessage();
            $retry_count = intval($email->retry_count ?? 0) + 1;
            $new_status = $retry_count < 3 ? 'pending' : 'failed';

            $db->where('id', $email_id);
            $db->update('crm_email_queue', [
                'status' => $new_status,
                'retry_count' => $retry_count,
                'failure_reason' => $err
            ]);

            $db->insert('crm_email_logs', [
                'queue_id'        => $email->id,
                'client_id'       => $email->client_id ?? null,
                'purchase_id'     => $email->purchase_id ?? null,
                'email_type'      => $email->email_type ?? '',
                'recipient_email' => $email->recipient_email,
                'recipient_name'  => $email->recipient_name ?? '',
                'subject'         => $subject,
                'sent_at'         => date('Y-m-d H:i:s'),
                'status'          => 'failed',
                'sent_by_system'  => 1,
                'error_message'   => $err,
                'attachments'     => json_encode($attachments)
            ]);

            echo json_encode(['status' => 500, 'message' => 'Failed to send email', 'error' => $err]);
            exit;
        }

    } catch (\Throwable $ex) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $ex->getMessage()]);
        exit;
    }
}


if ($s === 'send_all_pending_emails') {
    try {
        global $db, $wo;

        $now = date('Y-m-d H:i:s');
        $db->where('status', 'pending');
        // want (scheduled_send_date IS NULL OR scheduled_send_date <= now)
        $db->where('scheduled_send_date', 'IS NULL', '');
        $db->orWhere('scheduled_send_date', $now, '<=');
        $pending_emails = $db->get('crm_email_queue');

        $sent_count = 0;
        $failed_count = 0;

        if (!empty($pending_emails)) {
            foreach ($pending_emails as $email) {
                // same send logic as single email but isolate exceptions per-email
                try {
                    // prepare template if available
                    $template = [];
                    if (function_exists('prepareEmailTemplate')) {
                        $metadata = [];
                        if (!empty($email->template_variables)) {
                            $decoded = json_decode($email->template_variables, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $metadata = $decoded;
                        } elseif (!empty($email->metadata)) {
                            $decoded = json_decode($email->metadata, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $metadata = $decoded;
                        }
                        $template = prepareEmailTemplate($email->email_type, $metadata, dirname(__DIR__));
                    }

                    $subject = $template['subject'] ?? ($email->subject ?? '');
                    $htmlBody = $template['html_body'] ?? ($email->body ?? '');
                    $textBody = $template['text_body'] ?? strip_tags($htmlBody);
                    $attachments = $template['attachments'] ?? [];

                    $mail = new PHPMailer(true);
                    $mail->CharSet = 'UTF-8';
                    $send_method = $wo['config']['smtp_or_mail'] ?? 'smtp';

                    if ($send_method === 'mail') {
                        $mail->isMail();
                    } else {
                        $mail->isSMTP();
                        $mail->Host = $wo['config']['smtp_host'] ?? 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->SMTPKeepAlive = false;
                        $mail->Username = $wo['config']['smtp_username'] ?? '';
                        $encrypted_pass = $wo['config']['smtp_password'] ?? '';
                        if (!empty($encrypted_pass)) {
                            $mail->Password = @openssl_decrypt($encrypted_pass, "AES-128-ECB", "mysecretkey1234");
                        } else {
                            $mail->Password = '';
                        }
                        $mail->SMTPSecure = $wo['config']['smtp_encryption'] ?? 'tls';
                        $mail->Port = (int)($wo['config']['smtp_port'] ?? 587);
                        $mail->Timeout = 15;
                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            ]
                        ];
                    }

                    $fromEmail = $wo['config']['smtp_from_email'] ?? 'noreply@civicgroupbd.com';
                    $fromName  = $wo['config']['smtp_from_name'] ?? 'Civic Group BD';
                    $mail->setFrom($fromEmail, $fromName);

                    if (empty($email->recipient_email) || !filter_var($email->recipient_email, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('Invalid recipient email: ' . ($email->recipient_email ?? 'NULL'));
                    }
                    $mail->addAddress($email->recipient_email, $email->recipient_name ?? '');

                    if (!empty($template['cc']) && is_array($template['cc'])) {
                        foreach ($template['cc'] as $cc) {
                            if (filter_var($cc, FILTER_VALIDATE_EMAIL)) $mail->addCC($cc);
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $htmlBody;
                    $mail->AltBody = $textBody;

                    if (!empty($attachments) && is_array($attachments)) {
                        foreach ($attachments as $att) {
                            $path = $att['path'] ?? '';
                            $name = $att['name'] ?? null;
                            $resolved = null;
                            if (!empty($path) && file_exists($path)) {
                                $resolved = $path;
                            } else {
                                $candidate = dirname(__DIR__) . '/' . ltrim($path, '/');
                                if (!empty($path) && file_exists($candidate)) $resolved = $candidate;
                            }
                            if ($resolved) {
                                $mail->addAttachment($resolved, $name ?? basename($resolved));
                            }
                        }
                    }

                    $mail->send();

                    // mark sent
                    $db->where('id', $email->id);
                    $db->update('crm_email_queue', [
                        'status' => 'sent',
                        'send_date' => date('Y-m-d H:i:s'),
                        'failure_reason' => null
                    ]);

                    $db->insert('crm_email_logs', [
                        'queue_id'        => $email->id,
                        'client_id'       => $email->client_id ?? null,
                        'purchase_id'     => $email->purchase_id ?? null,
                        'email_type'      => $email->email_type ?? '',
                        'recipient_email' => $email->recipient_email,
                        'recipient_name'  => $email->recipient_name ?? '',
                        'subject'         => $subject,
                        'sent_at'         => date('Y-m-d H:i:s'),
                        'status'          => 'sent',
                        'sent_by_system'  => 1,
                        'attachments'     => json_encode($attachments)
                    ]);

                    $sent_count++;

                } catch (PHPMailerException $e) {
                    $err = $e->getMessage();
                    $retry_count = intval($email->retry_count ?? 0) + 1;
                    $new_status = $retry_count < 3 ? 'pending' : 'failed';

                    $db->where('id', $email->id);
                    $db->update('crm_email_queue', [
                        'status' => $new_status,
                        'retry_count' => $retry_count,
                        'failure_reason' => $err
                    ]);

                    $db->insert('crm_email_logs', [
                        'queue_id'        => $email->id,
                        'client_id'       => $email->client_id ?? null,
                        'purchase_id'     => $email->purchase_id ?? null,
                        'email_type'      => $email->email_type ?? '',
                        'recipient_email' => $email->recipient_email,
                        'recipient_name'  => $email->recipient_name ?? '',
                        'subject'         => $subject ?? ($email->subject ?? ''),
                        'sent_at'         => date('Y-m-d H:i:s'),
                        'status'          => 'failed',
                        'sent_by_system'  => 1,
                        'error_message'   => $err,
                        'attachments'     => json_encode($attachments ?? [])
                    ]);

                    $failed_count++;

                } catch (\Throwable $e) {
                    $err = $e->getMessage();
                    $retry_count = intval($email->retry_count ?? 0) + 1;
                    $new_status = $retry_count < 3 ? 'pending' : 'failed';

                    $db->where('id', $email->id);
                    $db->update('crm_email_queue', [
                        'status' => $new_status,
                        'retry_count' => $retry_count,
                        'failure_reason' => $err
                    ]);

                    $db->insert('crm_email_logs', [
                        'queue_id'        => $email->id,
                        'client_id'       => $email->client_id ?? null,
                        'purchase_id'     => $email->purchase_id ?? null,
                        'email_type'      => $email->email_type ?? '',
                        'recipient_email' => $email->recipient_email,
                        'recipient_name'  => $email->recipient_name ?? '',
                        'subject'         => $subject ?? ($email->subject ?? ''),
                        'sent_at'         => date('Y-m-d H:i:s'),
                        'status'          => 'failed',
                        'sent_by_system'  => 1,
                        'error_message'   => $err,
                        'attachments'     => json_encode($attachments ?? [])
                    ]);

                    $failed_count++;
                }

                // small pause to prevent SMTP rate issues
                usleep(150000);
            }
        }

        echo json_encode([
            'status' => 200,
            'message' => "Processed pending emails",
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ]);
        exit;

    } catch (\Throwable $e) {
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

// 5. GET ALL CLIENTS (for compose modal)
if ($s == 'get_all_clients') {
    try {
        $db->orderBy('first_name', 'ASC');
        $clients = $db->get('wo_users', null, ['user_id', 'first_name', 'last_name', 'email', 'phone_number']);
        
        $result = [];
        if (!empty($clients)) {
            foreach ($clients as $client) {
                $result[] = [
                    'id' => $client->user_id,
                    'name' => trim($client->first_name . ' ' . $client->last_name),
                    'email' => $client->email,
                    'phone' => $client->phone_number
                ];
            }
        }
        
        echo json_encode(['status' => 200, 'clients' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// 6. GET CLIENT PURCHASES (for compose modal)
if ($s == 'get_client_purchases') {
    try {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        
        if (!$client_id) {
            echo json_encode(['status' => 400, 'message' => 'Client ID required']);
            exit;
        }
        
        $db->where('client_id', $client_id);
        $db->orderBy('id', 'DESC');
        $purchases = $db->get('wo_booking_helper', null, [
            'id', 'file_num', 'project_name', 'plot_qty', 'plot_size', 'created_at'
        ]);
        
        $result = [];
        if (!empty($purchases)) {
            foreach ($purchases as $purchase) {
                $result[] = [
                    'id' => $purchase->id,
                    'file_num' => $purchase->file_num,
                    'project_name' => $purchase->project_name,
                    'plot_qty' => $purchase->plot_qty,
                    'plot_size' => $purchase->plot_size,
                    'created_at' => $purchase->created_at
                ];
            }
        }
        
        echo json_encode(['status' => 200, 'purchases' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// 7. GET PURCHASE NOMINEES (for compose modal)
if ($s == 'get_purchase_nominees') {
    try {
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
            exit;
        }
        
        $db->where('purchase_id', $purchase_id);
        $db->orderBy('id', 'ASC');
        $nominees = $db->get('wo_booking_nominee', null, [
            'id', 'nominee_name', 'nominee_email', 'nominee_phone', 'nominee_relation'
        ]);
        
        $result = [];
        if (!empty($nominees)) {
            foreach ($nominees as $nominee) {
                $result[] = [
                    'id' => $nominee->id,
                    'name' => $nominee->nominee_name,
                    'email' => $nominee->nominee_email,
                    'phone' => $nominee->nominee_phone,
                    'relation' => $nominee->nominee_relation
                ];
            }
        }
        
        echo json_encode(['status' => 200, 'nominees' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// 8. GET CLIENT INFO (for compose modal)
if ($s == 'get_client_info') {
    try {
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        
        if (!$client_id) {
            echo json_encode(['status' => 400, 'message' => 'Client ID required']);
            exit;
        }
        
        $db->where('user_id', $client_id);
        $client = $db->getOne('wo_users', ['user_id', 'first_name', 'last_name', 'email', 'phone_number']);
        
        if (!$client) {
            echo json_encode(['status' => 404, 'message' => 'Client not found']);
            exit;
        }
        
        $result = [
            'id' => $client->user_id,
            'name' => trim($client->first_name . ' ' . $client->last_name),
            'email' => $client->email,
            'phone' => $client->phone_number
        ];
        
        echo json_encode(['status' => 200, 'client' => $result]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
