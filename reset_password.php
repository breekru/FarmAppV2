<?php
// Include the configuration file
require_once 'config.php';

// 🔥 ADD PHPMAILER INCLUDES HERE 🔥
// Option 1: If you installed via Composer
// require_once 'vendor/autoload.php';

// Option 2: If you downloaded PHPMailer manually
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Continue with your existing code...
session_start();

// Check if the user is already logged in, if yes then redirect to welcome page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Get database connection
$db = getDbConnection();

// Define variables and initialize with empty values
$token = $password = $confirm_password = "";
$token_err = $password_err = $confirm_password_err = "";
$token_valid = false;
$user_data = null;
$success = false;

// Get token from URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Validate token format (should be 64 hex characters)
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token_err = "Invalid token format.";
    } else {
        // Check if token exists and is valid
        $sql = "
            SELECT prt.*, u.username, u.f_name, u.l_name 
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = :token 
            AND prt.expires_at > NOW() 
            AND prt.used = 0
        ";
        
        if ($stmt = $db->prepare($sql)) {
            $stmt->bindParam(":token", $token, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $user_data = $stmt->fetch();
                    $token_valid = true;
                } else {
                    $token_err = "This password reset link is invalid or has expired.";
                }
            } else {
                $token_err = "Something went wrong. Please try again later.";
            }
        }
    }
} else {
    $token_err = "No reset token provided.";
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a new password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }
    
    // Check input errors before updating the database
    if (empty($password_err) && empty($confirm_password_err)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Update user's password
            $update_sql = "UPDATE users SET password = :password WHERE id = :user_id";
            if ($update_stmt = $db->prepare($update_sql)) {
                $update_stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
                $update_stmt->bindParam(":user_id", $user_data['user_id'], PDO::PARAM_INT);
                
                // Create password hash
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                
                if ($update_stmt->execute()) {
                    // Mark token as used
                    $token_sql = "UPDATE password_reset_tokens SET used = 1 WHERE token = :token";
                    if ($token_stmt = $db->prepare($token_sql)) {
                        $token_stmt->bindParam(":token", $token, PDO::PARAM_STR);
                        
                        if ($token_stmt->execute()) {
                            // Commit transaction
                            $db->commit();
                            $success = true;
                            
                            // Optional: Send confirmation email
                            sendPasswordChangeConfirmation($user_data['email'], $user_data['f_name'] . ' ' . $user_data['l_name']);
                            
                        } else {
                            $db->rollBack();
                            $password_err = "Something went wrong. Please try again later.";
                        }
                    }
                } else {
                    $db->rollBack();
                    $password_err = "Something went wrong. Please try again later.";
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Password reset error: ' . $e->getMessage());
            $password_err = "Something went wrong. Please try again later.";
        }
    }
}

/**
 * Send password change confirmation email
 * 
 * @param string $to_email
 * @param string $user_name
 * @return bool
 */
function sendPasswordChangeConfirmation($to_email, $user_name) {
    $subject = "Password Changed Successfully - FarmApp";
    
    $message = "
    <html>
    <head>
        <title>Password Changed Successfully</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #198754;'>FarmApp</h1>
            </div>
            
            <h2>Password Changed Successfully</h2>
            
            <p>Hello " . htmlspecialchars($user_name) . ",</p>
            
            <p>Your FarmApp password has been successfully changed.</p>
            
            <p><strong>If you made this change:</strong></p>
            <p>No further action is required. You can now log in with your new password.</p>
            
            <p><strong>If you did not make this change:</strong></p>
            <p>Your account may have been compromised. Please contact our support team immediately and consider taking the following steps:</p>
            <ul>
                <li>Change your password again</li>
                <li>Review your account activity</li>
                <li>Enable two-factor authentication if available</li>
            </ul>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php' 
                   style='background-color: #198754; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                   Login to FarmApp
                </a>
            </div>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
            
            <p style='font-size: 12px; color: #666;'>
                This email was sent from FarmApp. If you have any questions or concerns, please contact our support team.
            </p>
        </div>
    </body>
    </html>
    ";

    // Email headers
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: FarmApp <noreply@' . $_SERVER['HTTP_HOST'] . '>',
        'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
        'X-Mailer: PHP/' . phpversion()
    );

    // Send email
    return mail($to_email, $subject, $message, implode("\r\n", $headers));
}

// Set page variables
$page_title = "Reset Password";
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
        .reset-password-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 40px;
        }
        .reset-password-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-password-logo img {
            max-width: 200px;
            height: auto;
        }
        .reset-password-card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background-color: rgba(255, 255, 255, 0.9);
        }
        .reset-password-card .card-header {
            background-color: #198754;
            color: white;
            font-weight: bold;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 15px 20px;
        }
        .reset-password-form .form-control {
            height: calc(2.5rem + 2px);
            padding: 0.5rem 1rem;
            font-size: 1.1rem;
        }
        .reset-password-btn {
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
        .password-strength {
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .password-strength.weak { color: #dc3545; }
        .password-strength.medium { color: #fd7e14; }
        .password-strength.strong { color: #198754; }
    </style>
</head>
<body>
    <!-- Background Image -->
    <div class="bg-farm"></div>
    
    <div class="container reset-password-container my-5">
        <div class="reset-password-logo">
            <img src="assets/img/logo.png" alt="FarmApp Logo">
        </div>
        
        <div class="card reset-password-card">
            <div class="card-header text-center">
                <h2 class="mb-0">
                    <i class="bi bi-shield-lock me-2"></i>Reset Password
                </h2>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($token_err)): ?>
                <div class="alert alert-danger">
                    <h4 class="alert-heading">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Invalid or Expired Link
                    </h4>
                    <p><?= $token_err ?></p>
                    <p class="mb-0">
                        <a href="forgot_password.php" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise me-2"></i>Request New Reset Link
                        </a>
                    </p>
                </div>
                <?php elseif ($success): ?>
                <div class="alert alert-success">
                    <h4 class="alert-heading">
                        <i class="bi bi-check-circle-fill me-2"></i>Password Reset Successful!
                    </h4>
                    <p>Your password has been successfully changed.</p>
                    <p class="mb-0">You can now log in with your new password.</p>
                </div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-success btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login to FarmApp
                    </a>
                </div>
                <?php elseif ($token_valid): ?>
                <div class="alert alert-info">
                    <p class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Hello <strong><?= htmlspecialchars($user_data['f_name'] . ' ' . $user_data['l_name']) ?></strong>! 
                        Please enter your new password below.
                    </p>
                </div>
                
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) . '?token=' . htmlspecialchars($token); ?>" method="post" class="reset-password-form">
                    <div class="mb-4">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" name="password" id="password" 
                                   class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Enter new password" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            <?= $password_err; ?>
                        </div>
                        <div id="passwordStrength" class="password-strength"></div>
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="form-control <?= (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Confirm new password" required>
                        </div>
                        <div class="invalid-feedback">
                            <?= $confirm_password_err; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <button type="submit" class="btn btn-success w-100 reset-password-btn">
                            <i class="bi bi-check-circle me-2"></i>Reset Password
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');
        
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'password') {
                    togglePasswordIcon.className = 'bi bi-eye';
                } else {
                    togglePasswordIcon.className = 'bi bi-eye-slash';
                }
            });
        }
        
        // Password strength indicator
        const passwordStrength = document.getElementById('passwordStrength');
        if (passwordInput && passwordStrength) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let strengthText = '';
                let strengthClass = '';
                
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                switch (strength) {
                    case 0:
                    case 1:
                        strengthText = 'Very Weak';
                        strengthClass = 'weak';
                        break;
                    case 2:
                        strengthText = 'Weak';
                        strengthClass = 'weak';
                        break;
                    case 3:
                        strengthText = 'Medium';
                        strengthClass = 'medium';
                        break;
                    case 4:
                        strengthText = 'Strong';
                        strengthClass = 'strong';
                        break;
                    case 5:
                        strengthText = 'Very Strong';
                        strengthClass = 'strong';
                        break;
                }
                
                if (password.length > 0) {
                    passwordStrength.textContent = 'Password Strength: ' + strengthText;
                    passwordStrength.className = 'password-strength ' + strengthClass;
                } else {
                    passwordStrength.textContent = '';
                    passwordStrength.className = 'password-strength';
                }
            });
        }
        
        // Password confirmation validation
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (passwordInput.value !== this.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            passwordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value && this.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            });
        }
    });
    </script>
</body>
</html>