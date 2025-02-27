<?php
// FILE PATH: asset_management_system/modules/disposal/view.php
include_once "../../includes/header.php";

// Get disposal request ID
$disposal_id = $_GET['id'] ?? 0;

// Fetch disposal request details
$query = "SELECT d.*, a.asset_name, a.asset_tag, a.serial_number, a.model, a.manufacturer,
          c.category_name, a.status as asset_status, a.condition_status,
          u1.full_name as requested_by_name,
          u2.full_name as approved_by_name
          FROM disposal_requests d
          JOIN assets a ON d.asset_id = a.asset_id
          LEFT JOIN categories c ON a.category_id = c.category_id
          JOIN users u1 ON d.requested_by = u1.user_id
          LEFT JOIN users u2 ON d.approved_by = u2.user_id
          WHERE d.disposal_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $disposal_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-danger">Disposal request not found.</div>';
    include_once "../../includes/footer.php";
    exit;
}

$disposal = mysqli_fetch_assoc($result);

// Check for messages
$success = '';
if(isset($_GET['success']) && $_GET['success'] == 'updated') {
    $success = 'Disposal request has been updated successfully.';
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-trash-alt mr-2"></i>Disposal Request Details</h1>
        <p class="text-muted">View disposal request information</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Request Information
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 35%;">Request Date:</th>
                        <td><?php echo date('F d, Y', strtotime($disposal['request_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($disposal['status'] == 'approved' ? 'success' : 
                                    ($disposal['status'] == 'rejected' ? 'danger' : 
                                    ($disposal['status'] == 'completed' ? 'info' : 'warning'))); 
                            ?>">
                                <?php echo ucfirst($disposal['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Requested By:</th>
                        <td><?php echo htmlspecialchars($disposal['requested_by_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Reason for Disposal:</th>
                        <td><?php echo nl2br(htmlspecialchars($disposal['reason'])); ?></td>
                    </tr>
                    <?php if($disposal['status'] != 'pending'): ?>
                    <tr>
                        <th>Reviewed By:</th>
                        <td><?php echo htmlspecialchars($disposal['approved_by_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Review Date:</th>
                        <td>
                            <?php echo !empty($disposal['approval_date']) ? 
                                date('F d, Y', strtotime($disposal['approval_date'])) : 'N/A'; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Review Notes:</th>
                        <td><?php echo nl2br(htmlspecialchars($disposal['approval_notes'] ?? 'N/A')); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if($disposal['status'] == 'completed'): ?>
                    <tr>
                        <th>Completion Date:</th>
                        <td>
                            <?php echo date('F d, Y', strtotime($disposal['completion_date'])); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <div class="mt-3">
                    <?php if($disposal['status'] == 'pending' && 
                          ($_SESSION['role'] == 'admin' || 
                           $_SESSION['user_id'] == $disposal['requested_by'])): ?>
                    <a href="edit.php?id=<?php echo $disposal_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit mr-1"></i>Edit Request
                    </a>
                    <?php endif; ?>
                    
                    <?php if($disposal['status'] == 'pending' && $_SESSION['role'] == 'admin'): ?>
                    <a href="approve.php?id=<?php echo $disposal_id; ?>" class="btn btn-success">
                        <i class="fas fa-check mr-1"></i>Review Request
                    </a>
                    <?php endif; ?>
                    
                    <?php if($disposal['status'] == 'approved' && $_SESSION['role'] == 'admin'): ?>
                    <a href="complete.php?id=<?php echo $disposal_id; ?>" class="btn btn-warning">
                        <i class="fas fa-trash-alt mr-1"></i>Mark as Disposed
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-box mr-1"></i>Asset Information
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 35%;">Asset Name:</th>
                        <td><?php echo htmlspecialchars($disposal['asset_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Asset Tag:</th>
                        <td><?php echo htmlspecialchars($disposal['asset_tag']); ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo htmlspecialchars($disposal['category_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Serial Number:</th>
                        <td><?php echo htmlspecialchars($disposal['serial_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td><?php echo htmlspecialchars($disposal['model'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Manufacturer:</th>
                        <td><?php echo htmlspecialchars($disposal['manufacturer'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Asset Status:</th>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($disposal['asset_status'] == 'available' ? 'success' : 
                                    ($disposal['asset_status'] == 'disposed' ? 'danger' : 'warning')); 
                            ?>">
                                <?php echo ucfirst($disposal['asset_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Condition:</th>
                        <td>
                            <?php echo ucfirst($disposal['condition_status']); ?>
                        </td>
                    </tr>
                </table>
                
                <div class="mt-3">
                    <a href="../inventory/view.php?id=<?php echo $disposal['asset_id']; ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-box mr-1"></i>View Asset Details
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>