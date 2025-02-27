<?php
// File: includes/functions.php

// Include database configuration if not already included
if(!defined('DB_SERVER')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/asset_management_system/config/database.php';
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user has a specific permission
 * 
 * @param string $permission_name The permission name to check for
 * @return bool True if user has permission, false otherwise
 */
function has_permission($permission_name) {
    global $conn;
    
    // If not logged in, no permissions
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? '';
    
    // Admin always has all permissions
    if ($user_role == 'admin') {
        return true;
    }
    
    // Get the permission ID for the given name
    $permission_query = "SELECT permission_id FROM permissions WHERE permission_name = ?";
    $stmt = mysqli_prepare($conn, $permission_query);
    mysqli_stmt_bind_param($stmt, "s", $permission_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($permission = mysqli_fetch_assoc($result)) {
        $permission_id = $permission['permission_id'];
        
        // Get the role ID for the user's role
        $role_query = "SELECT role_id FROM roles WHERE role_name = ?";
        $stmt = mysqli_prepare($conn, $role_query);
        mysqli_stmt_bind_param($stmt, "s", $user_role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($role = mysqli_fetch_assoc($result)) {
            $role_id = $role['role_id'];
            
            // Check if the role has the permission
            $check_query = "SELECT 1 FROM role_permissions 
                          WHERE role_id = ? AND permission_id = ?";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "ii", $role_id, $permission_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            return mysqli_num_rows($result) > 0;
        }
    }
    
    return false;
}

/**
 * Check if a user has permission to access a page/feature and redirect if not
 * 
 * @param string $permission_name The permission name to check
 * @param string $redirect_url Optional URL to redirect to if check fails (defaults to index.php)
 * @return void
 */
function require_permission($permission_name, $redirect_url = null) {
    if (!has_permission($permission_name)) {
        // Store error message in session
        $_SESSION['error'] = "Access denied. You don't have permission to perform this action.";
        
        // Redirect to specified URL or index
        if ($redirect_url) {
            header("Location: $redirect_url");
        } else {
            // Get base URL for redirection
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $path = dirname($_SERVER['SCRIPT_NAME']);
            $base_path = preg_replace('/(\/includes|\/modules\/[^\/]+)\/.*$/', '', $path);
            header("Location: " . $protocol . $domain . $base_path . "/index.php");
        }
        exit;
    }
}

/**
 * Conditionally display UI elements based on user permissions
 * 
 * @param string $permission_name The permission required to view the element
 * @param string $content The HTML content to display
 * @return void Outputs the content if user has permission
 */
function permission_content($permission_name, $content) {
    if (has_permission($permission_name)) {
        echo $content;
    }
}

/**
 * Get user information by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array|bool User data array or false if not found
 */
function get_user_by_id($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($result)) {
        return $row;
    }
    
    return false;
}

/**
 * Check if user has specific role
 * 
 * @param string $required_role Role to check for
 * @return bool True if user has required role
 */
function has_role($required_role) {
    if(!isset($_SESSION['role'])) {
        return false;
    }
    
    if($required_role == 'admin' && $_SESSION['role'] == 'admin') {
        return true;
    }
    
    if($required_role == 'staff' && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff')) {
        return true;
    }
    
    if($required_role == 'manager' && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'manager')) {
        return true;
    }
    
    if($required_role == $_SESSION['role']) {
        return true;
    }
    
    return false;
}

/**
 * Log user activity
 * 
 * @param string $activity_type Type of activity
 * @param string $description Description of activity
 * @return bool Success or failure
 */
function log_activity($activity_type, $description = '') {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $query = "INSERT INTO user_activity_logs (user_id, activity_type, description, ip_address) 
              VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $activity_type, $description, $ip_address);
    
    return mysqli_stmt_execute($stmt);
}

/**
 * Log asset history action - only define if not already defined
 */
if (!function_exists('log_asset_action')) {
    /**
     * Log action on an asset
     * 
     * @param mysqli $conn Database connection
     * @param int $asset_id Asset ID
     * @param string $action Action performed
     * @param int $user_id User who performed the action
     * @param string $notes Optional notes
     * @return bool True on success, false on failure
     */
    function log_asset_action($conn, $asset_id, $action, $user_id, $notes = '') {
        $sql = "INSERT INTO asset_history (asset_id, action, performed_by, notes) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isis", $asset_id, $action, $user_id, $notes);
        
        return mysqli_stmt_execute($stmt);
    }
}

/**
 * Generate a unique asset tag
 * 
 * @param mysqli $conn Database connection
 * @param string $prefix Optional prefix for the tag
 * @return string Unique asset tag
 */
function generate_asset_tag($conn, $prefix = 'AMS') {
    $year = date('Y');
    $unique = false;
    $tag = '';
    
    while(!$unique) {
        $random = mt_rand(100000, 999999);
        $tag = $prefix . '-' . $year . '-' . $random;
        
        // Check if tag already exists
        $sql = "SELECT asset_id FROM assets WHERE asset_tag = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $tag);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if(mysqli_stmt_num_rows($stmt) == 0) {
            $unique = true;
        }
    }
    
    return $tag;
}

/**
 * Get all permissions for a role
 * 
 * @param mysqli $conn Database connection
 * @param int $role_id The role ID
 * @return array Array of permission IDs
 */
function get_role_permissions($conn, $role_id) {
    $permissions = [];
    
    $query = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_id'];
    }
    
    return $permissions;
}

/**
 * Get asset status badge HTML
 * 
 * @param string $status Asset status
 * @return string HTML for status badge
 */
function get_status_badge($status) {
    $badge_class = '';
    switch($status) {
        case 'available': $badge_class = 'success'; break;
        case 'assigned': $badge_class = 'primary'; break;
        case 'under_repair': $badge_class = 'warning'; break;
        case 'disposed': $badge_class = 'secondary'; break;
        case 'lost': 
        case 'stolen': $badge_class = 'danger'; break;
        default: $badge_class = 'info';
    }
    
    $label = ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge badge-' . $badge_class . '">' . $label . '</span>';
}

/**
 * Format currency value
 * 
 * @param float $amount Amount to format
 * @return string Formatted currency string
 */
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Get category options for select dropdown
 * 
 * @param mysqli $conn Database connection
 * @param int $selected_id Optional currently selected category ID
 * @return string HTML options for select element
 */
function get_category_options($conn, $selected_id = null) {
    $output = '<option value="">-- Select Category --</option>';
    
    // Define default categories
    $default_categories = [
        'Computer Equipment',
        'Office Furniture',
        'Networking Equipment',
        'Mobile Devices',
        'Peripherals'
    ];
    
    // Check if each default category exists; if not, insert it
    foreach ($default_categories as $category_name) {
        $check_sql = "SELECT category_id FROM categories WHERE category_name = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $category_name);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) == 0) {
            // Category doesn't exist, insert it
            $insert_sql = "INSERT INTO categories (category_name) VALUES (?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "s", $category_name);
            mysqli_stmt_execute($insert_stmt);
        }
    }
    
    // Now get all categories from database
    $sql = "SELECT category_id, category_name FROM categories ORDER BY category_name";
    $result = mysqli_query($conn, $sql);
    
    while($row = mysqli_fetch_assoc($result)) {
        $selected = ($selected_id == $row['category_id']) ? 'selected' : '';
        $output .= '<option value="' . $row['category_id'] . '" ' . $selected . '>' . 
                  htmlspecialchars($row['category_name']) . '</option>';
    }
    
    return $output;
}

/**
 * Get location options for select dropdown
 * 
 * @param mysqli $conn Database connection
 * @param int $selected_id Optional currently selected location ID
 * @return string HTML options for select element
 */
function get_location_options($conn, $selected_id = null) {
    $output = '<option value="">-- Select Location --</option>';
    
    // Define default locations
    $default_locations = [
        ['building' => 'Main Building', 'room' => ''],
        ['building' => 'South Campus', 'room' => ''],
        ['building' => 'San Jose Branch', 'room' => '']
    ];
    
    // Check if each default location exists; if not, insert it
    foreach ($default_locations as $location) {
        $check_sql = "SELECT location_id FROM locations WHERE building = ? AND (room = ? OR (room IS NULL AND ? = ''))";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "sss", $location['building'], $location['room'], $location['room']);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) == 0) {
            // Location doesn't exist, insert it
            $insert_sql = "INSERT INTO locations (building, room) VALUES (?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            $room_value = empty($location['room']) ? NULL : $location['room'];
            mysqli_stmt_bind_param($insert_stmt, "ss", $location['building'], $room_value);
            mysqli_stmt_execute($insert_stmt);
        }
    }
    
    // Now get all locations from database
    $sql = "SELECT location_id, building, room FROM locations ORDER BY building, room";
    $result = mysqli_query($conn, $sql);
    
    while($row = mysqli_fetch_assoc($result)) {
        $location_name = htmlspecialchars($row['building']);
        if(!empty($row['room'])) {
            $location_name .= ' - ' . htmlspecialchars($row['room']);
        }
        
        $selected = ($selected_id == $row['location_id']) ? 'selected' : '';
        $output .= '<option value="' . $row['location_id'] . '" ' . $selected . '>' . 
                   $location_name . '</option>';
    }
    
    return $output;
}

/**
 * Get user options for select dropdown
 * 
 * @param mysqli $conn Database connection
 * @param int $selected_id Optional currently selected user ID
 * @return string HTML options for select element
 */
function get_user_options($conn, $selected_id = null) {
    $output = '<option value="">-- Select User --</option>';
    
    $sql = "SELECT user_id, full_name, username FROM users WHERE status = 'active' ORDER BY full_name";
    $result = mysqli_query($conn, $sql);
    
    while($row = mysqli_fetch_assoc($result)) {
        $user_name = htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['username']) . ')';
        
        $selected = ($selected_id == $row['user_id']) ? 'selected' : '';
        $output .= '<option value="' . $row['user_id'] . '" ' . $selected . '>' . 
                   $user_name . '</option>';
    }
    
    return $output;
}

/**
 * Generate navigation menu items based on user permissions
 * 
 * @return string HTML for navigation menu
 */
function generate_nav_menu() {
    $menu = '';
    
    // Dashboard - available to everyone
    $menu .= '<li class="nav-item">
                <a class="nav-link" href="' . get_base_url() . '/index.php">
                  <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
              </li>';
    
    // Inventory management
    if (has_permission('manage_assets')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/inventory/index.php">
                      <i class="fas fa-boxes"></i> Inventory
                    </a>
                  </li>';
    }
    
    // Receiving
    if (has_permission('manage_assets')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/receiving/index.php">
                      <i class="fas fa-truck-loading"></i> Receiving
                    </a>
                  </li>';
    }
    
    // Assignments
    if (has_permission('manage_assignments')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/assignments/index.php">
                      <i class="fas fa-tasks"></i> Assignments
                    </a>
                  </li>';
    }
    
    // Audits
    if (has_permission('conduct_audits')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/audits/index.php">
                      <i class="fas fa-clipboard-check"></i> Audits
                    </a>
                  </li>';
    }
    
    // Reports
    if (has_permission('generate_reports')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/reports/index.php">
                      <i class="fas fa-chart-bar"></i> Reports
                    </a>
                  </li>';
    }
    
    // Disposals
    if (has_permission('approve_disposals')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/disposals/index.php">
                      <i class="fas fa-trash-alt"></i> Disposals
                    </a>
                  </li>';
    }
    
    // Locations
    if (has_permission('manage_locations')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/locations/index.php">
                      <i class="fas fa-map-marker-alt"></i> Locations
                    </a>
                  </li>';
    }
    
    // Users - Admin only
    if (has_permission('manage_users')) {
        $menu .= '<li class="nav-item">
                    <a class="nav-link" href="' . get_base_url() . '/modules/users/index.php">
                      <i class="fas fa-users"></i> Users
                    </a>
                  </li>';
    }
    
    return $menu;
}

/**
 * Get application base URL
 * 
 * @return string Base URL
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove /includes or /modules/* from path to get base URL
    $base_path = preg_replace('/(\/includes|\/modules\/[^\/]+)\/.*$/', '', $path);
    
    return $protocol . $domain . $base_path;
}

/**
 * Handle AJAX requests for logging tag printing
 */
if(isset($_POST['action']) && $_POST['action'] == 'log_print') {
    // Check if user is logged in
    if(!isset($_SESSION['user_id'])) {
        die(json_encode(['success' => false, 'message' => 'Not authorized']));
    }
    
    // Check permission
    if(!has_permission('manage_assets')) {
        die(json_encode(['success' => false, 'message' => 'You do not have permission to perform this action']));
    }
    
    // Get asset ID from post data
    $asset_id = isset($_POST['asset_id']) ? intval($_POST['asset_id']) : 0;
    
    if($asset_id > 0) {
        // Call function based on what's available
        if (function_exists('log_action')) {
            log_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset tag printed');
        } else if (function_exists('log_asset_action')) {
            log_asset_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset tag printed');
        }
        die(json_encode(['success' => true]));
    }
    
    die(json_encode(['success' => false, 'message' => 'Invalid asset ID']));
}
?>