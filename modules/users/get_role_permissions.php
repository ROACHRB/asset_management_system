<?php
require_once "../../config/database.php";

if(isset($_GET['role_id'])) {
    $role_id = intval($_GET['role_id']);
    
    $query = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $permissions = [];
    while($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_id'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($permissions);
}
?>