<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\assignments\view.php
// Include header
include_once "../../includes/header.php";

// Check if assignment ID is provided
if(!isset($_GET['id']) || empty(trim($_GET['id']))) {
    // Redirect to the assignments page
    header("location: index.php");
    exit;
}

// Get assignment ID from URL
$assignment_id = trim($_GET['id']);

// Fetch assignment details
$sql = "SELECT aa.*, a.asset_id, a.asset_name, a.asset_tag, a.serial_number, 
               a.status as asset_status, a.condition_status,
               c.category_name, l.building, l.room,
               u1.full_name as assigned_to_name, u1.email as assigned_to_email, 
               u1.department as department_id, d.department_name,
               u2.full_name as assigned_by_name
        FROM asset_assignments aa
        JOIN assets a ON aa.asset_id = a.asset_id
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        JOIN users u1 ON aa.assigned_to = u1.user_id
        LEFT JOIN departments d ON u1.department = d.department_id
        JOIN users u2 ON aa.assigned_by = u2.user_id
        WHERE aa.assignment_id = ?";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $assignment_id);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $assignment = mysqli_fetch_assoc($result);
        } else {
            // Assignment not found
            $_SESSION['error_message'] = "Assignment not found.";
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

// Determine status
$status = $assignment['assignment_status'];
if($status == 'assigned' && !empty($assignment['expected_return_date']) && 
   strtotime($assignment['expected_return_date']) < time()) {
    $status = 'overdue';
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>
            <i class="fas fa-clipboard mr-2"></i>Assignment Details
            <small class="text-muted">(#<?php echo $assignment_id; ?>)</small>
        </h1>
        <p class="text-muted">View assignment information and history</p>
    </div>
    <div class="col-md-4 text-right">
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back to List
            </a>
            
            <?php if($assignment['assignment_status'] == 'assigned'): ?>
                <a href="return.php?id=<?php echo $assignment_id; ?>" class="btn btn-success">
                    <i class="fas fa-undo-alt mr-1"></i>Process Return
                </a>
                
                <a href="edit.php?id=<?php echo $assignment_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit mr-1"></i>Edit Assignment
                </a>
            <?php endif; ?>
            
            <a href="print.php?id=<?php echo $assignment_id; ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print mr-1"></i>Print
            </a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Left Column - Assignment Information -->
    <div class="col-lg-8">
        <!-- Status Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>
                Assignment Status
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <?php
                        $status_icon = '';
                        $status_color = '';
                        switch($status) {
                            case 'assigned':
                                $status_icon = 'fa-user-check';
                                $status_color = 'primary';
                                break;
                            case 'returned':
                                $status_icon = 'fa-undo-alt';
                                $status_color = 'success';
                                break;
                            case 'overdue':
                                $status_icon = 'fa-exclamation-triangle';
                                $status_color = 'danger';
                                break;
                            default:
                                $status_icon = 'fa-question-circle';
                                $status_color = 'secondary';
                        }
                        ?>
                        <div class="display-1 text-<?php echo $status_color; ?>">
                            <i class="fas <?php echo $status_icon; ?>"></i>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h4 class="text-<?php echo $status_color; ?>">
                            <?php 
                            echo ucfirst($status);
                            if($status == 'overdue') {
                                $days_overdue = floor((time() - strtotime($assignment['expected_return_date'])) / 86400);
                                echo ' (' . $days_overdue . ' days)';
                            }
                            ?>
                        </h4>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Assignment Date:</strong></p>
                                <p><?php echo date('M d, Y', strtotime($assignment['assignment_date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if($assignment['assignment_status'] == 'returned'): ?>
                                    <p class="mb-1"><strong>Return Date:</strong></p>
                                    <p><?php echo date('M d, Y', strtotime($assignment['actual_return_date'])); ?></p>
                                <?php else: ?>
                                    <p class="mb-1"><strong>Expected Return:</strong></p>
                                    <p>
                                        <?php 
                                        if(!empty($assignment['expected_return_date'])) {
                                            echo date('M d, Y', strtotime($assignment['expected_return_date']));
                                        } else {
                                            echo '<span class="text-muted">Permanent Assignment</span>';
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Asset Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-box mr-1"></i>
                Asset Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Asset Name:</strong><br> 
                            <?php echo htmlspecialchars($assignment['asset_name']); ?>
                        </p>
                        
                        <?php if(!empty($assignment['asset_tag'])): ?>
                            <p><strong>Asset Tag:</strong><br> 
                                <?php echo htmlspecialchars($assignment['asset_tag']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if(!empty($assignment['serial_number'])): ?>
                            <p><strong>Serial Number:</strong><br> 
                                <?php echo htmlspecialchars($assignment['serial_number']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <p><strong>Category:</strong><br> 
                            <?php echo htmlspecialchars($assignment['category_name'] ?? 'Uncategorized'); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Current Status:</strong><br>
                            <span class="badge badge-<?php 
                                echo ($assignment['asset_status'] == 'available' ? 'success' : 
                                    ($assignment['asset_status'] == 'assigned' ? 'primary' : 
                                    ($assignment['asset_status'] == 'under_repair' ? 'warning' : 
                                    'secondary')));
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $assignment['asset_status'])); ?>
                            </span>
                        </p>
                        
                        <p><strong>Condition:</strong><br>
                            <span class="badge badge-<?php 
                                echo ($assignment['condition_status'] == 'new' ? 'success' : 
                                    ($assignment['condition_status'] == 'good' ? 'info' : 
                                    ($assignment['condition_status'] == 'fair' ? 'primary' : 
                                    ($assignment['condition_status'] == 'poor' ? 'warning' : 
                                    'danger'))));
                            ?>">
                                <?php echo ucfirst($assignment['condition_status']); ?>
                            </span>
                        </p>
                        
                        <p><strong>Location:</strong><br>
                            <?php 
                            if(!empty($assignment['building'])) {
                                echo htmlspecialchars($assignment['building']);
                                if(!empty($assignment['room'])) {
                                    echo ' - ' . htmlspecialchars($assignment['room']);
                                }
                            } else {
                                echo '<span class="text-muted">Not specified</span>';
                            }
                            ?>
                        </p>
                        
                        <p>
                            <a href="../inventory/view.php?id=<?php echo $assignment['asset_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt mr-1"></i>View Asset Details
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Assignment Notes -->
        <?php if(!empty($assignment['notes'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-sticky-note mr-1"></i>
                Assignment Notes
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($assignment['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column - User Information -->
    <div class="col-lg-4">
        <!-- Assigned To Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-user mr-1"></i>
                Assigned To
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <span class="display-4">
                        <i class="fas fa-user-circle text-primary"></i>
                    </span>
                </div>
                
                <h5><?php echo htmlspecialchars($assignment['assigned_to_name']); ?></h5>
                
                <?php if(!empty($assignment['department_name'])): ?>
                <p class="text-muted"><?php echo htmlspecialchars($assignment['department_name']); ?></p>
                <?php endif; ?>
                
                <hr>
                
                <?php if(!empty($assignment['assigned_to_email'])): ?>
                <p>
                    <i class="fas fa-envelope mr-2"></i>
                    <a href="mailto:<?php echo htmlspecialchars($assignment['assigned_to_email']); ?>">
                        <?php echo htmlspecialchars($assignment['assigned_to_email']); ?>
                    </a>
                </p>
                <?php endif; ?>
                
                <p>
                    <i class="fas fa-calendar-alt mr-2"></i>
                    Assigned by <?php echo htmlspecialchars($assignment['assigned_by_name']); ?>
                </p>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-cogs mr-1"></i>
                Quick Actions
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php if($assignment['assignment_status'] == 'assigned'): ?>
                    <a href="return.php?id=<?php echo $assignment_id; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-undo-alt mr-1"></i>Process Return</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Record that this asset has been returned</small>
                    </a>
                    
                    <a href="edit.php?id=<?php echo $assignment_id; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-edit mr-1"></i>Edit Assignment</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Update assignment details</small>
                    </a>
                    <?php endif; ?>
                    
                    <a href="print.php?id=<?php echo $assignment_id; ?>" class="list-group-item list-group-item-action" target="_blank">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-print mr-1"></i>Print Assignment Form</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Print a copy of this assignment</small>
                    </a>
                    
                    <a href="send_reminder.php?id=<?php echo $assignment_id; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><i class="fas fa-bell mr-1"></i>Send Reminder</h6>
                            <small><i class="fas fa-chevron-right"></i></small>
                        </div>
                        <small class="text-muted">Notify the user about this assignment</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>