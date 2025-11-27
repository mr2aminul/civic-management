<?php
/**
 * Pending Changes Manager
 * Aggregates pending actions from various modules (Merges, Transfers, Reschedules)
 * Provides unified endpoints for listing and approving/denying changes.
 */

header('Content-Type: application/json; charset=utf-8');

if ($s == 'get_pending_changes') {
    try {
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        $changes = [];

        // 1. Get Pending Merges
        if ($purchase_id) $db->where('source_purchase_id', $purchase_id);
        $db->where('approval_status', 'pending');
        $merges = $db->get('crm_merge_requests');
        if ($db->count > 0) {
            foreach ($merges as $m) {
                // Fetch client name
                $client = $db->where('id', $m->client_id)->getOne(T_CUSTOMERS, ['name']);
                
                $changes[] = [
                    'id' => $m->id,
                    'change_type' => 'merge', // mapped for frontend
                    'purchase_id' => $m->source_purchase_id, // Primary ref
                    'client_name' => $client->name ?? 'Unknown',
                    'requested_by_name' => 'System', // or fetch user if stored
                    'request_date' => $m->created_at,
                    'request_reason' => $m->merge_reason ?? 'Merge Request',
                    'status' => 'pending',
                    'preview_data_json' => json_encode([
                        'target_purchase_id' => $m->target_purchase_id
                    ])
                ];
            }
        }

        // 3. Get Pending Reschedules (crm_pending_changes)
        if ($db->tableExists('crm_pending_changes')) {
            if ($purchase_id) $db->where('purchase_id', $purchase_id);
            $db->where('status', 'pending');
            $reschedules = $db->get('crm_pending_changes');
            if ($db->count > 0) {
                foreach ($reschedules as $r) {
                    // Fetch client name via purchase -> booking -> client? 
                    // Assuming purchase_id is linked to booking_helper
                    $client_name = 'Unknown';
                    $helper = $db->where('id', $r->purchase_id)->getOne(T_BOOKING_HELPER, ['client_id']);
                    if ($helper) {
                        $c = $db->where('id', $helper->client_id)->getOne(T_CUSTOMERS, ['name']);
                        if ($c) $client_name = $c->name;
                    }

                    $changes[] = [
                        'id' => $r->id,
                        'change_type' => $r->change_type ?? 'reschedule',
                        'purchase_id' => $r->purchase_id,
                        'client_name' => $client_name,
                        'requested_by_name' => 'System',
                        'request_date' => $r->request_date ?? $r->created_at,
                        'request_reason' => $r->request_reason ?? 'Reschedule Request',
                        'status' => $r->status ?? 'pending',
                        'preview_data_json' => $r->change_data_json ?? '' // Assuming this stores the before/after
                    ];
                }
            }
        }

        echo json_encode(['status' => 200, 'changes' => $changes]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error fetching pending changes: ' . $e->getMessage()]);
        exit;
    }
}

if ($s == 'submit_pending_change') {
    try {
        $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $change_type = isset($_POST['change_type']) ? Wo_Secure($_POST['change_type']) : '';
        $change_data = isset($_POST['change_data']) ? $_POST['change_data'] : '';
        $request_reason = isset($_POST['request_reason']) ? Wo_Secure($_POST['request_reason']) : '';

        if (!$purchase_id || !$change_type) {
            echo json_encode(['status' => 400, 'message' => 'Purchase ID and change type required']);
            exit;
        }

        // Get client_id from purchase
        $purchase = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER, ['client_id']);
        if (!$purchase) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        $client_id = intval($purchase->client_id);
        $requested_by = $wo['user_id'] ?? 0;

        // Insert into crm_pending_changes
        $data = [
            'purchase_id' => $purchase_id,
            'client_id' => $client_id,
            'change_type' => $change_type,
            'requested_by' => $requested_by,
            'change_data_json' => $change_data,
            'request_reason' => $request_reason,
            'status' => 'pending',
            'request_date' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $db->insert('crm_pending_changes', $data);

        if ($id) {
            // Update has_pending_changes flag on purchase
            $db->where('id', $purchase_id);
            $db->update(T_BOOKING_HELPER, ['has_pending_changes' => 1]);
            
            echo json_encode(['status' => 200, 'message' => 'Change request submitted', 'request_id' => $id]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Failed to submit request']);
        }
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s == 'approve_pending_change') {
    $id = isset($_POST['pending_change_id']) ? intval($_POST['pending_change_id']) : 0;
    $type = isset($_POST['change_type']) ? Wo_Secure($_POST['change_type']) : '';
    $notes = isset($_POST['notes']) ? Wo_Secure($_POST['notes']) : '';

    if (!$id || !$type) {
        echo json_encode(['status' => 400, 'message' => 'ID and Type required']);
        exit;
    }

    try {
        if ($type == 'merge') {
            // Fetch the merge request first
            $db->where('id', $id);
            $merge = $db->getOne('crm_merge_requests');
            
            if (!$merge) {
                echo json_encode(['status' => 404, 'message' => 'Merge request not found']);
                exit;
            }

            // Update status
            $db->where('id', $id);
            $update = $db->update('crm_merge_requests', [
                'approval_status' => 'approved',
                'approved_by' => $wo['user_id'] ?? 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'review_notes' => $notes
            ]);
            
            if ($update) {
                // --- EXECUTE MERGE LOGIC ---
                
                // 1. Transfer Invoices, Receipts, Credits
                if ($merge->merge_credits == 1) {
                    $db->where('purchase_id', $merge->source_purchase_id);
                    $db->update('crm_invoices', ['purchase_id' => $merge->target_purchase_id]);

                    $db->where('purchase_id', $merge->source_purchase_id);
                    $db->update('crm_money_receipts', ['purchase_id' => $merge->target_purchase_id]);

                    $db->where('purchase_id', $merge->source_purchase_id);
                    $db->update('crm_overpayment_credits', ['purchase_id' => $merge->target_purchase_id]);
                }

                // 2. Consolidate Payment Schedule
                if ($merge->merge_payment_schedule == 1) {
                    $db->where('purchase_id', $merge->source_purchase_id);
                    $db->update('crm_payment_schedule', ['purchase_id' => $merge->target_purchase_id]);
                }

                // 3. Update Source Purchase Status (e.g. 5 = Merged/Cancelled)
                $db->where('id', $merge->source_purchase_id);
                $db->update(T_BOOKING_HELPER, ['status' => 5, 'has_pending_changes' => 0]);

                // 4. Create Transfer History Record (as requested)
                if ($db->tableExists('crm_transfer_history')) {
                    $db->insert('crm_transfer_history', [
                        'purchase_id' => $merge->source_purchase_id,
                        'client_id' => $merge->client_id,
                        'transfer_type' => 'merge',
                        'transfer_reason' => 'Merged into Purchase #' . $merge->target_purchase_id,
                        'status' => 'approved',
                        'approved_by' => $wo['user_id'] ?? 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'remarks' => $notes
                    ]);
                }

                // 5. Audit Trail
                if ($db->tableExists('crm_audit_trail')) {
                    $db->insert('crm_audit_trail', [
                        'user_id' => $wo['user_id'] ?? 0,
                        'action' => 'merge_approved',
                        'details' => json_encode([
                            'merge_id' => $id,
                            'source' => $merge->source_purchase_id,
                            'target' => $merge->target_purchase_id
                        ]),
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                echo json_encode(['status' => 200, 'message' => 'Merge request approved and executed']);
            } else {
                echo json_encode(['status' => 500, 'message' => 'Failed to update merge status']);
            }

        } elseif (in_array($type, ['transfer', 'cancel', 'name_transfer', 'plot_transfer'])) {
            if ($db->tableExists('crm_transfer_history')) {
                // Fetch the transfer record first to get purchase_id
                $db->where('id', $id);
                $transfer = $db->getOne('crm_transfer_history');
                
                if (!$transfer) {
                    echo json_encode(['status' => 404, 'message' => 'Transfer record not found']);
                    exit;
                }
                
                $db->where('id', $id);
                $update = $db->update('crm_transfer_history', [
                    'status' => 'approved',
                    'approved_by' => $wo['user_id'] ?? 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'remarks' => $notes
                ]);
                
                if ($update) {
                    // If this is a cancellation, update the booking_helper table
                    if ($type === 'cancel' && !empty($transfer->purchase_id)) {
                        $db->where('id', $transfer->purchase_id);
                        $db->update(T_BOOKING_HELPER, [
                            'status' => 4, // Cancelled
                            'cancel_date' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    // Audit Trail
                    if ($db->tableExists('crm_audit_trail')) {
                        $db->insert('crm_audit_trail', [
                            'user_id' => $wo['user_id'] ?? 0,
                            'action' => $type . '_approved',
                            'details' => json_encode(['id' => $id, 'purchase_id' => $transfer->purchase_id ?? 0]),
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    echo json_encode(['status' => 200, 'message' => ucfirst($type) . ' request approved']);
                }
                else echo json_encode(['status' => 500, 'message' => 'Failed to update transfer status']);
            }
         } elseif ($type == 'reschedule') {
             if ($db->tableExists('crm_pending_changes')) {
                $db->where('id', $id);
                $update = $db->update('crm_pending_changes', [
                    'status' => 'approved',
                    'reviewed_by' => $wo['user_id'] ?? 0,
                    'review_date' => date('Y-m-d H:i:s'),
                    'review_notes' => $notes
                ]);
                
                // Here we would also apply the new schedule to crm_payment_schedule
                // Fetch the change data
                $change = $db->where('id', $id)->getOne('crm_pending_changes');
                if ($change && !empty($change['change_data_json'])) {
                    $newData = json_decode($change['change_data_json'], true);
                    if (isset($newData['new_schedule'])) {
                        // Update the booking helper installment
                        $db->where('id', $change['purchase_id']);
                        $db->update(T_BOOKING_HELPER, ['installment' => json_encode($newData['new_schedule'])]);
                    }
                }

                if ($update) {
                    // Audit Trail
                    if ($db->tableExists('crm_audit_trail')) {
                        $db->insert('crm_audit_trail', [
                            'user_id' => $wo['user_id'] ?? 0,
                            'action' => 'reschedule_approved',
                            'details' => json_encode(['id' => $id]),
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                    echo json_encode(['status' => 200, 'message' => 'Reschedule request approved and applied']);
                }
                else echo json_encode(['status' => 500, 'message' => 'Failed to update reschedule status']);
            }
        } else {
            echo json_encode(['status' => 400, 'message' => 'Unknown change type']);
        }
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error approving change: ' . $e->getMessage()]);
        exit;
    }
}

if ($s == 'deny_pending_change') {
    $id = isset($_POST['pending_change_id']) ? intval($_POST['pending_change_id']) : 0;
    $type = isset($_POST['change_type']) ? Wo_Secure($_POST['change_type']) : '';
    $reason = isset($_POST['reason']) ? Wo_Secure($_POST['reason']) : '';

    if (!$id || !$type) {
        echo json_encode(['status' => 400, 'message' => 'ID and Type required']);
        exit;
    }

    try {
        if ($type == 'merge') {
            $db->where('id', $id);
            $db->update('crm_merge_requests', [
                'approval_status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => $wo['user_id'] ?? 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } elseif (in_array($type, ['transfer', 'cancel', 'name_transfer', 'plot_transfer'])) {
             if ($db->tableExists('crm_transfer_history')) {
                $db->where('id', $id);
                $db->update('crm_transfer_history', [
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'approved_by' => $wo['user_id'] ?? 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
             }
        } elseif ($type == 'reschedule') {
             if ($db->tableExists('crm_pending_changes')) {
                $db->where('id', $id);
                $db->update('crm_pending_changes', [
                    'status' => 'denied',
                    'reviewed_by' => $wo['user_id'] ?? 0,
                    'review_date' => date('Y-m-d H:i:s'),
                    'review_notes' => $reason
                ]);
             }
        }

        echo json_encode(['status' => 200, 'message' => 'Change request denied']);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error denying change: ' . $e->getMessage()]);
        exit;
    }
}

if ($s == 'get_pending_change_detail') {
    $id = isset($_POST['pending_change_id']) ? intval($_POST['pending_change_id']) : 0;
    $change_type = isset($_POST['change_type']) ? Wo_Secure($_POST['change_type']) : '';
    
    if (!$id) {
        echo json_encode(['status' => 400, 'message' => 'Pending change ID required']);
        exit;
    }

    try {
        // Try to find in pending_changes first (Reschedule, Rate Change, etc.)
        if ($db->tableExists('crm_pending_changes') && (!$change_type || !in_array($change_type, ['merge', 'transfer', 'cancel', 'name_transfer', 'plot_transfer']))) {
            $db->where('id', $id);
            $change = $db->getOne('crm_pending_changes');
            
            if ($change) {
                $helper = $db->where('id', $change['purchase_id'])->getOne(T_BOOKING_HELPER, ['client_id', 'booking_id']);
                $client_name = 'Unknown';
                if ($helper) {
                    $c = $db->where('id', $helper['client_id'])->getOne(T_CUSTOMERS, ['name']);
                    if ($c) $client_name = $c['name'];
                }
                
                // Fetch reviewer name if exists
                $reviewer_name = 'Unknown';
                if (!empty($change['reviewed_by'])) {
                    $u = $db->where('user_id', $change['reviewed_by'])->getOne(T_USERS, ['username', 'first_name', 'last_name']);
                    if ($u) $reviewer_name = $u['first_name'] . ' ' . $u['last_name'];
                }

                echo json_encode([
                    'status' => 200,
                    'change' => [
                        'id' => $change['id'],
                        'purchase_id' => $change['purchase_id'],
                        'change_type' => $change['change_type'],
                        'reason' => $change['request_reason'] ?? $change['reason'],
                        'status' => $change['status'],
                        'client_name' => $client_name,
                        'change_data_json' => json_decode($change['change_data_json'], true),
                        'requested_by' => $change['requested_by'],
                        'requested_by_name' => $db->where('user_id', $change['requested_by'])->getValue(T_USERS, 'CONCAT(first_name, " ", last_name)') ?: 'System',
                        'created_at' => $change['created_at'],
                        'request_date' => $change['request_date'] ?? $change['created_at'],
                        'updated_at' => $change['updated_at'] ?? null,
                        'review_notes' => $change['review_notes'] ?? null,
                        'reviewed_by_name' => $reviewer_name,
                        'review_date' => $change['review_date'] ?? null
                    ]
                ]);
                exit;
            }
        }
        
        // Try merge requests
        if ($db->tableExists('crm_merge_requests') && (!$change_type || $change_type == 'merge')) {
            $db->where('id', $id);
            $merge = $db->getOne('crm_merge_requests');
            
            if ($merge) {
                $client = $db->where('id', $merge->client_id)->getOne(T_CUSTOMERS, ['name']);
                
                $reviewer_name = 'Unknown';
                if (!empty($merge->approved_by)) {
                    $u = $db->where('user_id', $merge->approved_by)->getOne(T_USERS, ['username', 'first_name', 'last_name']);
                    if ($u) $reviewer_name = $u['first_name'] . ' ' . $u['last_name'];
                }

                echo json_encode([
                    'status' => 200,
                    'change' => [
                        'id' => $merge->id,
                        'purchase_id' => $merge->source_purchase_id,
                        'change_type' => 'merge',
                        'reason' => $merge->merge_reason ?? 'Merge request',
                        'status' => $merge->approval_status,
                        'client_name' => $client->name ?? 'Unknown',
                        'target_purchase_id' => $merge->target_purchase_id,
                        'requested_by_name' => $db->where('user_id', $merge->client_id)->getValue(T_USERS, 'CONCAT(first_name, " ", last_name)') ?: 'System', // Assuming client initiated, or check created_by if exists
                        'created_at' => $merge->created_at,
                        'request_date' => $merge->created_at,
                        'review_notes' => $merge->review_notes ?? null, // Using review_notes as per user schema
                        'reviewed_by_name' => $reviewer_name,
                        'review_date' => $merge->updated_at,
                        'preview_data_json' => [
                            'target_purchase_id' => $merge->target_purchase_id,
                            'merge_paid_amount' => $merge->merge_paid_amount,
                            'merge_payment_schedule' => $merge->merge_payment_schedule,
                            'merge_credits' => $merge->merge_credits,
                            'reschedule_payments' => $merge->reschedule_payments
                        ]
                    ]
                ]);
                exit;
            }
        }

        // Try transfer history
        if ($db->tableExists('crm_transfer_history') && (!$change_type || in_array($change_type, ['transfer', 'cancel', 'name_transfer', 'plot_transfer']))) {
            $db->where('id', $id);
            $transfer = $db->getOne('crm_transfer_history');
            
            if ($transfer) {
                $client = $db->where('id', $transfer->client_id)->getOne(T_CUSTOMERS, ['name']);
                
                $reviewer_name = 'Unknown';
                if (!empty($transfer->approved_by)) {
                    $u = $db->where('user_id', $transfer->approved_by)->getOne(T_USERS, ['username', 'first_name', 'last_name']);
                    if ($u) $reviewer_name = $u['first_name'] . ' ' . $u['last_name'];
                }

                echo json_encode([
                    'status' => 200,
                    'change' => [
                        'id' => $transfer->id,
                        'purchase_id' => $transfer->purchase_id,
                        'change_type' => $transfer->transfer_type ?? 'transfer',
                        'reason' => $transfer->transfer_reason ?? 'Transfer request',
                        'status' => $transfer->status,
                        'client_name' => $client->name ?? 'Unknown',
                        'requested_by_name' => $db->where('user_id', $transfer->client_id)->getValue(T_USERS, 'CONCAT(first_name, " ", last_name)') ?: 'System',
                        'created_at' => $transfer->created_at,
                        'request_date' => $transfer->created_at,
                        'review_notes' => $transfer->remarks ?? null, // Mapping remarks to review_notes for frontend
                        'reviewed_by_name' => $reviewer_name,
                        'review_date' => $transfer->updated_at,
                        'preview_data_json' => [
                            'transfer_type' => $transfer->transfer_type
                        ]
                    ]
                ]);
                exit;
            }
        }
        
        echo json_encode(['status' => 404, 'message' => 'Pending change not found']);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s == 'get_purchase_pending_status') {
    $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : (isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0);
    
    if (!$purchase_id) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
        exit;
    }

    try {
        $has_pending = false;
        $pending_count = 0;
        $pending_types = [];
        
        // Check crm_pending_changes
        if ($db->tableExists('crm_pending_changes')) {
            $db->where('purchase_id', $purchase_id);
            $db->where('status', 'pending');
            $count = $db->getValue('crm_pending_changes', 'count(*)');
            if ($count > 0) {
                $has_pending = true;
                $pending_count += $count;
                $pending_types[] = 'reschedule';
            }
        }
        
        // Check crm_merge_requests (source)
        if ($db->tableExists('crm_merge_requests')) {
            $db->where('source_purchase_id', $purchase_id);
            $db->where('approval_status', 'pending');
            $count = $db->getValue('crm_merge_requests', 'count(*)');
            if ($count > 0) {
                $has_pending = true;
                $pending_count += $count;
                $pending_types[] = 'merge';
            }
        }
        
        // Check crm_transfer_history
        if ($db->tableExists('crm_transfer_history')) {
            $db->where('purchase_id', $purchase_id);
            $db->where('status', 'pending');
            $count = $db->getValue('crm_transfer_history', 'count(*)');
            if ($count > 0) {
                $has_pending = true;
                $pending_count += $count;
                $pending_types[] = 'transfer';
            }
        }
        
        echo json_encode([
            'status' => 200,
            'has_pending' => $has_pending,
            'pending_count' => $pending_count,
            'pending_types' => $pending_types
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($s == 'auto_expire_pending') {
    try {
        $expire_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        $expired_count = 0;
        
        // Expire old pending changes
        if ($db->tableExists('crm_pending_changes')) {
            $db->where('status', 'pending');
            $db->where('created_at', $expire_date, '<');
            $count = $db->update('crm_pending_changes', [
                'status' => 'expired',
                'updated_at' => date('Y-m-d H:i:s'),
                'review_notes' => 'Auto-expired after 30 days'
            ]);
            $expired_count += $count ?: 0;
        }
        
        // Expire old merge requests
        if ($db->tableExists('crm_merge_requests')) {
            $db->where('approval_status', 'pending');
            $db->where('created_at', $expire_date, '<');
            $count = $db->update('crm_merge_requests', [
                'approval_status' => 'expired',
                'updated_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => 'Auto-expired after 30 days'
            ]);
            $expired_count += $count ?: 0;
        }
        
        // Expire old transfers
        if ($db->tableExists('crm_transfer_history')) {
            $db->where('status', 'pending');
            $db->where('created_at', $expire_date, '<');
            $count = $db->update('crm_transfer_history', [
                'status' => 'expired',
                'updated_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => 'Auto-expired after 30 days'
            ]);
            $expired_count += $count ?: 0;
        }
        
        echo json_encode([
            'status' => 200,
            'message' => 'Expired old pending changes',
            'expired_count' => $expired_count
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
