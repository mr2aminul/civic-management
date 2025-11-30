<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); color: white; padding: 30px 20px; text-align: center; }
        .content { padding: 30px 20px; background: #fdf2f8; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .cake { font-size: 64px; text-align: center; margin: 20px 0; }
        .birthday-text { font-size: 24px; font-weight: bold; color: #ec4899; text-align: center; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #fce7f3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎂 Happy Birthday!</h1>
        </div>
        <div class="content">
            <div class="cake">🎂</div>
            <div class="birthday-text">Wishing You a Wonderful Birthday!</div>
            
            <div class="card">
                <p>Dear <strong><?= $client_name ?? 'Valued Customer' ?></strong>,</p>
                
                <p>On this special day, the entire team at Civic Group BD wishes you a very Happy Birthday! 🎉</p>
                
                <p>May this year bring you:</p>
                <ul>
                    <li>🌟 Joy and happiness</li>
                    <li>💫 Success in all your endeavors</li>
                    <li>🏡 Wonderful memories in your property</li>
                    <li>❤️ Good health and prosperity</li>
                </ul>
                
                <p>Thank you for being a valued member of the Civic Group family. We are honored to be part of your journey.</p>
                
                <p>Have a fantastic celebration!</p>
                
                <p><em>Warm wishes,</em><br>
                <strong>The Civic Group BD Team</strong></p>
            </div>
        </div>
        <div class="footer">
            <p><strong>Civic Group BD</strong></p>
            <p>&copy; <?= date('Y') ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
