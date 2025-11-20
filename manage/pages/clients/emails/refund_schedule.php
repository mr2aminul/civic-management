<?php
/**
 * Refund Schedule Email Template
 * Variables: $client_name, $total_refundable, $deduction_amount, $refund_installments (array)
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Refund Schedule</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; }
        .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        .table th { background-color: #f0f0f0; padding: 10px; text-align: left; font-weight: bold; border: 1px solid #ddd; }
        .table td { padding: 10px; border: 1px solid #ddd; }
        .summary-box { background-color: white; padding: 15px; margin-top: 15px; border-left: 4px solid #dc3545; }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
        .summary-row:last-child { border-bottom: none; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Refund Schedule</h2>
        </div>
        <div class="content">
            <p>Dear <strong><?php echo $client_name; ?></strong>,</p>
            
            <p>We are processing your refund request. Below is your refund schedule:</p>
            
            <div class="warning">
                <strong>Note:</strong> A deduction of <strong>৳<?php echo number_format($deduction_amount, 2); ?></strong> (penalty/administrative fees) has been applied to your total refund.
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Installment</th>
                        <th>Due Date</th>
                        <th align="right">Refund Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($refund_installments as $install): ?>
                    <tr>
                        <td>#<?php echo $install['installment_number']; ?></td>
                        <td><?php echo date('d M, Y', strtotime($install['due_date'])); ?></td>
                        <td align="right">৳<?php echo number_format($install['amount'], 2); ?></td>
                        <td>
                            <?php if ($install['status'] == 'paid'): ?>
                                <span style="color: #28a745; font-weight: bold;">✓ Paid</span>
                            <?php else: ?>
                                <span style="color: #ffc107; font-weight: bold;">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="summary-box">
                <div class="summary-row">
                    <span>Total Refundable Amount:</span>
                    <strong>৳<?php echo number_format($total_refundable, 2); ?></strong>
                </div>
            </div>
            
            <p style="margin-top: 20px;">The refund will be processed according to the schedule above. Please contact our office if you have any questions.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Civic Group. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
