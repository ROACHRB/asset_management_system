<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Include database connection - Adjust this path to your actual config file
require_once '../config/database.php';  // Prevents duplicate inclusion


// Get JSON data from request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate input
if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

// Sanitize input (prevent SQL injection)
function sanitize_input($conn, $input) {
    return htmlspecialchars(strip_tags(mysqli_real_escape_string($conn, $input)));
}

$username = sanitize_input($conn, $data['username']);
$password = $data['password'];  // Password should not be altered before verification

try {
    // Prepare SQL statement to get user data
    $stmt = $conn->prepare("SELECT user_id, username, password, status FROM users WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or user is inactive']);
        exit();
    }

    $user = $result->fetch_assoc();

    // Verify password
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        exit();
    }

    // Update last login timestamp
    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $updateStmt->bind_param("i", $user['user_id']);
    $updateStmt->execute();

    // Log login activity
    $logStmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, activity_type, ip_address) VALUES (?, 'login', ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $logStmt->bind_param("is", $user['user_id'], $ip);
    $logStmt->execute();

    // Generate JSON Web Token (JWT) or simple token
    $token = bin2hex(random_bytes(32));  // Replace with JWT if needed

    // Remove password from user data before sending response
    unset($user['password']);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => $user
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
