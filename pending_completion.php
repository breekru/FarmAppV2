<?php
/**
 * Pending Completion Page
 * 
 * Shows a list of animals that were added using the quick add form
 * and need additional information to complete their records.
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
$page_title = "Animals Pending Completion";
$page_header = "Animals Pending Completion";
$page_subheader = "Complete the records of animals added through quick entry";

// Fetch animals pending completion
try {
    $stmt = $db->prepare("
        SELECT id, name, number, type, gender, dob, image, created_at
        FROM animals
        WHERE user_id = :user_id AND pending_completion = 'Yes'
        ORDER BY created_at DESC
    ");
    $stmt->bindParam(':user_id', $current_user, PDO::PARAM_STR);
    $stmt->execute();
    $pending_animals = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Pending Animals Error: ' . $e->getMessage());
    $pending_animals = [];
}

// Include header
include_once 'includes/header.php';
?>

<style>
    .animal-card {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.2s;
        height: 100%;
    }
    
    .animal-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .animal-image {
        height: 160px;
        background-size: cover;
        background-position: center;
        background-color: #f8f9fa;
    }
    
    .animal-image-placeholder {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
        background-color: #f8f9fa;
        color: #adb5bd;
    }
    
    .animal-info {
        padding: 15px;
    }
    
    .animal-name {
        font-weight: 600;
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .animal-type-badge {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
    
    .animal-details {
        font-size: 0.9rem;
        color: #6c757d;
    }
    
    .pending-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
    }
    
    @media (max-width: 768px) {
        .animal-image {
            height: 120px;
        }
    }
</style>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><?= $page_header ?></h2>
            <p class="lead"><?= $page_subheader ?></p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="quick_add.php" class="btn btn-primary btn-lg">
                <i class="bi bi-plus-circle"></i> Quick Add
            </a>
        </div>
    </div>
    
    <?php if (empty($pending_animals)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i> No animals are pending completion.
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($pending_animals as $animal): 
            $createdDate = new DateTime($animal['created_at']);
            $now = new DateTime();
            $interval = $now->diff($createdDate);
            
            // Format time since created
            if ($interval->days > 0) {
                $timeSince = $interval->days . " day" . ($interval->days > 1 ? "s" : "") . " ago";
            } elseif ($interval->h > 0) {
                $timeSince = $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
            } else {
                $timeSince = $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
            }
        ?>
        <div class="col">
            <div class="animal-card position-relative">
                <span class="badge bg-warning text-dark pending-badge">
                    <i class="bi bi-exclamation-circle"></i> Pending
                </span>
                <?php if (!empty($animal['image'])): ?>
                <div class="animal-image" style="background-image: url('assets/img/animals/<?= htmlspecialchars($animal['image']) ?>')"></div>
                <?php else: ?>
                <div class="animal-image">
                    <div class="animal-image-placeholder">
                        <i class="bi bi-camera" style="font-size: 2rem;"></i>
                    </div>
                </div>
                <?php endif; ?>
                <div class="animal-info">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="animal-name"><?= htmlspecialchars($animal['name']) ?></h5>
                        <span class="badge animal-type-badge bg-primary"><?= htmlspecialchars($animal['type']) ?></span>
                    </div>
                    
                    <div class="animal-details mb-2">
                        <?php if (!empty($animal['number'])): ?>
                        <div><strong>Number:</strong> <?= htmlspecialchars($animal['number']) ?></div>
                        <?php endif; ?>
                        <div><strong>Gender:</strong> <?= !empty($animal['gender']) ? htmlspecialchars($animal['gender']) : 'Not specified' ?></div>
                        <div><strong>Added:</strong> <?= $timeSince ?></div>
                    </div>
                    
                    <div class="d-grid mt-3">
                        <a href="animal_edit.php?id=<?= $animal['id'] ?>" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Complete Record
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>