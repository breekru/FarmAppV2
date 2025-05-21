<?php
/**
 * Animal Listing Page
 * 
 * This page displays a list of animals filtered by type (optional).
 * It includes searching, sorting, and pagination functionality.
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

// Get current user
$current_user = $_SESSION["username"];

// Process animal type filter
$animal_type = isset($_GET['type']) ? $_GET['type'] : null;

// Set page title and header based on animal type
if ($animal_type) {
    $page_title = $animal_type . "s";
    $page_header = "View " . $animal_type . " Records";
    $page_subheader = "Manage your " . strtolower($animal_type) . " inventory";
} else {
    $page_title = "All Animals";
    $page_header = "View All Animals";
    $page_subheader = "Manage your complete livestock inventory";
}

// Get database connection
$db = getDbConnection();

// Prepare base query
$baseQuery = "
    SELECT id, type, breed, number, name, gender, status 
    FROM animals 
    WHERE user_id = :user_id 
";

$countQuery = "
    SELECT COUNT(*) as total
    FROM animals 
    WHERE user_id = :user_id 
";

// Add type filter if specified
$params = [':user_id' => $current_user];
if ($animal_type) {
    $baseQuery .= " AND type = :type";
    $countQuery .= " AND type = :type";
    $params[':type'] = $animal_type;
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $searchWhere = " AND (
        name LIKE :search OR 
        number LIKE :search OR 
        breed LIKE :search OR
        status LIKE :search
    )";
    $baseQuery .= $searchWhere;
    $countQuery .= $searchWhere;
    $params[':search'] = "%$search%";
}

// Sorting
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$sortOrder = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

// Validate sort column to prevent SQL injection
$allowedColumns = ['name', 'number', 'breed', 'gender', 'type', 'status'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'name';
}

$baseQuery .= " ORDER BY $sortColumn $sortOrder";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

$baseQuery .= " LIMIT :offset, :limit";

// Get total records count for pagination
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get records for current page
$stmt = $db->prepare($baseQuery);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$animals = $stmt->fetchAll();

// Include header
include_once 'includes/header.php';
?>

<!-- Main Content -->
<div class="row mb-3">
    <div class="col-md-6">
        <h2><?= htmlspecialchars($page_header) ?></h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="animal_add.php<?= $animal_type ? '?type=' . urlencode($animal_type) : '' ?>" 
           class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add New <?= $animal_type ?? 'Animal' ?>
        </a>
    </div>
</div>

<!-- Search and Filter Controls -->
<div class="row mb-4">
    <div class="col-md-6">
        <form action="" method="get" class="d-flex">
            <?php if ($animal_type): ?>
            <input type="hidden" name="type" value="<?= htmlspecialchars($animal_type) ?>">
            <?php endif; ?>
            
            <div class="input-group">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by name, number, breed..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                <a href="<?= $animal_type ? "?type=" . urlencode($animal_type) : "" ?>" 
                   class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-6 text-end">
        <?php if (!$animal_type): ?>
        <div class="btn-group">
            <a href="animal_list.php" class="btn btn-outline-secondary <?= !$animal_type ? 'active' : '' ?>">All</a>
            <a href="animal_list.php?type=Sheep" class="btn btn-outline-secondary <?= $animal_type === 'Sheep' ? 'active' : '' ?>">Sheep</a>
            <a href="animal_list.php?type=Chicken" class="btn btn-outline-secondary <?= $animal_type === 'Chicken' ? 'active' : '' ?>">Chickens</a>
            <a href="animal_list.php?type=Turkey" class="btn btn-outline-secondary <?= $animal_type === 'Turkey' ? 'active' : '' ?>">Turkeys</a>
            <a href="animal_list.php?type=Pig" class="btn btn-outline-secondary <?= $animal_type === 'Pig' ? 'active' : '' ?>">Pigs</a>
            <a href="animal_list.php?type=Cow" class="btn btn-outline-secondary <?= $animal_type === 'Cow' ? 'active' : '' ?>">Cows</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Animal Listing Table -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($animals)): ?>
        <div class="alert alert-info">
            No animals found. <?php if (!empty($search)): ?>Try a different search or <?php endif; ?>
            <a href="animal_add.php<?= $animal_type ? '?type=' . urlencode($animal_type) : '' ?>">add a new animal</a>.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'number', 'order' => $sortColumn === 'number' && $sortOrder === 'ASC' ? 'desc' : 'asc'])) ?>">
                                Number
                                <?php if ($sortColumn === 'number'): ?>
                                <i class="bi bi-sort-<?= $sortOrder === 'ASC' ? 'down' : 'up' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'name', 'order' => $sortColumn === 'name' && $sortOrder === 'ASC' ? 'desc' : 'asc'])) ?>">
                                Name
                                <?php if ($sortColumn === 'name'): ?>
                                <i class="bi bi-sort-<?= $sortOrder === 'ASC' ? 'down' : 'up' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'gender', 'order' => $sortColumn === 'gender' && $sortOrder === 'ASC' ? 'desc' : 'asc'])) ?>">
                                Gender
                                <?php if ($sortColumn === 'gender'): ?>
                                <i class="bi bi-sort-<?= $sortOrder === 'ASC' ? 'down' : 'up' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'breed', 'order' => $sortColumn === 'breed' && $sortOrder === 'ASC' ? 'desc' : 'asc'])) ?>">
                                Breed
                                <?php if ($sortColumn === 'breed'): ?>
                                <i class="bi bi-sort-<?= $sortOrder === 'ASC' ? 'down' : 'up' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <?php if (!$animal_type): ?>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'type', 'order' => $sortColumn === 'type' && $sortOrder === 'ASC' ? 'desc' : 'asc'])) ?>">
                                Type
                                <?php if ($sortColumn === 'type'): ?>
                                <i class="bi bi-sort-<?= $sortOrder === 'ASC' ? 'down' : 'up' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <?php endif; ?>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'status', 'order' => $sortColumn === 'status' && $sortOrder === 'ASC' ? 'desc' : 'asc'])) ?>">
                                Status
                                <?php if ($sortColumn === 'status'): ?>
                                <i class="bi bi-sort-<?= $sortOrder === 'ASC' ? 'down' : 'up' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($animals as $animal): ?>
                    <tr>
                        <td><?= htmlspecialchars($animal['number']) ?></td>
                        <td><?= htmlspecialchars($animal['name']) ?></td>
                        <td><?= htmlspecialchars($animal['gender']) ?></td>
                        <td><?= htmlspecialchars($animal['breed']) ?></td>
                        <?php if (!$animal_type): ?>
                        <td><?= htmlspecialchars($animal['type']) ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge rounded-pill bg-<?= getStatusBadgeClass($animal['status']) ?>">
                                <?= htmlspecialchars($animal['status']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="animal_view.php?id=<?= $animal['id'] ?>" class="btn btn-outline-success" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="animal_edit.php?id=<?= $animal['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal<?= $animal['id'] ?>" 
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            
                            <!-- Delete Confirmation Modal -->
                            <div class="modal fade" id="deleteModal<?= $animal['id'] ?>" tabindex="-1" 
                                 aria-labelledby="deleteModalLabel<?= $animal['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel<?= $animal['id'] ?>">Confirm Delete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            Are you sure you want to delete <?= htmlspecialchars($animal['name']) ?> 
                                            (<?= htmlspecialchars($animal['number']) ?>)?
                                            <p class="text-danger mt-2">This action cannot be undone.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <a href="animal_delete.php?id=<?= $animal['id'] ?>" class="btn btn-danger">Delete</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Animal list pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
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