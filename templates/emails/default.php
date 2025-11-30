<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f9fafb; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: #667eea; color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; }
        .card { background: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #e5e7eb; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Civic Group BD</h1>
        </div>
        <div class="content">
            <div class="card">
                <p>Dear Customer,</p>
                <p>This is a notification from Civic Group BD.</p>
                <p><?= $message ?? 'Please contact us for more information.' ?></p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
