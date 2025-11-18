<?php
/**
 * Comprehensive Payment and Invoice Management API
 * Path: xhr/manage_inventory_complete.php
 * Handles: Schedules, Invoices, Emails, Overpayment distribution, Audit trail
 */
 
$response = ['success' => false, 'message' => ''];

try {
  $db = new Database();

  switch ($action) {
    // ======================== SCHEDULES ========================
    case 'get_schedules_list':
      $purchaseId = intval($_POST['purchase_id'] ?? 0);
      $status = $_POST['status'] ?? '';
      $type = $_POST['type'] ?? '';
      $fromDate = $_POST['from_date'] ?? '';
      $toDate = $_POST['to_date'] ?? '';

      $query = "SELECT * FROM crm_payment_schedule WHERE purchase_id = ?";
      $params = [$purchaseId];

      if ($status !== '') {
        $query .= " AND status = ?";
        $params[] = intval($status);
      }
      if ($type) {
        $query .= " AND type = ?";
        $params[] = $type;
      }
      if ($fromDate) {
        $query .= " AND due_date >= ?";
        $params[] = $fromDate;
      }
      if ($toDate) {
        $query .= " AND due_date <= ?";
        $params[] = $toDate;
      }

      $query .= " ORDER BY installment_number ASC";

      $schedules = $db->query($query, $params)->fetchAll();

      // Calculate summary
      $summary = [
        'total_due' => 0,
        'total_paid' => 0,
        'pending_count' => 0,
        'overdue_count' => 0,
        'overpayment' => 0,
        'late_fees' => 0
      ];

      foreach ($schedules as $sch) {
        $summary['total_due'] += floatval($sch['installment_amount']);
        $summary['total_paid'] += floatval($sch['paid_amount']);
        if ($sch['status'] == 0 || $sch['status'] == 2) $summary['pending_count']++;
        if ($sch['status'] == 3) $summary['overdue_count']++;
        $summary['overpayment'] += floatval($sch['overpayment_amount'] ?? 0);
        $summary['late_fees'] += floatval($sch['late_fee_amount'] ?? 0);
      }

      $response = [
        'success' => true,
        'schedules' => $schedules,
        'summary' => $summary
      ];
      break;

    // ======================== INVOICES ========================
    case 'get_invoices_list':
      $purchaseId = intval($_POST['purchase_id'] ?? 0);
      $status = $_POST['status'] ?? '';
      $fromDate = $_POST['from_date'] ?? '';
      $toDate = $_POST['to_date'] ?? '';
      $search = $_POST['search'] ?? '';

      $query = "SELECT * FROM crm_invoices WHERE purchase_id = ?";
      $params = [$purchaseId];

      if ($status !== '') {
        $query .= " AND status = ?";
        $params[] = intval($status);
      }
      if ($fromDate) {
        $query .= " AND invoice_date >= ?";
        $params[] = $fromDate;
      }
      if ($toDate) {
        $query .= " AND invoice_date <= ?";
        $params[] = $toDate;
      }
      if ($search) {
        $query .= " AND (invoice_number LIKE ? OR money_receipt_no LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
      }

      $query .= " ORDER BY invoice_date DESC";
      $invoices = $db->query($query, $params)->fetchAll();

      // Get overpayment distributions
      $distQuery = "SELECT * FROM crm_overpayment_distribution WHERE schedule_id IN (
        SELECT id FROM crm_payment_schedule WHERE purchase_id = ?
      ) ORDER BY distribution_date DESC";
      $distributions = $db->query($distQuery, [$purchaseId])->fetchAll();

      // Calculate summary
      $summary = [
        'total_amount' => 0,
        'total_paid' => 0,
        'pending_count' => 0,
        'overdue_count' => 0,
        'outstanding' => 0,
        'overpayment' => 0
      ];

      foreach ($invoices as $inv) {
        $amount = floatval($inv['amount']);
        $paid = floatval($inv['paid_amount']);
        $summary['total_amount'] += $amount;
        $summary['total_paid'] += $paid;
        if ($inv['status'] == 0 || $inv['status'] == 2) $summary['pending_count']++;
        if ($inv['status'] == 0 && strtotime($inv['due_date']) < time()) $summary['overdue_count']++;
        $outstanding = $amount - $paid;
        if ($outstanding > 0) $summary['outstanding'] += $outstanding;
        if ($paid > $amount) $summary['overpayment'] += ($paid - $amount);
      }

      $response = [
        'success' => true,
        'invoices' => $invoices,
        'overpayment_distribution' => $distributions,
        'summary' => $summary
      ];
      break;

    // ======================== HANDLE OVERPAYMENT ========================
    case 'record_invoice_payment':
      $invoiceId = intval($_POST['invoice_id'] ?? 0);
      $paymentAmount = floatval($_POST['payment_amount'] ?? 0);
      $paymentMethod = $_POST['payment_method'] ?? '';
      $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
      $receiptNumber = $_POST['money_receipt_no'] ?? '';

      // Get invoice details
      $invoice = $db->query("SELECT * FROM crm_invoices WHERE id = ?", [$invoiceId])->fetch();
      if (!$invoice) throw new Exception('Invoice not found');

      $invoiceAmount = floatval($invoice['amount']);
      $currentPaid = floatval($invoice['paid_amount']);
      $newPaidAmount = $currentPaid + $paymentAmount;

      // Determine status
      if ($newPaidAmount >= $invoiceAmount) {
        $status = 3; // Paid
      } else if ($newPaidAmount > 0) {
        $status = 2; // Partial
      } else {
        $status = 0; // Pending
      }

      // Calculate overpayment
      $overpaymentAmount = max(0, $newPaidAmount - $invoiceAmount);

      // Update invoice
      $db->query(
        "UPDATE crm_invoices SET paid_amount = ?, status = ?, payment_date = ?, 
         payment_method = ?, money_receipt_no = ?, updated_at = NOW(), updated_by = ? 
         WHERE id = ?",
        [$newPaidAmount, $status, $paymentDate, $paymentMethod, $receiptNumber, $_SESSION['user_id'] ?? 0, $invoiceId]
      );

      // Update related schedule
      $scheduleId = $invoice['schedule_id'];
      $db->query(
        "UPDATE crm_payment_schedule SET paid_amount = ?, overpayment_amount = ?, 
         payment_date = ?, payment_method = ?, money_receipt_no = ?, updated_at = NOW() 
         WHERE id = ?",
        [$newPaidAmount, $overpaymentAmount, $paymentDate, $paymentMethod, $receiptNumber, $scheduleId]
      );

      // Handle overpayment distribution
      if ($overpaymentAmount > 0) {
        // Get next unpaid schedule
        $nextSchedule = $db->query(
          "SELECT * FROM crm_payment_schedule WHERE purchase_id = ? AND status != 1 AND status != 4 
           AND id > ? ORDER BY id ASC LIMIT 1",
          [$invoice['purchase_id'], $scheduleId]
        )->fetch();

        if ($nextSchedule) {
          $nextScheduleId = $nextSchedule['id'];
          $amountToApply = min($overpaymentAmount, floatval($nextSchedule['installment_amount']) - floatval($nextSchedule['paid_amount']));

          // Record distribution
          $db->query(
            "INSERT INTO crm_overpayment_distribution (schedule_id, applied_to_schedule_id, amount, distribution_date, created_by) 
             VALUES (?, ?, ?, ?, ?)",
            [$scheduleId, $nextScheduleId, $amountToApply, $paymentDate, $_SESSION['user_id'] ?? 0]
          );

          // Apply to next schedule
          $db->query(
            "UPDATE crm_payment_schedule SET paid_amount = paid_amount + ?, updated_at = NOW() WHERE id = ?",
            [$amountToApply, $nextScheduleId]
          );
        }
      }

      // Log to audit trail
      logAuditAction($db, $invoice['client_id'], 'invoice', 'update', $invoiceId, 
        ['paid_amount' => $currentPaid, 'status' => $invoice['status']], 
        ['paid_amount' => $newPaidAmount, 'status' => $status],
        "Payment recorded: ৳" . number_format($paymentAmount, 2));

      $response = [
        'success' => true,
        'message' => 'Payment recorded successfully',
        'overpayment_distributed' => $overpaymentAmount > 0
      ];
      break;

    // ======================== EMAIL OPERATIONS ========================
    case 'get_pending_emails':
      $purchaseId = intval($_POST['purchase_id'] ?? 0);
      $emailType = $_POST['email_type'] ?? '';
      $status = $_POST['status'] ?? '';
      $recipientType = $_POST['recipient_type'] ?? '';
      $search = $_POST['search'] ?? '';

      $query = "SELECT eq.*, p.client_id FROM crm_email_queue eq
                JOIN crm_payment_schedule p ON eq.schedule_id = p.id
                WHERE p.purchase_id = ?";
      $params = [$purchaseId];

      if ($emailType) {
        $query .= " AND eq.email_type = ?";
        $params[] = $emailType;
      }
      if ($status !== '') {
        $query .= " AND eq.status = ?";
        $params[] = intval($status);
      }
      if ($recipientType) {
        $query .= " AND eq.recipient_type = ?";
        $params[] = $recipientType;
      }
      if ($search) {
        $query .= " AND (eq.recipient_email LIKE ? OR eq.recipient_name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
      }

      $query .= " ORDER BY eq.created_at DESC";
      $emails = $db->query($query, $params)->fetchAll();

      // Count pending
      $pendingCount = $db->query(
        "SELECT COUNT(*) as cnt FROM crm_email_queue WHERE status = 0 AND scheduled_for <= NOW()"
      )->fetch()['cnt'];

      $response = [
        'success' => true,
        'emails' => $emails,
        'pending_count' => $pendingCount
      ];
      break;

    case 'send_bulk_emails':
      $emailIds = array_map('intval', explode(',', $_POST['email_ids'] ?? ''));
      if (empty($emailIds)) throw new Exception('No emails selected');

      $sent = 0;
      foreach ($emailIds as $emailId) {
        $email = $db->query("SELECT * FROM crm_email_queue WHERE id = ?", [$emailId])->fetch();
        if (!$email) continue;

        // Send email logic (integrate with your mail system)
        if (sendEmailViaProvider($email)) {
          $db->query(
            "UPDATE crm_email_queue SET status = 1, sent_at = NOW(), send_attempts = send_attempts + 1 WHERE id = ?",
            [$emailId]
          );
          $sent++;

          // Log to audit
          logAuditAction($db, $email['client_id'], 'email', 'send_email', $emailId, null, null,
            "Email sent to {$email['recipient_email']} - {$email['email_type']}");
        }
      }

      $response = [
        'success' => true,
        'message' => "$sent emails sent successfully",
        'sent_count' => $sent
      ];
      break;

    // ======================== AUDIT TRAIL ========================
    case 'get_audit_trail':
      $purchaseId = intval($_POST['purchase_id'] ?? 0);
      $module = $_POST['module'] ?? '';
      $actionType = $_POST['action'] ?? '';
      $fromDate = $_POST['from_date'] ?? '';
      $toDate = $_POST['to_date'] ?? '';
      $userName = $_POST['user_name'] ?? '';

      $query = "SELECT * FROM crm_audit_trail WHERE client_id IN (
                SELECT client_id FROM crm_payment_schedule WHERE purchase_id = ?
                )";
      $params = [$purchaseId];

      if ($module) {
        $query .= " AND module = ?";
        $params[] = $module;
      }
      if ($actionType) {
        $query .= " AND action = ?";
        $params[] = $actionType;
      }
      if ($fromDate) {
        $query .= " AND DATE(created_at) >= ?";
        $params[] = $fromDate;
      }
      if ($toDate) {
        $query .= " AND DATE(created_at) <= ?";
        $params[] = $toDate;
      }
      if ($userName) {
        $query .= " AND changed_by_name LIKE ?";
        $params[] = '%' . $userName . '%';
      }

      $query .= " ORDER BY created_at DESC LIMIT 200";
      $logs = $db->query($query, $params)->fetchAll();

      $response = [
        'success' => true,
        'audit_logs' => $logs
      ];
      break;

    default:
      throw new Exception('Invalid action: ' . $action);
  }

} catch (Exception $e) {
  $response['message'] = $e->getMessage();
  http_response_code(400);
}

echo json_encode($response);

/**
 * Helper function to send email via provider
 */
function sendEmailViaProvider($email) {
  // Integrate with your email provider (PHPMailer, Sendgrid, etc.)
  // For now, return true as placeholder
  return true;
}

/**
 * Helper function to log audit actions
 */
function logAuditAction($db, $clientId, $module, $action, $refId, $oldValues, $newValues, $description) {
  $db->query(
    "INSERT INTO crm_audit_trail (client_id, module, action, reference_id, reference_type, 
     old_values, new_values, description, changed_by, changed_by_name, ip_address, created_at)
     VALUES (?, ?, ?, ?, 'schedule', ?, ?, ?, ?, ?, ?, NOW())",
    [
      $clientId, $module, $action, $refId,
      $oldValues ? json_encode($oldValues) : null,
      $newValues ? json_encode($newValues) : null,
      $description,
      $_SESSION['user_id'] ?? 0,
      $_SESSION['user_name'] ?? 'System',
      $_SERVER['REMOTE_ADDR'] ?? ''
    ]
  );
}
?>
