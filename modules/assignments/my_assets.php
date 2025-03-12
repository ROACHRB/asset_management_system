<?php
// FILE: D:\xampp\htdocs\asset_management_system\modules\assignments\my_assets.php
// Description: Page for users to view their assigned assets

// Include header
include_once "../../includes/header.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /asset_management_system/login.php");
    exit;
}

// Get current user ID
$user_id = $_SESSION["user_id"];

// Initialize variables
$assigned_assets = [];
$error = $success = "";

// Fetch assigned assets for the current user
// Modify the query in my_assets.php to exclude disposed or pending disposal assets
// Fetch assigned assets for the current user
$query = "SELECT aa.assignment_id, a.asset_id, a.asset_tag, a.asset_name, a.description, 
               a.serial_number, a.model, a.manufacturer, a.condition_status, c.category_name,
               aa.assignment_date, aa.expected_return_date, aa.assignment_status,
               l.building, l.room
        FROM asset_assignments aa
        JOIN assets a ON aa.asset_id = a.asset_id
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        WHERE aa.assigned_to = ? 
        AND aa.assignment_status = 'assigned'
        AND a.status NOT IN ('pending_disposal', 'disposed')
        ORDER BY aa.assignment_date DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    $error = "Error retrieving assigned assets: " . mysqli_error($conn);
} else {
    while ($row = mysqli_fetch_assoc($result)) {
        $assigned_assets[] = $row;
    }
}

// Count assets by status
$total_assets = count($assigned_assets);
$due_soon_count = 0;
$overdue_count = 0;

$today = new DateTime();
foreach ($assigned_assets as $asset) {
    if (!empty($asset['expected_return_date'])) {
        $return_date = new DateTime($asset['expected_return_date']);
        
        if ($return_date < $today) {
            $overdue_count++;
        } elseif ($today->diff($return_date)->days <= 7) {
            $due_soon_count++;
        }
    }
}

// Check if there's a success message from a return request
if(isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<div class="row mb-4">
    <div class="col-md-10">
        <h1><i class="fas fa-laptop mr-2"></i>My Assigned Assets</h1>
        <p class="text-muted">View all assets currently assigned to you</p>
    </div>
    <div class="col-md-2 text-right">
        <?php if ($total_assets > 0): ?>
        <button class="btn btn-success" onclick="window.print()">
            <i class="fas fa-print mr-2"></i>Print List
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Error/Success Messages -->
<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Dashboard Cards -->
<div class="row mb-4">
    <!-- Total Assets Card -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Assigned Assets</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_assets; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-laptop fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Due Soon Card -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Due for Return Soon</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $due_soon_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overdue Card -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Overdue Returns</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overdue_count; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Asset Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">My Assets</h6>
    </div>
    <div class="card-body">
        <?php if (empty($assigned_assets)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i> You currently do not have any assets assigned to you.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered" id="assetTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Model / Serial</th>
                            <th>Assigned Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_assets as $asset): ?>
                            <?php
                                // Determine if return date is upcoming or overdue
                                $return_status = '';
                                $return_class = '';
                                
                                if (!empty($asset['expected_return_date'])) {
                                    $return_date = new DateTime($asset['expected_return_date']);
                                    $today = new DateTime();
                                    
                                    if ($return_date < $today) {
                                        $return_status = '<span class="badge badge-danger">Overdue</span>';
                                        $return_class = 'text-danger font-weight-bold';
                                    } elseif ($today->diff($return_date)->days <= 7) {
                                        $return_status = '<span class="badge badge-warning">Due Soon</span>';
                                        $return_class = 'text-warning';
                                    }
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['category_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($asset['model'])): ?>
                                        <?php echo htmlspecialchars($asset['model']); ?><br>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($asset['serial_number'])): ?>
                                        <small class="text-muted">SN: <?php echo htmlspecialchars($asset['serial_number']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">No serial number</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($asset['assignment_date'])); ?></td>
                                <td class="<?php echo $return_class; ?>">
                                    <?php if (!empty($asset['expected_return_date'])): ?>
                                        <?php echo date('M d, Y', strtotime($asset['expected_return_date'])); ?>
                                        <?php echo $return_status; ?>
                                    <?php else: ?>
                                        <span class="badge badge-success">Permanent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        $condition = $asset['condition_status'] ?? 'unknown';
                                        echo ($condition == 'new' ? 'success' : 
                                             ($condition == 'good' ? 'primary' : 
                                             ($condition == 'fair' ? 'info' : 
                                             ($condition == 'poor' ? 'warning' : 'danger')))); 
                                    ?>">
                                        <?php echo ucfirst($asset['condition_status'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm view-details" 
                                            data-toggle="modal" data-target="#assetModal" 
                                            data-id="<?php echo $asset['asset_id']; ?>">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                    
                                    <?php
                                    // Check if a return request already exists for this asset
                                    $check_return_query = "SELECT COUNT(*) as count FROM asset_return_requests 
                                                           WHERE asset_id = ? AND 
                                                                 (status = 'pending' OR status = 'approved')";
                                    $check_stmt = mysqli_prepare($conn, $check_return_query);
                                    mysqli_stmt_bind_param($check_stmt, "i", $asset['asset_id']);
                                    mysqli_stmt_execute($check_stmt);
                                    $check_result = mysqli_stmt_get_result($check_stmt);
                                    $return_request_exists = mysqli_fetch_assoc($check_result)['count'] > 0;
                                    
                                    if (!$return_request_exists):
                                    ?>
                                        <a href="request_return.php?id=<?php echo $asset['asset_id']; ?>" 
                                           class="btn btn-warning btn-sm"
                                           onclick="return confirm('Are you sure you want to request a return for this asset?');">
                                            <i class="fas fa-undo"></i> Return
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Return Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Asset Details Modal -->
<div class="modal fade" id="assetModal" tabindex="-1" role="dialog" aria-labelledby="assetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assetModalLabel">Asset Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="assetDetails">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Print-only styles -->
<style type="text/css" media="print">
    .navbar, .btn, .modal, footer, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info {
        display: none !important;
    }
    body {
        padding: 0;
        margin: 0;
    }
    .container-fluid {
        width: 100%;
        padding: 0;
    }
    table {
        width: 100% !important;
    }
    th, td {
        padding: 5px !important;
    }
</style>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#assetTable').DataTable({
        responsive: true,
        order: [[4, 'desc']] // Sort by assignment date by default
    });
    
    // Handle asset details modal
    $('.view-details').on('click', function() {
        const assetId = $(this).data('id');
        
        // Clear previous content and show loading spinner
        $('#assetDetails').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
        
        // Fetch asset details via AJAX
        $.ajax({
            url: 'get_my_asset_details.php',
            type: 'GET',
            data: { id: assetId },
            success: function(response) {
                $('#assetDetails').html(response);
            },
            error: function() {
                $('#assetDetails').html('<div class="alert alert-danger">Error loading asset details.</div>');
            }
        });
    });
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>