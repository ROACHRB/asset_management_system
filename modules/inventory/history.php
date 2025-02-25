<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\history.php
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
    <div class="col-md-8">
        <h1>
            <i class="fas fa-history mr-2"></i>Asset History
        </h1>
        <p class="text-muted">View complete history of: <?php echo htmlspecialchars($asset['asset_name']); ?> <?php echo !empty($asset['asset_tag']) ? '(' . htmlspecialchars($asset['asset_tag']) . ')' : ''; ?></p>
    </div>
    <div class="col-md-4 text-right">
        <div class="btn-group">
            <a href="view.php?id=<?php echo $asset_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back to Asset
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-list mr-1"></i>All Assets
            </a>
        </div>
    </div>
</div>

<!-- Asset Basic Info Card -->
<div class="card mb-4">
    <div class="card-header bg-secondary text-white">
        <i class="fas fa-info-circle mr-1"></i>
        Asset Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Asset Name:</strong> <?php echo htmlspecialchars($asset['asset_name']); ?></p>
                <p><strong>Asset Tag:</strong> <?php echo !empty($asset['asset_tag']) ? htmlspecialchars($asset['asset_tag']) : 'Not Tagged'; ?></p>
                <p><strong>Serial Number:</strong> <?php echo !empty($asset['serial_number']) ? htmlspecialchars($asset['serial_number']) : 'N/A'; ?></p>
                <p><strong>Category:</strong> <?php echo !empty($asset['category_name']) ? htmlspecialchars($asset['category_name']) : 'Uncategorized'; ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Status:</strong> 
                    <span class="badge badge-<?php 
                        echo ($asset['status'] == 'available' ? 'success' : 
                             ($asset['status'] == 'assigned' ? 'primary' : 
                             ($asset['status'] == 'under_repair' ? 'warning' : 
                             ($asset['status'] == 'lost' || $asset['status'] == 'stolen' ? 'danger' : 'secondary'))));
                    ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $asset['status'])); ?>
                    </span>
                </p>
                <p><strong>Condition:</strong> 
                    <span class="badge badge-<?php 
                        echo ($asset['condition_status'] == 'new' ? 'success' : 
                             ($asset['condition_status'] == 'good' ? 'info' : 
                             ($asset['condition_status'] == 'fair' ? 'primary' : 
                             ($asset['condition_status'] == 'poor' ? 'warning' : 'danger'))));
                    ?>">
                        <?php echo ucfirst($asset['condition_status']); ?>
                    </span>
                </p>
                <p><strong>Location:</strong> <?php 
                    if(!empty($asset['building'])) {
                        echo htmlspecialchars($asset['building']);
                        echo !empty($asset['room']) ? ' - ' . htmlspecialchars($asset['room']) : '';
                    } else {
                        echo 'Not assigned';
                    }
                ?></p>
                <p><strong>Created:</strong> <?php echo date('F d, Y', strtotime($asset['created_at'])); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Comprehensive History Timeline -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-history mr-1"></i>
        Complete History Timeline
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($history_result) > 0): ?>
            <div class="timeline">
                <?php while ($history = mysqli_fetch_assoc($history_result)): 
                    // Determine icon based on action
                    $icon_class = '';
                    switch($history['action']) {
                        case 'created': $icon_class = 'fa-plus-circle text-success'; break;
                        case 'updated': $icon_class = 'fa-edit text-info'; break;
                        case 'assigned': $icon_class = 'fa-user-check text-primary'; break;
                        case 'returned': $icon_class = 'fa-undo text-secondary'; break;
                        case 'transferred': $icon_class = 'fa-exchange-alt text-warning'; break;
                        case 'repaired': $icon_class = 'fa-tools text-primary'; break;
                        case 'disposed': $icon_class = 'fa-trash-alt text-danger'; break;
                        case 'lost':
                        case 'stolen': $icon_class = 'fa-exclamation-triangle text-danger'; break;
                        default: $icon_class = 'fa-circle text-secondary';
                    }
                ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas <?php echo $icon_class; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-date">
                                <?php echo date('F d, Y h:i A', strtotime($history['action_date'])); ?>
                            </div>
                            <h6><?php echo ucfirst($history['action']); ?></h6>
                            <p><?php echo nl2br(htmlspecialchars($history['notes'])); ?></p>
                            <small class="text-muted">
                                By: <?php echo htmlspecialchars($history['full_name'] ?? 'Unknown User'); ?>
                            </small>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i> No history records found for this asset.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Timeline styles */
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
    margin-bottom: 30px;
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
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
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