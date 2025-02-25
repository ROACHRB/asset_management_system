<?php
// File: modules/users/delete_role.php

// Include header and functions
include_once "../../config/database.php";
include_once "../../includes/functions.php";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check permission
if (!has_permission('manage_users')) {
    // Set error message
    $_SESSION['error'] = "Access denied. You don't have permission to delete roles.";
    
    // Redirect to roles page
    header("Location: roles.php");
    exit;
}

// Check if ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $role_id = (int)$_GET['id'];
    
    // Check if role exists
    $check_role = "SELECT role_name FROM roles WHERE role_id = ?";
    $stmt = mysqli_prepare($conn, $check_role);
    mysqli_stmt_bind_param($stmt, "i", $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($role = mysqli_fetch_assoc($result)) {
        $role_name = $role['role_name'];
        
        // Don't allow deletion of admin role
        if (strtolower($role_name) === 'admin') {
            $_SESSION['error'] = "Cannot delete the admin role as it is a system role.";
            header("Location: roles.php");
            exit;
        }
        
        // Check if role has users assigned
        $check_users = "SELECT COUNT(*) as user_count FROM users WHERE role = ?";
        $stmt = mysqli_prepare($conn, $check_users);
        mysqli_stmt_bind_param($stmt, "s", $role_name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if ($row['user_count'] > 0) {
            $_SESSION['error'] = "Cannot delete role '{$role_name}' because there are users assigned to it. Reassign users first.";
            header("Location: roles.php");
            exit;
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Delete role permissions first (foreign key relationship)
            $delete_permissions = "DELETE FROM role_permissions WHERE role_id = ?";
            $stmt = mysqli_prepare($conn, $delete_permissions);
            mysqli_stmt_bind_param($stmt, "i", $role_id);
            $result = mysqli_stmt_execute($stmt);
            
            if (!$result) {
                throw new Exception("Error deleting role permissions: " . mysqli_error($conn));
            }
            
            // Delete the role
            $delete_role = "DELETE FROM roles WHERE role_id = ?";
            $stmt = mysqli_prepare($conn, $delete_role);
            mysqli_stmt_bind_param($stmt, "i", $role_id);
            $result = mysqli_stmt_execute($stmt);
            
            if (!$result) {
                throw new Exception("Error deleting role: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Log activity
            log_activity('delete_role', "Deleted role: {$role_name}");
            
            // Set success message
            $_SESSION['success'] = "Role '{$role_name}' was successfully deleted.";
            
        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            
            // Set error message
            $_SESSION['error'] = $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Role not found.";
    }
} else {
    $_SESSION['error'] = "Invalid role ID.";
}

// Redirect back to roles page
header("Location: roles.php");
exit;
?>