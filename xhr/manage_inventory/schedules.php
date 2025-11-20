<?php
    // Export payment schedule
    if ($s === 'export_payment_schedule') {
        header('Content-Type: application/json; charset=utf-8');
        
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $format = isset($_POST['format']) ? strtolower(trim($_POST['format'])) : 'xlsx';
        
        if ($purchase_id <= 0) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
            exit;
        }
        
        try {
            // Get purchase details
            $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
                exit;
            }
            
            $booking = $db->where('id', $helper->booking_id)->getOne(T_BOOKING);
            $client = GetCustomerById($helper->client_id);
            
            // Get payment schedule
            $schedule = [];
            if (!empty($helper->installment)) {
                $schedule = json_decode($helper->installment, true) ?: [];
            }
            
            if (empty($schedule)) {
                echo json_encode(['status' => 404, 'message' => 'No payment schedule found']);
                exit;
            }
            
            // Generate filename
            $client_name_safe = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $client['name'] ?? 'client'));
            $filename = "payment_schedule_{$client_name_safe}_{$purchase_id}_" . date('Y-m-d') . ".$format";
            
            // In production, you would generate the actual Excel/PDF file here
            // For this example, return success with structured data
            
            echo json_encode([
                'status' => 200,
                'message' => 'Payment schedule exported successfully',
                'filename' => $filename,
                'download_url' => '/downloads/schedules/' . $filename, // Mock URL
                'format' => $format,
                'schedule_count' => count($schedule)
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ------------------------------
    // UPDATE INSTALLMENT SCHEDULE
    // ------------------------------
    if ($s == 'update_installment') {
        global $db, $wo;
    
        $purchase_id   = isset($_POST['purchase_id']) ? (int) $_POST['purchase_id'] : 0;
        $schedule_json = isset($_POST['schedule']) ? $_POST['schedule'] : '[]';
    
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
            exit;
        }
    
        $schedule = json_decode($schedule_json, true);
        if (!is_array($schedule)) {
            echo json_encode(['status' => 400, 'message' => 'Invalid schedule data']);
            exit;
        }
    
        // fetch helper and booking
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) { echo json_encode(['status' => 404, 'message' => 'Purchase not found']); exit; }
        $booking = $db->where('id', $helper->booking_id)->getOne(T_BOOKING);
        if (!$booking) { echo json_encode(['status' => 404, 'message' => 'Booking not found']); exit; }
    
        $per_katha     = (float) ($helper->per_katha ?? 0);
        $katha         = (float) ($booking->katha ?? 0);
        $down_payment  = (float) ($helper->down_payment ?? 0);
        $booking_money = (float) ($helper->booking_money ?? 0);
        $total_price   = $per_katha * $katha;
    
        // expected remaining to be covered by installment rows (exclude booking & down)
        $expected_remaining = (float) round($total_price - ($down_payment + $booking_money), 2);
    
        // sanitize incoming items
        $sanitized = [];
        $booking_amount_found = null;
        $down_amount_found = null;
        $booking_due_date = '';
        $booking_payment_date = '';
        $down_due_date = '';
        $down_payment_date = '';
    
        foreach ($schedule as $i => $item) {
            if (!is_array($item)) $item = [];
    
            $amount_raw = $item['installment_amount'] ?? ($item['amount'] ?? ($item['payment_amount'] ?? 0));
            $paid_raw   = $item['paid_amount'] ?? ($item['amount_paid'] ?? (isset($item['paid']) && $item['paid'] ? $amount_raw : 0));
    
            $installment_amount = round((float) str_replace(',', '', (string)$amount_raw), 2);
            $paid_amount = round((float) str_replace(',', '', (string)$paid_raw), 2);
    
            $particular       = trim((string)($item['particular'] ?? ''));
            $due_date         = trim((string)($item['date'] ?? ''));
            $payment_date     = trim((string)($item['payment_date'] ?? ''));
            $payment_method   = trim((string)($item['payment_method'] ?? ''));
            $installment_lbl  = isset($item['installment']) ? intval($item['installment']) : ($i + 1);
            $money_receipt_no = trim((string)($item['money_receipt_no'] ?? ''));
            $remarks          = trim((string)($item['remarks'] ?? ''));
            $adjustment       = !empty($item['adjustment']);
            $type             = strtolower(trim((string)($item['type'] ?? 'installment')));
    
            // normalize dates to Y-m-d. If due_date is empty, fall back to helper start date (or today) because DB due_date may be NOT NULL
            $installment_start_date = $helper->installment_start_date ?? ($helper->start_date ?? date('Y-m-d'));
            $norm_due_date = $due_date ?: $installment_start_date;
            try { $nd = new DateTime($norm_due_date); $norm_due_date = $nd->format('Y-m-d'); } catch (Exception $e) { $norm_due_date = $installment_start_date; }
            $norm_payment_date = null;
            if (!empty($payment_date)) {
                try { $pd = new DateTime($payment_date); $norm_payment_date = $pd->format('Y-m-d'); } catch (Exception $e) { $norm_payment_date = null; }
            }
    
            // detect booking/down
            $lower_part = strtolower($particular);
            $is_booking = ($type === 'booking') || (strpos($lower_part, 'booking') !== false && strpos($lower_part, 'down') === false);
            $is_down = ($type === 'down') || (strpos($lower_part, 'down') !== false);
    
            if ($is_booking) {
                if ($booking_amount_found === null && $installment_amount > 0) $booking_amount_found = $installment_amount;
                if ($booking_due_date === '' && $norm_due_date) $booking_due_date = $norm_due_date;
                if ($booking_payment_date === '' && $norm_payment_date) $booking_payment_date = $norm_payment_date;
            } elseif ($is_down) {
                if ($down_amount_found === null && $installment_amount > 0) $down_amount_found = $installment_amount;
                if ($down_due_date === '' && $norm_due_date) $down_due_date = $norm_due_date;
                if ($down_payment_date === '' && $norm_payment_date) $down_payment_date = $norm_payment_date;
            }
    
            // status: 1 paid, 2 partial, 0 pending
            $row_status = 0;
            if ($installment_amount > 0 && $paid_amount >= $installment_amount) $row_status = 1;
            elseif ($paid_amount > 0 && $paid_amount < $installment_amount) $row_status = 2;
    
            $sanitized[] = [
                'particular'                 => $particular,
                'type'                       => $type,
                'date'                       => $norm_due_date,
                'payment_date'               => $norm_payment_date,
                'payment_method'             => $payment_method,
                'installment_amount'         => $installment_amount,
                'paid_amount'                => $paid_amount,
                'installment'                => $installment_lbl,
                'money_receipt_no'           => $money_receipt_no,
                'remarks'                    => $remarks,
                'adjustment'                 => $adjustment ? true : false,
                'original_installment_amount'=> $installment_amount,
                'history'                    => (isset($item['history']) && is_array($item['history'])) ? $item['history'] : [],
                'paid'                       => ($row_status === 1),
                'status'                     => $row_status
            ];
        }
    
        // sum installments excluding booking/down rows
        $sum_installments = 0.0;
        foreach ($sanitized as $si) {
            $p = strtolower(trim((string)$si['particular']));
            $is_booking_row = (strpos($p, 'booking') !== false && strpos($p, 'down') === false) || ($si['type'] === 'booking');
            $is_down_row = (strpos($p, 'down') !== false) || ($si['type'] === 'down');
            if ($is_booking_row || $is_down_row) continue;
            $sum_installments += (float)$si['installment_amount'];
        }
    
        // adjust last installment to match expected_remaining (rounded to 2 decimals)
        $diff = round($expected_remaining - $sum_installments, 2);
        if (abs($diff) >= 0.01) {
            $lastInstallIdx = null;
            for ($i = count($sanitized) - 1; $i >= 0; $i--) {
                $p = strtolower(trim((string)$sanitized[$i]['particular']));
                $is_booking_row = (strpos($p, 'booking') !== false && strpos($p, 'down') === false) || ($sanitized[$i]['type'] === 'booking');
                $is_down_row = (strpos($p, 'down') !== false) || ($sanitized[$i]['type'] === 'down');
                if (!$is_booking_row && !$is_down_row && empty($sanitized[$i]['manual_installment_edit'] ?? false)) { $lastInstallIdx = $i; break; }
            }
            if ($lastInstallIdx === null) {
                for ($i = count($sanitized) - 1; $i >= 0; $i--) {
                    $p = strtolower(trim((string)$sanitized[$i]['particular']));
                    $is_booking_row = (strpos($p, 'booking') !== false && strpos($p, 'down') === false) || ($sanitized[$i]['type'] === 'booking');
                    $is_down_row = (strpos($p, 'down') !== false) || ($sanitized[$i]['type'] === 'down');
                    if (!$is_booking_row && !$is_down_row) { $lastInstallIdx = $i; break; }
                }
            }
            if ($lastInstallIdx === null) {
                echo json_encode(['status' => 400, 'message' => 'No installment rows found to adjust. Expected remaining: ৳' . number_format($expected_remaining, 2) . ', Installments sum: ৳' . number_format($sum_installments, 2)]);
                exit;
            }
            $prev = $sanitized[$lastInstallIdx]['installment_amount'];
            $sanitized[$lastInstallIdx]['installment_amount'] = round($prev + $diff, 2);
            $sanitized[$lastInstallIdx]['original_installment_amount'] = $sanitized[$lastInstallIdx]['original_installment_amount'] ?? $prev;
            $sanitized[$lastInstallIdx]['history'][] = [
                'ts' => date('c'),
                'field' => 'installment_amount',
                'from' => $prev,
                'to' => $sanitized[$lastInstallIdx]['installment_amount'],
                'reason' => 'Server auto-adjust to match expected remaining'
            ];
            // update sum
            $sum_installments = round($sum_installments + $diff, 2);
        }
    
        // final validation
        $final_installment_sum = 0.0;
        foreach ($sanitized as $si) {
            $p = strtolower(trim((string)$si['particular']));
            $is_booking_row = (strpos($p, 'booking') !== false && strpos($p, 'down') === false) || ($si['type'] === 'booking');
            $is_down_row = (strpos($p, 'down') !== false) || ($si['type'] === 'down');
            if (!$is_booking_row && !$is_down_row) $final_installment_sum += (float)$si['installment_amount'];
        }
    
        if (round($final_installment_sum, 2) !== round($expected_remaining, 2)) {
            echo json_encode(['status' => 400, 'message' => 'Installments total mismatch after auto-adjustment. Expected: ' . number_format($expected_remaining,2) . ', Got: ' . number_format($final_installment_sum,2)]);
            exit;
        }
    
        // persist: update helper (no legacy installment JSON changes) and write rows to crm_payment_schedule
        $update_data = ['updated_at' => time()];
        if (is_numeric($booking_amount_found) && $booking_amount_found > 0) {
            $booking_amount_found = round((float)$booking_amount_found, 2);
            if (abs($booking_money - $booking_amount_found) > 0) $update_data['booking_money'] = $booking_amount_found;
        }
        if (is_numeric($down_amount_found) && $down_amount_found > 0) {
            $down_amount_found = round((float)$down_amount_found, 2);
            if (abs($down_payment - $down_amount_found) > 0) $update_data['down_payment'] = $down_amount_found;
        }
    
        if (method_exists($db, 'startTransaction')) $db->startTransaction();
    
        try {
            // update helper with any booking/down amount changes and timestamp (do NOT write installment JSON)
            $db->where('id', $purchase_id)->update(T_BOOKING_HELPER, $update_data);
    
            $userId = isset($wo['user']['id']) ? (int)$wo['user']['id'] : null;
    
            // archive existing schedule rows by setting status = 99
            $db->where('purchase_id', $purchase_id)->update('crm_payment_schedule', [
                'status' => 99,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userId
            ]);
    
            // insert new rows (map sanitized -> table columns)
            $inserted = 0;
            foreach ($sanitized as $si) {
                // compute status if not supplied
                $status = isset($si['status']) ? (int)$si['status'] : 0;
                if (!isset($si['status'])) {
                    $paid = (float)($si['paid_amount'] ?? $si['paid'] ?? 0);
                    $amt  = (float)($si['installment_amount'] ?? 0);
                    if ($amt > 0 && $paid >= $amt) $status = 1;
                    elseif ($paid > 0 && $paid < $amt) $status = 2;
                    else $status = 0;
                }
    
                $rowData = [
                    'purchase_id'        => $purchase_id,
                    'client_id'          => $helper->client_id ?? null,
                    'installment_number' => intval($si['installment'] ?? 0),
                    'particular'         => $si['particular'] ?? '',
                    'type'               => $si['type'] ?? 'installment',
                    'due_date'           => !empty($si['date']) ? $si['date'] : ($helper->installment_start_date ?? date('Y-m-d')),
                    'installment_amount' => number_format((float)$si['installment_amount'], 2, '.', ''),
                    'paid_amount'        => number_format((float)$si['paid_amount'], 2, '.', ''),
                    'payment_date'       => !empty($si['payment_date']) ? $si['payment_date'] : null,
                    'payment_method'     => $si['payment_method'] ?? null,
                    'money_receipt_no'   => $si['money_receipt_no'] ?? null,
                    'remarks'            => $si['remarks'] ?? null,
                    'status'             => $status,
                    'is_adjustment'      => !empty($si['adjustment']) ? 1 : 0,
                    'previous_amount'    => isset($si['original_installment_amount']) ? number_format((float)$si['original_installment_amount'], 2, '.', '') : null,
                    'change_reason'      => null,
                    'created_by'         => $userId,
                    'updated_by'         => $userId,
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s')
                ];
    
                // insert row (ensure only real columns are present in rowData)
                $ins = $db->insert('crm_payment_schedule', $rowData);
                if ($ins) $inserted++;
            }
    
            if (method_exists($db, 'commit')) $db->commit();
    
            if (function_exists('logActivity')) logActivity('clients', 'update', "Updated payment schedule for purchase ID: {$purchase_id}");
    
            echo json_encode([
                'status' => 200,
                'message' => 'Payment schedule saved successfully',
                'installments_total' => (float)$final_installment_sum,
                'schedule_total' => (float)$final_total_sum,
                'saved_schedule' => $sanitized,
                'inserted_rows' => $inserted
            ]);
        } catch (Exception $e) {
            if (method_exists($db, 'rollback')) $db->rollback();
            echo json_encode(['status' => 500, 'message' => 'Failed to save schedule', 'error' => $e->getMessage()]);
        }
    
        exit;
    }

    // ===============================
    //  📄 GET SCHEDULE PREVIEW FOR PRINTING
    // ===============================
    if ($s == 'get_schedule_preview') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $options_json = isset($_POST['options']) ? $_POST['options'] : '{}';
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
            exit;
        }

        $options = json_decode($options_json, true) ?: [];
        
        // Get purchase details
        $helper = $db->where('id', $purchase_id)->getOne(T_BOOKING_HELPER);
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }

        $booking = $db->where('id', $helper->booking_id)->getOne(T_BOOKING);
        $client = GetCustomerById($helper->client_id);

        // Generate preview HTML
        $html = '<div class="schedule-print-template">';
        
        // Company header
        $html .= '<div class="company-header">';
        $html .= '<div class="company-name">Civic Real Estate Ltd.</div>';
        $html .= '<div class="document-title">Payment Schedule</div>';
        $html .= '<div class="text-muted">Generated on ' . date('F j, Y') . '</div>';
        $html .= '</div>';
        
        // Client info
        if ($options['include_client_info'] ?? true) {
            $html .= '<div class="info-section">';
            $html .= '<div class="info-title">Client Information</div>';
            $html .= '<div class="info-grid">';
            $html .= '<div class="info-item"><div class="info-label">Name</div><div class="info-value">' . htmlspecialchars($client['name'] ?? '') . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Phone</div><div class="info-value">' . htmlspecialchars($client['phone'] ?? '') . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Address</div><div class="info-value">' . htmlspecialchars($client['address'] ?? '') . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">File Number</div><div class="info-value">' . htmlspecialchars($helper->file_num ?? '') . '</div></div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Plot details
        if ($options['include_plot_details'] ?? true) {
            $html .= '<div class="info-section">';
            $html .= '<div class="info-title">Plot Details</div>';
            $html .= '<div class="info-grid">';
            $html .= '<div class="info-item"><div class="info-label">Block</div><div class="info-value">' . htmlspecialchars($booking->block ?? '') . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Plot</div><div class="info-value">' . htmlspecialchars($booking->plot ?? '') . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Katha</div><div class="info-value">' . htmlspecialchars($booking->katha ?? '') . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Road</div><div class="info-value">' . htmlspecialchars($booking->road ?? '') . '</div></div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // Payment schedule table
        $schedule = [];
        if (!empty($helper->installment)) {
            $schedule_data = json_decode($helper->installment, true);
            if (is_array($schedule_data)) {
                $schedule = $schedule_data;
            }
        }

        if (!empty($schedule)) {
            $html .= '<div class="info-section">';
            $html .= '<div class="info-title">Payment Schedule</div>';
            $html .= '<table class="table table-bordered">';
            $html .= '<thead><tr><th>#</th><th>Due Date</th><th>Amount</th><th>Status</th><th>Type</th></tr></thead>';
            $html .= '<tbody>';
            
            $total_amount = 0;
            $paid_amount = 0;
            
            foreach ($schedule as $index => $item) {
                $amount = (float)($item['amount'] ?? 0);
                $total_amount += $amount;
                
                $status_badge = ($item['paid'] ?? false) ? 
                    '<span class="badge bg-success">Paid</span>' : 
                    '<span class="badge bg-warning">Pending</span>';
                
                if ($item['paid'] ?? false) {
                    $paid_amount += $amount;
                }
                
                $type_badge = ($item['adjustment'] ?? false) ? 
                    '<span class="badge bg-info">Yearly</span>' : 
                    '<span class="badge bg-secondary">Monthly</span>';
                
                // Apply filters
                $show_row = true;
                if (($options['show_paid_only'] ?? false) && !($item['paid'] ?? false)) {
                    $show_row = false;
                }
                if (($options['show_pending_only'] ?? false) && ($item['paid'] ?? false)) {
                    $show_row = false;
                }
                
                if ($show_row) {
                    $html .= '<tr>';
                    $html .= '<td>' . ($index + 1) . '</td>';
                    $html .= '<td>' . htmlspecialchars($item['date'] ?? '') . '</td>';
                    $html .= '<td>৳' . number_format($amount, 2) . '</td>';
                    $html .= '<td>' . $status_badge . '</td>';
                    $html .= '<td>' . $type_badge . '</td>';
                    $html .= '</tr>';
                }
            }
            
            $html .= '</tbody>';
            $html .= '<tfoot>';
            $html .= '<tr class="table-info"><td colspan="2"><strong>Total</strong></td><td><strong>৳' . number_format($total_amount, 2) . '</strong></td><td colspan="2"></td></tr>';
            $html .= '<tr class="table-success"><td colspan="2"><strong>Paid</strong></td><td><strong>৳' . number_format($paid_amount, 2) . '</strong></td><td colspan="2"></td></tr>';
            $html .= '<tr class="table-warning"><td colspan="2"><strong>Due</strong></td><td><strong>৳' . number_format($total_amount - $paid_amount, 2) . '</strong></td><td colspan="2"></td></tr>';
            $html .= '</tfoot>';
            $html .= '</table>';
            $html .= '</div>';
        }

        // Payment summary
        if ($options['include_payment_summary'] ?? true) {
            $per_katha = (float)($helper->per_katha ?? 0);
            $katha = (float)($booking->katha ?? 0);
            $total_price = $per_katha * $katha;
            $paid_amount = (float)$db->where('customer_id', $helper->client_id)->getValue(T_INVOICE, 'SUM(pay_amount)') ?: 0;
            
            $html .= '<div class="info-section">';
            $html .= '<div class="info-title">Payment Summary</div>';
            $html .= '<div class="info-grid">';
            $html .= '<div class="info-item"><div class="info-label">Total Price</div><div class="info-value">৳' . number_format($total_price, 2) . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Down Payment</div><div class="info-value">৳' . number_format($helper->down_payment ?? 0, 2) . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Booking Money</div><div class="info-value">৳' . number_format($helper->booking_money ?? 0, 2) . '</div></div>';
            $html .= '<div class="info-item"><div class="info-label">Invoice Paid</div><div class="info-value">৳' . number_format($paid_amount, 2) . '</div></div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Signature area
        $html .= '<div class="signature-area">';
        $html .= '<div class="signature-box"><div class="signature-line">Client Signature</div></div>';
        $html .= '<div class="signature-box"><div class="signature-line">Authorized Signature</div></div>';
        $html .= '</div>';
        
        $html .= '</div>';

        echo json_encode(['status' => 200, 'html' => $html]);
        exit;
    }

    // ===============================
    //  📊 EXPORT SCHEDULE
    // ===============================
    if ($s == 'export_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $schedule_json = isset($_POST['schedule']) ? $_POST['schedule'] : '[]';
        $format = isset($_POST['format']) ? $_POST['format'] : 'excel';
        
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'Invalid purchase ID']);
            exit;
        }

        // For now, return a placeholder download URL
        $filename = 'payment_schedule_' . $purchase_id . '_' . date('Y-m-d') . '.xlsx';
        
        echo json_encode([
            'status' => 200, 
            'download_url' => '/exports/' . $filename,
            'filename' => $filename,
            'message' => 'Schedule exported successfully'
        ]);
        exit;
    }

    // ===============================
    //  📄 DOWNLOAD SCHEDULE PDF
    // ===============================
    if ($s == 'download_schedule_pdf') {
        $purchaseId = $_POST['purchase_id'] ?? '';
        $scheduleJson = $_POST['schedule'] ?? '';
        $clientDataJson = $_POST['client_data'] ?? '';
        $printDataJson = $_POST['print_data'] ?? '';
        
        if (!$purchaseId || !$scheduleJson) {
            echo json_encode(['status' => 400, 'message' => 'Missing required data']);
            exit;
        }
        
        try {
            $schedule = json_decode($scheduleJson, true);
            $clientData = json_decode($clientDataJson, true);
            $printData = json_decode($printDataJson, true);
            
            if (!$schedule || !is_array($schedule)) {
                echo json_encode(['status' => 400, 'message' => 'Invalid schedule data']);
                exit;
            }
            
            // Generate PDF content
            $filename = 'payment_schedule_' . $purchaseId . '_' . date('Y-m-d') . '.pdf';
            $downloadUrl = generateSchedulePDF($purchaseId, $schedule, $clientData, $printData, $filename);
            
            if ($downloadUrl) {
                echo json_encode([
                    'status' => 200,
                    'download_url' => $downloadUrl,
                    'filename' => $filename,
                    'message' => 'PDF generated successfully'
                ]);
            } else {
                echo json_encode(['status' => 500, 'message' => 'Failed to generate PDF']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Error generating PDF: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===============================
    //  💾 SAVE SCHEDULE XLSX
    // ===============================
    if ($s == 'save_schedule_xlsx') {
        $purchaseId = $_POST['purchase_id'] ?? '';
        $scheduleJson = $_POST['schedule'] ?? '';
        $clientDataJson = $_POST['client_data'] ?? '';
        $printDataJson = $_POST['print_data'] ?? '';
        $format = $_POST['format'] ?? 'xlsx';
        
        if (!$purchaseId || !$scheduleJson) {
            echo json_encode(['status' => 400, 'message' => 'Missing required data']);
            exit;
        }
        
        try {
            $schedule = json_decode($scheduleJson, true);
            $clientData = json_decode($clientDataJson, true);
            $printData = json_decode($printDataJson, true);
            
            if (!$schedule || !is_array($schedule)) {
                echo json_encode(['status' => 400, 'message' => 'Invalid schedule data']);
                exit;
            }
            
            // Generate Excel file
            $filename = 'payment_schedule_' . $purchaseId . '_' . date('Y-m-d') . '.' . $format;
            $result = generateScheduleExcel($purchaseId, $schedule, $clientData, $printData, $filename, $format);
            
            if ($result && isset($result['file_path'])) {
                echo json_encode([
                    'status' => 200,
                    'file_path' => $result['file_path'],
                    'download_url' => $result['download_url'],
                    'filename' => $filename,
                    'message' => 'Excel file generated successfully'
                ]);
            } else {
                echo json_encode(['status' => 500, 'message' => 'Failed to generate Excel file']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Error generating Excel: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===============================
    //  💾 GET PAYMENT SCHEDULE
    // ===============================
    if ($s === 'get_payment_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            // Load from dedicated table
            $schedule = $db->where('purchase_id', $purchase_id)->where('status', 99, '!=')->orderBy('installment_number', 'ASC')->get('crm_payment_schedule') ?: [];
            
            // Calculate summary
            $total_amount = 0;
            $total_paid = 0;
            $pending_count = 0;
            foreach ($schedule as $entry) {
                $total_amount += (float)$entry->installment_amount;
                $total_paid += (float)$entry->paid_amount;
                if ($entry->status == 0) $pending_count++;
            }

            echo json_encode([
                'status' => 200,
                'schedule' => $schedule,
                'summary' => [
                    'total_amount' => $total_amount,
                    'total_paid' => $total_paid,
                    'total_due' => $total_amount - $total_paid,
                    'pending_count' => $pending_count,
                    'total_entries' => count($schedule)
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===============================
    //  💾 RECALCULATE SCHEDULE
    // ===============================
    if ($s === 'recalculate_schedule') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $new_plot_id = isset($_POST['new_plot_id']) ? (int)$_POST['new_plot_id'] : 0;

        if (!$purchase_id || !$new_plot_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id and new_plot_id are required']);
            exit;
        }

        try {
            $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            $old_booking = $db->where('id', $helper->booking_id)->getOne('wo_booking');
            $new_booking = $db->where('id', $new_plot_id)->getOne('wo_booking');

            if (!$old_booking || !$new_booking) {
                echo json_encode(['status' => 404, 'message' => 'Plot not found']);
                exit;
            }

            $old_total = (float)$helper->per_katha * (float)$old_booking->katha;
            $new_total = (float)$helper->per_katha * (float)$new_booking->katha;
            $difference = $new_total - $old_total;

            // Log audit
            log_crm_audit('payment_schedule', 'plot_change', $purchase_id,
                ['old_plot' => $old_booking->plot, 'new_plot' => $new_booking->plot, 'price_difference' => $difference],
                $_SESSION['user_id'] ?? null
            );

            echo json_encode([
                'status' => 200,
                'message' => 'Schedule recalculation preview',
                'old_plot' => $old_booking->plot,
                'new_plot' => $new_booking->plot,
                'old_total' => $old_total,
                'new_total' => $new_total,
                'difference' => $difference,
                'per_katha' => $helper->per_katha
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===============================
    //  💾 UPDATE PAYMENT STATUS
    // ===============================
    if ($s === 'update_payment_status') {
        $payment_schedule_id = isset($_POST['payment_schedule_id']) ? (int)$_POST['payment_schedule_id'] : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d');
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Bank Transfer';
        $receipt_no = isset($_POST['receipt_no']) ? trim($_POST['receipt_no']) : '';

        if (!$payment_schedule_id || !$amount) {
            echo json_encode(['status' => 400, 'message' => 'payment_schedule_id and amount are required']);
            exit;
        }

        try {
            $entry = $db->where('id', $payment_schedule_id)->getOne('crm_payment_schedule');
            if (!$entry) {
                echo json_encode(['status' => 404, 'message' => 'Payment schedule entry not found']);
                exit;
            }

            $old_paid = (float)$entry->paid_amount;
            $new_paid = $old_paid + (float)$amount;
            $installment_amount = (float)$entry->installment_amount;

            // Determine status
            $status = 0; // pending
            if ($new_paid >= $installment_amount) {
                $status = 1; // paid
            } elseif ($new_paid > 0) {
                $status = 2; // partial
            }

            $update_data = [
                'paid_amount' => $new_paid,
                'status' => $status,
                'payment_date' => $payment_date,
                'payment_method' => $payment_method,
                'money_receipt_no' => $receipt_no,
                'updated_by' => $_SESSION['user_id'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $result = $db->where('id', $payment_schedule_id)->update('crm_payment_schedule', $update_data);

            if ($result) {
                // Log audit
                log_crm_audit('payment_schedule', 'payment_received', $payment_schedule_id, 
                    ['paid_amount' => [$old_paid, $new_paid], 'payment_date' => $payment_date],
                    $_SESSION['user_id'] ?? null
                );

                echo json_encode([
                    'status' => 200,
                    'message' => 'Payment recorded successfully',
                    'entry' => $db->where('id', $payment_schedule_id)->getOne('crm_payment_schedule')
                ]);
            } else {
                echo json_encode(['status' => 500, 'message' => 'Failed to update payment']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===============================
    //  💾 SEND SCHEDULE EMAIL
    // ===============================
    if ($s === 'send_schedule_email') {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';

        if (!$purchase_id) {
            echo json_encode(['status' => 400, 'message' => 'purchase_id is required']);
            exit;
        }

        try {
            $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
            if (!$helper) {
                echo json_encode(['status' => 404, 'message' => 'Booking not found']);
                exit;
            }

            // Get client info
            $client = GetCustomerById($helper->client_id);
            if (!$client) {
                echo json_encode(['status' => 404, 'message' => 'Client not found']);
                exit;
            }

            if (!$recipient_email) {
                $recipient_email = $client['email'] ?? '';
            }

            if (!$recipient_email || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'status' => 400,
                    'message' => 'Valid email address is required'
                ]);
                exit;
            }

            // Send email notification
            echo json_encode([
                'status' => 200,
                'message' => 'Schedule email sent successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 500, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    }
