<?php
// Include header
include_once "includes/header.php";

// Get counts for dashboard summary
$total_assets_query = "SELECT COUNT(*) as total FROM assets";
$assigned_assets_query = "SELECT COUNT(*) as total FROM assets WHERE status = 'assigned'";
$available_assets_query = "SELECT COUNT(*) as total FROM assets WHERE status = 'available'";
$pending_deliveries_query = "SELECT COUNT(*) as total FROM delivery_items WHERE status = 'pending'";

$total_assets_result = mysqli_query($conn, $total_assets_query);
$assigned_assets_result = mysqli_query($conn, $assigned_assets_query);
$available_assets_result = mysqli_query($conn, $available_assets_query);
$pending_deliveries_result = mysqli_query($conn, $pending_deliveries_query);

$total_assets = mysqli_fetch_assoc($total_assets_result)['total'];
$assigned_assets = mysqli_fetch_assoc($assigned_assets_result)['total'];
$available_assets = mysqli_fetch_assoc($available_assets_result)['total'];
$pending_deliveries = mysqli_fetch_assoc($pending_deliveries_result)['total'];

// Get recent asset activities
$recent_activities_query = "SELECT ah.*, a.asset_name, a.asset_tag, u.full_name 
                            FROM asset_history ah
                            LEFT JOIN assets a ON ah.asset_id = a.asset_id
                            LEFT JOIN users u ON ah.performed_by = u.user_id
                            ORDER BY ah.action_date DESC
                            LIMIT 10";
$recent_activities_result = mysqli_query($conn, $recent_activities_query);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</h1>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card bg-primary text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-boxes dashboard-icon"></i>
                <h5 class="card-title">Total Assets</h5>
                <h2 class="display-4"><?php echo $total_assets; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="modules/inventory/index.php" class="text-white">View Details</a>
                <div><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card bg-success text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-check-circle dashboard-icon"></i>
                <h5 class="card-title">Available Assets</h5>
                <h2 class="display-4"><?php echo $available_assets; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="modules/inventory/index.php?status=available" class="text-white">View Details</a>
                <div><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card bg-info text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-user-check dashboard-icon"></i>
                <h5 class="card-title">Assigned Assets</h5>
                <h2 class="display-4"><?php echo $assigned_assets; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="modules/inventory/index.php?status=assigned" class="text-white">View Details</a>
                <div><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card bg-warning text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-truck-loading dashboard-icon"></i>
                <h5 class="card-title">Pending Deliveries</h5>
                <h2 class="display-4"><?php echo $pending_deliveries; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="modules/receiving/index.php?status=pending" class="text-white">View Details</a>
                <div><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-history mr-1"></i>
                Recent Asset Activities
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered data-table" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Asset</th>
                                <th>Action</th>
                                <th>Performed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if(mysqli_num_rows($recent_activities_result) > 0) {
                                while($row = mysqli_fetch_assoc($recent_activities_result)) {
                                    echo '<tr>';
                                    echo '<td>' . date('M d, Y H:i', strtotime($row['action_date'])) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['asset_name'] ?? 'N/A') . ' <small class="text-muted">(' . htmlspecialchars($row['asset_tag'] ?? 'N/A') . ')</small></td>';
                                    
                                    // Set badge color based on action
                                    $badge_class = '';
                                    switch($row['action']) {
                                        case 'created': $badge_class = 'success'; break;
                                        case 'updated': $badge_class = 'info'; break;
                                        case 'assigned': $badge_class = 'primary'; break;
                                        case 'returned': $badge_class = 'secondary'; break;
                                        case 'transferred': $badge_class = 'warning'; break;
                                        case 'disposed': $badge_class = 'danger'; break;
                                        default: $badge_class = 'secondary';
                                    }
                                    
                                    echo '<td><span class="badge badge-' . $badge_class . '">' . ucfirst(htmlspecialchars($row['action'])) . '</span></td>';
                                    echo '<td>' . htmlspecialchars($row['full_name'] ?? 'N/A') . '</td>';
                                    echo '<td>' . htmlspecialchars($row['notes'] ?? '') . '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center">No recent activities found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "includes/footer.php";
?>