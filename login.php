<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize the session securely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: index.php");
    exit;
}

// Include database configuration
require_once "config/database.php";

// Initialize variables
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter your username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Proceed if no errors
    if (empty($username_err) && empty($password_err)) {
        // Prepare SQL statement
        $sql = "SELECT user_id, username, password, full_name, role, status FROM users WHERE username = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                // Check user status
                if ($row["status"] === 'suspended') {
                    $login_err = "Your account has been suspended by the admin.";
                } elseif ($row["status"] !== 'active') {
                    $login_err = "Your account is not yet approved. Please contact the administrator.";
                }
                 elseif (password_verify($password, $row['password'])) {
                    // Start session and store user data
                    session_regenerate_id(true);
                    $_SESSION["loggedin"] = true;
                    $_SESSION["user_id"] = $row["user_id"];
                    $_SESSION["username"] = $row["username"];
                    $_SESSION["full_name"] = $row["full_name"];
                    $_SESSION["role"] = $row["role"];

                    // Update last login time
                    $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "i", $row["user_id"]);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    }

                    // Log the login activity
                    $user_id = $row["user_id"];
                    $activity_type = 'login';
                    $description = 'User logged in to the system';
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    
                    $log_query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
                                VALUES (?, ?, ?, ?)";
                    
                    if ($log_stmt = mysqli_prepare($conn, $log_query)) {
                        mysqli_stmt_bind_param($log_stmt, "isss", $user_id, $activity_type, $description, $ip_address);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);
                    }

                    // Redirect to dashboard
                    header("Location: index.php");
                    exit;
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Invalid username or password.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $login_err = "Something went wrong. Try again later.";
        }
    }
    mysqli_close($conn);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
            padding: 15px;
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
        .password-toggle {
            cursor: pointer;
            padding: 0.375rem 0.75rem;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-left: none;
            border-radius: 0 0.25rem 0.25rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            background-color: #d1d7dc;
        }
        
        /* WebView specific styles */
        html, body {
            overflow-x: hidden;
            position: relative;
            width: 100%;
        }
        
        /* Improve mobile WebView compatibility */
        @media (max-width: 767px) {
            body {
                height: auto;
                min-height: 100vh;
                padding: 20px 15px;
            }
            
            .login-container {
                padding: 20px;
                max-width: 100%;
                margin: 0 auto;
            }
            
            .logo-icon {
                font-size: 2.5rem;
            }
            
            /* Fix input issues on mobile WebView */
            .form-control {
                -webkit-appearance: none;
                border-radius: 0.25rem;
                height: 44px; /* Slightly bigger tap targets */
                font-size: 16px !important; /* Prevents iOS zoom on focus */
            }
            
            .input-group-text {
                height: 44px;
            }
            
            /* Increase form field spacing */
            .form-group {
                margin-bottom: 20px;
            }
            
            /* Make login button more tappable */
            .btn {
                height: 44px;
                font-size: 16px;
            }
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

        <?php if (!empty($login_err)): ?>
            <div class="alert alert-danger"><?php echo $login_err; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                    <?php if (!empty($username_err)): ?>
                        <div class="invalid-feedback"><?php echo $username_err; ?></div>
                    <?php endif; ?>
                </div>
            </div>    
            <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <div class="input-group-append">
                        <span class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <?php if (!empty($password_err)): ?>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </div>
            <div class="text-center mt-3">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Toggle password visibility
        $("#togglePassword").click(function() {
            const passwordField = $("#password");
            const passwordFieldType = passwordField.attr("type");
            passwordField.attr("type", passwordFieldType === "password" ? "text" : "password");
            $(this).find("i").toggleClass("fa-eye fa-eye-slash");
        });
        
        // Fix for some WebView issues - ensure proper viewport height
        function adjustHeight() {
            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
        
        // Set the height initially and on resize
        adjustHeight();
        window.addEventListener('resize', adjustHeight);
        window.addEventListener('orientationchange', adjustHeight);
    });
    </script>
</body>
</html>