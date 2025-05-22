<?php
/**
 * Password Reset Token Cleanup Script
 * 
 * This script should be run periodically (via cron job) to clean up expired
 * and used password reset tokens from the database.
 * 
 * Usage: php cleanup_tokens.php
 * 
 * Recommended cron job (run daily at 2 AM):
 * 0 2 * * * /usr/bin/php /path/to/your/farmapp/cleanup_tokens.php
 */

// Include the configuration file
require_once 'config.php';

// Get database connection
try {
    $db = getDbConnection();
    
    // Clean up expired tokens (older than 24 hours)
    $cleanup_expired = $db->prepare("
        DELETE FROM password_reset_tokens 
        WHERE expires_at < NOW()
    ");
    
    // Clean up used tokens (older than 24 hours)
    $cleanup_used = $db->prepare("
        DELETE FROM password_reset_tokens 
        WHERE used = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    
    // Execute cleanup operations
    $expired_result = $cleanup_expired->execute();
    $expired_count = $cleanup_expired->rowCount();
    
    $used_result = $cleanup_used->execute();
    $used_count = $cleanup_used->rowCount();
    
    // Log results
    $total_cleaned = $expired_count + $used_count;
    $log_message = date('Y-m-d H:i:s') . " - Token cleanup completed. Expired: $expired_count, Used: $used_count, Total: $total_cleaned";
    
    // Log to file (create logs directory if it doesn't exist)
    $log_dir = dirname(__FILE__) . '/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_dir . '/token_cleanup.log', $log_message . "\n", FILE_APPEND | LOCK_EX);
    
    // Also log to system log
    error_log($log_message);
    
    // If running from command line, output results
    if (php_sapi_name() === 'cli') {
        echo $log_message . "\n";
    }
    
} catch (Exception $e) {
    $error_message = date('Y-m-d H:i:s') . " - Token cleanup failed: " . $e->getMessage();
    error_log($error_message);
    
    if (php_sapi_name() === 'cli') {
        echo $error_message . "\n";
    }
}
?>