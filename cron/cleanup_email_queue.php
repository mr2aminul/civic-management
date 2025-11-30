<?php
/**
 * cleanup_email_queue.php
 * 
 * Cleans up old queued emails that contain raw MIME headers in the body.
 * These emails cause display issues (showing raw headers/HTML) in clients.
 */

declare(strict_types=1);

// --- project root (adjust if this file is placed elsewhere) ---
$project_root = dirname(__DIR__); // expects this file in <project_root>/cron or <project_root>/scripts

// --- autoload and bootstrap (must run before referencing $db or $wo) ---
if (file_exists($project_root . '/vendor/autoload.php')) {
    require_once $project_root . '/vendor/autoload.php';
} elseif (file_exists($project_root . '/assets/libraries/PHPMailer-Master/vendor/autoload.php')) {
    // fallback if PHPMailer bundled inside assets
    require_once $project_root . '/assets/libraries/PHPMailer-Master/vendor/autoload.php';
}

// load your app bootstrap (this should define $db and $wo)
if (file_exists($project_root . '/assets/init.php')) {
    require_once $project_root . '/assets/init.php';
} else {
    die("Missing assets/init.php at {$project_root}/assets/init.php\n");
}

global $db;

if (!isset($db)) {
    die("Error: Database connection not available.\n");
}

echo "Starting email queue cleanup...\n";

// Fetch queued emails
$db->where('status', 'queued');
$emails = $db->get('crm_email_queue');

$count = 0;

foreach ($emails as $e) {
    // Check for MIME headers in template_variables or metadata
    $raw_content = '';
    
    // Check template_variables
    if (!empty($e->template_variables)) {
        $raw_content .= $e->template_variables;
    }
    
    // Check metadata
    if (!empty($e->metadata)) {
        $raw_content .= $e->metadata;
    }

    // Check if body/message inside JSON contains headers
    // Common headers that indicate raw MIME dump
    if (
        strpos($raw_content, 'Content-Type:') !== false || 
        strpos($raw_content, 'MIME-Version:') !== false ||
        strpos($raw_content, 'boundary=') !== false
    ) {
        $db->where('id', $e->id);
        $db->update('crm_email_queue', [
            'status' => 'failed',
            'failure_reason' => 'Cleanup: Contains raw MIME headers'
        ]);
        echo "Marked Email ID {$e->id} as failed (contained raw MIME headers).\n";
        $count++;
    }
}

echo "Cleanup complete. {$count} emails marked as failed.\n";
