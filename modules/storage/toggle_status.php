<?php
// File: modules/storage/toggle_status.php
include_once "../../includes/header.php";

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: index.php");
    exit;
}

$location_id = $_GET['id'];
$status = ($_GET['status'] == 'active') ? 'active' : 'inactive';

// Update location status
$update_query = "UPDATE locations SET status = ? WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "si", $status, $location_id);

if(mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = "Location status updated successfully.";
} else {
    $_SESSION['error'] = "Error updating location status: " . mysqli_error($conn);
}

// Redirect back to locations list
header("Location: index.php");
exit;
?>