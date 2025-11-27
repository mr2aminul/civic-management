<?php
/**
 * Overdue Payment Reminder Automation
 * Checks for overdue payments and queues reminder emails
 * Run this daily via cron job
 */

require_once '../assets/init.php';

if (!defined(T_BOOKING_HELPER')) {
    die('Configuration error');
}

try {
    $today = date('Y-m-d');
    $sent_count = 0;
    
    // Get unpaid schedules that are overdue
    if ($db->tableExists('crm_payment_schedule')) {
        $db->where('status', 0); // Unpaid
        $db->where('due_date <', $today);
        $overdueSchedules = $db->get('crm_payment_schedule', null, ['id', 'purchase_id', 'due_date', 'installment_amount', 'paid_amount']);
        
        $purchaseIds = [];
        foreach ($overdueSchedules as $schedule) {
            $purchaseIds[$schedule->purchase_id] = true;
        }
        
        foreach (array_keys($purchaseIds) as $purchase_id) {
            // Get purchase and client info
            $purchase = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER, ['client_id', 'file_num']);
            if (!$purchase) continue;
            
            $client = $db->where('id', $purchase->client_id)->getOne(T_CUSTOMERS, ['email', 'name']);
            if (!$client || empty($client->email)) continue;
            
            // Check if reminder already sent this week
            $weekAgo = date('Y-m-d', strtotime('-7 days'));
            $db->where('purchase_id', $purchase_id);
            $db->where('email_type', 'payment_overdue');
            $db->where('queue_date >=', $weekAgo);
            $existing = $db->getOne('crm_email_queue');
            
            if ($existing) continue; // Don't spam
            
            // Calculate total overdue amount
            $db->where('purchase_id', $purchase_id);
            $db->where('status', 0);
            $db->where('due_date <', $today);
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
            $db->insert('crm_email_queue', [
                'purchase_id' => $purchase_id,
                'client_id' => $purchase->client_id,
                'recipient_email' => $client->email,
                'recipient_name' => $client->name,
                'email_type' => 'payment_overdue',
                'subject' => "⚠️ Payment Overdue Reminder - File: {$purchase->file_num}",
                'body' => "Dear {$client->name},<br><br>This is a reminder that you have an overdue payment for your property booking (File: {$purchase->file_num}).<br><br><strong>Overdue Amount:</strong> ৳" . number_format($totalOverdue, 2) . "<br><strong>Days Overdue:</strong> $daysOverdue days<br><br>Please arrange payment at your earliest convenience to avoid late fees.<br><br>If you have any questions, please contact our office.<br><br>Thank you,<br>Civic Group BD",
                'status' => 'pending',
                'queue_date' => date('Y-m-d H:i:s')
            ]);
            $sent_count++;
        }
    }
    
    // Log
    if ($db->tableExists('crm_audit_trail')) {
        $db->insert('crm_audit_trail', [
            'user_id' => 0,
            'action' => 'overdue_automation',
            'details' => json_encode(['queued_count' => $sent_count, 'date' => $today]),
            'ip_address' => 'CRON',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo "Overdue reminder automation complete. Queued $sent_count emails.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
