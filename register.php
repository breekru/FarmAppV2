<?php
/**
 * Registration Page
 * 
 * Handles new user registration with improved validation and security.
 */

// Include the configuration file
require_once 'config.php';

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
$username = $password = $confirm_password = $f_name = $l_name = $farm_name = $email = "";
$username_err = $password_err = $confirm_password_err = $f_name_err = $l_name_err = $farm_name_err = $email_err = "";
$registration_success = false;

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = :username";
        
        if ($stmt = $db->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate first name
    if (empty(trim($_POST["f_name"]))) {
        $f_name_err = "Please enter your first name.";
    } else {
        $f_name = trim($_POST["f_name"]);
    }
    
    // Validate last name
    if (empty(trim($_POST["l_name"]))) {
        $l_name_err = "Please enter your last name.";
    } else {
        $l_name = trim($_POST["l_name"]);
    }
    
    // Validate farm name (optional)
    $farm_name = trim($_POST["farm_name"]);
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = :email";
        
        if ($stmt = $db->prepare($sql)) {
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
    }
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && 
        empty($f_name_err) && empty($l_name_err) && empty($email_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password, f_name, l_name, farm_name, email, created_at) 
                VALUES (:username, :password, :f_name, :l_name, :farm_name, :email, NOW())";
        
        if ($stmt = $db->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
            $stmt->bindParam(":f_name", $param_f_name, PDO::PARAM_STR);
            $stmt->bindParam(":l_name", $param_l_name, PDO::PARAM_STR);
            $stmt->bindParam(":farm_name", $param_farm_name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            
            // Set parameters
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_f_name = $f_name;
            $param_l_name = $l_name;
            $param_farm_name = $farm_name;
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Registration successful
                $registration_success = true;
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
    }
}

// Set page variables
$page_title = "Sign Up";

// Include custom header for sign-up page (not the full header with navbar)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'FarmApp' ?></title>
    
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
        .register-container {
            max-width: 650px;
            margin: 0 auto;
            padding: 40px 15px;
        }
        .register-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-logo img {
            max-width: 200px;
            height: auto;
        }
        .register-card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background-color: rgba(255, 255, 255, 0.9);
        }
        .register-card .card-header {
            background-color: #198754;
            color: white;
            font-weight: bold;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 15px 20px;
        }
        .register-form .form-control {
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }
        .register-btn {
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
    
    <div class="container register-container my-4">
        <div class="register-logo">
            <img src="assets/img/logo.png" alt="FarmApp Logo">
        </div>
        
        <?php if ($registration_success): ?>
        <div class="alert alert-success">
            <h4 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Registration Successful!</h4>
            <p>Your account has been created successfully. You can now <a href="login.php" class="alert-link">log in</a> and start using FarmApp.</p>
        </div>
        <?php else: ?>
        
        <div class="card register-card">
            <div class="card-header text-center">
                <h2 class="mb-0">Create Your FarmApp Account</h2>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4">Please fill out this form to create an account for your farm.</p>
                
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="register-form">
                    <div class="row">
                        <!-- Account Information -->
                        <div class="col-md-6">
                            <h4 class="mb-3">Account Information</h4>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" id="username" 
                                       class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?= htmlspecialchars($username); ?>" required>
                                <div class="invalid-feedback">
                                    <?= $username_err; ?>
                                </div>
                                <div class="form-text">Letters, numbers, and underscores only.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="email" 
                                       class="form-control <?= (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?= htmlspecialchars($email); ?>" required>
                                <div class="invalid-feedback">
                                    <?= $email_err; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" id="password" 
                                       class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    <?= $password_err; ?>
                                </div>
                                <div class="form-text">At least 6 characters.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" id="confirm_password" 
                                       class="form-control <?= (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    <?= $confirm_password_err; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h4 class="mb-3">Personal Information</h4>
                            
                            <div class="mb-3">
                                <label for="f_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="f_name" id="f_name" 
                                       class="form-control <?= (!empty($f_name_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?= htmlspecialchars($f_name); ?>" required>
                                <div class="invalid-feedback">
                                    <?= $f_name_err; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="l_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="l_name" id="l_name" 
                                       class="form-control <?= (!empty($l_name_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?= htmlspecialchars($l_name); ?>" required>
                                <div class="invalid-feedback">
                                    <?= $l_name_err; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="farm_name" class="form-label">Farm Name</label>
                                <input type="text" name="farm_name" id="farm_name" 
                                       class="form-control <?= (!empty($farm_name_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?= htmlspecialchars($farm_name); ?>">
                                <div class="invalid-feedback">
                                    <?= $farm_name_err; ?>
                                </div>
                                <div class="form-text">Optional: Enter the name of your farm.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="" id="termsCheck" required>
                                <label class="form-check-label" for="termsCheck">
                                    I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-success btn-lg register-btn px-5">
                                <i class="bi bi-person-plus me-2"></i> Register
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <p class="mb-0">Already have an account? <a href="login.php">Log in now</a></p>
                    </div>
                </form>
            </div>
        </div>
        
        <?php endif; ?>
        
        <div class="text-center text-muted mt-4">
            <p>&copy; <?= date('Y') ?> FarmApp. All rights reserved.</p>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>