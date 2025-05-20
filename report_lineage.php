<?php
/**
 * Lineage Report Page
 * 
 * This page displays lineage information for farm animals,
 * including ancestry and offspring data.
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

// Set page variables
$page_title = "Lineage Report";
$page_header = "Lineage Report";
$page_subheader = "Track animal relationships and breeding history";

// Get animal ID if provided
$animal_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'tree';

// Get specific animal type filter if provided
$animal_type = isset($_GET['type']) ? $_GET['type'] : null;

// If an animal ID is provided, get the animal data
$animal = null;
if ($animal_id) {
    $animalStmt = $db->prepare("
        SELECT * FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $animalStmt->bindParam(':id', $animal_id, PDO::PARAM_INT);
    $animalStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $animalStmt->execute();
    
    if ($animalStmt->rowCount() > 0) {
        $animal = $animalStmt->fetch(PDO::FETCH_ASSOC);
        $page_header = "Lineage for " . $animal['name'] . " (" . $animal['number'] . ")";
        $page_subheader = $animal['type'] . " - " . $animal['breed'];
    } else {
        // If animal not found, display error message
        $_SESSION['alert_message'] = "Animal not found or you don't have permission to view it.";
        $_SESSION['alert_type'] = "danger";
        header("location: report_lineage.php");
        exit;
    }
}

// Get available animal types for filter dropdown
$typesStmt = $db->prepare("
    SELECT DISTINCT type FROM animals 
    WHERE user_id = :user_id 
    ORDER BY type
");
$typesStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$typesStmt->execute();
$availableTypes = $typesStmt->fetchAll();

// Function to get parent information
function getParentInfo($db, $parent_id, $current_user) {
    if (!$parent_id) {
        return [
            'id' => null,
            'name' => 'Unknown',
            'number' => '---',
            'breed' => 'Unknown',
            'gender' => 'Unknown',
            'status' => 'Unknown'
        ];
    }
    
    $stmt = $db->prepare("
        SELECT id, name, number, breed, gender, status 
        FROM animals 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->bindParam(':id', $parent_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        return [
            'id' => null,
            'name' => 'Unknown',
            'number' => '---',
            'breed' => 'Unknown',
            'gender' => 'Unknown',
            'status' => 'Unknown'
        ];
    }
}

// Get parent and grandparent information if we have an animal
$parentInfo = [
    'dam' => null,
    'sire' => null,
    'dam_dam' => null,
    'dam_sire' => null,
    'sire_dam' => null,
    'sire_sire' => null
];

if ($animal) {
    // Get dam (mother) information
    $parentInfo['dam'] = getParentInfo($db, $animal['dam_id'], $current_user);
    
    // Get sire (father) information
    $parentInfo['sire'] = getParentInfo($db, $animal['sire_id'], $current_user);
    
    // Get grandparent information if available
    if ($parentInfo['dam']['id']) {
        $damStmt = $db->prepare("
            SELECT dam_id, sire_id 
            FROM animals 
            WHERE id = :id AND user_id = :user_id
        ");
        $damStmt->bindParam(':id', $animal['dam_id'], PDO::PARAM_INT);
        $damStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
        $damStmt->execute();
        
        if ($damInfo = $damStmt->fetch(PDO::FETCH_ASSOC)) {
            $parentInfo['dam_dam'] = getParentInfo($db, $damInfo['dam_id'], $current_user);
            $parentInfo['dam_sire'] = getParentInfo($db, $damInfo['sire_id'], $current_user);
        }
    }
    
    if ($parentInfo['sire']['id']) {
        $sireStmt = $db->prepare("
            SELECT dam_id, sire_id 
            FROM animals 
            WHERE id = :id AND user_id = :user_id
        ");
        $sireStmt->bindParam(':id', $animal['sire_id'], PDO::PARAM_INT);
        $sireStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
        $sireStmt->execute();
        
        if ($sireInfo = $sireStmt->fetch(PDO::FETCH_ASSOC)) {
            $parentInfo['sire_dam'] = getParentInfo($db, $sireInfo['dam_id'], $current_user);
            $parentInfo['sire_sire'] = getParentInfo($db, $sireInfo['sire_id'], $current_user);
        }
    }
}

// Get offspring of selected animal
$offspring = [];
if ($animal) {
    $parentField = $animal['gender'] === 'Female' ? 'dam_id' : 'sire_id';
    
    $offspringStmt = $db->prepare("
        SELECT id, name, number, gender, breed, dob, status 
        FROM animals 
        WHERE $parentField = :parent_id AND user_id = :user_id 
        ORDER BY dob DESC, name ASC
    ");
    $offspringStmt->bindParam(':parent_id', $animal_id, PDO::PARAM_INT);
    $offspringStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $offspringStmt->execute();
    
    $offspring = $offspringStmt->fetchAll();
}

// Get animals with offspring (breeding stock)
$breedingStockQuery = "
    SELECT DISTINCT a.id, a.name, a.number, a.type, a.breed, a.gender, a.status
    FROM animals a
    INNER JOIN animals o ON (o.dam_id = a.id OR o.sire_id = a.id)
    WHERE a.user_id = :user_id
";

if ($animal_type) {
    $breedingStockQuery .= " AND a.type = :type";
}

$breedingStockQuery .= " ORDER BY a.type, a.name";

$breedingStockStmt = $db->prepare($breedingStockQuery);
$breedingStockStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);

if ($animal_type) {
    $breedingStockStmt->bindParam(':type', $animal_type, PDO::PARAM_STR);
}

$breedingStockStmt->execute();
$breedingStock = $breedingStockStmt->fetchAll();

// Get count of offspring for each breeding animal
foreach ($breedingStock as &$animal) {
    $offspringCountStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM animals 
        WHERE (dam_id = :id OR sire_id = :id) AND user_id = :user_id
    ");
    $offspringCountStmt->bindParam(':id', $animal['id'], PDO::PARAM_INT);
    $offspringCountStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $offspringCountStmt->execute();
    $animal['offspring_count'] = $offspringCountStmt->fetch()['count'];
}
unset($animal); // Break the reference

// Get potential breeding stock (alive animals with no offspring)
$potentialStockQuery = "
    SELECT a.id, a.name, a.number, a.type, a.breed, a.gender, a.dob
    FROM animals a
    WHERE a.user_id = :user_id
    AND a.status = 'Alive'
    AND NOT EXISTS (
        SELECT 1 FROM animals o 
        WHERE (o.dam_id = a.id OR o.sire_id = a.id) AND o.user_id = :user_id
    )
";

if ($animal_type) {
    $potentialStockQuery .= " AND a.type = :type";
}

$potentialStockQuery .= " ORDER BY a.type, a.gender, a.name";

$potentialStockStmt = $db->prepare($potentialStockQuery);
$potentialStockStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);

if ($animal_type) {
    $potentialStockStmt->bindParam(':type', $animal_type, PDO::PARAM_STR);
}

$potentialStockStmt->execute();
$potentialStock = $potentialStockStmt->fetchAll();

// Include header
include_once 'includes/header.php';
?>

<!-- Filter and Actions -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Filter Lineage Report</h5>
            </div>
            <div class="card-body">
                <form action="report_lineage.php" method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="type" class="form-label">Animal Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($availableTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type['type']) ?>" <?= $animal_type === $type['type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['type']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Apply Filter
                        </button>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <a href="report_lineage.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle"></i> Clear Filter
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#lineageHelp">
                        <i class="bi bi-question-circle"></i> Lineage Management Help
                    </button>
                    <button type="button" onclick="window.print();" class="btn btn-outline-dark">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($animal): ?>
<!-- Ancestry Tree -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Ancestry Tree</h3>
        <div class="btn-group">
            <a href="report_lineage.php?id=<?= $animal_id ?>&view=tree" class="btn btn-sm btn-outline-primary <?= $view_mode === 'tree' ? 'active' : '' ?>">
                <i class="bi bi-diagram-3"></i> Tree View
            </a>
            <a href="report_lineage.php?id=<?= $animal_id ?>&view=list" class="btn btn-sm btn-outline-primary <?= $view_mode === 'list' ? 'active' : '' ?>">
                <i class="bi bi-list-ul"></i> List View
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($view_mode === 'tree'): ?>
        <!-- Tree View -->
        <div class="ancestry-tree">
            <div class="row">
                <!-- Main Animal -->
                <div class="col-md-12 text-center mb-4">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?= htmlspecialchars($animal['name']) ?> (<?= htmlspecialchars($animal['number']) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($animal['breed']) ?></p>
                            <p class="mb-1"><strong>Gender:</strong> <?= htmlspecialchars($animal['gender']) ?></p>
                            <p class="mb-0">
                                <span class="badge rounded-pill bg-<?= getStatusBadgeClass($animal['status']) ?>">
                                    <?= htmlspecialchars($animal['status']) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Parents -->
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card border-danger mb-3">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Dam (Mother)</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($parentInfo['dam']['id']): ?>
                            <h6 class="card-title"><?= htmlspecialchars($parentInfo['dam']['name']) ?> (<?= htmlspecialchars($parentInfo['dam']['number']) ?>)</h6>
                            <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($parentInfo['dam']['breed']) ?></p>
                            <p class="mb-1">
                                <span class="badge rounded-pill bg-<?= getStatusBadgeClass($parentInfo['dam']['status']) ?>">
                                    <?= htmlspecialchars($parentInfo['dam']['status']) ?>
                                </span>
                            </p>
                            <div class="text-center mt-2">
                                <a href="report_lineage.php?id=<?= $parentInfo['dam']['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-diagram-3"></i> View Lineage
                                </a>
                                <a href="animal_view.php?id=<?= $parentInfo['dam']['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-eye"></i> View Animal
                                </a>
                            </div>
                            <?php else: ?>
                            <p class="text-muted mb-0">No dam information available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-5 offset-md-2">
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Sire (Father)</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($parentInfo['sire']['id']): ?>
                            <h6 class="card-title"><?= htmlspecialchars($parentInfo['sire']['name']) ?> (<?= htmlspecialchars($parentInfo['sire']['number']) ?>)</h6>
                            <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($parentInfo['sire']['breed']) ?></p>
                            <p class="mb-1">
                                <span class="badge rounded-pill bg-<?= getStatusBadgeClass($parentInfo['sire']['status']) ?>">
                                    <?= htmlspecialchars($parentInfo['sire']['status']) ?>
                                </span>
                            </p>
                            <div class="text-center mt-2">
                                <a href="report_lineage.php?id=<?= $parentInfo['sire']['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-diagram-3"></i> View Lineage
                                </a>
                                <a href="animal_view.php?id=<?= $parentInfo['sire']['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-eye"></i> View Animal
                                </a>
                            </div>
                            <?php else: ?>
                            <p class="text-muted mb-0">No sire information available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grandparents -->
            <div class="row">
                <!-- Maternal Grandparents -->
                <div class="col-md-6">
                    <h5 class="text-center mb-3">Maternal Grandparents</h5>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="card border-danger mb-3">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">Maternal Grandmother</h6>
                                </div>
                                <div class="card-body small">
                                    <?php if ($parentInfo['dam_dam']['id']): ?>
                                    <p class="mb-1"><strong><?= htmlspecialchars($parentInfo['dam_dam']['name']) ?></strong> (<?= htmlspecialchars($parentInfo['dam_dam']['number']) ?>)</p>
                                    <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($parentInfo['dam_dam']['breed']) ?></p>
                                    <p class="mb-1">
                                        <span class="badge rounded-pill bg-<?= getStatusBadgeClass($parentInfo['dam_dam']['status']) ?>">
                                            <?= htmlspecialchars($parentInfo['dam_dam']['status']) ?>
                                        </span>
                                    </p>
                                    <div class="text-center mt-2">
                                        <a href="report_lineage.php?id=<?= $parentInfo['dam_dam']['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-diagram-3"></i> Lineage
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted mb-0">No information available</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-5 offset-md-2">
                            <div class="card border-primary mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Maternal Grandfather</h6>
                                </div>
                                <div class="card-body small">
                                    <?php if ($parentInfo['dam_sire']['id']): ?>
                                    <p class="mb-1"><strong><?= htmlspecialchars($parentInfo['dam_sire']['name']) ?></strong> (<?= htmlspecialchars($parentInfo['dam_sire']['number']) ?>)</p>
                                    <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($parentInfo['dam_sire']['breed']) ?></p>
                                    <p class="mb-1">
                                        <span class="badge rounded-pill bg-<?= getStatusBadgeClass($parentInfo['dam_sire']['status']) ?>">
                                            <?= htmlspecialchars($parentInfo['dam_sire']['status']) ?>
                                        </span>
                                    </p>
                                    <div class="text-center mt-2">
                                        <a href="report_lineage.php?id=<?= $parentInfo['dam_sire']['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-diagram-3"></i> Lineage
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted mb-0">No information available</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Paternal Grandparents -->
                <div class="col-md-6">
                    <h5 class="text-center mb-3">Paternal Grandparents</h5>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="card border-danger mb-3">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">Paternal Grandmother</h6>
                                </div>
                                <div class="card-body small">
                                    <?php if ($parentInfo['sire_dam']['id']): ?>
                                    <p class="mb-1"><strong><?= htmlspecialchars($parentInfo['sire_dam']['name']) ?></strong> (<?= htmlspecialchars($parentInfo['sire_dam']['number']) ?>)</p>
                                    <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($parentInfo['sire_dam']['breed']) ?></p>
                                    <p class="mb-1">
                                        <span class="badge rounded-pill bg-<?= getStatusBadgeClass($parentInfo['sire_dam']['status']) ?>">
                                            <?= htmlspecialchars($parentInfo['sire_dam']['status']) ?>
                                        </span>
                                    </p>
                                    <div class="text-center mt-2">
                                        <a href="report_lineage.php?id=<?= $parentInfo['sire_dam']['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-diagram-3"></i> Lineage
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted mb-0">No information available</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-5 offset-md-2">
                            <div class="card border-primary mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Paternal Grandfather</h6>
                                </div>
                                <div class="card-body small">
                                    <?php if ($parentInfo['sire_sire']['id']): ?>
                                    <p class="mb-1"><strong><?= htmlspecialchars($parentInfo['sire_sire']['name']) ?></strong> (<?= htmlspecialchars($parentInfo['sire_sire']['number']) ?>)</p>
                                    <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($parentInfo['sire_sire']['breed']) ?></p>
                                    <p class="mb-1">
                                        <span class="badge rounded-pill bg-<?= getStatusBadgeClass($parentInfo['sire_sire']['status']) ?>">
                                            <?= htmlspecialchars($parentInfo['sire_sire']['status']) ?>
                                        </span>
                                    </p>
                                    <div class="text-center mt-2">
                                        <a href="report_lineage.php?id=<?= $parentInfo['sire_sire']['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-diagram-3"></i> Lineage
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted mb-0">No information available</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- List View -->
        <div class="row">
            <div class="col-lg-6">
                <h4>Parents</h4>
                <table class="table table-bordered">
                    <tr>
                        <th class="table-primary">Dam (Mother)</th>
                        <td>
                            <?php if ($parentInfo['dam']['id']): ?>
                            <a href="report_lineage.php?id=<?= $parentInfo['dam']['id'] ?>">
                                <?= htmlspecialchars($parentInfo['dam']['name']) ?> (<?= htmlspecialchars($parentInfo['dam']['number']) ?>)
                            </a>
                            <?php else: ?>
                            Unknown
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="table-primary">Sire (Father)</th>
                        <td>
                            <?php if ($parentInfo['sire']['id']): ?>
                            <a href="report_lineage.php?id=<?= $parentInfo['sire']['id'] ?>">
                                <?= htmlspecialchars($parentInfo['sire']['name']) ?> (<?= htmlspecialchars($parentInfo['sire']['number']) ?>)
                            </a>
                            <?php else: ?>
                            Unknown
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="col-lg-6">
                <h4>Grandparents</h4>
                <table class="table table-bordered">
                    <tr>
                        <th class="table-danger">Maternal Grandmother</th>
                        <td>
                            <?php if ($parentInfo['dam_dam']['id']): ?>
                            <a href="report_lineage.php?id=<?= $parentInfo['dam_dam']['id'] ?>">
                                <?= htmlspecialchars($parentInfo['dam_dam']['name']) ?> (<?= htmlspecialchars($parentInfo['dam_dam']['number']) ?>)
                            </a>
                            <?php else: ?>
                            Unknown
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="table-danger">Maternal Grandfather</th>
                        <td>
                            <?php if ($parentInfo['dam_sire']['id']): ?>
                            <a href="report_lineage.php?id=<?= $parentInfo['dam_sire']['id'] ?>">
                                <?= htmlspecialchars($parentInfo['dam_sire']['name']) ?> (<?= htmlspecialchars($parentInfo['dam_sire']['number']) ?>)
                            </a>
                            <?php else: ?>
                            Unknown
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="table-primary">Paternal Grandmother</th>
                        <td>
                            <?php if ($parentInfo['sire_dam']['id']): ?>
                            <a href="report_lineage.php?id=<?= $parentInfo['sire_dam']['id'] ?>">
                                <?= htmlspecialchars($parentInfo['sire_dam']['name']) ?> (<?= htmlspecialchars($parentInfo['sire_dam']['number']) ?>)
                            </a>
                            <?php else: ?>
                            Unknown
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="table-primary">Paternal Grandfather</th>
                        <td>
                            <?php if ($parentInfo['sire_sire']['id']): ?>
                            <a href="report_lineage.php?id=<?= $parentInfo['sire_sire']['id'] ?>">
                                <?= htmlspecialchars($parentInfo['sire_sire']['name']) ?> (<?= htmlspecialchars($parentInfo['sire_sire']['number']) ?>)
                            </a>
                            <?php else: ?>
                            Unknown
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Offspring List -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h3 class="mb-0">Offspring</h3>
    </div>
    <div class="card-body">
        <?php if (empty($offspring)): ?>
        <div class="alert alert-info">
            No offspring found for this animal.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Number</th>
                        <th>Gender</th>
                        <th>Breed</th>
                        <th>Date of Birth</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offspring as $child): 
                        // Format date
                        $dob = !empty($child['dob']) ? date('M j, Y', strtotime($child['dob'])) : 'Unknown';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($child['name']) ?></td>
                        <td><?= htmlspecialchars($child['number']) ?></td>
                        <td><?= htmlspecialchars($child['gender']) ?></td>
                        <td><?= htmlspecialchars($child['breed']) ?></td>
                        <td><?= $dob ?></td>
                        <td>
                            <span class="badge rounded-pill bg-<?= getStatusBadgeClass($child['status']) ?>">
                                <?= htmlspecialchars($child['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="report_lineage.php?id=<?= $child['id'] ?>" class="btn btn-outline-primary" title="View Lineage">
                                    <i class="bi bi-diagram-3"></i>
                                </a>
                                <a href="animal_view.php?id=<?= $child['id'] ?>" class="btn btn-outline-success" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<!-- Breeding Stock List -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h3 class="mb-0">Breeding Stock</h3>
    </div>
    <div class="card-body">
        <?php if (empty($breedingStock)): ?>
        <div class="alert alert-info">
            No animals with recorded offspring found. Start establishing lineage connections by editing your animals and selecting parents.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Number</th>
                        <th>Type</th>
                        <th>Breed</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th class="text-center">Offspring</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($breedingStock as $animal): 
                        $offspringCount = $animal['offspring_count'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($animal['name']) ?></td>
                        <td><?= htmlspecialchars($animal['number']) ?></td>
                        <td><?= htmlspecialchars($animal['type']) ?></td>
                        <td><?= htmlspecialchars($animal['breed']) ?></td>
                        <td><?= htmlspecialchars($animal['gender']) ?></td>
                        <td>
                            <span class="badge rounded-pill bg-<?= getStatusBadgeClass($animal['status']) ?>">
                                <?= htmlspecialchars($animal['status']) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= $offspringCount ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="report_lineage.php?id=<?= $animal['id'] ?>" class="btn btn-outline-primary" title="View Lineage">
                                    <i class="bi bi-diagram-3"></i>
                                </a>
                                <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-outline-success" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($potentialStock)): ?>
<!-- Potential Breeding Stock -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h3 class="mb-0">Potential Breeding Stock</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            These animals are alive but have no recorded offspring. You can establish lineage connections by editing these animals or adding new animals and selecting them as parents.
        </div>
        
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Number</th>
                    <th>Type</th>
                    <th>Breed</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($potentialStock as $animal): 
                    // Calculate age
                    $age = "Unknown";
                    if (!empty($animal['dob'])) {
                        $dobDate = new DateTime($animal['dob']);
                        $now = new DateTime();
                        $interval = $now->diff($dobDate);
                        
                        if ($interval->y > 0) {
                            $age = $interval->y . " year" . ($interval->y > 1 ? "s" : "");
                        } else if ($interval->m > 0) {
                            $age = $interval->m . " month" . ($interval->m > 1 ? "s" : "");
                        } else {
                            $age = $interval->d . " day" . ($interval->d > 1 ? "s" : "");
                        }
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($animal['name']) ?></td>
                    <td><?= htmlspecialchars($animal['number']) ?></td>
                    <td><?= htmlspecialchars($animal['type']) ?></td>
                    <td><?= htmlspecialchars($animal['breed']) ?></td>
                    <td><?= htmlspecialchars($animal['gender']) ?></td>
                    <td><?= $age ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="report_lineage.php?id=<?= $animal['id'] ?>" class="btn btn-outline-primary" title="View Lineage">
                                <i class="bi bi-diagram-3"></i>
                            </a>
                            <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-outline-success" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="animal_edit.php?id=<?= $animal['id'] ?>" class="btn btn-outline-warning" title="Edit Animal">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Lineage Help Modal -->
<div class="modal fade" id="lineageHelp" tabindex="-1" aria-labelledby="lineageHelpLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lineageHelpLabel">Lineage Management Help</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5>Understanding Lineage in FarmApp</h5>
                <p>The lineage system helps you track family relationships and breeding history for your animals.</p>
                
                <h6>How to Establish Lineage Connections:</h6>
                <ol>
                    <li>When adding or editing an animal, select its dam (mother) and sire (father) from the dropdown menus.</li>
                    <li>Only animals of the appropriate gender will be shown as options (females as dams, males as sires).</li>
                    <li>Once connections are established, you can view family trees and track offspring.</li>
                </ol>
                
                <h6>Lineage Report Features:</h6>
                <ul>
                    <li><strong>Ancestry Tree:</strong> Shows parents and grandparents for a selected animal.</li>
                    <li><strong>Offspring List:</strong> Shows all recorded offspring for a selected animal.</li>
                    <li><strong>Breeding Stock:</strong> Animals that have offspring recorded in the system.</li>
                    <li><strong>Potential Breeding Stock:</strong> Animals that are alive but have no recorded offspring.</li>
                </ul>
                
                <h6>Lineage Best Practices:</h6>
                <ul>
                    <li>Record parent information when adding new animals to build your lineage database.</li>
                    <li>For purchased animals, record as much lineage information as available.</li>
                    <li>Use the lineage report to make breeding decisions and avoid inbreeding.</li>
                    <li>Track performance across generations to improve your breeding program.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

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