<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\assignments\index.php
// Include header
include_once "../../includes/header.php";

// Filter by status if provided
$status_filter = "";
$filter_value = "";
if(isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = "WHERE aa.assignment_status = ?";
    $filter_value = $_GET['status'];
}

// Query to get asset assignments
$query = "SELECT aa.*, a.asset_name, a.asset_tag, 
            u1.full_name as assigned_to_name, u2.full_name as assigned_by_name
          FROM asset_assignments aa
          LEFT JOIN assets a ON aa.asset_id = a.asset_id
          LEFT JOIN users u1 ON aa.assigned_to = u1.user_id
          LEFT JOIN users u2 ON aa.assigned_by = u2.user_id
          $status_filter
          ORDER BY aa.assignment_date DESC";

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
            <i class="fas fa-user-check mr-2"></i>Asset Assignments
            <?php 
            if(!empty($filter_value)) {
                echo ' - <span class="badge badge-' . 
                    ($filter_value == 'assigned' ? 'primary' : 
                    ($filter_value == 'returned' ? 'success' : 'danger')) . 
                    '">' . ucfirst($filter_value) . '</span>';
            }
            ?>
        </h1>
        <p class="text-muted">Manage asset assignments and returns</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="assign.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>New Assignment
        </a>
    </div>
</div>

<!-- Filter options -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter mr-1"></i>
        Filter Assignments
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="statusFilter">Status</label>
                <select id="statusFilter" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="assigned" <?php echo ($filter_value == 'assigned') ? 'selected' : ''; ?>>Currently Assigned</option>
                    <option value="returned" <?php echo ($filter_value == 'returned') ? 'selected' : ''; ?>>Returned</option>
                    <option value="overdue" <?php echo ($filter_value == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="userFilter">Assigned To</label>
                <select id="userFilter" class="form-control">
                    <option value="">All Users</option>
                    <?php
                    $users_query = "SELECT DISTINCT u.user_id, u.full_name 
                                    FROM asset_assignments aa
                                    JOIN users u ON aa.assigned_to = u.user_id
                                    ORDER BY u.full_name";
                    $users_result = mysqli_query($conn, $users_query);
                    while($user = mysqli_fetch_assoc($users_result)) {
                        echo '<option value="' . $user['user_id'] . '">' . 
                            htmlspecialchars($user['full_name']) . '</option>';
                    }
                    ?>
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

<!-- Assignments List

<!-- Assignments List -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>
        Asset Assignments
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="assignmentsTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Asset</th>
                        <th>Assigned To</th>
                        <th>Assignment Date</th>
                        <th>Expected Return</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            // Determine status
                            $status = $row['assignment_status'];
                            if($status == 'assigned' && !empty($row['expected_return_date']) && 
                               strtotime($row['expected_return_date']) < time()) {
                                $status = 'overdue';
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $row['assignment_id'] . '</td>';
                            echo '<td>';
                            echo htmlspecialchars($row['asset_name']);
                            if(!empty($row['asset_tag'])) {
                                echo ' <small class="text-muted">(' . htmlspecialchars($row['asset_tag']) . ')</small>';
                            }
                            echo '</td>';
                            echo '<td>' . htmlspecialchars($row['assigned_to_name']) . '</td>';
                            echo '<td>' . date('M d, Y', strtotime($row['assignment_date'])) . '</td>';
                            
                            // Expected return date
                            echo '<td>';
                            if(!empty($row['expected_return_date'])) {
                                echo date('M d, Y', strtotime($row['expected_return_date']));
                                
                                // Show overdue badge
                                if($status == 'overdue') {
                                    $days_overdue = floor((time() - strtotime($row['expected_return_date'])) / 86400);
                                    echo ' <span class="badge badge-danger">' . $days_overdue . ' days overdue</span>';
                                }
                            } else {
                                echo '<span class="text-muted">Permanent</span>';
                            }
                            echo '</td>';
                            
                            // Status badge
                            $badge_class = '';
                            switch($status) {
                                case 'assigned': $badge_class = 'primary'; break;
                                case 'returned': $badge_class = 'success'; break;
                                case 'overdue': $badge_class = 'danger'; break;
                                default: $badge_class = 'secondary';
                            }
                            
                            echo '<td><span class="badge badge-' . $badge_class . '">' . 
                                ucfirst($status) . '</span></td>';
                            
                            // Actions
                            echo '<td class="text-center">';
                            echo '<div class="btn-group" role="group">';
                            
                            echo '<a href="view.php?id=' . $row['assignment_id'] . '" class="btn btn-sm btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>';
                            
                            if($row['assignment_status'] == 'assigned') {
                                echo '<a href="return.php?id=' . $row['assignment_id'] . '" class="btn btn-sm btn-success" title="Process Return">
                                        <i class="fas fa-undo-alt"></i>
                                    </a>';
                                
                                echo '<a href="edit.php?id=' . $row['assignment_id'] . '" class="btn btn-sm btn-secondary" title="Edit Assignment">
                                        <i class="fas fa-edit"></i>
                                    </a>';
                            }
                            
                            echo '</div>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center">No assignments found</td></tr>';
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
    $('#assignmentsTable').DataTable({
        order: [[3, 'desc']], // Sort by assignment date descending
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
        
        // Get user filter
        const user = $('#userFilter').val();
        if(user) {
            params.push('user=' + user);
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