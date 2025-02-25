<?php
// File: includes/auth_middleware.php

/**
 * This file should be included at the beginning of each module page
 * to enforce proper access control throughout the application
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store original requested URL for later redirect after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: " . get_base_url() . "/login.php");
    exit;
}

// Define permission checks for various modules
$module_permissions = [
    'inventory' => 'manage_assets',
    'assets' => 'manage_assets',
    'assignments' => 'manage_assignments',
    'audits' => 'conduct_audits',
    'reports' => 'generate_reports',
    'users' => 'manage_users', 
    'locations' => 'manage_locations',
    'disposals' => 'approve_disposals'
];

// Get current module from URL
$current_url = $_SERVER['REQUEST_URI'];
$module = '';

// Extract module name from URL
foreach (array_keys($module_permissions) as $mod) {
    if (strpos($current_url, "modules/$mod/") !== false) {
        $module = $mod;
        break;
    }
}

// Include auth functions
include_once __DIR__ . "/auth_functions.php";

// Check permission for the current module
if (!empty($module) && isset($module_permissions[$module])) {
    $required_permission = $module_permissions[$module];
    
    // Skip permission check for index pages - they should handle their own display logic
    $is_index = preg_match('/\/index\.php$/', $current_url);
    
    if (!$is_index && !has_permission($required_permission)) {
        // Set error message
        $_SESSION['error'] = "You don't have permission to access this resource.";
        
        // Redirect to dashboard
        header("Location: " . get_base_url() . "/index.php");
        exit;
    }
}

/**
 * Get base URL of the application
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove /includes or /modules/* from path to get base URL
    $base_path = preg_replace('/(\/includes|\/modules\/[^\/]+)\/.*$/', '', $path);
    
    return $protocol . $domain . $base_path;
}