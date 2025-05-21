<?php
/**
 * Animal Add Page - ENHANCED VERSION
 * 
 * This page allows users to add a new animal to their inventory.
 * Enhanced with structured medication and notes entries.
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Pre-populate type if provided in URL
$preselected_type = isset($_GET['type']) ? $_GET['type'] : '';

// Set page variables
$page_title = "Add New Animal";
$page_header = "Add New Animal";
$page_subheader = "Add a new animal to your farm inventory";

// Get database connection
$db = getDbConnection();

// Process form submission
$errors = [];
$success = false;
$new_animal_id = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $breed = filter_input(INPUT_POST, 'breed', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $dod = filter_input(INPUT_POST, 'dod', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $dam_id = filter_input(INPUT_POST, 'dam_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $sire_id = filter_input(INPUT_POST, 'sire_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $reg_num = filter_input(INPUT_POST, 'reg_num', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $reg_name = filter_input(INPUT_POST, 'reg_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $date_purchased = filter_input(INPUT_POST, 'date_purchased', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $purch_cost = filter_input(INPUT_POST, 'purch_cost', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $purch_info = filter_input(INPUT_POST, 'purch_info', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $date_sold = filter_input(INPUT_POST, 'date_sold', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $sell_price = filter_input(INPUT_POST, 'sell_price', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $sell_info = filter_input(INPUT_POST, 'sell_info', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $for_sale = filter_input(INPUT_POST, 'for_sale', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // Check for initial medication and notes entries
    $initial_med_date = isset($_POST['initial_med_date']) ? $_POST['initial_med_date'] : date('Y-m-d');
    $initial_med_type = isset($_POST['initial_med_type']) ? $_POST['initial_med_type'] : '';
    $initial_med_amount = isset($_POST['initial_med_amount']) ? $_POST['initial_med_amount'] : '';
    $initial_med_notes = isset($_POST['initial_med_notes']) ? $_POST['initial_med_notes'] : '';
    
    $initial_note_date = isset($_POST['initial_note_date']) ? $_POST['initial_note_date'] : date('Y-m-d');
    $initial_note_title = isset($_POST['initial_note_title']) ? $_POST['initial_note_title'] : '';
    $initial_note_content = isset($_POST['initial_note_content']) ? $_POST['initial_note_content'] : '';
    
    $add_initial_medication = !empty($initial_med_type) && !empty($initial_med_amount);
    $add_initial_note = !empty($initial_note_title) && !empty($initial_note_content);
    
    // Validate required fields
    if (empty($type)) {
        $errors[] = "Type is required";
    }
    if (empty($number)) {
        $errors[] = "Number is required";
    }
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($gender)) {
        $errors[] = "Gender is required";
    }
    if (empty($status)) {
        $errors[] = "Status is required";
    }
    
    // Handle image upload if provided
    $fileName = null;
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Debug image upload
        error_log("Image upload info: " . print_r($_FILES['image'], true));
        
        // Make sure uploads directory exists
        $uploadDir = 'assets/img/animals/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $errors[] = "Failed to create upload directory. Please contact the administrator.";
            }
        }
        
        // Check for upload errors
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            switch ($_FILES['image']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "The uploaded file exceeds the maximum allowed size.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = "The file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errors[] = "Missing a temporary folder on the server.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errors[] = "A PHP extension stopped the file upload.";
                    break;
                default:
                    $errors[] = "Unknown upload error occurred.";
            }
        } else {
            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['image']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Invalid file type. Only JPEG, PNG, and GIF images are allowed.";
            } else {
                // Check file size (limit to 5MB)
                $maxSize = 5 * 1024 * 1024;
                if ($_FILES['image']['size'] > $maxSize) {
                    $errors[] = "File is too large. Maximum size is 5MB.";
                } else {
                    // Generate unique filename
                    $fileExt = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $fileName = time() . '_' . uniqid() . '.' . $fileExt;
                    $uploadPath = $uploadDir . $fileName;
                    
                    // Attempt to move the uploaded file
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                        $errors[] = "Failed to move uploaded file. Check directory permissions.";
                        error_log("Failed to move uploaded file to: $uploadPath");
                        $fileName = null;
                    }
                }
            }
        }
    }
    
    // If no errors, add the animal and optional initial notes/medications
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Prepare the SQL statement
            $insertQuery = "
                INSERT INTO animals (
                    type, breed, number, name, gender, color, dob, dod, 
                    dam_id, sire_id, status, reg_num, reg_name, 
                    date_purchased, purch_cost, purch_info, 
                    date_sold, sell_price, sell_info, 
                    for_sale, image, 
                    user_id, created_at, updated_at
                ) VALUES (
                    :type, :breed, :number, :name, :gender, :color, :dob, :dod, 
                    :dam_id, :sire_id, :status, :reg_num, :reg_name, 
                    :date_purchased, :purch_cost, :purch_info, 
                    :date_sold, :sell_price, :sell_info, 
                    :for_sale, :image, 
                    :user_id, NOW(), NOW()
                )
            ";
            
            $insertStmt = $db->prepare($insertQuery);
            
            // Bind parameters
            $insertStmt->bindParam(':type', $type);
            $insertStmt->bindParam(':breed', $breed);
            $insertStmt->bindParam(':number', $number);
            $insertStmt->bindParam(':name', $name);
            $insertStmt->bindParam(':gender', $gender);
            $insertStmt->bindParam(':color', $color);
            $insertStmt->bindParam(':dob', $dob);
            $insertStmt->bindParam(':dod', $dod);
            $insertStmt->bindParam(':dam_id', $dam_id);
            $insertStmt->bindParam(':sire_id', $sire_id);
            $insertStmt->bindParam(':status', $status);
            $insertStmt->bindParam(':reg_num', $reg_num);
            $insertStmt->bindParam(':reg_name', $reg_name);
            $insertStmt->bindParam(':date_purchased', $date_purchased);
            $insertStmt->bindParam(':purch_cost', $purch_cost);
            $insertStmt->bindParam(':purch_info', $purch_info);
            $insertStmt->bindParam(':date_sold', $date_sold);
            $insertStmt->bindParam(':sell_price', $sell_price);
            $insertStmt->bindParam(':sell_info', $sell_info);
            $insertStmt->bindParam(':for_sale', $for_sale);
            $insertStmt->bindParam(':image', $fileName);
            $insertStmt->bindParam(':user_id', $current_user);
            
            // Execute animal insert
            if (!$insertStmt->execute()) {
                throw new Exception("Database error: " . implode(", ", $insertStmt->errorInfo()));
            }
            
            // Get the new animal ID
            $new_animal_id = $db->lastInsertId();
            
            // Add initial medication if provided
            if ($add_initial_medication) {
                $medStmt = $db->prepare("
                    INSERT INTO animal_medications (animal_id, date, type, amount, notes)
                    VALUES (:animal_id, :date, :type, :amount, :notes)
                ");
                $medStmt->bindParam(':animal_id', $new_animal_id, PDO::PARAM_INT);
                $medStmt->bindParam(':date', $initial_med_date, PDO::PARAM_STR);
                $medStmt->bindParam(':type', $initial_med_type, PDO::PARAM_STR);
                $medStmt->bindParam(':amount', $initial_med_amount, PDO::PARAM_STR);
                $medStmt->bindParam(':notes', $initial_med_notes, PDO::PARAM_STR);
                
                if (!$medStmt->execute()) {
                    throw new Exception("Error adding medication entry: " . implode(", ", $medStmt->errorInfo()));
                }
            }
            
            // Add initial note if provided
            if ($add_initial_note) {
                $noteStmt = $db->prepare("
                    INSERT INTO animal_notes (animal_id, date, title, content)
                    VALUES (:animal_id, :date, :title, :content)
                ");
                $noteStmt->bindParam(':animal_id', $new_animal_id, PDO::PARAM_INT);
                $noteStmt->bindParam(':date', $initial_note_date, PDO::PARAM_STR);
                $noteStmt->bindParam(':title', $initial_note_title, PDO::PARAM_STR);
                $noteStmt->bindParam(':content', $initial_note_content, PDO::PARAM_STR);
                
                if (!$noteStmt->execute()) {
                    throw new Exception("Error adding note entry: " . implode(", ", $noteStmt->errorInfo()));
                }
            }
            
            // Commit transaction
            $db->commit();
            
            $success = true;
            $_SESSION['alert_message'] = "Animal added successfully!";
            $_SESSION['alert_type'] = "success";
            
            // Redirect to the animal view page
            header("location: animal_view.php?id=" . $new_animal_id);
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            
            error_log('Animal Add Error: ' . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
            
            // If there was an error and we uploaded an image, delete it
            if ($fileName && file_exists($uploadDir . $fileName)) {
                unlink($uploadDir . $fileName);
            }
        }
    } else {
        // If there are validation errors and we uploaded an image, delete it
        if ($fileName && file_exists($uploadDir . $fileName)) {
            unlink($uploadDir . $fileName);
        }
    }
}

// Get parent options for selection
try {
    // Get all possible dams (female animals) for parent selection dropdown
    $damStmt = $db->prepare("
        SELECT id, name, number, type
        FROM animals 
        WHERE user_id = :user_id 
        AND gender = 'Female' 
        ORDER BY name ASC
    ");
    $damStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $damStmt->execute();
    $dams = $damStmt->fetchAll();

    // Get all possible sires (male animals) for parent selection dropdown
    $sireStmt = $db->prepare("
        SELECT id, name, number, type
        FROM animals 
        WHERE user_id = :user_id 
        AND gender = 'Male' 
        ORDER BY name ASC
    ");
    $sireStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $sireStmt->execute();
    $sires = $sireStmt->fetchAll();
} catch (Exception $e) {
    error_log('Animal Add Error: ' . $e->getMessage());
    $errors[] = "Error loading parent options: " . $e->getMessage();
}

// Include header
include_once 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    <div class="col-md-6">
        <h2><?= $page_header ?></h2>
        <p class="lead"><?= $page_subheader ?></p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h5><i class="bi bi-exclamation-triangle-fill me-2"></i> Please fix the following errors:</h5>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="animalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
            <i class="bi bi-card-list"></i> Basic Information
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="additional-tab" data-bs-toggle="tab" data-bs-target="#additional" type="button" role="tab" aria-controls="additional" aria-selected="false">
            <i class="bi bi-file-earmark-plus"></i> Additional Information
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="initial-entries-tab" data-bs-toggle="tab" data-bs-target="#initial-entries" type="button" role="tab" aria-controls="initial-entries" aria-selected="false">
            <i class="bi bi-journal-medical"></i> Initial Entries
        </button>
    </li>
</ul>

<form action="animal_add.php" method="post" enctype="multipart/form-data" id="addAnimalForm">
    <div class="tab-content" id="animalTabsContent">
        <!-- Basic Information Tab -->
        <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="card-title mb-0">Basic Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type *</label>
                                <select id="type" name="type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="Sheep" <?= $preselected_type === 'Sheep' ? 'selected' : '' ?>>Sheep</option>
                                    <option value="Chicken" <?= $preselected_type === 'Chicken' ? 'selected' : '' ?>>Chicken</option>
                                    <option value="Turkey" <?= $preselected_type === 'Turkey' ? 'selected' : '' ?>>Turkey</option>
                                    <option value="Pig" <?= $preselected_type === 'Pig' ? 'selected' : '' ?>>Pig</option>
                                    <option value="Cow" <?= $preselected_type === 'Cow' ? 'selected' : '' ?>>Cow</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="breed" class="form-label">Breed</label>
                                <input type="text" id="breed" name="breed" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <label for="number" class="form-label">Number *</label>
                                <input type="text" id="number" name="number" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" id="name" name="name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender *</label>
                                <select id="gender" name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="color" class="form-label">Color Description</label>
                                <input type="text" id="color" name="color" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="">Select Status</option>
                                    <option value="Alive" selected>Alive</option>
                                    <option value="Dead">Dead</option>
                                    <option value="Sold">Sold</option>
                                    <option value="For Sale">For Sale</option>
                                    <option value="Harvested">Harvested</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="for_sale" class="form-label">List for Sale?</label>
                                <select id="for_sale" name="for_sale" class="form-select">
                                    <option value="No" selected>No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" id="dob" name="dob" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3 status-dependent" data-status="Dead,Harvested" style="display: none;">
                                <label for="dod" class="form-label">Date of Death/Dispatch</label>
                                <input type="date" id="dod" name="dod" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dam_id" class="form-label">Dam (Mother)</label>
                                <select id="dam_id" name="dam_id" class="form-select">
                                    <option value="">None Selected</option>
                                    <?php foreach ($dams as $dam): ?>
                                    <option value="<?= $dam['id'] ?>" data-type="<?= htmlspecialchars($dam['type']) ?>">
                                        <?= htmlspecialchars($dam['name']) ?> (<?= htmlspecialchars($dam['number']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sire_id" class="form-label">Sire (Father)</label>
                                <select id="sire_id" name="sire_id" class="form-select">
                                    <option value="">None Selected</option>
                                    <?php foreach ($sires as $sire): ?>
                                    <option value="<?= $sire['id'] ?>" data-type="<?= htmlspecialchars($sire['type']) ?>">
                                        <?= htmlspecialchars($sire['name']) ?> (<?= htmlspecialchars($sire['number']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-primary next-tab" data-next-tab="additional-tab">
                                    Next: Additional Information <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information Tab -->
        <div class="tab-pane fade" id="additional" role="tabpanel" aria-labelledby="additional-tab">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="card-title mb-0">Additional Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Registration Information -->
                        <div class="col-md-6">
                            <h4>Registration</h4>
                            
                            <div class="mb-3">
                                <label for="reg_num" class="form-label">Registration Number</label>
                                <input type="text" id="reg_num" name="reg_num" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <label for="reg_name" class="form-label">Registration Name</label>
                                <input type="text" id="reg_name" name="reg_name" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Animal Image</label>
                                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                                <div class="form-text">Maximum file size: 5MB. Accepted formats: JPEG, PNG, GIF</div>
                            </div>
                            
                            <div class="mb-3">
                                <div id="imagePreview" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <!-- Purchase/Sale Information -->
                        <div class="col-md-6">
                            <h4>Purchase Information</h4>
                            
                            <div class="mb-3">
                                <label for="date_purchased" class="form-label">Date Purchased</label>
                                <input type="date" id="date_purchased" name="date_purchased" class="form-control">
                            </div>
                            
                            <div class="mb-3">
                                <label for="purch_cost" class="form-label">Purchase Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" id="purch_cost" name="purch_cost" class="form-control">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="purch_info" class="form-label">Seller Information</label>
                                <input type="text" id="purch_info" name="purch_info" class="form-control">
                            </div>
                            
                            <h4 class="mt-4">Sale Information</h4>
                            
                            <div class="mb-3 status-dependent" data-status="Sold" style="display: none;">
                                <label for="date_sold" class="form-label">Date Sold</label>
                                <input type="date" id="date_sold" name="date_sold" class="form-control">
                            </div>
                            
                            <div class="mb-3 status-dependent" data-status="Sold,For Sale" style="display: none;">
                                <label for="sell_price" class="form-label">Sale Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" id="sell_price" name="sell_price" class="form-control">
                                </div>
                            </div>
                            
                            <div class="mb-3 status-dependent" data-status="Sold" style="display: none;">
                                <label for="sell_info" class="form-label">Buyer Information</label>
                                <input type="text" id="sell_info" name="sell_info" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary prev-tab" data-prev-tab="details-tab">
                                    <i class="bi bi-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary next-tab" data-next-tab="initial-entries-tab">
                                    Next: Initial Entries <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Initial Entries Tab -->
        <div class="tab-pane fade" id="initial-entries" role="tabpanel" aria-labelledby="initial-entries-tab">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="card-title mb-0">Initial Entries (Optional)</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Initial Medication Entry -->
                        <div class="col-md-6">
                            <h4>Initial Medication Entry</h4>
                            <p class="text-muted">Add an initial medication record for this animal (optional)</p>
                            
                            <div class="mb-3">
                                <label for="initial_med_date" class="form-label">Date</label>
                                <input type="date" id="initial_med_date" name="initial_med_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_med_type" class="form-label">Type</label>
                                <input type="text" id="initial_med_type" name="initial_med_type" class="form-control"
                                       placeholder="e.g., Vaccine, Antibiotic, Wormer">
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_med_amount" class="form-label">Amount</label>
                                <input type="text" id="initial_med_amount" name="initial_med_amount" class="form-control"
                                       placeholder="e.g., 10ml, 2 tablets, 1cc">
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_med_notes" class="form-label">Notes</label>
                                <textarea id="initial_med_notes" name="initial_med_notes" class="form-control" rows="3"
                                          placeholder="Optional additional details"></textarea>
                            </div>
                        </div>
                        
                        <!-- Initial Note Entry -->
                        <div class="col-md-6">
                            <h4>Initial Note Entry</h4>
                            <p class="text-muted">Add an initial note for this animal (optional)</p>
                            
                            <div class="mb-3">
                                <label for="initial_note_date" class="form-label">Date</label>
                                <input type="date" id="initial_note_date" name="initial_note_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_note_title" class="form-label">Title</label>
                                <input type="text" id="initial_note_title" name="initial_note_title" class="form-control"
                                       placeholder="Brief description of this note">
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_note_content" class="form-label">Content</label>
                                <textarea id="initial_note_content" name="initial_note_content" class="form-control" rows="5"
                                          placeholder="Enter detailed notes here..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary prev-tab" data-prev-tab="additional-tab">
                                    <i class="bi bi-arrow-left"></i> Previous
                                </button>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-plus-circle"></i> Add Animal
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- JavaScript for Tab Navigation, Image Preview, etc. -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation buttons
    document.querySelectorAll('.next-tab').forEach(function(button) {
        button.addEventListener('click', function() {
            const nextTabId = this.getAttribute('data-next-tab');
            const nextTab = document.getElementById(nextTabId);
            if (nextTab) {
                const tab = new bootstrap.Tab(nextTab);
                tab.show();
            }
        });
    });
    
    document.querySelectorAll('.prev-tab').forEach(function(button) {
        button.addEventListener('click', function() {
            const prevTabId = this.getAttribute('data-prev-tab');
            const prevTab = document.getElementById(prevTabId);
            if (prevTab) {
                const tab = new bootstrap.Tab(prevTab);
                tab.show();
            }
        });
    });
    
    // Status-dependent field visibility
    const statusSelect = document.getElementById('status');
    const statusDependentFields = document.querySelectorAll('.status-dependent');
    
    function updateFieldVisibility() {
        const selectedStatus = statusSelect.value;
        
        statusDependentFields.forEach(field => {
            const statusValues = field.getAttribute('data-status').split(',');
            if (statusValues.includes(selectedStatus)) {
                field.style.display = 'block';
            } else {
                field.style.display = 'none';
            }
        });
        
        // Update for_sale dropdown based on status
        const forSaleSelect = document.getElementById('for_sale');
        if (forSaleSelect) {
            if (selectedStatus === 'For Sale') {
                forSaleSelect.value = 'Yes';
            } else if (selectedStatus === 'Sold') {
                forSaleSelect.value = 'Has Been Sold';
            } else if (forSaleSelect.value === 'Yes' && selectedStatus !== 'For Sale') {
                forSaleSelect.value = 'No';
            }
        }
    }
    
    // Initial update and event listener
    if (statusSelect) {
        updateFieldVisibility();
        statusSelect.addEventListener('change', updateFieldVisibility);
        
        // For Sale toggle behavior
        const forSaleSelect = document.getElementById('for_sale');
        if (forSaleSelect) {
            forSaleSelect.addEventListener('change', function() {
                if (this.value === 'Yes') {
                    statusSelect.value = 'For Sale';
                    updateFieldVisibility();
                }
            });
        }
    }
    
    // Image preview
    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('imagePreview');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    imagePreview.innerHTML = `<img src="${e.target.result}" class="img-fluid img-thumbnail" style="max-height: 200px;">`;
                    imagePreview.style.display = 'block';
                };
                
                reader.readAsDataURL(this.files[0]);
            } else {
                imagePreview.innerHTML = '';
                imagePreview.style.display = 'none';
            }
        });
    }
    
    // Filter Dam and Sire options based on animal type
    const typeSelect = document.getElementById('type');
    const damSelect = document.getElementById('dam_id');
    const sireSelect = document.getElementById('sire_id');
    
    // Store all original options
    const damOptions = Array.from(damSelect.options);
    const sireOptions = Array.from(sireSelect.options);
    
    function filterParentOptions() {
        const selectedType = typeSelect.value;
        
        // Clear current options except the first "None Selected" option
        while (damSelect.options.length > 1) {
            damSelect.remove(1);
        }
        
        while (sireSelect.options.length > 1) {
            sireSelect.remove(1);
        }
        
        // If no type is selected, don't add any options
        if (!selectedType) {
            return;
        }
        
        // Add matching Dam options (skip the first "None Selected" option)
        damOptions.forEach(function(option, index) {
            // Skip the first option (None Selected) since we're keeping it
            if (index > 0 && option.dataset.type === selectedType) {
                const newOption = option.cloneNode(true);
                damSelect.add(newOption);
            }
        });
        
        // Add matching Sire options (skip the first "None Selected" option)
        sireOptions.forEach(function(option, index) {
            // Skip the first option (None Selected) since we're keeping it
            if (index > 0 && option.dataset.type === selectedType) {
                const newOption = option.cloneNode(true);
                sireSelect.add(newOption);
            }
        });
    }
    
    // Set up event listener for type change
    if (typeSelect) {
        typeSelect.addEventListener('change', filterParentOptions);
        
        // Initial filter on page load if type is pre-selected
        if (typeSelect.value) {
            filterParentOptions();
        }
    }
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>