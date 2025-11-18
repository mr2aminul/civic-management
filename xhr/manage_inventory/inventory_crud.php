<?php

/*
 * CIVIC INVENTORY CRUD ENDPOINTS
 * Handles basic inventory operations
 * Separated from: need_seperated_by_category.php
 */

// Create/Submit new inventory item
function submit() {
    global $db, $wo;
    
    if ($_POST['action'] === 'submit') {
        $project = $_POST['project'] ?? '';
        $block = $_POST['block'] ?? '';
        $plot = $_POST['plot'] ?? '';
        $katha = floatval($_POST['katha'] ?? 0);
        $facing = $_POST['facing'] ?? '';
        $file_num = $_POST['file_num'] ?? '';
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$project || !$block || !$plot) {
            echo json_encode(['status' => 'error', 'message' => 'Project, block, and plot required']);
            exit;
        }
        
        try {
            $booking_id = $db->insert("wo_booking", [
                'project' => $project,
                'block' => $block,
                'plot' => $plot,
                'katha' => $katha,
                'facing' => $facing,
                'file_num' => $file_num,
                'status' => 0,  // Available
                'object_id' => 0
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Inventory item created',
                'booking_id' => $booking_id
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

// Edit inventory item
function edit_inventory() {
    global $db, $wo;
    
    if ($_POST['action'] === 'edit_inventory') {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$booking_id) {
            echo json_encode(['status' => 'error', 'message' => 'Booking ID required']);
            exit;
        }
        
        try {
            $update_data = [];
            
            if (isset($_POST['plot'])) $update_data['plot'] = $_POST['plot'];
            if (isset($_POST['katha'])) $update_data['katha'] = floatval($_POST['katha']);
            if (isset($_POST['facing'])) $update_data['facing'] = $_POST['facing'];
            if (isset($_POST['file_num'])) $update_data['file_num'] = $_POST['file_num'];
            
            $db->update("wo_booking", $update_data, "WHERE id = $booking_id");
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Inventory item updated'
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

// Update inventory status
function update_status() {
    global $db, $wo;
    
    if ($_POST['action'] === 'update_status') {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $user_id = $_POST['user_id'] ?? $wo['user']->id;
        
        if (!$booking_id || $status < 0 || $status > 4) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid booking or status']);
            exit;
        }
        
        try {
            $db->update("wo_booking", ['status' => $status], "WHERE id = $booking_id");
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Status updated'
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

// Fetch inventory items
function fetch() {
    global $db;
    
    if ($_GET['action'] === 'fetch') {
        $project = $_GET['project'] ?? '';
        $block = $_GET['block'] ?? '';
        $status = $_GET['status'] ?? '';
        
        try {
            $query = "SELECT * FROM wo_booking WHERE 1=1";
            $params = [];
            
            if ($project) {
                $query .= " AND project = ?";
                $params[] = $project;
            }
            
            if ($block) {
                $query .= " AND block = ?";
                $params[] = $block;
            }
            
            if ($status !== '') {
                $query .= " AND status = ?";
                $params[] = intval($status);
            }
            
            $query .= " ORDER BY project, block, plot ASC";
            
            $items = $db->query($query, $params);
            
            echo json_encode([
                'status' => 'success',
                'data' => $items ?: []
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
submit();
edit_inventory();
update_status();
fetch();

?>
