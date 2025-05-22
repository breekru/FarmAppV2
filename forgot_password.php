<?php
/**
 * Forgot Password Page - FIXED VERSION
 * 
 * Allows users to request a password reset link by entering their email address.
 */

// Include the configuration file
require_once 'config.php';

// Include email configuration
require_once 'email_config.php';

// Include PHPMailer (adjust path as needed)
// Option 1: If you installed via Composer
// require_once 'vendor/autoload.php';

// Option 2: If you downloaded PHPMailer manually
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect to welcome page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Get database connection
$db = getDbConnection();

// Define variables and initialize with empty values
$email = "";
$email_err = "";
$success = false;
$rate_limit_exceeded = false;

// Rate limiting: Check if user has made too many requests recently
function checkRateLimit($db, $email, $ip_address) {
    // Allow maximum 3 password reset requests per email per hour
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM password_reset_tokens 
        WHERE email = :email 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $email_count = $stmt->fetch()['count'];
    
    return $email_count >= 3; // Simple email-based rate limiting
}

/**
 * Send password reset email using PHPMailer
 * 
 * @param string $to_email
 * @param string $user_name
 * @param string $reset_link
 * @return bool
 */
function sendPasswordResetEmailPHPMailer($to_email, $user_name, $reset_link) {
    try {
        // Create a new PHPMailer instance
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
        if (defined('SMTP_DEBUG') && SMTP_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        // Email settings
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to_email, $user_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME . ' Support');

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - FarmApp';
        
        $mail->Body = "
        <html>
        <head>
            <title>Password Reset Request</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #198754; }
                .button { 
                    background-color: #198754; 
                    color: white; 
                    padding: 12px 25px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    display: inline-block; 
                    margin: 20px 0;
                }
                .warning { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { font-size: 12px; color: #666; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>FarmApp</h1>
                </div>
                
                <h2>Password Reset Request</h2>
                
                <p>Hello " . htmlspecialchars($user_name) . ",</p>
                
                <p>We received a request to reset your FarmApp password. If you made this request, please click the button below to reset your password:</p>
                
                <div style='text-align: center;'>
                    <a href='" . htmlspecialchars($reset_link) . "' class='button'>Reset My Password</a>
                </div>
                
                <div class='warning'>
                    <p><strong>Important Security Information:</strong></p>
                    <ul>
                        <li>This link will expire in 1 hour for security reasons</li>
                        <li>If you didn't request this password reset, you can safely ignore this email</li>
                        <li>Your password will not be changed unless you click the link above and complete the reset process</li>
                    </ul>
                </div>
                
                <p>If the button above doesn't work, you can copy and paste the following link into your browser:</p>
                <p style='word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace;'>
                    " . htmlspecialchars($reset_link) . "
                </p>
                
                <div class='footer'>
                    <p>This email was sent from FarmApp. If you have any questions or concerns, please contact our support team.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Alternative text body for email clients that don't support HTML
        $mail->AltBody = "
        FarmApp Password Reset Request

        Hello $user_name,

        We received a request to reset your FarmApp password. If you made this request, please visit the following link to reset your password:

        $reset_link

        Important:
        - This link will expire in 1 hour for security reasons
        - If you didn't request this password reset, you can safely ignore this email
        - Your password will not be changed unless you visit the link above and complete the reset process

        If you have any questions, please contact our support team.

        This is an automated message, please do not reply to this email.
        ";

        // Send the email
        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log the error
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        $email = trim($_POST["email"]);
        
        // Check rate limiting
        if (checkRateLimit($db, $email, $_SERVER['REMOTE_ADDR'])) {
            $rate_limit_exceeded = true;
        } else {
            // Check if email exists in the database
            $sql = "SELECT id, username, f_name, l_name FROM users WHERE email = :email";
            
            if ($stmt = $db->prepare($sql)) {
                $stmt->bindParam(":email", $email, PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    if ($stmt->rowCount() == 1) {
                        $user = $stmt->fetch();
                        
                        try {
                            // Generate a secure token
                            $token = bin2hex(random_bytes(32));
                            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                            
                            // Clean up old tokens for this user (optional - helps keep table clean)
                            $cleanup_stmt = $db->prepare("
                                DELETE FROM password_reset_tokens 
                                WHERE email = :email 
                                AND (expires_at < NOW() OR used = 1)
                            ");
                            $cleanup_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                            $cleanup_stmt->execute();
                            
                            // Insert the token into the database
                            $insert_stmt = $db->prepare("
                                INSERT INTO password_reset_tokens (user_id, email, token, expires_at) 
                                VALUES (:user_id, :email, :token, :expires_at)
                            ");
                            $insert_stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                            $insert_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                            $insert_stmt->bindParam(':token', $token, PDO::PARAM_STR);
                            $insert_stmt->bindParam(':expires_at', $expires_at, PDO::PARAM_STR);
                            
                            if ($insert_stmt->execute()) {
                                // Send password reset email using PHPMailer
                                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                                $user_name = $user['f_name'] . ' ' . $user['l_name'];
                                
                                // FIXED: Call the correct function name
                                if (sendPasswordResetEmailPHPMailer($email, $user_name, $reset_link)) {
                                    $success = true;
                                } else {
                                    $email_err = "Failed to send password reset email. Please try again later.";
                                }
                            } else {
                                $email_err = "Something went wrong. Please try again later.";
                            }
                        } catch (Exception $e) {
                            error_log('Password reset error: ' . $e->getMessage());
                            $email_err = "Something went wrong. Please try again later.";
                        }
                    } else {
                        // Always show success message to prevent email enumeration
                        $success = true;
                    }
                } else {
                    $email_err = "Something went wrong. Please try again later.";
                }
            }
        }
    }
}

// Set page variables
$page_title = "Forgot Password";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - FarmApp</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .forgot-password-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 40px;
        }
        .forgot-password-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-password-logo img {
            max-width: 200px;
            height: auto;
        }
        .forgot-password-card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background-color: rgba(255, 255, 255, 0.9);
        }
        .forgot-password-card .card-header {
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 15px 20px;
        }
        .forgot-password-form .form-control {
            height: calc(2.5rem + 2px);
            padding: 0.5rem 1rem;
            font-size: 1.1rem;
        }
        .forgot-password-btn {
            padding: 0.6rem 1.2rem;
            font-size: 1.1rem;
        }
        .bg-farm {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('assets/img/background.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.3;
            z-index: -1;
        }
    </style>
</head>
<body>
    <!-- Background Image -->
    <div class="bg-farm"></div>
    
    <div class="container forgot-password-container my-5">
        <div class="forgot-password-logo">
            <img src="assets/img/logo.png" alt="FarmApp Logo">
        </div>
        
        <div class="card forgot-password-card">
            <div class="card-header text-center">
                <h2 class="mb-0">
                    <i class="bi bi-key me-2"></i>Forgot Password
                </h2>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <h4 class="alert-heading">
                        <i class="bi bi-check-circle-fill me-2"></i>Reset Link Sent!
                    </h4>
                    <p>If an account with that email address exists, we've sent you a password reset link.</p>
                    <p class="mb-0">Please check your email and follow the instructions to reset your password.</p>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
                <?php elseif ($rate_limit_exceeded): ?>
                <div class="alert alert-warning">
                    <h4 class="alert-heading">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Too Many Requests
                    </h4>
                    <p>You've made too many password reset requests recently.</p>
                    <p class="mb-0">Please wait an hour before trying again, or check your email for a previous reset link.</p>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
                <?php else: ?>
                <p class="text-muted mb-4">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
                
                <?php if (!empty($email_err)): ?>
                <div class="alert alert-danger"><?= $email_err ?></div>
                <?php endif; ?>
                
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="forgot-password-form">
                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" id="email" 
                                   class="form-control <?= (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?= htmlspecialchars($email); ?>" 
                                   placeholder="Enter your email address" required>
                        </div>
                        <div class="invalid-feedback">
                            <?= $email_err; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <button type="submit" class="btn btn-primary w-100 forgot-password-btn">
                            <i class="bi bi-send me-2"></i>Send Reset Link
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">
                            Remember your password? 
                            <a href="login.php">
                                <i class="bi bi-arrow-left me-1"></i>Back to Login
                            </a>
                        </p>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center text-muted mt-4">
            <p>&copy; <?= date('Y') ?> FarmApp. All rights reserved.</p>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>