<?php
    // Transfer purchase to another client
    if ($s === 'transfer_purchase') {
        header('Content-Type: application/json; charset=utf-8');
        
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $target_client_id = isset($_POST['target_client_id']) ? (int)$_POST['target_client_id'] : 0;
        $transfer_date = isset($_POST['transfer_date']) ? trim($_POST['transfer_date']) : '';
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        
        if ($purchase_id <= 0 || $target_client_id <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
            exit;
        }
        
        if (empty($reason)) {
            echo json_encode(['status' => 400, 'message' => 'Transfer reason is required']);
            exit;
        }
        
        try {
            // Verify source purchase exists
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
                exit;
            }
            
            // Verify target client exists
            $target_client = $db->where('id', $target_client_id)->getOne(T_CUSTOMERS);
            if (!$target_client) {
                echo json_encode(['status' => 404, 'message' => 'Target client not found']);
                exit;
            }
            
            $transfer_timestamp = !empty($transfer_date) ? strtotime($transfer_date) : time();
            
            $db->startTransaction();
            
            // Update the helper with new client
            $update_result = $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
                'client_id' => (string)$target_client_id,
                'time' => $transfer_timestamp
            ]);
            
            if ($update_result === false) {
                $db->rollback();
                echo json_encode(['status' => 500, 'message' => 'Failed to transfer purchase']);
                exit;
            }
            
            // Log the transfer
            $old_client = GetCustomerById($helper->client_id);
            $old_client_name = $old_client['name'] ?? 'Unknown';
            $new_client_name = $target_client->name ?? 'Unknown';
            
            logActivity('purchase', 'transfer', "Purchase #{$purchase_id} transferred from {$old_client_name} to {$new_client_name}. Reason: {$reason}");
            
            $db->commit();
            
            echo json_encode([
                'status' => 200, 
                'message' => 'Purchase transferred successfully',
                'transfer_id' => $purchase_id
            ]);
            
        } catch (Exception $e) {
            if (isset($db) && method_exists($db, 'rollback')) {
                $db->rollback();
            }
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Get transfer data for modal population
    if ($s === 'get_transfer_data') {
        header('Content-Type: application/json; charset=utf-8');
        
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        
        if ($purchase_id <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
            exit;
        }
        
        try {
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
                exit;
            }
            
            $client = GetCustomerById($helper->client_id);
            $booking = $db->where('id', $helper->booking_id)->getOne(T_BOOKING);
            
            // Get all available clients
            $clients = $db->orderBy('name', 'ASC')->get(T_CUSTOMERS, null, ['id', 'name', 'phone']);
            $available_clients = [];
            if (!empty($clients)) {
                foreach ($clients as $c) {
                    $available_clients[] = [
                        'id' => $c->id,
                        'name' => $c->name,
                        'phone' => $c->phone ?? ''
                    ];
                }
            }
            
            // Get all available plots
            $available_plots = $db->where('status', '0')->orWhere('status', '1')->get(T_BOOKING);
            $plots = [];
            if (!empty($available_plots)) {
                foreach ($available_plots as $p) {
                    $plots[] = [
                        'id' => $p->id,
                        'plot' => $p->plot,
                        'block' => $p->block,
                        'katha' => $p->katha,
                        'road' => $p->road,
                        'per_katha' => $helper->per_katha ?? 0
                    ];
                }
            }
            
            // Get total paid from payment schedule
            $paid_schedule = $db->where('purchase_id', $purchase_id)->where('status', 1)->get('crm_payment_schedule');
            $total_paid = 0;
            if (!empty($paid_schedule)) {
                foreach ($paid_schedule as $ps) {
                    $total_paid += floatval($ps->paid_amount ?? 0);
                }
            }
            
            $project_id = null;
            if (!empty($project_mapping) && !empty($booking->project) && isset($project_mapping[$booking->project])) {
                $project_id = $project_mapping[$booking->project];
            }
            echo json_encode([
                'status' => 200,
                'client_id' => $client['id'] ?? '0',
                'current_name' => $client['name'] ?? 'Unknown',
                'current_block' => $booking->block ? ucwords($booking->block) : '',
                'current_plot' => $booking->plot ?? '',
                'current_katha' => $booking->katha ?? '',
                'current_per_katha' => $helper->per_katha ?? 0,
                'total_paid' => $total_paid,
                'available_clients' => $available_clients,
                'available_plots' => $plots,
                'project_id' => $project_id,
                'project_slug' => $booking->project,
                'project_name' => ucwords(str_replace("-" , " ", $booking->project))
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Process name transfer
    if ($s === 'process_name_transfer') {
        header('Content-Type: application/json; charset=utf-8');
        
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        $fee_mode = isset($_POST['fee_mode']) ? trim($_POST['fee_mode']) : 'none';
        $fee_value = isset($_POST['fee_value']) ? floatval($_POST['fee_value']) : 0;
        
        if ($purchase_id <= 0 || !$client_id) {
            echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
            exit;
        }
        
        try {
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
                exit;
            }
            
            $db->startTransaction();
            
            // Get total paid from payment schedule
            $paid_schedule = $db->where('purchase_id', $purchase_id)->where('status', 1)->get('crm_payment_schedule');
            $total_paid = 0;
            if (!empty($paid_schedule)) {
                foreach ($paid_schedule as $ps) {
                    $total_paid += floatval($ps->paid_amount ?? 0);
                }
            }
            
            $transfer_fee = 0;
            if ($fee_mode === 'fixed') {
                $transfer_fee = $fee_value;
            } elseif ($fee_mode === 'percent') {
                // Use total paid amount as base for percentage calculation
                $transfer_fee = ($total_paid * $fee_value) / 100;
            }
            
            $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
                'client_id' => (string)$client_id,
                'time' => time(),
                'updated_at' => time()
            ]);
            
            $db->where('purchase_id', $purchase_id)->update('crm_payment_schedule', [
                'remarks' => 'Name transferred to client ID: ' . $client_id . ' | ' . $reason,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log the transfer with fee info
            $logMsg = "Purchase #{$purchase_id} name transferred to client {$client_id}. Reason: {$reason}";
            if ($transfer_fee > 0) {
                $logMsg .= " Fee: {$fee_mode} ({$fee_value}) = ৳" . number_format($transfer_fee, 2);
            }
            logActivity('purchase', 'name_transfer', $logMsg);
            
            $db->commit();
            
            echo json_encode([
                'status' => 200,
                'message' => 'Name transfer processed successfully',
                'transfer_fee' => round($transfer_fee, 2),
                'new_client_id' => $client_id
            ]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($s === 'process_plot_transfer') {
        header('Content-Type: application/json; charset=utf-8');
        
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $new_plot_id = isset($_POST['new_plot_id']) ? (int)$_POST['new_plot_id'] : 0;
        $new_per_katha = isset($_POST['new_per_katha']) ? floatval($_POST['new_per_katha']) : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        $fee_mode = isset($_POST['fee_mode']) ? trim($_POST['fee_mode']) : 'none';
        $fee_value = isset($_POST['fee_value']) ? floatval($_POST['fee_value']) : 0;
        
        if ($purchase_id <= 0 || $new_plot_id <= 0 || $new_per_katha <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invalid parameters']);
            exit;
        }
        
        try {
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
                exit;
            }
            
            $new_booking = $db->where('id', $new_plot_id)->getOne(T_BOOKING);
            if (!$new_booking) {
                echo json_encode(['status' => 404, 'message' => 'New plot not found']);
                exit;
            }
            
            $db->startTransaction();
            
            // Get total paid
            $paid_schedule = $db->where('purchase_id', $purchase_id)->where('status', 1)->get('crm_payment_schedule');
            $total_paid = 0;
            if (!empty($paid_schedule)) {
                foreach ($paid_schedule as $ps) {
                    $total_paid += floatval($ps->paid_amount ?? 0);
                }
            }
            
            // Calculate old and new totals
            $old_booking = $db->where('id', $helper->booking_id)->getOne(T_BOOKING);
            $old_katha = floatval($old_booking->katha ?? 0);
            $old_per_katha = floatval($helper->per_katha ?? 0);
            $old_total = $old_katha * $old_per_katha;
            
            $new_katha = floatval($new_booking->katha ?? 0);
            $new_total = $new_katha * $new_per_katha;
            
            $transfer_fee = 0;
            if ($fee_mode === 'fixed') {
                $transfer_fee = $fee_value;
            } elseif ($fee_mode === 'percent') {
                // Use new plot total as base for percentage calculation
                $transfer_fee = ($new_total * $fee_value) / 100;
            }
            
            $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
                'booking_id' => $new_plot_id,
                'per_katha' => $new_per_katha,
                'time' => time(),
                'updated_at' => time()
            ]);
            
            $db->where('purchase_id', $purchase_id)->update('crm_payment_schedule', [
                'remarks' => 'Plot transferred from plot ID: ' . $helper->booking_id . ' to plot ID: ' . $new_plot_id . ' | Old rate: ' . $old_per_katha . ' | New rate: ' . $new_per_katha . ' | ' . $reason,
                'status' => 99, // archived/transferred status
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log the plot transfer
            $logMsg = "Purchase #{$purchase_id} plot transferred from booking {$helper->booking_id} to {$new_plot_id}. Old price: ৳" . number_format($old_total, 2) . ", New price: ৳" . number_format($new_total, 2) . ". Reason: {$reason}";
            if ($transfer_fee > 0) {
                $logMsg .= " Fee: {$fee_mode} ({$fee_value}) = ৳" . number_format($transfer_fee, 2);
            }
            logActivity('purchase', 'plot_transfer', $logMsg);
            
            $db->commit();
            
            echo json_encode([
                'status' => 200,
                'message' => 'Plot transfer processed successfully. Schedule will be recalculated.',
                'old_total' => round($old_total, 2),
                'new_total' => round($new_total, 2),
                'transfer_fee' => round($transfer_fee, 2)
            ]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Initiate name or plot transfer
    if ($s === 'initiate_transfer') {
        header('Content-Type: application/json; charset=utf-8');
        
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $transfer_type = isset($_POST['transfer_type']) ? trim($_POST['transfer_type']) : '';
        $to_client_id = isset($_POST['to_client_id']) ? (int)$_POST['to_client_id'] : 0;
        $new_purchase_id = isset($_POST['new_purchase_id']) ? (int)$_POST['new_purchase_id'] : 0;
        $new_rate = isset($_POST['new_rate']) ? (float)$_POST['new_rate'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        if (!$purchase_id || !$transfer_type || !$reason) {
            echo json_encode([
                'status' => 400,
                'message' => 'purchase_id, transfer_type, and reason are required'
            ]);
            exit;
        }

        try {
            $result = [];

            if ($transfer_type === 'name_transfer') {
                if (!$to_client_id) {
                    echo json_encode(['status' => 400, 'message' => 'to_client_id required for name transfer']);
                    exit;
                }

                $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
                if (!$helper) {
                    echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                    exit;
                }

                $result = initiate_name_transfer(
                    $purchase_id,
                    (int)$helper->client_id,
                    $to_client_id,
                    ['reason' => $reason],
                    $wo['user']['id'] ?? null
                );
            } elseif ($transfer_type === 'plot_transfer') {
                if (!$new_purchase_id) {
                    echo json_encode(['status' => 400, 'message' => 'new_purchase_id required for plot transfer']);
                    exit;
                }

                $result = initiate_plot_transfer(
                    $purchase_id,
                    $new_purchase_id,
                    $new_rate ?: null,
                    $reason,
                    [],
                    $wo['user']['id'] ?? null
                );
            } else {
                echo json_encode(['status' => 400, 'message' => 'Invalid transfer_type']);
                exit;
            }

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Get transfer history for a booking
    if ($s === 'get_transfer_history') {
        header('Content-Type: application/json; charset=utf-8');
        
        $purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : (isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0);
        $transfer_type = isset($_GET['type']) ? trim($_GET['type']) : '';

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $filters = [];
            if ($transfer_type) {
                $filters['transfer_type'] = $transfer_type;
            }

            $history = get_transfer_history($purchase_id, $filters);

            echo json_encode([
                'status' => 200,
                'transfers' => $history ?: [],
                'count' => count($history ?: [])
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Get pending transfers
    if ($s === 'get_pending_transfers') {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $pending = get_pending_transfers(['approval_status' => 0]);

            echo json_encode([
                'status' => 200,
                'transfers' => $pending ?: [],
                'count' => count($pending ?: [])
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Admin approves a transfer with email notification
    if ($s === 'approve_transfer') {
        header('Content-Type: application/json; charset=utf-8');
        
        $transfer_id = isset($_POST['transfer_id']) ? (int)$_POST['transfer_id'] : 0;

        if (!$transfer_id) {
            echo json_encode(['status' => 400, 'message' => 'transfer_id is required']);
            exit;
        }

        try {
            $transfer = $db->where('id', $transfer_id)->getOne('crm_transfer_history');
            if (!$transfer) {
                echo json_encode(['status' => 404, 'message' => 'Transfer not found']);
                exit;
            }

            $result = [];
            if ($transfer->transfer_type === 'name_transfer') {
                $result = approve_name_transfer($transfer_id, $wo['user']['id'] ?? null);
            } else {
                $result = approve_plot_transfer($transfer_id, $wo['user']['id'] ?? null);
            }

            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Admin rejects a transfer
    if ($s === 'reject_transfer') {
        header('Content-Type: application/json; charset=utf-8');
        
        $transfer_id = isset($_POST['transfer_id']) ? (int)$_POST['transfer_id'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'No reason provided';

        if (!$transfer_id) {
            echo json_encode(['status' => 400, 'message' => 'transfer_id is required']);
            exit;
        }

        try {
            $result = reject_transfer($transfer_id, $reason, $wo['user']['id'] ?? null);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }
