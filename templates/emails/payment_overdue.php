<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #fef2f2; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .amount { font-size: 32px; font-weight: bold; color: #dc2626; margin: 15px 0; }
        .urgent { background: #fee2e2; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; border-radius: 4px; }
        .info-row { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .button { display: inline-block; background: #dc2626; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #fee2e2; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ PAYMENT OVERDUE</h1>
        </div>
        <div class="content">
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                
                <div class="urgent">
                    <strong style="font-size: 18px; color: #dc2626;">⚠️ URGENT: Your payment is OVERDUE</strong>
                </div>
                
                <p>This is an urgent notice that you have an overdue payment for your property purchase (File: <strong><?= $file_num ?? '-' ?></strong>).</p>
                
                <div class="amount">৳<?= number_format($amount ?? 0, 2) ?></div>
                <p style="color: #dc2626; font-weight: bold; font-size: 16px;">
                    Overdue by <?= $days_overdue ?? 0 ?> days
                </p>
                
                <div class="info-row">
                    <strong>Original Due Date:</strong> <?= date('d M Y', strtotime($due_date ?? date('Y-m-d'))) ?>
                </div>
                <div class="info-row">
                    <strong>Amount Due:</strong> ৳<?= number_format($amount ?? 0, 2) ?>
                </div>
                
                <div style="background: #fef3c7; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>⚠️ IMPORTANT:</strong>
                    <ul style="margin: 10px 0;">
                        <li>Late payment fees may apply</li>
                        <li>This may affect your payment schedule</li>
                        <li>Continued non-payment may result in additional charges</li>
                    </ul>
                </div>
                
                <p><strong>Please arrange immediate payment to avoid further complications.</strong></p>
                
                <p>If you are facing any difficulties, please contact our office immediately to discuss payment arrangements.</p>
                
                <p><strong>Contact Information:</strong><br>
                Phone: [Office Phone]<br>
                Email: [Office Email]<br>
                Office Hours: [Office Hours]</p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
