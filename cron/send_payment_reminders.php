<?php
/**
 * Payment Reminders Automation
 * Sends reminders 7, 3, and 1 day(s) before payment due dates
 * Run daily via cron job
 */

if (!defined('RUNNING_FROM_CRON')) {
    die('Must be run from cron-job.php');
}

try {
    $today = date('Y-m-d');
    
    // Reminder intervals (days before due date)
    $intervals = [
        7 => 'reminder_7_days',
        3 => 'reminder_3_days',
        1 => 'reminder_1_day'
    ];
    
    $total_sent = 0;
    $total_sms = 0;
    
    foreach ($intervals as $days => $email_type) {
        $target_date = date('Y-m-d', strtotime("+{$days} days"));
        
        // Get unpaid schedules due on target date
        $db->where('status', 0); // Unpaid
        $db->where('status', 99, '!='); // Not deleted
        $db->where('due_date', $target_date);
        $db->orderBy('due_date', 'ASC');
        $schedules = $db->get('crm_payment_schedule', null, [
            'id', 'purchase_id', 'client_id', 'installment_number', 
            'particular', 'due_date', 'installment_amount'
        ]);
        
        foreach ($schedules as $schedule) {
            // Get purchase details
            $purchase = $db->where('id', $schedule->purchase_id)->getOne('wo_booking_helper', [
                'client_id', 'file_num'
            ]);
            
            if (!$purchase) continue;
            
            // Get client details
            $client = $db->where('id', $schedule->client_id)->getOne('crm_customers', [
                'id', 'name', 'email', 'phone'
            ]);
            
            if (!$client) continue;
            
            // Check if reminder already sent for this schedule at this interval
            $db->where('purchase_id', $schedule->purchase_id);
            $db->where('email_type', $email_type);
            $db->where('metadata', '%"schedule_id":' . $schedule->id . '%', 'LIKE');
            $db->where('DATE(created_at)', date('Y-m-d'));
            $existing = $db->getOne('crm_email_queue');
            
            if ($existing) continue; // Already sent today
            
            // Queue email reminder
            if (!empty($client->email)) {
                $db->insert('crm_email_queue', [
                    'purchase_id' => $schedule->purchase_id,
                    'client_id' => $schedule->client_id,
                    'recipient_email' => $client->email,
                    'recipient_name' => $client->name,
                    'recipient_phone' => $client->phone,
                    'email_type' => $email_type,
                    'metadata' => json_encode([
                        'schedule_id' => $schedule->id,
                        'client_name' => $client->name,
                        'file_num' => $purchase->file_num,
                        'installment_number' => $schedule->installment_number,
                        'particular' => $schedule->particular,
                        'amount' => $schedule->installment_amount,
                        'due_date' => $schedule->due_date,
                        'days_until_due' => $days
                    ]),
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $total_sent++;
            }
            
            // Queue SMS reminder
            if (!empty($client->phone)) {
                $sms_type = str_replace('reminder_', '', $email_type);
                
                $db->insert('crm_sms_queue', [
                    'purchase_id' => $schedule->purchase_id,
                    'client_id' => $schedule->client_id,
                    'phone_number' => $client->phone,
                    'sms_type' => $email_type,
                    'metadata' => json_encode([
                        'name' => $client->name,
                        'amount' => $schedule->installment_amount,
                        'date' => date('d M', strtotime($schedule->due_date)),
                        'days' => $days
                    ]),
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $total_sms++;
            }
            
            // Log to reminders table
            $db->insert('crm_reminders', [
                'purchase_id' => $schedule->purchase_id,
                'schedule_id' => $schedule->id,
                'client_id' => $schedule->client_id,
                'reminder_type' => "due_in_{$days}_days",
                'sent_via' => (!empty($client->email) && !empty($client->phone)) ? 'both' : (!empty($client->email) ? 'email' : 'sms'),
                'sent_at' => date('Y-m-d H:i:s'),
                'status' => 'sent',
                'metadata' => json_encode([
                    'days_before_due' => $days,
                    'due_date' => $schedule->due_date,
                    'amount' => $schedule->installment_amount
                ])
            ]);
        }
    }
    
    // Log to audit trail
    if ($db->tableExists('crm_audit_trail')) {
        $db->insert('crm_audit_trail', [
            'client_id' => 0,
            'action_type' => 'system',
            'action_category' => 'automation',
            'action_description' => "Payment reminders: queued {$total_sent} emails, {$total_sms} SMS",
            'performed_by' => 0,
            'performed_at' => date('Y-m-d H:i:s'),
            'ip_address' => 'CRON'
        ]);
    }
    
    echo "[Payment Reminders] Queued {$total_sent} emails, {$total_sms} SMS for 7/3/1 day reminders\n";
    
} catch (Exception $e) {
    echo "[Payment Reminders] Error: " . $e->getMessage() . "\n";
}
