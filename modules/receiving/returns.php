<?php
// FILE PATH: asset_management_system/modules/receiving/returns.php
// Include header
include_once "../../includes/header.php";
include_once "../../config/database.php";

// Filter by status if provided
$status_filter = "";
$filter_value = "";
if(isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = "WHERE r.status = ?";
    $filter_value = $_GET['status'];
}

// Query to get returns
$query = "SELECT r.*, a.asset_tag, a.asset_name, u.full_name as returned_by_name
          FROM asset_returns r
          LEFT JOIN assets a ON r.asset_id = a.asset_id
          LEFT JOIN users u ON r.returned_by = u.user_id
          $status_filter
          ORDER BY r.return_date DESC";

if(!empty($status_filter)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $filter_value);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

// Get success message if any
$success_message = '';
if(isset($_GET['success'])) {
    if($_GET['success'] == 'processed') {
        $success_message = 'Asset return has been processed successfully.';
    }
}
?>

<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mt-4">
                <i class="fas fa-undo-alt me-2"></i>Asset Returns
                <?php 
                if(!empty($filter_value)) {
                    echo ' - <span class="badge bg-' . 
                        ($filter_value == 'pending' ? 'warning' : 
                        ($filter_value == 'approved' ? 'success' : 
                        ($filter_value == 'rejected' ? 'danger' : 'secondary'))) . 
                        '">' . ucfirst($filter_value) . '</span>';
                }
                ?>
            </h1>
            <p class="text-muted">Manage and process returned assets</p>
        </div>
        <div class="col-md-4 text-end d-flex align-items-center justify-content-end">
            <a href="add_return.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Record New Return
            </a>
        </div>
    </div>
    
    <?php if(!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Filter options -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filter Returns
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo ($filter_value == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($filter_value == 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo ($filter_value == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        <option value="repair" <?php echo ($filter_value == 'repair') ? 'selected' : ''; ?>>Needs Repair</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="dateFilter">Date Range</label>
                    <select id="dateFilter" class="form-select">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end mb-3">
                    <button id="applyFilters" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Apply Filters
                    </button>
                    <a href="returns.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Returns List -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Asset Returns
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered data-table" id="returnsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Asset</th>
                            <th>Return Date</th>
                            <th>Returned By</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if(mysqli_num_rows($result) > 0) {
                            while($row = mysqli_fetch_assoc($result)) {
                                echo '<tr>';
                                echo '<td>' . $row['return_id'] . '</td>';
                                echo '<td>' . htmlspecialchars($row['asset_tag'] . ' - ' . $row['asset_name']) . '</td>';
                                echo '<td>' . date('M d, Y', strtotime($row['return_date'])) . '</td>';
                                echo '<td>' . htmlspecialchars($row['returned_by_name']) . '</td>';
                                
                                // Condition (if processed)
                                $condition = $row['condition_on_return'] ?? 'Not assessed';
                                echo '<td>' . ucfirst($condition) . '</td>';
                                
                                // Status badge
                                $badge_class = '';
                                switch($row['status']) {
                                    case 'pending': $badge_class = 'warning'; break;
                                    case 'approved': $badge_class = 'success'; break;
                                    case 'rejected': $badge_class = 'danger'; break;
                                    case 'repair': $badge_class = 'info'; break;
                                    default: $badge_class = 'secondary';
                                }
                                
                                echo '<td><span class="badge bg-' . $badge_class . '">' . 
                                    ucfirst($row['status']) . '</span></td>';
                                
                                // Actions
                                echo '<td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="view_return.php?id=' . $row['return_id'] . '" class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>';
                                
                                // Only show process button for pending returns
                                if($row['status'] == 'pending') {
                                    echo '<a href="process_return.php?id=' . $row['return_id'] . '" class="btn btn-sm btn-primary" title="Process Return">
                                            <i class="fas fa-clipboard-check"></i>
                                          </a>';
                                }
                                
                                echo '</div>
                                    </td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" class="text-center">No returns found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#returnsTable').DataTable({
        order: [[2, 'desc']], // Sort by return date descending
        pageLength: 25
    });
    
    // Filter button click
    $('#applyFilters').click(function() {
        let url = 'returns.php';
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