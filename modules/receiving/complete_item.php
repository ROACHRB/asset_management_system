<?php
// FILE PATH: asset_management_system/modules/receiving/complete_item.php
// Start with a session check before including header
session_start();
include_once "../../config/database.php";
include_once "../../includes/functions.php";

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Get item ID and delivery ID
$item_id = $_GET['id'] ?? 0;
$delivery_id = $_GET['delivery_id'] ?? 0;

// Initialize variables
$error = '';
$success = '';

// Process the item if ID is provided
if (!empty($item_id) && !empty($delivery_id)) {
    // Update the item status to 'stored' (or create a new status 'completed' in the database)
    $update_query = "UPDATE delivery_items SET status = 'stored' WHERE item_id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    
    if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
        // Log the activity
        $log_desc = "Item ID $item_id marked as completed";
        log_activity($_SESSION['user_id'], 'complete_item', $log_desc);
        
        // Redirect back to the process_items page with success message
        header("Location: process_items.php?id=$delivery_id&success=completed");
        exit;
    } else {
        $error = "Failed to complete the item. It may have already been processed.";
    }
} else {
    $error = "No item ID or delivery ID provided.";
}

// Include header AFTER all potential redirects
include_once "../../includes/header.php";
?>

<div class="container mt-4">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
        </div>
        <p>Redirecting back to the items list...</p>
        <script>
            setTimeout(function() { 
                window.location.href = "process_items.php?id=<?php echo $delivery_id; ?>"; 
            }, 3000);
        </script>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>