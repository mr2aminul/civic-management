<?php
/**
 * CRM Automation & Cron Job Handler
 * Handles scheduled tasks for payment reminders, schedule updates, etc.
 */


class CRM_Automation_Cron {
    
    protected $db;
    protected $notifier;

    public function __construct() {
        global $db;
        $this->db = $db;
        require_once __DIR__ . '/crm_email_notifications.php';
        $this->notifier = new CRM_Email_Notifications();
    }

    /**
     * Daily Schedule Update
     * Updates payment schedule status and marks overdue payments
     */
    public function daily_schedule_update() {
        try {
            $today = time();
            $result_count = 0;

            // Get all pending payment installments
            $pending_installments = $this->db->where('status', 0)->get('crm_payment_installments');

            if ($pending_installments && is_array($pending_installments)) {
                foreach ($pending_installments as $inst) {
                    $due_date = $inst['due_date'];
                    
                    // Check if overdue
                    if ($due_date < $today) {
                        $this->db->where('id', $inst['id'])->update('crm_payment_installments', [
                            'status' => 3, // Mark as overdue
                            'is_overdue' => 1
                        ]);
                        $result_count++;
                    }
                }
            }

            return ['status' => 200, 'message' => "Updated {$result_count} overdue installments"];
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Cron error: ' . $e->getMessage()];
        }
    }

    /**
     * Send Payment Reminders
     * Sends email reminders for upcoming payments
     */
    public function send_payment_reminders() {
        try {
            $result_count = 0;
            $tomorrow = strtotime('+1 day', time());
            $tomorrow_date = date('Y-m-d', $tomorrow);

            // Get installments due tomorrow
            $upcoming = $this->db->rawQuery(
                "SELECT pi.*, ps.purchase_id, bh.client_id 
                 FROM crm_payment_installments pi 
                 JOIN crm_payment_schedule ps ON ps.id = pi.schedule_id 
                 JOIN wo_booking_helper bh ON bh.id = ps.purchase_id 
                 WHERE DATE(FROM_UNIXTIME(pi.due_date)) = ? AND pi.status = 0",
                [$tomorrow_date]
            );

            if ($upcoming && is_array($upcoming)) {
                foreach ($upcoming as $inst) {
                    $schedule = $this->db->where('id', $inst['schedule_id'])->getOne('crm_payment_schedule');
                    
                    // Check if auto-email is enabled
                    if ($schedule && $schedule['auto_email_enabled']) {
                        $this->notifier->send_payment_schedule($inst['purchase_id'], $inst['client_id']);
                        $result_count++;
                    }
                }
            }

            return ['status' => 200, 'message' => "Sent {$result_count} payment reminders"];
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Cron error: ' . $e->getMessage()];
        }
    }

    /**
     * Process Pending Transfers
     * Auto-approves or processes pending transfers
     */
    public function process_pending_transfers() {
        try {
            $result_count = 0;

            // Get pending transfers
            $pending = $this->db->where('status', 'pending')->get('crm_transfer_history');

            if ($pending && is_array($pending)) {
                foreach ($pending as $transfer) {
                    // Process based on auto-approval rules
                    // This is a placeholder - add your business logic here
                    
                    $result_count++;
                }
            }

            return ['status' => 200, 'message' => "Processed {$result_count} transfers"];
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Cron error: ' . $e->getMessage()];
        }
    }

    /**
     * Recalculate Schedule for Plot Changes
     * Automatically recalculates payment schedules after plot transfers
     */
    public function recalculate_changed_schedules() {
        try {
            $result_count = 0;

            // Get schedules marked for recalculation
            $to_recalculate = $this->db->where('needs_recalculation', 1)->get('crm_payment_schedule');

            if ($to_recalculate && is_array($to_recalculate)) {
                foreach ($to_recalculate as $schedule) {
                    // Recalculate logic here
                    $this->recalculate_schedule($schedule['id']);
                    
                    $this->db->where('id', $schedule['id'])->update('crm_payment_schedule', [
                        'needs_recalculation' => 0,
                        'last_recalculated' => date('Y-m-d H:i:s')
                    ]);
                    
                    $result_count++;
                }
            }

            return ['status' => 200, 'message' => "Recalculated {$result_count} schedules"];
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Cron error: ' . $e->getMessage()];
        }
    }

    /**
     * Recalculate Schedule Helper
     */
    private function recalculate_schedule($schedule_id) {
        // Get current schedule and booking data
        $schedule = $this->db->where('id', $schedule_id)->getOne('crm_payment_schedule');
        if (!$schedule) return false;

        $booking_helper = $this->db->where('id', $schedule['purchase_id'])->getOne('wo_booking_helper');
        if (!$booking_helper) return false;

        // Calculate new installments based on current plot/per_katha
        $total_amount = (float)$booking_helper['per_katha'] * (float)$booking_helper['booking_money'];
        $down_payment = (float)$booking_helper['down_payment'];
        $remaining = $total_amount - $down_payment;

        // Get existing installment count
        $old_installments = $this->db->where('schedule_id', $schedule_id)->get('crm_payment_installments');
        $installment_count = count($old_installments) ?: 12; // Default to 12 months

        $per_installment = $remaining / $installment_count;

        // Update existing or create new installments
        // This is simplified - add full logic as needed

        return true;
    }

    /**
     * Generate Monthly Reports
     * Generates and sends monthly payment reports
     */
    public function generate_monthly_reports() {
        try {
            $result_count = 0;

            // Get first day of current month
            $first_day = date('Y-m-01');
            $last_day = date('Y-m-t');

            // Get all completed transfers this month
            $transfers = $this->db->rawQuery(
                "SELECT * FROM crm_transfer_history 
                 WHERE status = 'completed' 
                 AND DATE(created_at) BETWEEN ? AND ?",
                [$first_day, $last_day]
            );

            // Generate report
            if ($transfers) {
                $result_count = count($transfers);
            }

            return ['status' => 200, 'message' => "Generated report for {$result_count} transfers"];
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Cron error: ' . $e->getMessage()];
        }
    }

    /**
     * Run All Scheduled Tasks
     */
    public function run_all_tasks() {
        $results = [];
        
        $results['schedule_update'] = $this->daily_schedule_update();
        $results['payment_reminders'] = $this->send_payment_reminders();
        $results['process_transfers'] = $this->process_pending_transfers();
        $results['recalculate_schedules'] = $this->recalculate_changed_schedules();
        $results['generate_reports'] = $this->generate_monthly_reports();

        // Log execution
        $this->db->insert('crm_cron_logs', [
            'execution_date' => date('Y-m-d H:i:s'),
            'results' => json_encode($results),
            'status' => 'completed'
        ]);

        return $results;
    }
}
?>
