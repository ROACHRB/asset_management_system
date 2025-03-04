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

// Initialize variables to store form data for persistence
$username = $full_name = $email = $department = "";
$role = "";
$error = "";

// Get roles list
$roles_query = "SELECT * FROM roles ORDER BY role_name";
$roles_result = mysqli_query($conn, $roles_query);

// Email validation function
function isValidEmail($email) {
    // First, basic filter validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Split email into local part and domain
    list($local, $domain) = explode('@', $email);
    
    // Check domain has at least one dot and is not too long
    if (!preg_match('/\./', $domain) || strlen($domain) > 255) {
        return false;
    }
    
    // MX record check (optional but recommended)
    if (function_exists('checkdnsrr')) {
        return checkdnsrr($domain, 'MX');
    }
    
    return true;
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Store form values to persist them
    $username = trim($_POST["username"]);
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $role = $_POST["role"];
    $department = !empty($_POST["department"]) ? trim($_POST["department"]) : "";
    
    // Validate username
    if(empty($username)) {
        $error = "Please enter a username.";
    } else {
        // Check if username exists
        $sql = "SELECT user_id FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
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
    } elseif(strlen(trim($_POST["password"])) < 8) {
        $error = "Password must have at least 8 characters.";
    } elseif(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,.<>])/', trim($_POST["password"]))) {
        $error = "Password must include at least one uppercase letter, one lowercase letter, one number, and one special character.";
    }
    
    // Validate email
    if(empty($email)) {
        $error = "Please enter an email address.";
    } elseif(!isValidEmail($email)) {
        $error = "Please enter a valid email address. The domain must be valid and have MX records.";
    } else {
        // Check if email already exists
        $sql = "SELECT user_id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) > 0) {
            $error = "This email address is already in use.";
        }
        mysqli_stmt_close($stmt);
    }
    
    // If no errors, proceed with insertion
    if(empty($error)) {
        $sql = "INSERT INTO users (username, password, full_name, email, role, department, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Prepare variables
            $param_password = password_hash(trim($_POST["password"]), PASSWORD_DEFAULT);
            
            // Bind parameters
            mysqli_stmt_bind_param($stmt, "ssssss", 
                $username,
                $param_password,
                $full_name,
                $email,
                $role,
                $department
            );
            
            if(mysqli_stmt_execute($stmt)) {
                // Log the action
                $new_user_id = mysqli_insert_id($conn);
                $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                             VALUES (?, 'user_created', ?, ?)";
                $log_stmt = mysqli_prepare($conn, $log_query);
                $current_user_id = $_SESSION['user_id'];
                $description = "Created new user: " . $username;
                $ip_address = $_SERVER['REMOTE_ADDR'];
                
                mysqli_stmt_bind_param($log_stmt, "iss", 
                    $current_user_id,
                    $description,
                    $ip_address
                );
                mysqli_stmt_execute($log_stmt);
                
                // Redirect to user list - this will now work correctly with output buffering
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
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="password" class="required-field">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" id="generatePassword">
                                <i class="fas fa-key mr-1"></i>Generate
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">
                        Requires minimum 8 characters with at least one uppercase letter, one lowercase letter,
                        one number, and one special character.
                    </small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="full_name" class="required-field">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="email" class="required-field">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <small class="form-text text-muted">
                        Please use a valid email with a real domain that has MX records.
                    </small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="role" class="required-field">Role</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <?php 
                        mysqli_data_seek($roles_result, 0);
                        while($role_option = mysqli_fetch_assoc($roles_result)): 
                            $selected = ($role == $role_option['role_name']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $role_option['role_name']; ?>" <?php echo $selected; ?>>
                                <?php echo ucfirst($role_option['role_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="department">Department</label>
                    <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($department); ?>">
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
    // Function to generate a strong password
    function generateStrongPassword(length = 12) {
        const lowercase = 'abcdefghijklmnopqrstuvwxyz';
        const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const numbers = '0123456789';
        const symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        // Ensure at least one of each character type
        let password = '';
        password += lowercase.charAt(Math.floor(Math.random() * lowercase.length));
        password += uppercase.charAt(Math.floor(Math.random() * uppercase.length));
        password += numbers.charAt(Math.floor(Math.random() * numbers.length));
        password += symbols.charAt(Math.floor(Math.random() * symbols.length));
        
        // Fill the rest of the password
        const allChars = lowercase + uppercase + numbers + symbols;
        for (let i = 4; i < length; i++) {
            password += allChars.charAt(Math.floor(Math.random() * allChars.length));
        }
        
        // Shuffle the password (Fisher-Yates algorithm for better randomization)
        const passwordArray = password.split('');
        for (let i = passwordArray.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [passwordArray[i], passwordArray[j]] = [passwordArray[j], passwordArray[i]];
        }
        
        return passwordArray.join('');
    }
    
    // Generate password button click handler
    $("#generatePassword").on('click', function(e) {
        e.preventDefault();
        const password = generateStrongPassword();
        $("#password").val(password);
        
        // Trigger validation if validator is initialized
        if($.validator && $("#userForm").valid) {
            $("#password").valid();
        }
    });

    // Custom email validation method
    $.validator.addMethod("validEmail", function(value, element) {
        // Basic email regex with more strict domain validation
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return this.optional(element) || emailRegex.test(value);
    }, "Please enter a valid email address with a proper domain.");

    // Form validation
    $("#userForm").validate({
        rules: {
            username: {
                required: true,
                minlength: 3
            },
            password: {
                required: true,
                minlength: 8,
                pattern: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{}|;:",./<>?])/
            },
            email: {
                required: true,
                email: true,
                validEmail: true
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
                minlength: "Password must be at least 8 characters",
                pattern: "Password must include at least one uppercase letter, one lowercase letter, one number, and one special character"
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

<?php 
include_once "../../includes/footer.php"; 
// End and flush the output buffer
ob_end_flush();
?>