<?php
// FILE: D:\xampp\htdocs\asset_management_system\modules\assignments\request_return.php
// Description: Page for users to request a return for an assigned asset

// Add output buffering to avoid "headers already sent" errors
ob_start();

// Include header
include_once "../../includes/header.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    $_SESSION['error'] = "You must be logged in to request a return.";
    echo '<script>window.location.href = "../../login.php";</script>';
    exit;
}

// Get asset ID from URL
$asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION["user_id"];

// Initialize variables
$error = $success = "";
$asset_info = null;

// Check if valid asset ID was provided
if ($asset_id <= 0) {
    $error = "Invalid asset ID.";
} else {
    // Check if this asset is actually assigned to the current user
    $check_query = "SELECT aa.assignment_id, a.asset_id, a.asset_tag, a.asset_name, 
                           c.category_name, a.model, a.serial_number,
                           aa.assignment_date, aa.expected_return_date, u.full_name as assigned_by
                    FROM asset_assignments aa
                    JOIN assets a ON aa.asset_id = a.asset_id
                    LEFT JOIN categories c ON a.category_id = c.category_id
                    LEFT JOIN users u ON aa.assigned_by = u.user_id
                    WHERE a.asset_id = ? 
                    AND aa.assigned_to = ?
                    AND aa.assignment_status = 'assigned'
                    AND a.status NOT IN ('pending_disposal', 'disposed')";
                    
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $asset_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 0) {
        $error = "This asset is not assigned to you or doesn't exist.";
    } else {
        $asset_info = mysqli_fetch_assoc($result);
    }
}

// Process return request form
if ($_SERVER["REQUEST_METHOD"] == "POST" && $asset_info) {
    $return_notes = trim($_POST["return_notes"] ?? '');
    $return_condition = trim($_POST["return_condition"] ?? '');
    
    if (empty($return_condition)) {
        $error = "Please select the condition of the asset.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Create a return request
            $insert_sql = "INSERT INTO asset_return_requests (
                              asset_id, assignment_id, requested_by, 
                              request_date, return_condition, notes, status
                          ) VALUES (?, ?, ?, NOW(), ?, ?, 'pending')";
                          
            $stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt, "iiiss", 
                                  $asset_id, 
                                  $asset_info['assignment_id'], 
                                  $user_id, 
                                  $return_condition, 
                                  $return_notes);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create return request: " . mysqli_error($conn));
            }
            
            // Log the action in asset history
            $history_sql = "INSERT INTO asset_history (
                           asset_id, action, action_date, performed_by, notes
                           ) VALUES (?, 'return_requested', NOW(), ?, ?)";
            
            $stmt = mysqli_prepare($conn, $history_sql);
            $history_notes = "User requested to return asset. Condition: $return_condition. Notes: $return_notes";
            mysqli_stmt_bind_param($stmt, "iis", $asset_id, $user_id, $history_notes);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to add asset history: " . mysqli_error($conn));
            }
            
            // Log user activity
            $activity_sql = "INSERT INTO user_activity_logs (
                           user_id, activity_type, description, ip_address, created_at
                           ) VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $activity_sql);
            $activity_type = 'request_return';
            $activity_desc = "Requested return of asset " . $asset_info['asset_tag'] . " (" . $asset_info['asset_name'] . ")";
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            mysqli_stmt_bind_param($stmt, "isss", $user_id, $activity_type, $activity_desc, $ip_address);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to log activity: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Set success message
            $success = "Return request submitted successfully. An administrator will process your request.";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Check if the return_requests table exists, create it if it doesn't
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'asset_return_requests'");
if(mysqli_num_rows($table_check) == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE asset_return_requests (
                            request_id INT(11) NOT NULL AUTO_INCREMENT,
                            asset_id INT(11) NOT NULL,
                            assignment_id INT(11) NOT NULL,
                            requested_by INT(11) NOT NULL,
                            request_date DATETIME NOT NULL,
                            processed_by INT(11) NULL DEFAULT NULL,
                            processed_date DATETIME NULL DEFAULT NULL,
                            return_condition ENUM('excellent','good','fair','damaged','incomplete') NOT NULL,
                            notes TEXT NULL DEFAULT NULL,
                            status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
                            PRIMARY KEY (request_id),
                            FOREIGN KEY (asset_id) REFERENCES assets(asset_id),
                            FOREIGN KEY (assignment_id) REFERENCES asset_assignments(assignment_id),
                            FOREIGN KEY (requested_by) REFERENCES users(user_id),
                            FOREIGN KEY (processed_by) REFERENCES users(user_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                        
    mysqli_query($conn, $create_table_sql);
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-undo mr-2"></i>Request Asset Return</h1>
        <p class="text-muted">Submit a request to return an asset assigned to you</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="my_assets.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to My Assets
        </a>
    </div>
</div>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
    <div class="mt-2">
        <a href="my_assets.php" class="btn btn-primary">
            <i class="fas fa-arrow-left mr-2"></i>Return to My Assets
        </a>
    </div>
</div>
<?php else: ?>

<?php if($asset_info): ?>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-undo mr-1"></i>Return Request Form
            </div>
            <div class="card-body">
                <form method="post" id="returnForm">
                    <div class="form-group">
                        <label for="return_condition" class="required-field">Asset Condition</label>
                        <select class="form-control" id="return_condition" name="return_condition" required>
                            <option value="">-- Select Condition --</option>
                            <option value="excellent">Excellent - Like new</option>
                            <option value="good">Good - Minor wear and tear</option>
                            <option value="fair">Fair - Visible wear but functional</option>
                            <option value="damaged">Damaged - Has issues but usable</option>
                            <option value="incomplete">Incomplete - Missing parts/accessories</option>
                        </select>
                        <small class="form-text text-muted">
                            Please honestly assess the current condition of the asset.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="return_notes">Additional Notes</label>
                        <textarea class="form-control" id="return_notes" name="return_notes" rows="4" 
                                  placeholder="Provide any additional details about the asset's condition or specific notes for the administrator."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i>
                        Your return request will be reviewed by an administrator. You will be notified when it is processed.
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-undo mr-1"></i>Submit Return Request
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Asset Information
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 35%;">Asset Tag:</th>
                        <td><?php echo htmlspecialchars($asset_info['asset_tag']); ?></td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td><?php echo htmlspecialchars($asset_info['asset_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($asset_info['category_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td><?php echo htmlspecialchars($asset_info['model'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Serial Number:</th>
                        <td><?php echo htmlspecialchars($asset_info['serial_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Assigned Date:</th>
                        <td><?php echo date('F d, Y', strtotime($asset_info['assignment_date'])); ?></td>
                    </tr>
                    <?php if($asset_info['expected_return_date']): ?>
                    <tr>
                        <th>Expected Return:</th>
                        <td>
                            <?php 
                                $return_date = new DateTime($asset_info['expected_return_date']);
                                $today = new DateTime();
                                $is_overdue = $return_date < $today;
                            ?>
                            <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                <?php echo date('F d, Y', strtotime($asset_info['expected_return_date'])); ?>
                                <?php if($is_overdue): ?>
                                    <span class="badge badge-danger">Overdue</span>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Assigned By:</th>
                        <td><?php echo htmlspecialchars($asset_info['assigned_by']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-clipboard-list mr-1"></i>Return Procedure
            </div>
            <div class="card-body">
                <ol>
                    <li>Submit this return request form.</li>
                    <li>An administrator will review your request.</li>
                    <li>You'll be contacted with instructions for physically returning the asset.</li>
                    <li>Return the asset as instructed, including all accessories and documentation.</li>
                    <li>The administrator will inspect the asset and finalize the return.</li>
                </ol>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong>Important:</strong> The asset remains your responsibility until the return process is completed and formally acknowledged by an administrator.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    $("#returnForm").validate({
        rules: {
            return_condition: "required"
        },
        messages: {
            return_condition: "Please select the condition of the asset"
        },
        errorElement: "div",
        errorClass: "invalid-feedback",
        highlight: function(element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function(element) {
            $(element).removeClass("is-invalid");
        }
    });
});
</script>
<?php endif; ?>
<?php endif; ?>

<?php
// Include footer
include_once "../../includes/footer.php";
// Flush output buffer
ob_end_flush();
?>