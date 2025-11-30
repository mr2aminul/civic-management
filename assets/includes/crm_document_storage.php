<?php
/**
 * CRM Document Storage Helper
 * Auto-create client/purchase directories and manage documents
 * Integrates with file manager system
 */

/**
 * Auto-create directory structure for a new client
 */
function crm_create_client_directories($client_id) {
    global $db;
    
    $client_id = (int)$client_id;
    if ($client_id <= 0) {
        return ['success' => false, 'message' => 'Invalid client ID'];
    }
    
    $base_path = "storage/client_docs/{$client_id}";
    $directories = [
        $base_path,
        "{$base_path}/Documents",
        "{$base_path}/Invoices",
        "{$base_path}/Receipts",
        "{$base_path}/Schedules",
        "{$base_path}/Agreements",
        "{$base_path}/Purchases"
    ];
    
    $created = [];
    
    try {
        foreach ($directories as $dir) {
            $folder_data = [
                'user_id' => 0,
                'filename' => basename($dir),
                'original_filename' => basename($dir),
                'path' => $dir,
                'is_folder' => 1,
                'is_global' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'metadata' => json_encode(['client_id' => $client_id, 'type' => 'client_directory']),
                'client_id' => $client_id
            ];
            
            $existing = $db->where('path', $dir)->getOne('fm_files', ['id']);
            if (!$existing) {
                $folder_id = $db->insert('fm_files', $folder_data);
                if ($folder_id) {
                    $created[] = $dir;
                    
                    $db->insert('fm_activity_log', [
                        'user_id' => 0,
                        'file_id' => $folder_id,
                        'action' => 'create_folder',
                        'details' => "Auto-created client directory: {$dir}",
                        'ip_address' => 'SYSTEM',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Client directories created',
            'paths' => $created,
            'count' => count($created)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Auto-create purchase-specific sub-directories
 */
function crm_create_purchase_directories($client_id, $file_num, $purchase_id) {
    global $db;
    
    $client_id = (int)$client_id;
    $purchase_id = (int)$purchase_id;
    
    if ($client_id <= 0 || empty($file_num)) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }
    
    crm_create_client_directories($client_id);
    
    $purchase_base = "storage/client_docs/{$client_id}/Purchases/{$file_num}";
    $directories = [
        $purchase_base,
        "{$purchase_base}/Schedules",
        "{$purchase_base}/Invoices",
        "{$purchase_base}/Receipts",
        "{$purchase_base}/Agreements",
        "{$purchase_base}/Certificates",
        "{$purchase_base}/Refunds"
    ];
    
    $created = [];
    
    try {
        foreach ($directories as $dir) {
            $folder_data = [
                'user_id' => 0,
                'filename' => basename($dir),
                'original_filename' => basename($dir),
                'path' => $dir,
                'is_folder' => 1,
                'is_global' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'metadata' => json_encode([
                    'client_id' => $client_id,
                    'purchase_id' => $purchase_id,
                    'file_num' => $file_num,
                    'type' => 'purchase_directory'
                ]),
                'client_id' => $client_id,
                'purchase_id' => $purchase_id
            ];
            
            $existing = $db->where('path', $dir)->getOne('fm_files', ['id']);
            if (!$existing) {
                $folder_id = $db->insert('fm_files', $folder_data);
                if ($folder_id) {
                    $created[] = $dir;
                    
                    $db->insert('fm_activity_log', [
                        'user_id' => 0,
                        'file_id' => $folder_id,
                        'action' => 'create_folder',
                        'details' => "Auto-created purchase directory: {$dir}",
                        'ip_address' => 'SYSTEM',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
        
        $db->where('id', $purchase_id)->update('wo_booking_helper', [
            'document_folder_path' => $purchase_base
        ]);
        
        return [
            'success' => true,
            'message' => 'Purchase directories created',
            'base_path' => $purchase_base,
            'paths' => $created,
            'count' => count($created)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Store a document in the proper client/purchase folder
 */
/**
 * Store a document in the proper client/purchase folder
 * Integrates with file_manager_helper for R2 and DB tracking
 */
function crm_store_document($source_path, $client_id, $purchase_id = null, $document_type = 'document', $metadata = []) {
    global $db;
    
    // Ensure helper is loaded
    if (!function_exists('fm_get_config')) {
        require_once __DIR__ . '/file_manager_helper.php';
    }
    
    if (!file_exists($source_path)) {
        return ['success' => false, 'message' => 'Source file not found'];
    }
    
    $cfg = fm_get_config();
    $storage_root = $cfg['local_storage']; // e.g. /home/civicbd/civicgroup/storage
    
    $client_id = (int)$client_id;
    
    // Determine relative path structure
    if ($purchase_id) {
        $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper', ['file_num']);
        $file_num = $purchase ? $purchase->file_num : 'Unknown';
        
        $subfolder_map = [
            'invoice' => 'Invoices',
            'receipt' => 'Receipts',
            'schedule' => 'Schedules',
            'agreement' => 'Agreements',
            'certificate' => 'Certificates',
            'refund' => 'Refunds'
        ];
        $subfolder = $subfolder_map[$document_type] ?? 'Documents';
        
        // Logical path for DB
        $relative_path = "client_docs/{$client_id}/Purchases/{$file_num}/{$subfolder}";
    } else {
        $relative_path = "client_docs/{$client_id}/Documents";
    }
    
    // Physical path
    $destination_dir = $storage_root . '/' . $relative_path;
    
    if (!is_dir($destination_dir)) {
        @mkdir($destination_dir, 0755, true);
    }
    
    $original_filename = basename($source_path);
    if (isset($metadata['original_filename'])) {
        $original_filename = $metadata['original_filename'];
    }
    
    $ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $safe_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $original_filename);
    
    // Avoid overwriting
    $final_name = $safe_name;
    $counter = 1;
    while (file_exists($destination_dir . '/' . $final_name)) {
        $name_parts = pathinfo($safe_name);
        $final_name = $name_parts['filename'] . '_' . $counter . '.' . $name_parts['extension'];
        $counter++;
    }
    
    $full_dest_path = $destination_dir . '/' . $final_name;
    
    // Move/Copy file
    if (is_uploaded_file($source_path)) {
        if (!move_uploaded_file($source_path, $full_dest_path)) {
             // Try copy if move fails (sometimes needed for temp files)
             if (!copy($source_path, $full_dest_path)) {
                 return ['success' => false, 'message' => 'Failed to move uploaded file'];
             }
        }
    } else {
        if (!copy($source_path, $full_dest_path)) {
            return ['success' => false, 'message' => 'Failed to copy file'];
        }
    }
    
    $file_size = filesize($full_dest_path);
    $checksum = md5_file($full_dest_path);
    
    // Insert into fm_files
    $file_data = [
        'user_id' => 0, // System or current user? Let's use 0 for system or get current user if possible
        'filename' => $final_name,
        'original_filename' => $original_filename,
        'path' => $relative_path . '/' . $final_name, // Store relative path from storage root
        'file_type' => $ext,
        'mime_type' => mime_content_type($full_dest_path),
        'size' => $file_size,
        'checksum' => $checksum,
        'is_folder' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'metadata' => json_encode(array_merge($metadata, [
            'client_id' => $client_id,
            'purchase_id' => $purchase_id,
            'document_type' => $document_type
        ])),
        'client_id' => $client_id,
        'purchase_id' => $purchase_id
    ];
    
    // Use fm_insert if available, else direct DB
    if (function_exists('fm_insert')) {
        $file_id = fm_insert('fm_files', $file_data);
    } else {
        $file_id = $db->insert('fm_files', $file_data);
    }
    
    if (!$file_id) {
        return ['success' => false, 'message' => 'Failed to insert file record'];
    }
    
    // R2 Integration
    $r2_key = 'files/' . $relative_path . '/' . $final_name;
    
    // Check auto-upload config
    $should_upload = false;
    if (!empty($cfg['auto_upload_types']) && in_array($ext, $cfg['auto_upload_types'])) {
        $should_upload = true;
    }
    
    if ($should_upload) {
        if (function_exists('fm_enqueue_r2_upload')) {
            fm_enqueue_r2_upload($full_dest_path, $r2_key, $file_id);
        }
    }
    
    // Insert into crm_documents (legacy/redundant but kept for compatibility)
    $db->insert('crm_documents', [
        'purchase_id' => $purchase_id,
        'client_id' => $client_id,
        'invoice_id' => $metadata['invoice_id'] ?? null,
        'receipt_id' => $metadata['receipt_id'] ?? null,
        'document_type' => $document_type,
        'file_name' => $final_name,
        'file_path' => $relative_path . '/' . $final_name,
        'file_size' => $file_size,
        'mime_type' => $file_data['mime_type'],
        'generated_at' => date('Y-m-d H:i:s'),
        'generated_by' => 0,
        'fm_file_id' => $file_id
    ]);
    
    return [
        'success' => true,
        'file_id' => $file_id,
        'path' => $relative_path . '/' . $final_name,
        'full_path' => $full_dest_path,
        'size' => $file_size
    ];
}

/**
 * Get all documents for a purchase
 */
function crm_get_purchase_documents($purchase_id, $type = null) {
    global $db;
    
    $db->where('purchase_id', $purchase_id);
    if ($type) {
        $db->where('document_type', $type);
    }
    $db->orderBy('generated_at', 'DESC');
    
    return $db->get('crm_documents') ?: [];
}

/**
 * Delete a document (soft delete)
 */
function crm_delete_document($file_id, $user_id = 0) {
    if (function_exists('fm_delete_file')) {
        return fm_delete_file($file_id, $user_id, true);
    }
    
    global $db;
    return $db->where('id', $file_id)->update('fm_files', [
        'is_deleted' => 1,
        'deleted_at' => date('Y-m-d H:i:s'),
        'deleted_by' => $user_id
    ]);
}
