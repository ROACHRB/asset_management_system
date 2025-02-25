<?php
// Start output buffering at the very beginning
ob_start();

include_once "../../includes/header.php";

// Check permission
if($_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "Access denied.";
    header("Location: index.php");
    exit;
}

// Check if user ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid user ID.";
    header("Location: index.php");
    exit;
}

$user_id = intval($_GET['id']);

// Check if user is trying to modify themselves
if($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot modify your own account status.";
    header("Location: index.php");
    exit;
}

// Get user details
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($user_result) == 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: index.php");
    exit;
}

$user = mysqli_fetch_assoc($user_result);

// If user is already active
if($user['status'] == 'active') {
    $_SESSION['warning'] = "This user is already active.";
    header("Location: index.php");
    exit;
}

// Activate the user
$update_query = "UPDATE users SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, "i", $user_id);

if(mysqli_stmt_execute($update_stmt)) {
    // Log the user activation
    $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                 VALUES (?, 'user_activated', ?, ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    $current_user_id = $_SESSION['user_id'];
    $description = "Activated user: " . $user['username'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    mysqli_stmt_bind_param($log_stmt, "iss", 
        $current_user_id,
        $description,
        $ip_address
    );
    mysqli_stmt_execute($log_stmt);
    
    $_SESSION['success'] = "User has been successfully Suspended.";
} else {
    $_SESSION['error'] = "Failed to activate user. Please try again.";
}

// Redirect back to users list
header("Location: index.php");
exit;

// End and flush the output buffer
ob_end_flush();
?>