<?php
/**
 * Birthday Wish Automation
 * Automatically queues birthday wish emails/SMS for clients and nominees
 * Run this daily via cron job (e.g., at midnight)
 * Now uses: crm_email_queue, crm_sms_queue, crm_audit_trail
 */

if (!defined('RUNNING_FROM_CRON')) {
    die('Must be run from cron-job.php');
}

$today = date('m-d'); // Format: MM-DD

try {
    $sent_count = 0;
    $sms_count = 0;
    
    // Get clients with birthdays today
    $db->where("DATE_FORMAT(birthday, '%m-%d')", $today);
    $clients = $db->get('crm_customers', null, ['id', 'name', 'email', 'phone', 'birthday']);
    
    foreach ($clients as $client) {
        // Queue birthday email
        if (!empty($client->email)) {
            // Check if already sent today
            $db->where('client_id', $client->id);
            $db->where('email_type', 'birthday_wish');
            $db->where('DATE(created_at)', date('Y-m-d'));
            $existing = $db->getOne('crm_email_queue');
            
            if (!$existing) {
                $age = date('Y') - date('Y', strtotime($client->birthday));
                
                $db->insert('crm_email_queue', [
                    'client_id' => $client->id,
                    'recipient_email' => $client->email,
                    'recipient_name' => $client->name,
                    'email_type' => 'birthday_wish',
                    'metadata' => json_encode([
                        'client_name' => $client->name,
                        'age' => $age
                    ]),
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $sent_count++;
            }
        }
        
        // Queue birthday SMS
        if (!empty($client->phone)) {
            $db->where('client_id', $client->id);
            $db->where('sms_type', 'birthday_wish');
            $db->where('DATE(created_at)', date('Y-m-d'));
            $existing_sms = $db->getOne('crm_sms_queue');
            
            if (!$existing_sms) {
                $db->insert('crm_sms_queue', [
                    'client_id' => $client->id,
                    'phone_number' => $client->phone,
                    'sms_type' => 'birthday_wish',
                    'metadata' => json_encode([
                        'name' => $client->name
                    ]),
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $sms_count++;
            }
        }
    }
    
    // Get nominees with birthdays today
    if ($db->tableExists('crm_nominees')) {
        $db->where("DATE_FORMAT(date_of_birth, '%m-%d')", $today);
        $nominees = $db->get('crm_nominees', null, ['id', 'name', 'email', 'phone', 'date_of_birth', 'client_id']);
        
        foreach ($nominees as $nominee) {
            if (!empty($nominee->email)) {
                $db->where('recipient_email', $nominee->email);
                $db->where('email_type', 'birthday_wish');
                $db->where('DATE(created_at)', date('Y-m-d'));
                $existing = $db->getOne('crm_email_queue');
                
                if (!$existing) {
                    $db->insert('crm_email_queue', [
                        'client_id' => $nominee->client_id,
                        'recipient_email' => $nominee->email,
                        'recipient_name' => $nominee->name,
                        'email_type' => 'birthday_wish',
                        'metadata' => json_encode([
                            'client_name' => $nominee->name,
                            'age' => date('Y') - date('Y', strtotime($nominee->date_of_birth))
                        ]),
                        'status' => 'queued',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    $sent_count++;
                }
            }
        }
    }
    
    // Log result
    if ($db->tableExists('crm_audit_trail')) {
        $db->insert('crm_audit_trail', [
            'client_id' => 0,
            'action_type' => 'system',
            'action_category' => 'automation',
            'action_description' => "Birthday automation: queued {$sent_count} emails, {$sms_count} SMS",
            'performed_by' => 0, // System
            'performed_at' => date('Y-m-d H:i:s'),
            'ip_address' => 'CRON'
        ]);
    }
    
    echo "[Birthday Wishes] Queued {$sent_count} emails, {$sms_count} SMS\n";
    
} catch (Exception $e) {
    echo "[Birthday Wishes] Error: " . $e->getMessage() . "\n";
}
