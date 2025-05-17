<?php
/**
 * Animal Add Page
 * 
 * This page allows users to add a new animal to their inventory.
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

// Pre-populate type if provided in URL
$preselected_type = isset($_GET['type']) ? $_GET['type'] : '';

// Set page variables
$page_title = "Add New Animal";
$page_header = "Add New Animal";
$page_subheader = "Add a new animal to your farm inventory";

// Get all possible dams (female animals) for parent selection dropdown
$damStmt = $db->prepare("
    SELECT id, name, number 
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
    SELECT id, name, number 
    FROM animals 
    WHERE user_id = :user_id 
    AND gender = 'Male' 
    ORDER BY name ASC
");
$sireStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$sireStmt->execute();
$sires = $sireStmt->fetchAll();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $breed = filter_input(INPUT_POST, 'breed', FILTER_SANITIZE_STRING);
    $number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_STRING);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_STRING) ?: null;
    $dod = filter_input(INPUT_POST, 'dod', FILTER_SANITIZE_STRING) ?: null;
    $dam_id = filter_input(INPUT_POST, 'dam_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $sire_id = filter_input(INPUT_POST, 'sire_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $date_purchased = filter_input(INPUT_POST, 'date_purchased', FILTER_SANITIZE_STRING) ?: null;
    $date_sold = filter_input(INPUT_POST, 'date_sold', FILTER_SANITIZE_STRING) ?: null;
    $sell_price = filter_input(INPUT_POST, 'sell_price', FILTER_SANITIZE_STRING) ?: null;
    $sell_info = filter_input(INPUT_POST, 'sell_info', FILTER_SANITIZE_STRING);
    $purch_cost = filter_input(INPUT_POST, 'purch_cost', FILTER_SANITIZE_STRING) ?: null;
    $purch_info = filter_input(INPUT_POST, 'purch_info', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $meds = filter_input(INPUT_POST, 'meds', FILTER_SANITIZE_STRING);
    $for_sale = filter_input(INPUT_POST, 'for_sale', FILTER_SANITIZE_STRING);
    $reg_num = filter_input(INPUT_POST, 'reg_num', FILTER_SANITIZE_STRING);
    $reg_name = filter_input(INPUT_POST, 'reg_name', FILTER_SANITIZE_STRING);
    $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_STRING);
    
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
    
    // If no errors, add the animal
    if (empty($errors)) {
        // Handle image upload if provided
        $fileName = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 262144; // 2MB max file size
            
            if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] <= $maxSize) {
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $uploadPath = 'assets/img/animals/' . $fileName;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $errors[] = "Failed to upload image. Please try again.";
                    $fileName = null;
                }
            } else {
                $errors[] = "Invalid image. Please upload a JPEG, PNG, or GIF file under 2MB.";
                $fileName = null;
            }
        }
        
        // If still no errors, proceed with database insertion
        if (empty($errors)) {
            // Prepare the SQL statement
            $insertQuery = "
                INSERT INTO animals (
                    type, breed, number, name, gender, dob, dod, 
                    dam_id, sire_id, status, date_purchased, date_sold, 
                    sell_price, sell_info, purch_cost, purch_info, 
                    notes, meds, for_sale, reg_num, reg_name, 
                    color, image, user_id, created_at, updated_at
                ) VALUES (
                    :type, :breed, :number, :name, :gender, :dob, :dod, 
                    :dam_id, :sire_id, :status, :date_purchased, :date_sold, 
                    :sell_price, :sell_info, :purch_cost, :purch_info, 
                    :notes, :meds, :for_sale, :reg_num, :reg_name, 
                    :color, :image, :user_id, NOW(), NOW()
                )
            ";
            
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bindParam(':type', $type);
            $insertStmt->bindParam(':breed', $breed);
            $insertStmt->bindParam(':number', $number);
            $insertStmt->bindParam(':name', $name);
            $insertStmt->bindParam(':gender', $gender);
            $insertStmt->bindParam(':dob', $dob);
            $insertStmt->bindParam(':dod', $dod);
            $insertStmt->bindParam(':dam_id', $dam_id);
            $insertStmt->bindParam(':sire_id', $sire_id);
            $insertStmt->bindParam(':status', $status);
            $insertStmt->bindParam(':date_purchased', $date_purchased);
            $insertStmt->bindParam(':date_sold', $date_sold);
            $insertStmt->bindParam(':sell_price', $sell_price);
            $insertStmt->bindParam(':sell_info', $sell_info);
            $insertStmt->bindParam(':purch_cost', $purch_cost);
            $insertStmt->bindParam(':purch_info', $purch_info);
            $insertStmt->bindParam(':notes', $notes);
            $insertStmt->bindParam(':meds', $meds);
            $insertStmt->bindParam(':for_sale', $for_sale);
            $insertStmt->bindParam(':reg_num', $reg_num);
            $insertStmt->bindParam(':reg_name', $reg_name);
            $insertStmt->bindParam(':color', $color);
            $insertStmt->bindParam(':image', $fileName);
            $insertStmt->bindParam(':user_id', $current_user);
            
            // Execute the insert statement
            if ($insertStmt->execute()) {
                $animal_id = $db->lastInsertId();
                $success = true;
                $_SESSION['alert_message'] = "Animal added successfully!";
                $_SESSION['alert_type'] = "success";
                
                // Redirect to the animal view page
                header("location: animal_view.php?id=" . $animal_id);
                exit;
            } else {
                $errors[] = "An error occurred while adding the animal. Please try again.";
            }
        }
    }
}

// Get return URL based on animal type
$returnUrl = 'animal_list.php';
if (!empty($preselected_type)) {
    $returnUrl .= '?type=' . urlencode($preselected_type);
}

// Include header
include_once 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col">
        <a href="<?= $returnUrl ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h5><i class="bi bi-exclamation-triangle-fill"></i> Please fix the following errors:</h5>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="card-title mb-0">Add New Animal</h3>
    </div>
    <div class="card-body">
        <form action="animal_add.php" method="post" enctype="multipart/form-data">
            <div class="row">
                <!-- Basic Information -->
                <div class="col-md-6">
                    <h4>Basic Information</h4>
                    
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
                    
                    <div class="mb-3">
                        <label for="color" class="form-label">Color Description</label>
                        <input type="text" id="color" name="color" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender *</label>
                        <select id="gender" name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
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
                
                <!-- Dates and Lineage Information -->
                <div class="col-md-6">
                    <h4>Dates & Lineage</h4>
                    
                    <div class="mb-3">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" id="dob" name="dob" class="form-control">
                    </div>
                    
                    <div class="mb-3 status-dependent" data-status="Dead,Harvested">
                        <label for="dod" class="form-label">Date of Death/Dispatch</label>
                        <input type="date" id="dod" name="dod" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label for="dam_id" class="form-label">Dam (Mother)</label>
                        <select id="dam_id" name="dam_id" class="form-select">
                            <option value="">None Selected</option>
                            <?php foreach ($dams as $dam): ?>
                            <option value="<?= $dam['id'] ?>">
                                <?= htmlspecialchars($dam['name']) ?> (<?= htmlspecialchars($dam['number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sire_id" class="form-label">Sire (Father)</label>
                        <select id="sire_id" name="sire_id" class="form-select">
                            <option value="">None Selected</option>
                            <?php foreach ($sires as $sire): ?>
                            <option value="<?= $sire['id'] ?>">
                                <?= htmlspecialchars($sire['name']) ?> (<?= htmlspecialchars($sire['number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <h4 class="mt-4">Registration</h4>
                    
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
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <!-- Purchase Information -->
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
                </div>
                
                <!-- Sale Information -->
                <div class="col-md-6">
                    <h4>Sale Information</h4>
                    
                    <div class="mb-3 status-dependent" data-status="Sold">
                        <label for="date_sold" class="form-label">Date Sold</label>
                        <input type="date" id="date_sold" name="date_sold" class="form-control">
                    </div>
                    
                    <div class="mb-3 status-dependent" data-status="Sold,For Sale">
                        <label for="sell_price" class="form-label">Sale Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" id="sell_price" name="sell_price" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3 status-dependent" data-status="Sold">
                        <label for="sell_info" class="form-label">Buyer Information</label>
                        <input type="text" id="sell_info" name="sell_info" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <!-- Notes and Medications -->
                <div class="col-md-6">
                    <h4>Notes</h4>
                    <div class="mb-3">
                        <textarea id="notes" name="notes" class="form-control" rows="5"></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h4>Medication History</h4>
                    <div class="mb-3">
                        <textarea id="meds" name="meds" class="form-control" rows="5"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Add Animal
                    </button>
                    <a href="<?= $returnUrl ?>" class="btn btn-secondary btn-lg ms-2">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Show/hide fields based on status selection
document.addEventListener('DOMContentLoaded', function() {
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
    }
    
    // Initial update
    updateFieldVisibility();
    
    // Update on status change
    statusSelect.addEventListener('change', updateFieldVisibility);
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>