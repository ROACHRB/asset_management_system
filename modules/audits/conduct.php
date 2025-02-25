<?php
// File: modules/audits/conduct.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('conduct_audits')) {
    $_SESSION['error'] = "Access denied. You don't have permission to conduct audits.";
    header("Location: ../../index.php");
    exit;
}

// Check if audit ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid audit ID.";
    header("Location: index.php");
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
    header("Location: index.php");
    exit;
}

$audit = mysqli_fetch_assoc($audit_result);

// Check if audit is completed
if($audit['status'] == 'completed') {
    $_SESSION['error'] = "This audit is already completed.";
    header("Location: view.php?id=$audit_id");
    exit;
}

// Get audit items
$items_query = "SELECT i.*, a.asset_name, a.asset_tag, a.serial_number, a.qr_code, a.barcode
                FROM audit_items i
                JOIN assets a ON i.asset_id = a.asset_id
                WHERE i.audit_id = ?
                ORDER BY i.status = 'pending' DESC, a.asset_name";
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

// Process scan or manual update
if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'scan':
                $asset_tag = trim($_POST['asset_tag']);
                
                // Find the asset by tag, QR code, or barcode
                $asset_query = "SELECT asset_id FROM assets WHERE asset_tag = ? OR qr_code = ? OR barcode = ?";
                $stmt = mysqli_prepare($conn, $asset_query);
                mysqli_stmt_bind_param($stmt, "sss", $asset_tag, $asset_tag, $asset_tag);
                mysqli_stmt_execute($stmt);
                $asset_result = mysqli_stmt_get_result($stmt);
                
                if(mysqli_num_rows($asset_result) > 0) {
                    $asset = mysqli_fetch_assoc($asset_result);
                    $asset_id = $asset['asset_id'];
                    
                    // Check if asset is part of this audit
                    $item_query = "SELECT item_id FROM audit_items 
                                  WHERE audit_id = ? AND asset_id = ?";
                    $stmt = mysqli_prepare($conn, $item_query);
                    mysqli_stmt_bind_param($stmt, "ii", $audit_id, $asset_id);
                    mysqli_stmt_execute($stmt);
                    $item_result = mysqli_stmt_get_result($stmt);
                    
                    if(mysqli_num_rows($item_result) > 0) {
                        $item = mysqli_fetch_assoc($item_result);
                        $item_id = $item['item_id'];
                        
                        // Update item as found
                        $update = "UPDATE audit_items SET status = 'found', scanned_at = NOW() 
                                  WHERE item_id = ?";
                        $stmt = mysqli_prepare($conn, $update);
                        mysqli_stmt_bind_param($stmt, "i", $item_id);
                        mysqli_stmt_execute($stmt);
                        
                        $_SESSION['success'] = "Asset found and marked accordingly.";
                    } else {
                        // Asset exists but isn't part of this audit (wrong location)
                        $insert = "INSERT INTO audit_items (audit_id, asset_id, status, scanned_at) 
                                  VALUES (?, ?, 'wrong_location', NOW())";
                        $stmt = mysqli_prepare($conn, $insert);
                        mysqli_stmt_bind_param($stmt, "ii", $audit_id, $asset_id);
                        mysqli_stmt_execute($stmt);
                        
                        $_SESSION['warning'] = "Asset found but was not expected in this location.";
                    }
                } else {
                    $_SESSION['error'] = "Asset not found. Check the tag and try again.";
                }
                
                break;
                
            case 'update':
                $item_id = $_POST['item_id'];
                $status = $_POST['status'];
                $notes = trim($_POST['notes']);
                
                // Update item status
                $update = "UPDATE audit_items SET status = ?, notes = ? WHERE item_id = ?";
                $stmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($stmt, "ssi", $status, $notes, $item_id);
                $result = mysqli_stmt_execute($stmt);
                
                if($result) {
                    $_SESSION['success'] = "Item status updated successfully.";
                } else {
                    $_SESSION['error'] = "Error updating item status: " . mysqli_error($conn);
                }
                
                break;
                
            case 'complete':
                // Check if all items have been processed
                if($pending_count > 0) {
                    $_SESSION['warning'] = "Cannot complete audit while there are still pending items.";
                } else {
                    // Update audit status to completed
                    $update = "UPDATE physical_audits SET status = 'completed', completed_date = NOW() 
                              WHERE audit_id = ?";
                    $stmt = mysqli_prepare($conn, $update);
                    mysqli_stmt_bind_param($stmt, "i", $audit_id);
                    $result = mysqli_stmt_execute($stmt);
                    
                    if($result) {
                        // Log activity
                        log_activity('complete_audit', "Completed audit #$audit_id");
                        
                        $_SESSION['success'] = "Audit completed successfully.";
                        header("Location: view.php?id=$audit_id");
                        exit;
                    } else {
                        $_SESSION['error'] = "Error completing audit: " . mysqli_error($conn);
                    }
                }
                
                break;
        }
        
        // Refresh page to show updated data
        header("Location: conduct.php?id=$audit_id");
        exit;
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-clipboard-check mr-2"></i>Conduct Audit</h1>
        <p class="text-muted">
            Audit #<?php echo $audit_id; ?> - 
            <?php echo htmlspecialchars($audit['location']); ?> - 
            <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>
        </p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Audits
        </a>
        <?php if($pending_count == 0): ?>
            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="btn btn-success ml-2">
                    <i class="fas fa-check-circle mr-2"></i>Complete Audit
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Scan Asset Form -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-barcode mr-1"></i>Scan Asset
    </div>
    <div class="card-body">
        <form method="post" class="form-inline justify-content-center">
            <input type="hidden" name="action" value="scan">
            <div class="input-group input-group-lg w-75">
                <input type="text" class="form-control" id="asset_tag" name="asset_tag" 
                       placeholder="Scan asset tag, barcode or QR code" autofocus>
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search mr-2"></i>Find
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Audit Progress -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-tasks mr-1"></i>Audit Progress
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
                <div class="h1 text-warning"><?php echo $pending_count; ?></div>
                <div>Pending</div>
            </div>
        </div>
        
        <div class="progress mt-3">
            <?php 
            $found_percent = ($total_items > 0) ? ($found_count / $total_items) * 100 : 0;
            $missing_percent = ($total_items > 0) ? ($missing_count / $total_items) * 100 : 0;
            $wrong_location_percent = ($total_items > 0) ? ($wrong_location_count / $total_items) * 100 : 0;
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
        </div>
    </div>
</div>

<!-- Asset List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Asset List
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="assetTable">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Serial Number</th>
                        <th>Status</th>
                        <th>Last Scanned</th>
                        <th>Actions</th>
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
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="openStatusModal(<?php echo $item['item_id']; ?>, '<?php echo $item['status']; ?>', '<?php echo addslashes($item['notes']); ?>')">
                                    <i class="fas fa-edit"></i> Update
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Asset Status</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="item_id" id="modal_item_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="modal_status" name="status">
                            <option value="pending">Pending</option>
                            <option value="found">Found</option>
                            <option value="missing">Missing</option>
                            <option value="wrong_location">Wrong Location</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#assetTable').DataTable({
        "order": [[3, "asc"]], // Sort by status by default
        "pageLength": 25,
        "columns": [
            null, // Asset Tag
            null, // Name
            null, // Serial Number
            null, // Status
            null, // Last Scanned
            { "orderable": false } // Actions
        ]
    });
    
    // Focus on scan input when page loads
    $('#asset_tag').focus();
});

// Open status update modal
function openStatusModal(itemId, status, notes) {
    $('#modal_item_id').val(itemId);
    $('#modal_status').val(status);
    $('#modal_notes').val(notes);
    $('#updateStatusModal').modal('show');
}
</script>