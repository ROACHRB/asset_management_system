<?php
// Set secure cookie parameters before session starts
ini_set('session.cookie_httponly', 1); // Prevents JavaScript access to cookies
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1); // Only transmit cookies over HTTPS
}

// Initialize the session
session_start();

// Set security headers to prevent XSS
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://code.jquery.com; style-src 'self' https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:;");

// Check if the user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /asset_management_system/login.php");
    exit;
}

// Include database configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/asset_management_system/config/database.php';

// Include functions
require_once $_SERVER['DOCUMENT_ROOT'] . '/asset_management_system/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/asset_management_system/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="/asset_management_system/index.php">
            <i class="fas fa-boxes mr-2"></i>Asset Management
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <!-- Dashboard - accessible to all -->
                <li class="nav-item">
                    <a class="nav-link" href="/asset_management_system/index.php">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                </li>

                <!-- My Assets - Only shown for users with 'user' role -->
                <?php if($_SESSION['role'] == 'user'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="myAssetsDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-laptop mr-1"></i>My Assets
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/assignments/my_assets.php">
                            <i class="fas fa-list mr-2"></i>View My Assets
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Inventory Management - requires manage_assets permission -->
                <?php if(has_permission('manage_assets')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="inventoryDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-boxes mr-1"></i>Inventory
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/inventory/index.php">
                            <i class="fas fa-list mr-2"></i>View All Assets
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/inventory/add.php">
                            <i class="fas fa-plus mr-2"></i>Add New Asset
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/tagging/index.php">
                            <i class="fas fa-tags mr-2"></i>Asset Tagging
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Receiving - requires manage_assets permission -->
                <?php if(has_permission('manage_assets')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="receivingDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-truck-loading mr-1"></i>Receiving
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/receiving/index.php">
                            <i class="fas fa-list mr-2"></i>View Deliveries
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/receiving/add_delivery.php">
                            <i class="fas fa-plus mr-2"></i>Add New Delivery
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Asset Assignment - requires manage_assignments permission -->
                <?php if(has_permission('manage_assignments')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="assignmentDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-hand-holding mr-1"></i>Assignments
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/assignments/index.php">
                            <i class="fas fa-list mr-2"></i>View Assignments
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/assignments/assign.php">
                            <i class="fas fa-user-plus mr-2"></i>New Assignment
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/assignments/returns.php">
                            <i class="fas fa-undo mr-2"></i>Process Returns
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Storage Management - requires manage_locations permission -->
                <?php if(has_permission('manage_locations')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="storageDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-warehouse mr-1"></i>Storage
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/storage/index.php">
                            <i class="fas fa-list mr-2"></i>View Locations
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/storage/add.php">
                            <i class="fas fa-plus mr-2"></i>Add Location
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Disposal Management - viewing requires view_only, requesting requires manage_assets -->
                <?php if(has_permission('view_only')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="disposalDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-trash-alt mr-1"></i>Disposal
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/disposal/index.php">
                            <i class="fas fa-list mr-2"></i>View Disposals
                        </a>
                        <?php if(has_permission('manage_assets')): ?>
                        <a class="dropdown-item" href="/asset_management_system/modules/disposal/request.php">
                            <i class="fas fa-plus mr-2"></i>New Disposal Request
                        </a>
                        <?php endif; ?>
                        <?php if(has_permission('approve_disposals')): ?>
                        <a class="dropdown-item" href="/asset_management_system/modules/disposal/approve.php">
                            <i class="fas fa-check-circle mr-2"></i>Approve Requests
                        </a>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Reports - requires generate_reports permission -->
                <?php if(has_permission('generate_reports')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-chart-bar mr-1"></i>Reports
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/reports/index.php">
                            <i class="fas fa-file mr-2"></i>Generate Reports
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/reports/inventory_report.php">
                            <i class="fas fa-boxes mr-2"></i>Inventory Report
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/reports/assignment_report.php">
                            <i class="fas fa-users mr-2"></i>Assignment Report
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- Audits - requires conduct_audits permission -->
                <?php if(has_permission('conduct_audits')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="auditsDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-clipboard-check mr-1"></i>Audits
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/audits/index.php">
                            <i class="fas fa-list mr-2"></i>View Audits
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/audits/new.php">
                            <i class="fas fa-plus mr-2"></i>New Audit
                        </a>
                    </div>
                </li>
                <?php endif; ?>

                <!-- User Management - requires manage_users permission -->
                <?php if(has_permission('manage_users')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="usersDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-users-cog mr-1"></i>Users
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/users/index.php">
                            <i class="fas fa-users mr-2"></i>Manage Users
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/users/add.php">
                            <i class="fas fa-user-plus mr-2"></i>Add New User
                        </a>
                        <a class="dropdown-item" href="/asset_management_system/modules/users/roles.php">
                            <i class="fas fa-user-shield mr-2"></i>Manage Roles
                        </a>
                    </div>
                </li>
                <?php endif; ?>
            </ul>

            <!-- User Profile Dropdown - available to all users -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-1"></i>
                        <?php echo htmlspecialchars($_SESSION["username"], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="badge badge-<?php 
                            echo ($_SESSION['role'] == 'admin' ? 'danger' : 
                                ($_SESSION['role'] == 'manager' ? 'warning' : 
                                ($_SESSION['role'] == 'staff' ? 'info' : 'secondary'))); 
                        ?> ml-1">
                            <?php echo ucfirst(htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8')); ?>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="/asset_management_system/modules/users/profile.php">
                            <i class="fas fa-user mr-2"></i>My Profile
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/asset_management_system/logout.php">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <!-- Display error messages if any -->
        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>
        
        <!-- Display success messages if any -->
        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>
        
        <!-- Page content will be inserted here -->