<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'asset_management_system');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect to database. " . mysqli_connect_error());
}

// Set charset to ensure proper character encoding
mysqli_set_charset($conn, "utf8mb4");

// Function to sanitize user inputs
function sanitize_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Function to log system actions
function log_action($conn, $asset_id, $action, $user_id, $notes = '') {
    $query = "INSERT INTO asset_history (asset_id, action, performed_by, notes) 
              VALUES (?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isis", $asset_id, $action, $user_id, $notes);
    
    return mysqli_stmt_execute($stmt);
}