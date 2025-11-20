<?php
/**
 * Birthday Wish Email Template
 * Variables: $recipient_name, $client_name, $company_name
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Happy Birthday!</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { font-size: 48px; margin: 0; }
        .header p { font-size: 18px; margin: 10px 0 0 0; }
        .content { background-color: #f8f9fa; padding: 30px 20px; text-align: center; }
        .cake { font-size: 60px; margin: 20px 0; }
        .message { background-color: white; padding: 20px; margin: 15px 0; border-radius: 8px; }
        .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Happy Birthday! 🎉</h1>
            <p>A special day for a special person</p>
        </div>
        <div class="content">
            <div class="cake">🎂</div>
            
            <div class="message">
                <p>Dear <strong><?php echo $recipient_name; ?></strong>,</p>
                
                <p>On this special day, we wish you a very <strong>Happy Birthday!</strong></p>
                
                <p>May your day be filled with joy, laughter, and wonderful moments with your loved ones. We hope this year brings you health, happiness, and success in all your endeavors.</p>
                
                <p>Thank you for being a valued client of <?php echo $company_name; ?>. We appreciate your trust and look forward to serving you for many more years to come.</p>
                
                <p style="font-size: 18px; color: #667eea;"><strong>Enjoy your special day! 🎈</strong></p>
            </div>
            
            <p style="margin-top: 20px; color: #6c757d;">Warm regards,<br>The <?php echo $company_name; ?> Family</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Civic Group. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
