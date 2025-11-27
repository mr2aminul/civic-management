<?php
/**
 * Download Schedule Endpoint
 * Generates and directly downloads Excel schedule file
 */

if ($s === 'download_schedule') {
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    
    if (!$purchase_id) {
        http_response_code(400);
        die('Purchase ID required');
    }

    try {
        global $db, $wo;
        
        // Set the purchase data in $wo for the modal to use
        $wo['modal_purchase'] = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$wo['modal_purchase']) {
            http_response_code(404);
            die('Purchase not found');
        }
        
        $wo['modal_booking'] = $db->where('id', $wo['modal_purchase']->booking_id)->getOne('wo_booking');
        $wo['modal_client'] = $db->where('id', $wo['modal_purchase']->client_id)->getOne(T_CUSTOMERS);
        
        // Set a flag to tell the modal to download instead of showing HTML
        $wo['download_mode'] = true;
        
        // Load the modal which will generate the Excel file
        // Suppress output
        ob_start();
        include 'manage/pages/clients/modals/installment_excel_modal.phtml';
        ob_end_clean();
        
        // The file should now exist, stream it for download
        $theme = $wo['config']['theme'] ?? 'default';
        $helper = $wo['modal_purchase'];
        $booking = $wo['modal_booking'];
        $client = $wo['modal_client'];
        
        $client_name_safe = isset($client->name) ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $client->name) : 'client';
        $project_safe = isset($booking->project) ? preg_replace('/[^A-Za-z0-9]/', '_', $booking->project) : 'project';
        $filename = "installment_{$client_name_safe}_" . intval($helper->id) . "_{$project_safe}.xlsx";
        $file_path = "./themes/{$theme}/{$filename}";
        
        if (!file_exists($file_path)) {
            http_response_code(500);
            die('Excel file generation failed');
        }
        
        // Stream the file for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: max-age=0');
        
        readfile($file_path);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        die('Error generating schedule: ' . $e->getMessage());
    }
}

