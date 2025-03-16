<?php
include_once "../../includes/header.php";

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details with department name
$user_query = "SELECT u.*, d.department_name 
               FROM users u
               LEFT JOIN departments d ON u.department = d.department_id
               WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

$password_error = "";
$current_password_error = "";
$success_message = "";

// Process password change request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    // Validate current password
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $current_password_error = "Current password is incorrect";
    } else if ($new_password != $confirm_password) {
        $password_error = "New passwords do not match";
    } else {
        // Check password complexity
        $uppercase = preg_match('@[A-Z]@', $new_password);
        $lowercase = preg_match('@[a-z]@', $new_password);
        $number    = preg_match('@[0-9]@', $new_password);
        $specialChars = preg_match('@[^\w]@', $new_password);
        
        if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($new_password) < 8) {
            $password_error = "Password must be at least 8 characters and include at least one uppercase letter, one lowercase letter, one number, and one special character";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the password in the database
            $update_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Log the activity
                log_activity('password_change', 'User changed their password');
                
                $success_message = "Password has been changed successfully!";
            } else {
                $password_error = "An error occurred. Please try again.";
            }
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-edit mr-2"></i>Edit Profile</h1>
        <p class="text-muted">Update your account information</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="profile.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Profile
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Account Information
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <?php if(!empty($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                             class="rounded-circle mb-3" width="120" height="120" alt="Profile Image">
                    <?php else: ?>
                        <i class="fas fa-user-circle fa-5x mb-3 text-secondary"></i>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Full Name:</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Department:</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['department_name'] ?? 'Not specified'); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Role:</label>
                    <input type="text" class="form-control" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Account Status:</label>
                    <input type="text" class="form-control" value="<?php echo ucfirst(htmlspecialchars($user['status'])); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Account Created:</label>
                    <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($user['created_at'])); ?>" readonly>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-key mr-1"></i>Change Password
            </div>
            <div class="card-body">
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="current_password">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo (!empty($current_password_error)) ? 'is-invalid' : ''; ?>" 
                               id="current_password" name="current_password" required>
                        <?php if(!empty($current_password_error)): ?>
                            <div class="invalid-feedback"><?php echo $current_password_error; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo (!empty($password_error)) ? 'is-invalid' : ''; ?>" 
                               id="new_password" name="new_password" required>
                        <small class="form-text text-muted">
                            Password must contain at least 8 characters, including uppercase, lowercase, numbers, and special characters.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo (!empty($password_error)) ? 'is-invalid' : ''; ?>" 
                               id="confirm_password" name="confirm_password" required>
                        <?php if(!empty($password_error)): ?>
                            <div class="invalid-feedback"><?php echo $password_error; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="password-strength-meter mt-3 mb-4">
                        <div class="progress">
                            <div id="password-strength" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div id="password-strength-text" class="text-muted small mt-1">Password strength: None</div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength meter
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    let strengthText = '';
    
    // Criteria check
    if (password.length >= 8) strength += 20;
    if (password.match(/[A-Z]+/)) strength += 20;
    if (password.match(/[a-z]+/)) strength += 20;
    if (password.match(/[0-9]+/)) strength += 20;
    if (password.match(/[^\w\s]+/)) strength += 20;
    
    // Update progress bar
    const progressBar = document.getElementById('password-strength');
    progressBar.style.width = strength + '%';
    
    // Update color based on strength
    if (strength <= 20) {
        progressBar.className = 'progress-bar bg-danger';
        strengthText = 'Very Weak';
    } else if (strength <= 40) {
        progressBar.className = 'progress-bar bg-warning';
        strengthText = 'Weak';
    } else if (strength <= 60) {
        progressBar.className = 'progress-bar bg-info';
        strengthText = 'Medium';
    } else if (strength <= 80) {
        progressBar.className = 'progress-bar bg-primary';
        strengthText = 'Strong';
    } else {
        progressBar.className = 'progress-bar bg-success';
        strengthText = 'Very Strong';
    }
    
    document.getElementById('password-strength-text').textContent = 'Password strength: ' + strengthText;
});
</script>

<?php include_once "../../includes/footer.php"; ?>