<?php
/**
 * Animal Edit Page - ENHANCED VERSION
 * 
 * This page allows users to edit an existing animal's information.
 * Enhanced with structured medication and notes entries.
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
    SELECT id, name, number, type
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
    SELECT id, name, number, type
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

// Process medication and notes CRUD operations
// Add new medication entry
if (isset($_POST['add_medication'])) {
    $med_date = $_POST['med_date'] ?? date('Y-m-d');
    $med_type = $_POST['med_type'] ?? '';
    $med_amount = $_POST['med_amount'] ?? '';
    $med_notes = $_POST['med_notes'] ?? '';
    
    if (empty($med_type)) {
        $errors[] = "Medication type is required";
    } elseif (empty($med_amount)) {
        $errors[] = "Medication amount is required";
    } else {
        try {
            $medStmt = $db->prepare("
                INSERT INTO animal_medications (animal_id, date, type, amount, notes)
                VALUES (:animal_id, :date, :type, :amount, :notes)
            ");
            $medStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
            $medStmt->bindParam(':date', $med_date, PDO::PARAM_STR);
            $medStmt->bindParam(':type', $med_type, PDO::PARAM_STR);
            $medStmt->bindParam(':amount', $med_amount, PDO::PARAM_STR);
            $medStmt->bindParam(':notes', $med_notes, PDO::PARAM_STR);
            
            if ($medStmt->execute()) {
                $_SESSION['alert_message'] = "Medication entry added successfully!";
                $_SESSION['alert_type'] = "success";
                header("location: animal_edit.php?id=$id#medications");
                exit;
            } else {
                $errors[] = "Failed to add medication entry";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Delete medication entry
if (isset($_GET['delete_medication']) && !empty($_GET['delete_medication'])) {
    $med_id = intval($_GET['delete_medication']);
    
    try {
        // Verify the medication belongs to this animal
        $checkStmt = $db->prepare("
            SELECT id FROM animal_medications 
            WHERE id = :med_id AND animal_id = :animal_id
        ");
        $checkStmt->bindParam(':med_id', $med_id, PDO::PARAM_INT);
        $checkStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $deleteStmt = $db->prepare("DELETE FROM animal_medications WHERE id = :med_id");
            $deleteStmt->bindParam(':med_id', $med_id, PDO::PARAM_INT);
            
            if ($deleteStmt->execute()) {
                $_SESSION['alert_message'] = "Medication entry deleted successfully!";
                $_SESSION['alert_type'] = "success";
                header("location: animal_edit.php?id=$id#medications");
                exit;
            } else {
                $errors[] = "Failed to delete medication entry";
            }
        } else {
            $errors[] = "Medication entry not found or doesn't belong to this animal";
        }
    } catch (Exception $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Update medication entry
if (isset($_POST['update_medication'])) {
    $med_id = intval($_POST['med_id'] ?? 0);
    $med_date = $_POST['med_date'] ?? date('Y-m-d');
    $med_type = $_POST['med_type'] ?? '';
    $med_amount = $_POST['med_amount'] ?? '';
    $med_notes = $_POST['med_notes'] ?? '';
    
    if (empty($med_type)) {
        $errors[] = "Medication type is required";
    } elseif (empty($med_amount)) {
        $errors[] = "Medication amount is required";
    } else {
        try {
            // Verify the medication belongs to this animal
            $checkStmt = $db->prepare("
                SELECT id FROM animal_medications 
                WHERE id = :med_id AND animal_id = :animal_id
            ");
            $checkStmt->bindParam(':med_id', $med_id, PDO::PARAM_INT);
            $checkStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $updateStmt = $db->prepare("
                    UPDATE animal_medications 
                    SET date = :date, type = :type, amount = :amount, notes = :notes, updated_at = NOW()
                    WHERE id = :med_id
                ");
                $updateStmt->bindParam(':date', $med_date, PDO::PARAM_STR);
                $updateStmt->bindParam(':type', $med_type, PDO::PARAM_STR);
                $updateStmt->bindParam(':amount', $med_amount, PDO::PARAM_STR);
                $updateStmt->bindParam(':notes', $med_notes, PDO::PARAM_STR);
                $updateStmt->bindParam(':med_id', $med_id, PDO::PARAM_INT);
                
                if ($updateStmt->execute()) {
                    $_SESSION['alert_message'] = "Medication entry updated successfully!";
                    $_SESSION['alert_type'] = "success";
                    header("location: animal_edit.php?id=$id#medications");
                    exit;
                } else {
                    $errors[] = "Failed to update medication entry";
                }
            } else {
                $errors[] = "Medication entry not found or doesn't belong to this animal";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Add new note entry
if (isset($_POST['add_note'])) {
    $note_date = $_POST['note_date'] ?? date('Y-m-d');
    $note_title = $_POST['note_title'] ?? '';
    $note_content = $_POST['note_content'] ?? '';
    
    if (empty($note_title)) {
        $errors[] = "Note title is required";
    } elseif (empty($note_content)) {
        $errors[] = "Note content is required";
    } else {
        try {
            $noteStmt = $db->prepare("
                INSERT INTO animal_notes (animal_id, date, title, content)
                VALUES (:animal_id, :date, :title, :content)
            ");
            $noteStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
            $noteStmt->bindParam(':date', $note_date, PDO::PARAM_STR);
            $noteStmt->bindParam(':title', $note_title, PDO::PARAM_STR);
            $noteStmt->bindParam(':content', $note_content, PDO::PARAM_STR);
            
            if ($noteStmt->execute()) {
                $_SESSION['alert_message'] = "Note entry added successfully!";
                $_SESSION['alert_type'] = "success";
                header("location: animal_edit.php?id=$id#notes");
                exit;
            } else {
                $errors[] = "Failed to add note entry";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Delete note entry
if (isset($_GET['delete_note']) && !empty($_GET['delete_note'])) {
    $note_id = intval($_GET['delete_note']);
    
    try {
        // Verify the note belongs to this animal
        $checkStmt = $db->prepare("
            SELECT id FROM animal_notes 
            WHERE id = :note_id AND animal_id = :animal_id
        ");
        $checkStmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
        $checkStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $deleteStmt = $db->prepare("DELETE FROM animal_notes WHERE id = :note_id");
            $deleteStmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
            
            if ($deleteStmt->execute()) {
                $_SESSION['alert_message'] = "Note entry deleted successfully!";
                $_SESSION['alert_type'] = "success";
                header("location: animal_edit.php?id=$id#notes");
                exit;
            } else {
                $errors[] = "Failed to delete note entry";
            }
        } else {
            $errors[] = "Note entry not found or doesn't belong to this animal";
        }
    } catch (Exception $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Update note entry
if (isset($_POST['update_note'])) {
    $note_id = intval($_POST['note_id'] ?? 0);
    $note_date = $_POST['note_date'] ?? date('Y-m-d');
    $note_title = $_POST['note_title'] ?? '';
    $note_content = $_POST['note_content'] ?? '';
    
    if (empty($note_title)) {
        $errors[] = "Note title is required";
    } elseif (empty($note_content)) {
        $errors[] = "Note content is required";
    } else {
        try {
            // Verify the note belongs to this animal
            $checkStmt = $db->prepare("
                SELECT id FROM animal_notes 
                WHERE id = :note_id AND animal_id = :animal_id
            ");
            $checkStmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
            $checkStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $updateStmt = $db->prepare("
                    UPDATE animal_notes 
                    SET date = :date, title = :title, content = :content, updated_at = NOW()
                    WHERE id = :note_id
                ");
                $updateStmt->bindParam(':date', $note_date, PDO::PARAM_STR);
                $updateStmt->bindParam(':title', $note_title, PDO::PARAM_STR);
                $updateStmt->bindParam(':content', $note_content, PDO::PARAM_STR);
                $updateStmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
                
                if ($updateStmt->execute()) {
                    $_SESSION['alert_message'] = "Note entry updated successfully!";
                    $_SESSION['alert_type'] = "success";
                    header("location: animal_edit.php?id=$id#notes");
                    exit;
                } else {
                    $errors[] = "Failed to update note entry";
                }
            } else {
                $errors[] = "Note entry not found or doesn't belong to this animal";
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch existing medication entries
$medications = [];
try {
    $medStmt = $db->prepare("
        SELECT * FROM animal_medications
        WHERE animal_id = :animal_id
        ORDER BY date DESC
    ");
    $medStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
    $medStmt->execute();
    $medications = $medStmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching medication entries: " . $e->getMessage());
}

// Fetch existing note entries
$notes = [];
try {
    $noteStmt = $db->prepare("
        SELECT * FROM animal_notes
        WHERE animal_id = :animal_id
        ORDER BY date DESC
    ");
    $noteStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
    $noteStmt->execute();
    $notes = $noteStmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching note entries: " . $e->getMessage());
}

// Fetch a specific medication for editing if requested
$edit_medication = null;
if (isset($_GET['edit_medication']) && !empty($_GET['edit_medication'])) {
    $med_id = intval($_GET['edit_medication']);
    
    try {
        $editMedStmt = $db->prepare("
            SELECT * FROM animal_medications
            WHERE id = :med_id AND animal_id = :animal_id
        ");
        $editMedStmt->bindParam(':med_id', $med_id, PDO::PARAM_INT);
        $editMedStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
        $editMedStmt->execute();
        
        if ($editMedStmt->rowCount() > 0) {
            $edit_medication = $editMedStmt->fetch();
        }
    } catch (Exception $e) {
        error_log("Error fetching medication entry for editing: " . $e->getMessage());
    }
}

// Fetch a specific note for editing if requested
$edit_note = null;
if (isset($_GET['edit_note']) && !empty($_GET['edit_note'])) {
    $note_id = intval($_GET['edit_note']);
    
    try {
        $editNoteStmt = $db->prepare("
            SELECT * FROM animal_notes
            WHERE id = :note_id AND animal_id = :animal_id
        ");
        $editNoteStmt->bindParam(':note_id', $note_id, PDO::PARAM_INT);
        $editNoteStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
        $editNoteStmt->execute();
        
        if ($editNoteStmt->rowCount() > 0) {
            $edit_note = $editNoteStmt->fetch();
        }
    } catch (Exception $e) {
        error_log("Error fetching note entry for editing: " . $e->getMessage());
    }
}

// Process main animal form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_animal'])) {


// Get form data and sanitize - FIXED version for date fields
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$breed = filter_input(INPUT_POST, 'breed', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Handle date fields properly - convert empty strings to NULL
$dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
$dod = !empty($_POST['dod']) ? $_POST['dod'] : null;
$date_purchased = !empty($_POST['date_purchased']) ? $_POST['date_purchased'] : null;
$date_sold = !empty($_POST['date_sold']) ? $_POST['date_sold'] : null;

$dam_id = filter_input(INPUT_POST, 'dam_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
$sire_id = filter_input(INPUT_POST, 'sire_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
$status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$sell_price = filter_input(INPUT_POST, 'sell_price', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
$sell_info = filter_input(INPUT_POST, 'sell_info', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$purch_cost = filter_input(INPUT_POST, 'purch_cost', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: null;
$purch_info = filter_input(INPUT_POST, 'purch_info', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
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

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="animalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
            <i class="bi bi-card-list"></i> Details
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="medications-tab" data-bs-toggle="tab" data-bs-target="#medications" type="button" role="tab" aria-controls="medications" aria-selected="false">
            <i class="bi bi-capsule"></i> Medications
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
            <i class="bi bi-journal-text"></i> Notes
        </button>
    </li>
</ul>

<div class="tab-content" id="animalTabsContent">
    <!-- Details Tab -->
    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title mb-0">Edit Animal Information</h3>
            </div>
            <div class="card-body">
                <form action="animal_edit.php?id=<?= $id ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="update_animal" value="1">
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
                                    <option value="<?= $dam['id'] ?>" data-type="<?= htmlspecialchars($dam['type']) ?>" <?= $animal['dam_id'] == $dam['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= $sire['id'] ?>" data-type="<?= htmlspecialchars($sire['type']) ?>" <?= $animal['sire_id'] == $sire['id'] ? 'selected' : '' ?>>
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
    </div>
    
    <!-- Medications Tab -->
    <div class="tab-pane fade" id="medications" role="tabpanel" aria-labelledby="medications-tab">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Medication History</h3>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMedicationModal">
                    <i class="bi bi-plus-circle"></i> Add Medication
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($medications)): ?>
                <div class="alert alert-info">
                    <p>No medication records found for this animal. Click the "Add Medication" button to add a medication entry.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medications as $med): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($med['date'])) ?></td>
                                <td><?= htmlspecialchars($med['type']) ?></td>
                                <td><?= htmlspecialchars($med['amount']) ?></td>
                                <td>
                                    <?php if (!empty($med['notes'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="popover" 
                                            title="Medication Notes" data-bs-content="<?= htmlspecialchars($med['notes']) ?>">
                                        <i class="bi bi-info-circle"></i> View Notes
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted">No notes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="animal_edit.php?id=<?= $id ?>&edit_medication=<?= $med['id'] ?>#medications" class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMedicationModal<?= $med['id'] ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Medication Modal -->
                                    <div class="modal fade" id="deleteMedicationModal<?= $med['id'] ?>" tabindex="-1" aria-labelledby="deleteMedicationModalLabel<?= $med['id'] ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteMedicationModalLabel<?= $med['id'] ?>">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete this medication record?</p>
                                                    <ul>
                                                        <li><strong>Date:</strong> <?= date('M j, Y', strtotime($med['date'])) ?></li>
                                                        <li><strong>Type:</strong> <?= htmlspecialchars($med['type']) ?></li>
                                                        <li><strong>Amount:</strong> <?= htmlspecialchars($med['amount']) ?></li>
                                                    </ul>
                                                    <p class="text-danger">This action cannot be undone.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="animal_edit.php?id=<?= $id ?>&delete_medication=<?= $med['id'] ?>" class="btn btn-danger">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if ($edit_medication): ?>
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Edit Medication Entry</h4>
                    </div>
                    <div class="card-body">
                        <form action="animal_edit.php?id=<?= $id ?>#medications" method="post">
                            <input type="hidden" name="update_medication" value="1">
                            <input type="hidden" name="med_id" value="<?= $edit_medication['id'] ?>">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="med_date" class="form-label">Date *</label>
                                    <input type="date" id="med_date" name="med_date" class="form-control" 
                                           value="<?= htmlspecialchars($edit_medication['date']) ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="med_type" class="form-label">Type *</label>
                                    <input type="text" id="med_type" name="med_type" class="form-control" 
                                           value="<?= htmlspecialchars($edit_medication['type']) ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="med_amount" class="form-label">Amount *</label>
                                    <input type="text" id="med_amount" name="med_amount" class="form-control" 
                                           value="<?= htmlspecialchars($edit_medication['amount']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_notes" class="form-label">Notes</label>
                                <textarea id="med_notes" name="med_notes" class="form-control" rows="3"><?= htmlspecialchars($edit_medication['notes']) ?></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Medication
                                </button>
                                <a href="animal_edit.php?id=<?= $id ?>#medications" class="btn btn-secondary ms-2">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Medication Modal -->
        <div class="modal fade" id="addMedicationModal" tabindex="-1" aria-labelledby="addMedicationModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addMedicationModalLabel">Add Medication Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="animal_edit.php?id=<?= $id ?>#medications" method="post">
                        <input type="hidden" name="add_medication" value="1">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="med_date" class="form-label">Date *</label>
                                <input type="date" id="med_date" name="med_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_type" class="form-label">Type *</label>
                                <input type="text" id="med_type" name="med_type" class="form-control" required
                                       placeholder="e.g., Vaccine, Antibiotic, Wormer">
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_amount" class="form-label">Amount *</label>
                                <input type="text" id="med_amount" name="med_amount" class="form-control" required
                                       placeholder="e.g., 10ml, 2 tablets, 1cc">
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_notes" class="form-label">Notes</label>
                                <textarea id="med_notes" name="med_notes" class="form-control" rows="3"
                                          placeholder="Optional additional details"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Medication</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notes Tab -->
    <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Notes</h3>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <i class="bi bi-plus-circle"></i> Add Note
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($notes)): ?>
                <div class="alert alert-info">
                    <p>No notes found for this animal. Click the "Add Note" button to add a note entry.</p>
                </div>
                <?php else: ?>
                <div class="accordion" id="notesAccordion">
                    <?php foreach ($notes as $index => $note): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="noteHeading<?= $note['id'] ?>">
                            <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#noteCollapse<?= $note['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" 
                                    aria-controls="noteCollapse<?= $note['id'] ?>">
                                <strong><?= htmlspecialchars($note['title']) ?></strong> &nbsp;-&nbsp; <?= date('M j, Y', strtotime($note['date'])) ?>
                            </button>
                        </h2>
                        <div id="noteCollapse<?= $note['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
                             aria-labelledby="noteHeading<?= $note['id'] ?>" data-bs-parent="#notesAccordion">
                            <div class="accordion-body">
                                <div class="mb-3">
                                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="animal_edit.php?id=<?= $id ?>&edit_note=<?= $note['id'] ?>#notes" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteNoteModal<?= $note['id'] ?>">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                                
                                <!-- Delete Note Modal -->
                                <div class="modal fade" id="deleteNoteModal<?= $note['id'] ?>" tabindex="-1" aria-labelledby="deleteNoteModalLabel<?= $note['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteNoteModalLabel<?= $note['id'] ?>">Confirm Delete</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this note?</p>
                                                <p><strong>Title:</strong> <?= htmlspecialchars($note['title']) ?></p>
                                                <p><strong>Date:</strong> <?= date('M j, Y', strtotime($note['date'])) ?></p>
                                                <p class="text-danger">This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <a href="animal_edit.php?id=<?= $id ?>&delete_note=<?= $note['id'] ?>" class="btn btn-danger">Delete</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($edit_note): ?>
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Edit Note</h4>
                    </div>
                    <div class="card-body">
                        <form action="animal_edit.php?id=<?= $id ?>#notes" method="post">
                            <input type="hidden" name="update_note" value="1">
                            <input type="hidden" name="note_id" value="<?= $edit_note['id'] ?>">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="note_date" class="form-label">Date *</label>
                                    <input type="date" id="note_date" name="note_date" class="form-control" 
                                           value="<?= htmlspecialchars($edit_note['date']) ?>" required>
                                </div>
                                
                                <div class="col-md-8 mb-3">
                                    <label for="note_title" class="form-label">Title *</label>
                                    <input type="text" id="note_title" name="note_title" class="form-control" 
                                           value="<?= htmlspecialchars($edit_note['title']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="note_content" class="form-label">Content *</label>
                                <textarea id="note_content" name="note_content" class="form-control" rows="5" required><?= htmlspecialchars($edit_note['content']) ?></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Note
                                </button>
                                <a href="animal_edit.php?id=<?= $id ?>#notes" class="btn btn-secondary ms-2">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Note Modal -->
        <div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addNoteModalLabel">Add Note</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="animal_edit.php?id=<?= $id ?>#notes" method="post">
                        <input type="hidden" name="add_note" value="1">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="note_date" class="form-label">Date *</label>
                                <input type="date" id="note_date" name="note_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="note_title" class="form-label">Title *</label>
                                <input type="text" id="note_title" name="note_title" class="form-control" required
                                       placeholder="Brief description of this note">
                            </div>
                            
                            <div class="mb-3">
                                <label for="note_content" class="form-label">Content *</label>
                                <textarea id="note_content" name="note_content" class="form-control" rows="5" required
                                          placeholder="Enter detailed notes here..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Note</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fixed Script for Status-Dependent Fields -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            html: true,
            trigger: 'click',
            placement: 'top'
        });
    });
    
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
    
    // Auto-activate tab based on URL hash
    const hash = window.location.hash;
    if (hash) {
        const triggerEl = document.querySelector(`button[data-bs-target="${hash}"]`);
        if (triggerEl) {
            new bootstrap.Tab(triggerEl).show();
        }
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
                if (option.selected) {
                    newOption.selected = true;
                }
                damSelect.add(newOption);
            }
        });
        
        // Add matching Sire options (skip the first "None Selected" option)
        sireOptions.forEach(function(option, index) {
            // Skip the first option (None Selected) since we're keeping it
            if (index > 0 && option.dataset.type === selectedType) {
                const newOption = option.cloneNode(true);
                if (option.selected) {
                    newOption.selected = true;
                }
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