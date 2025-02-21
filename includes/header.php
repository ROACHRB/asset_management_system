<?php
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
                <li class="nav-item">
                    <a class="nav-link" href="/asset_management_system/index.php">Dashboard</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="inventoryDropdown" role="button" data-toggle="dropdown">
                        Inventory
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/inventory/index.php">View All Assets</a>
                        <a class="dropdown-item" href="/asset_management_system/modules/inventory/add.php">Add New Asset</a>
                        <a class="dropdown-item" href="/asset_management_system/modules/tagging/index.php">Tagging Assets</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="receivingDropdown" role="button" data-toggle="dropdown">
                        Receiving
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/receiving/index.php">View Deliveries</a>
                        <a class="dropdown-item" href="/asset_management_system/modules/receiving/add_delivery.php">Record New Delivery</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="reportsDropdown" role="button" data-toggle="dropdown">
                        Reports
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/asset_management_system/modules/reports/index.php">Generate Reports</a>
                    </div>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle mr-1"></i><?php echo htmlspecialchars($_SESSION["username"]); ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="/asset_management_system/profile.php">My Profile</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/asset_management_system/logout.php">Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <!-- Page content will be here -->