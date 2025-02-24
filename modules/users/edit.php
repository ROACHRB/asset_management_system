<?php
include_once "../../includes/header.php";

// Check permission
if($_SESSION['role'] != 'admin') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    include_once "../../includes/footer.php";
    exit;
}

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: index.php");
    exit();
}

$user_id = $_GET['id'];

// Get roles list
$roles_query = "SELECT * FROM roles ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_query);

// Get user details
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $error = "";
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        $error = "Please enter a username.";
    } else {
        // Check if username exists (excluding current user)
        $sql = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", trim($_POST["username"]), $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) > 0) {
            $error = "This username is already taken.";
        }
        mysqli_stmt_close($stmt);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $error = "Please enter an email address.";
    } elseif(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    
    // If no errors, proceed with update
    if(empty($error)) {
        // Prepare base SQL without password
        $sql_parts = [
            "username = ?",
            "full_name = ?",
            "email = ?",
            "role = ?",
            "department = ?",
            "status = ?"
        ];
        $params = [
            trim($_POST["username"]),
            trim($_POST["full_name"]),
            trim($_POST["email"]),
            $_POST["role"],
            !empty($_POST["department"]) ? trim($_POST["department"]) : null,
            $_POST["status"]
        ];
        $types = "ssssss";

        // Add password update if provided
        if(!empty($_POST["password"])) {
            if(strlen(trim($_POST["password"])) < 6) {
                $error = "Password must have at least 6 characters.";
            } else {
                $sql_parts[] = "password = ?";
                $params[] = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT);
                $types .= "s";
            }
        }

        if(empty($error)) {
            $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE user_id = ?";
            $params[] = $user_id;
            $types .= "i";

            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                
                if(mysqli_stmt_execute($stmt)) {
                    // Log the action
                    $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                                VALUES (?, 'user_updated', ?, ?)";
                    $log_stmt = mysqli_prepare($conn, $log_query);
                    mysqli_stmt_bind_param($log_stmt, "iss", 
                        $_SESSION['user_id'],
                        "Updated user: " . trim($_POST["username"]),
                        $_SERVER['REMOTE_ADDR']
                    );
                    mysqli_stmt_execute($log_stmt);
                    
                    // Redirect to user list
                    header("location: index.php");
                    exit();
                } else {
                    $error = "Something went wrong. Please try again later.";
                }
                
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-edit mr-2"></i>Edit User</h1>
        <p class="text-muted">Update user information</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>User Information
    </div>
    <div class="card-body">
        <form method="post" id="userForm">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="username" class="required-field">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="password">New Password</label>
                    <input type="password" class="form-control" id="password" name="password">
                    <small class="form-text text-muted">Leave blank to keep current password</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="full_name" class="required-field">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="email" class="required-field">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="role" class="required-field">Role</label>
                    <select class="form-control" id="role" name="role" required>
                        <?php 
                        mysqli_data_seek($roles_result, 0);
                        while($role = mysqli_fetch_assoc($roles_result)): 
                        ?>
                            <option value="<?php echo $role['role_name']; ?>" 
                                    <?php if($user['role'] == $role['role_name']) echo 'selected'; ?>>
                                <?php echo ucfirst($role['role_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="department">Department</label>
                    <input type="text" class="form-control" id="department" name="department" 
                           value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="status" class="required-field">Status</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="active" <?php if($user['status'] == 'active') echo 'selected'; ?>>Active</option>
                        <option value="inactive" <?php if($user['status'] == 'inactive') echo 'selected'; ?>>Inactive</option>
                        <option value="suspended" <?php if($user['status'] == 'suspended') echo 'selected'; ?>>Suspended</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Update User
            </button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $("#userForm").validate({
        rules: {
            username: {
                required: true,
                minlength: 3
            },
            password: {
                minlength: 6
            },
            email: {
                required: true,
                email: true
            },
            full_name: "required",
            role: "required",
            status: "required"
        },
        messages: {
            username: {
                required: "Please enter a username",
                minlength: "Username must be at least 3 characters"
            },
            password: {
                minlength: "Password must be at least 6 characters"
            },
            email: {
                required: "Please enter an email address",
                email: "Please enter a valid email address"
            },
            full_name: "Please enter the full name",
            role: "Please select a role",
            status: "Please select a status"
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