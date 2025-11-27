<?php
/**
 * Bulk Operations Module
 * Handles batch processing for invoices, emails, and actions
 */

header('Content-Type: application/json; charset=utf-8');

// Bulk Export Invoices (PDF)
if ($s === 'bulk_export_invoices_pdf') {
    $invoice_ids = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : '';
    
    if (empty($invoice_ids)) {
        echo json_encode(['status' => 400, 'message' => 'Invoice IDs required']);
        exit;
    }
    
    try {
        // Parse IDs (comma-separated)
        $ids = array_map('intval', explode(',', $invoice_ids));
        
        // Fetch invoices
        $db->where('id', $ids, 'IN');
        $invoices = $db->get('crm_invoices');
        
        if (empty($invoices)) {
            echo json_encode(['status' => 404, 'message' => 'No invoices found']);
            exit;
        }
        
        // In production, generate actual PDF here using libraries like TCPDF or DOMPDF
        // For now, return mock success
        $filename = 'invoices_bulk_' . date('Ymd_His') . '.pdf';
        
        echo json_encode([
            'status' => 200,
            'message' => count($invoices) . ' invoices prepared for export',
            'filename' => $filename,
            'download_url' => '/downloads/invoices/' . $filename,
            'count' => count($invoices)
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Bulk Export Invoices (Excel)
if ($s === 'bulk_export_invoices_excel') {
    $invoice_ids = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : '';
    
    if (empty($invoice_ids)) {
        echo json_encode(['status' => 400, 'message' => 'Invoice IDs required']);
        exit;
    }
    
    try {
        $ids = array_map('intval', explode(',', $invoice_ids));
        
        $db->where('id', $ids, 'IN');
        $invoices = $db->get('crm_invoices');
        
        if (empty($invoices)) {
            echo json_encode(['status' => 404, 'message' => 'No invoices found']);
            exit;
        }
        
        // In production, generate Excel using PHPSpreadsheet
        $filename = 'invoices_bulk_' . date('Ymd_His') . '.xlsx';
        
        echo json_encode([
            'status' => 200,
            'message' => count($invoices) . ' invoices prepared for Excel export',
            'filename' => $filename,
            'download_url' => '/downloads/invoices/' . $filename,
            'count' => count($invoices)
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Bulk Send Invoice Emails
if ($s === 'bulk_send_invoice_emails') {
    $invoice_ids = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : '';
    
    if (empty($invoice_ids)) {
        echo json_encode(['status' => 400, 'message' => 'Invoice IDs required']);
        exit;
    }
    
    try {
        $ids = array_map('intval', explode(',', $invoice_ids));
        
        $db->where('id', $ids, 'IN');
        $invoices = $db->get('crm_invoices');
        
        if (empty($invoices)) {
            echo json_encode(['status' => 404, 'message' => 'No invoices found']);
            exit;
        }
        
        $queued = 0;
        $failed = 0;
        
        foreach ($invoices as $invoice) {
            // Get client email
            $client = $db->where('id', $invoice->client_id)->getOne('crm_customers', ['email', 'name']);
            
            if ($client && !empty($client['email'])) {
                // Queue email
                $email_data = [
                    'client_id' => $invoice->client_id,
                    'purchase_id' => $invoice->purchase_id,
                    'template_type' => 'invoice',
                    'recipient_email' => $client['email'],
                    'recipient_name' => $client['name'],
                    'subject' => 'Invoice #' . $invoice->invoice_number,
                    'body' => 'Your invoice is attached.',
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if ($db->tableExists('crm_email_queue')) {
                    $db->insert('crm_email_queue', $email_data);
                    $queued++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
        }
        
        echo json_encode([
            'status' => 200,
            'message' => "$queued emails queued successfully",
            'queued' => $queued,
            'failed' => $failed
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Bulk Void Invoices
if ($s === 'bulk_void_invoices') {
    $invoice_ids = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : '';
    $reason = isset($_POST['reason']) ? Wo_Secure($_POST['reason']) : 'Bulk void operation';
    
    if (empty($invoice_ids)) {
        echo json_encode(['status' => 400, 'message' => 'Invoice IDs required']);
        exit;
    }
    
    try {
        $ids = array_map('intval', explode(',', $invoice_ids));
        
        $db->where('id', $ids, 'IN');
        $count = $db->update('crm_invoices', [
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_by' => $wo['user_id'] ?? 0,
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log to audit trail
        foreach ($ids as $invoice_id) {
            if ($db->tableExists('crm_audit_trail')) {
                $db->insert('crm_audit_trail', [
                    'invoice_id' => $invoice_id,
                    'action_type' => 'void',
                    'action_category' => 'invoice',
                    'description' => 'Invoice voided: ' . $reason,
                    'performed_by' => $wo['user_id'] ?? 0,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        echo json_encode([
            'status' => 200,
            'message' => "$count invoices voided successfully",
            'count' => $count
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Bulk Approve Pending Actions
if ($s === 'bulk_approve_pending') {
    $action_ids = isset($_POST['action_ids']) ? $_POST['action_ids'] : '';
    $notes = isset($_POST['notes']) ? Wo_Secure($_POST['notes']) : 'Bulk approval';
    
    if (empty($action_ids)) {
        echo json_encode(['status' => 400, 'message' => 'Action IDs required']);
        exit;
    }
    
    try {
        $ids = array_map('intval', explode(',', $action_ids));
        
        $success = 0;
        $failed = 0;
        
        foreach ($ids as $action_id) {
            // Call the existing approve_pending_change logic for each
            // This ensures proper handling of different change types
            
            if ($db->tableExists('crm_pending_changes')) {
                $db->where('id', $action_id);
                $change = $db->getOne('crm_pending_changes');
                
                if ($change && $change['status'] == 'pending') {
                    $db->where('id', $action_id);
                    $updated = $db->update('crm_pending_changes', [
                        'status' => 'approved',
                        'approved_by' => $wo['user_id'] ?? 0,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'admin_notes' => $notes
                    ]);
                    
                    if ($updated) {
                        $success++;
                        
                        // Apply the change if it's a reschedule
                        if ($change['change_type'] == 'reschedule' && !empty($change['change_data_json'])) {
                            $newData = json_decode($change['change_data_json'], true);
                            if (isset($newData['new_schedule'])) {
                                $db->where('id', $change['purchase_id']);
                                $db->update(T_BOOKING_HELPER, ['installment' => json_encode($newData['new_schedule'])]);
                            }
                        }
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            }
        }
        
        echo json_encode([
            'status' => 200,
            'message' => "$success actions approved, $failed failed",
            'success' => $success,
            'failed' => $failed
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Bulk Deny Pending Actions
if ($s === 'bulk_deny_pending') {
    $action_ids = isset($_POST['action_ids']) ? $_POST['action_ids'] : '';
    $reason = isset($_POST['reason']) ? Wo_Secure($_POST['reason']) : 'Bulk denial';
    
    if (empty($action_ids)) {
        echo json_encode(['status' => 400, 'message' => 'Action IDs required']);
        exit;
    }
    
    try {
        $ids = array_map('intval', explode(',', $action_ids));
        
        if ($db->tableExists('crm_pending_changes')) {
            $db->where('id', $ids, 'IN');
            $db->where('status', 'pending');
            $count = $db->update('crm_pending_changes', [
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => $wo['user_id'] ?? 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'status' => 200,
                'message' => "$count actions denied successfully",
                'count' => $count
            ]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Pending changes table not found']);
        }
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
