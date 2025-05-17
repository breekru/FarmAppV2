<?php
// Display all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration
require_once 'config.php';

// Test database connection
try {
    $db = getDbConnection();
    echo "Database connection successful!<br>";
    
    // Check if the animals table has the required columns
    $stmt = $db->query("DESCRIBE animals");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columns in animals table:<br>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Test a simple query
    $stmt = $db->query("SELECT id, name FROM animals LIMIT 1");
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Sample animal:<br>";
    echo "<pre>";
    print_r($animal);
    echo "</pre>";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>