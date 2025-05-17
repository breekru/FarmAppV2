<?php
// Display all PHP errors
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

// Get ID parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user = $_SESSION["username"];

// Get database connection
$db = getDbConnection();

// Basic query without joins
try {
    $stmt = $db->prepare("
        SELECT * FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "Animal not found or you don't have permission to view it.";
        exit;
    }
    
    // Fetch animal data
    $animal = $stmt->fetch();
    
    echo "<h1>Animal Details</h1>";
    echo "<pre>";
    print_r($animal);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>