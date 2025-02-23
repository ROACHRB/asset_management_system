<?php
// FILE: D:\xampp\htdocs\asset_management_system\includes\header.php
// Initialize the session
session_start();

// Check if the user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /asset_management_system/login.php");
    exit;
}

// Include database configuration
require_once $_SERVER['DOCUMENT_ROOT'] . '/asset_management_system/config/database.php';
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
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link" href="/asset_management_system/index.php">
                        <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                    </a>
                </li>

                <!-- Inventory Management -->
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

                <!-- Receiving -->
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

                <!-- Asset Assignment -->
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

                <!-- Storage Management -->
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

                <!-- Disposal Management -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="disposalDropdown" role="button" data-toggle="dropdown">
        <i class="fas fa-trash-alt mr-1"></i>Disposal
    </a>
    <div class="dropdown-menu">
        <a class="dropdown-item" href="/asset_management_system/modules/disposal/index.php">
            <i class="fas fa-list mr-2"></i>View Disposals
        </a>
        <a class="dropdown-item" href="/asset_management_system/modules/disposal/request.php">
            <i class="fas fa-plus mr-2"></i>New Disposal Request
        </a>
        <?php if($_SESSION['role'] == 'admin'): ?>
        <a class="dropdown-item" href="/asset_management_system/modules/disposal/approve.php">
            <i class="fas fa-check-circle mr-2"></i>Approve Requests
        </a>
        <?php endif; ?>
    </div>
</li>

                <!-- Reports -->
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

                <!-- User Management (Admin Only) -->
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
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
                    </div>
                </li>
                <?php endif; ?>
            </ul>

            <!-- User Profile Dropdown -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-1"></i>
                        <?php echo htmlspecialchars($_SESSION["username"]); ?>
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
        <!-- Page content will be inserted here -->