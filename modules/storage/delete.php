<?php
// File: modules/storage/delete.php
// Start output buffering to prevent header issues
ob_start();

include_once "../../includes/header.php";

// Check permission
if(!has_permission('manage_locations')) {
    $_SESSION['error'] = "Access denied. You don't have permission to delete storage locations.";
    header("Location: index.php");
    exit;
}

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid location ID.";
    header("Location: index.php");
    exit;
}

$location_id = $_GET['id'];

// Check if location exists
$check_query = "SELECT * FROM locations WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$location_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($location_result) == 0) {
    $_SESSION['error'] = "Location not found.";
    header("Location: index.php");
    exit;
}

// Check if there are assets assigned to this location
$assets_query = "SELECT COUNT(*) as asset_count FROM assets WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $assets_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$assets_result = mysqli_stmt_get_result($stmt);
$assets = mysqli_fetch_assoc($assets_result);

if($assets['asset_count'] > 0) {
    $_SESSION['error'] = "Cannot delete location with existing assets. Please move assets or deactivate the location instead.";
    header("Location: index.php");
    exit;
}

// Process form submission (confirmation)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Delete location departments (will be cascaded automatically if foreign key constraints are set up)
        $delete_depts = "DELETE FROM location_departments WHERE location_id = ?";
        $stmt = mysqli_prepare($conn, $delete_depts);
        mysqli_stmt_bind_param($stmt, "i", $location_id);
        
        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting location departments: " . mysqli_error($conn));
        }
        
        // Delete the location
        $delete_location = "DELETE FROM locations WHERE location_id = ?";
        $stmt = mysqli_prepare($conn, $delete_location);
        mysqli_stmt_bind_param($stmt, "i", $location_id);
        
        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting location: " . mysqli_error($conn));
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Log activity if you have activity logging
        if(function_exists('log_activity')) {
            log_activity('delete_location', "Deleted location ID: $location_id");
        }
        
        // Set success message and redirect
        $_SESSION['success'] = "Location deleted successfully.";
        header("Location: index.php");
        exit;
        
    } catch(Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        
        $_SESSION['error'] = $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

// Get location details for confirmation
$location = mysqli_fetch_assoc($location_result);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-trash-alt mr-2"></i>Delete Location</h1>
        <p class="text-muted">Permanently remove this storage location</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        Confirmation Required
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <h4 class="alert-heading">Warning!</h4>
            <p>You are about to permanently delete the following location:</p>
            <ul>
                <li><strong>Building:</strong> <?php echo htmlspecialchars($location['building']); ?></li>
                <?php if(!empty($location['room'])): ?>
                <li><strong>Room:</strong> <?php echo htmlspecialchars($location['room']); ?></li>
                <?php endif; ?>
                <?php if(!empty($location['department'])): ?>
                <li><strong>Department:</strong> <?php echo htmlspecialchars($location['department']); ?></li>
                <?php endif; ?>
            </ul>
            <p>This action <strong>cannot be undone</strong>. Are you sure you want to proceed?</p>
        </div>
        
        <form method="post">
            <div class="form-group text-center mt-4">
                <a href="index.php" class="btn btn-secondary mr-2">
                    <i class="fas fa-times mr-1"></i>Cancel
                </a>
                <button type="submit" name="confirm_delete" class="btn btn-danger">
                    <i class="fas fa-trash-alt mr-1"></i>Yes, Delete Location
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
include_once "../../includes/footer.php"; 
// Flush output buffer before ending
ob_end_flush();
?>