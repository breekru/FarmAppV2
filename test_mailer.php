<?php
/**
 * PHPMailer Test Script
 * 
 * Run this script to test if your PHPMailer configuration is working
 * Delete this file after testing for security
 */

// Include configuration
require_once 'email_config.php';

// Include PHPMailer
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set content type to HTML for better error display
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHPMailer Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>PHPMailer Configuration Test</h1>
    
    <?php
    echo "<div class='info'><strong>Testing Configuration:</strong><br>";
    echo "SMTP Host: " . SMTP_HOST . "<br>";
    echo "SMTP Username: " . SMTP_USERNAME . "<br>";
    echo "SMTP Port: " . SMTP_PORT . "<br>";
    echo "SMTP Security: " . SMTP_SECURE . "<br>";
    echo "From Email: " . FROM_EMAIL . "</div>";
    
    // Test email - CHANGE THIS TO YOUR EMAIL
    $test_email = 'your-email@gmail.com';  // ← CHANGE THIS TO YOUR EMAIL ADDRESS
    
    try {
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_SECURE === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Enable debug output if configured
        if (SMTP_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        
        // Email content
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($test_email, 'Test User');
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME . ' Support');
        
        $mail->isHTML(true);
        $mail->Subject = 'FarmApp PHPMailer Test - ' . date('Y-m-d H:i:s');
        $mail->Body    = '
        <html>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">PHPMailer Test Successful!</h2>
            <p>Congratulations! Your FarmApp PHPMailer configuration is working correctly.</p>
            <p><strong>Test Details:</strong></p>
            <ul>
                <li>Date: ' . date('Y-m-d H:i:s') . '</li>
                <li>SMTP Host: ' . SMTP_HOST . '</li>
                <li>From: ' . FROM_EMAIL . '</li>
            </ul>
            <p>You can now use the password reset functionality in your FarmApp.</p>
            <hr>
            <p style="font-size: 12px; color: #666;">This is an automated test message from FarmApp.</p>
        </body>
        </html>';
        
        $mail->AltBody = 'PHPMailer Test Successful! Your FarmApp email configuration is working. Date: ' . date('Y-m-d H:i:s');
        
        // Send the email
        $mail->send();
        
        echo "<div class='success'>";
        echo "<h3>✅ SUCCESS!</h3>";
        echo "<p>Test email sent successfully to: <strong>$test_email</strong></p>";
        echo "<p>Check your email inbox (and spam folder) for the test message.</p>";
        echo "<p><strong>Next Steps:</strong></p>";
        echo "<ol>";
        echo "<li>Check your email to confirm you received the test message</li>";
        echo "<li>If received, your PHPMailer setup is complete!</li>";
        echo "<li>You can now use the password reset functionality</li>";
        echo "<li><strong>Delete this test file</strong> for security</li>";
        echo "</ol>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>❌ ERROR!</h3>";
        echo "<p><strong>Failed to send test email.</strong></p>";
        echo "<p><strong>Error Details:</strong></p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        
        if (isset($mail)) {
            echo "<p><strong>SMTP Error Info:</strong></p>";
            echo "<pre>" . htmlspecialchars($mail->ErrorInfo) . "</pre>";
        }
        
        echo "<p><strong>Common Solutions:</strong></p>";
        echo "<ul>";
        echo "<li>Double-check your email address and app password in email_config.php</li>";
        echo "<li>Make sure 2-Factor Authentication is enabled on your Google account</li>";
        echo "<li>Verify you're using an App Password, not your regular password</li>";
        echo "<li>Check if your server allows outbound SMTP connections on port 587</li>";
        echo "<li>Try enabling SMTP_DEBUG in email_config.php for more details</li>";
        echo "</ul>";
        echo "</div>";
    }
    ?>
    
    <hr>
    <p><strong>⚠️ Security Note:</strong> Delete this test file after testing!</p>
    
</body>
</html>