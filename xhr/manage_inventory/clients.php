<?php

/*
 * CIVIC CLIENT MANAGEMENT ENDPOINTS
 * Handles all client-related inventory operations
 * Separated from: need_seperated_by_category.php
 */


// Get all clients with their purchase details
function get_all_clients() {
    global $db, $sqlConnect;
    
    if ($_GET['action'] === 'get_all_clients') {
        try {
            $result = $db->query("SELECT 
                c.id,
                c.name,
                c.phone,
                c.email,
                c.address,
                COUNT(DISTINCT b.id) as total_purchases,
                SUM(CASE WHEN b.status = '2' THEN 1 ELSE 0 END) as active_purchases,
                SUM(CASE WHEN b.status = '4' THEN 1 ELSE 0 END) as cancelled_purchases,
                SUM(CASE WHEN b.status = '3' THEN 1 ELSE 0 END) as completed_purchases,
                MAX(b.time) as last_purchase_date
            FROM crm_customers c
            LEFT JOIN wo_booking_helper b ON c.id = b.client_id
            GROUP BY c.id
            ORDER BY c.time DESC");
            
            echo json_encode([
                'status' => 'success',
                'data' => $result ?: []
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Get a single client with all purchase details
function get_client_details() {
    global $db, $sqlConnect;
    
    if ($_GET['action'] === 'get_client_details') {
        $client_id = intval($_GET['client_id'] ?? 0);
        
        if (!$client_id) {
            echo json_encode(['status' => 'error', 'message' => 'Client ID required']);
            exit;
        }
        
        try {
            // Get client info
            $client = $db->getOne("crm_customers", "WHERE id = $client_id");
            
            // Get nominees
            $nominees = $db->query("SELECT * FROM crm_nominees WHERE customer_id = $client_id");
            
            // Get purchases
            $purchases = $db->query("SELECT 
                b.id,
                b.booking_id,
                b.file_num,
                b.status,
                b.per_katha,
                b.booking_money,
                b.down_payment,
                w.project,
                w.block,
                w.plot,
                w.katha,
                COUNT(DISTINCT ps.id) as total_installments,
                SUM(CASE WHEN ps.status = 1 THEN ps.installment_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN ps.status = 0 THEN ps.installment_amount ELSE 0 END) as pending_amount
            FROM wo_booking_helper b
            LEFT JOIN wo_booking w ON b.booking_id = w.id
            LEFT JOIN crm_payment_schedule ps ON b.id = ps.purchase_id
            WHERE b.client_id = $client_id
            GROUP BY b.id");
            
            echo json_encode([
                'status' => 'success',
                'client' => $client,
                'nominees' => $nominees ?: [],
                'purchases' => $purchases ?: []
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Get purchase details with full payment schedule
function get_purchase_by_id() {
    global $db, $sqlConnect;
    
    if ($_GET['action'] === 'get_purchase_by_id') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            // Get purchase with plot details
            $purchase = $db->getOne("wo_booking_helper b
                LEFT JOIN wo_booking w ON b.booking_id = w.id
                LEFT JOIN crm_customers c ON b.client_id = c.id", 
                "WHERE b.id = $purchase_id");
            
            // Get payment schedule
            $schedule = $db->query("SELECT * FROM crm_payment_schedule 
                WHERE purchase_id = $purchase_id 
                ORDER BY installment_number ASC, type ASC");
            
            // Get any pending changes
            $pending_changes = $db->query("SELECT * FROM crm_pending_changes 
                WHERE purchase_id = $purchase_id AND status = 0");
            
            echo json_encode([
                'status' => 'success',
                'purchase' => $purchase,
                'schedule' => $schedule ?: [],
                'pending_changes' => $pending_changes ?: []
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Call functions
get_all_clients();
get_client_details();
get_purchase_by_id();

?>
