<?php
// File: modules/users/roles.php
include_once "../../includes/header.php";
//include_once "../../includes/auth_functions.php";

// Check permission using the new auth system
require_permission('manage_users');

// Get roles with permission counts
$roles_query = "SELECT r.*, 
                COUNT(DISTINCT rp.permission_id) as permission_count,
                (SELECT COUNT(*) FROM users u WHERE u.role = r.role_name) as user_count
                FROM roles r
                LEFT JOIN role_permissions rp ON r.role_id = rp.role_id
                GROUP BY r.role_id
                ORDER BY r.role_name";
$roles_result = mysqli_query($conn, $roles_query);

// Get all permissions
$permissions_query = "SELECT * FROM permissions ORDER BY permission_name";
$permissions_result = mysqli_query($conn, $permissions_query);
$permissions = [];
while($permission = mysqli_fetch_assoc($permissions_result)) {
    $permissions[] = $permission;
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'update_permissions':
                $role_id = $_POST['role_id'];
                
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Delete existing permissions
                    $delete_sql = "DELETE FROM role_permissions WHERE role_id = ?";
                    $stmt = mysqli_prepare($conn, $delete_sql);
                    mysqli_stmt_bind_param($stmt, "i", $role_id);
                    mysqli_stmt_execute($stmt);
                    
                    // Insert new permissions
                    if(isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $insert_sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                        $stmt = mysqli_prepare($conn, $insert_sql);
                        
                        foreach($_POST['permissions'] as $permission_id) {
                            mysqli_stmt_bind_param($stmt, "ii", $role_id, $permission_id);
                            mysqli_stmt_execute($stmt);
                        }
                    }
                    
                    mysqli_commit($conn);
                    $_SESSION['success'] = "Role permissions updated successfully.";
                    log_activity('update_permissions', "Updated permissions for role ID: $role_id");
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $_SESSION['error'] = "Error updating permissions: " . $e->getMessage();
                }
                break;
                
            case 'add_role':
                $role_name = strtolower(trim($_POST['role_name'])); // Make lowercase for consistency
                $description = trim($_POST['description']);
                
                if(empty($role_name)) {
                    $_SESSION['error'] = "Role name is required.";
                } else {
                    // Check if role name already exists
                    $check_sql = "SELECT 1 FROM roles WHERE LOWER(role_name) = ?";
                    $stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($stmt, "s", $role_name);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if(mysqli_num_rows($result) > 0) {
                        $_SESSION['error'] = "A role with this name already exists.";
                    } else {
                        $sql = "INSERT INTO roles (role_name, description) VALUES (?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ss", $role_name, $description);
                        
                        if(mysqli_stmt_execute($stmt)) {
                            $_SESSION['success'] = "New role added successfully.";
                            log_activity('add_role', "Added new role: $role_name");
                            header("Location: roles.php");
                            exit();
                        } else {
                            $_SESSION['error'] = "Error adding role: " . mysqli_error($conn);
                        }
                    }
                }
                break;
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-shield mr-2"></i>Role Management</h1>
        <p class="text-muted">Manage user roles and permissions</p>
    </div>
    <div class="col-md-4 text-right">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRoleModal">
            <i class="fas fa-plus mr-2"></i>Add New Role
        </button>
    </div>
</div>

<?php if(isset($_SESSION['error'])): ?>
<div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<!-- Roles List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>System Roles
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Permissions</th>
                        <th>Users</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($role = mysqli_fetch_assoc($roles_result)): ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($role['role_name'] == 'admin' ? 'danger' : 
                                    ($role['role_name'] == 'manager' ? 'warning' : 
                                    ($role['role_name'] == 'staff' ? 'info' : 'secondary'))); 
                            ?>">
                                <?php echo ucfirst($role['role_name']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($role['description']); ?></td>
                        <td>
                            <?php echo $role['permission_count']; ?> permissions
                            <button class="btn btn-sm btn-outline-primary ml-2" 
                                    onclick="viewPermissions(<?php echo $role['role_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                        <td>
                            <?php 
                            echo $role['user_count']; 
                            echo $role['user_count'] == 1 ? ' user' : ' users';
                            ?>
                        </td>
                        <td>
                            <?php if($role['role_name'] != 'admin'): ?>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="deleteRole(<?php echo $role['role_id']; ?>)"
                                    <?php echo $role['user_count'] > 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Role</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_role">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="role_name">Role Name</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Permissions Modal -->
<div class="modal fade" id="editPermissionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Role Permissions</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_permissions">
                <input type="hidden" name="role_id" id="edit_role_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="permissions-list">
                            <?php foreach($permissions as $permission): ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" 
                                       id="permission_<?php echo $permission['permission_id']; ?>"
                                       name="permissions[]" 
                                       value="<?php echo $permission['permission_id']; ?>">
                                <label class="custom-control-label" 
                                       for="permission_<?php echo $permission['permission_id']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $permission['permission_name'])); ?>
                                    <small class="text-muted d-block">
                                        <?php echo $permission['description']; ?>
                                    </small>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// View/Edit Permissions
function viewPermissions(roleId) {
    $('#edit_role_id').val(roleId);
    
    // Reset checkboxes
    $('.permissions-list input[type="checkbox"]').prop('checked', false);
    
    // Get current permissions
    $.get('get_role_permissions.php', {role_id: roleId}, function(data) {
        data.forEach(function(permissionId) {
            $('#permission_' + permissionId).prop('checked', true);
        });
        $('#editPermissionsModal').modal('show');
    });
}

// Delete Role
function deleteRole(roleId) {
    if(confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
        window.location.href = 'delete_role.php?id=' + roleId;
    }
}
</script>

<?php include_once "../../includes/footer.php"; ?>