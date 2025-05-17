<?php
/**
 * Animal Delete Script
 * 
 * Handles the deletion of an animal record with proper validation and error handling.
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

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert_message'] = "No animal specified for deletion";
    $_SESSION['alert_type'] = "danger";
    header("location: index.php");
    exit;
}

$id = intval($_GET['id']);
$current_user = $_SESSION["username"];

// Process for confirmation step
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// If not confirmed and not AJAX request, show confirmation page
if (!$confirmed && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // Get database connection
    $db = getDbConnection();
    
    try {
        // Get the animal details for confirmation
        $stmt = $db->prepare("
            SELECT id, name, number, type 
            FROM animals 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
        $stmt->execute();
        
        // Check if animal exists and belongs to current user
        if ($stmt->rowCount() === 0) {
            $_SESSION['alert_message'] = "Animal not found or you don't have permission to delete it.";
            $_SESSION['alert_type'] = "danger";
            header("location: index.php");
            exit;
        }
        
        // Fetch animal data
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get offspring count
        $offspringStmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM animals 
            WHERE (dam_id = :id OR sire_id = :id) 
            AND user_id = :user_id
        ");
        $offspringStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $offspringStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
        $offspringStmt->execute();
        $offspringCount = $offspringStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Set return URL based on animal type
        $returnUrl = 'index.php';
        if (!empty($animal['type'])) {
            $returnUrl = 'index.php?type=' . urlencode($animal['type']);
        }
        
        // Show confirmation page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirm Delete - FarmApp</title>
            
            <!-- Bootstrap 5 CSS -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            
            <!-- Bootstrap Icons -->
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
            
            <!-- Custom CSS -->
            <link rel="stylesheet" href="assets/css/styles.css">
        </head>
        <body>
            <div class="container py-5">
                <div class="card shadow-sm mx-auto" style="max-width: 500px;">
                    <div class="card-header bg-danger text-white">
                        <h3 class="card-title mb-0">Confirm Deletion</h3>
                    </div>
                    <div class="card-body">
                        <p class="lead">Are you sure you want to delete this animal?</p>
                        
                        <div class="alert alert-warning">
                            <p><strong>Animal Details:</strong></p>
                            <ul>
                                <li><strong>Name:</strong> <?= htmlspecialchars($animal['name']) ?></li>
                                <li><strong>Number:</strong> <?= htmlspecialchars($animal['number']) ?></li>
                                <li><strong>Type:</strong> <?= htmlspecialchars($animal['type']) ?></li>
                            </ul>
                        </div>
                        
                        <?php if ($offspringCount > 0): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Warning:</strong> This animal has <?= $offspringCount ?> offspring records.
                            Deleting this animal will affect lineage tracking.
                        </div>
                        <?php endif; ?>
                        
                        <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= $returnUrl ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <a href="delete.php?id=<?= $id ?>&confirm=yes" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i> Yes, Delete Animal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bootstrap Bundle with Popper -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit; // Stop execution here
    } catch (Exception $e) {
        // Log error and redirect
        error_log('Animal Delete Error: ' . $e->getMessage());
        $_SESSION['alert_message'] = "An error occurred while retrieving animal data for deletion.";
        $_SESSION['alert_type'] = "danger";
        header("location: index.php");
        exit;
    }
}

// If we're here, either confirmation was given or it's an AJAX request
// Get database connection
$db = getDbConnection();

try {
    // Start transaction
    $db->beginTransaction();
    
    // Before deleting, verify that the animal belongs to current user
    $verifyStmt = $db->prepare("
        SELECT id FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $verifyStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $verifyStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $verifyStmt->execute();
    
    if ($verifyStmt->rowCount() === 0) {
        // Animal not found or doesn't belong to user
        throw new Exception("Animal not found or you don't have permission to delete it.");
    }
    
    // Update any animals that reference this one as parent
    $updateDamStmt = $db->prepare("
        UPDATE animals 
        SET dam_id = NULL 
        WHERE dam_id = :id
    ");
    $updateDamStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $updateDamStmt->execute();
    
    $updateSireStmt = $db->prepare("
        UPDATE animals 
        SET sire_id = NULL 
        WHERE sire_id = :id
    ");
    $updateSireStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $updateSireStmt->execute();
    
    // Now delete the animal
    $deleteStmt = $db->prepare("
        DELETE FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $deleteStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $deleteStmt->execute();
    
    // Check if delete was successful
    if ($deleteStmt->rowCount() === 0) {
        throw new Exception("Failed to delete the animal.");
    }
    
    // Commit transaction
    $db->commit();
    
    // Set success message
    $_SESSION['alert_message'] = "Animal deleted successfully.";
    $_SESSION['alert_type'] = "success";
    
    // Get the type from the URL if it exists, for proper redirection
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $returnUrl = empty($type) ? 'index.php' : 'index.php?type=' . urlencode($type);
    
    // Redirect
    header("location: $returnUrl");
    exit;
    
} catch (Exception $e) {
    // Rollback transaction
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error and redirect with error message
    error_log('Animal Delete Error: ' . $e->getMessage());
    $_SESSION['alert_message'] = "An error occurred while deleting the animal: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
    header("location: index.php");
    exit;
}
?>