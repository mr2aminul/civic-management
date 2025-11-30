<?php
/**
 * Invoice Auto-Features Helper
 * Handles auto-PDF generation, storage, and email queuing
 * Requires: pdf_generator.php, crm_document_storage.php
 */

/**
 * Generate and store invoice PDF, then queue email
 * 
 * @param int $invoice_id The invoice ID
 * @return array Result with PDF path and email queue status
 */
function auto_generate_invoice_pdf($invoice_id) {
    global $db;
    
    try {
        // Helpers already loaded via init.php
        // No need to require_once anymore
        
        $generator = new PDFGenerator();
        
        // Generate PDF
        $pdf_result = $generator->generateInvoice($invoice_id);
        
        if (!isset($pdf_result['path']) || !file_exists($pdf_result['path'])) {
            return ['success' => false, 'message' => 'PDF generation failed'];
        }
        
        // Get invoice details
        $invoice = $db->where('id', $invoice_id)->getOne('crm_invoices');
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }
        
        // Store PDF in purchase folder
        $store_result = crm_store_document(
            $pdf_result['path'],
            $invoice->client_id,
            $invoice->purchase_id,
            'invoice',
            ['invoice_id' => $invoice_id, 'invoice_number' => $invoice->invoice_number]
        );
        
        if (!$store_result['success']) {
            return $store_result;
        }
        
        // Get client details
        $client = $db->where('id', $invoice->client_id)->getOne('crm_customers', ['name', 'email', 'phone']);
        
        // Queue email with PDF attachment
        if ($client && !empty($client->email)) {
            // Get purchase for file_num
            $purchase = $db->where('id', $invoice->purchase_id)->getOne('wo_booking_helper', ['file_num']);
            
            $db->insert('crm_email_queue', [
                'purchase_id' => $invoice->purchase_id,
                'client_id' => $invoice->client_id,
                'recipient_email' => $client->email,
                'recipient_name' => $client->name,
                'email_type' => 'invoice_created',
                'metadata' => json_encode([
                    'client_name' => $client->name,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'amount' => $invoice->amount,
                    'due_date' => $invoice->due_date,
                    'description' => $invoice->description,
                    'file_num' => $purchase->file_num ?? '-',
                    'pdf_path' => $store_result['full_path']
                ]),
                'attachment_path' => $store_result['full_path'],
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return [
            'success' => true,
            'pdf_path' => $store_result['path'],
            'file_id' => $store_result['file_id'],
            'email_queued' => !empty($client->email)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Generate and store receipt PDF for a payment
 * 
 * @param int $receipt_id The money receipt ID
 * @return array Result
 */
function auto_generate_receipt_pdf($receipt_id) {
    global $db;
    
    try {
        // Helpers already loaded via init.php
        
        $generator = new PDFGenerator();
        
        // Generate receipt PDF
        $pdf_result = $generator->generateReceipt($receipt_id);
        
        if (!isset($pdf_result['path']) || !file_exists($pdf_result['path'])) {
            return ['success' => false, 'message' => 'Receipt PDF generation failed'];
        }
        
        // Get receipt details
        $receipt = $db->where('id', $receipt_id)->getOne('crm_money_receipts');
        if (!$receipt) {
            return ['success' => false, 'message' => 'Receipt not found'];
        }
        
        // Store PDF
        $store_result = crm_store_document(
            $pdf_result['path'],
            $receipt->client_id,
            $receipt->purchase_id,
            'receipt',
            ['receipt_id' => $receipt_id, 'receipt_number' => $receipt->receipt_number]
        );
        
        if (!$store_result['success']) {
            return $store_result;
        }
        
        return [
            'success' => true,
            'pdf_path' => $store_result['path'],
            'full_path' => $store_result['full_path'],
            'file_id' => $store_result['file_id']
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Check if all payments are complete for a purchase
 * If yes, generate completion certificate
 * 
 * @param int $purchase_id The purchase ID
 * @return array Result with certificate info if generated
 */
function check_and_generate_completion_certificate($purchase_id) {
    global $db;
    
    try {
        // Check if all payment schedules are paid
        $db->where('purchase_id', $purchase_id);
        $db->where('status', [0, 2, 3], 'IN'); // Unpaid, partial, overdue
        $db->where('status', 99, '!='); // Not deleted
        $unpaid = $db->getOne('crm_payment_schedule');
        
        if ($unpaid) {
            // Still has unpaid installments
            return ['success' => false, 'message' => 'Payments not complete', 'all_paid' => false];
        }
        
        // Check if certificate already generated
        $existing_cert = $db->where('purchase_id', $purchase_id)
                            ->where('document_type', 'certificate')
                            ->getOne('crm_documents');
        
        if ($existing_cert) {
            return ['success' => false, 'message' => 'Certificate already exists', 'all_paid' => true];
        }
        
        // All paid! Generate completion certificate
        // Helpers already loaded via init.php
        
        $generator = new PDFGenerator();
        $pdf_result = $generator->generateCompletionCertificate($purchase_id);
        
        if (!isset($pdf_result['path']) || !file_exists($pdf_result['path'])) {
            return ['success' => false, 'message' => 'Certificate generation failed'];
        }
        
        // Get purchase details
        $purchase = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$purchase) {
            return ['success' => false, 'message' => 'Purchase not found'];
        }
        
        // Store certificate
        $store_result = crm_store_document(
            $pdf_result['path'],
            $purchase->client_id,
            $purchase_id,
            'certificate',
            ['completion_date' => date('Y-m-d')]
        );
        
        if (!$store_result['success']) {
            return $store_result;
        }
        
        // Get client and plot details for email
        $client = $db->where('id', $purchase->client_id)->getOne('crm_customers', ['name', 'email', 'phone']);
        $booking = $db->where('id', $purchase->booking_id)->getOne('wo_booking', ['plot', 'block', 'project']);
        
        // Queue congratulations email with certificate
        if ($client && !empty($client->email)) {
            $db->insert('crm_email_queue', [
                'purchase_id' => $purchase_id,
                'client_id' => $purchase->client_id,
                'recipient_email' => $client->email,
                'recipient_name' => $client->name,
                'email_type' => 'payment_completed',
                'metadata' => json_encode([
                    'client_name' => $client->name,
                    'file_num' => $purchase->file_num,
                    'plot' => $booking->plot ?? '-',
                    'block' => $booking->block ?? '-',
                    'project' => $booking->project ?? '-',
                    'pdf_path' => $store_result['full_path']
                ]),
                'attachment_path' => $store_result['full_path'],
                'status' => 'queued',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Also queue SMS
            if (!empty($client->phone)) {
                $db->insert('crm_sms_queue', [
                    'purchase_id' => $purchase_id,
                    'client_id' => $purchase->client_id,
                    'phone_number' => $client->phone,
                    'sms_type' => 'payment_completed',
                    'metadata' => json_encode([
                        'name' => $client->name,
                        'file_num' => $purchase->file_num
                    ]),
                    'status' => 'queued',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        return [
            'success' => true,
            'all_paid' => true,
            'certificate_generated' => true,
            'pdf_path' => $store_result['path'],
            'file_id' => $store_result['file_id'],
            'email_queued' => !empty($client->email)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
