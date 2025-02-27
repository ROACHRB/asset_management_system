<?php
// FILE PATH: asset_management_system/modules/disposal/complete.php
include_once "../../includes/header.php";

// Check if user has admin role
if ($_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Get disposal request ID
$disposal_id = $_GET['id'] ?? 0;

// Fetch disposal request details
$query = "SELECT d.*, a.asset_name, a.asset_tag, a.serial_number, a.model, a.manufacturer,
          c.category_name, a.status as asset_status, a.condition_status,
          u1.full_name as requested_by_name, u2.full_name as approved_by_name
          FROM disposal_requests d
          JOIN assets a ON d.asset_id = a.asset_id
          LEFT JOIN categories c ON a.category_id = c.category_id
          JOIN users u1 ON d.requested_by = u1.user_id
          JOIN users u2 ON d.approved_by = u2.user_id
          WHERE d.disposal_id = ? AND d.status = 'approved'";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $disposal_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-danger">Disposal request not found or not approved.</div>';
    include_once "../../includes/footer.php";
    exit;
}

$disposal = mysqli_fetch_assoc($result);

// Process completion form
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $disposal_method = trim($_POST["disposal_method"] ?? '');
    $completion_notes = trim($_POST["completion_notes"] ?? '');
    
    if (empty($disposal_method)) {
        $error = "Please select a disposal method.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update disposal request
            $update_sql = "UPDATE disposal_requests SET 
                          status = 'completed', 
                          completion_date = NOW(),
                          completion_notes = ?
                          WHERE disposal_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_sql);
            $combined_notes = "Disposal method: $disposal_method\n\n$completion_notes";
            mysqli_stmt_bind_param($stmt, "si", $combined_notes, $disposal_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update disposal request: " . mysqli_error($conn));
            }
            
            // Update asset status to disposed
            $update_asset = "UPDATE assets SET 
                            status = 'disposed', 
                            notes = CONCAT(IFNULL(notes, ''), '\n\nDisposed on " . date('Y-m-d') . "')
                            WHERE asset_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_asset);
            mysqli_stmt_bind_param($stmt, "i", $disposal['asset_id']);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update asset status: " . mysqli_error($conn));
            }
            
            // Add to asset history
            $history_sql = "INSERT INTO asset_history (
                           asset_id, action, action_date, performed_by, notes
                           ) VALUES (?, 'disposed', NOW(), ?, ?)";
            
            $stmt = mysqli_prepare($conn, $history_sql);
            $history_notes = "Asset has been disposed. Method: $disposal_method. $completion_notes";
            mysqli_stmt_bind_param($stmt, "iis", $disposal['asset_id'], $_SESSION["user_id"], $history_notes);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to add asset history: " . mysqli_error($conn));
            }
            
            // Log activity
            $activity_sql = "INSERT INTO user_activity_logs (
                           user_id, activity_type, description, ip_address, created_at
                           ) VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $activity_sql);
            $activity_type = 'complete_disposal';
            $activity_desc = "Completed disposal for asset " . $disposal['asset_tag'] . " using method: $disposal_method";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            mysqli_stmt_bind_param($stmt, "isss", $_SESSION["user_id"], $activity_type, $activity_desc, $ip_address);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to log activity: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Set success message and redirect
            $_SESSION['success_message'] = "Asset has been marked as disposed successfully.";
            header("Location: view.php?id=$disposal_id");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-trash-alt mr-2"></i>Complete Disposal</h1>
        <p class="text-muted">Mark asset as disposed and complete the disposal process</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="view.php?id=<?php echo $disposal_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Details
        </a>
    </div>
</div>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-1"></i> 
    <strong>Warning:</strong> This action is final and cannot be undone. The asset will be marked as disposed and removed from inventory.
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
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-trash-alt mr-1"></i>Disposal Completion
            </div>
            <div class="card-body">
                <form method="post" id="completionForm">
                    <div class="form-group">
                        <label for="disposal_method" class="required-field">Disposal Method</label>
                        <select class="form-control" id="disposal_method" name="disposal_method" required>
                            <option value="">-- Select Disposal Method --</option>
                            <option value="Recycled">Recycled</option>
                            <option value="Sold">Sold</option>
                            <option value="Donated">Donated</option>
                            <option value="Scrapped">Scrapped</option>
                            <option value="Returned to Vendor">Returned to Vendor</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="completion_notes">Additional Notes</label>
                        <textarea class="form-control" id="completion_notes" name="completion_notes" rows="4"></textarea>
                        <small class="form-text text-muted">
                            Include any relevant details such as recycling certificate number, sales amount, donation recipient, etc.
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to mark this asset as disposed? This action cannot be undone.');">
                        <i class="fas fa-trash-alt mr-1"></i>Complete Disposal
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Asset Information
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 35%;">Asset:</th>
                        <td>
                            <?php echo htmlspecialchars($disposal['asset_name']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($disposal['asset_tag']); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($disposal['category_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Serial Number:</th>
                        <td><?php echo htmlspecialchars($disposal['serial_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td><?php echo htmlspecialchars($disposal['model'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Current Status:</th>
                        <td>
                            <span class="badge badge-warning">
                                <?php echo ucfirst($disposal['asset_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Requested By:</th>
                        <td><?php echo htmlspecialchars($disposal['requested_by_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Request Date:</th>
                        <td><?php echo date('F d, Y', strtotime($disposal['request_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Approved By:</th>
                        <td><?php echo htmlspecialchars($disposal['approved_by_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Approval Date:</th>
                        <td><?php echo date('F d, Y', strtotime($disposal['approval_date'])); ?></td>
                    </tr>
                </table>
                
                <div class="mt-3">
                    <a href="../inventory/view.php?id=<?php echo $disposal['asset_id']; ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-box mr-1"></i>View Asset Details
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    $("#completionForm").validate({
        rules: {
            disposal_method: "required"
        },
        messages: {
            disposal_method: "Please select a disposal method"
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