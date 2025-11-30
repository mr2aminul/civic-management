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
                
                // 1. Transfer Invoices (if configured)
                if ($merge->merge_credits == 1) {
                    $db->where('purchase_id', $merge->source_purchase_id);
                    $db->update('crm_invoices', ['purchase_id' => $merge->target_purchase_id]);
                }

                // 2. Transfer Payments/Receipts (if configured)
                if ($merge->merge_paid_amount == 1) {
                    // Transfer receipts
                    $db->where('purchase_id', $merge->source_purchase_id);
                    $db->update('crm_money_receipts', ['purchase_id' => $merge->target_purchase_id]);

                    // Transfer payment credits
                    if ($db->tableExists('crm_payment_credits')) {
                        $db->where('purchase_id', $merge->source_purchase_id);
                        $db->update('crm_payment_credits', ['purchase_id' => $merge->target_purchase_id]);
                    }
                }

                // 3. Consolidate Payment Schedule (if configured)
                if ($merge->merge_payment_schedule == 1) {
                    // Get max installment number from target to append source installments
                    $max_inst = $db->where('purchase_id', $merge->target_purchase_id)->getValue('crm_payment_schedule', 'MAX(installment_number)');
                    $max_inst = $max_inst ? intval($max_inst) : 0;

                    // Update source schedule items: change purchase_id and increment installment_number
                    $db->rawQuery("UPDATE crm_payment_schedule 
                                   SET purchase_id = ?, 
                                       installment_number = installment_number + ? 
                                   WHERE purchase_id = ?", 
                                   [$merge->target_purchase_id, $max_inst, $merge->source_purchase_id]);
                }

                // 4. Update Source Purchase Status (e.g. 5 = Merged)
                $db->where('id', $merge->source_purchase_id);
                $db->update(T_BOOKING_HELPER, ['status' => 5, 'has_pending_changes' => 0]);

                // 5. Audit Trail
                if ($db->tableExists('crm_audit_trail')) {
                    $db->insert('crm_audit_trail', [
                        'user_id' => $wo['user_id'] ?? 0,
                        'action_category' => 'purchase',
                        'action_type' => 'merge_approved',
                        'action_description' => "Purchase {$merge->source_purchase_id} merged into {$merge->target_purchase_id}",
                        'details' => json_encode([
                            'merge_id' => $id,
                            'source' => $merge->source_purchase_id,
                            'target' => $merge->target_purchase_id
                        ]),
                        'performed_by' => $wo['user_id'] ?? 0,
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'performed_at' => date('Y-m-d H:i:s')
                    ]);
                }

                echo json_encode(['status' => 200, 'message' => 'Merge request approved and executed']);
            } else {
                echo json_encode(['status' => 500, 'message' => 'Failed to update merge status']);
            }

        } elseif (in_array($type, ['name_transfer', 'plot_transfer'])) {
             if ($db->tableExists('crm_pending_changes')) {
                // 1. Fetch from crm_pending_changes
                $db->where('id', $id);
                $change = $db->getOne('crm_pending_changes');
                
                if (!$change) {
                    echo json_encode(['status' => 404, 'message' => 'Change request not found']);
                    exit;
                }

                $data = json_decode($change['change_data_json'], true);
                $transfer_id = $data['transfer_id'] ?? 0;
                $purchase_id = $change['purchase_id'];
                
                // 2. Fetch Transfer History
                $transfer = $db->where('id', $transfer_id)->getOne('crm_transfer_history');
                if (!$transfer) {
                    echo json_encode(['status' => 404, 'message' => 'Transfer history record not found']);
                    exit;
                }

                $db->startTransaction();
                try {
                    // 3. Execute Logic
                    if ($type == 'name_transfer') {
                        $client_id = $data['to_client_id'];
                        
                        // Update Helper
                        $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
                            'client_id' => (string)$client_id,
                            'time' => time(),
                            'has_pending_changes' => 0,
                            'updated_at' => time()
                        ]);
                        
                        // Update Schedule
                        $db->where('purchase_id', $purchase_id)->update('crm_payment_schedule', [
                            'remarks' => 'Name transferred to client ID: ' . $client_id . ' | ' . $change['request_reason'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                    } elseif ($type == 'plot_transfer') {
                        $new_plot_id = $data['new_plot_id'];
                        $new_per_katha = $data['new_per_katha'];
                        $old_per_katha = $transfer->plot_transfer_rate_old; // or from data
                        
                        // Update Helper
                        $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
                            'booking_id' => $new_plot_id,
                            'per_katha' => $new_per_katha,
                            'time' => time(),
                            'has_pending_changes' => 0,
                            'updated_at' => time()
                        ]);
                        
                        // Update Schedule (Archive old)
                        $db->where('purchase_id', $purchase_id)->update('crm_payment_schedule', [
                            'remarks' => 'Plot transferred to plot ID: ' . $new_plot_id . ' | Old rate: ' . $old_per_katha . ' | New rate: ' . $new_per_katha . ' | ' . $change['request_reason'],
                            'status' => 99, // archived/transferred status
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    // 4. Update Statuses
                    $db->where('id', $id);
                    $db->update('crm_pending_changes', [
                        'status' => 'approved',
                        'reviewed_by' => $wo['user_id'] ?? 0,
                        'review_date' => date('Y-m-d H:i:s'),
                        'review_notes' => $notes
                    ]);
                    
                    $db->where('id', $transfer_id);
                    $db->update('crm_transfer_history', [
                        'approval_status' => 1, // Approved
                        'approved_by' => $wo['user_id'] ?? 0,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'remarks' => $notes
                    ]);

                    // 5. Audit Trail
                    if ($db->tableExists('crm_audit_trail')) {
                        $db->insert('crm_audit_trail', [
                            'user_id' => $wo['user_id'] ?? 0,
                            'action_category' => 'purchase',
                            'action_type' => $type . '_approved',
                            'action_description' => ucfirst(str_replace('_', ' ', $type)) . " approved for Purchase #$purchase_id",
                            'details' => json_encode(['pending_change_id' => $id, 'transfer_id' => $transfer_id]),
                            'performed_by' => $wo['user_id'] ?? 0,
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'performed_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    $db->commit();
                    echo json_encode(['status' => 200, 'message' => ucfirst(str_replace('_', ' ', $type)) . ' request approved and executed']);

                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['status' => 500, 'message' => 'Error executing transfer: ' . $e->getMessage()]);
                }
             }
        } elseif ($type == 'cancel') {
             if ($db->tableExists('crm_pending_changes')) {
                $db->where('id', $id);
                $change = $db->getOne('crm_pending_changes');
                
                if (!$change) {
                    echo json_encode(['status' => 404, 'message' => 'Change request not found']);
                    exit;
                }

                $data = json_decode($change['change_data_json'], true);
                $purchase_id = $change['purchase_id'];
                $reason = $change['request_reason'];
                
                // Execute Cancellation Logic
                $db->startTransaction();
                try {
                    // 1. Insert into crm_purchase_cancellations
                    $cancel_data = [
                        'purchase_id' => $purchase_id,
                        'client_id' => $change['client_id'],
                        'cancellation_reason' => $reason,
                        'total_paid_amount' => $data['total_paid'] ?? 0,
                        'cancellation_fee_mode' => $data['fee_mode'] ?? 'none',
                        'cancellation_fee_value' => $data['fee_value'] ?? 0,
                        'refund_initiated' => $data['initiate_refund'] ?? 0,
                        'cancelled_by' => $wo['user_id'] ?? 0,
                        'cancelled_at' => date('Y-m-d H:i:s'),
                        'remarks' => $notes
                    ];
                    $db->insert('crm_purchase_cancellations', $cancel_data);

                    // 2. Update Booking Helper
                    $db->where('id', $purchase_id);
                    $db->update(T_BOOKING_HELPER, [
                        'status' => 4, 
                        'cancel_date' => time(),
                        'has_pending_changes' => 0,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    // 3. Update Payment Schedule
                    $db->where('purchase_id', $purchase_id);
                    $db->update('crm_payment_schedule', [
                        'status' => 4,
                        'remarks' => 'Cancelled. Reason: ' . $reason,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    // 4. Booking Level Logic (Clear file number if needed)
                    $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
                    if ($helper) {
                        $booking_id = $helper->booking_id;
                        $otherHelpers = $db->where('booking_id', $booking_id)
                                           ->where('id', $purchase_id, '!=')
                                           ->where('status', ['0','1','4'], 'NOT IN') // Active statuses
                                           ->get(T_BOOKING_HELPER);
                        
                        if (empty($otherHelpers)) {
                            // No other active purchases, cancel booking
                            $db->where('id', $booking_id)->update(T_BOOKING, ['status' => 4, 'file_num' => null]);
                        }
                    }

                    // 5. Create Refund Schedule (if requested)
                    if (($data['initiate_refund'] ?? 0) == 1 && ($data['refundable_amount'] ?? 0) > 0) {
                        $db->insert('crm_refund_schedule', [
                            'purchase_id' => $purchase_id,
                            'client_id' => $change['client_id'],
                            'refund_initiation_date' => date('Y-m-d'),
                            'total_paid_amount' => $data['total_paid'],
                            'deduction_percentage' => ($data['fee_mode'] == 'percent') ? $data['fee_value'] : 0,
                            'deduction_amount' => $data['cancellation_fee'],
                            'refundable_amount' => $data['refundable_amount'],
                            'installment_number' => 1,
                            'installment_amount' => $data['refundable_amount'],
                            'due_date' => date('Y-m-d', strtotime('+30 days')),
                            'status' => 0,
                            'created_by' => $wo['user_id'] ?? 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    // 6. Update Pending Change Status
                    $db->where('id', $id);
                    $db->update('crm_pending_changes', [
                        'status' => 'approved',
                        'reviewed_by' => $wo['user_id'] ?? 0,
                        'review_date' => date('Y-m-d H:i:s'),
                        'review_notes' => $notes
                    ]);

                    // 7. Audit Trail
                    if ($db->tableExists('crm_audit_trail')) {
                        $db->insert('crm_audit_trail', [
                            'user_id' => $wo['user_id'] ?? 0,
                            'action_category' => 'purchase',
                            'action_type' => 'cancel_approved',
                            'action_description' => "Cancellation approved for Purchase #$purchase_id",
                            'details' => json_encode(['pending_change_id' => $id, 'purchase_id' => $purchase_id]),
                            'performed_by' => $wo['user_id'] ?? 0,
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'performed_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    $db->commit();
                    echo json_encode(['status' => 200, 'message' => 'Cancellation approved and executed']);

                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['status' => 500, 'message' => 'Error executing cancellation: ' . $e->getMessage()]);
                }
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
                
        } elseif ($type == 'reschedule') {
             if ($db->tableExists('crm_pending_changes')) {
                $db->where('id', $id);
                $change = $db->getOne('crm_pending_changes');
                
                if (!$change) {
                    echo json_encode(['status' => 404, 'message' => 'Change request not found']);
                    exit;
                }

                $data = json_decode($change['change_data_json'], true);
                $purchase_id = $change['purchase_id'];
                $monthly_amount = $data['monthly_amount'];
                $reschedule_history_id = $data['reschedule_history_id'] ?? 0;

                $db->startTransaction();
                try {
                    // 1. Mark old unpaid installments as deleted (status = 99)
                    $db->where('purchase_id', $purchase_id);
                    $db->where('status', 0); // Unpaid only
                    $affected = $db->update('crm_payment_schedule', [
                        'status' => 99, // Mark as deleted
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    // 2. Calculate remaining balance from marked-deleted items
                    $remaining_balance = 0;
                    $db->where('purchase_id', $purchase_id);
                    $db->where('status', 99);
                    // Ensure we only pick up the ones we just deleted or previously deleted? 
                    // Better to rely on the fact that we just marked them.
                    // But to be safe, let's sum up ALL status 99 items? No, that might include previous reschedules.
                    // Actually, the logic in reschedule_payment.php was:
                    // $db->where('purchase_id', $purchase_id); $db->where('status', 99); $marked_deleted = $db->get('crm_payment_schedule');
                    // This gets ALL deleted items. This might be wrong if there were previous reschedules.
                    // However, if we assume previous reschedules were "processed" and maybe purged or we just want to reschedule *everything* that is unpaid...
                    // The logic "Mark old unpaid installments as deleted" implies we are rescheduling the *current* unpaid balance.
                    // So we should sum the amount of the items we JUST marked as 99.
                    // But we can't easily identify them unless we tracked IDs.
                    // Alternative: Calculate remaining balance BEFORE update.
                    
                    // Let's rollback and do it safer:
                    // Get unpaid items first
                    $db->where('purchase_id', $purchase_id);
                    $db->where('status', 0);
                    $unpaid_items = $db->get('crm_payment_schedule');
                    
                    $remaining_balance = 0;
                    foreach ($unpaid_items as $item) {
                        $remaining_balance += (floatval($item->installment_amount) - floatval($item->paid_amount));
                    }
                    
                    // NOW mark them as deleted
                    $db->where('purchase_id', $purchase_id);
                    $db->where('status', 0);
                    $db->update('crm_payment_schedule', ['status' => 99, 'updated_at' => date('Y-m-d H:i:s')]);

                    // 3. Get last installment number to continue sequence
                    $db->where('purchase_id', $purchase_id);
                    $db->where('status', '99', '!=');
                    $db->orderBy('installment_number', 'DESC');
                    $last_item = $db->getOne('crm_payment_schedule');
                    $last_installment_number = $last_item ? intval($last_item->installment_number) : 0;

                    // 4. Create new installments
                    $new_installment_count = ceil($remaining_balance / $monthly_amount);
                    $running_total = 0;
                    $first_new_number = $last_installment_number + 1;
                    
                    for ($i = 0; $i < $new_installment_count; $i++) {
                        $installment_number = $first_new_number + $i;
                        $is_last = ($i == $new_installment_count - 1);
                        
                        if ($is_last) {
                            $amount = $remaining_balance - $running_total;
                        } else {
                            $amount = $monthly_amount;
                        }
                        
                        $running_total += $amount;
                        
                        // Helper function for ordinal (if not available, just use number)
                        $ordinal = $installment_number . 'th'; // Simplified
                        
                        $db->insert('crm_payment_schedule', [
                            'purchase_id' => $purchase_id,
                            'installment_number' => $installment_number,
                            'particular' => $ordinal . ' Installment (R)',
                            'installment_type' => 'installment',
                            'due_date' => date('Y-m-d', strtotime('+' . ($i + 1) . ' month')),
                            'installment_amount' => $amount,
                            'paid_amount' => 0,
                            'status' => 0, // Unpaid
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    // 5. Update Booking Helper
                    $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, [
                        'monthly_amount' => $monthly_amount,
                        'installment_count' => $new_installment_count, // This might be total or remaining? Usually total. But here we calculated new count.
                        // If we want total count, we should add $last_installment_number?
                        // But usually installment_count in helper is just for reference or "how many installments".
                        // Let's set it to the new total count (paid + new).
                        // Wait, $new_installment_count is just the NEW ones.
                        // Total count = (count of non-99 items).
                        // Let's count them.
                        // $total_count = $db->where('purchase_id', $purchase_id)->where('status', '99', '!=')->getValue('crm_payment_schedule', 'count(*)');
                        // But we are inside transaction, so we can just use $last_installment_number + $new_installment_count.
                        'installment_count' => $last_installment_number + $new_installment_count,
                        'has_pending_changes' => 0,
                        'updated_at' => time()
                    ]);

                    // 6. Update Statuses
                    $db->where('id', $id);
                    $db->update('crm_pending_changes', [
                        'status' => 'approved',
                        'reviewed_by' => $wo['user_id'] ?? 0,
                        'review_date' => date('Y-m-d H:i:s'),
                        'review_notes' => $notes
                    ]);
                    
                    if ($reschedule_history_id && $db->tableExists('crm_payment_reschedule_history')) {
                        $db->where('id', $reschedule_history_id);
                        $db->update('crm_payment_reschedule_history', [
                            'status' => 'approved',
                            'approved_by' => $wo['user_id'] ?? 0,
                            'approved_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    // 7. Audit Trail
                    if ($db->tableExists('crm_audit_trail')) {
                        $db->insert('crm_audit_trail', [
                            'user_id' => $wo['user_id'] ?? 0,
                            'action_category' => 'purchase',
                            'action_type' => 'reschedule_approved',
                            'action_description' => "Reschedule approved for Purchase #$purchase_id",
                            'details' => json_encode(['pending_change_id' => $id, 'new_monthly' => $monthly_amount]),
                            'performed_by' => $wo['user_id'] ?? 0,
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'performed_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    $db->commit();
                    echo json_encode(['status' => 200, 'message' => 'Reschedule request approved and executed']);

                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['status' => 500, 'message' => 'Error executing reschedule: ' . $e->getMessage()]);
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
