<?php
// Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the configuration file
require_once 'config.php';

// Initialize the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo "Not logged in. Please <a href='login.php'>log in</a> first.";
    exit;
}

// Get ID parameter and user
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user = $_SESSION["username"];

if ($id === 0) {
    echo "No animal ID specified.";
    exit;
}

// Try to connect to the database and retrieve the animal
try {
    // Get database connection
    $db = getDbConnection();
    
    // Prepare a very simple query - only get essential fields
    $stmt = $db->prepare("
        SELECT id, name, number, type, breed, gender, status 
        FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    
    // Bind parameters
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    
    // Execute the query
    $stmt->execute();
    
    // Check if animal exists
    if ($stmt->rowCount() === 0) {
        echo "Animal not found or you don't have permission to view it.";
        exit;
    }
    
    // Fetch the animal data
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Log the error and display a detailed message
    error_log('Animal View Error: ' . $e->getMessage());
    echo "<h1>Error</h1>";
    echo "<p>An error occurred while retrieving animal data:</p>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Animal: <?= htmlspecialchars($animal['name']) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h2><?= htmlspecialchars($animal['name']) ?> (<?= htmlspecialchars($animal['number']) ?>)</h2>
            </div>
            <div class="card-body">
                <p><strong>Type:</strong> <?= htmlspecialchars($animal['type']) ?></p>
                <p><strong>Breed:</strong> <?= htmlspecialchars($animal['breed']) ?></p>
                <p><strong>Gender:</strong> <?= htmlspecialchars($animal['gender']) ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($animal['status']) ?></p>
                
                <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>