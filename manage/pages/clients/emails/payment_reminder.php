<?php
/**
 * Payment Reminder Email Template
 * Variables: $client_name, $amount_due, $due_date, $schedule_id, $payment_method
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; }
        .amount { font-size: 24px; color: #dc3545; font-weight: bold; }
        .button { display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Payment Reminder</h2>
        </div>
        <div class="content">
            <p>Dear <strong><?php echo $client_name; ?></strong>,</p>
            
            <p>This is a friendly reminder that your payment is due on <strong><?php echo date('d M, Y', strtotime($due_date)); ?></strong>.</p>
            
            <p style="text-align: center;">
                <span class="amount">৳<?php echo number_format($amount_due, 2); ?></span>
            </p>
            
            <p>Please arrange to make the payment through any of the following methods:</p>
            <ul>
                <li>Bank Transfer</li>
                <li>Cheque</li>
                <li>Cash</li>
                <li>Online Payment</li>
            </ul>
            
            <p style="text-align: center;">
                <a href="https://yoursite.com/payment/<?php echo $schedule_id; ?>" class="button">Make Payment Online</a>
            </p>
            
            <p>If you have already made this payment, please disregard this reminder.</p>
            
            <p>Thank you for your business!</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Civic Group. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
