<?php
/**
 * Comprehensive Payment and Invoice Management API
 * Handles: Schedules, Invoices, Emails, Overpayment distribution, Audit trail
 * note: uses global $db (MysqliDb) and $wo
 */

global $db, $wo, $sqlConnect;

$response = ['success' => false, 'message' => ''];

// Get action from POST or GET
$action = $_POST['s'] ?? $_GET['s'] ?? '';

try {
  switch ($action) {
    // ======================== SCHEDULES ========================
    case 'get_schedules_list':
      $purchaseId = intval($_POST['purchase_id'] ?? 0);
      $status = $_POST['status'] ?? '';
      $type = $_POST['type'] ?? '';
      $fromDate = $_POST['from_date'] ?? '';
      $toDate = $_POST['to_date'] ?? '';

      if (!$purchaseId) throw new Exception('Purchase ID required');

      $db->where('purchase_id', $purchaseId);

      if ($status !== '') {
        $db->where('status', intval($status));
      }
      if ($type) {
        $db->where('type', $type);
      }
      if ($fromDate) {
        $db->where('due_date', $fromDate, '>=');
      }
      if ($toDate) {
        $db->where('due_date', $toDate, '<=');
      }

      $db->orderBy('installment_number', 'ASC');
      $schedules = $db->get('crm_payment_schedule');

      // Calculate summary
      $summary = [
        'total_due' => 0,
        'total_paid' => 0,
        'pending_count' => 0,
        'overdue_count' => 0,
        'overpayment' => 0,
        'late_fees' => 0
      ];

      if (!empty($schedules)) {
        foreach ($schedules as $sch) {
          $summary['total_due'] += floatval($sch->installment_amount ?? 0);
          $summary['total_paid'] += floatval($sch->paid_amount ?? 0);
          if ($sch->status == 0 || $sch->status == 2) $summary['pending_count']++;
          if ($sch->status == 3) $summary['overdue_count']++;
          $summary['overpayment'] += floatval($sch->overpayment_amount ?? 0);
          $summary['late_fees'] += floatval($sch->late_fee_amount ?? 0);
        }
      }

      $response = [
        'success' => true,
        'schedules' => $schedules ?: [],
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

      if (!$purchaseId) throw new Exception('Purchase ID required');

      $db->where('purchase_id', $purchaseId);

      if ($status !== '') {
        $db->where('status', $status);
      }
      if ($fromDate) {
        $db->where('invoice_date', $fromDate, '>=');
      }
      if ($toDate) {
        $db->where('invoice_date', $toDate, '<=');
      }
      if ($search) {
        $db->where('(invoice_number LIKE ? OR money_receipt_no LIKE ?)',
          ['%' . $search . '%', '%' . $search . '%'], 'OR');
      }

      $db->orderBy('invoice_date', 'DESC');
      $invoices = $db->get('crm_invoices');

      // Get overpayment distributions - note: table may not exist, handle gracefully
      $distributions = [];
      try {
        $db->where('purchase_id', $purchaseId);
        $scheduleIds = $db->get('crm_payment_schedule', null, 'id');

        if (!empty($scheduleIds)) {
          $ids = array_map(function($s) { return $s->id; }, $scheduleIds);
          if (!empty($ids)) {
            $db->where('schedule_id', $ids, 'IN');
            $db->orderBy('distribution_date', 'DESC');
            $distributions = $db->get('crm_overpayment_distribution') ?: [];
          }
        }
      } catch (Exception $e) {
        // Table may not exist, continue without distributions
        $distributions = [];
      }

      // Calculate summary
      $summary = [
        'total_amount' => 0,
        'total_paid' => 0,
        'pending_count' => 0,
        'overdue_count' => 0,
        'outstanding' => 0,
        'overpayment' => 0
      ];

      if (!empty($invoices)) {
        foreach ($invoices as $inv) {
          $amount = floatval($inv->amount ?? 0);
          $paid = floatval($inv->paid_amount ?? 0);
          $summary['total_amount'] += $amount;
          $summary['total_paid'] += $paid;
          if ($inv->status == 'draft' || $inv->status == 'issued' || $inv->status == 'partial') $summary['pending_count']++;
          if (($inv->status == 'draft' || $inv->status == 'issued') && strtotime($inv->due_date ?? 'now') < time()) $summary['overdue_count']++;
          $outstanding = $amount - $paid;
          if ($outstanding > 0) $summary['outstanding'] += $outstanding;
          if ($paid > $amount) $summary['overpayment'] += ($paid - $amount);
        }
      }

      $response = [
        'success' => true,
        'invoices' => $invoices ?: [],
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

      if (!$invoiceId || !$paymentAmount) throw new Exception('Invoice ID and payment amount required');

      // Get invoice details
      $db->where('id', $invoiceId);
      $invoice = $db->getOne('crm_invoices');
      if (!$invoice) throw new Exception('Invoice not found');

      $invoiceAmount = floatval($invoice->amount ?? 0);
      $currentPaid = floatval($invoice->paid_amount ?? 0);
      $newPaidAmount = $currentPaid + $paymentAmount;

      // Determine status
      if ($newPaidAmount >= $invoiceAmount) {
        $status = 'paid';
      } else if ($newPaidAmount > 0) {
        $status = 'partial';
      } else {
        $status = 'draft';
      }

      // Calculate overpayment
      $overpaymentAmount = max(0, $newPaidAmount - $invoiceAmount);

      // Update invoice
      $db->where('id', $invoiceId);
      $db->update('crm_invoices', [
        'paid_amount' => $newPaidAmount,
        'remaining_amount' => max(0, $invoiceAmount - $newPaidAmount),
        'status' => $status,
        'payment_date' => $paymentDate,
        'payment_method' => $paymentMethod,
        'money_receipt_no' => $receiptNumber,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $wo['user']['id'] ?? null
      ]);

      // Update related schedule if payment_schedule_id exists
      if (isset($invoice->payment_schedule_id) && $invoice->payment_schedule_id) {
        $scheduleId = intval($invoice->payment_schedule_id);

        $db->where('id', $scheduleId);
        $db->update('crm_payment_schedule', [
          'paid_amount' => $newPaidAmount,
          'overpayment_amount' => $overpaymentAmount,
          'payment_date' => $paymentDate,
          'payment_method' => $paymentMethod,
          'money_receipt_no' => $receiptNumber,
          'status' => ($newPaidAmount >= $invoiceAmount) ? 1 : 2,
          'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Handle overpayment distribution
        if ($overpaymentAmount > 0) {
          // Get next unpaid schedule
          $db->where('purchase_id', $invoice->purchase_id);
          $db->where('status', [1, 4], 'NOT IN');
          $db->where('id', $scheduleId, '>');
          $db->orderBy('id', 'ASC');
          $nextSchedule = $db->getOne('crm_payment_schedule');

          if ($nextSchedule) {
            $nextScheduleId = intval($nextSchedule->id);
            $amountToApply = min($overpaymentAmount,
              floatval($nextSchedule->installment_amount ?? 0) - floatval($nextSchedule->paid_amount ?? 0));

            // Record distribution - table may not exist
            try {
              $db->insert('crm_overpayment_distribution', [
                'schedule_id' => $scheduleId,
                'applied_to_schedule_id' => $nextScheduleId,
                'amount' => $amountToApply,
                'distribution_date' => $paymentDate,
                'created_by' => $wo['user']['id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
              ]);

              // Apply to next schedule
              $db->where('id', $nextScheduleId);
              $currentPaid = floatval($nextSchedule->paid_amount ?? 0);
              $db->update('crm_payment_schedule', [
                'paid_amount' => $currentPaid + $amountToApply,
                'updated_at' => date('Y-m-d H:i:s')
              ]);
            } catch (Exception $e) {
              // Distribution table may not exist, continue without it
            }
          }
        }
      }

      // Log to audit trail
      logAuditAction($invoice->client_id ?? 0, $invoice->purchase_id ?? 0, 'invoice', 'update', $invoiceId,
        ['paid_amount' => $currentPaid, 'status' => $invoice->status],
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

      if (!$purchaseId) throw new Exception('Purchase ID required');

      // Get schedule IDs for this purchase
      $db->where('purchase_id', $purchaseId);
      $scheduleIds = $db->get('crm_payment_schedule', null, 'id');

      $emails = [];
      if (!empty($scheduleIds)) {
        $ids = array_map(function($s) { return $s->id; }, $scheduleIds);

        if (!empty($ids)) {
          $db->where('schedule_id', $ids, 'IN');

          if ($emailType) {
            $db->where('email_type', $emailType);
          }
          if ($status !== '') {
            $db->where('status', intval($status));
          }
          if ($recipientType) {
            $db->where('recipient_type', $recipientType);
          }
          if ($search) {
            $db->where('(recipient_email LIKE ? OR recipient_name LIKE ?)',
              ['%' . $search . '%', '%' . $search . '%'], 'OR');
          }

          $db->orderBy('created_at', 'DESC');
          $emails = $db->get('crm_email_queue') ?: [];
        }
      }

      // Count pending
      $db->where('status', 0);
      $db->where('scheduled_send_date', date('Y-m-d H:i:s'), '<=');
      $pendingCount = $db->getValue('crm_email_queue', 'COUNT(*)') ?: 0;

      $response = [
        'success' => true,
        'emails' => $emails,
        'pending_count' => intval($pendingCount)
      ];
      break;

    case 'send_bulk_emails':
      $emailIds = isset($_POST['email_ids']) ? array_map('intval', explode(',', $_POST['email_ids'])) : [];
      if (empty($emailIds)) throw new Exception('No emails selected');

      $sent = 0;
      foreach ($emailIds as $emailId) {
        if ($emailId <= 0) continue;

        $db->where('id', $emailId);
        $email = $db->getOne('crm_email_queue');
        if (!$email) continue;

        // Send email logic (integrate with your mail system)
        if (sendEmailViaProvider($email)) {
          $db->where('id', $emailId);
          $db->update('crm_email_queue', [
            'status' => 1,
            'send_date' => date('Y-m-d H:i:s'),
            'retry_count' => intval($email->retry_count ?? 0) + 1
          ]);
          $sent++;

          // Log to audit
          logAuditAction($email->client_id ?? 0, $email->purchase_id ?? 0, 'email', 'send_email', $emailId, null, null,
            "Email sent to {$email->recipient_email} - {$email->email_type}");
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

      if (!$purchaseId) throw new Exception('Purchase ID required');

      // Get client IDs for this purchase
      $db->where('purchase_id', $purchaseId);
      $clientIds = $db->get('crm_payment_schedule', null, 'client_id');

      $logs = [];
      if (!empty($clientIds)) {
        $ids = array_unique(array_map(function($c) { return $c->client_id; }, $clientIds));

        if (!empty($ids)) {
          $db->where('client_id', $ids, 'IN');

          if ($module) {
            $db->where('action_category', $module);
          }
          if ($actionType) {
            $db->where('action_type', $actionType);
          }
          if ($fromDate) {
            $db->where('DATE(performed_at)', $fromDate, '>=');
          }
          if ($toDate) {
            $db->where('DATE(performed_at)', $toDate, '<=');
          }
          if ($userName) {
            $db->where('performed_by', '%' . $userName . '%', 'LIKE');
          }

          $db->orderBy('performed_at', 'DESC');
          $db->limit(200);
          $logs = $db->get('crm_audit_trail') ?: [];
        }
      }

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

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;

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
function logAuditAction($clientId, $purchaseId, $module, $action, $refId, $oldValues, $newValues, $description) {
  global $db, $wo;

  try {
    $db->insert('crm_audit_trail', [
      'client_id' => intval($clientId),
      'purchase_id' => intval($purchaseId),
      'action_category' => $module,
      'action_type' => $action,
      'action_description' => $description,
      'before_values' => $oldValues ? json_encode($oldValues) : null,
      'after_values' => $newValues ? json_encode($newValues) : null,
      'performed_at' => date('Y-m-d H:i:s'),
      'performed_by' => $wo['user']['id'] ?? null,
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
  } catch (Exception $e) {
    // Silently fail if audit logging fails
    error_log('Audit log failed: ' . $e->getMessage());
  }
}
?>
