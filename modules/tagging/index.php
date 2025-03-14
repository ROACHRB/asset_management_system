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
            <form id="assetsForm">
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
                                        <input type="checkbox" class="asset-checkbox" name="asset_ids[]" value="<?php echo $asset['asset_id']; ?>">
                                    </td>
                                    <td><?php echo htmlspecialchars($asset['asset_tag'] ?? 'No Tag'); ?></td>
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
            </form>
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
    var table = $('#assetsTable').DataTable({
        "order": [[1, "asc"]], // Sort by Asset Tag by default
        "columnDefs": [
            { "orderable": false, "targets": [0, 6] } // Disable sorting on checkbox and actions columns
        ],
        "drawCallback": function() {
            // When DataTable redraws (filtering, sorting, pagination), maintain checkbox states
            updateBatchPrintButton();
        }
    });
    
    // Select All checkbox - use direct click handler
    $('#selectAll').on('click', function() {
        var isChecked = $(this).prop('checked');
        console.log('Select All clicked, checked:', isChecked);
        
        // Apply to all visible checkboxes
        $('.asset-checkbox:visible').prop('checked', isChecked);
        
        // Update the button state
        updateBatchPrintButton();
    });
    
    // Individual checkbox click handler - use event delegation to handle dynamically created elements
    $(document).on('change', '.asset-checkbox', function() {
        console.log('Checkbox changed:', $(this).val(), 'checked:', $(this).prop('checked'));
        
        // Update "Select All" checkbox state
        var totalVisible = $('.asset-checkbox:visible').length;
        var totalChecked = $('.asset-checkbox:visible:checked').length;
        
        $('#selectAll').prop('checked', totalVisible > 0 && totalChecked === totalVisible);
        
        // Update button
        updateBatchPrintButton();
    });
    
    // Batch print button click handler
    $('#batchPrintBtn').click(function(e) {
        e.preventDefault();
        
        // Use a more direct approach to collect checked boxes
        var selectedAssets = [];
        $('.asset-checkbox:checked').each(function() {
            selectedAssets.push($(this).val());
        });
        
        console.log('Selected assets for printing:', selectedAssets);
        
        if(selectedAssets.length > 0) {
            // Generate URL and open in new window
            var url = 'print_multiple.php?ids=' + selectedAssets.join(',');
            console.log('Opening URL:', url);
            window.open(url, '_blank');
        } else {
            alert('Please select at least one asset to print.');
        }
    });
    
    // Update batch print button state
    function updateBatchPrintButton() {
        var selectedCount = $('.asset-checkbox:checked').length;
        console.log('Updated button state, selected count:', selectedCount);
        
        $('#batchPrintBtn').prop('disabled', selectedCount === 0);
        
        if(selectedCount > 0) {
            $('#batchPrintBtn').text('Print Selected Tags (' + selectedCount + ')');
        } else {
            $('#batchPrintBtn').text('Print Selected Tags');
        }
    }
    
    // Initialize button state
    updateBatchPrintButton();
});
</script>

<?php include_once "../../includes/footer.php"; ?>