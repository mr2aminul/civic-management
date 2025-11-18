<?php

/*
 * CIVIC PAYMENT SCHEDULE ENDPOINTS
 * Handles payment schedule viewing and export
 * Separated from: need_seperated_by_category.php
 */

// Get purchase details with full schedule
function get_purchase_details() {
    global $db;
    
    if ($_GET['action'] === 'get_purchase_details') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            $purchase = $db->getOne("wo_booking_helper b
                LEFT JOIN wo_booking w ON b.booking_id = w.id
                LEFT JOIN crm_customers c ON b.client_id = c.id",
                "WHERE b.id = $purchase_id");
            
            $schedule = $db->query("SELECT * FROM crm_payment_schedule 
                WHERE purchase_id = $purchase_id 
                ORDER BY installment_number ASC, type ASC");
            
            // Calculate summary
            $total_amount = array_sum(array_column($schedule ?: [], 'installment_amount'));
            $paid_amount = array_sum(array_map(function($item) {
                return $item['status'] == 1 ? $item['installment_amount'] : 0;
            }, $schedule ?: []));
            $pending_amount = $total_amount - $paid_amount;
            
            echo json_encode([
                'status' => 'success',
                'purchase' => $purchase,
                'schedule' => $schedule ?: [],
                'summary' => [
                    'total_amount' => $total_amount,
                    'paid_amount' => $paid_amount,
                    'pending_amount' => $pending_amount,
                    'completion_percentage' => $total_amount > 0 ? round(($paid_amount / $total_amount) * 100, 2) : 0
                ]
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

// Export payment schedule as CSV
function export_payment_schedule() {
    global $db;
    
    if ($_GET['action'] === 'export_payment_schedule') {
        $purchase_id = intval($_GET['purchase_id'] ?? 0);
        
        if (!$purchase_id) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase ID required']);
            exit;
        }
        
        try {
            $purchase = $db->getOne("wo_booking_helper b
                LEFT JOIN wo_booking w ON b.booking_id = w.id
                LEFT JOIN crm_customers c ON b.client_id = c.id",
                "WHERE b.id = $purchase_id");
            
            $schedule = $db->query("SELECT * FROM crm_payment_schedule 
                WHERE purchase_id = $purchase_id 
                ORDER BY installment_number ASC, type ASC");
            
            // CSV Headers
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"payment_schedule_$purchase_id.csv\"");
            
            $output = fopen('php://output', 'w');
            
            // Write header row
            fputcsv($output, [
                'Client Name',
                'Plot',
                'Installment #',
                'Particular',
                'Type',
                'Due Date',
                'Amount',
                'Paid Amount',
                'Status',
                'Payment Date',
                'Payment Method',
                'Receipt No'
            ]);
            
            // Write data rows
            foreach ($schedule as $row) {
                fputcsv($output, [
                    $purchase->name,
                    $purchase->plot,
                    $row['installment_number'],
                    $row['particular'],
                    $row['type'],
                    $row['due_date'],
                    $row['installment_amount'],
                    $row['paid_amount'],
                    ['0' => 'Pending', '1' => 'Paid', '2' => 'Partial', '3' => 'Overdue', '4' => 'Cancelled'][$row['status']] ?? 'Unknown',
                    $row['payment_date'],
                    $row['payment_method'],
                    $row['money_receipt_no']
                ]);
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}

?>
