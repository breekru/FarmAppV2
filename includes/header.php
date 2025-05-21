<?php
/**
 * Common Header File
 * Includes common HTML head elements, Bootstrap 5, and starts the page structure
 */

// Initialize the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'FarmApp' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="manifest" href="assets/site.webmanifest">
</head>
<body>
    <!-- Main container -->
    <div class="container-fluid p-0">
        <!-- Navbar included here -->
        <?php include_once 'includes/navbar.php'; ?>
        
        <!-- Page content container -->
        <main class="container py-4">
            <?php if (isset($page_header)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="text-center"><?= $page_header ?></h1>
                    <?php if (isset($page_subheader)): ?>
                    <p class="text-center text-muted"><?= $page_subheader ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Alert messages display -->
            <?php if (isset($_SESSION['alert_message'])): ?>
            <div class="alert alert-<?= $_SESSION['alert_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['alert_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php 
                // Clear the message after displaying it
                unset($_SESSION['alert_message']);
                unset($_SESSION['alert_type']);
            endif; 
            ?>