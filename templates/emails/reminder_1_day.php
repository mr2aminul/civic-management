<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9fafb; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .amount { font-size: 28px; font-weight: bold; color: #dc2626; margin: 10px 0; }
        .due-date { font-size: 18px; color: #f59e0b; font-weight: 600; margin: 10px 0; }
        .info-row { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f3f4f6; }
        .urgent { background: #fee2e2; padding: 15px; border-left: 4px solid #dc2626; margin: 15px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Payment Reminder</h1>
        </div>
        <div class="content">
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                
                <?php 
                $days = $days_until_due ?? 7;
                $urgency = $days <= 1 ? 'URGENT' : ($days <= 3 ? 'Important' : 'Friendly');
                ?>
                
                <p>This is a <strong><?= $urgency ?></strong> reminder that you have a payment due in <strong><?= $days ?> day<?= $days != 1 ? 's' : '' ?></strong>.</p>
                
                <div class="amount">৳<?= number_format($amount ?? 0, 2) ?></div>
                <div class="due-date">Due Date: <?= date('d M Y', strtotime($due_date ?? date('Y-m-d'))) ?></div>
                
                <div class="info-row">
                    <strong>File Number:</strong> <?= $file_num ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>Installment:</strong> <?= $installment_number ?? '-' ?> <?= $particular ?? '' ?>
                </div>
            </div>
            
            <div class="urgent">
                <?php if ($days <= 1): ?>
                    <strong>⚠️ FINAL REMINDER:</strong> This payment is due tomorrow. Please arrange payment immediately to avoid late fees.
                <?php elseif ($days <= 3): ?>
                    <strong>⚠️ URGENT:</strong> Please arrange payment within the next <?= $days ?> days.
                <?php else: ?>
                    <strong>📌 Reminder:</strong> Please ensure payment is made by the due date.
                <?php endif; ?>
            </div>
            
            <p>You can make payment via:</p>
            <ul>
                <li>Bank Transfer</li>
                <li>Cash at our office</li>
                <li>Cheque</li>
                <li>Mobile Banking (bKash/Nagad)</li>
            </ul>
            
            <p>Thank you for your cooperation!</p>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
