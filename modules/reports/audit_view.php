<?php
// File: modules/reports/audit_view.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('generate_reports')) {
    $_SESSION['error'] = "Access denied. You don't have permission to view reports.";
    header("Location: ../../index.php");
    exit;
}

// Check if audit ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid audit ID.";
    header("Location: audit_list.php");
    exit;
}

$audit_id = $_GET['id'];

// Get audit information
$audit_query = "SELECT a.*, u.full_name as auditor_name 
                FROM physical_audits a
                JOIN users u ON a.auditor_id = u.user_id
                WHERE a.audit_id = ?";
$stmt = mysqli_prepare($conn, $audit_query);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$audit_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($audit_result) == 0) {
    $_SESSION['error'] = "Audit not found.";
    header("Location: audit_list.php");
    exit;
}

$audit = mysqli_fetch_assoc($audit_result);

// Get audit items
$items_query = "SELECT i.*, a.asset_name, a.asset_tag, a.serial_number, a.model, a.manufacturer
                FROM audit_items i
                JOIN assets a ON i.asset_id = a.asset_id
                WHERE i.audit_id = ?
                ORDER BY i.status, a.asset_name";
$stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

// Count statistics
$total_items = mysqli_num_rows($items_result);
$found_count = 0;
$missing_count = 0;
$pending_count = 0;
$wrong_location_count = 0;

$items = [];
while($item = mysqli_fetch_assoc($items_result)) {
    $items[] = $item;
    
    switch($item['status']) {
        case 'found': $found_count++; break;
        case 'missing': $missing_count++; break;
        case 'wrong_location': $wrong_location_count++; break;
        default: $pending_count++;
    }
}

// Reopen audit if requested
if(isset($_POST['action']) && $_POST['action'] == 'reopen' && has_permission('conduct_audits')) {
    if($audit['status'] == 'completed') {
        $update = "UPDATE physical_audits SET status = 'in_progress', completed_date = NULL WHERE audit_id = ?";
        $stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($stmt, "i", $audit_id);
        $result = mysqli_stmt_execute($stmt);
        
        if($result) {
            // Log activity
            log_activity('reopen_audit', "Reopened audit #$audit_id");
            
            $_SESSION['success'] = "Audit reopened successfully.";
            header("Location: audit_conduct.php?id=$audit_id");
            exit;
        } else {
            $_SESSION['error'] = "Error reopening audit: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "This audit is not completed and cannot be reopened.";
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-clipboard-check mr-2"></i>Audit Details</h1>
        <p class="text-muted">
            Audit #<?php echo $audit_id; ?> - 
            <?php echo htmlspecialchars($audit['location']); ?> - 
            <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>
        </p>
    </div>
    <div class="col-md-4 text-right">
        <a href="audit_list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Audit List
        </a>
        <?php if($audit['status'] == 'completed'): ?>
            <a href="audit_report.php?id=<?php echo $audit_id; ?>" class="btn btn-success ml-2">
                <i class="fas fa-file-alt mr-2"></i>Generate Report
            </a>
            <?php if(has_permission('conduct_audits')): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="reopen">
                    <button type="submit" class="btn btn-warning ml-2" 
                            onclick="return confirm('Are you sure you want to reopen this audit?');">
                        <i class="fas fa-redo mr-2"></i>Reopen
                    </button>
                </form>
            <?php endif; ?>
        <?php elseif(has_permission('conduct_audits')): ?>
            <a href="audit_conduct.php?id=<?php echo $audit_id; ?>" class="btn btn-primary ml-2">
                <i class="fas fa-tasks mr-2"></i>Continue Audit
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Audit Details -->
<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle mr-1"></i>Audit Details
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Audit ID:</th>
                        <td><?php echo $audit_id; ?></td>
                    </tr>
                    <tr>
                        <th>Location:</th>
                        <td><?php echo htmlspecialchars($audit['location']); ?></td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td><?php echo date('M d, Y', strtotime($audit['audit_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Auditor:</th>
                        <td><?php echo htmlspecialchars($audit['auditor_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge badge-<?php echo ($audit['status'] == 'completed') ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($audit['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if($audit['status'] == 'completed' && $audit['completed_date']): ?>
                    <tr>
                        <th>Completed:</th>
                        <td><?php echo date('M d, Y H:i', strtotime($audit['completed_date'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if(!empty($audit['notes'])): ?>
                    <tr>
                        <th>Notes:</th>
                        <td><?php echo nl2br(htmlspecialchars($audit['notes'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-pie mr-1"></i>Audit Results
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="h1"><?php echo $total_items; ?></div>
                        <div>Total Assets</div>
                    </div>
                    <div class="col-md-3">
                        <div class="h1 text-success"><?php echo $found_count; ?></div>
                        <div>Found</div>
                    </div>
                    <div class="col-md-3">
                        <div class="h1 text-danger"><?php echo $missing_count; ?></div>
                        <div>Missing</div>
                    </div>
                    <div class="col-md-3">
                        <div class="h1 text-warning"><?php echo $wrong_location_count; ?></div>
                        <div>Wrong Location</div>
                    </div>
                </div>
                
                <div class="progress mt-3">
                    <?php 
                    $found_percent = ($total_items > 0) ? ($found_count / $total_items) * 100 : 0;
                    $missing_percent = ($total_items > 0) ? ($missing_count / $total_items) * 100 : 0;
                    $wrong_location_percent = ($total_items > 0) ? ($wrong_location_count / $total_items) * 100 : 0;
                    $pending_percent = ($total_items > 0) ? ($pending_count / $total_items) * 100 : 0;
                    ?>
                    <div class="progress-bar bg-success" role="progressbar" 
                         style="width: <?php echo $found_percent; ?>%" 
                         aria-valuenow="<?php echo $found_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo round($found_percent); ?>%
                    </div>
                    <div class="progress-bar bg-danger" role="progressbar" 
                         style="width: <?php echo $missing_percent; ?>%" 
                         aria-valuenow="<?php echo $missing_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo round($missing_percent); ?>%
                    </div>
                    <div class="progress-bar bg-warning" role="progressbar" 
                         style="width: <?php echo $wrong_location_percent; ?>%" 
                         aria-valuenow="<?php echo $wrong_location_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo round($wrong_location_percent); ?>%
                    </div>
                    <div class="progress-bar bg-secondary" role="progressbar" 
                         style="width: <?php echo $pending_percent; ?>%" 
                         aria-valuenow="<?php echo $pending_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo round($pending_percent); ?>%
                    </div>
                </div>
                
                <?php if($pending_count > 0 && $audit['status'] == 'in_progress'): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    This audit is still in progress with <?php echo $pending_count; ?> pending items.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Asset List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Asset List
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs" id="assetTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab">
                    All Assets (<?php echo $total_items; ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="found-tab" data-toggle="tab" href="#found" role="tab">
                    Found (<?php echo $found_count; ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="missing-tab" data-toggle="tab" href="#missing" role="tab">
                    Missing (<?php echo $missing_count; ?>)
                </a>
            </li>
            <?php if($wrong_location_count > 0): ?>
            <li class="nav-item">
                <a class="nav-link" id="wrong-location-tab" data-toggle="tab" href="#wrong-location" role="tab">
                    Wrong Location (<?php echo $wrong_location_count; ?>)
                </a>
            </li>
            <?php endif; ?>
            <?php if($pending_count > 0): ?>
            <li class="nav-item">
                <a class="nav-link" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                    Pending (<?php echo $pending_count; ?>)
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="tab-content mt-3" id="assetTabsContent">
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>Status</th>
                                <th>Last Scanned</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $item): ?>
                                <tr class="
                                    <?php 
                                    echo ($item['status'] == 'found') ? 'table-success' : 
                                         (($item['status'] == 'missing') ? 'table-danger' : 
                                         (($item['status'] == 'wrong_location') ? 'table-warning' : '')); 
                                    ?>">
                                    <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                                    <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($item['manufacturer'] . ' ' . $item['model']); 
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                    <td>
                                        <span class="badge badge-
                                            <?php 
                                            echo ($item['status'] == 'found') ? 'success' : 
                                                 (($item['status'] == 'missing') ? 'danger' : 
                                                 (($item['status'] == 'wrong_location') ? 'warning' : 'secondary')); 
                                            ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo ($item['scanned_at']) ? date('M d, Y H:i', strtotime($item['scanned_at'])) : 'Not scanned';
                                        ?>
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($item['notes'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Found Assets Tab -->
            <div class="tab-pane fade" id="found" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>Last Scanned</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $item): ?>
                                <?php if($item['status'] == 'found'): ?>
                                    <tr class="table-success">
                                        <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                                        <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($item['manufacturer'] . ' ' . $item['model']); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                        <td>
                                            <?php 
                                            echo ($item['scanned_at']) ? date('M d, Y H:i', strtotime($item['scanned_at'])) : 'Not scanned';
                                            ?>
                                        </td>
                                        <td><?php echo nl2br(htmlspecialchars($item['notes'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Missing Assets Tab -->
            <div class="tab-pane fade" id="missing" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $item): ?>
                                <?php if($item['status'] == 'missing'): ?>
                                    <tr class="table-danger">
                                        <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                                        <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($item['manufacturer'] . ' ' . $item['model']); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($item['notes'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Wrong Location Assets Tab -->
            <?php if($wrong_location_count > 0): ?>
            <div class="tab-pane fade" id="wrong-location" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>Last Scanned</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $item): ?>
                                <?php if($item['status'] == 'wrong_location'): ?>
                                    <tr class="table-warning">
                                        <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                                        <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($item['manufacturer'] . ' ' . $item['model']); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                        <td>
                                            <?php 
                                            echo ($item['scanned_at']) ? date('M d, Y H:i', strtotime($item['scanned_at'])) : 'Not scanned';
                                            ?>
                                        </td>
                                        <td><?php echo nl2br(htmlspecialchars($item['notes'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Pending Assets Tab -->
            <?php if($pending_count > 0): ?>
            <div class="tab-pane fade" id="pending" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $item): ?>
                                <?php if($item['status'] == 'pending'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                                        <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($item['manufacturer'] . ' ' . $item['model']); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['serial_number']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($item['notes'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('.datatable').DataTable({
        "pageLength": 25,
        "responsive": true
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>