<?php
// FILE PATH: asset_management_system/modules/disposal/approve.php
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
          u1.full_name as requested_by_name
          FROM disposal_requests d
          JOIN assets a ON d.asset_id = a.asset_id
          LEFT JOIN categories c ON a.category_id = c.category_id
          JOIN users u1 ON d.requested_by = u1.user_id
          WHERE d.disposal_id = ? AND d.status = 'pending'";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $disposal_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-danger">Disposal request not found or already processed.</div>';
    include_once "../../includes/footer.php";
    exit;
}

$disposal = mysqli_fetch_assoc($result);

// Process approval form
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $status = $_POST["status"] ?? '';
    $notes = trim($_POST["notes"] ?? '');
    
    if (empty($status) || !in_array($status, ['approved', 'rejected'])) {
        $error = "Please select a valid decision.";
    } elseif (empty($notes)) {
        $error = "Please provide notes about your decision.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update disposal request
            $update_sql = "UPDATE disposal_requests SET 
                          status = ?, 
                          approved_by = ?, 
                          approval_date = NOW(), 
                          approval_notes = ? 
                          WHERE disposal_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sisi", $status, $_SESSION["user_id"], $notes, $disposal_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update disposal request: " . mysqli_error($conn));
            }
            
            // If approved, update asset status to mark it as pending disposal
            if ($status == 'approved') {
                $update_asset = "UPDATE assets SET 
                                status = 'pending_disposal' 
                                WHERE asset_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_asset);
                mysqli_stmt_bind_param($stmt, "i", $disposal['asset_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update asset status: " . mysqli_error($conn));
                }
                
                // Add to asset history
                $history_sql = "INSERT INTO asset_history (
                               asset_id, action, action_date, performed_by, notes
                               ) VALUES (?, 'approved_disposal', NOW(), ?, ?)";
                
                $stmt = mysqli_prepare($conn, $history_sql);
                $history_notes = "Disposal request approved: " . $notes;
                mysqli_stmt_bind_param($stmt, "iis", $disposal['asset_id'], $_SESSION["user_id"], $history_notes);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to add asset history: " . mysqli_error($conn));
                }
            }
            
            // Log activity
            $activity_sql = "INSERT INTO user_activity_logs (
                           user_id, activity_type, description, ip_address, created_at
                           ) VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $activity_sql);
            $activity_type = $status == 'approved' ? 'approve_disposal' : 'reject_disposal';
            $activity_desc = ($status == 'approved' ? "Approved" : "Rejected") . " disposal request #$disposal_id for asset " . 
                           $disposal['asset_tag'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            mysqli_stmt_bind_param($stmt, "isss", $_SESSION["user_id"], $activity_type, $activity_desc, $ip_address);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to log activity: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Set success message and redirect
            $_SESSION['success_message'] = "Disposal request has been " . ($status == 'approved' ? "approved" : "rejected") . " successfully.";
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
        <h1><i class="fas fa-check mr-2"></i>Review Disposal Request</h1>
        <p class="text-muted">Approve or reject disposal request for an asset</p>
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
            <div class="card-header bg-primary text-white">
                <i class="fas fa-edit mr-1"></i>Review Decision
            </div>
            <div class="card-body">
                <form method="post" id="approvalForm">
                    <div class="form-group">
                        <label class="required-field">Decision</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="approve" value="approved">
                            <label class="form-check-label" for="approve">
                                <span class="text-success"><i class="fas fa-check-circle mr-1"></i>Approve Disposal</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="reject" value="rejected">
                            <label class="form-check-label" for="reject">
                                <span class="text-danger"><i class="fas fa-times-circle mr-1"></i>Reject Disposal</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="required-field">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" required></textarea>
                        <small class="form-text text-muted">
                            Please provide a detailed explanation for your decision.
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>Submit Decision
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
                        <th>Current Status:</th>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($disposal['asset_status'] == 'available' ? 'success' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($disposal['asset_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Condition:</th>
                        <td><?php echo ucfirst($disposal['condition_status']); ?></td>
                    </tr>
                    <tr>
                        <th>Request Date:</th>
                        <td><?php echo date('F d, Y', strtotime($disposal['request_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Requested By:</th>
                        <td><?php echo htmlspecialchars($disposal['requested_by_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Reason for Disposal:</th>
                        <td><?php echo nl2br(htmlspecialchars($disposal['reason'])); ?></td>
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
    $("#approvalForm").validate({
        rules: {
            status: "required",
            notes: {
                required: true,
                minlength: 10
            }
        },
        messages: {
            status: "Please select a decision",
            notes: {
                required: "Please provide notes for your decision",
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