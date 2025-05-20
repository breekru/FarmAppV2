<?php
/**
 * Change Password Page
 * 
 * Allows users to change their password with proper validation.
 */

// Include the configuration file
require_once 'config.php';

// Initialize the session
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$current_user = $_SESSION["username"];

// Get database connection
$db = getDbConnection();

// Define variables and initialize with empty values
$current_password = $new_password = $confirm_password = "";
$current_password_err = $new_password_err = $confirm_password_err = "";
$success = false;

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate current password
    if (empty(trim($_POST["current_password"]))) {
        $current_password_err = "Please enter your current password.";
    } else {
        $current_password = trim($_POST["current_password"]);
        
        // Check if the current password is correct
        $sql = "SELECT password FROM users WHERE username = :username";
        
        if ($stmt = $db->prepare($sql)) {
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $param_username = $current_user;
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $hashed_password = $row["password"];
                        if (!password_verify($current_password, $hashed_password)) {
                            $current_password_err = "The current password you entered is not valid.";
                        }
                    }
                } else {
                    // This shouldn't happen as the user is logged in
                    $current_password_err = "Something went wrong. Please try again later.";
                }
            } else {
                $current_password_err = "Oops! Something went wrong. Please try again later.";
            }
        }
    }
    
    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Please enter the new password.";     
    } elseif (strlen(trim($_POST["new_password"])) < 6) {
        $new_password_err = "Password must have at least 6 characters.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }
    
    // Check input errors before updating the database
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Prepare an update statement
        $sql = "UPDATE users SET password = :password WHERE username = :username";
        
        if ($stmt = $db->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Set parameters
            $param_password = password_hash($new_password, PASSWORD_DEFAULT);
            $param_username = $current_user;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Password updated successfully
                $success = true;
                
                // Store success message in session for display after redirect
                $_SESSION['alert_message'] = "Your password has been changed successfully.";
                $_SESSION['alert_type'] = "success";
                
                // Redirect to the profile page or dashboard
                header("location: index.php");
                exit();
            } else {
                $confirm_password_err = "Oops! Something went wrong. Please try again later.";
            }
        }
    }
}

// Set page variables
$page_title = "Change Password";
$page_header = "Change Your Password";
$page_subheader = "Update your account password";

// Include header
include_once 'includes/header.php';
?>

<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header">
                <h3 class="mb-0">Change Your Password</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <h4 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Password Changed!</h4>
                    <p>Your password has been updated successfully.</p>
                </div>
                <?php else: ?>
                <p class="mb-4">Please fill out this form to change your account password.</p>
                
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="current_password" id="current_password" 
                                   class="form-control <?= (!empty($current_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback">
                                <?= $current_password_err; ?>
                            </div>
                        </div>
                        <div class="form-text">Enter your current password to verify your identity.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" name="new_password" id="new_password" 
                                   class="form-control <?= (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback">
                                <?= $new_password_err; ?>
                            </div>
                        </div>
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="form-control <?= (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback">
                                <?= $confirm_password_err; ?>
                            </div>
                        </div>
                        <div class="form-text">Re-enter your new password to confirm.</div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle me-2"></i> Change Password
                            </button>
                        </div>
                        <div class="col-md-6">
                            <a href="index.php" class="btn btn-secondary w-100">
                                <i class="bi bi-x-circle me-2"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header">
                <h3 class="mb-0">Password Security Tips</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="bi bi-shield-check me-2 text-success"></i>Strong Password Guidelines</h5>
                        <ul class="list-unstyled ps-4">
                            <li><i class="bi bi-check-circle-fill me-2 text-success"></i>Use at least 8 characters</li>
                            <li><i class="bi bi-check-circle-fill me-2 text-success"></i>Include uppercase and lowercase letters</li>
                            <li><i class="bi bi-check-circle-fill me-2 text-success"></i>Include numbers and special characters</li>
                            <li><i class="bi bi-check-circle-fill me-2 text-success"></i>Avoid using dictionary words</li>
                            <li><i class="bi bi-check-circle-fill me-2 text-success"></i>Don't use personal information</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Password Don'ts</h5>
                        <ul class="list-unstyled ps-4">
                            <li><i class="bi bi-x-circle-fill me-2 text-danger"></i>Don't reuse passwords across websites</li>
                            <li><i class="bi bi-x-circle-fill me-2 text-danger"></i>Don't share your password with others</li>
                            <li><i class="bi bi-x-circle-fill me-2 text-danger"></i>Don't write down your password</li>
                            <li><i class="bi bi-x-circle-fill me-2 text-danger"></i>Don't use "password" or "123456"</li>
                            <li><i class="bi bi-x-circle-fill me-2 text-danger"></i>Don't use the same password for years</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show password toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    // Function to add show password toggle
    function addPasswordToggle(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        const inputGroup = input.parentElement;
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'btn btn-outline-secondary';
        toggleButton.innerHTML = '<i class="bi bi-eye"></i>';
        toggleButton.title = 'Show password';
        
        toggleButton.addEventListener('click', function() {
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="bi bi-eye-slash"></i>';
                this.title = 'Hide password';
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="bi bi-eye"></i>';
                this.title = 'Show password';
            }
        });
        
        inputGroup.appendChild(toggleButton);
    }
    
    // Add toggle to all password fields
    addPasswordToggle('current_password');
    addPasswordToggle('new_password');
    addPasswordToggle('confirm_password');
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>