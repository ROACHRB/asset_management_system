<?php
include_once "../../includes/header.php";

// Check permission
if($_SESSION['role'] != 'admin') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    include_once "../../includes/footer.php";
    exit;
}

// Get roles list
$roles_query = "SELECT * FROM roles ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_query);

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $error = "";
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        $error = "Please enter a username.";
    } else {
        // Check if username exists
        $sql = "SELECT user_id FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", trim($_POST["username"]));
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) > 0) {
            $error = "This username is already taken.";
        }
        mysqli_stmt_close($stmt);
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $error = "Please enter a password.";
    } elseif(strlen(trim($_POST["password"])) < 6) {
        $error = "Password must have at least 6 characters.";
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $error = "Please enter an email address.";
    } elseif(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    
    // If no errors, proceed with insertion
    if(empty($error)) {
        $sql = "INSERT INTO users (username, password, full_name, email, role, department, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Hash the password
            $param_password = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT);
            
            // Bind parameters
            mysqli_stmt_bind_param($stmt, "ssssss", 
                trim($_POST["username"]),
                $param_password,
                trim($_POST["full_name"]),
                trim($_POST["email"]),
                $_POST["role"],
                !empty($_POST["department"]) ? trim($_POST["department"]) : null
            );
            
            if(mysqli_stmt_execute($stmt)) {
                // Log the action
                $new_user_id = mysqli_insert_id($conn);
                $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                             VALUES (?, 'user_created', ?, ?)";
                $log_stmt = mysqli_prepare($conn, $log_query);
                mysqli_stmt_bind_param($log_stmt, "iss", 
                    $_SESSION['user_id'],
                    "Created new user: " . trim($_POST["username"]),
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
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-plus mr-2"></i>Add New User</h1>
        <p class="text-muted">Create a new system user</p>
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
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="password" class="required-field">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <small class="form-text text-muted">Minimum 6 characters</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="full_name" class="required-field">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="email" class="required-field">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="role" class="required-field">Role</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <?php while($role = mysqli_fetch_assoc($roles_result)): ?>
                            <option value="<?php echo $role['role_name']; ?>">
                                <?php echo ucfirst($role['role_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="department">Department</label>
                    <input type="text" class="form-control" id="department" name="department">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Create User
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
                required: true,
                minlength: 6
            },
            email: {
                required: true,
                email: true
            },
            full_name: "required",
            role: "required"
        },
        messages: {
            username: {
                required: "Please enter a username",
                minlength: "Username must be at least 3 characters"
            },
            password: {
                required: "Please enter a password",
                minlength: "Password must be at least 6 characters"
            },
            email: {
                required: "Please enter an email address",
                email: "Please enter a valid email address"
            },
            full_name: "Please enter the full name",
            role: "Please select a role"
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