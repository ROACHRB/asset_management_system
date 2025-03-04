<?php
// Start output buffering at the very beginning
ob_start();

include_once "../../includes/header.php";

// Check permission
if($_SESSION['role'] != 'admin') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    include_once "../../includes/footer.php";
    exit;
}

// Process approval/rejection
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action']; // 'approve' or 'reject'
    
    // Validate the user exists and is pending
    $check_query = "SELECT * FROM users WHERE user_id = ? AND status = 'pending'";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if(mysqli_num_rows($check_result) > 0) {
        $user = mysqli_fetch_assoc($check_result);
        
        if($action == 'approve') {
            // Approve the user - set status to active
            $update_query = "UPDATE users SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "i", $user_id);
            
            if(mysqli_stmt_execute($update_stmt)) {
                // Log the approval action
                $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                             VALUES (?, 'user_approved', ?, ?)";
                $log_stmt = mysqli_prepare($conn, $log_query);
                $current_user_id = $_SESSION['user_id'];
                $description = "Approved registration for: " . $user['username'];
                $ip_address = $_SERVER['REMOTE_ADDR'];
                
                mysqli_stmt_bind_param($log_stmt, "iss", 
                    $current_user_id,
                    $description,
                    $ip_address
                );
                mysqli_stmt_execute($log_stmt);
                
                $_SESSION['success'] = "User account has been approved successfully.";
            } else {
                $_SESSION['error'] = "Failed to approve user account.";
            }
        }
        
    } else {
        $_SESSION['error'] = "Invalid user or user is not in pending status.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: pending_approvals.php");
    exit;
}

// Display success/warning/error messages
if(isset($_SESSION['success']) && !empty($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

if(isset($_SESSION['warning']) && !empty($_SESSION['warning'])) {
    echo '<div class="alert alert-warning">' . $_SESSION['warning'] . '</div>';
    unset($_SESSION['warning']);
}

if(isset($_SESSION['error']) && !empty($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

// Get pending users
$query = "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
$pending_count = mysqli_num_rows($result);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-clock mr-2"></i>Pending Approvals</h1>
        <p class="text-muted">Review and approve new user registrations</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Users
        </a>
    </div>
</div>

<?php if($pending_count == 0): ?>
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
            <h4>No Pending Approvals</h4>
            <p class="text-muted">There are no user registrations waiting for approval.</p>
        </div>
    </div>
</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-user-plus mr-1"></i>Pending User Registrations (<?php echo $pending_count; ?>)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo !empty($row['department']) ? htmlspecialchars($row['department']) : '<em>None</em>'; ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-success approve-btn" 
                                        data-id="<?php echo $row['user_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['full_name']); ?>"
                                        data-toggle="modal" data-target="#approveModal">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </button>
                                <button class="btn btn-sm btn-danger reject-btn"
                                        data-id="<?php echo $row['user_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['full_name']); ?>"
                                        data-toggle="modal" data-target="#rejectModal">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveModalLabel">Approve User Registration</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <p>Are you sure you want to approve the registration for <strong id="approveUserName"></strong>?</p>
                    <p>The user will be granted access to the system with the role of <strong>user</strong>.</p>
                    <input type="hidden" name="user_id" id="approveUserId">
                    <input type="hidden" name="action" value="approve">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectModalLabel">Reject User Registration</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <p>Are you sure you want to reject the registration for <strong id="rejectUserName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone. The user will need to register again if they want access.</p>
                    <input type="hidden" name="user_id" id="rejectUserId">
                    <input type="hidden" name="action" value="reject">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle approve button click
    $('.approve-btn').click(function() {
        let userId = $(this).data('id');
        let userName = $(this).data('name');
        $('#approveUserId').val(userId);
        $('#approveUserName').text(userName);
    });
    
    // Handle reject button click
    $('.reject-btn').click(function() {
        let userId = $(this).data('id');
        let userName = $(this).data('name');
        $('#rejectUserId').val(userId);
        $('#rejectUserName').text(userName);
    });
});
</script>
<?php endif; ?>

<?php 
include_once "../../includes/footer.php"; 
// End and flush the output buffer
ob_end_flush();
?>