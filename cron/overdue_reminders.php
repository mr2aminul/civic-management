<?php
/**
 * Overdue Payment Reminder Automation
 * Checks for overdue payments and queues reminder emails/SMS
 * Run this daily via cron job
 * NOW USES: crm_payment_schedule with installment_amount column
 */

if (!defined('RUNNING_FROM_CRON')) {
    die('Must be run from cron-job.php');
}

try {
    $today = date('Y-m-d');
    $sent_count = 0;
    $sms_count = 0;
    
    // Get unpaid schedules that are overdue
    if ($db->tableExists('crm_payment_schedule')) {
        $db->where('status', 0); // Unpaid
        $db->where('status', 99, '!='); // Not deleted
        $db->where('due_date', $today, '<');
        $overdueSchedules = $db->get('crm_payment_schedule', null, ['id', 'purchase_id', 'client_id', 'due_date', 'installment_amount', 'paid_amount']);
        
        $purchaseIds = [];
        foreach ($overdueSchedules as $schedule) {
            $purchaseIds[$schedule->purchase_id] = true;
        }
        
        foreach (array_keys($purchaseIds) as $purchase_id) {
            // Get purchase and client info
            $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['client_id', 'file_num']);
            if (!$purchase) continue;
            
            $client = $db->where('id', $purchase->client_id)->getOne('crm_customers', ['id', 'email', 'phone', 'name']);
            if (!$client) continue;
            
            // Check if reminder already sent this week
            $weekAgo = date('Y-m-d', strtotime('-7 days'));
            $db->where('purchase_id', $purchase_id);
            $db->where('email_type', 'payment_overdue');
            $db->where('created_at', $weekAgo, '>=');
            $existing = $db->getOne('crm_email_queue');
            
            if ($existing) continue; // Don't spam
            
            // Calculate total overdue amount
            $db->where('purchase_id', $purchase_id);
            $db->where('status', 0);
            $db->where('status', 99, '!=');
            $db->where('due_date', $today, '<');
            $overdueForPurchase = $db->get('crm_payment_schedule');
            
            $totalOverdue = 0;
            $oldestDue = '';
            foreach ($overdueForPurchase as $item) {
                $totalOverdue += ($item->installment_amount - $item->paid_amount);
                if (empty($oldestDue) || $item->due_date < $oldestDue) {
                    $oldestDue = $item->due_date;
                }
            }
            
            $daysOverdue = floor((strtotime($today) - strtotime($oldestDue)) / 86400);
            
            // Queue reminder email
            if (!empty($client->email)) {
                $db->insert('crm_email_queue', [
                    'purchase_id' => $purchase_id,
                    'client_id' => $purchase->client_id,
                    'recipient_email' => $client->email,
                    'recipient_name' => $client->name,
                    'email_type' => 'payment_overdue',
                    'metadata' => json_encode([
                        'client_name' => $client->name,
                        'file_num' => $purchase->file_num,
                        'amount' => $totalOverdue,
                        'days_overdue' => $daysOverdue
                    ]),
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $sent_count++;
            }
            
            // Queue SMS
            if (!empty($client->phone)) {
                $db->insert('crm_sms_queue', [
                    'purchase_id' => $purchase_id,
                    'client_id' => $purchase->client_id,
                    'phone_number' => $client->phone,
                    'sms_type' => 'payment_overdue',
                    'metadata' => json_encode([
                        'name' => $client->name,
                        'amount' => $totalOverdue
                    ]),
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $sms_count++;
            }
            
            // Update schedule status to overdue (3)
            $db->where('purchase_id', $purchase_id);
            $db->where('status', 0);
            $db->where('due_date', $today, '<');
            $db->update('crm_payment_schedule', ['status' => 3]); // 3 = overdue
        }
    }
    
    // Log
    if ($db->tableExists('crm_audit_trail')) {
        $db->insert('crm_audit_trail', [
            'client_id' => 0,
            'action_type' => 'system',
            'action_category' => 'automation',
            'action_description' => "Overdue automation: queued {$sent_count} emails, {$sms_count} SMS",
            'performed_by' => 0,
            'performed_at' => date('Y-m-d H:i:s'),
            'ip_address' => 'CRON'
        ]);
    }
    
    echo "[Overdue Reminders] Queued {$sent_count} emails, {$sms_count} SMS\n";
    
} catch (Exception $e) {
    echo "[Overdue Reminders] Error: " . $e->getMessage() . "\n";
}
