<?php
// Include database connection
require_once "config/database.php";

// Set user details
$username = 'admin';
$raw_password = 'admin123';
$full_name = 'System Administrator';
$email = 'admin@example.com';
$role = 'admin';

// Create password hash using current algorithm
$hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

// Show the hash for verification
echo "Username: $username<br>";
echo "Password: $raw_password<br>";
echo "Generated hash: $hashed_password<br><br>";

// Check if username already exists
$check_sql = "SELECT user_id FROM users WHERE username = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "s", $username);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if(mysqli_stmt_num_rows($check_stmt) > 0) {
    // Update existing user
    $update_sql = "UPDATE users SET password = ? WHERE username = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $username);
    
    if(mysqli_stmt_execute($update_stmt)) {
        echo "User '$username' updated successfully with new password.<br>";
        echo "You can now log in with:<br>";
        echo "Username: $username<br>";
        echo "Password: $raw_password<br>";
    } else {
        echo "Error updating user: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($update_stmt);
} else {
    // Insert new user
    $insert_sql = "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "sssss", $username, $hashed_password, $full_name, $email, $role);
    
    if(mysqli_stmt_execute($insert_stmt)) {
        echo "User '$username' created successfully.<br>";
        echo "You can now log in with:<br>";
        echo "Username: $username<br>";
        echo "Password: $raw_password<br>";
    } else {
        echo "Error creating user: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($insert_stmt);
}

mysqli_stmt_close($check_stmt);
mysqli_close($conn);
?>