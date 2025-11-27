<?php
/**
 * Generate Schedule Preview Endpoint
 * Creates a preview of payment schedule based on parameters
 * Mode 1 = Full Payment, Mode 2 = Installment
 * Note: getOrdinalSuffix() function is defined globally elsewhere
 */

header('Content-Type: application/json; charset=utf-8');

if ($s === 'generate_schedule_preview') {
    $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;
    $booking_money = isset($_POST['booking_money']) ? floatval($_POST['booking_money']) : 0;
    $down_payment = isset($_POST['down_payment']) ? floatval($_POST['down_payment']) : 0;
    $payment_mode = isset($_POST['payment_mode']) ? $_POST['payment_mode'] : '2'; // 1=Full, 2=Installment
    $installments = isset($_POST['installments']) ? intval($_POST['installments']) : 60;
    $monthly_amount = isset($_POST['monthly_amount']) ? floatval($_POST['monthly_amount']) : 0;
    $adjustment_type = isset($_POST['adjustment_type']) ? $_POST['adjustment_type'] : 'year_end';
    $yearly_adjustment = isset($_POST['yearly_adjustment']) ? floatval($_POST['yearly_adjustment']) : 0;
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $start_option = isset($_POST['start_option']) ? $_POST['start_option'] : 'exact';
    $interest_rate = isset($_POST['interest_rate']) ? floatval($_POST['interest_rate']) : 3;
    
    if ($total_price <= 0) {
        echo json_encode(['status' => 400, 'message' => 'Total price must be greater than 0']);
        exit;
    }
    
    try {
        $schedule = [];
        $installment_number = 1;
        
        // Calculate remaining after booking and down payment
        $remaining = $total_price - $booking_money - $down_payment;
        
        if ($remaining < 0) {
            echo json_encode(['status' => 400, 'message' => 'Booking + Down payment cannot exceed total price']);
            exit;
        }
        
        // Add Booking Money if > 0
        if ($booking_money > 0) {
            $schedule[] = [
                'installment_number' => $installment_number++,
                'particular' => 'Booking Money',
                'due_date' => $start_date,
                'installment_amount' => $booking_money,
                'installment_type' => 'booking',
                'paid_amount' => 0,
                'status' => 0
            ];
        }
        
        // Add Down Payment if > 0
        if ($down_payment > 0) {
            $down_date = date('Y-m-d', strtotime($start_date . ' +15 days'));
            $schedule[] = [
                'installment_number' => $installment_number++,
                'particular' => 'Down Payment',
                'due_date' => $down_date,
                'installment_amount' => $down_payment,
                'installment_type' => 'down',
                'paid_amount' => 0,
                'status' => 0
            ];
        }
        
        // Handle payment modes
        if ($payment_mode == '1') {
            // Full Payment mode - single installment for remaining
            if ($remaining > 0) {
                $full_payment_date = date('Y-m-d', strtotime($start_date . ' +30 days'));
                $schedule[] = [
                    'installment_number' => $installment_number++,
                    'particular' => 'Full Payment',
                    'due_date' => $full_payment_date,
                    'installment_amount' => $remaining,
                    'installment_type' => 'installment',
                    'paid_amount' => 0,
                    'status' => 0
                ];
            }
        } else {
            // Installment mode (mode = 2)
            if ($remaining > 0) {
                // Determine monthly installment amount
                if ($monthly_amount > 0) {
                    $base_installment = $monthly_amount;
                } else {
                    // Auto-calculate: divide remaining by number of installments
                    $base_installment = $remaining / $installments;
                }
                
                // Generate installments
                $total_distributed = 0;
                $current_date = new DateTime($start_date);
                
                for ($i = 0; $i < $installments; $i++) {
                    $is_last = ($i == $installments - 1);
                    
                    // Calculate installment amount
                    if ($is_last) {
                        // Last installment gets the remainder
                        $amount = $remaining - $total_distributed;
                    } else {
                        $amount = $base_installment;
                        
                        // Apply yearly adjustment based on adjustment type
                        $year_num = floor($i / 12);
                        $month_in_year = $i % 12;
                        
                        switch ($adjustment_type) {
                            case 'year_start':
                                if ($month_in_year === 0 && $year_num > 0) {
                                    $amount += $yearly_adjustment;
                                }
                                break;
                            case 'year_middle':
                                if ($month_in_year === 6 && $year_num >= 0) {
                                    $amount += $yearly_adjustment;
                                }
                                break;
                            case 'year_end':
                                if ($month_in_year === 11 && $year_num >= 0) {
                                    $amount += $yearly_adjustment;
                                }
                                break;
                            case 'year_quarterly':
                                if ($month_in_year % 3 === 0 && $i > 0) {
                                    $amount += ($yearly_adjustment / 4); // Distribute quarterly
                                }
                                break;
                            case 'custom':
                                // No automatic yearly adjustment
                                break;
                        }
                    }
                    
                    $total_distributed += $amount;
                    
                    // Calculate due date based on start_option
                    $due_date = clone $current_date;
                    $due_date->modify('+1 month');
                    
                    switch ($start_option) {
                        case 'start':
                            $due_date->modify('first day of this month');
                            break;
                        case 'middle':
                            $due_date->modify('first day of this month');
                            $due_date->modify('+14 days');
                            break;
                        case 'end':
                            $due_date->modify('last day of this month');
                            break;
                        case 'exact':
                        default:
                            // Keep the day as is
                            break;
                    }
                    
                    // Generate ordinal suffix (using global function)
                    $ordinal = getOrdinalSuffix($i + 1);
                    
                    $schedule[] = [
                        'installment_number' => $installment_number++,
                        'particular' => $ordinal . ' Installment',
                        'due_date' => $due_date->format('Y-m-d'),
                        'installment_amount' => round($amount, 2),
                        'installment_type' => 'installment',
                        'paid_amount' => 0,
                        'status' => 0
                    ];
                    
                    $current_date = $due_date;
                }
            }
        }
        
        echo json_encode([
            'status' => 200,
            'schedule' => $schedule,
            'summary' => [
                'total_installments' => count($schedule),
                'total_amount' => $total_price,
                'monthly_payment' => $monthly_amount > 0 ? $monthly_amount : ($remaining > 0 ? $base_installment : 0),
                'remaining_balance' => $remaining,
                'payment_mode' => $payment_mode == '1' ? 'Full Payment' : 'Installment'
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 500, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}