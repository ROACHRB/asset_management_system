// FILE: D:\xampp\htdocs\asset_management_system\modules\audit\view.php
<?php
include_once "../../includes/header.php";

// Check if audit ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: index.php");
    exit();
}

$audit_id = $_GET['id'];

// Get audit details
$audit_sql = "SELECT a.*, u.full_name as auditor_name 
              FROM physical_audits a
              JOIN users u ON a.auditor_id = u.user_id
              WHERE a.audit_id = ?";
$stmt = mysqli_prepare($conn, $audit_sql);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$audit_result = mysqli_stmt_get_result($stmt);
$audit = mysqli_fetch_assoc($audit_result);

// Get audit items
$items_sql = "SELECT ai.*, a.asset_name, a.asset_tag, 
              l1.building as expected_building, l1.room as expected_room,
              l2.building as actual_building, l2.room as actual_room
              FROM audit_items ai
              JOIN assets a ON ai.asset_id = a.asset_id
              LEFT JOIN locations l1 ON ai.expected_location_id = l1.location_id
              LEFT JOIN locations l2 ON ai.actual_location_id = l2.location_id
              WHERE ai.audit_id = ?
              ORDER BY ai.status ASC, a.asset_name ASC";
$stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-clipboard-check mr-2"></i>Audit Details</h1>
        <p class="text-muted">
            View physical inventory audit results
        </p>
    </div>
    <div class="col-md-4 text-right">
        <a href="report.php?id=<?php echo $audit_id; ?>" class="btn btn-primary mr-2">
            <i class="fas fa-download mr-2"></i>Download Report
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<!-- Audit Information -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-info-circle mr-1"></i>Audit Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Location:</strong><br> 
                    <?php echo htmlspecialchars($audit['location']); ?>
                </p>
                <p><strong>Audit Date:</strong><br>
                    <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>
                </p>
            </div>
            <div class="col-md-6">
                <p><strong>Auditor:</strong><br>
                    <?php echo htmlspecialchars($audit['auditor_name']); ?>
                </p>
                <p><strong>Status:</strong><br>
                    <span class="badge badge-<?php 
                        echo ($audit['status'] == 'completed' ? 'success' : 
                            ($audit['status'] == 'in_progress' ? 'warning' : 'info')); 
                    ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $audit['status'])); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <?php if(!empty($audit['notes'])): ?>
        <div class="row">
            <div class="col-12">
                <p><strong>Notes:</strong><br>
                    <?php echo nl2br(htmlspecialchars($audit['notes'])); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Audit Summary -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-pie mr-1"></i>Audit Summary
    </div>
    <div class="card-body">
        <div class="row text-center">
            <?php
            $total = mysqli_num_rows($items_result);
            $found = 0;
            $missing = 0;
            $wrong_location = 0;
            $pending = 0;
            
            mysqli_data_seek($items_result, 0);
            while($item = mysqli_fetch_assoc($items_result)) {
                switch($item['status']) {
                    case 'found': $found++; break;
                    case 'missing': $missing++; break;
                    case 'wrong_location': $wrong_location++; break;
                    default: $pending++; break;
                }
            }
            mysqli_data_seek($items_result, 0);
            ?>
            <div class="col">
                <h4><?php echo $total; ?></h4>
                <p class="text-muted mb-0">Total Items</p>
            </div>
            <div class="col">
                <h4 class="text-success"><?php echo $found; ?></h4>
                <p class="text-muted mb-0">Found</p>
            </div>
            <div class="col">
                <h4 class="text-danger"><?php echo $missing; ?></h4>
                <p class="text-muted mb-0">Missing</p>
            </div>
            <div class="col">
                <h4 class="text-warning"><?php echo $wrong_location; ?></h4>
                <p class="text-muted mb-0">Wrong Location</p>
            </div>
            <div class="col">
                <h4><?php echo $pending; ?></h4>
                <p class="text-muted mb-0">Pending</p>
            </div>
        </div>
    </div>
</div>

<!-- Audit Results -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Audit Results
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table">
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Expected Location</th>
                        <th>Status</th>
                        <th>Actual Location</th>
                        <th>Verified At</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = mysqli_fetch_assoc($items_result)): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($item['asset_name']); ?>
                            <small class="text-muted d-block">
                                <?php echo htmlspecialchars($item['asset_tag']); ?>
                            </small>
                        </td>
                        <td>
                            <?php 
                            echo htmlspecialchars($item['expected_building']);
                            if(!empty($item['expected_room'])) {
                                echo ' - ' . htmlspecialchars($item['expected_room']);
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($item['status'] == 'found' ? 'success' : 
                                    ($item['status'] == 'missing' ? 'danger' : 
                                    ($item['status'] == 'wrong_location' ? 'warning' : 'secondary'))); 
                            ?>">
                                <?php echo ucfirst($item['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            if(!empty($item['actual_building'])) {
                                echo htmlspecialchars($item['actual_building']);
                                if(!empty($item['actual_room'])) {
                                    echo ' - ' . htmlspecialchars($item['actual_room']);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            echo !empty($item['scanned_at']) ? 
                                date('M d, Y H:i', strtotime($item['scanned_at'])) : '-';
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['notes'] ?? ''); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.data-table').DataTable({
        order: [[2, 'asc']] // Sort by status
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>