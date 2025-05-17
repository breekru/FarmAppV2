<?php
/**
 * Navigation Bar Component
 * Responsive Bootstrap 5 navbar with user menu and authentication controls
 */

// Check if user is logged in
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$username = $is_logged_in ? htmlspecialchars($_SESSION["username"]) : '';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/logo_small.png" alt="FarmApp" height="30" class="d-inline-block align-text-top me-2">
            FarmApp
        </a>
        
        <!-- Mobile toggle button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" 
                aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navbar content -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <?php if ($is_logged_in): ?>
            <!-- Navigation links (only shown when logged in) -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'index.php' || $current_page == 'welcome.php') ? 'active' : '' ?>" 
                       href="index.php">Home</a>
                </li>
                
                <!-- Animal Management Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= (strpos($current_page, 'animal') !== false) ? 'active' : '' ?>" 
                       href="#" id="animalDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        Animals
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="animalDropdown">
                        <li><a class="dropdown-item" href="animal_list.php">All Animals</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="animal_list.php?type=Sheep">Sheep</a></li>
                        <li><a class="dropdown-item" href="animal_list.php?type=Chicken">Chickens</a></li>
                        <li><a class="dropdown-item" href="animal_list.php?type=Turkey">Turkeys</a></li>
                        <li><a class="dropdown-item" href="animal_list.php?type=Pig">Pigs</a></li>
                        <li><a class="dropdown-item" href="animal_list.php?type=Cow">Cows</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="animal_add.php">Add New Animal</a></li>
                    </ul>
                </li>
                
                <!-- Reports Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= (strpos($current_page, 'report') !== false) ? 'active' : '' ?>" 
                       href="#" id="reportsDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        Reports
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="reportsDropdown">
                        <li><a class="dropdown-item" href="report_inventory.php">Inventory</a></li>
                        <li><a class="dropdown-item" href="report_lineage.php">Lineage</a></li>
                        <li><a class="dropdown-item" href="report_financial.php">Financial</a></li>
                    </ul>
                </li>
            </ul>
            
            <!-- Search Form -->
            <form class="d-flex my-2 my-lg-0 me-lg-3" action="search.php" method="get">
                <div class="input-group">
                    <input class="form-control" type="search" placeholder="Search animals" 
                           aria-label="Search" name="q">
                    <button class="btn btn-outline-light" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- User Menu (when logged in) -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" 
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i> <?= $username ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="bi bi-person me-2"></i> My Profile
                        </a></li>
                        <li><a class="dropdown-item" href="change_password.php">
                            <i class="bi bi-key me-2"></i> Change Password
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Log Out
                        </a></li>
                    </ul>
                </li>
            </ul>
            
            <?php else: ?>
            <!-- Login/Register links (when not logged in) -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'login.php') ? 'active' : '' ?>" 
                       href="login.php">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'register.php') ? 'active' : '' ?>" 
                       href="register.php">
                        <i class="bi bi-person-plus me-1"></i> Register
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>