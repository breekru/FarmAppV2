<?php
/**
 * Dashboard/Index Page
 * 
 * This is the main dashboard page that shows summary information and stats.
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

// Get farm info if available
$farmStmt = $db->prepare("
    SELECT f_name, l_name, farm_name 
    FROM users 
    WHERE username = :username
");
$farmStmt->bindParam(':username', $current_user, PDO::PARAM_STR);
$farmStmt->execute();
$farmInfo = $farmStmt->fetch();

// Get total animal count
$totalStmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM animals 
    WHERE user_id = :user_id
");
$totalStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$totalStmt->execute();
$totalAnimals = $totalStmt->fetch()['total'];

// Get counts for each animal type
$typeStmt = $db->prepare("
    SELECT type, COUNT(*) as count 
    FROM animals 
    WHERE user_id = :user_id 
    GROUP BY type
");
$typeStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$typeStmt->execute();
$animalTypes = $typeStmt->fetchAll();

// Convert to associative array for easier access
$typeData = [];
foreach ($animalTypes as $type) {
    $typeData[$type['type']] = $type['count'];
}

// Get total animals by status
$statusStmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM animals 
    WHERE user_id = :user_id 
    GROUP BY status
");
$statusStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$statusStmt->execute();
$statusData = [];
foreach ($statusStmt->fetchAll() as $status) {
    $statusData[$status['status']] = $status['count'];
}

// Get recent animals (last 5 added)
$recentStmt = $db->prepare("
    SELECT id, name, type, status, created_at 
    FROM animals 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$recentStmt->execute();
$recentAnimals = $recentStmt->fetchAll();

// Get for sale animals
$forSaleStmt = $db->prepare("
    SELECT id, name, type, breed, status, sell_price
    FROM animals 
    WHERE user_id = :user_id 
    AND for_sale = 'Yes' 
    ORDER BY date_purchased DESC 
    LIMIT 5
");
$forSaleStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$forSaleStmt->execute();
$forSaleAnimals = $forSaleStmt->fetchAll();

// Set page variables
$page_title = "Dashboard";
$page_header = $farmInfo['farm_name'] ? $farmInfo['farm_name'] . " Dashboard" : "Farm Dashboard";
$page_subheader = "Welcome, " . ($farmInfo['f_name'] ? $farmInfo['f_name'] . " " . $farmInfo['l_name'] : $current_user);

// Include header
include_once 'includes/header.php';
?>

<!-- Dashboard Content -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100 stat-card">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-clipboard-data"></i></div>
                <div class="stat-value"><?= $totalAnimals ?></div>
                <div class="stat-label">Total Animals</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100 stat-card">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-heart"></i></div>
                <div class="stat-value"><?= $statusData['Alive'] ?? 0 ?></div>
                <div class="stat-label">Alive</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100 stat-card">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-tag"></i></div>
                <div class="stat-value"><?= $statusData['For Sale'] ?? 0 ?></div>
                <div class="stat-label">For Sale</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card h-100 stat-card">
            <div class="card-body">
                <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
                <div class="stat-value"><?= $statusData['Sold'] ?? 0 ?></div>
                <div class="stat-label">Sold</div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Animal Type Distribution -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Animal Types</h5>
                <a href="animal_list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if ($totalAnimals > 0): ?>
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="animalTypeChart"></canvas>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3"><i class="bi bi-bar-chart-line text-muted" style="font-size: 3rem;"></i></div>
                    <h5>No Animals Yet</h5>
                    <p class="text-muted">Add animals to see statistics</p>
                    <a href="animal_add.php" class="btn btn-primary">Add Your First Animal</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recently Added Animals -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recently Added</h5>
                <a href="animal_list.php?sort=created_at&order=desc" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentAnimals)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Added</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAnimals as $animal): ?>
                            <tr>
                                <td><?= htmlspecialchars($animal['name']) ?></td>
                                <td><?= htmlspecialchars($animal['type']) ?></td>
                                <td>
                                    <span class="badge rounded-pill bg-<?= getStatusBadgeClass($animal['status']) ?>">
                                        <?= htmlspecialchars($animal['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($animal['created_at'])) ?></td>
                                <td>
                                    <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3"><i class="bi bi-list-ul text-muted" style="font-size: 3rem;"></i></div>
                    <h5>No Animals Yet</h5>
                    <p class="text-muted">Add animals to see them listed here</p>
                    <a href="animal_add.php" class="btn btn-primary">Add Your First Animal</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Animals For Sale -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">For Sale</h5>
                <a href="animal_list.php?status=For%20Sale" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($forSaleAnimals)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Breed</th>
                                <th>Price</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forSaleAnimals as $animal): ?>
                            <tr>
                                <td><?= htmlspecialchars($animal['name']) ?></td>
                                <td><?= htmlspecialchars($animal['type']) ?></td>
                                <td><?= htmlspecialchars($animal['breed']) ?></td>
                                <td>$<?= number_format((float)$animal['sell_price'], 2) ?></td>
                                <td>
                                    <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3"><i class="bi bi-tag text-muted" style="font-size: 3rem;"></i></div>
                    <h5>No Animals For Sale</h5>
                    <p class="text-muted">Mark animals as "For Sale" to list them here</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="animal_add.php" class="btn btn-success w-100 p-3">
                            <i class="bi bi-plus-circle-fill me-2"></i>
                            Add New Animal
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="report_inventory.php" class="btn btn-primary w-100 p-3">
                            <i class="bi bi-clipboard-data me-2"></i>
                            Generate Report
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="report_lineage.php" class="btn btn-info w-100 p-3 text-white">
                            <i class="bi bi-diagram-3 me-2"></i>
                            View Lineage
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="profile.php" class="btn btn-secondary w-100 p-3">
                            <i class="bi bi-gear me-2"></i>
                            Farm Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize chart if there are animals
    <?php if ($totalAnimals > 0): ?>
    // Animal type chart
    const typeCtx = document.getElementById('animalTypeChart');
    const typeLabels = [<?= "'" . implode("', '", array_keys($typeData)) . "'" ?>];
    const typeValues = [<?= implode(", ", array_values($typeData)) ?>];
    const typeColors = [
        'rgba(25, 135, 84, 0.7)',    // Green
        'rgba(13, 110, 253, 0.7)',   // Blue
        'rgba(255, 193, 7, 0.7)',    // Yellow
        'rgba(220, 53, 69, 0.7)',    // Red
        'rgba(108, 117, 125, 0.7)'   // Gray
    ];
    
    new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: typeLabels,
            datasets: [{
                data: typeValues,
                backgroundColor: typeColors,
                borderColor: 'white',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    <?php endif; ?>
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
<a href="quick_add.php" class="floating-action-button d-md-none">
    <i class="bi bi-plus-lg"></i>
</a>
// Include footer
include_once 'includes/footer.php';
?>