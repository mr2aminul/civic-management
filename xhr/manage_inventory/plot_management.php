<?php

/*
 * CIVIC PLOT MANAGEMENT ENDPOINTS
 * Handles plot availability, searching, and status
 * Separated from: need_seperated_by_category.php
 */

// Get available plots
function get_available_plots() {
    global $db;
    
    if ($_GET['action'] === 'get_available_plots') {
        $project = $_GET['project'] ?? '';
        $block = $_GET['block'] ?? '';
        
        try {
            $query = "SELECT w.*, 
                CASE WHEN b.id IS NOT NULL THEN 'sold' ELSE 'available' END as availability,
                b.client_id, c.name as client_name
            FROM wo_booking w
            LEFT JOIN wo_booking_helper b ON w.id = b.booking_id AND b.status = '2'
            LEFT JOIN crm_customers c ON b.client_id = c.id
            WHERE w.status IN (0, 1)";
            
            $params = [];
            
            if ($project) {
                $query .= " AND w.project = ?";
                $params[] = $project;
            }
            
            if ($block) {
                $query .= " AND w.block = ?";
                $params[] = $block;
            }
            
            $query .= " ORDER BY w.project, w.block, w.plot ASC";
            
            $plots = $db->query($query, $params);
            
            echo json_encode([
                'status' => 'success',
                'data' => $plots ?: []
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

// Check plot booking status
function check_plot_booking() {
    global $db;
    
    if ($_GET['action'] === 'check_plot_booking') {
        $plot_id = intval($_GET['plot_id'] ?? 0);
        
        if (!$plot_id) {
            echo json_encode(['status' => 'error', 'message' => 'Plot ID required']);
            exit;
        }
        
        try {
            $booking = $db->getOne("wo_booking_helper b
                LEFT JOIN crm_customers c ON b.client_id = c.id", 
                "WHERE b.booking_id = $plot_id AND b.status = '2'");
            
            echo json_encode([
                'status' => 'success',
                'is_booked' => !empty($booking),
                'booking_info' => $booking ?: null
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

// Get available purchases for client
function get_available_purchases() {
    global $db;
    
    if ($_GET['action'] === 'get_available_purchases') {
        $client_id = intval($_GET['client_id'] ?? 0);
        
        if (!$client_id) {
            echo json_encode(['status' => 'error', 'message' => 'Client ID required']);
            exit;
        }
        
        try {
            $purchases = $db->query("SELECT b.*, w.project, w.block, w.plot, w.katha
            FROM wo_booking_helper b
            LEFT JOIN wo_booking w ON b.booking_id = w.id
            WHERE b.client_id = $client_id AND b.status = '2'
            ORDER BY b.time DESC");
            
            echo json_encode([
                'status' => 'success',
                'data' => $purchases ?: []
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

// Search purchases
function search_purchases() {
    global $db;
    
    if ($_GET['action'] === 'search_purchases') {
        $search_term = $_GET['search_term'] ?? '';
        
        if (strlen($search_term) < 2) {
            echo json_encode(['status' => 'error', 'message' => 'Search term too short']);
            exit;
        }
        
        try {
            $term = '%' . $search_term . '%';
            
            $results = $db->query("SELECT DISTINCT
                b.id as purchase_id,
                c.name, c.phone, c.email,
                w.project, w.block, w.plot,
                b.status
            FROM wo_booking_helper b
            LEFT JOIN crm_customers c ON b.client_id = c.id
            LEFT JOIN wo_booking w ON b.booking_id = w.id
            WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR w.plot LIKE ?
            LIMIT 50", [$term, $term, $term, $term]);
            
            echo json_encode([
                'status' => 'success',
                'data' => $results ?: []
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


?>
