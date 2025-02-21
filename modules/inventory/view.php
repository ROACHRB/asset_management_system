<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\view.php
// Include header
include_once "../../includes/header.php";

// Check if asset ID is provided
if(!isset($_GET['id']) || empty(trim($_GET['id']))) {
    // Redirect to the inventory page
    header("location: index.php");
    exit;
}

// Get asset ID from URL
$asset_id = trim($_GET['id']);

// Fetch asset details
$sql = "SELECT a.*, c.category_name, l.building, l.room, u.full_name as created_by_name
        FROM assets a
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN users u ON a.created_by = u.user_id
        WHERE a.asset_id = ?";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $asset_id);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $asset = mysqli_fetch_assoc($result);
        } else {
            // Asset not found
            header("location: index.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo "Oops! Something went wrong. Please try again later.";
    exit;
}

// Get current assignment if any
$assignment_sql = "SELECT aa.*, u.full_name as assigned_to_name, u2.full_name as assigned_by_name
                  FROM asset_assignments aa
                  LEFT JOIN users u ON aa.assigned_to = u.user_id
                  LEFT JOIN users u2 ON aa.assigned_by = u2.user_id
                  WHERE aa.asset_id = ? AND aa.assignment_status = 'assigned'";
$assignment_stmt = mysqli_prepare($conn, $assignment_sql);
mysqli_stmt_bind_param($assignment_stmt, "i", $asset_id);
mysqli_stmt_execute($assignment_stmt);
$assignment_result = mysqli_stmt_get_result($assignment_stmt);
$current_assignment = false;
if(mysqli_num_rows($assignment_result) > 0) {
    $current_assignment = mysqli_fetch_assoc($assignment_result);
}
mysqli_stmt_close($assignment_stmt);

// Get asset history
$history_sql = "SELECT ah.*, u.full_name 
               FROM asset_history ah
               LEFT JOIN users u ON ah.performed_by = u.user_id
               WHERE ah.asset_id = ?
               ORDER BY ah.action_date DESC";
$history_stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($history_stmt, "i", $asset_id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);
?>

<div class="row mb-4">
    <div class="col-md-7">
        <h1>
            <i class="fas fa-info-circle mr-2"></i>Asset Details
            <?php if(!empty($asset['asset_tag'])): ?>
                <small class="text-muted">(<?php echo htmlspecialchars($asset['asset_tag']); ?>)</small>
            <?php endif; ?>
        </h1>
        <p class="text-muted">View complete information about this asset</p>
    </div>
    <div class="col-md-5 text-right">
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back to List
            </a>
            <a href="edit.php?id=<?php echo $asset_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit mr-1"></i>Edit Asset
            </a>
            <?php if(empty($asset['asset_tag']) || empty($asset['qr_code'])): ?>
                <a href="../tagging/generate_tag.php?id=<?php echo $asset_id; ?>" class="btn btn-warning">
                    <i class="fas fa-tag mr-1"></i>Generate Tag
                </a>
            <?php else: ?>
                <a href="../tagging/print_tag.php?id=<?php echo $asset_id; ?>" class="btn btn-info">
                    <i class="fas fa-print mr-1"></i>Print Tag
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Left Column - Asset Information -->
    <div class="col-lg-8">
        <!-- General Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-clipboard-list mr-1"></i>
                General Information
                <span class="badge badge-<?php 
                    echo ($asset['status'] == 'available' ? 'success' : 
                         ($asset['status'] == 'assigned' ? 'primary' : 
                         ($asset['status'] == 'under_repair' ? 'warning' : 
                         ($asset['status'] == 'lost' || $asset['status'] == 'stolen' ? 'danger' : 'secondary'))));
                ?> float-right">
                    <?php echo ucfirst(str_replace('_', ' ', $asset['status'])); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Asset Name:</strong><br> <?php echo htmlspecialchars($asset['asset_name']); ?></p>
                        <p><strong>Category:</strong><br> <?php echo htmlspecialchars($asset['category_name'] ?? 'Uncategorized'); ?></p>
                        <p><strong>Description:</strong><br> <?php echo !empty($asset['description']) ? htmlspecialchars($asset['description']) : 'No description available'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p>
                            <strong>Condition:</strong><br>
                            <span class="badge badge-<?php 
                                echo ($asset['condition_status'] == 'new' ? 'success' : 
                                     ($asset['condition_status'] == 'good' ? 'info' : 
                                     ($asset['condition_status'] == 'fair' ? 'primary' : 
                                     ($asset['condition_status'] == 'poor' ? 'warning' : 'danger'))));
                            ?> p-2">
                                <?php echo ucfirst($asset['condition_status']); ?>
                            </span>
                        </p>
                        <p><strong>Location:</strong><br> 
                            <?php 
                            if(!empty($asset['building'])) {
                                echo htmlspecialchars($asset['building']);
                                echo !empty($asset['room']) ? ' - ' . htmlspecialchars($asset['room']) : '';
                            } else {
                                echo 'Not assigned';
                            }
                            ?>
                        </p>
                        <p><strong>Created By:</strong><br> <?php echo htmlspecialchars($asset['created_by_name'] ?? 'Unknown'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Technical Details Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-cogs mr-1"></i>
                Technical Details
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Serial Number:</strong><br> <?php echo !empty($asset['serial_number']) ? htmlspecialchars($asset['serial_number']) : 'N/A'; ?></p>
                        <p><strong>Model:</strong><br> <?php echo !empty($asset['model']) ? htmlspecialchars($asset['model']) : 'N/A'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Manufacturer:</strong><br> <?php echo !empty($asset['manufacturer']) ? htmlspecialchars($asset['manufacturer']) : 'N/A'; ?></p>
                        <p><strong>Last Updated:</strong><br> <?php echo date('M d, Y H:i', strtotime($asset['updated_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Purchase Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-shopping-cart mr-1"></i>
                Purchase Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Purchase Date:</strong><br> <?php echo !empty($asset['purchase_date']) ? date('M d, Y', strtotime($asset['purchase_date'])) : 'N/A'; ?></p>
                        <p><strong>Purchase Cost:</strong><br> <?php echo !empty($asset['purchase_cost']) ? '$' . number_format($asset['purchase_cost'], 2) : 'N/A'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Supplier:</strong><br> <?php echo !empty($asset['supplier']) ? htmlspecialchars($asset['supplier']) : 'N/A'; ?></p>
                        <p><strong>Warranty Expiry:</strong><br>
                            <?php 
                            if(!empty($asset['warranty_expiry'])) {
                                $warranty_date = strtotime($asset['warranty_expiry']);
                                $current_date = time();
                                $expired = ($warranty_date < $current_date);
                                
                                echo date('M d, Y', $warranty_date);
                                echo ' <span class="badge badge-' . ($expired ? 'danger' : 'success') . '">';
                                echo $expired ? 'Expired' : 'Active';
                                echo '</span>';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Assignment Information Card (if assigned) -->
        <?php if($current_assignment): ?>
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-user-check mr-1"></i>
                Current Assignment
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Assigned To:</strong><br> <?php echo htmlspecialchars($current_assignment['assigned_to_name']); ?></p>
                        <p><strong>Assigned By:</strong><br> <?php echo htmlspecialchars($current_assignment['assigned_by_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Assignment Date:</strong><br> <?php echo date('M d, Y', strtotime($current_assignment['assignment_date'])); ?></p>
                        <p><strong>Expected Return:</strong><br> 
                            <?php 
                            if(!empty($current_assignment['expected_return_date'])) {
                                echo date('M d, Y', strtotime($current_assignment['expected_return_date']));
                                
                                // Check if overdue
                                $return_date = strtotime($current_assignment['expected_return_date']);
                                $current_date = time();
                                if($return_date < $current_date) {
                                    echo ' <span class="badge badge-danger">Overdue</span>';
                                }
                            } else {
                                echo 'Permanent Assignment';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <div class="mt-3">
                    <p><strong>Notes:</strong><br> <?php echo !empty($current_assignment['notes']) ? nl2br(htmlspecialchars($current_assignment['notes'])) : 'No notes available'; ?></p>
                </div>
            </div>
            <div class="card-footer">
                <a href="../assignments/return.php?id=<?php echo $current_assignment['assignment_id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-undo mr-1"></i>Process Return
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column - Tags, QR Code, and History -->
    <div class="col-lg-4">
        <!-- Asset Tags Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-tags mr-1"></i>
                Asset Identification
            </div>
            <div class="card-body">
                <!-- Asset Tag -->
                <?php if(!empty($asset['asset_tag'])): ?>
                <div class="mb-4">
                    <h6>Asset Tag</h6>
                    <div class="asset-tag w-100 p-3">
                        <h4 class="mb-0"><?php echo htmlspecialchars($asset['asset_tag']); ?></h4>
                    </div>
                </div>
                
                <!-- QR Code (Simplified for demo) -->
                <div class="mb-4 text-center">
                    <h6>QR Code</h6>
                    <div class="qr-code-container">
                        <!-- In a real system, you'd generate an actual QR code image -->
                        <div style="width: 150px; height: 150px; background-color: #f8f9fa; border: 1px solid #ddd; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                            <span class="text-muted">QR Code</span>
                        </div>
                        <small class="text-muted d-block mt-2">Scan for asset details</small>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="text-center">
                    <a href="../tagging/print_tag.php?id=<?php echo $asset_id; ?>" class="btn btn-primary">
                        <i class="fas fa-print mr-1"></i>Print Tag
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    This asset has not been tagged yet.
                </div>
                <div class="text-center">
                    <a href="../tagging/generate_tag.php?id=<?php echo $asset_id; ?>" class="btn btn-warning">
                        <i class="fas fa-tag mr-1"></i>Generate Tag
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-tools mr-1"></i>
                Quick Actions
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if($asset['status'] == 'available'): ?>
                    <a href="../assignments/assign.php?id=<?php echo $asset_id; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-user-plus mr-1"></i>Assign Asset</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Assign this asset to a user</small>
                    </a>
                    <?php endif; ?>
                    
                    <a href="edit.php?id=<?php echo $asset_id; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-edit mr-1"></i>Edit Asset</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Update asset information</small>
                    </a>
                    
                    <?php if($asset['status'] != 'under_repair'): ?>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-wrench mr-1"></i>Mark for Repair</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Record that asset needs maintenance</small>
                    </a>
                    <?php endif; ?>
                    
                    <?php if($asset['status'] != 'disposed'): ?>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-trash-alt mr-1"></i>Dispose Asset</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Record asset disposal</small>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Asset History Timeline -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-history mr-1"></i>
        Asset History
    </div>
    <div class="card-body">
        <div class="timeline">
            <?php 
            if(mysqli_num_rows($history_result) > 0) {
                while($history = mysqli_fetch_assoc($history_result)) {
                    // Determine icon based on action
                    $icon_class = '';
                    switch($history['action']) {
                        case 'created': $icon_class = 'fa-plus-circle text-success'; break;
                        case 'updated': $icon_class = 'fa-edit text-info'; break;
                        case 'assigned': $icon_class = 'fa-user-check text-primary'; break;
                        case 'returned': $icon_class = 'fa-undo text-secondary'; break;
                        case 'transferred': $icon_class = 'fa-exchange-alt text-warning'; break;
                        case 'disposed': $icon_class = 'fa-trash-alt text-danger'; break;
                        default: $icon_class = 'fa-circle';
                    }
            ?>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas <?php echo $icon_class; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-date"><?php echo date('M d, Y H:i', strtotime($history['action_date'])); ?></div>
                        <h6><?php echo ucfirst($history['action']); ?></h6>
                        <p><?php echo htmlspecialchars($history['notes']); ?></p>
                        <small>By: <?php echo htmlspecialchars($history['full_name'] ?? 'Unknown'); ?></small>
                    </div>
                </div>
            <?php 
                }
            } else {
                echo '<p class="text-center text-muted">No history records found</p>';
            }
            ?>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 20px;
    width: 2px;
    background: #ddd;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-left: 50px;
}
.timeline-icon {
    position: absolute;
    left: 10px;
    width: 20px;
    height: 20px;
    text-align: center;
    z-index: 1;
}
.timeline-content {
    padding: 15px;
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.timeline-date {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 5px;
}
</style>

<?php
// Include footer
include_once "../../includes/footer.php";
?>