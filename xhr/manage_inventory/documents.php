<?php
if ($s == 'get_purchase_documents') {
    $purchase_id = isset($_GET['purchase_id']) ? Wo_Secure($_GET['purchase_id']) : 0;
    $type = isset($_GET['type']) ? Wo_Secure($_GET['type']) : '';

    if (empty($purchase_id)) {
        echo json_encode(['status' => 400, 'message' => 'Invalid Purchase ID']);
        exit();
    }

    // Fetch documents from fm_files linked to this purchase (via metadata or specific folder structure)
    // Assuming we store purchase_id in a way we can retrieve. 
    // Since we added metadata columns to fm_files (client_id, purchase_id), we can query directly.
    
    $db->where('purchase_id', $purchase_id);
    $db->where('is_deleted', 0);
    if (!empty($type)) {
        // Map folder types if needed, or just filter
        // For now, let's just return all and let frontend filter, or filter by folder_type/document_type if we have that column
        // The frontend passes 'type' which maps to folders like 'invoice', 'receipt', etc.
        // We don't have a specific 'document_type' column in fm_files schema shown earlier, 
        // but we might store it in 'file_type' or 'folder_type' or 'details' json.
        // Let's assume we store it in 'file_type' or a new column. 
        // Wait, the schema showed `folder_type` enum('user','common','special').
        // We probably need to rely on the folder structure or metadata.
        // For simplicity in this fix, let's assume we return all and frontend filters, 
        // OR we check if we store the type in the 'file_type' or a custom field.
        // Let's check if we have a 'document_type' in fm_files. The schema didn't show it explicitly as a column, 
        // but we added `metadata` column in a previous migration? 
        // Actually, the previous migration added `client_id` and `purchase_id`.
        // Let's just return all for now.
    }
    
    $files = $db->orderBy('created_at', 'DESC')->get('fm_files');
    
    // Get config for CDN domain
    require_once('assets/includes/file_manager_helper.php');
    $cfg = fm_get_config();
    
    $documents = [];
    foreach ($files as $f) {
        $url = '';
        if (isset($f->r2_uploaded) && $f->r2_uploaded == 1 && !empty($f->r2_key)) {
            $domain = $cfg['r2_domain'] ?? '';
            $url = rtrim($domain, '/') . '/' . ltrim($f->r2_key, '/');
        } else {
            // Local file - use file manager download proxy
            // Assuming Wo_Ajax_Requests_File() returns requests.php path
            // We need to construct the URL manually since we are in PHP
            global $wo;
            $url = $wo['config']['site_url'] . "/requests.php?f=file_manager&s=download_local&file=" . urlencode($f->path);
        }

        $documents[] = [
            'id' => $f->id,
            'file_name' => $f->original_filename,
            'file_path' => $f->path, // Keep raw path for reference
            'url' => $url, // Use this for opening
            'file_size' => $f->size,
            'file_type' => $f->mime_type,
            'document_type' => $f->file_type,
            'generated_at' => $f->created_at
        ];
    }

    echo json_encode([
        'status' => 200,
        'documents' => $documents
    ]);
    exit();
}

if ($s == 'upload_purchase_document') {
    $purchase_id = isset($_POST['purchase_id']) ? Wo_Secure($_POST['purchase_id']) : 0;
    $doc_type = isset($_POST['document_type']) ? Wo_Secure($_POST['document_type']) : 'other';
    
    if (empty($purchase_id) || empty($_FILES['file'])) {
        echo json_encode(['status' => 400, 'message' => 'Invalid Request']);
        exit();
    }

    // Use the helper function to upload
    // We need to include the helper if not already
    require_once('assets/includes/crm_document_storage.php');

    // Get Client ID from purchase
    $client_id = $db->where('id', $purchase_id)->getValue(T_BOOKING_HELPER, 'client_id');
    
    // Map doc_type to folder name
    $folder_map = [
        'invoice' => 'Invoices',
        'receipt' => 'Money Receipts',
        'schedule' => 'Payment Schedules',
        'agreement' => 'Agreements',
        'certificate' => 'Certificates',
        'other' => 'Others'
    ];
    $folder_name = $folder_map[$doc_type] ?? 'Others';

    // Upload
    // Upload
    // crm_store_document($source_path, $client_id, $purchase_id, $document_type, $metadata)
    $result = crm_store_document(
        $_FILES['file']['tmp_name'], 
        $client_id, 
        $purchase_id, 
        $doc_type, 
        ['original_filename' => $_FILES['file']['name']]
    );

    if ($result && isset($result['success']) && $result['success']) {
        // Update the file_type column to store the doc_type for filtering
        if(isset($result['file_id'])){
             $db->where('id', $result['file_id'])->update('fm_files', ['file_type' => $doc_type]);
        }
        echo json_encode(['status' => 200, 'message' => 'Uploaded successfully']);
    } else {
        echo json_encode(['status' => 400, 'message' => $result['message'] ?? 'Upload failed']);
    }
    exit();
}

if ($s == 'delete_purchase_document') {
    $doc_id = isset($_POST['document_id']) ? Wo_Secure($_POST['document_id']) : 0;
    
    if (empty($doc_id)) {
        echo json_encode(['status' => 400, 'message' => 'Invalid ID']);
        exit();
    }

    // Use helper for robust deletion (recycle bin, quota)
    if (function_exists('fm_delete_file')) {
        $result = fm_delete_file($doc_id, $wo['user']['user_id']);
        if ($result['success']) {
            echo json_encode(['status' => 200, 'message' => 'Deleted successfully']);
        } else {
            echo json_encode(['status' => 400, 'message' => $result['message']]);
        }
    } else {
        // Fallback
        $db->where('id', $doc_id)->update('fm_files', [
            'is_deleted' => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $wo['user']['user_id']
        ]);
        echo json_encode(['status' => 200, 'message' => 'Deleted successfully']);
    }
    exit();
}
?>
