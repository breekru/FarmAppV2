<?php
/**
 * Inventory Report Page
 * 
 * This page displays an inventory report of all animals with filtering options.
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

// Important: Get database connection
$db = getDbConnection();

// CRITICAL FIX: Get type only from URL parameter and not from anywhere else
// Force clean GET parameters to avoid any interference
$type = '';
$originalType = '';

if (isset($_GET['type'])) {
    // Store the original type for debugging
    $originalType = trim($_GET['type']);
    // Set the actual type variable
    $type = $originalType;
    
    // Debug output
    // echo "<!-- DEBUG: original type from URL = '" . htmlspecialchars($originalType) . "' -->";
    // echo "<!-- DEBUG: type variable set to = '" . htmlspecialchars($type) . "' -->";
}

// Similarly handle other filters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$gender = isset($_GET['gender']) ? trim($_GET['gender']) : '';

// Set page variables
$page_title = "Inventory Report";
$page_header = "Livestock Inventory Report";
$page_subheader = "Track and manage your farm animal inventory";

// Store the current type value
$type_before_query = $type;

// Prepare base query
$baseQuery = "
    SELECT id, type, breed, number, name, gender, status, dob 
    FROM animals 
    WHERE user_id = :user_id 
";

// Add filters
$params = [':user_id' => $current_user];

if (!empty($type)) {
    $baseQuery .= " AND type = :type";
    $params[':type'] = $type;
}

if (!empty($status)) {
    $baseQuery .= " AND status = :status";
    $params[':status'] = $status;
}

if (!empty($gender)) {
    $baseQuery .= " AND gender = :gender";
    $params[':gender'] = $gender;
}

// Add sorting
$baseQuery .= " ORDER BY type, name";

// Execute the query
$stmt = $db->prepare($baseQuery);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$animals = $stmt->fetchAll();

// Get totals by type
$typeStmt = $db->prepare("
    SELECT type, COUNT(*) as count 
    FROM animals 
    WHERE user_id = :user_id 
    GROUP BY type
");
$typeStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$typeStmt->execute();
$typeData = $typeStmt->fetchAll();

// Get totals by status
$statusStmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM animals 
    WHERE user_id = :user_id 
    GROUP BY status
");
$statusStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$statusStmt->execute();
$statusData = $statusStmt->fetchAll();

// Get available animal types for filter dropdown
$typesStmt = $db->prepare("
    SELECT DISTINCT type FROM animals 
    WHERE user_id = :user_id 
    ORDER BY type
");
$typesStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$typesStmt->execute();
$availableTypes = $typesStmt->fetchAll();

// Get breed data for chart
$breedStmt = $db->prepare("
    SELECT type, breed, COUNT(*) as count 
    FROM animals 
    WHERE user_id = :user_id 
    GROUP BY type, breed 
    ORDER BY type, count DESC
");
$breedStmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
$breedStmt->execute();
$breedData = [];

while ($row = $breedStmt->fetch()) {
    $type = $row['type'];
    $breed = $row['breed'] ?: 'Unknown';
    $count = $row['count'];
    
    if (!isset($breedData[$type])) {
        $breedData[$type] = [
            'labels' => [],
            'data' => []
        ];
    }
    
    $breedData[$type]['labels'][] = $breed;
    $breedData[$type]['data'][] = $count;
}

// Include header
include_once 'includes/header.php';

// Check if type has changed after including header.php
$type_after_header = $type;
if ($type_before_query !== $type_after_header) {
    // Force restoration of the original type from URL
    $type = $originalType;
}

?>

<!-- Filter Controls -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Filter Inventory</h5>
            </div>
            <div class="card-body">
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="get" class="row g-3" id="filterForm">
                    <div class="col-md-4">
                        <label for="type" class="form-label">Animal Type</label>
                        <!-- Force debugger output -->
                        <!-- Current type value: <?= htmlspecialchars($type) ?> -->
                        <select id="type" name="type" class="form-select">
                            <option value="">All Types</option>
                            <?php 
                            // Debug each option
                            foreach ($availableTypes as $availableType): 
                                $typeValue = $availableType['type'];
                                // Force strict comparison
                                $isSelected = (strcasecmp($type, $typeValue) === 0);
                                // Debug each comparison
                                // echo "<!-- Comparing '" . htmlspecialchars($type) . "' with '" . htmlspecialchars($typeValue) . "' = " . ($isSelected ? "MATCH" : "NO MATCH") . " -->";
                            ?>
                            <option value="<?= htmlspecialchars($typeValue) ?>" <?= $isSelected ? 'selected="selected"' : '' ?>>
                                <?= htmlspecialchars($typeValue) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Alive" <?= ($status === 'Alive') ? 'selected' : '' ?>>Alive</option>
                            <option value="Dead" <?= ($status === 'Dead') ? 'selected' : '' ?>>Dead</option>
                            <option value="Sold" <?= ($status === 'Sold') ? 'selected' : '' ?>>Sold</option>
                            <option value="For Sale" <?= ($status === 'For Sale') ? 'selected' : '' ?>>For Sale</option>
                            <option value="Harvested" <?= ($status === 'Harvested') ? 'selected' : '' ?>>Harvested</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="gender" class="form-label">Gender</label>
                        <select id="gender" name="gender" class="form-select">
                            <option value="">All Genders</option>
                            <option value="Male" <?= ($gender === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($gender === 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="applyFilters">
                            <i class="bi bi-filter"></i> Apply Filters
                        </button>
                        <a href="report_inventory.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Clear Filters
                        </a>
                        <button type="button" id="exportCSV" class="btn btn-success float-end">
                            <i class="bi bi-download"></i> Export to CSV
                        </button>
                        <button type="button" onclick="window.print();" class="btn btn-outline-dark float-end me-2">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </form>
                
                <script>
                // Add this to help debug the form submission and current values
                document.addEventListener('DOMContentLoaded', function() {
                    // Check URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    const urlType = urlParams.get('type');
                    
                    console.log('URL parameters:', {
                        type: urlType,
                        status: urlParams.get('status'),
                        gender: urlParams.get('gender')
                    });
                    
                    // Check what's selected in the dropdown
                    const typeDropdown = document.getElementById('type');
                    console.log('Dropdown selected value:', typeDropdown.value);
                    
                    // Check PHP variable passed to JavaScript
                    console.log('PHP type variable:', '<?= addslashes($type) ?>');
                    
                    // Force correct selection if needed
                    if (urlType && typeDropdown.value !== urlType) {
                        console.log('Fixing selection mismatch!');
                        // Find and select the correct option
                        for (let i = 0; i < typeDropdown.options.length; i++) {
                            if (typeDropdown.options[i].value === urlType) {
                                typeDropdown.selectedIndex = i;
                                console.log('Fixed selection to match URL parameter');
                                break;
                            }
                        }
                    }
                });
                
                document.getElementById('filterForm').addEventListener('submit', function(e) {
                    // Log form values before submit
                    const typeValue = document.getElementById('type').value;
                    const statusValue = document.getElementById('status').value;
                    const genderValue = document.getElementById('gender').value;
                    
                    console.log('Submitting with values:', {
                        type: typeValue,
                        status: statusValue,
                        gender: genderValue
                    });
                });
                </script>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Inventory Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 border-end">
                        <h6 class="text-center">By Type</h6>
                        <ul class="list-group list-group-flush">
                            <?php 
                            $totalAnimals = 0;
                            foreach ($typeData as $item): 
                                $totalAnimals += $item['count'];
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <?= htmlspecialchars($item['type']) ?>
                                <span class="badge bg-primary rounded-pill"><?= $item['count'] ?></span>
                            </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 fw-bold">
                                Total
                                <span class="badge bg-dark rounded-pill"><?= $totalAnimals ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <h6 class="text-center">By Status</h6>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($statusData as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <?= htmlspecialchars($item['status']) ?>
                                <span class="badge bg-<?= getStatusBadgeClass($item['status']) ?> rounded-pill"><?= $item['count'] ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Visualization -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Animals by Type</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">Breed Distribution</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="breedChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Detailed Inventory</h5>
        <div class="no-print">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#inventoryTable" aria-expanded="true">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="collapse show" id="inventoryTable">
        <div class="card-body">
            <?php if (empty($animals)): ?>
            <div class="alert alert-info">
                No animals found with the selected filters. <a href="report_inventory.php">Clear filters</a> to see all animals.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="inventory-table">
                    <thead class="table-light">
                        <tr>
                            <th>Number</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Breed</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th>Age</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($animals as $animal): 
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
                            <td><?= htmlspecialchars($animal['number']) ?></td>
                            <td><?= htmlspecialchars($animal['name']) ?></td>
                            <td><?= htmlspecialchars($animal['type']) ?></td>
                            <td><?= htmlspecialchars($animal['breed']) ?></td>
                            <td><?= htmlspecialchars($animal['gender']) ?></td>
                            <td>
                                <span class="badge rounded-pill bg-<?= getStatusBadgeClass($animal['status']) ?>">
                                    <?= htmlspecialchars($animal['status']) ?>
                                </span>
                            </td>
                            <td><?= $age ?></td>
                            <td class="no-print">
                                <div class="btn-group btn-group-sm">
                                    <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="animal_edit.php?id=<?= $animal['id'] ?>" class="btn btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the type parameter directly from URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlType = urlParams.get('type');
    
    // Define breedData object
    const breedData = {
        <?php 
        foreach ($breedData as $animalType => $data) {
            echo "'" . $animalType . "': {";
            echo "labels: ['" . implode("', '", $data['labels']) . "'],";
            echo "data: [" . implode(", ", $data['data']) . "]";
            echo "},\n";
        }
        ?>
    };
    
    // Type Chart
    const typeCtx = document.getElementById('typeChart');
    const typeLabels = [<?php 
        $labels = [];
        $values = [];
        foreach ($typeData as $item) {
            $labels[] = "'" . $item['type'] . "'";
            $values[] = $item['count'];
        }
        echo implode(', ', $labels);
    ?>];
    
    const typeValues = [<?= implode(', ', $values) ?>];
    
    new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: typeLabels,
            datasets: [{
                data: typeValues,
                backgroundColor: [
                    'rgba(25, 135, 84, 0.7)',    // Green
                    'rgba(13, 110, 253, 0.7)',   // Blue
                    'rgba(255, 193, 7, 0.7)',    // Yellow
                    'rgba(220, 53, 69, 0.7)',    // Red
                    'rgba(108, 117, 125, 0.7)'   // Gray
                ],
                borderColor: 'white',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Breed Chart - use URL parameter first, then PHP variable, then most common type
    const breedCtx = document.getElementById('breedChart');
    
    // Find most common type (as fallback)
    let mostCommonType = '<?= !empty($typeData) ? $typeData[0]['type'] : ''; ?>';
    let maxCount = <?= !empty($typeData) ? $typeData[0]['count'] : 0; ?>;
    
    <?php foreach ($typeData as $key => $item): ?>
    if (<?= $item['count'] ?> > maxCount) {
        maxCount = <?= $item['count'] ?>;
        mostCommonType = '<?= $item['type'] ?>';
    }
    <?php endforeach; ?>
    
    // Use URL parameter as the primary source of truth, then fall back to PHP variable, then to most common type
    let chartType = urlType || '<?= htmlspecialchars($type) ?>' || mostCommonType;
    
    // Default datasets
    let breedLabels = [];
    let breedValues = [];
    
    // Use the selected chart type if breed data exists
    if (breedData[chartType]) {
        breedLabels = breedData[chartType].labels;
        breedValues = breedData[chartType].data;
    } else if (breedData[mostCommonType]) {
        // Fallback to most common type if selected type has no breed data
        breedLabels = breedData[mostCommonType].labels;
        breedValues = breedData[mostCommonType].data;
        chartType = mostCommonType; // Update chart type for display
    }
    
    const breedChart = new Chart(breedCtx, {
        type: 'bar',
        data: {
            labels: breedLabels,
            datasets: [{
                label: 'Number of Animals',
                data: breedValues,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Breeds for ' + chartType
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Type filter change should update breed chart
    document.getElementById('type').addEventListener('change', function() {
        const newType = this.value;
        
        // Use selected type, or fallback to most common type if empty
        const typeToUse = newType || mostCommonType;
        
        if (breedData[typeToUse]) {
            breedChart.data.labels = breedData[typeToUse].labels;
            breedChart.data.datasets[0].data = breedData[typeToUse].data;
            breedChart.options.plugins.title.text = 'Breeds for ' + typeToUse;
            breedChart.update();
        }
    });
    
    // Make sure dropdown reflects URL parameter
    if (urlType) {
        const typeDropdown = document.getElementById('type');
        for (let i = 0; i < typeDropdown.options.length; i++) {
            if (typeDropdown.options[i].value === urlType) {
                typeDropdown.selectedIndex = i;
                break;
            }
        }
    }
    
    // Export to CSV functionality
    document.getElementById('exportCSV').addEventListener('click', function() {
        try {
            // Get table data
            const table = document.getElementById('inventory-table');
            if (!table) {
                console.error("Table with ID 'inventory-table' not found");
                alert("Could not find inventory table for export");
                return;
            }
            
            let csvContent = [];
            const rows = table.querySelectorAll('tr');
            
            // Process each row
            for (let i = 0; i < rows.length; i++) {
                const rowData = [];
                const cells = rows[i].querySelectorAll('td, th');
                
                // Process all cells except the last one (Actions column)
                for (let j = 0; j < cells.length - 1; j++) {
                    // Get text content only (strips HTML)
                    let cellText = cells[j].textContent.trim();
                    
                    // Handle special case for status badges (just extract the status text)
                    if (cells[j].querySelector('.badge')) {
                        cellText = cells[j].querySelector('.badge').textContent.trim();
                    }
                    
                    // Escape quotes and wrap in quotes
                    cellText = '"' + cellText.replace(/"/g, '""') + '"';
                    rowData.push(cellText);
                }
                
                csvContent.push(rowData.join(','));
            }
            
            // Join rows with newlines
            const csvString = csvContent.join('\n');
            
            // Generate filename with current date
            const filename = 'farm_inventory_' + new Date().toISOString().slice(0, 10) + '.csv';
            
            // Create download
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            
            // Create download link
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            // Set up and trigger download
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            
            // Trigger download and clean up
            link.click();
            
            // Clean up
            setTimeout(function() {
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            }, 100);
        } catch (error) {
            console.error("Error exporting CSV:", error);
            alert("Error exporting CSV: " + error.message);
        }
    });
});
</script>

<style>
@media print {
    .btn, .card-header button, .no-print {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #fff !important;
        border-bottom: 1px solid #000 !important;
        color: #000 !important;
    }
    
    .table {
        border-collapse: collapse !important;
    }
    
    .table td, .table th {
        border: 1px solid #ddd !important;
    }
}
</style>
<?php include_once 'includes/mobile_tab_bar.php'; ?>
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