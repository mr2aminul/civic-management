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
    global $pdo;

    $client_id = intval($_GET['client_id'] ?? 0);
    $purchase_id = intval($_GET['purchase_id'] ?? 0);
    $action_type = trim($_GET['action_type'] ?? '');
    $action_category = trim($_GET['action_category'] ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to = trim($_GET['date_to'] ?? '');
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    $query = "SELECT * FROM crm_audit_trail WHERE 1=1";
    $params = [];

    if ($client_id > 0) {
        $query .= " AND client_id = ?";
        $params[] = $client_id;
    }

    if ($purchase_id > 0) {
        $query .= " AND purchase_id = ?";
        $params[] = $purchase_id;
    }

    if ($action_type) {
        $query .= " AND action_type = ?";
        $params[] = $action_type;
    }

    if ($action_category) {
        $query .= " AND action_category = ?";
        $params[] = $action_category;
    }

    if ($date_from) {
        $query .= " AND DATE(action_date) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $query .= " AND DATE(action_date) <= ?";
        $params[] = $date_to;
    }

    $query .= " ORDER BY action_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $trail = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $count_query = "SELECT COUNT(*) FROM crm_audit_trail WHERE 1=1";
    if ($client_id > 0) $count_query .= " AND client_id = $client_id";
    if ($purchase_id > 0) $count_query .= " AND purchase_id = $purchase_id";
    if ($action_type) $count_query .= " AND action_type = '$action_type'";
    if ($action_category) $count_query .= " AND action_category = '$action_category'";
    if ($date_from) $count_query .= " AND DATE(action_date) >= '$date_from'";
    if ($date_to) $count_query .= " AND DATE(action_date) <= '$date_to'";

    $total = $pdo->query($count_query)->fetchColumn();

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
    global $pdo;

    $client_id = intval($_POST['client_id'] ?? 0);
    $purchase_id = intval($_POST['purchase_id'] ?? 0);
    $email_type = trim($_POST['email_type'] ?? ''); // payment_reminder, payment_received, schedule_report, invoice, birthday_wish
    $recipient_email = trim($_POST['recipient_email'] ?? '');
    $recipient_phone = trim($_POST['recipient_phone'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $recipient_type = trim($_POST['recipient_type'] ?? 'client'); // client, nominee, co_buyer
    $template_name = trim($_POST['template_name'] ?? '');
    $template_variables = $_POST['template_variables'] ?? '{}'; // JSON
    $scheduled_send = trim($_POST['scheduled_send_date'] ?? '');

    if (!$client_id || !$email_type || !$recipient_email) {
        throw new Exception('Missing required fields');
    }

    // Parse template variables if string
    if (is_string($template_variables)) {
        $template_variables = json_decode($template_variables, true);
    }

    // Validate email
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    $stmt = $pdo->prepare("
        INSERT INTO crm_email_queue 
        (client_id, purchase_id, email_type, recipient_email, recipient_phone, recipient_name, 
         recipient_type, template_name, template_variables, queue_date, scheduled_send_date, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'pending', ?)
    ");

    $stmt->execute([
        $client_id, $purchase_id ?: null, $email_type, $recipient_email, 
        $recipient_phone, $recipient_name, $recipient_type, $template_name,
        json_encode($template_variables), $scheduled_send ?: null, $user_id
    ]);

    $queue_id = $pdo->lastInsertId();

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
    global $pdo;

    $client_id = intval($_GET['client_id'] ?? 0);
    $status = trim($_GET['status'] ?? 'pending');
    $email_type = trim($_GET['email_type'] ?? '');
    $limit = intval($_GET['limit'] ?? 50);

    $query = "SELECT * FROM crm_email_queue WHERE status = ?";
    $params = [$status];

    if ($client_id > 0) {
        $query .= " AND client_id = ?";
        $params[] = $client_id;
    }

    if ($email_type) {
        $query .= " AND email_type = ?";
        $params[] = $email_type;
    }

    $query .= " ORDER BY queue_date ASC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    global $pdo;

    $queue_id = intval($_POST['queue_id'] ?? 0);
    if (!$queue_id) throw new Exception('Queue ID required');

    $pdo->beginTransaction();

    try {
        $queue = $pdo->query("SELECT * FROM crm_email_queue WHERE id = $queue_id")->fetch(PDO::FETCH_ASSOC);
        if (!$queue) throw new Exception('Queue item not found');

        // Get template
        $template_path = dirname(dirname(__FILE__)) . '/manage/pages/clients/emails/' . $queue['template_name'] . '.php';

        if (!file_exists($template_path)) {
            throw new Exception('Email template not found: ' . $queue['template_name']);
        }

        // Build email content
        $template_variables = json_decode($queue['template_variables'], true);
        ob_start();
        include $template_path;
        $email_body = ob_get_clean();

        // Extract subject (first line of template)
        $subject = $template_variables['subject'] ?? 'Payment Notification from Civic BD Group';

        // Send email (using PHP mail or SendGrid/SMTP configured in your system)
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@civicbdgroup.com\r\n";
        $headers .= "Reply-To: support@civicbdgroup.com\r\n";

        $mail_sent = mail($queue['recipient_email'], $subject, $email_body, $headers);

        if (!$mail_sent) {
            throw new Exception('Failed to send email');
        }

        // Update queue status
        $pdo->prepare("
            UPDATE crm_email_queue 
            SET status = 'sent', send_date = NOW()
            WHERE id = ?
        ")->execute([$queue_id]);

        // Log email send
        $pdo->prepare("
            INSERT INTO crm_email_logs 
            (queue_id, client_id, purchase_id, email_type, recipient_email, recipient_phone, 
             recipient_name, subject, status, sent_by_user)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent', ?)
        ")->execute([
            $queue_id, $queue['client_id'], $queue['purchase_id'], $queue['email_type'],
            $queue['recipient_email'], $queue['recipient_phone'], $queue['recipient_name'],
            $subject, $user_id
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully',
            'recipient' => $queue['recipient_email']
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();

        // Log failed send
        $pdo->prepare("
            UPDATE crm_email_queue 
            SET status = 'failed', failure_reason = ?, retry_count = retry_count + 1
            WHERE id = ?
        ")->execute([$e->getMessage(), $queue_id]);

        throw $e;
    }
}

// ========================================
// GET EMAIL LOGS
// ========================================
function getEmailLogs() {
    global $pdo;

    $client_id = intval($_GET['client_id'] ?? 0);
    $email_type = trim($_GET['email_type'] ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to = trim($_GET['date_to'] ?? '');
    $limit = intval($_GET['limit'] ?? 100);

    $query = "SELECT * FROM crm_email_logs WHERE 1=1";
    $params = [];

    if ($client_id > 0) {
        $query .= " AND client_id = ?";
        $params[] = $client_id;
    }

    if ($email_type) {
        $query .= " AND email_type = ?";
        $params[] = $email_type;
    }

    if ($date_from) {
        $query .= " AND DATE(sent_at) >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $query .= " AND DATE(sent_at) <= ?";
        $params[] = $date_to;
    }

    $query .= " ORDER BY sent_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
