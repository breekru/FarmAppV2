<?php
/**
 * User Profile Page
 * 
 * Allows users to view and update their profile information.
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
$f_name = $l_name = $farm_name = $email = "";
$f_name_err = $l_name_err = $email_err = "";
$success = false;

// Fetch current user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindParam(":username", $current_user, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $f_name = $user["f_name"];
        $l_name = $user["l_name"];
        $farm_name = $user["farm_name"];
        $email = $user["email"];
        $created_at = $user["created_at"];
    } else {
        // This shouldn't happen as the user is logged in
        $_SESSION['alert_message'] = "Error retrieving user data.";
        $_SESSION['alert_type'] = "danger";
        header("location: index.php");
        exit;
    }
} catch (Exception $e) {
    error_log('Profile Error: ' . $e->getMessage());
    $_SESSION['alert_message'] = "Error retrieving user data.";
    $_SESSION['alert_type'] = "danger";
    header("location: index.php");
    exit;
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
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
    
    // Farm name is optional
    $farm_name = trim($_POST["farm_name"]);
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email already exists and belongs to another user
        $sql = "SELECT id FROM users WHERE email = :email AND username != :username";
        
        if ($stmt = $db->prepare($sql)) {
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $stmt->bindParam(":username", $current_user, PDO::PARAM_STR);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $email_err = "This email is already taken by another user.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                $email_err = "Oops! Something went wrong. Please try again later.";
            }
        }
    }
    
    // Check input errors before updating the database
    if (empty($f_name_err) && empty($l_name_err) && empty($email_err)) {
        // Prepare an update statement
        $sql = "UPDATE users SET f_name = :f_name, l_name = :l_name, farm_name = :farm_name, email = :email WHERE username = :username";
        
        if ($stmt = $db->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":f_name", $f_name, PDO::PARAM_STR);
            $stmt->bindParam(":l_name", $l_name, PDO::PARAM_STR);
            $stmt->bindParam(":farm_name", $farm_name, PDO::PARAM_STR);
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt->bindParam(":username", $current_user, PDO::PARAM_STR);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Profile updated successfully
                $success = true;
                
                // Store success message in session for display after redirect
                $_SESSION['alert_message'] = "Your profile has been updated successfully.";
                $_SESSION['alert_type'] = "success";
                
                // Redirect to the same page to show success message
                header("location: profile.php");
                exit();
            } else {
                $_SESSION['alert_message'] = "Oops! Something went wrong. Please try again later.";
                $_SESSION['alert_type'] = "danger";
            }
        }
    }
}

// Set page variables
$page_title = "My Profile";
$page_header = "My Profile";
$page_subheader = "View and update your account information";

// Include header
include_once 'includes/header.php';
?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Profile Information</h3>
                <span class="badge bg-primary">
                    Member since <?= date('M Y', strtotime($created_at)) ?>
                </span>
            </div>
            <div class="card-body">
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h4 class="mb-3">Personal Information</h4>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" id="username" class="form-control" value="<?= htmlspecialchars($current_user); ?>" disabled>
                                <div class="form-text">Your username cannot be changed.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="f_name" class="form-label">First Name</label>
                                <input type="text" name="f_name" id="f_name" 
                                       class="form-control <?= (!empty($f_name_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?= htmlspecialchars($f_name); ?>" required>
                                <div class="invalid-feedback">
                                    <?= $f_name_err; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="l_name" class="form-label">Last Name</label>
                                <input type="text" name="l_name" id="l_name" 
                                       class="form-control <?= (!empty($l_name_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?= htmlspecialchars($l_name); ?>" required>
                                <div class="invalid-feedback">
                                    <?= $l_name_err; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Farm Information -->
                        <div class="col-md-6">
                            <h4 class="mb-3">Farm Information</h4>
                            
                            <div class="mb-3">
                                <label for="farm_name" class="form-label">Farm Name</label>
                                <input type="text" name="farm_name" id="farm_name" 
                                       class="form-control"
                                       value="<?= htmlspecialchars($farm_name); ?>">
                                <div class="form-text">Optional: Enter the name of your farm.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" 
                                       class="form-control <?= (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?= htmlspecialchars($email); ?>" required>
                                <div class="invalid-feedback">
                                    <?= $email_err; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar with Account Options -->
    <div class="col-lg-4">
        <!-- Account Actions -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h4 class="mb-0">Account Actions</h4>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="change_password.php" class="btn btn-outline-primary">
                        <i class="bi bi-key me-2"></i> Change Password
                    </a>
                    <a href="report_inventory.php" class="btn btn-outline-success">
                        <i class="bi bi-clipboard-data me-2"></i> View Inventory Report
                    </a>
                    <a href="report_financial.php" class="btn btn-outline-info">
                        <i class="bi bi-graph-up me-2"></i> View Financial Report
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="bi bi-box-arrow-right me-2"></i> Log Out
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Account Statistics -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h4 class="mb-0">Farm Statistics</h4>
            </div>
            <div class="card-body">
                <?php
                // Get animal counts
                $animalQuery = "
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Alive' THEN 1 ELSE 0 END) as alive,
                        COUNT(DISTINCT type) as types
                    FROM animals 
                    WHERE user_id = :user_id
                ";
                $animalStmt = $db->prepare($animalQuery);
                $animalStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
                $animalStmt->execute();
                $stats = $animalStmt->fetch(PDO::FETCH_ASSOC);
                
                $totalAnimals = $stats['total'] ?? 0;
                $aliveAnimals = $stats['alive'] ?? 0;
                $animalTypes = $stats['types'] ?? 0;
                ?>
                
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Animals
                        <span class="badge bg-primary rounded-pill"><?= $totalAnimals ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Currently Alive
                        <span class="badge bg-success rounded-pill"><?= $aliveAnimals ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Animal Types
                        <span class="badge bg-info rounded-pill"><?= $animalTypes ?></span>
                    </li>
                </ul>
                
                <div class="d-grid mt-3">
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-house-door me-2"></i> Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>