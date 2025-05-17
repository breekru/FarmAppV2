<?php
/**
 * Simplified Animal View Page
 * 
 * A basic version that should work with existing database structure
 */

// Display errors for debugging (remove in production)
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
    $_SESSION['alert_message'] = "No animal specified";
    $_SESSION['alert_type'] = "danger";
    header("location: index.php");
    exit;
}

$id = intval($_GET['id']);
$current_user = $_SESSION["username"];

// Get database connection
$db = getDbConnection();

// Basic query to get animal details - using only fields we know exist in the current database
try {
    $stmt = $db->prepare("
        SELECT id, type, breed, number, name, gender, offspring, parents, 
               status, date_purchased, date_sold, sell_price, sell_info,
               notes, meds, dob, dod, image
        FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $stmt->execute();

    // Check if animal exists and belongs to current user
    if ($stmt->rowCount() === 0) {
        $_SESSION['alert_message'] = "Animal not found or you don't have permission to view it.";
        $_SESSION['alert_type'] = "danger";
        header("location: index.php");
        exit;
    }

    // Fetch animal data
    $animal = $stmt->fetch();

    // Set page variables
    $page_title = "View Animal: " . $animal['name'];
    $page_header = $animal['name'] . " (" . $animal['number'] . ")";
    $page_subheader = $animal['type'] . " - " . $animal['breed'];

    // Format dates for display
    $dobFormatted = !empty($animal['dob']) ? date("F j, Y", strtotime($animal['dob'])) : "Unknown";
    $dodFormatted = !empty($animal['dod']) ? date("F j, Y", strtotime($animal['dod'])) : "N/A";
    $purchasedFormatted = !empty($animal['date_purchased']) ? date("F j, Y", strtotime($animal['date_purchased'])) : "N/A";
    $soldFormatted = !empty($animal['date_sold']) ? date("F j, Y", strtotime($animal['date_sold'])) : "N/A";

    // Get return URL based on animal type
    $returnUrl = 'index.php';
    if (!empty($animal['type'])) {
        $returnUrl = 'index.php?type=' . urlencode($animal['type']);
    }

} catch (Exception $e) {
    // Log error and redirect
    error_log('Animal View Error: ' . $e->getMessage());
    $_SESSION['alert_message'] = "An error occurred while retrieving animal data.";
    $_SESSION['alert_type'] = "danger";
    header("location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'View Animal' ?> - FarmApp</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-md-6">
                <a href="<?= $returnUrl ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group">
                    <a href="animal_edit.php?id=<?= $id ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <button type="button" class="btn btn-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Main Details -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Animal Information</h3>
                        <span class="badge bg-<?= getStatusBadgeClass($animal['status']) ?> rounded-pill fs-6">
                            <?= htmlspecialchars($animal['status']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <!-- Animal image if available -->
                            <?php if (!empty($animal['image'])): ?>
                            <div class="col-md-4 text-center mb-3">
                                <img src="assets/img/animals/<?= htmlspecialchars($animal['image']) ?>" 
                                     alt="<?= htmlspecialchars($animal['name']) ?>"
                                     class="img-fluid rounded-circle" style="max-height: 200px;">
                            </div>
                            <div class="col-md-8">
                            <?php else: ?>
                            <div class="col-12">
                            <?php endif; ?>
                                <table class="table table-borderless">
                                    <tbody>
                                        <tr>
                                            <th scope="row" style="width: 30%;">Number:</th>
                                            <td><?= htmlspecialchars($animal['number']) ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Name:</th>
                                            <td><?= htmlspecialchars($animal['name']) ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Type:</th>
                                            <td><?= htmlspecialchars($animal['type']) ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Breed:</th>
                                            <td><?= htmlspecialchars($animal['breed']) ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Gender:</th>
                                            <td><?= htmlspecialchars($animal['gender']) ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Date of Birth:</th>
                                            <td><?= htmlspecialchars($dobFormatted) ?></td>
                                        </tr>
                                        <?php if ($animal['status'] === 'Dead' || $animal['status'] === 'Harvested'): ?>
                                        <tr>
                                            <th scope="row">Date of Death/Dispatch:</th>
                                            <td><?= htmlspecialchars($dodFormatted) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Legacy Lineage Information (using the old text fields) -->
                        <div class="row">
                            <div class="col-12">
                                <h4>Lineage</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header">Parents</div>
                                            <div class="card-body">
                                                <?php if (!empty($animal['parents'])): ?>
                                                <p><?= nl2br(htmlspecialchars($animal['parents'])) ?></p>
                                                <?php else: ?>
                                                <p class="text-muted">No parent information available</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header">Offspring</div>
                                            <div class="card-body">
                                                <?php if (!empty($animal['offspring'])): ?>
                                                <p><?= nl2br(htmlspecialchars($animal['offspring'])) ?></p>
                                                <?php else: ?>
                                                <p class="text-muted">No offspring information available</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Additional Details -->
            <div class="col-lg-4">
                <!-- Purchase/Sale Info -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Purchase & Sale Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Date Purchased:</strong> <?= htmlspecialchars($purchasedFormatted) ?></p>
                        
                        <?php if ($animal['status'] === 'Sold'): ?>
                        <hr>
                        <p><strong>Date Sold:</strong> <?= htmlspecialchars($soldFormatted) ?></p>
                        <?php if (!empty($animal['sell_price'])): ?>
                        <p><strong>Sell Price:</strong> $<?= htmlspecialchars($animal['sell_price']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($animal['sell_info'])): ?>
                        <p><strong>Buyer Info:</strong> <?= htmlspecialchars($animal['sell_info']) ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notes -->
                <?php if (!empty($animal['notes'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notes</h5>
                    </div>
                    <div class="card-body">
                        <div class="notes-content">
                            <?= nl2br(htmlspecialchars($animal['notes'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Medication History -->
                <?php if (!empty($animal['meds'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Medication History</h5>
                    </div>
                    <div class="card-body">
                        <div class="meds-content">
                            <?= nl2br(htmlspecialchars($animal['meds'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <?= htmlspecialchars($animal['name']) ?> 
                        (<?= htmlspecialchars($animal['number']) ?>)?</p>
                        
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <a href="delete.php?id=<?= $id ?>" class="btn btn-danger">Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>

<?php
/**
 * Helper function to get appropriate badge class based on animal status
 * 
 * @param string $status Animal status
 * @return string CSS class name for the badge
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Alive':
            return 'success';
        case 'Dead':
            return 'danger';
        case 'Sold':
            return 'info';
        case 'For Sale':
            return 'warning';
        case 'Harvested':
            return 'secondary';
        default:
            return 'primary';
    }
}
?>