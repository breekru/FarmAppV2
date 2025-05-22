<?php
/**
 * Security Configuration and Helper Functions
 * 
 * This file contains security-related configurations and helper functions
 * for the password reset functionality and other security features.
 */

/**
 * Security Configuration Constants
 */
define('PASSWORD_RESET_TOKEN_LIFETIME', 3600); // 1 hour in seconds
define('PASSWORD_RESET_MAX_ATTEMPTS_PER_EMAIL', 3); // Max attempts per email per hour
define('PASSWORD_RESET_MAX_ATTEMPTS_PER_IP', 5); // Max attempts per IP per hour
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_REQUIRE_MIXED_CASE', false);
define('PASSWORD_REQUIRE_NUMBERS', false);
define('PASSWORD_REQUIRE_SYMBOLS', false);

/**
 * Generate a cryptographically secure token
 * 
 * @param int $length Token length in bytes (will be doubled when hex encoded)
 * @return string Hex-encoded token
 */
function generateSecureToken($length = 32) {
    try {
        return bin2hex(random_bytes($length));
    } catch (Exception $e) {
        // Fallback for older PHP versions or systems without good entropy
        error_log('Failed to generate secure token with random_bytes: ' . $e->getMessage());
        return hash('sha256', uniqid(mt_rand(), true) . microtime(true) . $_SERVER['REMOTE_ADDR']);
    }
}

/**
 * Validate password strength
 * 
 * @param string $password
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (PASSWORD_REQUIRE_MIXED_CASE) {
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
    }
    
    if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (PASSWORD_REQUIRE_SYMBOLS && !preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Check rate limiting for password reset requests
 * 
 * @param PDO $db Database connection
 * @param string $email Email address
 * @param string $ip_address IP address
 * @return array ['allowed' => bool, 'reason' => string]
 */
function checkPasswordResetRateLimit($db, $email, $ip_address) {
    try {
        // Check email-based rate limiting
        $email_stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM password_reset_tokens 
            WHERE email = :email 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $email_stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $email_stmt->execute();
        $email_count = $email_stmt->fetch()['count'];
        
        if ($email_count >= PASSWORD_RESET_MAX_ATTEMPTS_PER_EMAIL) {
            return [
                'allowed' => false,
                'reason' => 'Too many password reset requests for this email address. Please try again in an hour.'
            ];
        }
        
        // Note: IP-based rate limiting would require adding an ip_address column to the table
        // For now, we'll just do email-based limiting
        
        return ['allowed' => true, 'reason' => ''];
        
    } catch (Exception $e) {
        error_log('Rate limit check failed: ' . $e->getMessage());
        return ['allowed' => false, 'reason' => 'Unable to process request. Please try again later.'];
    }
}

/**
 * Log security events
 * 
 * @param string $event_type
 * @param string $description
 * @param array $context Additional context data
 */
function logSecurityEvent($event_type, $description, $context = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'context' => $context
    ];
    
    $log_message = json_encode($log_entry);
    
    // Log to file
    $log_dir = dirname(__FILE__) . '/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_dir . '/security.log', $log_message . "\n", FILE_APPEND | LOCK_EX);
    
    // Also log to system log for critical events
    if (in_array($event_type, ['password_reset_abuse', 'invalid_token_access', 'suspicious_activity'])) {
        error_log("SECURITY EVENT: $event_type - $description");
    }
}

/**
 * Sanitize and validate email address
 * 
 * @param string $email
 * @return array ['valid' => bool, 'email' => string, 'error' => string]
 */
function validateEmail($email) {
    $email = trim($email);
    
    if (empty($email)) {
        return ['valid' => false, 'email' => '', 'error' => 'Email address is required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'email' => '', 'error' => 'Please enter a valid email address'];
    }
    
    // Additional checks for suspicious patterns
    if (preg_match('/[<>"\']/', $email)) {
        logSecurityEvent('suspicious_email', 'Email contains suspicious characters', ['email' => $email]);
        return ['valid' => false, 'email' => '', 'error' => 'Invalid email format'];
    }
    
    return ['valid' => true, 'email' => strtolower($email), 'error' => ''];
}

/**
 * Validate token format
 * 
 * @param string $token
 * @return bool
 */
function validateTokenFormat($token) {
    // Token should be 64 hexadecimal characters (32 bytes encoded as hex)
    return preg_match('/^[a-f0-9]{64}$/', $token);
}

/**
 * Get user IP address (handles proxies and load balancers)
 * 
 * @return string
 */
function getUserIPAddress() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Hash sensitive data for logging (partial masking)
 * 
 * @param string $data
 * @return string
 */
function hashForLogging($data) {
    if (strlen($data) <= 4) {
        return str_repeat('*', strlen($data));
    }
    
    return substr($data, 0, 2) . str_repeat('*', strlen($data) - 4) . substr($data, -2);
}

/**
 * Check if request is coming from a suspicious source
 * 
 * @return bool
 */
function isSuspiciousRequest() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = getUserIPAddress();
    
    // Check for empty or suspicious user agents
    if (empty($user_agent) || strlen($user_agent) < 10) {
        return true;
    }
    
    // Check for common bot/scanner patterns
    $bot_patterns = [
        '/curl/i',
        '/wget/i',
        '/python/i',
        '/scanner/i',
        '/bot/i',
        '/crawler/i'
    ];
    
    foreach ($bot_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate CSRF token for forms
 * 
 * @return string
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken(16);
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * 
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>