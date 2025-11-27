<?php
/**
 * Birthday Wish Automation
 * Automatically queues birthday wish emails for clients and nominees
 * Run this daily via cron job (e.g., at midnight)
 */

require_once '../assets/init.php';

if (!defined('T_CUSTOMERS')) {
    die('Configuration error');
}

$today = date('m-d'); // Format: MM-DD

try {
    $sent_count = 0;
    
    // Get clients with birthdays today
    $db->where("DATE_FORMAT(date_of_birth, '%m-%d')", $today);
    $clients = $db->get(T_CUSTOMERS, null, ['id', 'name', 'email', 'date_of_birth']);
    
    foreach ($clients as $client) {
        if (empty($client->email)) continue;
        
        // Queue birthday email
        if ($db->tableExists('crm_email_queue')) {
            // Check if already sent today
            $db->where('recipient_email', $client->email);
            $db->where('email_type', 'birthday_wish');
            $db->where('DATE(queue_date)', date('Y-m-d'));
            $existing = $db->getOne('crm_email_queue');
            
            if ($existing) continue; // Already queued
            
            $age = date('Y') - date('Y', strtotime($client->date_of_birth));
            
            $db->insert('crm_email_queue', [
                'client_id' => $client->id,
                'recipient_email' => $client->email,
                'recipient_name' => $client->name,
                'email_type' => 'birthday_wish',
                'subject' => "🎉 Happy Birthday {$client->name}!",
                'body' => "Dear {$client->name},<br><br>🎂 Wishing you a very Happy Birthday!<br><br>May this special day bring you joy, happiness, and wonderful moments.<br><br>Thank you for being a valued member of the Civic Group family.<br><br>Warm regards,<br>Civic Group BD",
                'status' => 'pending',
                'queue_date' => date('Y-m-d H:i:s')
            ]);
            $sent_count++;
        }
    }
    
    // Get nominees with birthdays today
    if ($db->tableExists('crm_nominees')) {
        $db->where("DATE_FORMAT(date_of_birth, '%m-%d')", $today);
        $nominees = $db->get('crm_nominees', null, ['id', 'name', 'email', 'date_of_birth', 'client_id']);
        
        foreach ($nominees as $nominee) {
            if (empty($nominee->email)) continue;
            
            // Check if already sent today
            $db->where('recipient_email', $nominee->email);
            $db->where('email_type', 'birthday_wish');
            $db->where('DATE(queue_date)', date('Y-m-d'));
            $existing = $db->getOne('crm_email_queue');
            
            if ($existing) continue;
            
            $db->insert('crm_email_queue', [
                'client_id' => $nominee->client_id,
                'recipient_email' => $nominee->email,
                'recipient_name' => $nominee->name,
                'email_type' => 'birthday_wish',
                'subject' => "🎉 Happy Birthday {$nominee->name}!",
                'body' => "Dear {$nominee->name},<br><br>🎂 Wishing you a very Happy Birthday!<br><br>May this special day bring you joy, happiness, and wonderful moments.<br><br>Thank you for being part of the Civic Group family.<br><br>Warm regards,<br>Civic Group BD",
                'status' => 'pending',
                'queue_date' => date('Y-m-d H:i:s')
            ]);
            $sent_count++;
        }
    }
    
    // Log result
    if ($db->tableExists('crm_audit_trail')) {
        $db->insert('crm_audit_trail', [
            'user_id' => 0, // System
            'action' => 'birthday_automation',
            'details' => json_encode(['queued_count' => $sent_count, 'date' => date('Y-m-d')]),
            'ip_address' => 'CRON',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo "Birthday automation complete. Queued $sent_count emails.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
