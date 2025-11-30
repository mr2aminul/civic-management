<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f0fdf4; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .checkmark { font-size: 64px; text-align: center; color: #10b981; margin: 20px 0; }
        .amount { font-size: 28px; font-weight: bold; color: #10b981; margin: 15px 0; }
        .success-box { background: #d1fae5; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid: #10b981; }
        .info-row { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #d1fae5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ Invoice Paid in Full</h1>
        </div>
        <div class="content">
            <div class="checkmark">✓</div>
            
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                
                <p>Great news! Your invoice has been paid in full.</p>
                
                <div class="success-box">
                    <strong>✓ Payment Complete</strong><br>
                    This invoice has been fully settled. Thank you!
                </div>
                
                <div class="info-row">
                    <strong>Invoice Number:</strong> <?= $invoice_number ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>Total Amount:</strong> ৳<?= number_format($amount ?? 0, 2) ?>
                </div>
                <div class="info-row">
                    <strong>Paid Amount:</strong> ৳<?= number_format($paid_amount ?? 0, 2) ?>
                </div>
                <div class="info-row">
                    <strong>Payment Date:</strong> <?= date('d M Y', strtotime($payment_date ?? date('Y-m-d'))) ?>
                </div>
                <div class="info-row">
                    <strong>Receipt Number:</strong> <?= $receipt_number ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>File Number:</strong> <?= $file_num ?? '-' ?>
                </div>
                
                <p style="margin-top: 20px;"><strong>📎 Your payment receipt is attached to this email.</strong></p>
                
                <p>Thank you for your timely payment. We appreciate your business!</p>
                
                <?php if (!empty($remaining_installments)): ?>
                <div style="background: #fef3c7; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>📌 Reminder:</strong> You have <?= $remaining_installments ?> remaining installment(s).
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
