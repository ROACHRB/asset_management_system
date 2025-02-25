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

// Check if user ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid user ID.</div>';
    include_once "../../includes/footer.php";
    exit;
}

$user_id = intval($_GET['id']);

// Check if user is trying to delete themselves
if($user_id == $_SESSION['user_id']) {
    echo '<div class="alert alert-danger">You cannot delete your own account.</div>';
    echo '<p><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Back to User List</a></p>';
    include_once "../../includes/footer.php";
    exit;
}

// Get user details
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($user_result) == 0) {
    echo '<div class="alert alert-danger">User not found.</div>';
    include_once "../../includes/footer.php";
    exit;
}

$user = mysqli_fetch_assoc($user_result);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-times mr-2"></i>Delete User</h1>
        <p class="text-muted">Remove a user from the system</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <i class="fas fa-exclamation-triangle mr-1"></i>Confirm User Deletion
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle mr-2"></i>Warning: This action might be irreversible.
        </div>
        
        <p>You are about to delete the following user:</p>
        
        <table class="table table-bordered">
            <tr>
                <th style="width: 200px;">Username</th>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
            </tr>
            <tr>
                <th>Full Name</th>
                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
            </tr>
            <tr>
                <th>Role</th>
                <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
            </tr>
            <tr>
                <th>Department</th>
                <td><?php echo !empty($user['department']) ? htmlspecialchars($user['department']) : '<em>None</em>'; ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php if($user['status'] == 'active'): ?>
                        <span class="badge badge-success">Active</span>
                    <?php elseif($user['status'] == 'suspended'): ?>
                        <span class="badge badge-warning">Suspended</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <p><strong>What happens when you delete a user?</strong></p>
        <ul>
            <li>If the user has no related records, they will be completely removed from the system.</li>
            <li>If the user has related records (assets, assignments, etc.), their account will be suspended instead to maintain data integrity.</li>
        </ul>
        
        <div class="mt-4">
            <a href="process_delete.php?id=<?php echo $user_id; ?>" class="btn btn-danger">
                <i class="fas fa-trash mr-1"></i>Confirm Delete
            </a>
            <a href="index.php" class="btn btn-secondary ml-2">
                <i class="fas fa-times mr-1"></i>Cancel
            </a>
        </div>
    </div>
</div>

<?php 
include_once "../../includes/footer.php"; 
// End and flush the output buffer
ob_end_flush();
?>