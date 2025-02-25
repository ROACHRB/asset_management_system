<?php
// File: modules/tagging/index.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('manage_assets')) {
    $_SESSION['error'] = "Access denied. You don't have permission to access asset tagging.";
    header("Location: ../../index.php");
    exit;
}

// Get all assets for tagging
$assets_query = "SELECT a.*, c.category_name, l.building, l.room
                FROM assets a
                LEFT JOIN categories c ON a.category_id = c.category_id
                LEFT JOIN locations l ON a.location_id = l.location_id
                ORDER BY a.asset_id DESC";
$assets_result = mysqli_query($conn, $assets_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-tags mr-2"></i>Asset Tagging</h1>
        <p class="text-muted">Generate and print tags, barcodes, and QR codes for assets</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="../inventory/add.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Add New Asset
        </a>
        <a href="print_multiple.php" class="btn btn-success ml-2">
            <i class="fas fa-print mr-2"></i>Batch Print
        </a>
    </div>
</div>

<!-- Asset List for Tagging -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Assets
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="assetsTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($assets_result) > 0): ?>
                        <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="asset-checkbox" value="<?php echo $asset['asset_id']; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($asset['asset_tag']); ?></td>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td><?php echo htmlspecialchars($asset['category_name'] ?? 'Uncategorized'); ?></td>
                                <td>
                                    <?php 
                                    if($asset['location_id']) {
                                        echo htmlspecialchars($asset['building']);
                                        if(!empty($asset['room'])) {
                                            echo ' - ' . htmlspecialchars($asset['room']);
                                        }
                                    } else {
                                        echo 'Not assigned';
                                    }
                                    ?>
                                </td>
                                <td><?php echo get_status_badge($asset['status']); ?></td>
                                <td>
                                    <a href="generate_tag.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-tag"></i> Generate Tag
                                    </a>
                                    <a href="print_tag.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-success" target="_blank">
                                        <i class="fas fa-print"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No assets found. <a href="../inventory/add.php">Add your first asset</a>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <button id="batchPrintBtn" class="btn btn-success" disabled>
            <i class="fas fa-print mr-2"></i>Print Selected Tags
        </button>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#assetsTable').DataTable({
        "order": [[1, "asc"]], // Sort by Asset Tag by default
        "columnDefs": [
            { "orderable": false, "targets": [0, 6] } // Disable sorting on checkbox and actions columns
        ]
    });
    
    // Select All checkbox
    $('#selectAll').change(function() {
        $('.asset-checkbox').prop('checked', $(this).prop('checked'));
        updateBatchPrintButton();
    });
    
    // Update batch print button state when individual checkboxes change
    $(document).on('change', '.asset-checkbox', function() {
        updateBatchPrintButton();
    });
    
    // Batch print button click
    $('#batchPrintBtn').click(function() {
        var selectedAssets = [];
        $('.asset-checkbox:checked').each(function() {
            selectedAssets.push($(this).val());
        });
        
        if(selectedAssets.length > 0) {
            // Redirect to batch print page with selected IDs
            window.open('print_multiple.php?ids=' + selectedAssets.join(','), '_blank');
        }
    });
    
    // Update batch print button state
    function updateBatchPrintButton() {
        var selectedCount = $('.asset-checkbox:checked').length;
        $('#batchPrintBtn').prop('disabled', selectedCount === 0);
        
        if(selectedCount > 0) {
            $('#batchPrintBtn').text('Print Selected Tags (' + selectedCount + ')');
        } else {
            $('#batchPrintBtn').text('Print Selected Tags');
        }
    }
});
</script>

<?php include_once "../../includes/footer.php"; ?>