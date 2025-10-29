<?php
/**
 * Security Configuration for API
 * Implements CORS, rate limiting, and request validation
 */

// Enable CORS for allowed origins only
$allowed_origins = ['https://civicgroupbd.com', 'https://www.civicgroupbd.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 3600');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'');

// Rate limiting
class RateLimiter {
    private $redis;
    private $prefix = 'rate_limit:';
    private $limit = 100; // requests
    private $window = 3600; // seconds (1 hour)
    
    public function __construct() {
        // Initialize Redis connection if available
        // For now, use file-based rate limiting
    }
    
    public function checkLimit($identifier) {
        $key = $this->prefix . $identifier;
        $file = sys_get_temp_dir() . '/' . md5($key);
        
        $data = @file_get_contents($file);
        $data = $data ? json_decode($data, true) : ['count' => 0, 'reset' => time() + $this->window];
        
        if (time() > $data['reset']) {
            $data = ['count' => 0, 'reset' => time() + $this->window];
        }
        
        $data['count']++;
        file_put_contents($file, json_encode($data));
        
        return $data['count'] <= $this->limit;
    }
}

// Validate API key
function validateApiKey($api_key) {
    // Implement your API key validation logic
    // This is a placeholder
    return !empty($api_key) && strlen($api_key) >= 32;
}

// Sanitize input
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Log security events
function logSecurityEvent($event, $details = []) {
    $log_file = __DIR__ . '/../logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = [
        'timestamp' => $timestamp,
        'event' => $event,
        'ip' => $ip,
        'user_agent' => $user_agent,
        'details' => $details
    ];
    
    @file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND);
}

// Validate request signature
function validateRequestSignature($data, $signature, $secret) {
    $expected_signature = hash_hmac('sha256', $data, $secret);
    return hash_equals($expected_signature, $signature);
}
?>
