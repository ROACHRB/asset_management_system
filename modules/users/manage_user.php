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

// Check for dependencies (to show appropriate warnings)
$tables_to_check = [
    'assets' => 'created_by',
    'asset_assignments' => ['assigned_to', 'assigned_by'],
    'asset_history' => 'performed_by',
    'deliveries' => 'received_by',
    'disposal_requests' => ['requested_by', 'approved_by'],
    'physical_audits' => 'auditor_id',
    'user_activity_logs' => 'user_id'
];

$has_dependencies = false;
$dependency_details = [];
$total_dependencies = 0;

// Check each table for dependencies
foreach($tables_to_check as $table => $columns) {
    if(!is_array($columns)) {
        $columns = [$columns];
    }
    
    foreach($columns as $column) {
        $check_query = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $count_row = mysqli_fetch_assoc($check_result);
        $count = $count_row['count'];
        
        if($count > 0) {
            $has_dependencies = true;
            $dependency_details[] = "$count record(s) in $table ($column)";
            $total_dependencies += $count;
        }
        
        mysqli_stmt_close($check_stmt);
    }
}

// Check if showing page as force delete confirmation
$force_delete = isset($_GET['force']) && $_GET['force'] == 'true';
$page_title = $force_delete ? "Force Delete User" : "Manage User";
$action_phrase = $force_delete ? "force delete" : "manage";
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-cog mr-2"></i><?php echo $page_title; ?></h1>
        <p class="text-muted"><?php echo $force_delete ? "Permanently remove a user and all dependencies" : "Manage user account status"; ?></p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header <?php echo $force_delete ? 'bg-danger' : 'bg-primary'; ?> text-white">
        <i class="fas <?php echo $force_delete ? 'fa-exclamation-triangle' : 'fa-user-edit'; ?> mr-1"></i>
        <?php echo $force_delete ? "Confirm Force Delete" : "User Information"; ?>
    </div>
    <div class="card-body">
        <?php if($force_delete): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle mr-2"></i>Warning: Force deleting a user is irreversible and may cause data integrity issues!
        </div>
        <?php endif; ?>
        
        <p>User details:</p>
        
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
            <?php if($has_dependencies): ?>
            <tr>
                <th>Dependencies</th>
                <td>
                    <p><strong><?php echo $total_dependencies; ?> total associated records</strong></p>
                    <ul class="mb-0">
                        <?php foreach($dependency_details as $detail): ?>
                            <li><?php echo htmlspecialchars($detail); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php if($force_delete): ?>
            <div class="alert alert-warning mt-3">
                <strong>You are about to force delete this user.</strong> This will permanently remove the user from the system, 
                even though they have <?php echo $total_dependencies; ?> associated records. These relationships will be affected and 
                may cause data integrity issues or broken references.
            </div>
        <?php else: ?>
            <?php if($has_dependencies): ?>
            <div class="alert alert-info mt-3">
                <p><strong>This user has associated records in the system.</strong></p>
                <p>If you choose to delete this user, they will be suspended instead of fully deleted to preserve data integrity.</p>
                <p>If you absolutely need to delete this user completely, you will have the option to force delete after suspension.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="mt-4">
            <?php if($force_delete): ?>
                <a href="process_user.php?id=<?php echo $user_id; ?>&action=delete&force=true" class="btn btn-danger">
                    <i class="fas fa-trash mr-1"></i>Confirm Force Delete
                </a>
            <?php else: ?>
                <?php if($user['status'] == 'active'): ?>
                    <a href="process_user.php?id=<?php echo $user_id; ?>&action=suspend" class="btn btn-warning mr-2">
                        <i class="fas fa-ban mr-1"></i>Suspend User
                    </a>
                <?php elseif($user['status'] == 'suspended'): ?>
                    <a href="process_user.php?id=<?php echo $user_id; ?>&action=activate" class="btn btn-success mr-2">
                        <i class="fas fa-check-circle mr-1"></i>Activate User
                    </a>
                <?php endif; ?>
                
                <a href="process_user.php?id=<?php echo $user_id; ?>&action=delete" class="btn btn-danger mr-2">
                    <i class="fas fa-trash mr-1"></i>Delete User
                </a>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-secondary">
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