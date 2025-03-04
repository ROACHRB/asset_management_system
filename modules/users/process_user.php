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

// Get action type (delete or suspend)
$action = isset($_GET['action']) ? $_GET['action'] : 'delete';
if(!in_array($action, ['delete', 'suspend', 'activate'])) {
    $action = 'delete'; // Default to delete if invalid action
}

// Check if user is trying to modify themselves
if($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot {$action} your own account.";
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

// Handle specific actions
if($action == 'suspend') {
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
        
        $_SESSION['success'] = "User has been successfully suspended.";
        
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
}
else if($action == 'activate') {
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
        
        $_SESSION['success'] = "User has been successfully activated.";
    } else {
        $_SESSION['error'] = "Failed to activate user. Please try again.";
    }
    
    // Redirect back to users list
    header("Location: index.php");
    exit;
}
else if($action == 'delete') {
    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Force delete parameter - if this is set, attempt to delete regardless of dependencies
        $force_delete = isset($_GET['force']) && $_GET['force'] == 'true';
        
        // Check for dependencies in related tables (only if not force deleting)
        $has_dependencies = false;
        $dependency_details = [];
        
        if(!$force_delete) {
            $tables_to_check = [
                'assets' => 'created_by',
                'asset_assignments' => ['assigned_to', 'assigned_by'],
                'asset_history' => 'performed_by',
                'deliveries' => 'received_by',
                'disposal_requests' => ['requested_by', 'approved_by'],
                'physical_audits' => 'auditor_id',
                'user_activity_logs' => 'user_id'
            ];
            
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
        }
        
        // If user has dependencies and not force deleting, suspend instead
        if($has_dependencies && !$force_delete) {
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
            $_SESSION['show_force_delete'] = true;
            $_SESSION['force_delete_user_id'] = $user_id;
        } 
        // If no dependencies or force deleting, proceed with actual deletion
        else {
            // Handle all tables with foreign key constraints to user_id
            $tables_with_constraints = [
                'user_activity_logs' => ['user_id'],
                'asset_assignments' => ['assigned_to', 'assigned_by'],
                'asset_history' => ['performed_by'],
                'deliveries' => ['received_by'],
                'disposal_requests' => ['requested_by', 'approved_by'],
                'physical_audits' => ['auditor_id']
            ];
            
            // Delete related records from all constraint tables
            foreach($tables_with_constraints as $table => $columns) {
                foreach($columns as $column) {
                    $delete_related_query = "DELETE FROM $table WHERE $column = ?";
                    $delete_related_stmt = mysqli_prepare($conn, $delete_related_query);
                    mysqli_stmt_bind_param($delete_related_stmt, "i", $user_id);
                    mysqli_stmt_execute($delete_related_stmt);
                    mysqli_stmt_close($delete_related_stmt);
                }
            }
            
            // Finally delete the user
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
            $description = "Deleted user: " . $user['username'] . ($force_delete ? " (force delete)" : "");
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
}

// End and flush the output buffer
ob_end_flush();
?>