<?php
/**
 * Enhanced Quick Add Form for Newborn Animals with Offline Support
 * 
 * A mobile-optimized form to quickly add newborn animals while in the field
 * with offline capabilities using Progressive Web App features.
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
$page_title = "Quick Add Newborn";
$errors = [];
$success = false;
$new_animal_id = null;

// Process form submission (same as before)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-d');
    $dam_id = filter_input(INPUT_POST, 'dam_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
    $sire_id = filter_input(INPUT_POST, 'sire_id', FILTER_SANITIZE_NUMBER_INT) ?: null;

    // Generate a default name if not provided
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($name)) {
        $name = $type . " " . date('Ymd-His');
    }
    
    // Set default status and pending completion
    $status = "Alive";
    $pending_completion = "Yes";
    
    // Validate minimal required fields
    if (empty($type)) {
        $errors[] = "Animal type is required";
    }
    
    // Handle image upload if provided (same as before)
    $fileName = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
        // ... (same image upload code)
    }
    
    // If no errors, add the animal
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            $insertQuery = "
                INSERT INTO animals (
                    type, number, name, gender, color, dob,
                    dam_id, sire_id, status, image, 
                    user_id, pending_completion, created_at, updated_at
                ) VALUES (
                    :type, :number, :name, :gender, :color, :dob,
                    :dam_id, :sire_id, :status, :image, 
                    :user_id, :pending_completion, NOW(), NOW()
                )
            ";
            
            $insertStmt = $db->prepare($insertQuery);
            
            // Bind parameters
            $insertStmt->bindParam(':type', $type);
            $insertStmt->bindParam(':number', $number);
            $insertStmt->bindParam(':name', $name);
            $insertStmt->bindParam(':gender', $gender);
            $insertStmt->bindParam(':color', $color);
            $insertStmt->bindParam(':dob', $dob);
            $insertStmt->bindParam(':dam_id', $dam_id);
            $insertStmt->bindParam(':sire_id', $sire_id);
            $insertStmt->bindParam(':status', $status);
            $insertStmt->bindParam(':image', $fileName);
            $insertStmt->bindParam(':user_id', $current_user);
            $insertStmt->bindParam(':pending_completion', $pending_completion);
            
            if (!$insertStmt->execute()) {
                throw new Exception("Database error: " . implode(", ", $insertStmt->errorInfo()));
            }
            
            $new_animal_id = $db->lastInsertId();
            $db->commit();
            
            $success = true;
            
            // Return JSON response for AJAX requests (offline handling)
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Animal added successfully!',
                    'animal_id' => $new_animal_id
                ]);
                exit;
            }
            
            $_SESSION['alert_message'] = "Animal added successfully!";
            $_SESSION['alert_type'] = "success";
            
            // Redirect based on form action
            if (isset($_POST['add_another']) && $_POST['add_another'] == '1') {
                header("location: quick_add.php?type=" . urlencode($type) . "&success=1");
            } else {
                header("location: animal_view.php?id=" . $new_animal_id);
            }
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Quick Add Error: ' . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // Return JSON response for AJAX requests with errors
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $errors
        ]);
        exit;
    }
}

// Get parent options for selection (same as before)
try {
    $damStmt = $db->prepare("
        SELECT id, name, number, type
        FROM animals 
        WHERE user_id = :user_id 
        AND gender = 'Female' 
        AND status = 'Alive'
        ORDER BY name ASC
    ");
    $damStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $damStmt->execute();
    $dams = $damStmt->fetchAll();

    $sireStmt = $db->prepare("
        SELECT id, name, number, type
        FROM animals 
        WHERE user_id = :user_id 
        AND gender = 'Male' 
        AND status = 'Alive'
        ORDER BY name ASC
    ");
    $sireStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $sireStmt->execute();
    $sires = $sireStmt->fetchAll();
} catch (Exception $e) {
    error_log('Quick Add Error: ' . $e->getMessage());
}

$justAdded = isset($_GET['success']) && $_GET['success'] == '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - FarmApp</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#198754">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FarmApp">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/ico" href="assets/img/favicon.png">
    
    <style>
        /* Enhanced mobile-optimized styles */
        .quick-add-form .form-control,
        .quick-add-form .form-select,
        .quick-add-form .btn {
            height: 50px;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        
        .quick-add-form .btn-lg {
            height: 60px;
            font-size: 1.2rem;
        }
        
        .quick-add-form label {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ddd;
            display: none;
        }
        
        .camera-container {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .camera-button {
            width: 100%;
            height: 100px;
            border: 2px dashed #ccc;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            background-color: #f8f9fa;
        }
        
        .camera-button i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .action-bar {
            position: sticky;
            bottom: 0;
            background-color: #fff;
            padding: 10px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }
        
        .quick-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .quick-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.125);
            padding: 12px 15px;
        }
        
        .quick-card .card-body {
            padding: 15px;
        }
        
        /* Offline status styles */
        .alert-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .status-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.125);
        }
        
        .main-content {
            margin-top: 60px; /* Account for status bar */
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .quick-add-form .form-label {
                margin-bottom: 2px;
            }
            
            .quick-add-form .form-control,
            .quick-add-form .form-select {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Status Bar for Connection and Pending Uploads -->
    <div class="status-bar">
        <div class="container">
            <div id="connection-status"></div>
            <div id="pending-uploads" style="display: none;"></div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container main-content my-3">
        <!-- Message Container for Notifications -->
        <div id="message-container"></div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <h4 class="mb-0 text-center">Quick Add Newborn</h4>
            <div style="width: 40px;"></div> <!-- Spacer -->
        </div>
        
        <?php if ($justAdded): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i> Animal added successfully! Add another?
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
            <div><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <form id="quick-add-form" action="quick_add.php" method="post" enctype="multipart/form-data" class="quick-add-form">
            <!-- Photo/Camera Section -->
            <div class="quick-card mb-3">
                <div class="card-body">
                    <div class="camera-container">
                        <label class="camera-button" for="image">
                            <i class="bi bi-camera"></i>
                            <span>Take Photo</span>
                        </label>
                        <input type="file" id="image" name="image" accept="image/*" capture="environment" class="d-none">
                        <img id="preview" class="image-preview" alt="Image preview">
                    </div>
                </div>
            </div>
            
            <!-- Essential Information Card -->
            <div class="quick-card">
                <div class="card-header">
                    <h5 class="mb-0">Essential Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label for="type" class="form-label">Animal Type <span class="text-danger">*</span></label>
                        <select id="type" name="type" class="form-select form-select-lg" required>
                            <option value="">Select Type</option>
                            <option value="Sheep" <?= $preselected_type === 'Sheep' ? 'selected' : '' ?>>Sheep</option>
                            <option value="Chicken" <?= $preselected_type === 'Chicken' ? 'selected' : '' ?>>Chicken</option>
                            <option value="Turkey" <?= $preselected_type === 'Turkey' ? 'selected' : '' ?>>Turkey</option>
                            <option value="Pig" <?= $preselected_type === 'Pig' ? 'selected' : '' ?>>Pig</option>
                            <option value="Cow" <?= $preselected_type === 'Cow' ? 'selected' : '' ?>>Cow</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-2">
                                <label for="gender" class="form-label">Gender</label>
                                <select id="gender" name="gender" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-2">
                                <label for="number" class="form-label">Tag/ID Number</label>
                                <input type="text" id="number" name="number" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" id="dob" name="dob" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-2">
                        <label for="color" class="form-label">Color/Markings</label>
                        <textarea id="color" name="color" class="form-control" rows="2" placeholder="Describe color and distinctive markings"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Parents Card -->
            <div class="quick-card">
                <div class="card-header">
                    <h5 class="mb-0">Parents</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label for="dam_id" class="form-label">Dam (Mother)</label>
                        <select id="dam_id" name="dam_id" class="form-select">
                            <option value="">Select Dam</option>
                            <?php foreach ($dams as $dam): ?>
                            <option value="<?= $dam['id'] ?>" data-type="<?= htmlspecialchars($dam['type']) ?>">
                                <?= htmlspecialchars($dam['name']) ?> (<?= htmlspecialchars($dam['number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-2">
                        <label for="sire_id" class="form-label">Sire (Father)</label>
                        <select id="sire_id" name="sire_id" class="form-select">
                            <option value="">Select Sire</option>
                            <?php foreach ($sires as $sire): ?>
                            <option value="<?= $sire['id'] ?>" data-type="<?= htmlspecialchars($sire['type']) ?>">
                                <?= htmlspecialchars($sire['name']) ?> (<?= htmlspecialchars($sire['number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Action Bar -->
            <div class="action-bar mt-3">
                <div class="container">
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="submit" name="add_another" value="1" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-plus-circle"></i> Save & Add Another
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-check2-circle"></i> Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Offline Handler Script -->
    <script src="offline-handler.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Image preview functionality
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('preview');
            
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Filter Dam and Sire dropdowns based on selected animal type
            const typeSelect = document.getElementById('type');
            const damSelect = document.getElementById('dam_id');
            const sireSelect = document.getElementById('sire_id');
            
            // Store all original options
            const damOptions = Array.from(damSelect.options);
            const sireOptions = Array.from(sireSelect.options);
            
            typeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                
                // Reset dropdowns
                damSelect.innerHTML = '<option value="">Select Dam</option>';
                sireSelect.innerHTML = '<option value="">Select Sire</option>';
                
                if (!selectedType) return;
                
                // Filter dam options
                damOptions.forEach(function(option) {
                    if (option.value === '' || option.dataset.type === selectedType) {
                        damSelect.appendChild(option.cloneNode(true));
                    }
                });
                
                // Filter sire options
                sireOptions.forEach(function(option) {
                    if (option.value === '' || option.dataset.type === selectedType) {
                        sireSelect.appendChild(option.cloneNode(true));
                    }
                });
            });
            
            // Trigger filtering if type is preselected
            if (typeSelect.value) {
                typeSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>