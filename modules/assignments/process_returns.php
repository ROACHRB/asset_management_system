<?php
// FILE: D:\xampp\htdocs\asset_management_system\modules\assignments\process_returns.php
// Description: Admin page to view and process asset return requests

// Add output buffering to avoid "headers already sent" errors
ob_start();

// Include header
include_once "../../includes/header.php";

// Check if user has admin privileges
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !has_permission('manage_assignments')){
    $_SESSION['error'] = "You don't have permission to access this page.";
    echo '<script>window.location.href = "../../index.php";</script>';
    exit;
}

// Initialize variables
$error = $success = "";
$return_requests = [];

// Get specific request ID if viewing a specific request
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If a specific request is being processed
if ($request_id > 0 && $_SERVER["REQUEST_METHOD"] == "POST") {
    $status = trim($_POST["status"] ?? '');
    $admin_notes = trim($_POST["admin_notes"] ?? '');
    
    if (empty($status) || !in_array($status, ['approved', 'rejected', 'completed'])) {
        $error = "Please select a valid status.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Get request details
            $request_query = "SELECT r.*, a.asset_tag, a.asset_name, a.status as asset_status,
                                    aa.assignment_status
                              FROM asset_return_requests r
                              JOIN assets a ON r.asset_id = a.asset_id
                              JOIN asset_assignments aa ON r.assignment_id = aa.assignment_id
                              WHERE r.request_id = ?";
            $stmt = mysqli_prepare($conn, $request_query);
            mysqli_stmt_bind_param($stmt, "i", $request_id);
            mysqli_stmt_execute($stmt);
            $request_result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($request_result) == 0) {
                throw new Exception("Return request not found.");
            }
            
            $request = mysqli_fetch_assoc($request_result);
            
            // Update request status
            $update_sql = "UPDATE asset_return_requests SET 
                           status = ?, 
                           processed_by = ?, 
                           processed_date = NOW(), 
                           admin_notes = ?
                           WHERE request_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sisi", $status, $_SESSION["user_id"], $admin_notes, $request_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update return request: " . mysqli_error($conn));
            }
            
            // If completing the return, update asset status and assignment
            if ($status == 'completed') {
                // Update asset status
                $update_asset = "UPDATE assets SET 
                               status = 'available'
                               WHERE asset_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_asset);
                mysqli_stmt_bind_param($stmt, "i", $request['asset_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update asset status: " . mysqli_error($conn));
                }
                
                // Update assignment status
                $update_assignment = "UPDATE asset_assignments SET 
                                    assignment_status = 'returned',
                                    actual_return_date = NOW()
                                    WHERE assignment_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_assignment);
                mysqli_stmt_bind_param($stmt, "i", $request['assignment_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update assignment: " . mysqli_error($conn));
                }
                
                // Add to asset history
                $history_sql = "INSERT INTO asset_history (
                              asset_id, action, action_date, performed_by, notes
                              ) VALUES (?, 'returned', NOW(), ?, ?)";
                
                $stmt = mysqli_prepare($conn, $history_sql);
                $history_notes = "Asset returned by user. Condition: {$request['return_condition']}. Admin notes: $admin_notes";
                mysqli_stmt_bind_param($stmt, "iis", $request['asset_id'], $_SESSION["user_id"], $history_notes);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to add asset history: " . mysqli_error($conn));
                }
            }
            
            // Log activity
            $activity_sql = "INSERT INTO user_activity_logs (
                           user_id, activity_type, description, ip_address, created_at
                           ) VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $activity_sql);
            $activity_type = 'process_return_request';
            $activity_desc = ucfirst($status) . " return request for asset " . $request['asset_tag'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            mysqli_stmt_bind_param($stmt, "isss", $_SESSION["user_id"], $activity_type, $activity_desc, $ip_address);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to log activity: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Set success message
            $success = "Return request has been " . ($status == 'completed' ? "completed" : $status) . " successfully.";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Fetch all return requests
$all_requests_query = "SELECT r.*, 
                        a.asset_tag, a.asset_name, a.model, c.category_name,
                        u1.full_name as requested_by_name,
                        u2.full_name as processed_by_name
                      FROM asset_return_requests r
                      JOIN assets a ON r.asset_id = a.asset_id
                      LEFT JOIN categories c ON a.category_id = c.category_id
                      JOIN users u1 ON r.requested_by = u1.user_id
                      LEFT JOIN users u2 ON r.processed_by = u2.user_id
                      ORDER BY 
                        CASE r.status
                          WHEN 'pending' THEN 1
                          WHEN 'approved' THEN 2
                          WHEN 'rejected' THEN 3
                          WHEN 'completed' THEN 4
                        END,
                        r.request_date DESC";

$all_requests_result = mysqli_query($conn, $all_requests_query);

if ($all_requests_result) {
    while ($row = mysqli_fetch_assoc($all_requests_result)) {
        $return_requests[] = $row;
    }
}

// If viewing a specific request
$specific_request = null;
if ($request_id > 0) {
    $specific_query = "SELECT r.*, 
                        a.asset_tag, a.asset_name, a.model, a.serial_number, a.status as asset_status, c.category_name,
                        u1.full_name as requested_by_name, u1.email as requested_by_email,
                        u2.full_name as processed_by_name,
                        aa.assignment_date, aa.expected_return_date, aa.assignment_status
                      FROM asset_return_requests r
                      JOIN assets a ON r.asset_id = a.asset_id
                      LEFT JOIN categories c ON a.category_id = c.category_id
                      JOIN users u1 ON r.requested_by = u1.user_id
                      LEFT JOIN users u2 ON r.processed_by = u2.user_id
                      JOIN asset_assignments aa ON r.assignment_id = aa.assignment_id
                      WHERE r.request_id = ?";
                      
    $stmt = mysqli_prepare($conn, $specific_query);
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $specific_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($specific_result) > 0) {
        $specific_request = mysqli_fetch_assoc($specific_result);
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-undo mr-2"></i>Process Asset Returns</h1>
        <p class="text-muted">Review and process asset return requests from users</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Assignments
        </a>
    </div>
</div>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if($specific_request): ?>
<!-- Single Request View -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-clipboard-check mr-1"></i>Process Return Request
            </div>
            <div class="card-body">
                <?php if($specific_request['status'] == 'completed'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        <strong>This return has been completed</strong><br>
                        Processed by: <?php echo htmlspecialchars($specific_request['processed_by_name']); ?><br>
                        Date: <?php echo date('F d, Y, g:i A', strtotime($specific_request['processed_date'])); ?>
                    </div>
                    
                    <?php if(!empty($specific_request['admin_notes'])): ?>
                        <div class="form-group">
                            <label>Admin Notes:</label>
                            <div class="form-control bg-light" style="height: auto;">
                                <?php echo nl2br(htmlspecialchars($specific_request['admin_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <a href="process_returns.php" class="btn btn-primary">
                        <i class="fas fa-list mr-1"></i>View All Return Requests
                    </a>
                    
                <?php elseif($specific_request['status'] == 'rejected'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle mr-1"></i>
                        <strong>This return request has been rejected</strong><br>
                        Processed by: <?php echo htmlspecialchars($specific_request['processed_by_name']); ?><br>
                        Date: <?php echo date('F d, Y, g:i A', strtotime($specific_request['processed_date'])); ?>
                    </div>
                    
                    <?php if(!empty($specific_request['admin_notes'])): ?>
                        <div class="form-group">
                            <label>Admin Notes:</label>
                            <div class="form-control bg-light" style="height: auto;">
                                <?php echo nl2br(htmlspecialchars($specific_request['admin_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <a href="process_returns.php" class="btn btn-primary">
                        <i class="fas fa-list mr-1"></i>View All Return Requests
                    </a>
                    
                <?php else: ?>
                    <form method="post" id="processForm">
                        <div class="form-group">
                            <label class="required-field">Action</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="approve" value="approved" 
                                       <?php echo ($specific_request['status'] == 'approved') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="approve">
                                    <span class="text-primary"><i class="fas fa-check-circle mr-1"></i>Approve Request</span>
                                </label>
                                <small class="form-text text-muted ml-4">
                                    Notify user to physically return the asset.
                                </small>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="status" id="complete" value="completed">
                                <label class="form-check-label" for="complete">
                                    <span class="text-success"><i class="fas fa-check-double mr-1"></i>Complete Return</span>
                                </label>
                                <small class="form-text text-muted ml-4">
                                    Mark asset as returned and available for reassignment.
                                </small>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="status" id="reject" value="rejected">
                                <label class="form-check-label" for="reject">
                                    <span class="text-danger"><i class="fas fa-times-circle mr-1"></i>Reject Request</span>
                                </label>
                                <small class="form-text text-muted ml-4">
                                    Reject the return request and provide a reason.
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_notes">Notes</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="4" 
                                      placeholder="Provide additional information or instructions for the user."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i>Submit
                        </button>
                        <a href="process_returns.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Return Request Details
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 35%;">Request ID:</th>
                        <td><?php echo $specific_request['request_id']; ?></td>
                    </tr>
                    <tr>
                        <th>Asset:</th>
                        <td>
                            <?php echo htmlspecialchars($specific_request['asset_name']); ?>
                            <br>
                            <span class="badge badge-info"><?php echo htmlspecialchars($specific_request['asset_tag']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($specific_request['category_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Model / Serial:</th>
                        <td>
                            <?php echo htmlspecialchars($specific_request['model'] ?? 'N/A'); ?>
                            <?php if(!empty($specific_request['serial_number'])): ?>
                                <br><small class="text-muted">SN: <?php echo htmlspecialchars($specific_request['serial_number']); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Request Date:</th>
                        <td><?php echo date('F d, Y, g:i A', strtotime($specific_request['request_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge badge-<?php
                                echo ($specific_request['status'] == 'pending' ? 'warning' : 
                                     ($specific_request['status'] == 'approved' ? 'primary' : 
                                     ($specific_request['status'] == 'completed' ? 'success' : 'danger')));
                            ?>">
                                <?php echo ucfirst($specific_request['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Requested By:</th>
                        <td>
                            <?php echo htmlspecialchars($specific_request['requested_by_name']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($specific_request['requested_by_email']); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <th>Asset Condition:</th>
                        <td>
                            <span class="badge badge-<?php
                                echo ($specific_request['return_condition'] == 'excellent' ? 'success' : 
                                     ($specific_request['return_condition'] == 'good' ? 'primary' : 
                                     ($specific_request['return_condition'] == 'fair' ? 'info' : 
                                     ($specific_request['return_condition'] == 'damaged' ? 'warning' : 'danger'))));
                            ?>">
                                <?php echo ucfirst($specific_request['return_condition']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>User Notes:</th>
                        <td>
                            <?php if(!empty($specific_request['notes'])): ?>
                                <?php echo nl2br(htmlspecialchars($specific_request['notes'])); ?>
                            <?php else: ?>
                                <span class="text-muted">No notes provided</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <div class="mt-3">
                    <a href="../inventory/view.php?id=<?php echo $specific_request['asset_id']; ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-box mr-1"></i>View Asset Details
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-history mr-1"></i>Assignment Details
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 35%;">Assignment Date:</th>
                        <td><?php echo date('F d, Y', strtotime($specific_request['assignment_date'])); ?></td>
                    </tr>
                    <?php if(!empty($specific_request['expected_return_date'])): ?>
                    <tr>
                        <th>Expected Return:</th>
                        <td>
                            <?php 
                                $return_date = new DateTime($specific_request['expected_return_date']);
                                $today = new DateTime();
                                $is_overdue = $return_date < $today;
                            ?>
                            <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                <?php echo date('F d, Y', strtotime($specific_request['expected_return_date'])); ?>
                                <?php if($is_overdue): ?>
                                    <span class="badge badge-danger">Overdue</span>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th>Assignment Type:</th>
                        <td><span class="badge badge-success">Permanent</span></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Assignment Status:</th>
                        <td>
                            <span class="badge badge-<?php
                                echo ($specific_request['assignment_status'] == 'assigned' ? 'primary' : 
                                     ($specific_request['assignment_status'] == 'returned' ? 'success' : 'secondary'));
                            ?>">
                                <?php echo ucfirst($specific_request['assignment_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Asset Status:</th>
                        <td>
                            <span class="badge badge-<?php
                                echo ($specific_request['asset_status'] == 'available' ? 'success' : 
                                     ($specific_request['asset_status'] == 'assigned' ? 'primary' : 'warning'));
                            ?>">
                                <?php echo ucfirst($specific_request['asset_status']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Return Requests List View -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Asset Return Requests</h6>
    </div>
    <div class="card-body">
        <?php if (empty($return_requests)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i> There are no asset return requests at this time.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered" id="returnRequestsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Asset</th>
                            <th>Requested By</th>
                            <th>Request Date</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($return_requests as $request): ?>
                            <tr>
                                <td><?php echo $request['request_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['asset_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($request['asset_tag']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php
                                        echo ($request['return_condition'] == 'excellent' ? 'success' : 
                                             ($request['return_condition'] == 'good' ? 'primary' : 
                                             ($request['return_condition'] == 'fair' ? 'info' : 
                                             ($request['return_condition'] == 'damaged' ? 'warning' : 'danger'))));
                                    ?>">
                                        <?php echo ucfirst($request['return_condition']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php
                                        echo ($request['status'] == 'pending' ? 'warning' : 
                                             ($request['status'] == 'approved' ? 'primary' : 
                                             ($request['status'] == 'completed' ? 'success' : 'danger')));
                                    ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                    <?php if ($request['status'] != 'pending'): ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($request['processed_by_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="process_returns.php?id=<?php echo $request['request_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($request['status'] == 'pending' || $request['status'] == 'approved'): ?>
                                    <a href="process_returns.php?id=<?php echo $request['request_id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Process
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
<?php endif; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#returnRequestsTable').DataTable({
        order: [[3, 'desc']], // Sort by request date by default
        columnDefs: [
            { orderable: false, targets: [6] } // Disable sorting on actions column
        ]
    });
    
    // Form validation for process form
    $("#processForm").validate({
        rules: {
            status: "required"
        },
        messages: {
            status: "Please select an action"
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

<?php
// Include footer
include_once "../../includes/footer.php";
// Flush output buffer
ob_end_flush();
?>