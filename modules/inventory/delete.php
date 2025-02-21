<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\delete.php
// Include database config and functions
require_once "../../config/database.php";
require_once "../../includes/functions.php";

// Initialize the session
session_start();

// Check if the user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /asset_management_system/login.php");
    exit;
}

// Check if asset ID is provided
if(!isset($_GET['id']) || empty(trim($_GET['id']))) {
    // Redirect to the inventory page
    header("location: index.php");
    exit;
}

// Get asset ID from URL
$asset_id = trim($_GET['id']);

// Process deletion
if(isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
    // First check if the asset exists
    $check_sql = "SELECT asset_id, asset_name, asset_tag FROM assets WHERE asset_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $asset_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if(mysqli_num_rows($result) == 1) {
        $asset = mysqli_fetch_assoc($result);
        
        // Check if asset is assigned
        $assignment_check = "SELECT assignment_id FROM asset_assignments 
                            WHERE asset_id = ? AND assignment_status = 'assigned'";
        $assignment_stmt = mysqli_prepare($conn, $assignment_check);
        mysqli_stmt_bind_param($assignment_stmt, "i", $asset_id);
        mysqli_stmt_execute($assignment_stmt);
        $assignment_result = mysqli_stmt_get_result($assignment_stmt);
        
        if(mysqli_num_rows($assignment_result) > 0) {
            // Asset is currently assigned, redirect with error
            $_SESSION['error_message'] = "Cannot delete asset. It is currently assigned to a user.";
            header("location: view.php?id=" . $asset_id);
            exit;
        }
        
        // Delete related records in asset_history
        $history_delete = "DELETE FROM asset_history WHERE asset_id = ?";
        $history_stmt = mysqli_prepare($conn, $history_delete);
        mysqli_stmt_bind_param($history_stmt, "i", $asset_id);
        mysqli_stmt_execute($history_stmt);
        
        // Delete asset
        $delete_sql = "DELETE FROM assets WHERE asset_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $asset_id);
        
        if(mysqli_stmt_execute($delete_stmt)) {
            // Set success message
            $_SESSION['success_message'] = "Asset '" . htmlspecialchars($asset['asset_name']) . 
                                           "' (" . htmlspecialchars($asset['asset_tag']) . ") was successfully deleted.";
            // Redirect to inventory page
            header("location: index.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Error deleting asset. Please try again.";
            header("location: view.php?id=" . $asset_id);
            exit;
        }
    } else {
        // Asset not found
        $_SESSION['error_message'] = "Asset not found.";
        header("location: index.php");
        exit;
    }
} else {
    // Fetch asset details for confirmation
    $fetch_sql = "SELECT asset_name, asset_tag, status FROM assets WHERE asset_id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $asset_id);
    mysqli_stmt_execute($fetch_stmt);
    $result = mysqli_stmt_get_result($fetch_stmt);
    
    if(mysqli_num_rows($result) != 1) {
        // Asset not found
        $_SESSION['error_message'] = "Asset not found.";
        header("location: index.php");
        exit;
    }
    
    $asset = mysqli_fetch_assoc($result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Asset - Asset Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/asset_management_system/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4><i class="fas fa-exclamation-triangle mr-2"></i>Confirm Deletion</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <p>Are you sure you want to delete the following asset?</p>
                            <ul>
                                <li><strong>Asset Name:</strong> <?php echo htmlspecialchars($asset['asset_name']); ?></li>
                                <li><strong>Asset Tag:</strong> <?php echo htmlspecialchars($asset['asset_tag'] ?? 'Not Tagged'); ?></li>
                                <li><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $asset['status'])); ?></li>
                            </ul>
                            <p class="font-weight-bold text-danger">
                                This action cannot be undone. All history records associated with this asset will also be deleted.
                            </p>
                        </div>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $asset_id); ?>" method="post">
                            <input type="hidden" name="confirm_delete" value="yes">
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirm_checkbox" required>
                                    <label class="form-check-label" for="confirm_checkbox">
                                        I understand that this action cannot be undone
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <a href="view.php?id=<?php echo $asset_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left mr-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-danger" id="delete_button" disabled>
                                    <i class="fas fa-trash-alt mr-1"></i>Delete Asset Permanently
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Enable/disable delete button based on checkbox
            $('#confirm_checkbox').change(function() {
                if($(this).is(':checked')) {
                    $('#delete_button').prop('disabled', false);
                } else {
                    $('#delete_button').prop('disabled', true);
                }
            });
        });
    </script>
</body>
</html>