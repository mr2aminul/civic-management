<?php
/**
 * Payment Received Email Template
 * Variables: $client_name, $amount_paid, $payment_date, $money_receipt_no, $remaining_balance
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Received</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; }
        .receipt-box { background-color: white; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0; }
        .receipt-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
        .receipt-row:last-child { border-bottom: none; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✓ Payment Received</h2>
        </div>
        <div class="content">
            <p>Dear <strong><?php echo $client_name; ?></strong>,</p>
            
            <p>Thank you for your payment! We have successfully received your payment.</p>
            
            <div class="receipt-box">
                <div class="receipt-row">
                    <span>Receipt Number:</span>
                    <strong><?php echo $money_receipt_no; ?></strong>
                </div>
                <div class="receipt-row">
                    <span>Amount Paid:</span>
                    <strong style="color: #28a745;">৳<?php echo number_format($amount_paid, 2); ?></strong>
                </div>
                <div class="receipt-row">
                    <span>Payment Date:</span>
                    <strong><?php echo date('d M, Y', strtotime($payment_date)); ?></strong>
                </div>
                <div class="receipt-row">
                    <span>Remaining Balance:</span>
                    <strong>৳<?php echo number_format($remaining_balance, 2); ?></strong>
                </div>
            </div>
            
            <p>Your payment has been recorded in our system. Please keep this email for your records.</p>
            
            <p>Thank you for choosing us!</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Civic Group. All rights reserved.</p>
            <p>For support, contact: info@civicgroup.com</p>
        </div>
    </div>
</body>
</html>
