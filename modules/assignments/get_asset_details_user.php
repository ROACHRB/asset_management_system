<?php
// FILE: D:\xampp\htdocs\asset_management_system\modules\assignments\get_my_asset_details.php
// Description: AJAX handler to return asset details for the user view

// Include necessary files
require_once "../../config/database.php";
require_once "../../includes/functions.php";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo '<div class="alert alert-danger">You must be logged in to view asset details.</div>';
    exit;
}

// Get the asset ID and current user ID
$asset_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION["user_id"];

// Validate asset ID
if ($asset_id <= 0) {
    echo '<div class="alert alert-danger">Invalid asset ID.</div>';
    exit;
}

// Verify this asset is assigned to the current user
$check_query = "SELECT COUNT(*) as count 
                FROM asset_assignments 
                WHERE asset_id = ? 
                AND assigned_to = ? 
                AND assignment_status = 'assigned'";
                
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "ii", $asset_id, $user_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$row = mysqli_fetch_assoc($check_result);

if ($row['count'] == 0) {
    echo '<div class="alert alert-danger">You do not have permission to view this asset.</div>';
    exit;
}

// Get detailed asset information
$query = "SELECT a.*, c.category_name, l.building, l.room, 
                 aa.assignment_id, aa.assignment_date, aa.expected_return_date, 
                 aa.assignment_status, aa.notes as assignment_notes,
                 u.full_name as assigned_by_name
          FROM assets a
          JOIN asset_assignments aa ON a.asset_id = aa.asset_id
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN locations l ON a.location_id = l.location_id
          LEFT JOIN users u ON aa.assigned_by = u.user_id
          WHERE a.asset_id = ?
          AND aa.assigned_to = ?
          AND aa.assignment_status = 'assigned'";
          
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $asset_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-danger">Asset not found or not assigned to you.</div>';
    exit;
}

$asset = mysqli_fetch_assoc($result);

// Get asset history (limited to recent entries)
$history_query = "SELECT ah.*, u.full_name
                  FROM asset_history ah
                  LEFT JOIN users u ON ah.performed_by = u.user_id
                  WHERE ah.asset_id = ?
                  ORDER BY ah.action_date DESC
                  LIMIT 5";
                  
$history_stmt = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($history_stmt, "i", $asset_id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);
?>

<div class="row">
    <div class="col-md-6">
        <!-- Asset Information -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold">Asset Information</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($asset['qr_code']) || !empty($asset['barcode'])): ?>
                <div class="mb-3 text-center">
                    <?php if (!empty($asset['qr_code'])): ?>
                    <img src="../../qrcode.php?text=<?php echo urlencode($asset['qr_code']); ?>" 
                         alt="QR Code" class="img-fluid mb-2" style="max-width: 150px;">
                    <div class="small text-muted mb-3">Asset QR Code</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <table class="table table-sm">
                    <tr>
                        <th width="35%">Asset Tag:</th>
                        <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($asset['category_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo !empty($asset['description']) ? htmlspecialchars($asset['description']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Serial Number:</th>
                        <td><?php echo !empty($asset['serial_number']) ? htmlspecialchars($asset['serial_number']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td><?php echo !empty($asset['model']) ? htmlspecialchars($asset['model']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Manufacturer:</th>
                        <td><?php echo !empty($asset['manufacturer']) ? htmlspecialchars($asset['manufacturer']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge badge-primary">
                                <?php echo ucfirst(htmlspecialchars($asset['status'])); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Condition:</th>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($asset['condition_status'] == 'new' ? 'success' : 
                                     ($asset['condition_status'] == 'good' ? 'primary' : 
                                     ($asset['condition_status'] == 'fair' ? 'info' : 
                                     ($asset['condition_status'] == 'poor' ? 'warning' : 'danger')))); 
                            ?>">
                                <?php echo ucfirst($asset['condition_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if (!empty($asset['purchase_date'])): ?>
                    <tr>
                        <th>Purchase Date:</th>
                        <td><?php echo date('M d, Y', strtotime($asset['purchase_date'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($asset['warranty_expiry'])): ?>
                    <tr>
                        <th>Warranty Expires:</th>
                        <td>
                            <?php 
                                $warranty_date = new DateTime($asset['warranty_expiry']);
                                $today = new DateTime();
                                $is_expired = $warranty_date < $today;
                            ?>
                            <span class="<?php echo $is_expired ? 'text-danger' : ''; ?>">
                                <?php echo date('M d, Y', strtotime($asset['warranty_expiry'])); ?>
                                <?php if ($is_expired): ?>
                                    <span class="badge badge-danger ml-1">Expired</span>
                                <?php else: ?>
                                    <span class="badge badge-success ml-1">Active</span>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- Assignment Details -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h6 class="m-0 font-weight-bold">Assignment Details</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Assigned Date:</th>
                        <td><?php echo date('M d, Y', strtotime($asset['assignment_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Expected Return:</th>
                        <td>
                            <?php if (!empty($asset['expected_return_date'])): ?>
                                <?php 
                                    $return_date = new DateTime($asset['expected_return_date']);
                                    $today = new DateTime();
                                    $days_diff = $today->diff($return_date)->days;
                                    $is_overdue = $return_date < $today;
                                    $is_soon = $days_diff <= 7 && $return_date >= $today;
                                ?>
                                <span class="<?php echo $is_overdue ? 'text-danger font-weight-bold' : ($is_soon ? 'text-warning' : ''); ?>">
                                    <?php echo date('M d, Y', strtotime($asset['expected_return_date'])); ?>
                                    
                                    <?php if ($is_overdue): ?>
                                        <span class="badge badge-danger ml-1">Overdue</span>
                                    <?php elseif ($is_soon): ?>
                                        <span class="badge badge-warning ml-1">Due Soon</span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">Permanent Assignment</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Assigned By:</th>
                        <td><?php echo htmlspecialchars($asset['assigned_by_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Location:</th>
                        <td>
                            <?php
                            $location_parts = [];
                            if (!empty($asset['building'])) {
                                $location_parts[] = htmlspecialchars($asset['building']);
                            }
                            if (!empty($asset['room'])) {
                                $location_parts[] = 'Room ' . htmlspecialchars($asset['room']);
                            }
                            echo !empty($location_parts) ? implode(', ', $location_parts) : 'N/A';
                            ?>
                        </td>
                    </tr>
                    <?php if (!empty($asset['assignment_notes'])): ?>
                    <tr>
                        <th>Assignment Notes:</th>
                        <td><?php echo htmlspecialchars($asset['assignment_notes']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <!-- Asset Care Instructions -->
                <div class="alert alert-info mt-3 mb-0">
                    <h6 class="font-weight-bold"><i class="fas fa-info-circle mr-2"></i>Asset Care Instructions</h6>
                    <ul class="mb-0 pl-4">
                        <li>Please handle this asset with care and follow proper usage guidelines</li>
                        <li>Report any issues or damage to IT immediately</li>
                        <li>Do not install unauthorized software or modify hardware</li>
                        <?php if (!empty($asset['expected_return_date'])): ?>
                        <li>Return this asset by its due date or request an extension if needed</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h6 class="m-0 font-weight-bold">Recent Activity</h6>
            </div>
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($history_result) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($history = mysqli_fetch_assoc($history_result)): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php 
                                            $action = str_replace('_', ' ', $history['action']);
                                            echo ucfirst($action); 
                                        ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($history['action_date'])); ?>
                                    </small>
                                </div>
                                <?php if (!empty($history['notes'])): ?>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($history['notes']); ?></p>
                                <?php endif; ?>
                                <small class="text-primary">By: <?php echo htmlspecialchars($history['full_name']); ?></small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-center text-muted">No activity records found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>