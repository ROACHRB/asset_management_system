<?php
// Initialize the session
session_start();

// Include database connection
require_once "config/database.php";

// Log the logout action if user was logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Check if the user_activity_logs table exists before trying to insert
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'user_activity_logs'");
    if(mysqli_num_rows($check_table) > 0) {
        // Table exists, log the action
        $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, ip_address) 
                    VALUES (?, 'logout', ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        mysqli_stmt_bind_param($log_stmt, "is", $_SESSION["user_id"], $_SERVER['REMOTE_ADDR']);
        mysqli_stmt_execute($log_stmt);
    }
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to login page
header("location: login.php");
exit;
?>