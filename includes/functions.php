<?php
// Include database configuration if not already included
if(!defined('DB_SERVER')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/asset_management_system/config/database.php';
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
    
    return false;
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
 * Handle AJAX requests for logging tag printing
 */
if(isset($_POST['action']) && $_POST['action'] == 'log_print') {
    // Check if user is logged in
    session_start();
    if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        die(json_encode(['success' => false, 'message' => 'Not authorized']));
    }
    
    // Get asset ID from post data
    $asset_id = isset($_POST['asset_id']) ? intval($_POST['asset_id']) : 0;
    
    if($asset_id > 0) {
        // Log the print action
        log_action($conn, $asset_id, 'updated', $_SESSION['user_id'], 'Asset tag printed');
        die(json_encode(['success' => true]));
    }
    
    die(json_encode(['success' => false, 'message' => 'Invalid asset ID']));
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
    return '$' . number_format($amount, 2);
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