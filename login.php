<?php
// Initialize the session
session_start();

// TEMPORARY TESTING BACKDOOR - REMOVE THIS IN PRODUCTION
if(isset($_GET['directlogin']) && $_GET['directlogin'] == 'test123') {
    $_SESSION["loggedin"] = true;
    $_SESSION["user_id"] = 1;
    $_SESSION["username"] = "admin";
    $_SESSION["full_name"] = "System Administrator";
    $_SESSION["role"] = "admin";
    header("location: index.php");
    exit;
}

// Check if the user is already logged in, if yes redirect to dashboard
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: index.php");
    exit;
}

// Include config file
require_once "config/database.php";

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Check if username is empty
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        // Simplified approach - directly query the database
        $sql = "SELECT user_id, username, password, full_name, role FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if($row = mysqli_fetch_assoc($result)) {
            // User found, verify password
            if(password_verify($password, $row['password'])) {
                // Password is correct, start a new session
                session_start();
                
                // Store data in session variables
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = $row["user_id"];
                $_SESSION["username"] = $row["username"];
                $_SESSION["full_name"] = $row["full_name"];
                $_SESSION["role"] = $row["role"];
                
                // Redirect to dashboard
                header("location: index.php");
                exit;
            } else {
                // Password is not valid
                $login_err = "Invalid username or password.";
            }
        } else {
            // Username doesn't exist
            $login_err = "Invalid username or password.";
        }
        
        mysqli_stmt_close($stmt);
    }
    
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Asset Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <h2 class="mt-3">Asset Management</h2>
            <p class="text-muted">Login to your account</p>
        </div>

        <?php 
        if(!empty($login_err)){
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }        
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                </div>
            </div>    
            <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </div>
        </form>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>