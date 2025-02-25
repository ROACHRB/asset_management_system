<?php
// Include database connection
include_once "../../config/database.php";
include_once "../../includes/functions.php";

// Check if user is logged in and has permission
if(!isset($_SESSION['user_id']) || !has_permission('manage_users')) {
    // Return empty array if not authorized
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Check if role_id is provided
if(isset($_GET['role_id']) && is_numeric($_GET['role_id'])) {
    $role_id = intval($_GET['role_id']);
    
    // Get permissions for the role
    $permissions = [];
    
    $query = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_assoc($result)) {
        $permissions[] = (int)$row['permission_id'];
    }
    
    // Return permissions as JSON
    header('Content-Type: application/json');
    echo json_encode($permissions);
} else {
    // Return empty array if no role_id provided
    header('Content-Type: application/json');
    echo json_encode([]);
}