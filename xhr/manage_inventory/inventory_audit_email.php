<?php
/**
 * Audit Trail & Email System
 * Handles: Complete action logging, email queue, email sending, and compliance tracking
 * note: don't use $pdo, we have $db with $db = new MysqliDb($sqlConnect); and raw connection with $sqlConnect   = $wo["sqlConnect"] = mysqli_connect($sql_db_host, $sql_db_user, $sql_db_pass, $sql_db_name, 3306);
 */

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id && $action !== 'get_audit_trail' && $action !== 'get_email_logs') {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    switch ($action) {
        case 'get_audit_trail':
            getAuditTrail();
            break;

        case 'queue_email':
            queueEmail($user_id);
            break;

        case 'get_queued_emails':
            getQueuedEmails();
            break;

        case 'send_email':
            sendEmail($user_id);
            break;

        case 'get_email_logs':
            getEmailLogs();
            break;

        case 'get_email_templates':
            getEmailTemplates();
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ========================================
// GET AUDIT TRAIL
// ========================================
function getAuditTrail() {
    global $db;

    $client_id = intval($_GET['client_id'] ?? 0);
    $purchase_id = intval($_GET['purchase_id'] ?? 0);
    $action_type = trim($_GET['action_type'] ?? '');
    $action_category = trim($_GET['action_category'] ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to = trim($_GET['date_to'] ?? '');
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    $db->where('1', '1', '=');

    if ($client_id > 0) {
        $db->where('client_id', $client_id, '=');
    }

    if ($purchase_id > 0) {
        $db->where('purchase_id', $purchase_id, '=');
    }

    if ($action_type) {
        $db->where('action_type', $action_type, '=');
    }

    if ($action_category) {
        $db->where('action_category', $action_category, '=');
    }

    if ($date_from) {
        $db->where('DATE(action_date)', $date_from, '>=');
    }

    if ($date_to) {
        $db->where('DATE(action_date)', $date_to, '<=');
    }

    $db->orderBy('action_date', 'DESC');
    $db->limit($limit, $offset);
    $trail = $db->get('crm_audit_trail');

    if (!$trail) {
        $trail = [];
    }

    $db->where('1', '1', '=');

    if ($client_id > 0) {
        $db->where('client_id', $client_id, '=');
    }

    if ($purchase_id > 0) {
        $db->where('purchase_id', $purchase_id, '=');
    }

    if ($action_type) {
        $db->where('action_type', $action_type, '=');
    }

    if ($action_category) {
        $db->where('action_category', $action_category, '=');
    }

    if ($date_from) {
        $db->where('DATE(action_date)', $date_from, '>=');
    }

    if ($date_to) {
        $db->where('DATE(action_date)', $date_to, '<=');
    }

    $total = $db->getValue('crm_audit_trail', 'COUNT(*)');

    echo json_encode([
        'success' => true,
        'audit_trail' => $trail,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

// ========================================
// QUEUE EMAIL
// ========================================
function queueEmail($user_id) {
    global $db;

    $client_id = intval($_POST['client_id'] ?? 0);
    $purchase_id = intval($_POST['purchase_id'] ?? 0);
    $email_type = trim($_POST['email_type'] ?? '');
    $recipient_email = trim($_POST['recipient_email'] ?? '');
    $recipient_phone = trim($_POST['recipient_phone'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $recipient_type = trim($_POST['recipient_type'] ?? 'client');
    $template_name = trim($_POST['template_name'] ?? '');
    $template_variables = $_POST['template_variables'] ?? '{}';
    $scheduled_send = trim($_POST['scheduled_send_date'] ?? '');

    if (!$client_id || !$email_type || !$recipient_email) {
        throw new Exception('Missing required fields');
    }

    if (is_string($template_variables)) {
        $template_variables = json_decode($template_variables, true);
    }

    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
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
        'created_by' => $user_id
    ];

    $queue_id = $db->insert('crm_email_queue', $data);

    if (!$queue_id) {
        throw new Exception('Failed to queue email');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Email queued successfully',
        'queue_id' => $queue_id
    ]);
}

// ========================================
// GET QUEUED EMAILS
// ========================================
function getQueuedEmails() {
    global $db;

    $client_id = intval($_GET['client_id'] ?? 0);
    $status = trim($_GET['status'] ?? 'pending');
    $email_type = trim($_GET['email_type'] ?? '');
    $limit = intval($_GET['limit'] ?? 50);

    $db->where('status', $status, '=');

    if ($client_id > 0) {
        $db->where('client_id', $client_id, '=');
    }

    if ($email_type) {
        $db->where('email_type', $email_type, '=');
    }

    $db->orderBy('queue_date', 'ASC');
    $db->limit($limit);
    $emails = $db->get('crm_email_queue');

    if (!$emails) {
        $emails = [];
    }

    echo json_encode([
        'success' => true,
        'queued_emails' => $emails,
        'count' => count($emails)
    ]);
}

// ========================================
// SEND EMAIL
// ========================================
function sendEmail($user_id) {
    global $db, $sqlConnect;

    $queue_id = intval($_POST['queue_id'] ?? 0);
    if (!$queue_id) throw new Exception('Queue ID required');

    $db->where('id', $queue_id, '=');
    $queue = $db->getOne('crm_email_queue');

    if (!$queue) throw new Exception('Queue item not found');

    try {
        $template_path = dirname(dirname(__FILE__)) . '/manage/pages/clients/emails/' . $queue['template_name'] . '.php';

        if (!file_exists($template_path)) {
            throw new Exception('Email template not found: ' . $queue['template_name']);
        }

        $template_variables = json_decode($queue['template_variables'], true);
        ob_start();
        include $template_path;
        $email_body = ob_get_clean();

        $subject = $template_variables['subject'] ?? 'Payment Notification from Civic BD Group';

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@civicbdgroup.com\r\n";
        $headers .= "Reply-To: support@civicbdgroup.com\r\n";

        $mail_sent = mail($queue['recipient_email'], $subject, $email_body, $headers);

        if (!$mail_sent) {
            throw new Exception('Failed to send email');
        }

        $db->where('id', $queue_id, '=');
        $db->update('crm_email_queue', [
            'status' => 'sent',
            'send_date' => date('Y-m-d H:i:s')
        ]);

        $log_data = [
            'queue_id' => $queue_id,
            'client_id' => $queue['client_id'],
            'purchase_id' => $queue['purchase_id'],
            'email_type' => $queue['email_type'],
            'recipient_email' => $queue['recipient_email'],
            'recipient_phone' => $queue['recipient_phone'],
            'recipient_name' => $queue['recipient_name'],
            'subject' => $subject,
            'status' => 'sent',
            'sent_by_user' => $user_id,
            'sent_at' => date('Y-m-d H:i:s')
        ];

        $db->insert('crm_email_logs', $log_data);

        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully',
            'recipient' => $queue['recipient_email']
        ]);

    } catch (Exception $e) {
        $db->where('id', $queue_id, '=');
        $db->update('crm_email_queue', [
            'status' => 'failed',
            'failure_reason' => $e->getMessage(),
            'retry_count' => $db->rawQuery("retry_count + 1")
        ]);

        throw $e;
    }
}

// ========================================
// GET EMAIL LOGS
// ========================================
function getEmailLogs() {
    global $db;

    $client_id = intval($_GET['client_id'] ?? 0);
    $email_type = trim($_GET['email_type'] ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to = trim($_GET['date_to'] ?? '');
    $limit = intval($_GET['limit'] ?? 100);

    $db->where('1', '1', '=');

    if ($client_id > 0) {
        $db->where('client_id', $client_id, '=');
    }

    if ($email_type) {
        $db->where('email_type', $email_type, '=');
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
        'success' => true,
        'email_logs' => $logs,
        'count' => count($logs)
    ]);
}

// ========================================
// GET AVAILABLE EMAIL TEMPLATES
// ========================================
function getEmailTemplates() {
    $template_dir = dirname(dirname(__FILE__)) . '/manage/pages/clients/emails/';
    
    if (!is_dir($template_dir)) {
        echo json_encode([
            'success' => false,
            'templates' => [],
            'message' => 'Template directory not found'
        ]);
        return;
    }

    $files = glob($template_dir . '*.php');
    $templates = [];

    foreach ($files as $file) {
        $name = basename($file, '.php');
        if ($name !== 'template-base') { // Skip base templates
            $templates[] = [
                'name' => $name,
                'file' => $name . '.php'
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'templates' => $templates,
        'count' => count($templates)
    ]);
}
?>
