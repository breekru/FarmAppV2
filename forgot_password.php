<?php
/**
 * DEBUG VERSION - Forgot Password Page
 * 
 * This version includes extensive debugging to find the issue
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- DEBUG: Script started -->\n";

// Include the configuration file
require_once 'config.php';
echo "<!-- DEBUG: Config loaded -->\n";

// Initialize the session
session_start();
echo "<!-- DEBUG: Session started -->\n";

// Check if the user is already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Get database connection
try {
    $db = getDbConnection();
    echo "<!-- DEBUG: Database connected successfully -->\n";
    
    // Test if the password_reset_tokens table exists
    $test_query = $db->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($test_query->rowCount() == 0) {
        die("ERROR: The password_reset_tokens table does not exist. Please run the SQL schema first.");
    }
    echo "<!-- DEBUG: password_reset_tokens table exists -->\n";
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Define variables and initialize with empty values
$email = "";
$email_err = "";
$success = false;
$rate_limit_exceeded = false;
$debug_messages = [];

// Rate limiting function
function checkRateLimit($db, $email, $ip_address) {
    global $debug_messages;
    $debug_messages[] = "Checking rate limit for email: " . $email;
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM password_reset_tokens 
            WHERE email = :email 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $email_count = $stmt->fetch()['count'];
        
        $debug_messages[] = "Rate limit check result: " . $email_count . " requests in last hour";
        return $email_count >= 3;
    } catch (Exception $e) {
        $debug_messages[] = "Rate limit check error: " . $e->getMessage();
        return false;
    }
}

// Simple email function for testing
function sendPasswordResetEmail($to_email, $user_name, $reset_link) {
    global $debug_messages;
    $debug_messages[] = "Attempting to send email to: " . $to_email;
    
    $subject = "Password Reset Request - FarmApp";
    
    $message = "
    <html>
    <head>
        <title>Password Reset Request</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h1 style='color: #198754;'>FarmApp</h1>
            <h2>Password Reset Request</h2>
            <p>Hello " . htmlspecialchars($user_name) . ",</p>
            <p>Click the link below to reset your password:</p>
            <p><a href='" . htmlspecialchars($reset_link) . "'>Reset My Password</a></p>
            <p>This link will expire in 1 hour.</p>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: FarmApp <noreply@' . $_SERVER['HTTP_HOST'] . '>',
    );

    $result = mail($to_email, $subject, $message, implode("\r\n", $headers));
    $debug_messages[] = "Email send result: " . ($result ? "SUCCESS" : "FAILED");
    
    return $result;
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $debug_messages[] = "Form submitted via POST";
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email address.";
        $debug_messages[] = "Validation failed: Email is empty";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
        $debug_messages[] = "Validation failed: Invalid email format";
    } else {
        $email = trim($_POST["email"]);
        $debug_messages[] = "Email validation passed: " . $email;
        
        // Check rate limiting
        if (checkRateLimit($db, $email, $_SERVER['REMOTE_ADDR'])) {
            $rate_limit_exceeded = true;
            $debug_messages[] = "Rate limit exceeded";
        } else {
            $debug_messages[] = "Rate limit check passed";
            
            // Check if email exists in the database
            $sql = "SELECT id, username, f_name, l_name FROM users WHERE email = :email";
            $debug_messages[] = "Executing user lookup query";
            
            try {
                $stmt = $db->prepare($sql);
                $stmt->bindParam(":email", $email, PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    $debug_messages[] = "User lookup query executed successfully";
                    
                    if ($stmt->rowCount() == 1) {
                        $user = $stmt->fetch();
                        $debug_messages[] = "User found: " . $user['username'];
                        
                        try {
                            // Generate a secure token
                            $token = bin2hex(random_bytes(32));
                            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                            $debug_messages[] = "Token generated: " . substr($token, 0, 10) . "...";
                            
                            // Clean up old tokens
                            $cleanup_stmt = $db->prepare("
                                DELETE FROM password_reset_tokens 
                                WHERE email = :email 
                                AND (expires_at < NOW() OR used = 1)
                            ");
                            $cleanup_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                            $cleanup_stmt->execute();
                            $debug_messages[] = "Old tokens cleaned up";
                            
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
                                $debug_messages[] = "Token inserted into database successfully";
                                
                                // Send password reset email
                                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                                $user_name = $user['f_name'] . ' ' . $user['l_name'];
                                $debug_messages[] = "Reset link: " . $reset_link;
                                
                                if (sendPasswordResetEmail($email, $user_name, $reset_link)) {
                                    $success = true;
                                    $debug_messages[] = "SUCCESS: Email sent successfully";
                                } else {
                                    $email_err = "Failed to send password reset email. Please try again later.";
                                    $debug_messages[] = "ERROR: Failed to send email";
                                }
                            } else {
                                $email_err = "Something went wrong. Please try again later.";
                                $debug_messages[] = "ERROR: Failed to insert token into database";
                            }
                        } catch (Exception $e) {
                            error_log('Password reset error: ' . $e->getMessage());
                            $email_err = "Something went wrong. Please try again later.";
                            $debug_messages[] = "ERROR: Exception in token generation: " . $e->getMessage();
                        }
                    } else {
                        // Always show success message to prevent email enumeration
                        $success = true;
                        $debug_messages[] = "User not found, but showing success message for security";
                    }
                } else {
                    $email_err = "Something went wrong. Please try again later.";
                    $debug_messages[] = "ERROR: User lookup query failed to execute";
                }
            } catch (Exception $e) {
                $email_err = "Database error occurred.";
                $debug_messages[] = "ERROR: Database exception: " . $e->getMessage();
            }
        }
    }
}

// Set page variables
$page_title = "Forgot Password - DEBUG";
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
    
    <style>
        body { background-color: #f8f9fa; }
        .debug-info { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            padding: 15px; 
            margin: 20px 0; 
            border-radius: 5px; 
            font-family: monospace;
            font-size: 12px;
        }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; }
        .card { border-radius: 10px; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .card-header { background-color: #0d6efd; color: white; font-weight: bold; padding: 15px 20px; }
        .form-control { height: calc(2.5rem + 2px); padding: 0.5rem 1rem; font-size: 1.1rem; }
        .btn { padding: 0.6rem 1.2rem; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header text-center">
                <h2 class="mb-0">
                    <i class="bi bi-key me-2"></i>Forgot Password - DEBUG MODE
                </h2>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($debug_messages)): ?>
                <div class="debug-info">
                    <strong>🐛 DEBUG INFORMATION:</strong><br>
                    <?php foreach ($debug_messages as $message): ?>
                    • <?= htmlspecialchars($message) ?><br>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
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
                
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
                        <button type="submit" class="btn btn-primary w-100">
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