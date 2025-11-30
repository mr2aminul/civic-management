<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9fafb; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .info-box { background: #eef2ff; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .info-row { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Cancellation Confirmed</h1>
        </div>
        <div class="content">
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                
                <p>This is to confirm that your cancellation request has been approved and processed.</p>
                
                <div class="info-box">
                    <strong>Cancellation Details:</strong>
                </div>
                
                <div class="info-row">
                    <strong>File Number:</strong> <?= $file_num ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>Plot:</strong> <?= $plot ?? '-' ?>, Block: <?= $block ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>Project:</strong> <?= $project ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>Cancellation Date:</strong> <?= date('d M Y', strtotime($cancellation_date ?? date('Y-m-d'))) ?>
                </div>
                
                <?php if (!empty($refund_amount) && $refund_amount > 0): ?>
                <div style="background: #d1fae5; padding: 20px; margin: 20px 0; border-radius: 8px;">
                    <h3 style="margin-top: 0; color: #059669;">💰 Refund Information</h3>
                    <div class="info-row">
                        <strong>Total Paid:</strong> ৳<?= number_format($total_paid ?? 0, 2) ?>
                    </div>
                    <div class="info-row">
                        <strong>Cancellation Fee:</strong> ৳<?= number_format($cancellation_fee ?? 0, 2) ?>
                    </div>
                    <div class="info-row">
                        <strong>Refund Amount:</strong> <span style="color: #059669; font-size: 18px; font-weight: bold;">৳<?= number_format($refund_amount, 2) ?></span>
                    </div>
                    <?php if (!empty($refund_installments) && $refund_installments > 1): ?>
                    <div class="info-row">
                        <strong>Refund Schedule:</strong> <?= $refund_installments ?> monthly installments
                    </div>
                    <?php endif; ?>
                </div>
                
                <p><strong>📎 Detailed refund schedule is attached to this email.</strong></p>
                <?php endif; ?>
                
                <p>We regret to see you go and hope we can serve you again in the future.</p>
                
                <p>If you have any questions regarding this cancellation or the refund process, please contact our office.</p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
