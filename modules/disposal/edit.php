<?php
// FILE PATH: asset_management_system/modules/disposal/edit.php
include_once "../../includes/header.php";

// Get disposal request ID
$disposal_id = $_GET['id'] ?? 0;

// Fetch disposal request details
$query = "SELECT d.*, a.asset_name, a.asset_tag, a.status as asset_status,
          u1.full_name as requested_by_name
          FROM disposal_requests d
          JOIN assets a ON d.asset_id = a.asset_id
          JOIN users u1 ON d.requested_by = u1.user_id
          WHERE d.disposal_id = ? AND d.status = 'pending'";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $disposal_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-danger">Disposal request not found or cannot be edited.</div>';
    include_once "../../includes/footer.php";
    exit;
}

$disposal = mysqli_fetch_assoc($result);

// Check if user has permission to edit this request
if ($_SESSION['role'] != 'admin' && $_SESSION['user_id'] != $disposal['requested_by']) {
    echo '<div class="alert alert-danger">You do not have permission to edit this request.</div>';
    include_once "../../includes/footer.php";
    exit;
}

// Process form submission
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reason = trim($_POST["reason"] ?? '');
    
    if (empty($reason)) {
        $error = "Please provide a reason for disposal.";
    } else {
        // Update disposal request
        $update_sql = "UPDATE disposal_requests SET 
                      reason = ?
                      WHERE disposal_id = ?";
        
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "si", $reason, $disposal_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Log activity
            $activity_sql = "INSERT INTO user_activity_logs (
                           user_id, activity_type, description, ip_address, created_at
                           ) VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $activity_sql);
            $activity_type = 'edit_disposal_request';
            $activity_desc = "Edited disposal request #$disposal_id for asset " . $disposal['asset_tag'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            mysqli_stmt_bind_param($stmt, "isss", $_SESSION["user_id"], $activity_type, $activity_desc, $ip_address);
            mysqli_stmt_execute($stmt);
            
            // Redirect to view page
            header("Location: view.php?id=$disposal_id&success=updated");
            exit;
        } else {
            $error = "Error updating disposal request: " . mysqli_error($conn);
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-edit mr-2"></i>Edit Disposal Request</h1>
        <p class="text-muted">Modify your disposal request</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="view.php?id=<?php echo $disposal_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Details
        </a>
    </div>
</div>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-edit mr-1"></i>Edit Request
            </div>
            <div class="card-body">
                <form method="post" id="editForm">
                    <div class="form-group">
                        <label for="asset_info">Asset</label>
                        <input type="text" class="form-control" id="asset_info" value="<?php echo htmlspecialchars($disposal['asset_name']) . ' (' . htmlspecialchars($disposal['asset_tag']) . ')'; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason" class="required-field">Reason for Disposal</label>
                        <textarea class="form-control" id="reason" name="reason" rows="6" required><?php echo htmlspecialchars($disposal['reason']); ?></textarea>
                        <small class="form-text text-muted">
                            Please provide a detailed explanation for why this asset needs to be disposed of.
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>Update Request
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Request Information
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 35%;">Request ID:</th>
                        <td><?php echo $disposal_id; ?></td>
                    </tr>
                    <tr>
                        <th>Request Date:</th>
                        <td><?php echo date('F d, Y', strtotime($disposal['request_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge badge-warning">Pending</span>
                        </td>
                    </tr>
                    <tr>
                        <th>Requested By:</th>
                        <td><?php echo htmlspecialchars($disposal['requested_by_name']); ?></td>
                    </tr>
                </table>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Note:</strong> You can only edit the reason for disposal. If you need to change the asset, please cancel this request and create a new one.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    $("#editForm").validate({
        rules: {
            reason: {
                required: true,
                minlength: 10
            }
        },
        messages: {
            reason: {
                required: "Please provide a reason for disposal",
                minlength: "Please provide a more detailed explanation"
            }
        },
        errorElement: "div",
        errorClass: "invalid-feedback",
        highlight: function(element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function(element) {
            $(element).removeClass("is-invalid");
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>