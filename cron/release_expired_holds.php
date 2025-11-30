<?php
/**
 * Release Expired Plot Holds
 * Auto-releases plot holds that have expired
 * Run daily via cron job
 */

if (!defined('RUNNING_FROM_CRON')) {
    die('Must be run from cron-job.php');
}

try {
    $now = date('Y-m-d H:i:s');
    $released_count = 0;
    
    // Get all held plots with expired hold_end_date
    $db->where('hold_status', 1); // Currently held
    $db->where('hold_auto_release', 1); // Auto-release enabled
    $db->where('hold_end_date', $now, '<='); // Expired
    $expired_holds = $db->get('wo_booking', null, [
        'id', 'plot', 'block', 'project', 'held_by', 'hold_start_date', 'hold_end_date', 'hold_reason'
    ]);
    
    foreach ($expired_holds as $plot) {
        // Release the hold
        $db->where('id', $plot->id);
        $db->update('wo_booking', [
            'hold_status' => 0,
            'hold_released_at' => $now,
            'hold_released_by' => 0 // System auto-release
        ]);
        
        $released_count++;
        
        // Notify the employee who placed the hold
        if ($plot->held_by > 0) {
            $employee = $db->where('user_id', $plot->held_by)->getOne('users', ['email', 'name']);
            
            if ($employee && !empty($employee->email)) {
                // Queue notification email
                $db->insert('crm_email_queue', [
                    'client_id' => 0,
                    'recipient_email' => $employee->email,
                    'recipient_name' => $employee->name,
                    'email_type' => 'hold_expired',
                    'metadata' => json_encode([
                        'name' => $employee->name,
                        'plot' => $plot->plot,
                        'block' => $plot->block,
                        'project' => $plot->project,
                        'hold_start' => $plot->hold_start_date,
                        'hold_end' => $plot->hold_end_date,
                        'hold_reason' => $plot->hold_reason
                    ]),
                    'status' => 'queued',
                    'created_at' => $now
                ]);
                
                // Queue SMS
                $user_phone = $db->where('user_id', $plot->held_by)->getValue('users', 'phone');
                if ($user_phone) {
                    $db->insert('crm_sms_queue', [
                        'client_id' => 0,
                        'phone_number' => $user_phone,
                        'sms_type' => 'hold_expired',
                        'metadata' => json_encode([
                            'plot' => $plot->plot . ', ' . $plot->block
                        ]),
                        'status' => 'queued',
                        'created_at' => $now
                    ]);
                }
            }
        }
        
        // Log to audit trail
        $db->insert('crm_audit_trail', [
            'client_id' => 0,
            'action_type' => 'system',
            'action_category' => 'plot_management',
            'action_description' => "Auto-released expired hold on Plot {$plot->plot}, Block {$plot->block}",
            'before_values' => json_encode(['hold_status' => 1]),
            'after_values' => json_encode(['hold_status' => 0, 'released_by' => 'system']),
            'performed_by' => 0,
            'performed_at' => $now,
            'ip_address' => 'CRON'
        ]);
    }
    
    echo "[Plot Holds] Released {$released_count} expired holds\n";
    
} catch (Exception $e) {
    echo "[Plot Holds] Error: " . $e->getMessage() . "\n";
}
