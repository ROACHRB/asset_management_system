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

// Check if user is trying to delete themselves
if($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot delete your own account.";
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

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Check for dependencies in related tables
    $tables_to_check = [
        'assets' => 'created_by',
        'asset_assignments' => ['assigned_to', 'assigned_by'],
        'asset_history' => 'performed_by',
        'deliveries' => 'received_by',
        'disposal_requests' => ['requested_by', 'approved_by'],
        'physical_audits' => 'auditor_id',
        'user_activity_logs' => 'user_id'
    ];
    
    $has_dependencies = false;
    $dependency_details = [];
    
    // Check each table for dependencies
    foreach($tables_to_check as $table => $columns) {
        if(!is_array($columns)) {
            $columns = [$columns];
        }
        
        foreach($columns as $column) {
            $check_query = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "i", $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $count_row = mysqli_fetch_assoc($check_result);
            $count = $count_row['count'];
            
            if($count > 0) {
                $has_dependencies = true;
                $dependency_details[] = "$count record(s) in $table ($column)";
            }
            
            mysqli_stmt_close($check_stmt);
        }
    }
    
    // If user has dependencies, suspend instead of delete
    if($has_dependencies) {
        // Soft delete (suspend the user)
        $update_query = "UPDATE users SET status = 'suspended', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "i", $user_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // Log the user suspension
        $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                     VALUES (?, 'user_suspended', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        $current_user_id = $_SESSION['user_id'];
        $description = "User suspended due to existing dependencies: " . implode(", ", $dependency_details);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        mysqli_stmt_bind_param($log_stmt, "iss", 
            $current_user_id,
            $description,
            $ip_address
        );
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
        
        mysqli_commit($conn);
        
        $_SESSION['warning'] = "User could not be fully deleted due to existing dependencies. The user has been suspended instead. Dependencies: " . implode(", ", $dependency_details);
    } 
    // If no dependencies, proceed with actual deletion
    else {
        // Delete the user
        $delete_query = "DELETE FROM users WHERE user_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
        
        // Log the user deletion
        $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                     VALUES (?, 'user_deleted', ?, ?)";
        $log_stmt = mysqli_prepare($conn, $log_query);
        $current_user_id = $_SESSION['user_id'];
        $description = "Deleted user: " . $user['username'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        mysqli_stmt_bind_param($log_stmt, "iss", 
            $current_user_id,
            $description,
            $ip_address
        );
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
        
        mysqli_commit($conn);
        
        $_SESSION['success'] = "User has been successfully deleted.";
    }
    
    // Redirect to user list
    header("Location: index.php");
    exit();
    
} catch(Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// End and flush the output buffer
ob_end_flush();
?>