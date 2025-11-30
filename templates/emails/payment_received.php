<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { padding: 30px 20px; background: #f9fafb; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .amount { font-size: 28px; font-weight: bold; color: #667eea; margin: 10px 0; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .info-label { font-weight: 600; color: #6b7280; }
        .info-value { color: #111827; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; }
        .button:hover { background: #5568d3; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f3f4f6; }
        .highlight { background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; margin: 15px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Payment Received!</h1>
        </div>
        <div class="content">
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                <p>We have successfully received your payment. Thank you for your prompt payment!</p>
                
                <div class="amount">৳<?= number_format($amount ?? 0, 2) ?></div>
                
                <div class="info-row">
                    <span class="info-label">Receipt Number:</span>
                    <span class="info-value"><?= $receipt_number ?? '-' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Date:</span>
                    <span class="info-value"><?= date('d M Y', strtotime($payment_date ?? date('Y-m-d'))) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?= ucfirst($payment_method ?? 'Cash') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">File Number:</span>
                    <span class="info-value"><?= $file_num ?? '-' ?></span>
                </div>
            </div>
            
            <div class="highlight">
                <strong>✓ Your receipt is attached to this email</strong><br>
                Please keep it for your records.
            </div>
            
            <p>If you have any questions about this payment, please don't hesitate to contact us.</p>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>Address: [Your Address]<br>
            Phone: [Your Phone] | Email: [Your Email]</p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
