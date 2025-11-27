<?php
/**
 * Load Installment Excel Modal
 * Returns the HTML of the modal for preview
 */

if ($s === 'load_installment_modal' || $s === 'installment_excel_modal') {
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    
    if (!$purchase_id) {
        echo '<div class="alert alert-danger">Purchase ID required</div>';
        exit;
    }

    try {
        // Clean output buffer to prevent any stray output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffer
        ob_start();
        
        // Set the purchase data in $wo global for the modal to use
        $wo['modal_purchase'] = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$wo['modal_purchase']) {
            ob_end_clean();
            echo '<div class="alert alert-danger">Purchase not found</div>';
            exit;
        }
        
        $wo['modal_booking'] = $db->where('id', $wo['modal_purchase']->booking_id)->getOne('wo_booking');
        $wo['modal_client'] = $db->where('id', $wo['modal_purchase']->client_id)->getOne(T_CUSTOMERS);
        
        // Include and output the modal HTML
        echo Wo_LoadManagePage('clients/modals/installment_excel_modal');
        
        // Get clean output
        $html = ob_get_clean();
        
        // Output only the clean HTML
        echo $html;
        exit;
        
    } catch (Exception $e) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo '<div class="alert alert-danger">Error loading modal: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
}
