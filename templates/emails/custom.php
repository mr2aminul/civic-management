<?php
// Custom Email Template
// Variables available: $subject, $body, $client_name, $message
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0066cc; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Civic Group BD</h1>
        </div>
        <div class="content">
            <h2><?php echo htmlspecialchars($subject ?? 'Notification'); ?></h2>
            <p>Dear <?php echo htmlspecialchars($client_name ?? 'Customer'); ?>,</p>
            
            <?php 
            $bodyContent = $body ?? $message ?? '';
            // If body doesn't contain HTML tags, wrap it in paragraphs and convert line breaks
            if (strip_tags($bodyContent) === $bodyContent) {
                // Plain text - convert to HTML
                $bodyContent = nl2br(htmlspecialchars($bodyContent));
                if (!empty($bodyContent)) {
                    echo '<div style="white-space: pre-wrap;">' . $bodyContent . '</div>';
                }
            } else {
                // Already contains HTML
                echo $bodyContent;
            }
            ?>
            
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Civic Group BD. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
