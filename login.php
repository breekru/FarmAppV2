<?php
/**
 * Login Page - Updated with Forgot Password Link
 * 
 * Handles user authentication.
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
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, password FROM users WHERE username = :username";
        
        if ($stmt = $db->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Check if username exists, if yes then verify password
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $id = $row["id"];
                        $username = $row["username"];
                        $hashed_password = $row["password"];
                        
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            
                            // Redirect user to welcome page
                            header("location: index.php");
                            exit;
                        } else {
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
            }
        }
    }
}

// Set page variables
$page_title = "Login";

// Include header (but without navbar since user is not logged in)
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
        .login-container {
            max-width: 450px;
            margin: 0 auto;
            padding: 40px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            max-width: 200px;
            height: auto;
        }
        .login-card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background-color: rgba(255, 255, 255, 0.9);
        }
        .login-card .card-header {
            background-color: #198754;
            color: white;
            font-weight: bold;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 15px 20px;
        }
        .login-form .form-control {
            height: calc(2.5rem + 2px);
            padding: 0.5rem 1rem;
            font-size: 1.1rem;
        }
        .login-btn {
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
        .forgot-password-link {
            color: #0d6efd;
            text-decoration: none;
        }
        .forgot-password-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Background Image -->
    <div class="bg-farm"></div>
    
    <div class="container login-container my-5">
        <div class="login-logo">
            <img src="assets/img/logo.png" alt="FarmApp Logo">
        </div>
        
        <div class="card login-card">
            <div class="card-header text-center">
                <h2 class="mb-0">Login to FarmApp</h2>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($login_err)): ?>
                <div class="alert alert-danger"><?= $login_err ?></div>
                <?php endif; ?>
                
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="login-form">
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" id="username" 
                                   class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?= $username; ?>" placeholder="Enter your username">
                        </div>
                        <div class="invalid-feedback">
                            <?= $username_err; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="password" 
                                   class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Enter your password">
                        </div>
                        <div class="invalid-feedback">
                            <?= $password_err; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <button type="submit" class="btn btn-success w-100 login-btn">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Login
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-2">Don't have an account? <a href="register.php">Sign up now</a></p>
                        <p class="mb-0">
                            <a href="forgot_password.php" class="forgot-password-link">
                                <i class="bi bi-key me-1"></i> Forgot your password?
                            </a>
                        </p>
                    </div>
                </form>
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