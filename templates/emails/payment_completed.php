<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9fafb; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .celebrate { font-size: 48px; text-align: center; margin: 20px 0; }
        .congrats { font-size: 24px; font-weight: bold; color: #10b981; text-align: center; margin: 20px 0; }
        .info-box { background: #d1fae5; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Congratulations!</h1>
        </div>
        <div class="content">
            <div class="celebrate">🎊 🎉 🎊</div>
            <div class="congrats">All Payments Completed!</div>
            
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                
                <p>We are delighted to inform you that you have successfully completed all payment obligations for your property!</p>
                
                <div class="info-box">
                    <strong>Property Details:</strong><br>
                    File Number: <?= $file_num ?? '-' ?><br>
                    Plot: <?= $plot ?? '-' ?>, Block: <?= $block ?? '-' ?><br>
                    Project: <?= $project ?? '-' ?>
                </div>
                
                <p><strong>✓ Completion Certificate</strong> is attached to this email.</p>
                
                <p>Your dedication to timely payments is truly appreciated. You are now eligible for:</p>
                <ul>
                    <li>Property registration process</li>
                    <li>Possession handover</li>
                    <li>All ownership documents</li>
                </ul>
                
                <p>Our team will contact you shortly to arrange the next steps for property handover and documentation.</p>
                
                <p>Thank you for choosing Civic Group BD and being a valued member of our community!</p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
