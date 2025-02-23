// FILE: D:\xampp\htdocs\asset_management_system\modules\audit\continue.php
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

// Process item verification
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $asset_id = $_POST['asset_id'];
    $status = $_POST['status'];
    $actual_location = $_POST['actual_location'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    // Update item status
    $update_sql = "UPDATE audit_items 
                   SET status = ?, 
                       actual_location_id = ?, 
                       notes = ?,
                       scanned_at = NOW()
                   WHERE audit_id = ? AND asset_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "sisii", $status, $actual_location, $notes, $audit_id, $asset_id);
    mysqli_stmt_execute($stmt);
    
    // Redirect to refresh
    header("location: continue.php?id=" . $audit_id);
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-clipboard-check mr-2"></i>Continue Audit</h1>
        <p class="text-muted">
            Location: <?php echo htmlspecialchars($audit['location']); ?><br>
            Date: <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>
        </p>
    </div>
    <div class="col-md-4 text-right">
        <a href="complete.php?id=<?php echo $audit_id; ?>" class="btn btn-success mr-2">
            <i class="fas fa-check mr-2"></i>Complete Audit
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-times mr-2"></i>Exit
        </a>
    </div>
</div>

<!-- Progress Summary -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row text-center">
            <?php
            $total = mysqli_num_rows($items_result);
            $verified = 0;
            $missing = 0;
            $wrong_location = 0;
            
            mysqli_data_seek($items_result, 0);
            while($item = mysqli_fetch_assoc($items_result)) {
                if($item['status'] != 'pending') $verified++;
                if($item['status'] == 'missing') $missing++;
                if($item['status'] == 'wrong_location') $wrong_location++;
            }
            mysqli_data_seek($items_result, 0);
            ?>
            <div class="col">
                <h4><?php echo $total; ?></h4>
                <p class="text-muted mb-0">Total Items</p>
            </div>
            <div class="col">
                <h4><?php echo $verified; ?></h4>
                <p class="text-muted mb-0">Verified</p>
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
                <h4><?php echo round(($verified/$total) * 100); ?>%</h4>
                <p class="text-muted mb-0">Complete</p>
            </div>
        </div>
    </div>
</div>

<!-- Asset List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Assets to Verify
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
                        <th>Actions</th>
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
                            <button type="button" class="btn btn-sm btn-primary verify-item" 
                                    data-asset-id="<?php echo $item['asset_id']; ?>"
                                    data-asset-name="<?php echo htmlspecialchars($item['asset_name']); ?>"
                                    data-asset-tag="<?php echo htmlspecialchars($item['asset_tag']); ?>">
                                <i class="fas fa-check"></i> Verify
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Asset</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="asset_id" id="modalAssetId">
                    
                    <div class="asset-details mb-3">
                        <h6 id="modalAssetName"></h6>
                        <small class="text-muted" id="modalAssetTag"></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-success active">
                                <input type="radio" name="status" value="found" checked> Found
                            </label>
                            <label class="btn btn-outline-warning">
                                <input type="radio" name="status" value="wrong_location"> Wrong Location
                            </label>
                            <label class="btn btn-outline-danger">
                                <input type="radio" name="status" value="missing"> Missing
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="locationGroup">
                        <label>Actual Location</label>
                        <select class="form-control" name="actual_location">
                            <option value="">Select Location</option>
                            <?php
                            $locations_sql = "SELECT * FROM locations ORDER BY building, room";
                            $locations_result = mysqli_query($conn, $locations_sql);
                            while($loc = mysqli_fetch_assoc($locations_result)) {
                                echo '<option value="' . $loc['location_id'] . '">';
                                echo htmlspecialchars($loc['building']);
                                if(!empty($loc['room'])) {
                                    echo ' - ' . htmlspecialchars($loc['room']);
                                }
                                echo '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('.data-table').DataTable({
        order: [[2, 'asc']] // Sort by status
    });
    
    // Handle verify button click
    $('.verify-item').click(function() {
        const assetId = $(this).data('asset-id');
        const assetName = $(this).data('asset-name');
        const assetTag = $(this).data('asset-tag');
        
        $('#modalAssetId').val(assetId);
        $('#modalAssetName').text(assetName);
        $('#modalAssetTag').text(assetTag);
        
        $('#verifyModal').modal('show');
    });
    
    // Handle status change
    $('input[name="status"]').change(function() {
        if($(this).val() === 'wrong_location') {
            $('#locationGroup').show();
        } else {
            $('#locationGroup').hide();
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>