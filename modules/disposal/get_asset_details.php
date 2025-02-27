<?php
// FILE PATH: asset_management_system/modules/disposal/get_asset_details.php
include_once "../../config/database.php";

// Check if ID is provided
if(empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>No asset ID provided</div>";
    exit;
}

$asset_id = intval($_GET['id']);

// Fetch asset details
$query = "SELECT a.*, c.category_name, l.building, l.room, l.department
          FROM assets a
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN locations l ON a.location_id = l.location_id
          WHERE a.asset_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $asset_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>Asset not found</div>";
    exit;
}

$asset = mysqli_fetch_assoc($result);

// Check if asset is already in a disposal request
$check_query = "SELECT disposal_id FROM disposal_requests 
                WHERE asset_id = ? AND status IN ('pending', 'approved')";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "i", $asset_id);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($check_result) > 0) {
    $disposal = mysqli_fetch_assoc($check_result);
    echo "<div class='alert alert-warning'>
          <i class='fas fa-exclamation-triangle mr-1'></i>
          This asset is already in an active disposal request. 
          <a href='view.php?id={$disposal['disposal_id']}' class='alert-link'>View request</a>
          </div>";
    exit;
}

// Format location
$location = $asset['building'] ?? 'Unknown';
if(!empty($asset['room'])) {
    $location .= ' - ' . $asset['room'];
}
if(!empty($asset['department'])) {
    $location .= ' (' . $asset['department'] . ')';
}

// Get status badge class
switch($asset['status']) {
    case 'available':
        $status_class = 'success';
        break;
    case 'assigned':
        $status_class = 'primary';
        break;
    case 'under_repair':
        $status_class = 'warning';
        break;
    case 'lost':
    case 'stolen':
        $status_class = 'danger';
        break;
    default:
        $status_class = 'secondary';
}
?>

<div class="row">
    <div class="col-md-6">
        <table class="table table-sm">
            <tr>
                <th>Asset Name:</th>
                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
            </tr>
            <tr>
                <th>Asset Tag:</th>
                <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
            </tr>
            <tr>
                <th>Category:</th>
                <td><?php echo htmlspecialchars($asset['category_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <span class="badge badge-<?php echo $status_class; ?>">
                        <?php echo ucfirst($asset['status']); ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-sm">
            <tr>
                <th>Serial Number:</th>
                <td><?php echo htmlspecialchars($asset['serial_number'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Model:</th>
                <td><?php echo htmlspecialchars($asset['model'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Manufacturer:</th>
                <td><?php echo htmlspecialchars($asset['manufacturer'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Location:</th>
                <td><?php echo htmlspecialchars($location); ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if($asset['status'] != 'available'): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle mr-1"></i>
    <strong>Note:</strong> This asset is currently marked as <strong><?php echo ucfirst($asset['status']); ?></strong>. 
    If approved for disposal, the asset will need to be recovered first.
</div>
<?php endif; ?>