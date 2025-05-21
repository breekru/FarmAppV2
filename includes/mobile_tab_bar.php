<?php
/**
 * Mobile Tab Bar Component
 * 
 * A mobile-optimized bottom navigation bar that appears only on small screens.
 * Place this file in the includes directory and include it at the end of each page
 * before the footer for a better mobile experience.
 */

// First, get the count of pending animals (if not already done in the navbar)
if (!isset($pendingCount)) {
    $pendingCount = 0;
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        try {
            $db = getDbConnection();
            $pendingStmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM animals 
                WHERE user_id = :user_id AND pending_completion = 'Yes'
            ");
            $pendingStmt->bindParam(':user_id', $_SESSION["username"], PDO::PARAM_STR);
            $pendingStmt->execute();
            $pendingCount = $pendingStmt->fetchColumn();
        } catch (Exception $e) {
            error_log('Error counting pending animals in mobile tab bar: ' . $e->getMessage());
        }
    }
}

// Determine current page for active tabs
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Bottom Tab Bar - Only shows on small screens -->
<div class="d-block d-md-none" style="height: 60px;"><!-- Spacer for fixed navbar --></div>

<nav class="mobile-tab-bar fixed-bottom d-block d-md-none">
    <div class="container">
        <div class="row g-0">
            <div class="col">
                <a href="index.php" class="tab-item <?= ($current_page == 'index.php' || $current_page == 'welcome.php') ? 'active' : '' ?>">
                    <i class="bi bi-house-door<?= ($current_page == 'index.php' || $current_page == 'welcome.php') ? '-fill' : '' ?>"></i>
                    <span>Home</span>
                </a>
            </div>
            <div class="col">
                <a href="animal_list.php" class="tab-item <?= ($current_page == 'animal_list.php') ? 'active' : '' ?>">
                    <i class="bi bi-list<?= ($current_page == 'animal_list.php') ? '-check' : '' ?>"></i>
                    <span>Animals</span>
                </a>
            </div>
            <div class="col">
                <a href="quick_add.php" class="tab-item tab-item-center <?= ($current_page == 'quick_add.php') ? 'active' : '' ?>">
                    <div class="tab-circle">
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <span>Add</span>
                </a>
            </div>
            <div class="col">
                <a href="pending_completion.php" class="tab-item <?= ($current_page == 'pending_completion.php') ? 'active' : '' ?>">
                    <?php if ($pendingCount > 0): ?>
                    <div class="badge-container">
                        <i class="bi bi-exclamation-circle<?= ($current_page == 'pending_completion.php') ? '-fill' : '' ?>"></i>
                        <span class="badge rounded-pill bg-danger position-absolute"><?= $pendingCount ?></span>
                    </div>
                    <?php else: ?>
                    <i class="bi bi-check-circle<?= ($current_page == 'pending_completion.php') ? '-fill' : '' ?>"></i>
                    <?php endif; ?>
                    <span>Pending</span>
                </a>
            </div>
            <div class="col">
                <a href="profile.php" class="tab-item <?= ($current_page == 'profile.php') ? 'active' : '' ?>">
                    <i class="bi bi-person<?= ($current_page == 'profile.php') ? '-fill' : '' ?>"></i>
                    <span>Profile</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
    .mobile-tab-bar {
        background-color: #ffffff;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        padding: 5px 0;
        z-index: 1030;
    }
    
    .tab-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        text-decoration: none;
        font-size: 0.7rem;
        padding: 8px 0;
        transition: color 0.2s;
    }
    
    .tab-item i {
        font-size: 1.3rem;
        margin-bottom: 2px;
    }
    
    .tab-item.active {
        color: #0d6efd;
    }
    
    .tab-item-center {
        margin-top: -20px;
    }
    
    .tab-circle {
        width: 50px;
        height: 50px;
        background-color: #0d6efd;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        box-shadow: 0 2px 10px rgba(13, 110, 253, 0.3);
    }
    
    .tab-circle i {
        font-size: 1.5rem;
        margin: 0;
    }
    
    .badge-container {
        position: relative;
    }
    
    .badge-container .badge {
        position: absolute;
        top: -8px;
        right: -8px;
        font-size: 0.6rem;
        padding: 0.25rem 0.4rem;
        min-width: 18px;
    }
</style>