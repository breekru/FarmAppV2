<?php
/**
 * Animal View Page - ENHANCED VERSION
 * 
 * Displays detailed information about a specific animal.
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
    header("location: index.php");
    exit;
}

$id = intval($_GET['id']);
$current_user = $_SESSION["username"];

// Get database connection
$db = getDbConnection();

try {
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
        $_SESSION['alert_message'] = "Animal not found or you don't have permission to view it.";
        $_SESSION['alert_type'] = "danger";
        header("location: index.php");
        exit;
    }

    // Fetch animal data
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format dates for display (with null checks)
    $dobFormatted = !empty($animal['dob']) ? date("F j, Y", strtotime($animal['dob'])) : "Unknown";
    $dodFormatted = !empty($animal['dod']) ? date("F j, Y", strtotime($animal['dod'])) : "N/A";
    $purchasedFormatted = !empty($animal['date_purchased']) ? date("F j, Y", strtotime($animal['date_purchased'])) : "N/A";
    $soldFormatted = !empty($animal['date_sold']) ? date("F j, Y", strtotime($animal['date_sold'])) : "N/A";

    // Fetch medication entries
    $medications = [];
    $medStmt = $db->prepare("
        SELECT * FROM animal_medications
        WHERE animal_id = :animal_id
        ORDER BY date DESC
    ");
    $medStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
    $medStmt->execute();
    $medications = $medStmt->fetchAll();

    // Fetch note entries
    $notes = [];
    $noteStmt = $db->prepare("
        SELECT * FROM animal_notes
        WHERE animal_id = :animal_id
        ORDER BY date DESC
    ");
    $noteStmt->bindParam(':animal_id', $id, PDO::PARAM_INT);
    $noteStmt->execute();
    $notes = $noteStmt->fetchAll();

    // Get return URL based on animal type
    $returnUrl = 'animal_list.php';
    if (!empty($animal['type'])) {
        $returnUrl .= '?type=' . urlencode($animal['type']);
    }

    // Set page title
    $page_title = "View Animal: " . $animal['name'];

} catch (Exception $e) {
    // Log the error
    error_log('Animal View Error: ' . $e->getMessage());
    $_SESSION['alert_message'] = "An error occurred while retrieving animal data.";
    $_SESSION['alert_type'] = "danger";
    header("location: index.php");
    exit;
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
            <a href="report_lineage.php?id=<?= $id ?>" class="btn btn-info text-white">
                <i class="bi bi-diagram-3"></i> View Lineage
            </a>
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

<!-- Animal Information Tabs -->
<ul class="nav nav-tabs mb-4" id="animalTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="true">
            <i class="bi bi-card-list"></i> Details
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="medications-tab" data-bs-toggle="tab" data-bs-target="#medications" type="button" role="tab" aria-controls="medications" aria-selected="false">
            <i class="bi bi-capsule"></i> Medications 
            <?php if (count($medications) > 0): ?>
            <span class="badge bg-primary rounded-pill"><?= count($medications) ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
            <i class="bi bi-journal-text"></i> Notes
            <?php if (count($notes) > 0): ?>
            <span class="badge bg-primary rounded-pill"><?= count($notes) ?></span>
            <?php endif; ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="animalTabsContent">
    <!-- Details Tab -->
    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
        <div class="row">
            <!-- Left Column - Main Details -->
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><?= htmlspecialchars($animal['name']) ?> (<?= htmlspecialchars($animal['number']) ?>)</h3>
                        <span class="badge bg-<?= getStatusBadgeClass($animal['status']) ?> rounded-pill">
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
                                        <?php if (!empty($animal['color'])): ?>
                                        <tr>
                                            <th scope="row">Color:</th>
                                            <td><?= htmlspecialchars($animal['color']) ?></td>
                                        </tr>
                                        <?php endif; ?>
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

                        <!-- Registration Information (if available) -->
                        <?php if (!empty($animal['reg_num']) || !empty($animal['reg_name'])): ?>
                        <div class="mb-4">
                            <h4>Registration Information</h4>
                            <div class="row">
                                <?php if (!empty($animal['reg_num'])): ?>
                                <div class="col-md-6">
                                    <p><strong>Registration Number:</strong> <?= htmlspecialchars($animal['reg_num']) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($animal['reg_name'])): ?>
                                <div class="col-md-6">
                                    <p><strong>Registration Name:</strong> <?= htmlspecialchars($animal['reg_name']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Parent Information (if available) -->
                        <div class="mb-4">
                            <h4>Lineage</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">Dam (Mother)</div>
                                        <div class="card-body">
                                            <?php if (!empty($animal['dam_id'])): 
                                                // Fetch dam details
                                                $damStmt = $db->prepare("
                                                    SELECT id, name, number 
                                                    FROM animals 
                                                    WHERE id = :dam_id AND user_id = :user_id
                                                ");
                                                $damStmt->bindParam(':dam_id', $animal['dam_id'], PDO::PARAM_INT);
                                                $damStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
                                                $damStmt->execute();
                                                $dam = $damStmt->fetch();
                                                
                                                if ($dam):
                                            ?>
                                            <p>
                                                <a href="animal_view.php?id=<?= $dam['id'] ?>">
                                                    <?= htmlspecialchars($dam['name']) ?> (<?= htmlspecialchars($dam['number']) ?>)
                                                </a>
                                            </p>
                                            <div class="mt-2">
                                                <a href="report_lineage.php?id=<?= $dam['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-diagram-3"></i> View Lineage
                                                </a>
                                            </div>
                                            <?php else: ?>
                                            <p>
                                                <a href="animal_view.php?id=<?= $animal['dam_id'] ?>">
                                                    View Dam
                                                </a>
                                            </p>
                                            <?php endif; ?>
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
                                            <?php if (!empty($animal['sire_id'])): 
                                                // Fetch sire details
                                                $sireStmt = $db->prepare("
                                                    SELECT id, name, number 
                                                    FROM animals 
                                                    WHERE id = :sire_id AND user_id = :user_id
                                                ");
                                                $sireStmt->bindParam(':sire_id', $animal['sire_id'], PDO::PARAM_INT);
                                                $sireStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
                                                $sireStmt->execute();
                                                $sire = $sireStmt->fetch();
                                                
                                                if ($sire):
                                            ?>
                                            <p>
                                                <a href="animal_view.php?id=<?= $sire['id'] ?>">
                                                    <?= htmlspecialchars($sire['name']) ?> (<?= htmlspecialchars($sire['number']) ?>)
                                                </a>
                                            </p>
                                            <div class="mt-2">
                                                <a href="report_lineage.php?id=<?= $sire['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-diagram-3"></i> View Lineage
                                                </a>
                                            </div>
                                            <?php else: ?>
                                            <p>
                                                <a href="animal_view.php?id=<?= $animal['sire_id'] ?>">
                                                    View Sire
                                                </a>
                                            </p>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <p class="text-muted">No sire information available</p>
                                            <?php endif; ?>
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
                        <?php if (!empty($animal['date_purchased']) || !empty($animal['purch_cost'])): ?>
                        <p><strong>Date Purchased:</strong> <?= htmlspecialchars($purchasedFormatted) ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($animal['purch_cost'])): ?>
                        <p><strong>Purchase Cost:</strong> $<?= htmlspecialchars($animal['purch_cost']) ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($animal['purch_info'])): ?>
                        <p><strong>Seller Info:</strong> <?= htmlspecialchars($animal['purch_info']) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($animal['status'] === 'Sold' || !empty($animal['date_sold'])): ?>
                        <hr>
                        <p><strong>Date Sold:</strong> <?= htmlspecialchars($soldFormatted) ?></p>
                        
                        <?php if (!empty($animal['sell_price'])): ?>
                        <p><strong>Sell Price:</strong> $<?= htmlspecialchars($animal['sell_price']) ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($animal['sell_info'])): ?>
                        <p><strong>Buyer Info:</strong> <?= htmlspecialchars($animal['sell_info']) ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($animal['for_sale']) && $animal['for_sale'] === 'Yes'): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-tag-fill me-2"></i> This animal is currently for sale
                            <?php if (!empty($animal['sell_price'])): ?>
                            for $<?= htmlspecialchars($animal['sell_price']) ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity Summary -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-capsule text-primary me-2"></i> Medications:</span>
                            <span class="badge bg-primary rounded-pill"><?= count($medications) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span><i class="bi bi-journal-text text-success me-2"></i> Notes:</span>
                            <span class="badge bg-success rounded-pill"><?= count($notes) ?></span>
                        </div>
                        
                        <?php if (count($medications) > 0 || count($notes) > 0): ?>
                        <div class="list-group small">
                            <?php 
                            // Combine and sort both types of entries
                            $recentActivity = [];
                            
                            foreach ($medications as $med) {
                                $recentActivity[] = [
                                    'date' => $med['date'],
                                    'type' => 'medication',
                                    'title' => $med['type'],
                                    'id' => $med['id']
                                ];
                            }
                            
                            foreach ($notes as $note) {
                                $recentActivity[] = [
                                    'date' => $note['date'],
                                    'type' => 'note',
                                    'title' => $note['title'],
                                    'id' => $note['id']
                                ];
                            }
                            
                            // Sort by date (newest first)
                            usort($recentActivity, function($a, $b) {
                                return strtotime($b['date']) - strtotime($a['date']);
                            });
                            
                            // Show only the 5 most recent activities
                            $recentActivity = array_slice($recentActivity, 0, 5);
                            
                            foreach ($recentActivity as $activity):
                                $activityDate = date('M j, Y', strtotime($activity['date']));
                                $icon = $activity['type'] === 'medication' ? 'bi-capsule text-primary' : 'bi-journal-text text-success';
                                $tabId = $activity['type'] === 'medication' ? 'medications-tab' : 'notes-tab';
                            ?>
                            <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                               onclick="document.getElementById('<?= $tabId ?>').click(); return false;">
                                <div>
                                    <i class="bi <?= $icon ?> me-2"></i>
                                    <span><?= htmlspecialchars($activity['title']) ?></span>
                                    <small class="text-muted ms-2"><?= $activityDate ?></small>
                                </div>
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">No recent activity</p>
                        <div class="d-grid gap-2">
                            <a href="animal_edit.php?id=<?= $id ?>#medications" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Add Medication
                            </a>
                            <a href="animal_edit.php?id=<?= $id ?>#notes" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-plus-circle"></i> Add Note
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Medications Tab -->
    <div class="tab-pane fade" id="medications" role="tabpanel" aria-labelledby="medications-tab">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Medication History</h3>
                <a href="animal_edit.php?id=<?= $id ?>#medications" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Medication
                </a>
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
                                    <a href="animal_edit.php?id=<?= $id ?>&edit_medication=<?= $med['id'] ?>#medications" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Notes Tab -->
    <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Notes</h3>
                <a href="animal_edit.php?id=<?= $id ?>#notes" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Note
                </a>
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
                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                    <strong><?= htmlspecialchars($note['title']) ?></strong>
                                    <span class="badge bg-secondary rounded-pill"><?= date('M j, Y', strtotime($note['date'])) ?></span>
                                </div>
                            </button>
                        </h2>
                        <div id="noteCollapse<?= $note['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
                             aria-labelledby="noteHeading<?= $note['id'] ?>" data-bs-parent="#notesAccordion">
                            <div class="accordion-body">
                                <div class="mb-3">
                                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="animal_edit.php?id=<?= $id ?>&edit_note=<?= $note['id'] ?>#notes" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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
                
                <p class="text-danger">This action cannot be undone and will delete all associated medication and note records.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="animal_delete.php?id=<?= $id ?>" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

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
    
    // Hide popovers when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-bs-toggle="popover"]') && 
            !e.target.closest('.popover')) {
            popoverTriggerList.forEach(function(popover) {
                bootstrap.Popover.getInstance(popover)?.hide();
            });
        }
    });
    
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