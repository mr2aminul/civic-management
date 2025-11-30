<?php
/**
 * PDF Generator Helper
 * Generates PDFs for invoices, receipts, certificates, schedules
 * Uses TCPDF library
 */


class PDFGenerator {
    private $pdf;
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Generate Invoice PDF
     */
    public function generateInvoice($invoice_id) {
        // Get invoice data
        $invoice = $this->db->where('id', $invoice_id)->getOne('crm_invoices');
        if (!$invoice) {
            throw new Exception("Invoice not found");
        }
        
        // Get related data
        $purchase = $this->db->where('id', $invoice->purchase_id)->getOne('wo_booking_helper');
        $client = $this->db->where('id', $invoice->client_id)->getOne('crm_customers');
        $plot = $this->db->where('id', $purchase->booking_id)->getOne('wo_booking');
        
        // Create PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        
        // Set document info
        $pdf->SetCreator('Civic Group BD');
        $pdf->SetAuthor('Civic Group BD');
        $pdf->SetTitle('Invoice ' . $invoice->invoice_number);
        
        // Remove header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Add page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Company Header
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'CIVIC GROUP BD', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Address: [Company Address]', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Phone: [Phone] | Email: [Email]', 0, 1, 'C');
        $pdf->Ln(10);
        
        // Invoice Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Invoice Details (Two columns)
        $pdf->SetFont('helvetica', '', 10);
        
        // Left column
        $pdf->Cell(95, 6, 'Invoice Number: ' . $invoice->invoice_number, 0, 0);
        $pdf->Cell(95, 6, 'Date: ' . date('d M Y', strtotime($invoice->invoice_date)), 0, 1);
        
        $pdf->Cell(95, 6, 'Due Date: ' . date('d M Y', strtotime($invoice->due_date)), 0, 0);
        $pdf->Cell(95, 6, 'File Number: ' . $purchase->file_num, 0, 1);
        $pdf->Ln(5);
        
        // Client Details
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'Bill To:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $client->name, 0, 1);
        $pdf->Cell(0, 6, $client->address, 0, 1);
        $pdf->Cell(0, 6, 'Phone: ' . $client->phone, 0, 1);
        $pdf->Ln(10);
        
        // Plot Details
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'Property Details:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Project: ' . $plot->project, 0, 1);
        $pdf->Cell(0, 6, 'Block: ' . $plot->block . ' | Plot: ' . $plot->plot . ' | Road: ' . $plot->road, 0, 1);
        $pdf->Cell(0, 6, 'Size: ' . $plot->katha . ' Katha | Facing: ' . $plot->facing, 0, 1);
        $pdf->Ln(10);
        
        // Invoice Items Table
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(100, 7, 'Description', 1, 0, 'L', true);
        $pdf->Cell(45, 7, 'Type', 1, 0, 'C', true);
        $pdf->Cell(45, 7, 'Amount', 1, 1, 'R', true);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(100, 7, $invoice->description ?? ucfirst(str_replace('_', ' ', $invoice->invoice_type)), 1, 0);
        $pdf->Cell(45, 7, strtoupper($invoice->invoice_type), 1, 0, 'C');
        $pdf->Cell(45, 7, '৳ ' . number_format($invoice->amount, 2), 1, 1, 'R');
        
        $pdf->Ln(5);
        
        // Total Section
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(145, 7, 'Total Amount:', 0, 0, 'R');
        $pdf->Cell(45, 7, '৳ ' . number_format($invoice->amount, 2), 1, 1, 'R');
        
        if ($invoice->paid_amount > 0) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(145, 7, 'Paid:', 0, 0, 'R');
            $pdf->Cell(45, 7, '৳ ' . number_format($invoice->paid_amount, 2), 1, 1, 'R');
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(145, 7, 'Balance Due:', 0, 0, 'R');
            $pdf->Cell(45, 7, '৳ ' . number_format($invoice->remaining_amount, 2), 1, 1, 'R');
        }
        
        $pdf->Ln(15);
        
        // Footer
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 5, 'Thank you for your business!', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Please make payment by the due date to avoid late fees.', 0, 1, 'C');
        
        // Save PDF
        $output_dir = dirname(__DIR__) . "/storage/client_docs/{$client->id}/Purchases/{$purchase->file_num}/Invoices/";
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        
        $filename = "Invoice_{$invoice->invoice_number}.pdf";
        $filepath = $output_dir . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return [
            'path' => $filepath,
            'filename' => $filename,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Generate Money Receipt PDF
     */
    public function generateReceipt($receipt_id) {
        // Get receipt data
        $receipt = $this->db->where('id', $receipt_id)->getOne('crm_money_receipts');
        if (!$receipt) {
            throw new Exception("Receipt not found");
        }
        
        // Get related data
        $purchase = $this->db->where('id', $receipt->purchase_id)->getOne('wo_booking_helper');
        $client = $this->db->where('id', $receipt->client_id)->getOne('crm_customers');
        
        // Get invoices paid
        $invoices_paid = json_decode($receipt->invoices_paid, true) ?? [];
        
        // Create PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Civic Group BD');
        $pdf->SetTitle('Receipt ' . $receipt->receipt_number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        
        // Company Header
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'CIVIC GROUP BD', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Money Receipt', 0, 1, 'C');
        $pdf->Ln(10);
        
        // Receipt Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Receipt Details
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(95, 6, 'Receipt Number: ' . $receipt->receipt_number, 0, 0);
        $pdf->Cell(95, 6, 'Date: ' . date('d M Y', strtotime($receipt->payment_date)), 0, 1);
        $pdf->Cell(95, 6, 'Payment Method: ' . strtoupper($receipt->payment_method), 0, 0);
        if ($receipt->transaction_reference) {
            $pdf->Cell(95, 6, 'Reference: ' . $receipt->transaction_reference, 0, 1);
        } else {
            $pdf->Ln();
        }
        $pdf->Ln(5);
        
        // Client Details
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'Received From:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $client->name, 0, 1);
        $pdf->Cell(0, 6, 'File Number: ' . $purchase->file_num, 0, 1);
        $pdf->Ln(10);
        
        // Payment Details Table
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(100, 7, 'Invoice Number', 1, 0, 'L', true);
        $pdf->Cell(45, 7, 'Amount Applied', 1, 1, 'R', true);
        
        $pdf->SetFont('helvetica', '', 10);
        $total_applied = 0;
        foreach ($invoices_paid as $inv) {
            $pdf->Cell(100, 7, $inv['invoice_number'], 1, 0);
            $pdf->Cell(45, 7, '৳ ' . number_format($inv['applied_amount'], 2), 1, 1, 'R');
            $total_applied += $inv['applied_amount'];
        }
        
        $pdf->Ln(5);
        
        // Total
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(100, 8, 'Total Amount Received:', 0, 0, 'R');
        $pdf->Cell(45, 8, '৳ ' . number_format($receipt->amount, 2), 1, 1, 'R');
        
        $pdf->Ln(15);
        
        // Footer
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 5, 'Thank you for your payment!', 0, 1, 'C');
        
        // Signature lines
        $pdf->Ln(20);
        $pdf->Cell(95, 5, '________________________', 0, 0, 'C');
        $pdf->Cell(95, 5, '________________________', 0, 1, 'C');
        $pdf->Cell(95, 5, 'Received By', 0, 0, 'C');
        $pdf->Cell(95, 5, 'Client Signature', 0, 1, 'C');
        
        // Save PDF
        $output_dir = dirname(__DIR__) . "/storage/client_docs/{$client->id}/Purchases/{$purchase->file_num}/Receipts/";
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        
        $filename = "Receipt_{$receipt->receipt_number}.pdf";
        $filepath = $output_dir . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return [
            'path' => $filepath,
            'filename' => $filename,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Generate Completion Certificate PDF
     */
    public function generateCompletionCertificate($purchase_id) {
        // Get purchase data
        $purchase = $this->db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$purchase) {
            throw new Exception("Purchase not found");
        }
        
        $client = $this->db->where('id', $purchase->client_id)->getOne('crm_customers');
        $plot = $this->db->where('id', $purchase->booking_id)->getOne('wo_booking');
        
        // Create PDF (Landscape for certificate)
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Civic Group BD');
        $pdf->SetTitle('Completion Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        
        // Add border
        $pdf->Rect(10, 10, 277, 190, 'D');
        $pdf->Rect(12, 12, 273, 186, 'D');
        
        // Title
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetY(40);
        $pdf->Cell(0, 15, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 14);
        $pdf->Ln(10);
        
        // Certificate text
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(0, 8, 'This is to certify that', 0, 'C');
        
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->MultiCell(0, 10, strtoupper($client->name), 0, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(0, 8, 'has successfully completed all payment obligations for', 0, 'C');
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->MultiCell(0, 10, "Plot: {$plot->plot}, Block: {$plot->block}, {$plot->project}", 0, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(0, 8, 'File Number: ' . $purchase->file_num, 0, 'C');
        
        $pdf->Ln(10);
        $pdf->MultiCell(0, 8, 'Completion Date: ' . date('d F Y'), 0, 'C');
        
        // Signatures
        $pdf->SetY(160);
        $pdf->Cell(143, 5, '________________________', 0, 0, 'C');
        $pdf->Cell(140, 5, '________________________', 0, 1, 'C');
        $pdf->Cell(143, 5, 'Authorized Signature', 0, 0, 'C');
        $pdf->Cell(140, 5, 'Director', 0, 1, 'C');
        
        // Save PDF
        $output_dir = dirname(__DIR__) . "/storage/client_docs/{$client->id}/Purchases/{$purchase->file_num}/Certificates/";
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }
        
        $filename = "Completion_Certificate_{$purchase->file_num}.pdf";
        $filepath = $output_dir . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return [
            'path' => $filepath,
            'filename' => $filename,
            'size' => filesize($filepath)
        ];
    }
}
