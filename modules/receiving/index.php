<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\receiving\index.php
// Include header
include_once "../../includes/header.php";

// Filter by status if provided
$status_filter = "";
$filter_value = "";
if(isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = "WHERE d.status = ?";
    $filter_value = $_GET['status'];
}

// Query to get deliveries
$query = "SELECT d.*, COUNT(di.item_id) as item_count, 
            SUM(CASE WHEN di.status = 'pending' THEN 1 ELSE 0 END) as pending_items,
            u.full_name as received_by_name
          FROM deliveries d
          LEFT JOIN delivery_items di ON d.delivery_id = di.delivery_id
          LEFT JOIN users u ON d.received_by = u.user_id
          $status_filter
          GROUP BY d.delivery_id
          ORDER BY d.delivery_date DESC";

if(!empty($status_filter)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $filter_value);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>
            <i class="fas fa-truck-loading mr-2"></i>Received Deliveries
            <?php 
            if(!empty($filter_value)) {
                echo ' - <span class="badge badge-' . 
                    ($filter_value == 'received' ? 'primary' : 
                    ($filter_value == 'processing' ? 'info' : 
                    ($filter_value == 'completed' ? 'success' : 'secondary'))) . 
                    '">' . ucfirst($filter_value) . '</span>';
            }
            ?>
        </h1>
        <p class="text-muted">Manage and process received items</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="add_delivery.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Record New Delivery
        </a>
    </div>
</div>

<!-- Filter options -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter mr-1"></i>
        Filter Deliveries
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="statusFilter">Status</label>
                <select id="statusFilter" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="received" <?php echo ($filter_value == 'received') ? 'selected' : ''; ?>>Received</option>
                    <option value="processing" <?php echo ($filter_value == 'processing') ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo ($filter_value == 'completed') ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="dateFilter">Date Range</label>
                <select id="dateFilter" class="form-control">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="quarter">This Quarter</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="supplierFilter">Supplier</label>
                <select id="supplierFilter" class="form-control">
                    <option value="">All Suppliers</option>
                    <?php
                    $suppliers_query = "SELECT DISTINCT supplier FROM deliveries ORDER BY supplier";
                    $suppliers_result = mysqli_query($conn, $suppliers_query);
                    while($supplier = mysqli_fetch_assoc($suppliers_result)) {
                        if(!empty($supplier['supplier'])) {
                            echo '<option value="' . htmlspecialchars($supplier['supplier']) . '">' . 
                                htmlspecialchars($supplier['supplier']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end mb-3">
                <button id="applyFilters" class="btn btn-primary mr-2">
                    <i class="fas fa-search mr-1"></i>Apply Filters
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-redo mr-1"></i>Reset
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Deliveries List -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>
        Received Deliveries
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="deliveriesTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Delivery Date</th>
                        <th>Supplier</th>
                        <th>Reference Number</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Received By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            // Determine status based on pending items
                            $status = 'completed';
                            if($row['pending_items'] > 0) {
                                if($row['pending_items'] == $row['item_count']) {
                                    $status = 'received'; // Changed from 'pending' to 'received'
                                } else {
                                    $status = 'processing';
                                }
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $row['delivery_id'] . '</td>';
                            echo '<td>' . date('M d, Y', strtotime($row['delivery_date'])) . '</td>';
                            echo '<td>' . htmlspecialchars($row['supplier']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['reference_number'] ?? 'N/A') . '</td>';
                            echo '<td>' . $row['item_count'] . ' (' . $row['pending_items'] . ' pending)</td>';
                            
                            // Status badge
                            $badge_class = '';
                            switch($status) {
                                case 'received': $badge_class = 'primary'; break;
                                case 'processing': $badge_class = 'info'; break;
                                case 'completed': $badge_class = 'success'; break;
                                default: $badge_class = 'secondary';
                            }
                            
                            echo '<td><span class="badge badge-' . $badge_class . '">' . 
                                ucfirst($status) . '</span></td>';
                            echo '<td>' . htmlspecialchars($row['received_by_name']) . '</td>';
                            
                            // Actions
                            echo '<td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="view_delivery.php?id=' . $row['delivery_id'] . '" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="process_items.php?id=' . $row['delivery_id'] . '" class="btn btn-sm btn-primary" title="Process Items">
                                        <i class="fas fa-clipboard-check"></i>
                                    </a>
                                    <a href="edit_delivery.php?id=' . $row['delivery_id'] . '" class="btn btn-sm btn-secondary" title="Edit Delivery">
                                        <i class="fas fa-edit"></i>
                                    </a>';
                            
                            // Only show delete for received (formerly pending) deliveries
                            if($status == 'received') {
                                echo '<a href="delete_delivery.php?id=' . $row['delivery_id'] . '" class="btn btn-sm btn-danger confirm-delete" title="Delete Delivery">
                                        <i class="fas fa-trash"></i>
                                    </a>';
                            }
                            
                            echo '</div>
                                </td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">No deliveries found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#deliveriesTable').DataTable({
        order: [[1, 'desc']], // Sort by delivery date descending
        pageLength: 25
    });
    
    // Filter button click
    $('#applyFilters').click(function() {
        let url = 'index.php';
        let params = [];
        
        // Get status filter
        const status = $('#statusFilter').val();
        if(status) {
            params.push('status=' + status);
        }
        
        // Get date filter
        const dateRange = $('#dateFilter').val();
        if(dateRange) {
            params.push('date=' + dateRange);
        }
        
        // Get supplier filter
        const supplier = $('#supplierFilter').val();
        if(supplier) {
            params.push('supplier=' + encodeURIComponent(supplier));
        }
        
        // Build URL with parameters
        if(params.length > 0) {
            url += '?' + params.join('&');
        }
        
        // Redirect to filtered page
        window.location.href = url;
    });
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>