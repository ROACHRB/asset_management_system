<?php
// FILE PATH: asset_management_system/modules/disposal/approve.php

// We need to output buffering to fix the "headers already sent" issue
ob_start();

include_once "../../includes/header.php";

// Check if user has admin role
if ($_SESSION['role'] != 'admin') {
    $_SESSION['error'] = "You don't have permission to access this page.";
    echo '<script>window.location.href = "index.php";</script>';
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

// Flag to check if we have a specific disposal request to approve
$has_disposal_request = mysqli_num_rows($result) > 0;

// If we have a specific request, get its details
if ($has_disposal_request) {
    $disposal = mysqli_fetch_assoc($result);
}

// Process approval form
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && $has_disposal_request) {
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
            
            // Set success message and redirect using JavaScript instead of header()
            $_SESSION['success_message'] = "Disposal request has been " . ($status == 'approved' ? "approved" : "rejected") . " successfully.";
            echo '<script>window.location.href = "view.php?id=' . $disposal_id . '";</script>';
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Fetch all successfully disposed assets (if no specific disposal request was found)
$disposed_assets = [];
if (!$has_disposal_request) {
    $disposed_query = "SELECT d.disposal_id, d.status, d.request_date, d.approval_date, d.completion_date,
                        a.asset_id, a.asset_tag, a.asset_name, a.serial_number, a.model,
                        c.category_name, 
                        u1.full_name as requested_by,
                        u2.full_name as approved_by,
                        COALESCE(a.purchase_cost, 0) as purchase_cost
                      FROM disposal_requests d
                      JOIN assets a ON d.asset_id = a.asset_id
                      LEFT JOIN categories c ON a.category_id = c.category_id
                      LEFT JOIN users u1 ON d.requested_by = u1.user_id
                      LEFT JOIN users u2 ON d.approved_by = u2.user_id
                      WHERE d.status IN ('completed', 'approved')
                      ORDER BY 
                        CASE 
                          WHEN d.status = 'approved' THEN 1 
                          WHEN d.status = 'completed' THEN 2
                        END,
                        d.completion_date DESC, 
                        d.approval_date DESC
                      LIMIT 15";
    
    $disposed_result = mysqli_query($conn, $disposed_query);
    if ($disposed_result) {
        while ($row = mysqli_fetch_assoc($disposed_result)) {
            $disposed_assets[] = $row;
        }
    }
}
?>

<?php if ($has_disposal_request): ?>
<!-- Regular Approval View -->
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

<?php else: ?>
<!-- Disposed Assets Summary View -->
<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-trash-alt mr-2"></i>Disposed Assets Summary</h1>
        <p class="text-muted">Recent asset disposal requests that have been approved or completed</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-list mr-2"></i>All Disposal Requests
        </a>
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle mr-2"></i>
    The disposal request you're looking for (#<?php echo $disposal_id; ?>) was not found or has already been processed.
    Below is a summary of recent approved and completed disposal requests.
</div>

<!-- Recently Disposed Assets Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Recently Approved/Disposed Assets</h6>
    </div>
    <div class="card-body">
        <?php if (empty($disposed_assets)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-1"></i> No approved or completed disposal requests found.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered" id="disposedAssetsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Original Value</th>
                            <th>Status</th>
                            <th>Request Date</th>
                            <th>Requested By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disposed_assets as $asset): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($asset['asset_name']); ?>
                                    <?php if (!empty($asset['serial_number'])): ?>
                                        <br><small class="text-muted">SN: <?php echo htmlspecialchars($asset['serial_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($asset['category_name'] ?? 'N/A'); ?></td>
                                <td><?php echo format_currency($asset['purchase_cost']); ?></td>
                                <td>
                                    <?php if ($asset['status'] == 'approved'): ?>
                                        <span class="badge badge-warning">Pending Disposal</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Disposed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($asset['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($asset['requested_by']); ?></td>
                                <td>
                                    <a href="view.php?id=<?php echo $asset['disposal_id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($asset['status'] == 'approved'): ?>
                                    <a href="complete.php?id=<?php echo $asset['disposal_id']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-check"></i> Complete
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#disposedAssetsTable').DataTable({
        order: [[5, 'desc']] // Sort by request date by default
    });
});
</script>
<?php endif; ?>

<?php if ($has_disposal_request): ?>
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
<?php endif; ?>

<?php 
include_once "../../includes/footer.php"; 
// Flush the output buffer
ob_end_flush();
?>