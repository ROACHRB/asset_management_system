<?php
// File: includes/check_login.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
include_once "config/database.php";

// Function to check login
function check_login($conn) {
    // Check if user is logged in
    if(isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        $user_id = $_SESSION['user_id'];
        
        // Verify user exists and is active
        $query = "SELECT user_id, username, full_name, email, role, status FROM users 
                 WHERE user_id = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            // User exists and is active
            $user = mysqli_fetch_assoc($result);
            
            // Update session variables if needed
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            
            return true;
        } else {
            // User doesn't exist or is inactive, clear session
            logout();
            return false;
        }
    }
    
    return false;
}

// Function to log user in
function login($username, $password, $conn) {
    $query = "SELECT user_id, username, password, full_name, email, role, status 
             FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Verify password
        if(password_verify($password, $user['password'])) {
            // Check if user is active
            if($user['status'] == 'active') {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login time
                $update_query = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
                mysqli_stmt_execute($stmt);
                
                // Log activity
                log_login($user['user_id'], $conn);
                
                return true;
            } else {
                // User account is inactive
                return 'inactive';
            }
        }
    }
    
    return false;
}

// Function to log out
function logout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Log login activity
function log_login($user_id, $conn) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
              VALUES (?, 'login', 'User logged in', ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $ip_address);
    mysqli_stmt_execute($stmt);
}

// Include auth functions for permissions
include_once "includes/auth_functions.php";