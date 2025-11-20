<?php
/**
 * Payment Schedule Report Email Template
 * Variables: $client_name, $schedules (array), $total_paid, $remaining_balance
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Schedule Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        .table th { background-color: #f0f0f0; padding: 10px; text-align: left; font-weight: bold; border: 1px solid #ddd; }
        .table td { padding: 10px; border: 1px solid #ddd; }
        .status-paid { color: #28a745; font-weight: bold; }
        .status-pending { color: #ffc107; font-weight: bold; }
        .summary { background-color: white; padding: 15px; margin-top: 15px; border-left: 4px solid #0066cc; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Payment Schedule Report</h2>
        </div>
        <div class="content">
            <p>Dear <strong><?php echo $client_name; ?></strong>,</p>
            
            <p>Here is your complete payment schedule as of today:</p>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Installment</th>
                        <th>Particular</th>
                        <th>Due Date</th>
                        <th align="right">Amount</th>
                        <th align="right">Paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $sch): ?>
                    <tr>
                        <td><?php echo $sch['installment_number']; ?></td>
                        <td><?php echo $sch['particular']; ?></td>
                        <td><?php echo date('d M, Y', strtotime($sch['due_date'])); ?></td>
                        <td align="right">৳<?php echo number_format($sch['installment_amount'], 2); ?></td>
                        <td align="right">৳<?php echo number_format($sch['paid_amount'], 2); ?></td>
                        <td>
                            <?php if ($sch['status'] == 1): ?>
                                <span class="status-paid">✓ Paid</span>
                            <?php else: ?>
                                <span class="status-pending">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="summary">
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd;">
                    <span>Total Paid:</span>
                    <strong>৳<?php echo number_format($total_paid, 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                    <span>Remaining Balance:</span>
                    <strong style="color: #dc3545;">৳<?php echo number_format($remaining_balance, 2); ?></strong>
                </div>
            </div>
            
            <p style="margin-top: 20px;">If you have any questions about your schedule, please contact our office.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Civic Group. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
