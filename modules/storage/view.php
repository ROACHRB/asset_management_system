<?php
// File: modules/storage/view.php
include_once "../../includes/header.php";

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid location ID.";
    header("Location: index.php");
    exit;
}

$location_id = $_GET['id'];

// Get location details
$location_query = "SELECT l.*, 
                  (SELECT COUNT(*) FROM assets WHERE location_id = l.location_id) as total_assets,
                  (SELECT COUNT(*) FROM assets WHERE location_id = l.location_id AND status = 'available') as available_assets
                  FROM locations l
                  WHERE l.location_id = ?";
$stmt = mysqli_prepare($conn, $location_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$location_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($location_result) == 0) {
    $_SESSION['error'] = "Location not found.";
    header("Location: index.php");
    exit;
}

$location = mysqli_fetch_assoc($location_result);

// Get departments assigned to this location
$departments = [];

// First check if we have entries in location_departments table
$dept_query = "SELECT department FROM location_departments WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $dept_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$dept_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($dept_result) > 0) {
    while($dept = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $dept['department'];
    }
} else if(!empty($location['department'])) {
    // If no entries in junction table, check the old department field
    $departments[] = $location['department'];
}

// Get assets in this location
$assets_query = "SELECT a.*, c.category_name 
                FROM assets a
                LEFT JOIN categories c ON a.category_id = c.category_id
                WHERE a.location_id = ?
                ORDER BY a.asset_name
                LIMIT 10"; // Limit to 10 assets for performance
$stmt = mysqli_prepare($conn, $assets_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$assets_result = mysqli_stmt_get_result($stmt);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-map-marker-alt mr-2"></i>View Location</h1>
        <p class="text-muted">View details for the selected storage location</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary mr-2">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
        <a href="edit.php?id=<?php echo $location_id; ?>" class="btn btn-primary">
            <i class="fas fa-edit mr-2"></i>Edit Location
        </a>
    </div>
</div>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success">
    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="alert alert-danger">
    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<!-- Location Details -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>
                Location Details
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Location ID:</th>
                        <td><?php echo $location_id; ?></td>
                    </tr>
                    <tr>
                        <th>Building:</th>
                        <td><?php echo htmlspecialchars($location['building']); ?></td>
                    </tr>
                    <tr>
                        <th>Room:</th>
                        <td><?php echo !empty($location['room']) ? htmlspecialchars($location['room']) : '<em>Not specified</em>'; ?></td>
                    </tr>
                    <tr>
                        <th>Department(s):</th>
                        <td>
                            <?php if(!empty($departments)): ?>
                                <?php foreach($departments as $index => $dept): ?>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($dept); ?></span>
                                    <?php if($index < count($departments) - 1) echo ' '; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <em>No departments assigned</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo !empty($location['description']) ? nl2br(htmlspecialchars($location['description'])) : '<em>No description provided</em>'; ?></td>
                    </tr>
                    <tr>
                        <th>Created:</th>
                        <td><?php echo date('M d, Y', strtotime($location['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-pie mr-1"></i>
                Asset Statistics
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-6 mb-4">
                        <div class="h1 mb-0"><?php echo $location['total_assets']; ?></div>
                        <div class="small text-muted">Total Assets</div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="h1 mb-0"><?php echo $location['available_assets']; ?></div>
                        <div class="small text-muted">Available Assets</div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <?php 
                    // Calculate available percentage
                    $available_percent = ($location['total_assets'] > 0) 
                        ? round(($location['available_assets'] / $location['total_assets']) * 100) 
                        : 0;
                    
                    // Calculate assigned percentage
                    $assigned_percent = 100 - $available_percent;
                    ?>
                    
                    <h5 class="small font-weight-bold">
                        Availability Status
                        <span class="float-right"><?php echo $available_percent; ?>%</span>
                    </h5>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $available_percent; ?>%"
                             aria-valuenow="<?php echo $available_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                            Available
                        </div>
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $assigned_percent; ?>%"
                             aria-valuenow="<?php echo $assigned_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                            Assigned/Other
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="../inventory/index.php?location=<?php echo $location_id; ?>" class="btn btn-primary">
                        <i class="fas fa-search mr-1"></i>View All Assets at This Location
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assets at this Location -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-boxes mr-1"></i>
        Assets in this Location
    </div>
    <div class="card-body">
        <?php if(mysqli_num_rows($assets_result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Purchase Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                            <tr>
                                <td><?php echo !empty($asset['asset_tag']) ? htmlspecialchars($asset['asset_tag']) : '<span class="badge badge-warning">Not Tagged</span>'; ?></td>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['category_name'] ?? 'Uncategorized'); ?></td>
                                <td>
                                    <?php
                                    $badge_class = '';
                                    switch($asset['status']) {
                                        case 'available': $badge_class = 'success'; break;
                                        case 'assigned': $badge_class = 'primary'; break;
                                        case 'under_repair': $badge_class = 'warning'; break;
                                        case 'disposed': $badge_class = 'secondary'; break;
                                        case 'lost': 
                                        case 'stolen': $badge_class = 'danger'; break;
                                        default: $badge_class = 'info';
                                    }
                                    ?>
                                    <span class="badge badge-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $asset['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($asset['purchase_date']) && $asset['purchase_date'] != '0000-00-00' ? date('M d, Y', strtotime($asset['purchase_date'])) : 'N/A'; ?></td>
                                <td class="text-center">
                                    <a href="../inventory/view.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="../inventory/edit.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-primary" title="Edit Asset">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($location['total_assets'] > 10): ?>
                <div class="text-center mt-3">
                    <p class="text-muted">Showing 10 of <?php echo $location['total_assets']; ?> assets</p>
                    <a href="../inventory/index.php?location=<?php echo $location_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-list mr-1"></i>View All Assets
                    </a>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle mr-2"></i>
                There are no assets currently stored in this location.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>