<?php
/**
 * CRM Email Notification System
 * Handles all email communications for payment schedules, transfers, and refunds
 */


class CRM_Email_Notifications {
    
    protected $db;
    protected $mail;
    protected $settings;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->load_mail_service();
        $this->load_settings();
    }

    /**
     * Load PHPMailer configuration
     */
    private function load_mail_service() {
        require 'assets/libraries/PHPMailer-Master/vendor/autoload.php';
        
        // Check if PHPMailer exists
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->mail = new PHPMailer\PHPMailer\PHPMailer(true);
        } else if (class_exists('PHPMailer')) {
            $this->mail = new PHPMailer();
        }
    }

    /**
     * Load email settings from config
     */
    private function load_settings() {
        $this->settings = [
            'from_email' => defined('MAIL_FROM') ? MAIL_FROM : 'noreply@crm.local',
            'from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'CRM System',
            'smtp_enabled' => defined('SMTP_ENABLED') ? SMTP_ENABLED : false,
            'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : '',
            'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
            'smtp_user' => defined('SMTP_USER') ? SMTP_USER : '',
            'smtp_password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
        ];
    }

    /**
     * Send Payment Schedule Email
     */
    public function send_payment_schedule($purchase_id, $customer_id) {
        try {
            if (!$this->mail) return ['status' => 400, 'message' => 'Mail service not available'];

            // Fetch schedule and customer data
            $schedule = $this->get_payment_schedule($purchase_id);
            $customer = $this->get_customer($customer_id);

            if (!$schedule || !$customer) {
                return ['status' => 404, 'message' => 'Schedule or customer not found'];
            }

            // Generate schedule HTML
            $html_body = $this->generate_schedule_html($schedule, $customer);

            // Configure mail
            $this->configure_mail();
            $this->mail->addAddress($customer['email'], $customer['name']);
            $this->mail->Subject = 'Payment Schedule - ' . $customer['name'];
            $this->mail->msgHTML($html_body);
            $this->mail->AltBody = 'Please view this in an HTML email client';

            // Send
            if ($this->mail->send()) {
                // Log email sent
                $this->log_email_sent($customer_id, $purchase_id, 'payment_schedule', $customer['email']);
                return ['status' => 200, 'message' => 'Payment schedule sent successfully'];
            } else {
                return ['status' => 500, 'message' => 'Failed to send email: ' . $this->mail->ErrorInfo];
            }
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Email error: ' . $e->getMessage()];
        }
    }

    /**
     * Send Transfer Notification
     */
    public function send_transfer_notification($purchase_id, $customer_id, $transfer_type, $transfer_data) {
        try {
            if (!$this->mail) return ['status' => 400, 'message' => 'Mail service not available'];

            $customer = $this->get_customer($customer_id);
            if (!$customer) return ['status' => 404, 'message' => 'Customer not found'];

            $subject = $transfer_type === 'name' ? 'Name Transfer Notification' : 'Plot Transfer Notification';
            $html_body = $this->generate_transfer_html($transfer_type, $transfer_data, $customer);

            $this->configure_mail();
            $this->mail->addAddress($customer['email'], $customer['name']);
            $this->mail->Subject = $subject;
            $this->mail->msgHTML($html_body);

            if ($this->mail->send()) {
                $this->log_email_sent($customer_id, $purchase_id, 'transfer_' . $transfer_type, $customer['email']);
                return ['status' => 200, 'message' => 'Transfer notification sent'];
            } else {
                return ['status' => 500, 'message' => 'Failed to send email'];
            }
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Email error: ' . $e->getMessage()];
        }
    }

    /**
     * Send Refund Schedule Email
     */
    public function send_refund_schedule($purchase_id, $customer_id) {
        try {
            if (!$this->mail) return ['status' => 400, 'message' => 'Mail service not available'];

            $refund = $this->get_refund_data($purchase_id);
            $customer = $this->get_customer($customer_id);

            if (!$refund || !$customer) {
                return ['status' => 404, 'message' => 'Refund or customer not found'];
            }

            $html_body = $this->generate_refund_html($refund, $customer);

            $this->configure_mail();
            $this->mail->addAddress($customer['email'], $customer['name']);
            $this->mail->Subject = 'Refund Schedule - ' . $customer['name'];
            $this->mail->msgHTML($html_body);

            if ($this->mail->send()) {
                $this->log_email_sent($customer_id, $purchase_id, 'refund_schedule', $customer['email']);
                return ['status' => 200, 'message' => 'Refund schedule sent'];
            } else {
                return ['status' => 500, 'message' => 'Failed to send email'];
            }
        } catch (Exception $e) {
            return ['status' => 500, 'message' => 'Email error: ' . $e->getMessage()];
        }
    }

    /**
     * Generate Payment Schedule HTML
     */
    private function generate_schedule_html($schedule, $customer) {
        $html = '<html><body style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Payment Schedule</h2>';
        $html .= '<p>Dear ' . htmlspecialchars($customer['name']) . ',</p>';
        $html .= '<p>Below is your payment schedule:</p>';
        
        $html .= '<table style="width:100%; border-collapse: collapse; margin: 20px 0;">';
        $html .= '<tr style="background-color: #f0f0f0;">';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">#</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">Due Date</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">Amount</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">Status</th>';
        $html .= '</tr>';

        if (isset($schedule['installments']) && is_array($schedule['installments'])) {
            foreach ($schedule['installments'] as $idx => $inst) {
                $status_text = $inst['status'] == 1 ? 'Paid' : 'Pending';
                $due_date = date('d M Y', $inst['due_date']);
                $amount = number_format($inst['amount'], 2);

                $html .= '<tr>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . ($idx + 1) . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $due_date . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">৳' . $amount . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $status_text . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</table>';
        $html .= '<p>If you have any questions, please contact us.</p>';
        $html .= '<p>Best regards,<br>CRM Team</p>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Generate Transfer Notification HTML
     */
    private function generate_transfer_html($transfer_type, $transfer_data, $customer) {
        $html = '<html><body style="font-family: Arial, sans-serif;">';
        $html .= '<h2>' . ($transfer_type === 'name' ? 'Name Transfer' : 'Plot Transfer') . ' Notification</h2>';
        $html .= '<p>Dear ' . htmlspecialchars($customer['name']) . ',</p>';

        if ($transfer_type === 'name') {
            $html .= '<p>This is to inform you that a name transfer has been initiated for your property.</p>';
            $html .= '<p><strong>New Name:</strong> ' . htmlspecialchars($transfer_data['new_name']) . '</p>';
            $html .= '<p><strong>Reason:</strong> ' . htmlspecialchars($transfer_data['reason']) . '</p>';
            $html .= '<p><strong>Transfer Date:</strong> ' . $transfer_data['date'] . '</p>';
        } else {
            $html .= '<p>This is to inform you that your plot has been transferred.</p>';
            $html .= '<p><strong>Current Plot:</strong> ' . htmlspecialchars($transfer_data['current_plot']) . '</p>';
            $html .= '<p><strong>New Plot:</strong> ' . htmlspecialchars($transfer_data['new_plot']) . '</p>';
            $html .= '<p><strong>New Per Katha Rate:</strong> ৳' . number_format($transfer_data['new_per_katha'], 2) . '</p>';
            $html .= '<p><strong>Reason:</strong> ' . htmlspecialchars($transfer_data['reason']) . '</p>';
        }

        $html .= '<p>Please note that your payment schedule may be adjusted based on these changes.</p>';
        $html .= '<p>Best regards,<br>CRM Team</p>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Generate Refund Schedule HTML
     */
    private function generate_refund_html($refund, $customer) {
        $html = '<html><body style="font-family: Arial, sans-serif;">';
        $html .= '<h2>Refund Schedule</h2>';
        $html .= '<p>Dear ' . htmlspecialchars($customer['name']) . ',</p>';
        $html .= '<p>Below is your refund schedule:</p>';

        $html .= '<table style="width:100%; border-collapse: collapse; margin: 20px 0;">';
        $html .= '<tr style="background-color: #f0f0f0;">';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">#</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">Date</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">Amount</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 8px;">Balance</th>';
        $html .= '</tr>';

        if (isset($refund['installments']) && is_array($refund['installments'])) {
            foreach ($refund['installments'] as $idx => $inst) {
                $refund_date = date('d M Y', $inst['date']);
                $amount = number_format($inst['amount'], 2);
                $balance = number_format($inst['balance'], 2);

                $html .= '<tr>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . ($idx + 1) . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $refund_date . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">৳' . $amount . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">৳' . $balance . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</table>';
        $html .= '<p>Best regards,<br>CRM Team</p>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Configure Mail Service
     */
    private function configure_mail() {
        if ($this->settings['smtp_enabled']) {
            $this->mail->isSMTP();
            $this->mail->Host = $this->settings['smtp_host'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->settings['smtp_user'];
            $this->mail->Password = $this->settings['smtp_password'];
            $this->mail->SMTPSecure = 'tls';
            $this->mail->Port = $this->settings['smtp_port'];
        }
        
        $this->mail->setFrom($this->settings['from_email'], $this->settings['from_name']);
    }

    /**
     * Get Payment Schedule Data
     */
    private function get_payment_schedule($purchase_id) {
        $schedule = $this->db->where('purchase_id', $purchase_id)->getOne('crm_payment_schedule');
        if (!$schedule) return null;

        $installments = $this->db->where('schedule_id', $schedule['id'])->get('crm_payment_installments');
        $schedule['installments'] = $installments ?: [];

        return $schedule;
    }

    /**
     * Get Refund Data
     */
    private function get_refund_data($purchase_id) {
        $refund = $this->db->where('purchase_id', $purchase_id)->getOne('crm_refund_schedule');
        if (!$refund) return null;

        $installments = $this->db->where('refund_schedule_id', $refund['id'])->get('crm_refund_installments');
        $refund['installments'] = $installments ?: [];

        return $refund;
    }

    /**
     * Get Customer Data
     */
    private function get_customer($customer_id) {
        return $this->db->where('id', $customer_id)->getOne('crm_customers');
    }

    /**
     * Log Email Sent
     */
    private function log_email_sent($customer_id, $purchase_id, $email_type, $recipient_email) {
        $this->db->insert('crm_email_logs', [
            'customer_id' => $customer_id,
            'purchase_id' => $purchase_id,
            'email_type' => $email_type,
            'recipient_email' => $recipient_email,
            'sent_date' => date('Y-m-d H:i:s'),
            'status' => 'sent'
        ]);
    }
}

// Helper function
function send_crm_email($type, $customer_id, $purchase_id, $data = []) {
    $notifier = new CRM_Email_Notifications();
    
    switch ($type) {
        case 'payment_schedule':
            return $notifier->send_payment_schedule($purchase_id, $customer_id);
        case 'transfer':
            return $notifier->send_transfer_notification($purchase_id, $customer_id, $data['transfer_type'], $data['transfer_data']);
        case 'refund':
            return $notifier->send_refund_schedule($purchase_id, $customer_id);
        default:
            return ['status' => 400, 'message' => 'Unknown email type'];
    }
}
?>
