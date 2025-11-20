<?php
/**
 * Nominee Notification Email Template
 * Variables: $nominee_name, $client_name, $relationship, $share_percent, $message
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nominee Notification</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; }
        .info-box { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #17a2b8; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Important Notification</h2>
        </div>
        <div class="content">
            <p>Dear <strong><?php echo $nominee_name; ?></strong>,</p>
            
            <p>This is to inform you that you have been designated as a nominee in our records.</p>
            
            <div class="info-box">
                <strong>Nominee Information:</strong>
                <div style="padding: 10px 0;">
                    <div><strong>Client Name:</strong> <?php echo $client_name; ?></div>
                    <div><strong>Relationship:</strong> <?php echo $relationship; ?></div>
                    <div><strong>Share Percentage:</strong> <?php echo $share_percent; ?>%</div>
                </div>
            </div>
            
            <p><?php echo nl2br($message); ?></p>
            
            <p style="margin-top: 20px;">If you have any questions or need to update your information, please contact our office immediately.</p>
            
            <p>Thank you.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Civic Group. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
