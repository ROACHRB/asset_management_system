<?php
include_once "../../includes/header.php";

// Check if user has permission to manage users
if($_SESSION['role'] != 'admin') {
    echo '<div class="alert alert-danger">You do not have permission to access this page.</div>';
    include_once "../../includes/footer.php";
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

// Get users list with roles and status
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM asset_assignments 
           WHERE assigned_to = u.user_id AND assignment_status = 'assigned') as active_assignments,
          r.role_name
          FROM users u
          LEFT JOIN roles r ON u.role = r.role_name
          ORDER BY u.full_name";
$result = mysqli_query($conn, $query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-users mr-2"></i>User Management</h1>
        <p class="text-muted">Manage system users, roles, and permissions</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-user-plus mr-2"></i>Add New User
        </a>
        <a href="roles.php" class="btn btn-secondary ml-2">
            <i class="fas fa-user-shield mr-2"></i>Manage Roles
        </a>
    </div>
</div>

<!-- User Statistics -->
<div class="row mb-4">
    <?php
    $total_users = mysqli_num_rows($result);
    $active_users = 0;
    $suspended_users = 0;
    
    mysqli_data_seek($result, 0);
    while($row = mysqli_fetch_assoc($result)) {
        if($row['status'] == 'active') $active_users++;
        if($row['status'] == 'suspended') $suspended_users++;
    }
    mysqli_data_seek($result, 0);
    ?>
    
    <div class="col-xl-3 col-md-6">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Active Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_users; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Suspended Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $suspended_users; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-lock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card border-left-info h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Online Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php
                            // Count users who logged in within last 15 minutes
                            $online_users = 0;
                            mysqli_data_seek($result, 0);
                            while($row = mysqli_fetch_assoc($result)) {
                                if(!empty($row['last_login']) && 
                                   strtotime($row['last_login']) > strtotime('-15 minutes')) {
                                    $online_users++;
                                }
                            }
                            echo $online_users;
                            mysqli_data_seek($result, 0);
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>System Users
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if(!empty($row['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['profile_image']); ?>" 
                                         class="rounded-circle mr-2" width="30" height="30">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-2x mr-2 text-secondary"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($row['full_name']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($row['role'] == 'admin' ? 'danger' : 
                                    ($row['role'] == 'manager' ? 'warning' : 
                                    ($row['role'] == 'staff' ? 'info' : 'secondary'))); 
                            ?>">
                                <?php echo ucfirst($row['role']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($row['status'] == 'active' ? 'success' : 
                                    ($row['status'] == 'suspended' ? 'danger' : 'warning')); 
                            ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            if(!empty($row['last_login'])) {
                                echo date('M d, Y H:i', strtotime($row['last_login']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $row['user_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $row['user_id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if($row['user_id'] != $_SESSION['user_id']): ?>
                                    <?php if($row['status'] == 'active'): ?>
                                    <a href="suspend.php?id=<?php echo $row['user_id']; ?>" 
                                       class="btn btn-sm btn-warning confirm-action" 
                                       data-message="Are you sure you want to suspend this user?"
                                       title="Suspend User">
                                        <i class="fas fa-user-lock"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="activate.php?id=<?php echo $row['user_id']; ?>" 
                                       class="btn btn-sm btn-success confirm-action"
                                       data-message="Are you sure you want to activate this user?"
                                       title="Activate User">
                                        <i class="fas fa-user-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="delete_user.php?id=<?php echo $row['user_id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       title="Delete User">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                                
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('.data-table').DataTable();
    
    // Handle confirm actions
    $('.confirm-action').click(function(e) {
        e.preventDefault();
        if(confirm($(this).data('message'))) {
            window.location = $(this).attr('href');
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>