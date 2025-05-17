<?php
/**
 * Animal Delete Script - Simplified Version
 * 
 * Handles the deletion of an animal record with minimal complexity
 * to ensure it works in your environment.
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

// Simple direct deletion - no confirmation page
try {
    // Get database connection
    $db = getDbConnection();
    
    // First, verify that the animal belongs to current user
    $verifyStmt = $db->prepare("
        SELECT id, type FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $verifyStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $verifyStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $verifyStmt->execute();
    
    if ($verifyStmt->rowCount() === 0) {
        // Animal not found or doesn't belong to user
        $_SESSION['alert_message'] = "Animal not found or you don't have permission to delete it.";
        $_SESSION['alert_type'] = "danger";
        header("location: index.php");
        exit;
    }
    
    // Get the animal type for redirection
    $animalType = $verifyStmt->fetch(PDO::FETCH_ASSOC)['type'];
    
    // Delete the animal record
    $deleteStmt = $db->prepare("
        DELETE FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $deleteStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $deleteStmt->execute();
    
    // Set success message
    $_SESSION['alert_message'] = "Animal deleted successfully.";
    $_SESSION['alert_type'] = "success";
    
    // Redirect based on animal type
    $returnUrl = !empty($animalType) ? "index.php?type=" . urlencode($animalType) : "index.php";
    header("location: $returnUrl");
    exit;
    
} catch (Exception $e) {
    // Log error and redirect with error message
    error_log('Animal Delete Error: ' . $e->getMessage());
    $_SESSION['alert_message'] = "An error occurred while deleting the animal: " . $e->getMessage();
    $_SESSION['alert_type'] = "danger";
    header("location: index.php");
    exit;
}
?>