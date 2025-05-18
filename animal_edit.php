<?php
/**
 * Animal Edit Page - FIXED
 * 
 * This page allows users to edit an existing animal's information.
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

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert_message'] = "No animal specified";
    $_SESSION['alert_type'] = "danger";
    header("location: animal_list.php");
    exit;
}

$id = intval($_GET['id']);
$current_user = $_SESSION["username"];

// Get database connection
$db = getDbConnection();

// Fetch the animal data
$stmt = $db->prepare("
    SELECT * FROM animals 
    WHERE id = :id AND user_id = :user_id
");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$stmt->execute();

// Check if animal exists and belongs to current user
if ($stmt->rowCount() === 0) {
    $_SESSION['alert_message'] = "Animal not found or you don't have permission to edit it.";
    $_SESSION['alert_type'] = "danger";
    header("location: animal_list.php");
    exit;
}

// Fetch animal data
$animal = $stmt->fetch();

// Set page variables
$page_title = "Edit Animal: " . $animal['name'];
$page_header = "Edit " . $animal['name'] . " (" . $animal['number'] . ")";
$page_subheader = "Update information for this " . strtolower($animal['type']);

// Get all possible dams (female animals) for parent selection dropdown
$damStmt = $db->prepare("
    SELECT id, name, number 
    FROM animals 
    WHERE user_id = :user_id 
    AND gender = 'Female' 
    AND id != :id
    ORDER BY name ASC
");
$damStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$damStmt->bindParam(':id', $id, PDO::PARAM_INT);
$damStmt->execute();
$dams = $damStmt->fetchAll();

// Get all possible sires (male animals) for parent selection dropdown
$sireStmt = $db->prepare("
    SELECT id, name, number 
    FROM animals 
    WHERE user_id = :user_id 
    AND gender = 'Male' 
    AND id != :id
    ORDER BY name ASC
");
$sireStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$sireStmt->bindParam(':id', $id, PDO::PARAM_INT);
$sireStmt->execute();
$sires = $sireStmt->fetchAll();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $breed = filter_input(INPUT_POST, 'breed', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $dod = filter_input(INPUT_POST, 'dod', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $dam_id = filter_input(INPUT_POST, 'dam_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $sire_id = filter_input(INPUT_POST, 'sire_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $date_purchased = filter_input(INPUT_POST, 'date_purchased', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $date_sold = filter_input(INPUT_POST, 'date_sold', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $sell_price = filter_input(INPUT_POST, 'sell_price', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $sell_info = filter_input(INPUT_POST, 'sell_info', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $purch_cost = filter_input(INPUT_POST, 'purch_cost', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
    $purch_info = filter_input(INPUT_POST, 'purch_info', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // FIX: Better handling for notes and medication fields
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $meds = isset($_POST['meds']) ? trim($_POST['meds']) : '';
    
    $for_sale = filter_input(INPUT_POST, 'for_sale', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $reg_num = filter_input(INPUT_POST, 'reg_num', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $reg_name = filter_input(INPUT_POST, 'reg_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
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
    
    // If no errors, update the animal
    if (empty($errors)) {
        try {
            // Prepare the SQL statement
            $updateQuery = "
                UPDATE animals SET
                    type = :type,
                    breed = :breed,
                    number = :number,
                    name = :name,
                    gender = :gender,
                    dob = :dob,
                    dod = :dod,
                    dam_id = :dam_id,
                    sire_id = :sire_id,
                    status = :status,
                    date_purchased = :date_purchased,
                    date_sold = :date_sold,
                    sell_price = :sell_price,
                    sell_info = :sell_info,
                    purch_cost = :purch_cost,
                    purch_info = :purch_info,
                    notes = :notes,
                    meds = :meds,
                    for_sale = :for_sale,
                    reg_num = :reg_num,
                    reg_name = :reg_name,
                    color = :color,
                    updated_at = NOW()
                WHERE id = :id AND user_id = :user_id
            ";
            
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':type', $type);
            $updateStmt->bindParam(':breed', $breed);
            $updateStmt->bindParam(':number', $number);
            $updateStmt->bindParam(':name', $name);
            $updateStmt->bindParam(':gender', $gender);
            $updateStmt->bindParam(':dob', $dob);
            $updateStmt->bindParam(':dod', $dod);
            $updateStmt->bindParam(':dam_id', $dam_id);
            $updateStmt->bindParam(':sire_id', $sire_id);
            $updateStmt->bindParam(':status', $status);
            $updateStmt->bindParam(':date_purchased', $date_purchased);
            $updateStmt->bindParam(':date_sold', $date_sold);
            $updateStmt->bindParam(':sell_price', $sell_price);
            $updateStmt->bindParam(':sell_info', $sell_info);
            $updateStmt->bindParam(':purch_cost', $purch_cost);
            $updateStmt->bindParam(':purch_info', $purch_info);
            
            // FIX: Correctly bind notes and meds as PDO::PARAM_STR
            $updateStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $updateStmt->bindParam(':meds', $meds, PDO::PARAM_STR);
            
            $updateStmt->bindParam(':for_sale', $for_sale);
            $updateStmt->bindParam(':reg_num', $reg_num);
            $updateStmt->bindParam(':reg_name', $reg_name);
            $updateStmt->bindParam(':color', $color);
            $updateStmt->bindParam(':id', $id);
            $updateStmt->bindParam(':user_id', $current_user);
            
            // Handle image upload if a new image was provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxSize = 2 * 1024 * 1024; // 2MB max file size
                
                if (in_array($_FILES['image']['type'], $allowedTypes) && $_FILES['image']['size'] <= $maxSize) {
                    $fileName = time() . '_' . basename($_FILES['image']['name']);
                    $uploadPath = 'assets/img/animals/' . $fileName;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                        // Update the image field in the database
                        $imageStmt = $db->prepare("
                            UPDATE animals SET image = :image WHERE id = :id AND user_id = :user_id
                        ");
                        $imageStmt->bindParam(':image', $fileName);
                        $imageStmt->bindParam(':id', $id);
                        $imageStmt->bindParam(':user_id', $current_user);
                        $imageStmt->execute();
                    } else {
                        $errors[] = "Failed to upload image. Please try again.";
                    }
                } else {
                    $errors[] = "Invalid image. Please upload a JPEG, PNG, or GIF file under 2MB.";
                }
            }
            
            // FIX: Better error handling for execute
            if (!$updateStmt->execute()) {
                $errors[] = "Database error: " . implode(", ", $updateStmt->errorInfo());
                error_log("Update animal error: " . print_r($updateStmt->errorInfo(), true));
            } else {
                $success = true;
                $_SESSION['alert_message'] = "Animal information updated successfully!";
                $_SESSION['alert_type'] = "success";
                
                // Redirect to animal view page
                header("location: animal_view.php?id=" . $id);
                exit;
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
            error_log("Exception in animal_edit.php: " . $e->getMessage());
        }
    }
}

// Get return URL based on animal type
$returnUrl = 'animal_list.php';
if (!empty($animal['type'])) {
    $returnUrl .= '?type=' . urlencode($animal['type']);
}

// Include header
include_once 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <a href="<?= $returnUrl ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
    <div class="col-md-6 text-end">
        <a href="animal_view.php?id=<?= $id ?>" class="btn btn-outline-primary">
            <i class="bi bi-eye"></i> View Animal
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
        <h3 class="card-title mb-0">Edit Animal Information</h3>
    </div>
    <div class="card-body">
        <form action="animal_edit.php?id=<?= $id ?>" method="post" enctype="multipart/form-data">
            <div class="row">
                <!-- Basic Information -->
                <div class="col-md-6">
                    <h4>Basic Information</h4>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Type *</label>
                        <select id="type" name="type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="Sheep" <?= $animal['type'] === 'Sheep' ? 'selected' : '' ?>>Sheep</option>
                            <option value="Chicken" <?= $animal['type'] === 'Chicken' ? 'selected' : '' ?>>Chicken</option>
                            <option value="Turkey" <?= $animal['type'] === 'Turkey' ? 'selected' : '' ?>>Turkey</option>
                            <option value="Pig" <?= $animal['type'] === 'Pig' ? 'selected' : '' ?>>Pig</option>
                            <option value="Cow" <?= $animal['type'] === 'Cow' ? 'selected' : '' ?>>Cow</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="breed" class="form-label">Breed</label>
                        <input type="text" id="breed" name="breed" class="form-control" 
                               value="<?= htmlspecialchars($animal['breed']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="number" class="form-label">Number *</label>
                        <input type="text" id="number" name="number" class="form-control" required
                               value="<?= htmlspecialchars($animal['number']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required
                               value="<?= htmlspecialchars($animal['name']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="color" class="form-label">Color Description</label>
                        <input type="text" id="color" name="color" class="form-control"
                               value="<?= htmlspecialchars($animal['color']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender *</label>
                        <select id="gender" name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?= $animal['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $animal['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status *</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="">Select Status</option>
                            <option value="Alive" <?= $animal['status'] === 'Alive' ? 'selected' : '' ?>>Alive</option>
                            <option value="Dead" <?= $animal['status'] === 'Dead' ? 'selected' : '' ?>>Dead</option>
                            <option value="Sold" <?= $animal['status'] === 'Sold' ? 'selected' : '' ?>>Sold</option>
                            <option value="For Sale" <?= $animal['status'] === 'For Sale' ? 'selected' : '' ?>>For Sale</option>
                            <option value="Harvested" <?= $animal['status'] === 'Harvested' ? 'selected' : '' ?>>Harvested</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="for_sale" class="form-label">List for Sale?</label>
                        <select id="for_sale" name="for_sale" class="form-select">
                            <option value="No" <?= $animal['for_sale'] === 'No' ? 'selected' : '' ?>>No</option>
                            <option value="Yes" <?= $animal['for_sale'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                            <option value="Has Been Sold" <?= $animal['for_sale'] === 'Has Been Sold' ? 'selected' : '' ?>>Has Been Sold</option>
                        </select>
                    </div>
                </div>
                
                <!-- Dates and Lineage Information -->
                <div class="col-md-6">
                    <h4>Dates & Lineage</h4>
                    
                    <div class="mb-3">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" id="dob" name="dob" class="form-control" 
                               value="<?= htmlspecialchars($animal['dob']) ?>">
                    </div>
                    
                    <div class="mb-3 status-dependent" data-status="Dead,Harvested">
                        <label for="dod" class="form-label">Date of Death/Dispatch</label>
                        <input type="date" id="dod" name="dod" class="form-control" 
                               value="<?= htmlspecialchars($animal['dod']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="dam_id" class="form-label">Dam (Mother)</label>
                        <select id="dam_id" name="dam_id" class="form-select">
                            <option value="">None Selected</option>
                            <?php foreach ($dams as $dam): ?>
                            <option value="<?= $dam['id'] ?>" <?= $animal['dam_id'] == $dam['id'] ? 'selected' : '' ?>>
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
                            <option value="<?= $sire['id'] ?>" <?= $animal['sire_id'] == $sire['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sire['name']) ?> (<?= htmlspecialchars($sire['number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <h4 class="mt-4">Registration</h4>
                    
                    <div class="mb-3">
                        <label for="reg_num" class="form-label">Registration Number</label>
                        <input type="text" id="reg_num" name="reg_num" class="form-control" 
                               value="<?= htmlspecialchars($animal['reg_num']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="reg_name" class="form-label">Registration Name</label>
                        <input type="text" id="reg_name" name="reg_name" class="form-control" 
                               value="<?= htmlspecialchars($animal['reg_name']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Animal Image</label>
                        <?php if (!empty($animal['image'])): ?>
                        <div class="mb-2">
                            <img src="assets/img/animals/<?= htmlspecialchars($animal['image']) ?>" 
                                 alt="Current image" class="img-thumbnail" style="max-height: 100px;">
                        </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        <div class="form-text">Upload a new image to replace the current one. Leave empty to keep the existing image.</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <!-- Purchase Information -->
                <div class="col-md-6">
                    <h4>Purchase Information</h4>
                    
                    <div class="mb-3">
                        <label for="date_purchased" class="form-label">Date Purchased</label>
                        <input type="date" id="date_purchased" name="date_purchased" class="form-control" 
                               value="<?= htmlspecialchars($animal['date_purchased']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="purch_cost" class="form-label">Purchase Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" id="purch_cost" name="purch_cost" class="form-control" 
                                   value="<?= htmlspecialchars($animal['purch_cost']) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="purch_info" class="form-label">Seller Information</label>
                        <input type="text" id="purch_info" name="purch_info" class="form-control" 
                               value="<?= htmlspecialchars($animal['purch_info']) ?>">
                    </div>
                </div>
                
                <!-- Sale Information -->
                <div class="col-md-6">
                    <h4>Sale Information</h4>
                    
                    <div class="mb-3 status-dependent" data-status="Sold">
                        <label for="date_sold" class="form-label">Date Sold</label>
                        <input type="date" id="date_sold" name="date_sold" class="form-control" 
                               value="<?= htmlspecialchars($animal['date_sold']) ?>">
                    </div>
                    
                    <div class="mb-3 status-dependent" data-status="Sold,For Sale">
                        <label for="sell_price" class="form-label">Sale Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" id="sell_price" name="sell_price" class="form-control" 
                                   value="<?= htmlspecialchars($animal['sell_price']) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3 status-dependent" data-status="Sold">
                        <label for="sell_info" class="form-label">Buyer Information</label>
                        <input type="text" id="sell_info" name="sell_info" class="form-control" 
                               value="<?= htmlspecialchars($animal['sell_info']) ?>">
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <!-- Notes and Medications -->
                <div class="col-md-6">
                    <h4>Notes</h4>
                    <div class="mb-3">
                        <textarea id="notes" name="notes" class="form-control" rows="5"><?= htmlspecialchars($animal['notes']) ?></textarea>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h4>Medication History</h4>
                    <div class="mb-3">
                        <textarea id="meds" name="meds" class="form-control" rows="5"><?= htmlspecialchars($animal['meds']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <a href="animal_view.php?id=<?= $id ?>" class="btn btn-secondary btn-lg ms-2">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Fixed Script for Status-Dependent Fields -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide fields based on status selection
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
    
    // Initial update
    if (statusSelect) {
        updateFieldVisibility();
        
        // Update on status change
        statusSelect.addEventListener('change', updateFieldVisibility);
        
        // Dynamic behavior for the for-sale toggle
        const forSaleSelect = document.getElementById('for_sale');
        if (forSaleSelect) {
            forSaleSelect.addEventListener('change', function() {
                if (this.value === 'Yes') {
                    // If marked for sale, update status
                    statusSelect.value = 'For Sale';
                    updateFieldVisibility();
                }
            });
        }
    }
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>