<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes redirect to dashboard
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: index.php");
    exit;
}

// Include config file
require_once "config/database.php";

// First, we need to modify the users table to add 'pending' status if it doesn't exist
$alter_query = "ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'suspended', 'pending') NOT NULL DEFAULT 'pending'";
mysqli_query($conn, $alter_query);

// Get departments for dropdown
// Get departments for dropdown
$departments_query = "SELECT department_id, department_name FROM departments ORDER BY department_name";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
if ($departments_result) {
    while($row = mysqli_fetch_assoc($departments_result)) {
        $departments[$row['department_id']] = $row['department_name'];
    }
}

// Get campus locations - assuming these are stored in locations table
$locations_query = "SELECT DISTINCT building FROM locations ORDER BY building";
$locations_result = mysqli_query($conn, $locations_query);
$locations = [];
while($row = mysqli_fetch_assoc($locations_result)) {
    if(!empty($row['building'])) {
        $locations[] = $row['building'];
    }
}

// Define variables and initialize with empty values
$username = $password = $confirm_password = $full_name = $email = $department = $campus = "";
$username_err = $password_err = $confirm_password_err = $full_name_err = $email_err = $success = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else{
        // Prepare a select statement
        $sql = "SELECT user_id FROM users WHERE username = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // Set parameters
            $param_username = trim($_POST["username"]);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store result
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) > 0){
                    $username_err = "This username is already taken.";
                } else{
                    $username = trim($_POST["username"]);
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 8){
        $password_err = "Password must have at least 8 characters.";
    } elseif(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_=+{};:,.<>])/', trim($_POST["password"]))) {
        $password_err = "Password must include at least one uppercase letter, one lowercase letter, one number, and one special character.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate full name
    if(empty(trim($_POST["full_name"]))){
        $full_name_err = "Please enter your full name.";
    } else{
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter an email address.";
    } elseif(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)){
        $email_err = "Please enter a valid email address.";
    } else{
        // Check if email already exists
        $sql = "SELECT user_id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $email_param = trim($_POST["email"]);
mysqli_stmt_bind_param($stmt, "s", $email_param);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) > 0){
            $email_err = "This email is already registered.";
        } else {
            $email = trim($_POST["email"]);
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get other form data
    $department = !empty($_POST["department"]) ? trim($_POST["department"]) : "";
    $campus = !empty($_POST["campus"]) ? trim($_POST["campus"]) : "";
    
    // Check input errors before inserting in database
    if(empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($full_name_err) && empty($email_err)){
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password, full_name, email, role, department, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
         
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssssss", $param_username, $param_password, $param_full_name, $param_email, $param_role, $param_department);
            
            // Set parameters
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_full_name = $full_name;
            $param_email = $email;
            $param_role = "user"; // Default role for new registrations
            // Handle both numeric department IDs and "other" custom departments
if ($department === "other" && !empty($_POST["other_department"])) {
    $param_department = trim($_POST["other_department"]);
} else {
    $param_department = $department; // This will now be the department_id
}
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store additional data in session for confirmation page
                $_SESSION['registration_data'] = [
                    'username' => $username,
                    'full_name' => $full_name,
                    'email' => $email
                ];
                
                // Registration successful
                $success = "Your registration has been submitted successfully. An administrator will review your account shortly.";
                
                // Clear form fields after successful submission
                $username = $password = $confirm_password = $full_name = $email = $department = $campus = "";
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Asset Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .register-container {
            max-width: 600px;
            width: 100%;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-icon {
            font-size: 3rem;
            color: #007bff;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .password-toggle {
            cursor: pointer;
            padding: 0.375rem 0.75rem;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-left: none;
            border-radius: 0 0.25rem 0.25rem 0;
        }
        .password-toggle:hover {
            background-color: #d1d7dc;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <h2 class="mt-3">Asset Management</h2>
            <p class="text-muted">Create a new account</p>
        </div>

        <?php if(!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <div class="text-center mb-4">
                <p>Your account is pending approval from an administrator.</p>
                <a href="login.php" class="btn btn-primary">Return to Login</a>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="registerForm">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                            <div class="invalid-feedback"><?php echo $username_err; ?></div>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            </div>
                            <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $full_name; ?>">
                            <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        </div>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="generatePassword">
                                    <i class="fas fa-key"></i>
                                </button>
                                <span class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        </div>
                        <small class="password-requirements">
                            Requires minimum 8 characters with at least one uppercase letter, one lowercase letter, 
                            one number, and one special character.
                        </small>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="input-group-append">
                                <span class="password-toggle" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Department</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                            </div>
                            <select name="department" class="form-control">
    <option value="">Select Department</option>
    <?php foreach($departments as $id => $name): ?>
    <option value="<?php echo htmlspecialchars($id); ?>" <?php if($department == $id) echo 'selected'; ?>>
        <?php echo htmlspecialchars($name); ?>
    </option>
    <?php endforeach; ?>
                                <!-- Option to enter custom department -->
                                <option value="other">Other (Specify)</option>
                            </select>
                        </div>
                        <div id="otherDepartmentDiv" style="display:none;" class="mt-2">
                            <input type="text" id="otherDepartment" class="form-control" placeholder="Enter your department">
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Campus Location</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            </div>
                            <select name="campus" class="form-control">
                                <option value="">Select Campus</option>
                                <?php foreach($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" <?php if($campus == $loc) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                                <?php endforeach; ?>
                                <!-- If no locations exist in the database yet -->
                                <?php if(empty($locations)): ?>
                                <option value="Main Campus">Main Campus</option>
                                <option value="North Campus">North Campus</option>
                                <option value="South Campus">South Campus</option>
                                <option value="East Campus">East Campus</option>
                                <option value="West Campus">West Campus</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Register</button>
                </div>
                <div class="text-center">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    $(document).ready(function() {
        // Function to generate a strong password
        function generateStrongPassword(length = 12) {
            const lowercase = 'abcdefghijklmnopqrstuvwxyz';
            const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const numbers = '0123456789';
            const symbols = '!@#$%^&*()-_=+{}[];:,.<>';
            
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
            
            // Shuffle the password (Fisher-Yates algorithm)
            const passwordArray = password.split('');
            for (let i = passwordArray.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [passwordArray[i], passwordArray[j]] = [passwordArray[j], passwordArray[i]];
            }
            
            return passwordArray.join('');
        }
        
        // Toggle password visibility
        $("#togglePassword").click(function() {
            // Toggle the type attribute of the password field
            const passwordField = $("#password");
            const passwordFieldType = passwordField.attr("type");
            
            // Toggle type between password and text
            passwordField.attr("type", 
                passwordFieldType === "password" ? "text" : "password"
            );
            
            // Toggle eye icon
            $(this).find("i").toggleClass("fa-eye fa-eye-slash");
        });
        
        // Toggle confirm password visibility
        $("#toggleConfirmPassword").click(function() {
            // Toggle the type attribute of the confirm password field
            const confirmPasswordField = $("#confirm_password");
            const confirmPasswordFieldType = confirmPasswordField.attr("type");
            
            // Toggle type between password and text
            confirmPasswordField.attr("type", 
                confirmPasswordFieldType === "password" ? "text" : "password"
            );
            
            // Toggle eye icon
            $(this).find("i").toggleClass("fa-eye fa-eye-slash");
        });
        
        // Generate password button click handler
        $("#generatePassword").on('click', function(e) {
            e.preventDefault();
            const password = generateStrongPassword();
            $("#password").val(password);
            $("#confirm_password").val(password);
            
            // If password fields are currently shown as text, keep them visible
            if($("#password").attr("type") === "text") {
                // Password is already visible, do nothing
            } else {
                // Make password visible
                $("#password").attr("type", "text");
                $("#togglePassword").find("i").removeClass("fa-eye").addClass("fa-eye-slash");
            }
            
            if($("#confirm_password").attr("type") === "text") {
                // Confirm password is already visible, do nothing
            } else {
                // Make confirm password visible
                $("#confirm_password").attr("type", "text");
                $("#toggleConfirmPassword").find("i").removeClass("fa-eye").addClass("fa-eye-slash");
            }
            
            // After 3 seconds, hide the passwords again
            setTimeout(function() {
                if($("#password").attr("type") === "text") {
                    $("#password").attr("type", "password");
                    $("#togglePassword").find("i").removeClass("fa-eye-slash").addClass("fa-eye");
                }
                
                if($("#confirm_password").attr("type") === "text") {
                    $("#confirm_password").attr("type", "password");
                    $("#toggleConfirmPassword").find("i").removeClass("fa-eye-slash").addClass("fa-eye");
                }
            }, 3000);
        });
        
        // Handle "Other" department selection
        $("select[name='department']").change(function() {
            if ($(this).val() === "other") {
                $("#otherDepartmentDiv").show();
            } else {
                $("#otherDepartmentDiv").hide();
            }
        });
        
        // Before form submission, check if "other" department is selected
        $("#registerForm").submit(function() {
            if ($("select[name='department']").val() === "other") {
                let otherDept = $("#otherDepartment").val();
                if (otherDept) {
                    $("select[name='department']").append(
                        $('<option>', {
                            value: otherDept,
                            text: otherDept,
                            selected: 'selected'
                        })
                    );
                }
            }
        });
    });
    </script>
</body>
</html>