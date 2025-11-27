<?php
/**
 * Client Analytics & Metrics Module
 * Provides advanced analytics for client dashboard
 */

header('Content-Type: application/json; charset=utf-8');

// Get Client Metrics
if ($s === 'get_client_metrics') {
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : (isset($_POST['client_id']) ? intval($_POST['client_id']) : 0);
    
    if (!$client_id) {
        echo json_encode(['status' => 400, 'message' => 'Client ID required']);
        exit;
    }

    try {
        $metrics = [];
        
        // Get all purchases for this client
        $db->where('client_id', $client_id);
        $db->where('status', '4', '!='); // Exclude cancelled
        $purchases = $db->get('wo_booking_helper');
        
        $total_amount = 0;
        $total_paid = 0;
        $total_katha = 0;
        $active_purchases = 0;
        $project_distribution = [];
        
        foreach ($purchases as $p) {
            $booking = $db->where('id', $p->booking_id)->getOne('wo_booking');
            if ($booking) {
                $per_katha = floatval($p->per_katha ?? 0);
                $katha = floatval($booking->katha ?? 0);
                $amount = $per_katha * $katha;
                
                $total_amount += $amount;
                $total_katha += $katha;
                $active_purchases++;
                
                // Project distribution
                $proj = $booking->project ?? 'unknown';
                if (!isset($project_distribution[$proj])) {
                    $project_distribution[$proj] = 0;
                }
                $project_distribution[$proj]++;
                
                // Calculate paid from schedule
                if (!empty($p->installment)) {
                    $schedule = json_decode($p->installment, true);
                    if (!is_array($schedule)) $schedule = [];
                    foreach ($schedule as $item) {
                        $total_paid += floatval($item['paid_amount'] ?? 0);
                    }
                }
            }
        }
        
        // Payment Health Score (0-100)
        $health_score = 0;
        if ($total_amount > 0) {
            $paid_ratio = ($total_paid / $total_amount) * 60; // 60% weight for payment progress
            
            // Check overdue count
            $overdue_count = 0;
            $total_installments = 0;
            foreach ($purchases as $p) {
                if (!empty($p->installment)) {
                    $schedule = json_decode($p->installment, true);
                    if (!is_array($schedule)) $schedule = [];
                    foreach ($schedule as $item) {
                        $due_amt = floatval($item['installment_amount'] ?? 0);
                        $paid_amt = floatval($item['paid_amount'] ?? 0);
                        if ($paid_amt < $due_amt) {
                            $total_installments++;
                            $due_date = $item['date'] ?? '';
                            if ($due_date && strtotime($due_date) < time()) {
                                $overdue_count++;
                            }
                        }
                    }
                }
            }
            
            $overdue_penalty = 0;
            if ($total_installments > 0) {
                $overdue_penalty = ($overdue_count / $total_installments) * 30; // 30% penalty for overdue
            }
            
            $health_score = max(0, min(100, $paid_ratio - $overdue_penalty + 10)); // +10 base score
        }
        
        // Get available credits (use remaining_amount computed column)
        $db->where('client_id', $client_id);
        $db->where('remaining_amount > 0');
        $credits_total = floatval($db->getValue('crm_payment_credits', 'SUM(remaining_amount)') ?? 0);
        
        // Get upcoming payments (next 30 days)
        $upcoming_amount = 0;
        $upcoming_count = 0;
        $date_30_days = date('Y-m-d', strtotime('+30 days'));
        
        foreach ($purchases as $p) {
            if (!empty($p->installment)) {
                $schedule = json_decode($p->installment, true);
                if (!is_array($schedule)) $schedule = [];
                foreach ($schedule as $item) {
                    $due_amt = floatval($item['installment_amount'] ?? 0);
                    $paid_amt = floatval($item['paid_amount'] ?? 0);
                    if ($paid_amt < $due_amt) {
                        $due_date = $item['date'] ?? '';
                        if ($due_date && $due_date >= date('Y-m-d') && $due_date <= $date_30_days) {
                            $upcoming_amount += ($due_amt - $paid_amt);
                            $upcoming_count++;
                        }
                    }
                }
            }
        }
        
        // Get pending actions count
        $pending_actions = 0;
        if ($db->tableExists('crm_pending_changes')) {
            $db->where('status', 'pending');
            $db->join('wo_booking_helper bh', 'bh.id = crm_pending_changes.purchase_id', 'LEFT');
            $db->where('bh.client_id', $client_id);
            $pending_actions = intval($db->getValue('crm_pending_changes', 'COUNT(*)') ?? 0);
        }
        
        // Completion progress
        $completion_percentage = $total_amount > 0 ? round(($total_paid / $total_amount) * 100, 1) : 0;
        
        echo json_encode([
            'status' => 200,
            'metrics' => [
                'health_score' => round($health_score, 1),
                'total_credits' => $credits_total,
                'upcoming_payments' => [
                    'count' => $upcoming_count,
                    'amount' => $upcoming_amount
                ],
                'completion_percentage' => $completion_percentage,
                'active_purchases' => $active_purchases,
                'pending_actions' => $pending_actions,
                'portfolio' => [
                    'total_katha' => $total_katha,
                    'avg_per_katha' => $total_katha > 0 ? round($total_amount / $total_katha, 2) : 0,
                    'project_distribution' => $project_distribution
                ]
            ]
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Get Payment Behavior Analytics
if ($s === 'get_payment_behavior') {
    $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
    
    if (!$client_id) {
        echo json_encode(['status' => 400, 'message' => 'Client ID required']);
        exit;
    }

    try {
        // Get all money receipts for this client
        $db->where('client_id', $client_id);
        $db->orderBy('payment_date', 'DESC');
        $receipts = $db->get('crm_money_receipts', 100); // Last 100 payments
        
        $payment_methods = [];
        $monthly_payments = [];
        $total_days_diff = 0;
        $payment_count = 0;
        
        foreach ($receipts as $receipt) {
            // Payment method distribution
            $method = $receipt->payment_method ?? 'unknown';
            if (!isset($payment_methods[$method])) {
                $payment_methods[$method] = 0;
            }
            $payment_methods[$method]++;
            
            // Monthly distribution
            $month = date('Y-m', strtotime($receipt->payment_date));
            if (!isset($monthly_payments[$month])) {
                $monthly_payments[$month] = 0;
            }
            $monthly_payments[$month] += floatval($receipt->amount ?? 0);
            
            // Calculate average days to payment (if schedule_date exists)
            if (!empty($receipt->schedule_date) && !empty($receipt->payment_date)) {
                $scheduled = strtotime($receipt->schedule_date);
                $paid = strtotime($receipt->payment_date);
                if ($scheduled && $paid) {
                    $days_diff = ($paid - $scheduled) / 86400;
                    $total_days_diff += $days_diff;
                    $payment_count++;
                }
            }
        }
        
        $avg_days_to_payment = $payment_count > 0 ? round($total_days_diff / $payment_count, 1) : 0;
        
        // Get peak payment months
        arsort($monthly_payments);
        $peak_months = array_slice($monthly_payments, 0, 3, true);
        
        echo json_encode([
            'status' => 200,
            'behavior' => [
                'avg_days_to_payment' => $avg_days_to_payment,
                'payment_method_preference' => $payment_methods,
                'peak_payment_months' => $peak_months,
                'monthly_trend' => $monthly_payments
            ]
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Suggest Next Invoice
if ($s === 'suggest_next_invoice') {
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    
    if (!$purchase_id) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID required']);
        exit;
    }

    try {
        $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        
        $schedule = !empty($helper->installment) ? json_decode($helper->installment, true) : [];
        if (!is_array($schedule)) $schedule = [];
        
        // Find next unpaid installment
        $suggestion = null;
        $today = date('Y-m-d');
        
        foreach ($schedule as $idx => $item) {
            $due_amt = floatval($item['installment_amount'] ?? 0);
            $paid_amt = floatval($item['paid_amount'] ?? 0);
            
            if ($paid_amt < $due_amt) {
                $remaining = $due_amt - $paid_amt;
                $due_date = $item['date'] ?? '';
                $is_overdue = $due_date && $due_date < $today;
                
                // Calculate late fee if overdue
                $late_fee = 0;
                if ($is_overdue && $due_date) {
                    $days_overdue = (strtotime($today) - strtotime($due_date)) / 86400;
                    if ($days_overdue > 7) { // Grace period of 7 days
                        // 1% per month after grace period
                        $late_fee = round($remaining * 0.01 * floor($days_overdue / 30), 2);
                    }
                }
                
                $suggestion = [
                    'index' => $idx,
                    'type' => $item['type'] ?? $item['particular'] ?? 'installment',
                    'amount' => $remaining,
                    'due_date' => $due_date,
                    'is_overdue' => $is_overdue,
                    'days_overdue' => $is_overdue ? floor((strtotime($today) - strtotime($due_date)) / 86400) : 0,
                    'late_fee' => $late_fee,
                    'suggested_amount' => $remaining + $late_fee,
                    'particular' => $item['particular'] ?? 'Installment #' . ($idx + 1)
                ];
                break;
            }
        }
        
        if (!$suggestion) {
            echo json_encode(['status' => 200, 'suggestion' => null, 'message' => 'All installments paid']);
            exit;
        }
        
        echo json_encode(['status' => 200, 'suggestion' => $suggestion]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Calculate Late Fees
if ($s === 'calculate_late_fees') {
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    
    if (!$purchase_id || !$amount) {
        echo json_encode(['status' => 400, 'message' => 'Purchase ID and amount required']);
        exit;
    }

    try {
        $helper = $db->where('id', $purchase_id)->getOne('wo_booking_helper');
        if (!$helper) {
            echo json_encode(['status' => 404, 'message' => 'Purchase not found']);
            exit;
        }
        
        $schedule = !empty($helper->installment) ? json_decode($helper->installment, true) : [];
        if (!is_array($schedule)) $schedule = [];
        $today = date('Y-m-d');
        $total_late_fee = 0;
        $overdue_items = [];
        
        foreach ($schedule as $item) {
            $due_amt = floatval($item['installment_amount'] ?? 0);
            $paid_amt = floatval($item['paid_amount'] ?? 0);
            $due_date = $item['date'] ?? '';
            
            if ($paid_amt < $due_amt && $due_date && $due_date < $today) {
                $days_overdue = floor((strtotime($today) - strtotime($due_date)) / 86400);
                if ($days_overdue > 7) {
                    $remaining = $due_amt - $paid_amt;
                    $late_fee = round($remaining * 0.01 * floor($days_overdue / 30), 2);
                    $total_late_fee += $late_fee;
                    
                    $overdue_items[] = [
                        'particular' => $item['particular'] ?? 'Installment',
                        'due_date' => $due_date,
                        'days_overdue' => $days_overdue,
                        'amount' => $remaining,
                        'late_fee' => $late_fee
                    ];
                }
            }
        }
        
        echo json_encode([
            'status' => 200,
            'total_late_fee' => $total_late_fee,
            'overdue_items' => $overdue_items,
            'suggested_total' => $amount + $total_late_fee
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
