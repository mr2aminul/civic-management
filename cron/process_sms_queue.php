<?php
/**
 * Processes queued SMS from crm_sms_queue
 * Uses existing SMS functions (iglweb, elitbuzz)
 * 
 * Called from cron-job.php
 */

// Process batch
$batch_size = 20; // SMS is faster than email

try {
    // Get queued SMS
    $db->where('status', 'queued');
    $db->orderBy('created_at', 'ASC');
    $sms_queue = $db->get('crm_sms_queue', $batch_size);
    
    if (empty($sms_queue)) {
        echo "No SMS in queue\n";
        if (!defined('RUNNING_FROM_CRON')) exit(0);
        return;
    }
    
    echo "Processing " . count($sms_queue) . " SMS...\n";
    
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($sms_queue as $sms) {
        try {
            // Prepare SMS content from template
            $metadata = json_decode($sms->metadata, true) ?? [];
            $message = prepareSMSTemplate($sms->sms_type, $metadata);
            
            // Determine SMS vendor (default to elitbuzz)
            $sms_vendor = $metadata['sms_vendor'] ?? 'elitbuzz';
            
            // Prepare data for sms_send function
            $sms_data = [
                'sms_vendor' => $sms_vendor,
                'senderid' => $metadata['senderid'] ?? '38756', // Default CIVIC PLOTS
                'type' => 'text',
                'contacts' => $sms->phone_number,
                'msg' => $message,
                'user_id' => $wo['user']['user_id'] ?? 0
            ];
            
            // Send SMS using existing function
            echo "  Sending SMS to {$sms->phone_number} via {$sms_vendor}...\n";
            $result = sms_send($sms_data);
            echo "  SMS API Result: " . (is_string($result) ? $result : json_encode($result)) . "\n";
            
            // Check if sent successfully
            if (strpos($result, 'successfully') !== false || strpos($result, 'SMS SUBMITTED') !== false) {
                // Update queue
                $db->where('id', $sms->id);
                $db->update('crm_sms_queue', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s')
                ]);
                
                $sent_count++;
                echo "✓ Sent to {$sms->phone_number}\n";
            } else {
                throw new Exception($result);
            }
            
        } catch (Exception $e) {
            // Log failure
            $error_msg = $e->getMessage();
            
            $db->where('id', $sms->id);
            $db->update('crm_sms_queue', [
                'status' => 'failed',
                'error_message' => $error_msg
            ]);
            
            $failed_count++;
            echo "✗ Failed {$sms->phone_number}: {$error_msg}\n";
        }
    }
    
    echo "\nSummary: {$sent_count} sent, {$failed_count} failed\n";
    
    if (!defined('RUNNING_FROM_CRON')) exit(0);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (!defined('RUNNING_FROM_CRON')) exit(1);
}

/**
 * Prepare SMS message from template
 */
function prepareSMSTemplate($sms_type, $metadata) {
    // SMS templates (160 chars max for single SMS)
    $templates = [
        'payment_received' => "Payment received: ৳{amount}. Thank you! Receipt: {receipt_number} - Civic Group",
        'reminder_7_days' => "Hi {name}, payment of ৳{amount} due on {date}. Please pay on time. - Civic Group",
        'reminder_3_days' => "URGENT: Payment of ৳{amount} due in 3 days ({date}). Please arrange payment. - Civic",
        'reminder_1_day' => "FINAL: Payment of ৳{amount} due tomorrow ({date}). Please pay now. - Civic Group",
        'payment_overdue' => "Your payment of ৳{amount} is overdue. Contact us urgently. - Civic Group",
        'payment_completed' => "Congratulations {name}! All payments complete for Plot {plot}. - Civic Group",
        'cancellation_approved' => "Cancellation approved. Refund details sent via email. - Civic Group",
        'plot_held' => "Plot {plot} held until {date}. - Civic Group",
        'hold_expired' => "Your hold on Plot {plot} has expired. - Civic Group",
        'invoice_created' => "Invoice {invoice_number} generated. Amount: ৳{amount}, Due: {due_date} - Civic",
        'reschedule_approved' => "Payment reschedule approved. New installment: ৳{amount}. - Civic Group"
    ];
    
    $template = $templates[$sms_type] ?? "Notification from Civic Group";
    
    // Replace placeholders
    foreach ($metadata as $key => $value) {
        $template = str_replace("{{$key}}", $value, $template);
    }
    
    // Ensure max 160 chars (single SMS)
    return substr($template, 0, 160);
}
