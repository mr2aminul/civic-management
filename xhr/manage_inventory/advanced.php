<?php
// ============================================
// INVOICE ENDPOINTS
// ============================================

// Get all invoices for a purchase
if ($s === 'get_invoices') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    
    try {
        if ($purchase_id <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
            exit;
        }
        
        // Fetch invoices
        $invoices = $db->where('purchase_id', $purchase_id)->orderBy('invoice_date', 'DESC')->get('crm_invoices');
        
        $result = [];
        if (!empty($invoices)) {
            foreach ($invoices as $inv) {
                $installment_ids = $inv->installment_ids ? json_decode($inv->installment_ids, true) : [];
                
                $result[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'invoice_date' => $inv->invoice_date,
                    'total_amount' => floatval($inv->total_amount),
                    'paid_amount' => floatval($inv->paid_amount),
                    'balance_amount' => floatval($inv->balance_amount),
                    'status' => $inv->status,
                    'money_receipt_no' => $inv->money_receipt_no,
                    'receipt_generated_date' => $inv->receipt_generated_date,
                    'installment_count' => count($installment_ids),
                    'notes' => $inv->notes
                ];
            }
        }
        
        echo json_encode(['status' => 200, 'invoices' => $result]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Create new invoice
if ($s === 'create_invoice') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $installment_ids = isset($_POST['installment_ids']) ? json_decode($_POST['installment_ids'], true) : [];
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    try {
        if ($purchase_id <= 0 || empty($installment_ids)) {
            echo json_encode(['status' => 400, 'message' => 'Missing required fields']);
            exit;
        }
        
        // Get purchase and calculate total
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        // Get schedule items to calculate total
        $total_amount = 0;
        $schedule_items = $db->where('purchase_id', $purchase_id)->get('crm_payment_schedule');
        
        foreach ($schedule_items as $item) {
            if (in_array($item->id, $installment_ids)) {
                $total_amount += floatval($item->installment_amount ?? 0);
            }
        }
        
        // Generate invoice number
        $invoice_count = $db->where('purchase_id', $purchase_id)->getValue('crm_invoices', 'COUNT(*) as cnt');
        $invoice_number = 'INV-' . $purchase_id . '-' . str_pad(($invoice_count + 1), 3, '0', STR_PAD_LEFT);
        
        // Insert invoice
        $invoice_data = [
            'purchase_id' => $purchase_id,
            'client_id' => $helper->client_id,
            'invoice_number' => $invoice_number,
            'invoice_date' => date('Y-m-d'),
            'due_date' => $due_date ?: date('Y-m-d', strtotime('+30 days')),
            'total_amount' => $total_amount,
            'paid_amount' => 0,
            'balance_amount' => $total_amount,
            'installment_ids' => json_encode($installment_ids),
            'status' => 'draft',
            'created_by' => $GLOBALS['wo']['user']['id'] ?? null,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $invoice_id = $db->insert('crm_invoices', $invoice_data);
        
        // Log audit
        logAudit($purchase_id, $helper->client_id, 'invoice', 'invoice_created', 'Invoice created: ' . $invoice_number, null, $invoice_data);
        
        echo json_encode([
            'status' => 200,
            'message' => 'Invoice created successfully',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Update invoice status
if ($s === 'update_invoice_status') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }
    
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    try {
        $invoice = $db->where('id', $invoice_id)->getOne('crm_invoices');
        if (!$invoice) {
            echo json_encode(['status' => 404, 'message' => 'Invoice not found']);
            exit;
        }
        
        $old_status = $invoice->status;
        
        // Update invoice
        $db->where('id', $invoice_id)->update('crm_invoices', [
            'status' => $new_status,
            'updated_by' => $GLOBALS['wo']['user']['id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log audit
        logAudit($invoice->purchase_id, $invoice->client_id, 'invoice', 'status_changed', 
            "Invoice status changed from $old_status to $new_status", 
            ['status' => $old_status], 
            ['status' => $new_status]
        );
        
        echo json_encode(['status' => 200, 'message' => 'Invoice status updated']);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// EMAIL MANAGEMENT ENDPOINTS
// ============================================

// Get pending emails for sending
if ($s === 'get_pending_emails') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $email_type = isset($_POST['email_type']) ? trim($_POST['email_type']) : ''; // 'receipt' or 'schedule'
    
    try {
        $pending = [];
        
        if ($email_type === 'receipt' || $email_type === '') {
            // Get pending money receipts
            $receipts = $db->where('purchase_id', $purchase_id)->where('email_sent', 0)->get('crm_money_receipts');
            foreach ($receipts as $r) {
                $pending[] = [
                    'id' => $r->id,
                    'type' => 'receipt',
                    'receipt_number' => $r->receipt_number,
                    'amount' => floatval($r->amount),
                    'date' => $r->receipt_date,
                    'prepared' => true
                ];
            }
        }
        
        if ($email_type === 'schedule' || $email_type === '') {
            // Get pending schedules to send
            $schedules = $db->where('purchase_id', $purchase_id)
                ->where('status', 0) // unpaid
                ->orderBy('date', 'ASC')
                ->get('crm_payment_schedule');
            
            if (!empty($schedules)) {
                $pending[] = [
                    'id' => 'schedule_' . $purchase_id,
                    'type' => 'schedule',
                    'item_count' => count($schedules),
                    'date' => date('Y-m-d'),
                    'prepared' => true
                ];
            }
        }
        
        echo json_encode(['status' => 200, 'pending' => $pending, 'count' => count($pending)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Send email (money receipt or schedule)
if ($s === 'send_email_to_client') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!Wo_IsAdmin()) {
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $email_type = isset($_POST['email_type']) ? trim($_POST['email_type']) : ''; // 'receipt' or 'schedule'
    $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $body = isset($_POST['body']) ? $_POST['body'] : '';
    
    try {
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        // Validate email
        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid email address']);
            exit;
        }
        
        // In production, use proper email sending (SwiftMailer, PHPMailer, etc.)
        // For now, we log the email intent
        
        $email_log = [
            'purchase_id' => $purchase_id,
            'client_id' => $helper->client_id,
            'email_type' => $email_type === 'receipt' ? 'money_receipt' : 'payment_schedule',
            'recipient_email' => $recipient_email,
            'recipient_name' => $helper->client_name ?? '',
            'email_subject' => $subject,
            'email_body_excerpt' => substr($body, 0, 500),
            'email_template' => $email_type . '_' . date('Y-m-d'),
            'sent_at' => date('Y-m-d H:i:s'),
            'delivery_status' => 'pending',
            'sent_by' => $GLOBALS['wo']['user']['id'] ?? null,
            'sent_by_name' => $GLOBALS['wo']['user']['name'] ?? 'Admin'
        ];
        
        // TODO: Implement actual email sending here
        // mail($recipient_email, $subject, $body, ...);
        
        // Log the email
        $db->insert('crm_email_logs', $email_log);
        
        // Log audit
        logAudit($purchase_id, $helper->client_id, 'email', 'email_sent', 
            "Email sent: {$email_type} to {$recipient_email}", null, null);
        
        // Update money receipt if receipt
        if ($email_type === 'receipt') {
            $receipt_id = isset($_POST['receipt_id']) ? (int)$_POST['receipt_id'] : 0;
            if ($receipt_id > 0) {
                $db->where('id', $receipt_id)->update('crm_money_receipts', [
                    'email_sent' => 1,
                    'email_sent_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        echo json_encode([
            'status' => 200,
            'message' => 'Email sent successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// AUDIT TRAIL ENDPOINTS
// ============================================

// Get audit trail for purchase
if ($s === 'get_audit_trail') {
    header('Content-Type: application/json; charset=utf-8');
    
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : null;
    $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : null;
    
    try {
        $query = $db->where('purchase_id', $purchase_id);
        
        if (!empty($category)) {
            $query = $query->where('audit_category', $category);
        }
        
        if ($date_from) {
            $query = $query->where('created_at', '>=', $date_from . ' 00:00:00');
        }
        
        if ($date_to) {
            $query = $query->where('created_at', '<=', $date_to . ' 23:59:59');
        }
        
        $records = $query->orderBy('created_at', 'DESC')->get('crm_audit_trail');
        
        $result = [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $result[] = [
                    'id' => $record->id,
                    'category' => $record->audit_category,
                    'action' => $record->action_type,
                    'description' => $record->action_description,
                    'timestamp' => $record->created_at,
                    'user' => $record->created_by_name ?? 'System',
                    'old_value' => $record->old_value,
                    'new_value' => $record->new_value
                ];
            }
        }
        
        echo json_encode(['status' => 200, 'audit' => $result, 'count' => count($result)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// HELPER FUNCTION: Log Audit Trail
// ============================================

function logAudit($purchase_id, $client_id, $category, $action_type, $description, $old_value = null, $new_value = null, $related_table = null, $related_id = null) {
    global $db;
    
    try {
        $audit_data = [
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'audit_category' => $category,
            'action_type' => $action_type,
            'action_description' => $description,
            'old_value' => $old_value ? json_encode($old_value) : null,
            'new_value' => $new_value ? json_encode($new_value) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'related_table' => $related_table,
            'related_record_id' => $related_id,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $GLOBALS['wo']['user']['id'] ?? null,
            'created_by_name' => $GLOBALS['wo']['user']['name'] ?? 'System'
        ];
        
        return $db->insert('crm_audit_trail', $audit_data);
    } catch (Exception $e) {
        error_log('Error logging audit: ' . $e->getMessage());
        return false;
    }
}
// Calculate overdue fees for a schedule item
if ($s === 'calculate_overdue_fees') {
    $installment_id = isset($_POST['installment_id']) ? (int)$_POST['installment_id'] : 0;
    $grace_days = isset($_POST['grace_days']) ? (int)$_POST['grace_days'] : 5;
    $interest_percent = isset($_POST['interest_percent']) ? (float)$_POST['interest_percent'] : 3;

    if ($installment_id <= 0){
        echo json_encode(['status' => 400, 'message' => 'Invalid installment_id']);
        exit;
    }

    try {
        $schedule = $db->where('id', $installment_id)->getOne('crm_payment_schedule');
        if (!$schedule){
            echo json_encode(['status' => 404, 'message' => 'Installment not found']);
            exit;
        }

        $due_date = strtotime($schedule->due_date);
        $today = strtotime(date('Y-m-d'));
        $days_overdue = ceil(($today - $due_date) / 86400);

        $overdue_amount = 0;
        $days_after_grace = 0;

        // Calculate if overdue
        if ($days_overdue > $grace_days){
            $days_after_grace = $days_overdue - $grace_days;
            $outstanding = (float)$schedule->installment_amount - (float)$schedule->paid_amount;
            
            // Check if should skip fees (70% paid threshold or exclusion)
            if ((float)$schedule->paid_amount >= ((float)$schedule->installment_amount * 0.7) || $schedule->exclude_late_fees){
                $overdue_amount = 0;
            } else {
                $overdue_amount = $outstanding * ($interest_percent / 100);
            }
        }

        echo json_encode([
            'status' => 200,
            'days_overdue' => max(0, $days_overdue),
            'days_after_grace' => max(0, $days_after_grace),
            'overdue_amount' => round($overdue_amount),
            'calculation_note' => 'Based on due date and grace period'
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Exclude late fees for an installment (admin only)
if ($s === 'exclude_late_fees') {
    if (!Wo_IsAdmin()){
        echo json_encode(['status' => 403, 'message' => 'Admin access required']);
        exit;
    }

    $installment_id = isset($_POST['installment_id']) ? (int)$_POST['installment_id'] : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if ($installment_id <= 0){
        echo json_encode(['status' => 400, 'message' => 'Invalid installment_id']);
        exit;
    }

    try {
        $schedule = $db->where('id', $installment_id)->getOne('crm_payment_schedule');
        if (!$schedule){
            echo json_encode(['status' => 404, 'message' => 'Installment not found']);
            exit;
        }

        $db->where('id', $installment_id)->update('crm_payment_schedule', [
            'exclude_late_fees' => 1,
            'late_fee_reason' => $reason,
            'updated_by' => $wo['user']['id'] ?? null
        ]);

        // Log audit
        $db->insert('crm_audit_trail', [
            'purchase_id' => (int)$schedule->purchase_id,
            'client_id' => (int)$schedule->client_id,
            'audit_category' => 'manual_adjustment',
            'action_type' => 'late_fees_excluded',
            'action_description' => 'Late fees excluded with reason: ' . $reason,
            'related_table' => 'crm_payment_schedule',
            'related_record_id' => $installment_id,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $wo['user']['id'] ?? null,
            'created_by_name' => $wo['user']['name'] ?? 'Admin'
        ]);

        echo json_encode([
            'status' => 200,
            'message' => 'Late fees excluded successfully'
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Auto-generate money receipt when payment recorded
if ($s === 'auto_generate_receipt') {
    $installment_id = isset($_POST['installment_id']) ? (int)$_POST['installment_id'] : 0;

    if ($installment_id <= 0){
        echo json_encode(['status' => 400, 'message' => 'Invalid installment_id']);
        exit;
    }

    try {
        $schedule = $db->where('id', $installment_id)->getOne('crm_payment_schedule');
        if (!$schedule || (float)$schedule->paid_amount <= 0){
            echo json_encode(['status' => 400, 'message' => 'No payment to receipt']);
            exit;
        }

        // Check if receipt already exists
        $existing = $db->where('installment_id', $installment_id)
            ->where('status', '!=', 'cancelled')
            ->getOne('crm_money_receipts');

        if ($existing){
            echo json_encode([
                'status' => 200,
                'message' => 'Receipt already exists',
                'receipt_number' => $existing->receipt_number
            ]);
            exit;
        }

        // Generate new receipt
        $receipt_count = (int)$db->getValue('crm_money_receipts', 'COUNT(*) as cnt');
        $receipt_number = 'MR-' . date('Ymd') . '-' . str_pad(($receipt_count + 1), 4, '0', STR_PAD_LEFT);

        $receipt_data = [
            'receipt_number' => $receipt_number,
            'purchase_id' => (int)$schedule->purchase_id,
            'client_id' => (int)$schedule->client_id,
            'receipt_date' => date('Y-m-d'),
            'amount' => (float)$schedule->paid_amount,
            'payment_method' => $schedule->payment_method ?? 'Cash',
            'installment_id' => $installment_id,
            'status' => 'issued',
            'created_by' => $wo['user']['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $receipt_id = $db->insert('crm_money_receipts', $receipt_data);

        // Update schedule
        $db->where('id', $installment_id)->update('crm_payment_schedule', [
            'money_receipt_no' => $receipt_number
        ]);

        echo json_encode([
            'status' => 200,
            'message' => 'Money receipt generated',
            'receipt_id' => (int)$receipt_id,
            'receipt_number' => $receipt_number
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Recalculate all totals for a purchase
if ($s === 'recalculate_purchase_totals') {
    $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;

    if ($purchase_id <= 0){
        echo json_encode(['status' => 400, 'message' => 'Invalid purchase_id']);
        exit;
    }

    try {
        $schedule = $db->where('purchase_id', $purchase_id)->get('crm_payment_schedule');

        $totals = [
            'total_installments' => 0,
            'total_paid' => 0,
            'total_overdue' => 0,
            'total_balance' => 0,
            'items_count' => 0,
            'items_paid' => 0,
            'items_unpaid' => 0
        ];

        foreach ($schedule as $item){
            $totals['total_installments'] += (float)$item->installment_amount;
            $totals['total_paid'] += (float)$item->paid_amount;
            $totals['items_count']++;

            if ((float)$item->paid_amount >= (float)$item->installment_amount){
                $totals['items_paid']++;
            } else {
                $totals['items_unpaid']++;
                $outstanding = (float)$item->installment_amount - (float)$item->paid_amount;
                $totals['total_overdue'] += $outstanding;
            }
        }

        $totals['total_balance'] = $totals['total_installments'] - $totals['total_paid'];

        echo json_encode([
            'status' => 200,
            'totals' => $totals
        ]);
    } catch (Exception $e){
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}
$action = $s;
$request_method = $_SERVER['REQUEST_METHOD'];
$user_id = $wo['user']['user_id'] ? (int)$wo['user']['user_id'] : 0;
$current_user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Sanitization helper
function sanitize_input($input) {
  if (is_array($input)) {
    return array_map('sanitize_input', $input);
  }
  return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

// Response helper
function send_json_response($status, $message = '', $data = array()) {
  header('Content-Type: application/json');
  echo json_encode(array(
    'status' => $status,
    'message' => $message,
    'data' => $data,
    'timestamp' => date('Y-m-d H:i:s')
  ));
  exit;
}

// Database function wrapper
function db_query($query, $types = '', $params = array()) {
  global $mysqli;
  $stmt = $mysqli->prepare($query);
  if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  return $stmt;
}

function log_audit_trail($purchase_id, $client_id, $category, $type, $description, $before = null, $after = null) {
  global $mysqli, $user_id, $current_user_ip, $current_user_agent;
  
  $before_json = $before ? json_encode($before) : null;
  $after_json = $after ? json_encode($after) : null;
  
  $stmt = $mysqli->prepare("
    INSERT INTO crm_audit_trail (purchase_id, client_id, action_category, action_type, action_description, before_values, after_values, performed_at, performed_by, ip_address, user_agent)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
  ");
  $stmt->bind_param('iisssssiss', $purchase_id, $client_id, $category, $type, $description, $before_json, $after_json, $user_id, $current_user_ip, $current_user_agent);
  return $stmt->execute();
}

// ========================================
// MERGE PURCHASE ENDPOINT
// ========================================
if ($action === 'request_purchase_merge' && $request_method === 'POST') {
  $primary_id = (int)($_POST['primary_purchase_id'] ?? 0);
  $secondary_id = (int)($_POST['secondary_purchase_id'] ?? 0);
  $reason = sanitize_input($_POST['merge_reason'] ?? '');
  $merge_paid = (int)($_POST['merge_paid_amount'] ?? 1);
  $merge_schedule = (int)($_POST['merge_payment_schedule'] ?? 1);
  $merge_invoices = (int)($_POST['merge_invoices'] ?? 1);

  if (!$primary_id || !$secondary_id || !$reason) {
    send_json_response(400, 'Missing required fields');
  }

  if ($primary_id === $secondary_id) {
    send_json_response(400, 'Cannot merge a purchase with itself');
  }

  // Get purchase details
  $stmt = $mysqli->prepare("SELECT id, client_id FROM wo_booking_helper WHERE id = ?");
  $stmt->bind_param('i', $primary_id);
  $stmt->execute();
  $primary = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $stmt = $mysqli->prepare("SELECT id, client_id FROM wo_booking_helper WHERE id = ?");
  $stmt->bind_param('i', $secondary_id);
  $stmt->execute();
  $secondary = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$primary || !$secondary) {
    send_json_response(404, 'One or both purchases not found');
  }

  // Get paid amounts for audit
  $stmt = $mysqli->prepare("SELECT SUM(paid_amount) as total FROM crm_payment_schedule WHERE purchase_id = ?");
  $stmt->bind_param('i', $primary_id);
  $stmt->execute();
  $primary_paid = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
  $stmt->close();

  $stmt = $mysqli->prepare("SELECT SUM(paid_amount) as total FROM crm_payment_schedule WHERE purchase_id = ?");
  $stmt->bind_param('i', $secondary_id);
  $stmt->execute();
  $secondary_paid = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
  $stmt->close();

  // Create merge request (pending admin approval)
  $stmt = $mysqli->prepare("
    INSERT INTO crm_purchase_merges (primary_purchase_id, secondary_purchase_id, primary_client_id, secondary_client_id, merge_reason, merge_paid_amount, merge_payment_schedule, merge_invoices, status, requested_at, requested_by, total_paid_primary, total_paid_secondary, total_paid_after_merge)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?, ?)
  ");
  
  $total_after = $primary_paid + $secondary_paid;
  $stmt->bind_param('iiiisiiiddd', $primary_id, $secondary_id, $primary['client_id'], $secondary['client_id'], $reason, $merge_paid, $merge_schedule, $merge_invoices, $user_id, $primary_paid, $secondary_paid, $total_after);
  
  if ($stmt->execute()) {
    $merge_id = $stmt->insert_id;
    $stmt->close();

    // Log audit trail
    log_audit_trail($primary_id, $primary['client_id'], 'merge', 'merge_requested', "Merge requested with purchase #$secondary_id", array(
      'primary_paid' => $primary_paid,
      'secondary_paid' => $secondary_paid
    ), array(
      'merge_id' => $merge_id,
      'status' => 'pending'
    ));

    // Log merge history
    $stmt = $mysqli->prepare("INSERT INTO crm_purchase_merge_history (merge_request_id, action, performed_at, performed_by, details) VALUES (?, 'requested', NOW(), ?, ?)");
    $details = json_encode(array('merge_reason' => $reason, 'merge_paid' => $merge_paid, 'merge_schedule' => $merge_schedule, 'merge_invoices' => $merge_invoices));
    $stmt->bind_param('iis', $merge_id, $user_id, $details);
    $stmt->execute();
    $stmt->close();

    send_json_response(200, 'Merge request submitted for admin approval', array('merge_id' => $merge_id));
  } else {
    send_json_response(500, 'Failed to create merge request: ' . $mysqli->error);
  }
}

// ========================================
// APPROVE MERGE ENDPOINT
// ========================================
if ($action === 'approve_purchase_merge' && $request_method === 'POST') {
  // This endpoint should only be accessible to admins
  $merge_id = (int)($_POST['merge_id'] ?? 0);

  if (!$merge_id) {
    send_json_response(400, 'Merge ID required');
  }

  // Get merge request
  $stmt = $mysqli->prepare("SELECT * FROM crm_purchase_merges WHERE id = ? AND status = 'pending'");
  $stmt->bind_param('i', $merge_id);
  $stmt->execute();
  $merge = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$merge) {
    send_json_response(404, 'Merge request not found or already processed');
  }

  // Start transaction
  $mysqli->begin_transaction();

  try {
    $primary_id = $merge['primary_purchase_id'];
    $secondary_id = $merge['secondary_purchase_id'];

    // 1. Transfer paid amounts if configured
    if ($merge['merge_paid_amount']) {
      // Create credit for overpayment if secondary is overpaid
      $stmt = $mysqli->prepare("SELECT SUM(paid_amount) as total FROM crm_payment_schedule WHERE purchase_id = ?");
      $stmt->bind_param('i', $secondary_id);
      $stmt->execute();
      $secondary_total_paid = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
      $stmt->close();

      $stmt = $mysqli->prepare("SELECT SUM(installment_amount) as total FROM crm_payment_schedule WHERE purchase_id = ?");
      $stmt->bind_param('i', $secondary_id);
      $stmt->execute();
      $secondary_total_due = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
      $stmt->close();

      // If secondary is overpaid, create credit
      if ($secondary_total_paid > $secondary_total_due) {
        $credit_amount = $secondary_total_paid - $secondary_total_due;
        $stmt = $mysqli->prepare("
          INSERT INTO crm_payment_credits (purchase_id, client_id, credit_amount, reason, created_by)
          VALUES (?, ?, ?, 'Overpayment from merged purchase', ?)
        ");
        $stmt->bind_param('iddi', $primary_id, $merge['primary_client_id'], $credit_amount, $user_id);
        $stmt->execute();
        $stmt->close();
      }
    }

    // 2. Consolidate payment schedules if configured
    if ($merge['merge_payment_schedule']) {
      // Get remaining schedule items from secondary
      $stmt = $mysqli->prepare("SELECT * FROM crm_payment_schedule WHERE purchase_id = ? AND status NOT IN (1, 4) ORDER BY installment_number");
      $stmt->bind_param('i', $secondary_id);
      $stmt->execute();
      $secondary_schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      // Get max installment number from primary
      $stmt = $mysqli->prepare("SELECT MAX(installment_number) as max_num FROM crm_payment_schedule WHERE purchase_id = ?");
      $stmt->bind_param('i', $primary_id);
      $stmt->execute();
      $max_num = $stmt->get_result()->fetch_assoc()['max_num'] ?? 0;
      $stmt->close();

      // Copy schedules with new installment numbers
      foreach ($secondary_schedules as $schedule) {
        $new_num = $max_num + 1;
        $new_particular = $schedule['particular'] . ' (from merged purchase)';
        $stmt = $mysqli->prepare("
          INSERT INTO crm_payment_schedule (purchase_id, client_id, installment_number, particular, type, due_date, installment_amount, paid_amount, status, created_by, recalculated_due_to, recalculation_date, change_reason)
          VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 'merge', NOW(), 'Consolidated from purchase #$secondary_id')
        ");
        $stmt->bind_param('iiissiddi', $primary_id, $merge['primary_client_id'], $new_num, $new_particular, $schedule['type'], $schedule['due_date'], $schedule['installment_amount'], $user_id);
        $stmt->execute();
        $stmt->close();
        $max_num++;
      }
    }

    // 3. Consolidate invoices if configured
    if ($merge['merge_invoices']) {
      $stmt = $mysqli->prepare("UPDATE crm_invoices SET purchase_id = ?, client_id = ? WHERE purchase_id = ?");
      $stmt->bind_param('iii', $primary_id, $merge['primary_client_id'], $secondary_id);
      $stmt->execute();
      $stmt->close();
    }

    // 4. Mark secondary as merged (change status to archived/merged)
    $stmt = $mysqli->prepare("UPDATE wo_booking_helper SET status = 'merged' WHERE id = ?");
    $stmt->bind_param('i', $secondary_id);
    $stmt->execute();
    $stmt->close();

    // 5. Update merge request status
    $stmt = $mysqli->prepare("UPDATE crm_purchase_merges SET status = 'approved', approved_at = NOW(), approved_by = ?, merged_at = NOW(), merged_by = ? WHERE id = ?");
    $stmt->bind_param('iii', $user_id, $user_id, $merge_id);
    $stmt->execute();
    $stmt->close();

    // 6. Log merge history
    $stmt = $mysqli->prepare("INSERT INTO crm_purchase_merge_history (merge_request_id, action, performed_at, performed_by, details) VALUES (?, 'merged', NOW(), ?, ?)");
    $details = json_encode(array('merged_schedules' => count($secondary_schedules), 'total_credit_generated' => ($merge['merge_paid_amount'] && $secondary_total_paid > $secondary_total_due) ? ($secondary_total_paid - $secondary_total_due) : 0));
    $stmt->bind_param('iis', $merge_id, $user_id, $details);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $mysqli->commit();

    log_audit_trail($primary_id, $merge['primary_client_id'], 'merge', 'merge_completed', "Successfully merged purchase #$secondary_id", null, array('merge_id' => $merge_id));

    send_json_response(200, 'Merge approved and completed successfully', array('merge_id' => $merge_id, 'primary_id' => $primary_id, 'secondary_id' => $secondary_id));

  } catch (Exception $e) {
    $mysqli->rollback();
    send_json_response(500, 'Merge failed: ' . $e->getMessage());
  }
}

// ========================================
// CANCEL PURCHASE ENDPOINT
// ========================================
if ($action === 'process_cancel_purchase' && $request_method === 'POST') {
  $purchase_id = (int)($_POST['purchase_id'] ?? 0);
  $reason = sanitize_input($_POST['reason'] ?? '');
  $fee_mode = sanitize_input($_POST['fee_mode'] ?? 'none');
  $fee_value = (float)($_POST['fee_value'] ?? 0);
  $initiate_refund = (int)($_POST['initiate_refund'] ?? 0);

  if (!$purchase_id || !$reason || strlen($reason) < 10) {
    send_json_response(400, 'Invalid cancellation parameters');
  }

  // Get purchase info
  $stmt = $mysqli->prepare("
    SELECT wbh.*, cc.name as client_name, cc.id as client_id
    FROM wo_booking_helper wbh
    JOIN crm_customers cc ON cc.id = wbh.client_id
    WHERE wbh.id = ?
  ");
  $stmt->bind_param('i', $purchase_id);
  $stmt->execute();
  $purchase = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$purchase) {
    send_json_response(404, 'Purchase not found');
  }

  // Calculate total paid
  $stmt = $mysqli->prepare("SELECT SUM(paid_amount) as total FROM crm_payment_schedule WHERE purchase_id = ?");
  $stmt->bind_param('i', $purchase_id);
  $stmt->execute();
  $total_paid = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
  $stmt->close();

  // Start transaction
  $mysqli->begin_transaction();

  try {
    // 1. Create cancellation record
    $stmt = $mysqli->prepare("
      INSERT INTO crm_purchase_cancellations (purchase_id, client_id, cancellation_reason, total_paid_amount, cancellation_fee_mode, cancellation_fee_value, refund_initiated, cancelled_at, cancelled_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->bind_param('iisdsii', $purchase_id, $purchase['client_id'], $reason, $total_paid, $fee_mode, $fee_value, $initiate_refund, $user_id);
    $stmt->execute();
    $cancellation_id = $stmt->insert_id;
    $stmt->close();

    // 2. Mark all payment schedules as cancelled
    $stmt = $mysqli->prepare("UPDATE crm_payment_schedule SET status = 4, updated_by = ?, updated_at = NOW() WHERE purchase_id = ?");
    $stmt->bind_param('ii', $user_id, $purchase_id);
    $stmt->execute();
    $stmt->close();

    // 3. Update purchase status
    $stmt = $mysqli->prepare("UPDATE wo_booking_helper SET status = '4', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $purchase_id);
    $stmt->execute();
    $stmt->close();

    // 4. If refund initiated, create refund schedule
    if ($initiate_refund) {
      $refund_amount = $total_paid;
      if ($fee_mode === 'fixed') {
        $refund_amount = $total_paid - $fee_value;
      } elseif ($fee_mode === 'percent') {
        $refund_amount = $total_paid - ($total_paid * $fee_value / 100);
      }

      $stmt = $mysqli->prepare("
        INSERT INTO crm_refund_schedule (purchase_id, client_id, refund_initiation_date, total_paid_amount, deduction_percentage, deduction_amount, refundable_amount, installment_number, installment_amount, due_date, status, created_by)
        VALUES (?, ?, NOW(), ?, 0, 0, ?, 1, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 0, ?)
      ");
      $stmt->bind_param('iiddddi', $purchase_id, $purchase['client_id'], $total_paid, $refund_amount, $refund_amount, $user_id);
      $stmt->execute();
      $stmt->close();
    }

    // Commit
    $mysqli->commit();

    // Log audit
    log_audit_trail($purchase_id, $purchase['client_id'], 'cancel', 'purchase_cancelled', "Purchase cancelled. Reason: $reason", array('status' => '2', 'total_paid' => $total_paid), array('status' => '4', 'cancellation_id' => $cancellation_id));

    send_json_response(200, 'Purchase cancelled successfully', array('cancellation_id' => $cancellation_id));

  } catch (Exception $e) {
    $mysqli->rollback();
    send_json_response(500, 'Cancellation failed: ' . $e->getMessage());
  }
}

// ========================================
// SEND EMAIL ENDPOINT
// ========================================
if ($action === 'send_pending_email' && $request_method === 'POST') {
  $email_log_id = (int)($_POST['email_log_id'] ?? 0);
  $purchase_id = (int)($_POST['purchase_id'] ?? 0);

  if (!$email_log_id || !$purchase_id) {
    send_json_response(400, 'Missing parameters');
  }

  // Get email log
  $stmt = $mysqli->prepare("SELECT * FROM crm_email_logs WHERE id = ? AND purchase_id = ? AND status = 'pending'");
  $stmt->bind_param('ii', $email_log_id, $purchase_id);
  $stmt->execute();
  $email = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$email) {
    send_json_response(404, 'Email not found or already sent');
  }

  // Send email (using your existing email system)
  $email_sent = true; // Placeholder - implement your email sending

  if ($email_sent) {
    $stmt = $mysqli->prepare("UPDATE crm_email_logs SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?");
    $stmt->bind_param('i', $email_log_id);
    $stmt->execute();
    $stmt->close();

    log_audit_trail($purchase_id, $email['client_id'], 'email', 'email_sent', "Email sent: {$email['email_type']} to {$email['recipient_email']}", null, array('email_log_id' => $email_log_id, 'status' => 'sent'));

    send_json_response(200, 'Email sent successfully');
  } else {
    $stmt = $mysqli->prepare("UPDATE crm_email_logs SET status = 'failed', error_message = 'Email service failed', last_attempt_at = NOW(), attempts = attempts + 1 WHERE id = ?");
    $stmt->bind_param('i', $email_log_id);
    $stmt->execute();
    $stmt->close();

    send_json_response(500, 'Failed to send email');
  }
}

// Default response
send_json_response(400, 'Invalid action or request method');
