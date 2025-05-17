<?php
/**
 * Animal View Page
 * 
 * Displays detailed information about a specific animal.
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

// Prepare and execute query to get animal details
$stmt = $db->prepare("
    SELECT * FROM animals 
    WHERE id = :id AND user_id = :user_id
");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$stmt->execute();

// Check if animal exists and belongs to current user
if ($stmt->rowCount() === 0) {
    $_SESSION['alert_message'] = "Animal not found or you don't have permission to view it.";
    $_SESSION['alert_type'] = "danger";
    header("location: animal_list.php");
    exit;
}

// Fetch animal data
$animal = $stmt->fetch();

// Set page variables
$page_title = "View Animal: " . $animal['name'];
$page_header = $animal['name'] . " (" . $animal['number'] . ")";
$page_subheader = $animal['type'] . " - " . $animal['breed'];

// Get parent information if present
$dam = null;
$sire = null;

if (!empty($animal['dam_id'])) {
    $damStmt = $db->prepare("SELECT id, name, number FROM animals WHERE id = :id");
    $damStmt->bindParam(':id', $animal['dam_id'], PDO::PARAM_INT);
    $damStmt->execute();
    $dam = $damStmt->fetch();
}

if (!empty($animal['sire_id'])) {
    $sireStmt = $db->prepare("SELECT id, name, number FROM animals WHERE id = :id");
    $sireStmt->bindParam(':id', $animal['sire_id'], PDO::PARAM_INT);
    $sireStmt->execute();
    $sire = $sireStmt->fetch();
}

// Get offspring information
$offspringStmt = $db->prepare("
    SELECT id, name, number, gender, dob 
    FROM animals 
    WHERE (dam_id = :id OR sire_id = :id) 
    AND user_id = :user_id
    ORDER BY dob DESC
");
$offspringStmt->bindParam(':id', $id, PDO::PARAM_INT);
$offspringStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$offspringStmt->execute();
$offspring = $offspringStmt->fetchAll();

// Format dates for display
$dobFormatted = !empty($animal['dob']) ? date("F j, Y", strtotime($animal['dob'])) : "Unknown";
$dodFormatted = !empty($animal['dod']) ? date("F j, Y", strtotime($animal['dod'])) : "N/A";
$purchasedFormatted = !empty($animal['date_purchased']) ? date("F j, Y", strtotime($animal['date_purchased'])) : "N/A";
$soldFormatted = !empty($animal['date_sold']) ? date("F j, Y", strtotime($animal['date_sold'])) : "N/A";

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
                <span class="badge rounded-pill fs-6 bg-<?= getStatusBadgeClass($animal['status']) ?>">
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

                <!-- Lineage Information -->
                <div class="row">
                    <div class="col-12">
                        <h4>Lineage</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">Dam (Mother)</div>
                                    <div class="card-body">
                                        <?php if ($dam): ?>
                                        <p>
                                            <a href="animal_view.php?id=<?= $dam['id'] ?>">
                                                <?= htmlspecialchars($dam['name']) ?> (<?= htmlspecialchars($dam['number']) ?>)
                                            </a>
                                        </p>
                                        <?php else: ?>
                                        <p class="text-muted">No dam information available</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">Sire (Father)</div>
                                    <div class="card-body">
                                        <?php if ($sire): ?>
                                        <p>
                                            <a href="animal_view.php?id=<?= $sire['id'] ?>">
                                                <?= htmlspecialchars($sire['name']) ?> (<?= htmlspecialchars($sire['number']) ?>)
                                            </a>
                                        </p>
                                        <?php else: ?>
                                        <p class="text-muted">No sire information available</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Offspring Information -->
                <?php if (!empty($offspring)): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h4>Offspring (<?= count($offspring) ?>)</h4>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Number</th>
                                        <th>Gender</th>
                                        <th>Birth Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($offspring as $child): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($child['name']) ?></td>
                                        <td><?= htmlspecialchars($child['number']) ?></td>
                                        <td><?= htmlspecialchars($child['gender']) ?></td>
                                        <td><?= !empty($child['dob']) ? date("M j, Y", strtotime($child['dob'])) : "Unknown" ?></td>
                                        <td>
                                            <a href="animal_view.php?id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column - Additional Details -->
    <div class="col-lg-4">
        <!-- Registration Info -->
        <?php if (!empty($animal['reg_num']) || !empty($animal['reg_name'])): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Registration Information</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($animal['reg_num'])): ?>
                <p><strong>Registration Number:</strong> <?= htmlspecialchars($animal['reg_num']) ?></p>
                <?php endif; ?>
                <?php if (!empty($animal['reg_name'])): ?>
                <p><strong>Registration Name:</strong> <?= htmlspecialchars($animal['reg_name']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Purchase/Sale Info -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Purchase & Sale Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Date Purchased:</strong> <?= htmlspecialchars($purchasedFormatted) ?></p>
                <?php if (!empty($animal['purch_cost'])): ?>
                <p><strong>Purchase Cost:</strong> $<?= htmlspecialchars($animal['purch_cost']) ?></p>
                <?php endif; ?>
                <?php if (!empty($animal['purch_info'])): ?>
                <p><strong>Seller Info:</strong> <?= htmlspecialchars($animal['purch_info']) ?></p>
                <?php endif; ?>
                
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

<!-- Family Tree View Tab -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3 class="card-title mb-0">Family Tree</h3>
            </div>
            <div class="card-body">
                <div class="family-tree-container">
                    <div class="family-tree">
                        <!-- This would be populated with a visual family tree using JavaScript -->
                        <p class="text-center text-muted">
                            Family tree visualization will be implemented in a future update.
                        </p>
                    </div>
                </div>
            </div>
        </div>
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
                
                <?php if (!empty($offspring)): ?>
                <div class="alert alert-warning">
                    <strong>Warning!</strong> This animal has <?= count($offspring) ?> offspring records linked to it.
                    Deleting this animal may affect lineage tracking.
                </div>
                <?php endif; ?>
                
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="animal_delete.php?id=<?= $id ?>" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

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

// Include footer
include_once 'includes/footer.php';
?>