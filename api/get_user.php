<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ensure this is a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Include database connection - adjust this path to your existing config file
require_once '../config/database.php';  // Use your actual file name

// Check for authorization header
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authorization required']);
    exit();
}

// Extract token from header
$auth_header = $_SERVER['HTTP_AUTHORIZATION'];
$token = null;

// Check if token format is "Bearer {token}"
if (strpos($auth_header, 'Bearer ') === 0) {
    $token = substr($auth_header, 7);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid authorization format']);
    exit();
}

// In a real application, you would validate the token against a stored token
// For this example, we'll assume any token is valid

try {
    // Get user ID from Authorization header (in a real app, you'd extract this from a JWT token)
    // For demo purposes, we'll just get the first active admin user
    $stmt = $conn->prepare("SELECT * FROM users WHERE status = 'active' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Remove password from user data before sending response
    unset($user['password']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}