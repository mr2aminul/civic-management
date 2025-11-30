<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #fffbeb; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .warning { background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #fef3c7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Payment Reschedule Proposal</h1>
        </div>
        <div class="content">
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                
                <p>We have prepared a payment reschedule proposal for your account (File: <?= $file_num ?? '-' ?>).</p>
                
                <div class="warning">
                    <strong>⚠️ Action Required:</strong> Please review the attached reschedule proposal and let us know if you accept the new terms.
                </div>
                
                <p><strong>New Payment Schedule highlights:</strong></p>
                <ul>
                    <li>Total Installments: <?= $installment_count ?? '-' ?></li>
                    <li>Monthly Installment: ৳<?= number_format($monthly_amount ?? 0, 2) ?></li>
                    <li>Start Date: <?= date('d M Y', strtotime($start_date ?? date('Y-m-d'))) ?></li>
                </ul>
                
                <p><strong>📎 Detailed schedule is attached to this email.</strong></p>
                
                <p>Please contact our office if you have any questions or need to discuss the proposal.</p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
