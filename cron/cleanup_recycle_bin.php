<?php
/**
 * Cleanup Recycle Bin
 * Permanently deletes files that have been in recycle bin for 30+ days
 * Run daily via cron job
 */

if (!defined('RUNNING_FROM_CRON')) {
    die('Must be run from cron-job.php');
}

try {
    $now = date('Y-m-d H:i:s');
    $deleted_count = 0;
    $freed_bytes = 0;
    
    // Get items that should be auto-deleted
    $db->where('auto_delete_at', $now, '<=');
    $db->where('restored_at', NULL, 'IS');
    $db->where('force_deleted_at', NULL, 'IS');
    $expired_items = $db->get('fm_recycle_bin');
    
    foreach ($expired_items as $item) {
        // Get the file record
        $file = $db->where('id', $item->file_id)->getOne('fm_files');
        
        if ($file) {
            // Delete physical file if it exists
            $cfg = fm_get_config();
            $full_path = $cfg['local_storage'] . '/' . $file->filename;
            
            if (file_exists($full_path)) {
                @unlink($full_path);
                $freed_bytes += $file->size;
            }
            
            // Update user quota
            if (function_exists('fm_update_user_quota')) {
                fm_update_user_quota($file->user_id, -$file->size);
            }
        }
        
        // Mark as permanently deleted
        $db->where('id', $item->id);
        $db->update('fm_recycle_bin', [
            'force_deleted_at' => $now,
            'force_deleted_by' => 0 // System
        ]);
        
        $deleted_count++;
        
        // Log activity
        if ($db->tableExists('fm_activity_log')) {
            $db->insert('fm_activity_log', [
                'user_id' => 0,
                'file_id' => $item->file_id,
                'action' => 'permanent_delete',
                'details' => "Auto-deleted after 30 days: {$item->filename}",
                'ip_address' => 'CRON',
                'created_at' => $now
            ]);
        }
    }
    
    $freed_mb = round($freed_bytes / 1024 / 1024, 2);
    
    // Log to audit trail
    if ($db->tableExists('crm_audit_trail')) {
        $db->insert('crm_audit_trail', [
            'client_id' => 0,
            'action_type' => 'system',
            'action_category' => 'file_management',
            'action_description' => "Recycle bin cleanup: deleted {$deleted_count} files, freed {$freed_mb} MB",
            'performed_by' => 0,
            'performed_at' => $now,
            'ip_address' => 'CRON'
        ]);
    }
    
    echo "[Recycle Bin] Permanently deleted {$deleted_count} files, freed {$freed_mb} MB\n";
    
} catch (Exception $e) {
    echo "[Recycle Bin] Error: " . $e->getMessage() . "\n";
}
