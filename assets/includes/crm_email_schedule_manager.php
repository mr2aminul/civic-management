<?php
/**
 * CRM Email & Schedule Management
 * 
 * Handles:
 * - Queuing payment schedules for email sending
 * - Sending emails to clients
 * - Auto-send configuration
 * - Manual send triggers
 */

// ==================================================
// QUEUE SCHEDULE FOR EMAIL
// ==================================================

/**
 * Queue payment schedule for email sending
 * 
 * @param int $purchase_id The booking helper ID
 * @param string $email_type Type: payment_schedule, refund_notification, transfer_notice
 * @param string $recipient_email Client email
 * @param array $schedule_data Schedule data for email
 * @param array $options Additional options (delay, auto_send, etc)
 * @param int $created_by User ID
 * @return array Result with queue entry ID
 */
function queue_schedule_for_email($purchase_id, $email_type, $recipient_email, $schedule_data = [], $options = [], $created_by = null) {
    global $db;
    
    if (!$purchase_id || empty($email_type) || empty($recipient_email)) {
        return ['status' => 400, 'message' => 'Missing required parameters'];
    }
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 400, 'message' => 'Invalid email address'];
    }
    
    try {
        $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            return ['status' => 404, 'message' => 'Purchase not found'];
        }
        
        // Determine email subject
        $subject = 'Payment Schedule Update - CRM';
        if ($email_type === 'payment_schedule') {
            $subject = 'Your Updated Payment Schedule';
        } elseif ($email_type === 'refund_notification') {
            $subject = 'Refund Processing Update';
        } elseif ($email_type === 'transfer_notice') {
            $subject = 'Ownership Transfer Notification';
        }
        
        // Prepare payload
        $payload = [
            'purchase_id' => $purchase_id,
            'email_type' => $email_type,
            'schedule_data' => $schedule_data,
            'options' => $options,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Determine scheduled send time
        $scheduled_send_time = null;
        if (isset($options['delay_hours']) && $options['delay_hours'] > 0) {
            $scheduled_send_time = date('Y-m-d H:i:s', strtotime("+{$options['delay_hours']} hours"));
        }
        
        // Create queue entry
        $queue_data = [
            'purchase_id' => $purchase_id,
            'client_id' => (int)$helper->client_id,
            'email_type' => $email_type,
            'recipient_email' => $recipient_email,
            'subject' => $subject,
            'email_body_template' => "email/{$email_type}.phtml",
            'payload' => json_encode($payload),
            'status' => 'pending',
            'retry_count' => 0,
            'scheduled_send_time' => $scheduled_send_time,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $queue_id = $db->insert('crm_email_queue', $queue_data);
        
        if (!$queue_id) {
            return ['status' => 500, 'message' => 'Failed to queue email'];
        }
        
        return [
            'status' => 200,
            'message' => 'Email queued successfully',
            'queue_id' => $queue_id,
            'scheduled_for' => $scheduled_send_time
        ];
        
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==================================================
// SEND QUEUED EMAILS (CRON JOB)
// ==================================================

/**
 * Process pending emails from queue (called by cron)
 * 
 * @param int $max_retries Maximum retry attempts
 * @param int $max_emails Maximum emails to process in one run
 * @return array Result with counts
 */
function process_email_queue($max_retries = 3, $max_emails = 50) {
    global $db;
    
    try {
        // Get pending emails that are ready to send
        $pending_emails = $db->where('status', 'pending')
                             ->where('retry_count', $max_retries, '<')
                             ->where('scheduled_send_time', null)
                             ->orWhere('scheduled_send_time', date('Y-m-d H:i:s'), '<=')
                             ->limit($max_emails)
                             ->get('crm_email_queue');
        
        if (!$pending_emails || count($pending_emails) === 0) {
            return [
                'status' => 200,
                'message' => 'No pending emails to process',
                'processed' => 0,
                'failed' => 0
            ];
        }
        
        $processed = 0;
        $failed = 0;
        
        foreach ($pending_emails as $email_entry) {
            $payload = json_decode($email_entry->payload, true);
            
            $sent = send_email_with_template(
                $email_entry->recipient_email,
                $email_entry->subject,
                $email_entry->email_body_template,
                $payload
            );
            
            if ($sent) {
                // Mark as sent
                $db->where('id', (int)$email_entry->id)->update('crm_email_queue', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $processed++;
            } else {
                // Increment retry count
                $new_retry = (int)$email_entry->retry_count + 1;
                $status = ($new_retry >= $max_retries) ? 'failed' : 'pending';
                
                $db->where('id', (int)$email_entry->id)->update('crm_email_queue', [
                    'retry_count' => $new_retry,
                    'status' => $status,
                    'last_error' => 'SMTP send failed',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $failed++;
            }
        }
        
        return [
            'status' => 200,
            'message' => "Processed {$processed} emails, {$failed} failed",
            'processed' => $processed,
            'failed' => $failed,
            'total_queued' => count($pending_emails)
        ];
        
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==================================================
// SEND EMAIL WITH TEMPLATE
// ==================================================

/**
 * Send email using template
 * 
 * @param string $recipient Email recipient
 * @param string $subject Email subject
 * @param string $template_file Template file path
 * @param array $data Template data
 * @return bool Success status
 */
function send_email_with_template($recipient, $subject, $template_file, $data = []) {
    try {
        // Use PHPMailer or built-in mail() function
        $message = render_email_template($template_file, $data);
        
        if (empty($message)) {
            error_log("Failed to render email template: {$template_file}");
            return false;
        }
        
        // Headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@civicgroup.com\r\n";
        $headers .= "Reply-To: support@civicgroup.com\r\n";
        
        // Send
        $result = mail($recipient, $subject, $message, $headers);
        
        if (!$result) {
            error_log("Failed to send email to: {$recipient}");
        }
        
        return (bool)$result;
        
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return false;
    }
}

// ==================================================
// RENDER EMAIL TEMPLATE
// ==================================================

/**
 * Render email template with data
 * 
 * @param string $template_file Template file path
 * @param array $data Template data
 * @return string Rendered HTML
 */
function render_email_template($template_file, $data = []) {
    try {
        // Look for template in manage/pages/emails/
        $template_path = __DIR__ . '/../../manage/pages/emails/' . basename($template_file);
        
        if (!file_exists($template_path)) {
            error_log("Template not found: {$template_path}");
            return '';
        }
        
        // Extract data to variables
        extract($data, EXTR_PREFIX_ALL, 'email');
        
        // Buffer output
        ob_start();
        include($template_path);
        $html = ob_get_clean();
        
        return $html;
        
    } catch (Exception $e) {
        error_log("Template render error: " . $e->getMessage());
        return '';
    }
}

// ==================================================
// SEND SCHEDULE EMAIL MANUALLY
// ==================================================

/**
 * Send schedule email to client manually
 * 
 * @param int $purchase_id The booking helper ID
 * @param string $recipient_email Override email (optional)
 * @param int $created_by User ID
 * @return array Result status
 */
function send_schedule_email_manual($purchase_id, $recipient_email = null, $created_by = null) {
    global $db;
    
    try {
        $helper = $db->where('id', (int)$purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            return ['status' => 404, 'message' => 'Purchase not found'];
        }
        
        $client = GetCustomerById((int)$helper->client_id);
        if (!$client) {
            return ['status' => 404, 'message' => 'Client not found'];
        }
        
        $email = $recipient_email ?: $client['email'];
        if (empty($email)) {
            return ['status' => 400, 'message' => 'No email address available'];
        }
        
        // Get schedule data
        $schedule = $db->where('purchase_id', $purchase_id)
                      ->where('status', 99, '!=')
                      ->orderBy('due_date', 'ASC')
                      ->get('crm_payment_schedule');
        
        $schedule_data = [];
        if ($schedule) {
            foreach ($schedule as $row) {
                $schedule_data[] = [
                    'particular' => $row->particular,
                    'due_date' => $row->due_date,
                    'amount' => (float)$row->installment_amount,
                    'paid' => (float)$row->paid_amount,
                    'status' => $row->status
                ];
            }
        }
        
        // Queue email
        $queue_result = queue_schedule_for_email(
            $purchase_id,
            'payment_schedule',
            $email,
            [
                'client' => $client,
                'helper' => $helper,
                'schedule' => $schedule_data
            ],
            ['send_immediately' => true],
            $created_by
        );
        
        if ($queue_result['status'] === 200) {
            // Try to send immediately
            $sent = send_email_with_template(
                $email,
                'Your Payment Schedule - CRM',
                'email/payment_schedule.phtml',
                [
                    'client' => $client,
                    'helper' => $helper,
                    'schedule' => $schedule_data
                ]
            );
            
            return [
                'status' => 200,
                'message' => $sent ? 'Email sent successfully' : 'Email queued (will be sent by cron)',
                'queue_id' => $queue_result['queue_id'],
                'sent_immediately' => $sent
            ];
        }
        
        return $queue_result;
        
    } catch (Exception $e) {
        return ['status' => 500, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==================================================
// GET AUTO-SEND CONFIGURATION
// ==================================================

/**
 * Get auto-send configuration for a client or all
 * 
 * @param int $client_id Optional: filter by client
 * @return array Configuration settings
 */
function get_email_autosend_config($client_id = null) {
    global $db;
    
    try {
        // For now, return default configuration
        // This can be extended to store per-client settings
        return [
            'enabled' => true,
            'send_on_schedule_change' => true,
            'send_on_payment_due' => true,
            'send_on_refund_start' => true,
            'send_on_transfer' => true,
            'send_days_before_due' => 5,
            'max_emails_per_day' => 100,
            'email_time' => '09:00' // Send at 9 AM
        ];
        
    } catch (Exception $e) {
        error_log("Error getting email config: " . $e->getMessage());
        return [];
    }
}

// ==================================================
// UPDATE AUTO-SEND CONFIGURATION
// ==================================================

/**
 * Update auto-send configuration
 * 
 * @param array $config Configuration array
 * @param int $client_id Optional: set for specific client
 * @param int $updated_by User ID
 * @return array Result status
 */
function update_email_autosend_config($config, $client_id = null, $updated_by = null) {
    // Store in database or config file as needed
    // For now, this is a placeholder
    
    return [
        'status' => 200,
        'message' => 'Configuration updated successfully',
        'config' => $config
    ];
}

// Helper function to queue emails
function queueEmail($client_id, $email_type, $metadata = []) {
    global $db;
    
    // Get client email
    $client = $db->where('id', $client_id)->getOne('crm_customers', ['email', 'name']);
    if (!$client || empty($client->email)) {
        return false;
    }

    $email_data = [
        'client_id' => $client_id,
        'recipient_email' => $client->email,
        'email_type' => $email_type,
        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        'status' => 'queued',
        'created_at' => date('Y-m-d H:i:s')
    ];

    return $db->insert('crm_email_queue', $email_data);
}
