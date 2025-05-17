<?php
// Display all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Basic configuration
require_once 'config.php';

// Initialize the session
session_start();

// Check authentication
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo "Not logged in";
    exit;
}

// Get the ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user = $_SESSION["username"];

echo "<h1>Diagnostic Results for Animal ID: {$id}</h1>";
echo "<pre>";

// 1. Test database connection
echo "Step 1: Testing database connection...\n";
try {
    $db = getDbConnection();
    echo "✓ Database connection successful\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit;
}

// 2. Check if the animals table exists
echo "Step 2: Checking if animals table exists...\n";
try {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('animals', $tables)) {
        echo "✓ Animals table exists\n\n";
    } else {
        echo "✗ Animals table does not exist! Available tables:\n";
        print_r($tables);
        exit;
    }
} catch (Exception $e) {
    echo "✗ Error checking tables: " . $e->getMessage() . "\n";
    exit;
}

// 3. Check table structure
echo "Step 3: Checking animals table structure...\n";
try {
    $columns = $db->query("DESCRIBE animals")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Table structure retrieved\n";
    echo "Columns in animals table:\n";
    print_r($columns);
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error examining table structure: " . $e->getMessage() . "\n";
    exit;
}

// 4. Test a basic query without where conditions
echo "Step 4: Testing a basic query...\n";
try {
    $stmt = $db->query("SELECT COUNT(*) FROM animals");
    $count = $stmt->fetchColumn();
    echo "✓ Basic query successful. Found {$count} animal records in total.\n\n";
} catch (Exception $e) {
    echo "✗ Basic query failed: " . $e->getMessage() . "\n";
    exit;
}

// 5. Check if the specified ID exists
echo "Step 5: Checking if animal ID {$id} exists...\n";
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM animals WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if ($exists) {
        echo "✓ Animal with ID {$id} exists\n\n";
    } else {
        echo "✗ No animal found with ID {$id}\n";
        exit;
    }
} catch (Exception $e) {
    echo "✗ ID check failed: " . $e->getMessage() . "\n";
    exit;
}

// 6. Test the full query with user check
echo "Step 6: Testing full query with user check...\n";
try {
    $stmt = $db->prepare("
        SELECT * FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $stmt->execute();
    
    $rowCount = $stmt->rowCount();
    if ($rowCount > 0) {
        echo "✓ Full query successful. Found {$rowCount} matching records.\n\n";
        
        // Fetch and display the record
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Animal data retrieved:\n";
        print_r($animal);
    } else {
        echo "✗ No records found for ID {$id} belonging to user '{$current_user}'\n";
        
        // Check for the animal regardless of user
        $stmt2 = $db->prepare("SELECT user_id FROM animals WHERE id = :id");
        $stmt2->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt2->execute();
        $owner = $stmt2->fetchColumn();
        
        if ($owner) {
            echo "Note: Animal with ID {$id} exists but belongs to user '{$owner}', not '{$current_user}'\n";
        }
        exit;
    }
} catch (Exception $e) {
    echo "✗ Full query failed: " . $e->getMessage() . "\n";
    exit;
}

// 7. Test date formatting
echo "\nStep 7: Testing date formatting...\n";
try {
    $dobFormatted = !empty($animal['dob']) ? date("F j, Y", strtotime($animal['dob'])) : "Unknown";
    $dodFormatted = !empty($animal['dod']) ? date("F j, Y", strtotime($animal['dod'])) : "N/A";
    $purchasedFormatted = !empty($animal['date_purchased']) ? date("F j, Y", strtotime($animal['date_purchased'])) : "N/A";
    $soldFormatted = !empty($animal['date_sold']) ? date("F j, Y", strtotime($animal['date_sold'])) : "N/A";
    
    echo "Date of Birth: " . $dobFormatted . "\n";
    echo "Date of Death: " . $dodFormatted . "\n";
    echo "Date Purchased: " . $purchasedFormatted . "\n";
    echo "Date Sold: " . $soldFormatted . "\n";
    echo "✓ Date formatting successful\n";
} catch (Exception $e) {
    echo "✗ Date formatting failed: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><strong>All diagnostic tests completed.</strong></p>";
echo "<p>If all tests passed, review the animal data above for any issues with specific fields. If all looks good, check PHP error logs for more details.</p>";
?>