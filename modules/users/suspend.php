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
    $_SESSION['error'] = "You cannot suspend your own account.";
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

// If user is already suspended
if($user['status'] == 'suspended') {
    $_SESSION['warning'] = "This user is already suspended.";
    header("Location: index.php");
    exit;
}

// Suspend the user
$update_query = "UPDATE users SET status = 'suspended', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, "i", $user_id);

if(mysqli_stmt_execute($update_stmt)) {
    // Check if there are any active assignments for this user
    $assignment_query = "SELECT COUNT(*) as count FROM asset_assignments 
                        WHERE assigned_to = ? AND assignment_status = 'assigned'";
    $assignment_stmt = mysqli_prepare($conn, $assignment_query);
    mysqli_stmt_bind_param($assignment_stmt, "i", $user_id);
    mysqli_stmt_execute($assignment_stmt);
    $assignment_result = mysqli_stmt_get_result($assignment_stmt);
    $assignment_count = mysqli_fetch_assoc($assignment_result)['count'];
    
    // Log the user suspension
    $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                 VALUES (?, 'user_suspended', ?, ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    $current_user_id = $_SESSION['user_id'];
    $description = "Suspended user: " . $user['username'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    mysqli_stmt_bind_param($log_stmt, "iss", 
        $current_user_id,
        $description,
        $ip_address
    );
    mysqli_stmt_execute($log_stmt);
    
    $_SESSION['success'] = "User has been successfully Activated.";
    
    // Warn if user has active assignments
    if($assignment_count > 0) {
        $_SESSION['warning'] = "Note: This user still has $assignment_count active asset assignments.";
    }
} else {
    $_SESSION['error'] = "Failed to suspend user. Please try again.";
}

// Redirect back to users list
header("Location: index.php");
exit;

// End and flush the output buffer
ob_end_flush();
?>