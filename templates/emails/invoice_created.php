<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9fafb; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .amount { font-size: 28px; font-weight: bold; color: #3b82f6; }
        .info-row { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 New Invoice Generated</h1>
        </div>
        <div class="content">
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                <p>A new invoice has been generated for your account.</p>
                
                <div class="amount">৳<?= number_format($amount ?? 0, 2) ?></div>
                
                <div class="info-row">
                    <strong>Invoice Number:</strong> <?= $invoice_number ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>Invoice Date:</strong> <?= date('d M Y', strtotime($invoice_date ?? date('Y-m-d'))) ?>
                </div>
                <div class="info-row">
                    <strong>Due Date:</strong> <?= date('d M Y', strtotime($due_date ?? date('Y-m-d'))) ?>
                </div>
                <div class="info-row">
                    <strong>Description:</strong> <?= $description ?? '-' ?>
                </div>
                <div class="info-row">
                    <strong>File Number:</strong> <?= $file_num ?? '-' ?>
                </div>
                
                <p style="margin-top: 20px;"><strong>📎 Invoice PDF is attached to this email.</strong></p>
                
                <p>Please make payment by the due date to avoid any late fees.</p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
