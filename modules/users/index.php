<?php
include_once "../../includes/header.php";

// Get users list
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM asset_assignments WHERE assigned_to = u.user_id AND assignment_status = 'assigned') as active_assignments
          FROM users u 
          ORDER BY u.full_name";
$result = mysqli_query($conn, $query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-users mr-2"></i>User Management</h1>
        <p class="text-muted">Manage system users and their roles</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-user-plus mr-2"></i>Add New User
        </a>
    </div>
</div>

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
                        <th>Active Assignments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($row['role'] == 'admin' ? 'danger' : 
                                    ($row['role'] == 'staff' ? 'primary' : 'secondary')); 
                            ?>">
                                <?php echo ucfirst($row['role']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                        <td class="text-center">
                            <?php if($row['active_assignments'] > 0): ?>
                                <a href="../assignments/index.php?user=<?php echo $row['user_id']; ?>" 
                                   class="badge badge-info">
                                    <?php echo $row['active_assignments']; ?> items
                                </a>
                            <?php else: ?>
                                <span class="badge badge-secondary">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $row['user_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $row['user_id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if($row['user_id'] != $_SESSION['user_id']): ?>
                                <a href="delete.php?id=<?php echo $row['user_id']; ?>" 
                                   class="btn btn-sm btn-danger confirm-delete" title="Delete User"
                                   data-assignments="<?php echo $row['active_assignments']; ?>">
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
    $('.confirm-delete').click(function(e) {
        e.preventDefault();
        const assignments = $(this).data('assignments');
        let message = 'Are you sure you want to delete this user?';
        
        if(assignments > 0) {
            message += '\nWarning: This user has ' + assignments + ' active assignments.';
        }
        
        if(confirm(message)) {
            window.location = $(this).attr('href');
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>