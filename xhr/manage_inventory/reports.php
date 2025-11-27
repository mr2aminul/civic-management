<?php
/**
 * Reports & Export Module
 * Handles report generation and data export functionality
 */

header('Content-Type: application/json; charset=utf-8');

// Generate Purchase Report (PDF/Excel comprehensive report)
if ($s === 'generate_purchase_report') {
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    $format = isset($_POST['format']) ? $_POST['format'] : 'pdf'; // pdf, excel, or both
    
    if (!$purchase_id) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
        exit;
    }

    try {
        // Fetch purchase details
        $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        $booking = $db->where('id', $helper->booking_id)->getOne('wo_booking');
        $client = $db->where('id', $helper->client_id)->getOne('crm_customers');
        
        // Parse schedule
        $schedule = json_decode($helper->installment, true) ?: [];
        
        // Get invoices
        $db->where('purchase_id', $purchase_id);
        $invoices = $db->get('crm_invoices');
        
        // Get receipts
        $db->where('purchase_id', $purchase_id);
        $receipts = $db->get('crm_money_receipts');
        
        // Calculate totals
        $total_amount = 0;
        $total_paid = 0;
        foreach ($schedule as $item) {
            $total_amount += floatval($item['installment_amount'] ?? 0);
            $total_paid += floatval($item['paid_amount'] ?? 0);
        }
        
        $report_data = [
            'purchase_id' => $purchase_id,
            'client_name' => $client->name ?? 'Unknown',
            'client_email' => $client->email ?? '',
            'client_phone' => $client->phone ?? '',
            'project' => $booking->project ?? '',
            'plot' => $booking->plot ?? '',
            'block' => $booking->block ?? '',
            'katha' => $booking->katha ?? 0,
            'file_num' => $helper->file_num ?? '',
            'total_amount' => $total_amount,
            'total_paid' => $total_paid,
            'total_due' => $total_amount - $total_paid,
            'schedule' => $schedule,
            'invoices' => $invoices,
            'receipts' => $receipts,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // In production, generate actual PDF/Excel here
        $filename = 'purchase_report_' . $purchase_id . '_' . date('Ymd_His');
        
        echo json_encode([
            'status' => 200,
            'message' => 'Report generated successfully',
            'format' => $format,
            'filename' => $filename . '.' . $format,
            'download_url' => '/downloads/reports/' . $filename . '.' . $format,
            'data' => $report_data // For preview
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Export Purchase Data (CSV/Excel for data analysis)
if ($s === 'export_purchase_data') {
    $format = isset($_POST['format']) ? $_POST['format'] : 'excel'; // excel, csv, json
    $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
    
    try {
        // Build query based on filters
        $db->orderBy('id', 'DESC');
        
        if (!empty($filters['client_id'])) {
            $db->where('client_id', intval($filters['client_id']));
        }
        
        if (!empty($filters['status'])) {
            $db->where('status', $filters['status']);
        }
        
        if (!empty($filters['project'])) {
            $db->join('wo_booking b', 'b.id = wo_booking_helper.booking_id', 'LEFT');
            $db->where('b.project', $filters['project']);
        }
        
        $purchases = $db->get('wo_booking_helper');
        
        if (empty($purchases)) {
            echo json_encode(['status' => 404, 'message' => 'No purchases found']);
            exit;
        }
        
        // Enrich data
        $export_data = [];
        foreach ($purchases as $p) {
            $booking = $db->where('id', $p->booking_id)->getOne('wo_booking');
            $client = $db->where('id', $p->client_id)->getOne('crm_customers');
            $schedule = json_decode($p->installment, true) ?: [];
            
            $total_amount = 0;
            $total_paid = 0;
            foreach ($schedule as $item) {
                $total_amount += floatval($item['installment_amount'] ?? 0);
                $total_paid += floatval($item['paid_amount'] ?? 0);
            }
            
            $export_data[] = [
                'Purchase ID' => $p->id,
                'File Number' => $p->file_num ?? '',
                'Client Name' => $client->name ?? '',
                'Client Email' => $client->email ?? '',
                'Client Phone' => $client->phone ?? '',
                'Project' => $booking->project ?? '',
                'Block' => $booking->block ?? '',
                'Plot' => $booking->plot ?? '',
                'Road' => $booking->road ?? '',
                'Katha' => $booking->katha ?? 0,
                'Per Katha Rate' => $p->per_katha ?? 0,
                'Total Amount' => $total_amount,
                'Total Paid' => $total_paid,
                'Total Due' => $total_amount - $total_paid,
                'Status' => $p->status ?? '',
                'Purchase Date' => date('Y-m-d', $p->time ?? time()),
                'Installments Count' => count($schedule)
            ];
        }
        
        $filename = 'purchase_export_' . date('Ymd_His');
        
        // In production, generate actual file here
        echo json_encode([
            'status' => 200,
            'message' => count($export_data) . ' purchases exported',
            'format' => $format,
            'filename' => $filename . '.' . ($format === 'excel' ? 'xlsx' : $format),
            'download_url' => '/downloads/exports/' . $filename . '.' . ($format === 'excel' ? 'xlsx' : $format),
            'count' => count($export_data),
            'data' => $export_data // For preview
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}


// Get All Clients (for dropdowns and selects)
if ($s === 'get_all_clients') {
    $search = isset($_GET['search']) ? Wo_Secure($_GET['search']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    try {
        if ($search) {
            $db->where("(name LIKE ? OR email LIKE ? OR phone LIKE ?)", 
                      ["%$search%", "%$search%", "%$search%"], 'OR');
        }
        
        $db->orderBy('name', 'ASC');
        $clients = $db->get('crm_customers', $limit, ['id', 'name', 'email', 'phone', 'address']);
        
        $formatted = [];
        foreach ($clients as $client) {
            $formatted[] = [
                'id' => $client->id,
                'text' => $client->name . ' (' . ($client->phone ?? $client->email) . ')',
                'name' => $client->name,
                'email' => $client->email ?? '',
                'phone' => $client->phone ?? '',
                'address' => $client->address ?? ''
            ];
        }
        
        echo json_encode([
            'status' => 200,
            'clients' => $formatted,
            'count' => count($formatted)
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
