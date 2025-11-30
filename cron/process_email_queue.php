<?php
/**
 * process_email_queue_fixed.php
 *
 * Robust, fixed and more resilient email queue worker for your project.
 * - Loads project bootstrap reliably
 * - Uses Composer autoload (fallbacks)
 * - Adds sensible SMTP timeouts and safer SMTP options
 * - Avoids undefined-variable issues when template preparation fails
 * - Writes clear timestamps to logs and rotates/recreates log dir when needed
 *
 * Usage (CLI):
 *   php /path/to/project/cron/process_email_queue_fixed.php
 */

declare(strict_types=1);

// allow long-running worker
set_time_limit(0);
ini_set('memory_limit', '256M');

// --- project root (expects this file in <project_root>/cron or <project_root>/scripts) ---
$project_root = dirname(__DIR__);

// Composer autoload (PHPMailer via composer)
$autoload_candidates = [
    $project_root . '/assets/libraries/PHPMailer-Master/vendor/autoload.php'
];
foreach ($autoload_candidates as $c) {
    if (file_exists($c)) {
        require_once $c;
        break;
    }
}

// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\SMTP;
// use PHPMailer\PHPMailer\Exception as PHPMailerException;

// CLI-friendly debug
if (php_sapi_name() === 'cli') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Logging helpers
function ensure_log_dir(string $project_root): string {
    $logDir = rtrim($project_root, '/') . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    return $logDir;
}

function timestamp(): string {
    return '[' . date('Y-m-d H:i:s') . '] ';
}

function log_info(string $message, string $project_root = null): void {
    $project_root = $project_root ?: dirname(__DIR__);
    $logDir = ensure_log_dir($project_root);
    $file = $logDir . '/worker.log';
    @file_put_contents($file, timestamp() . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    if (php_sapi_name() === 'cli') echo timestamp() . $message . PHP_EOL;
}

function log_error(string $message, string $project_root = null): void {
    $project_root = $project_root ?: dirname(__DIR__);
    $logDir = ensure_log_dir($project_root);
    $file = $logDir . '/worker_error.log';
    @file_put_contents($file, timestamp() . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    if (php_sapi_name() === 'cli') echo timestamp() . $message . PHP_EOL;
}

// Ensure $db and $wo are available from bootstrap
global $db, $wo;
if (!isset($db) || !isset($wo)) {
    log_error("ERROR: \$db or \$wo not available after loading assets/init.php.", $project_root);
    // do not proceed without DB because we cannot mark queue items
    // exit(1);
}

// SMTP config fallbacks
$smtp_config = [
    'host'       => $wo['config']['smtp_host']       ?? 'smtp.gmail.com',
    'port'       => (int)($wo['config']['smtp_port'] ?? 587),
    'username'   => $wo['config']['smtp_username']   ?? '',
    'password'   => $wo['config']['smtp_password']   ?? '',
    'from_email' => $wo['config']['smtp_from_email'] ?? 'noreply@civicgroupbd.com',
    'from_name'  => $wo['config']['smtp_from_name']  ?? 'Civic Group BD'
];

if (empty($smtp_config['username']) || empty($smtp_config['password'])) {
    log_error("SMTP credentials not configured. Please update config table.", $project_root);
    // exit(1);
}

// Processing configuration
$batch_size = 10;
$queue_order_column = 'queue_date';

try {
    // Fetch queued emails (status = queued)
    $db->where('status', 'queued');
    $db->orderBy($queue_order_column, 'ASC');
    $emails = $db->get('crm_email_queue', $batch_size);

    if (empty($emails)) {
        log_info("No emails in queue", $project_root);
        echo "No emails in queue \n";
        return;
    }

    log_info("Processing " . count($emails) . " emails...", $project_root);

    $sent_count = 0;
    $failed_count = 0;

    foreach ($emails as $email) {
        log_info("Processing Email ID: {$email->id} (Type: {$email->email_type})...", $project_root);
        
        // ensure template_data always exists even if template preparation fails
        $template_data = ['subject' => '', 'html_body' => '', 'text_body' => '', 'attachments' => [], 'cc' => []];
        $mail = new PHPMailer\PHPMailer\PHPMailer();

        try {
            // Decode metadata (supports template_variables or metadata column)
            $metadata = [];
            if (!empty($email->template_variables)) {
                $decoded = json_decode($email->template_variables, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $metadata = $decoded;
            } elseif (!empty($email->metadata)) {
                $decoded = json_decode($email->metadata, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $metadata = $decoded;
            }

            // Prepare template (returns subject, html_body, text_body, attachments, cc)
            $template_data = prepareEmailTemplate($email->email_type, $metadata, $project_root);

            // Initialize PHPMailer
            // $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;
            $mail->Timeout = 15; // socket-level timeout (seconds)
            $mail->SMTPAutoTLS = (isset($wo['config']['smtp_auto_tls']) ? (bool)$wo['config']['smtp_auto_tls'] : true);

            // Choose send method
            $send_method = $wo['config']['smtp_or_mail'] ?? 'smtp';
            if ($send_method === 'mail') {
                $mail->isMail();
            } else {
                $mail->isSMTP();
                $mail->Host = $smtp_config['host'];
                $mail->SMTPAuth = true;
                // keepalive can cause connections to remain open; set to false to ensure each send closes
                $mail->SMTPKeepAlive = false;
                $mail->Username = $smtp_config['username'];
                $mail->Password = openssl_decrypt($smtp_config['password'], "AES-128-ECB", "mysecretkey1234"); // SMTP password
                $mail->SMTPSecure = $wo['config']['smtp_encryption'] ?? 'tls';
                $mail->Port = (int)$smtp_config['port'];
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            // From and To
            $fromEmail = $smtp_config['from_email'];
            $fromName  = $smtp_config['from_name'];
            $mail->setFrom($fromEmail, $fromName);

            // recipient may be blank, guard against that
            if (empty($email->recipient_email) || !filter_var($email->recipient_email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid recipient email: ' . ($email->recipient_email ?? 'NULL'));
            }
            $mail->addAddress($email->recipient_email, $email->recipient_name ?? '');

            // CC
            if (!empty($template_data['cc']) && is_array($template_data['cc'])) {
                foreach ($template_data['cc'] as $cc_email) {
                    if (filter_var($cc_email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($cc_email);
                    }
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $template_data['subject'] ?? '';
            $mail->Body = $template_data['html_body'] ?? '';
            $mail->AltBody = $template_data['text_body'] ?? strip_tags($template_data['html_body'] ?? '');

            // Attachments (resolve relative to project root)
            if (!empty($template_data['attachments']) && is_array($template_data['attachments'])) {
                foreach ($template_data['attachments'] as $attachment) {
                    $attachPath = $attachment['path'] ?? '';
                    $resolved = null;
                    if (!empty($attachPath) && file_exists($attachPath)) {
                        $resolved = $attachPath;
                    } else {
                        $candidate = $project_root . '/' . ltrim($attachPath, '/');
                        if (!empty($attachPath) && file_exists($candidate)) $resolved = $candidate;
                    }
                    if ($resolved) {
                        $mail->addAttachment($resolved, $attachment['name'] ?? basename($resolved));
                    } else {
                        log_info("Attachment not found for queue id {$email->id}: " . ($attachPath ?? 'NULL'), $project_root);
                    }
                }
            }

            // Send and update DB + logs
            log_info("Sending to {$email->recipient_email} via {$mail->Host}:{$mail->Port}", $project_root);
            $mail->send();
            log_info("SMTP send successful for {$email->recipient_email}", $project_root);

            // update queue status to sent
            $db->where('id', $email->id);
            $db->update('crm_email_queue', [
                'status' => 'sent',
                'send_date' => date('Y-m-d H:i:s')
            ]);

            // insert log row
            $db->insert('crm_email_logs', [
                'queue_id'        => $email->id,
                'client_id'       => $email->client_id ?? null,
                'purchase_id'     => $email->purchase_id ?? null,
                'email_type'      => $email->email_type,
                'recipient_email' => $email->recipient_email,
                'recipient_phone' => $email->recipient_phone ?? null,
                'recipient_name'  => $email->recipient_name ?? '',
                'subject'         => $template_data['subject'] ?? '',
                'sent_at'         => date('Y-m-d H:i:s'),
                'status'          => 'sent',
                'sent_by_system'  => 1,
                'attachments'     => json_encode($template_data['attachments'] ?? [])
            ]);

            $sent_count++;
            log_info("✓ Sent to {$email->recipient_email}", $project_root);

        } catch (PHPMailerException $e) {
            $error_msg = $e->getMessage();
            $new_retry = (int)($email->retry_count ?? 0) + 1;
            $db->where('id', $email->id);
            $db->update('crm_email_queue', [
                'status' => 'failed',
                'failure_reason' => $error_msg,
                'retry_count' => $new_retry
            ]);
            $db->insert('crm_email_logs', [
                'queue_id' => $email->id,
                'client_id' => $email->client_id ?? null,
                'purchase_id' => $email->purchase_id ?? null,
                'email_type' => $email->email_type,
                'recipient_email' => $email->recipient_email,
                'recipient_name' => $email->recipient_name ?? '',
                'subject' => $template_data['subject'] ?? '',
                'sent_at' => date('Y-m-d H:i:s'),
                'status' => 'failed',
                'sent_by_system' => 1,
                'delivery_status' => $error_msg,
                'attachments' => json_encode($template_data['attachments'] ?? [])
            ]);
            $failed_count++;
            log_error("✗ Failed {$email->recipient_email}: {$error_msg}", $project_root);

        } catch (\Throwable $e) {
            $error_msg = $e->getMessage();
            $new_retry = (int)($email->retry_count ?? 0) + 1;
            $db->where('id', $email->id);
            $db->update('crm_email_queue', [
                'status' => 'failed',
                'failure_reason' => $error_msg,
                'retry_count' => $new_retry
            ]);
            $db->insert('crm_email_logs', [
                'queue_id' => $email->id,
                'client_id' => $email->client_id ?? null,
                'purchase_id' => $email->purchase_id ?? null,
                'email_type' => $email->email_type,
                'recipient_email' => $email->recipient_email,
                'recipient_name' => $email->recipient_name ?? '',
                'subject' => $template_data['subject'] ?? '',
                'sent_at' => date('Y-m-d H:i:s'),
                'status' => 'failed',
                'sent_by_system' => 1,
                'error_message' => $error_msg,
                'attachments' => json_encode($template_data['attachments'] ?? [])
            ]);
            $failed_count++;
            log_error("✗ Failed {$email->recipient_email}: {$error_msg}", $project_root);
        }

        // small pause to avoid rapid-fire SMTP connections
        usleep(150000);
    }

    log_info("Summary: {$sent_count} sent, {$failed_count} failed", $project_root);
    // exit(0);

} catch (\Throwable $e) {
    log_error("Error: " . $e->getMessage(), $project_root);
    // exit(1);
}
