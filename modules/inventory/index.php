<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\index.php
// Include header
include_once "../../includes/header.php";

// Filter by status if provided
$status_filter = "";
$filter_value = "";
if(isset($_GET['status']) && !empty($_GET['status'])) {
    $status_filter = "WHERE a.status = ?";
    $filter_value = $_GET['status'];
}

// Query to get assets
$query = "SELECT a.*, c.category_name, l.building, l.room 
          FROM assets a
          LEFT JOIN categories c ON a.category_id = c.category_id
          LEFT JOIN locations l ON a.location_id = l.location_id
          $status_filter
          ORDER BY a.asset_id DESC";

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
            <i class="fas fa-boxes mr-2"></i>Asset Inventory
            <?php 
            if(!empty($filter_value)) {
                echo ' - <span class="badge badge-' . 
                    ($filter_value == 'available' ? 'success' : 
                    ($filter_value == 'assigned' ? 'primary' : 
                    ($filter_value == 'under_repair' ? 'warning' : 'secondary'))) . 
                    '">' . ucfirst(str_replace('_', ' ', $filter_value)) . '</span>';
            }
            ?>
        </h1>
        <p class="text-muted">Manage and track all assets</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Add New Asset
        </a>
    </div>
</div>

<!-- Filter options -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter mr-1"></i>
        Filter Assets
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="statusFilter">Status</label>
                <select id="statusFilter" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="available" <?php echo ($filter_value == 'available') ? 'selected' : ''; ?>>Available</option>
                    <option value="assigned" <?php echo ($filter_value == 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                    <option value="under_repair" <?php echo ($filter_value == 'under_repair') ? 'selected' : ''; ?>>Under Repair</option>
                    <option value="disposed" <?php echo ($filter_value == 'disposed') ? 'selected' : ''; ?>>Disposed</option>
                    <option value="lost" <?php echo ($filter_value == 'lost') ? 'selected' : ''; ?>>Lost</option>
                    <option value="stolen" <?php echo ($filter_value == 'stolen') ? 'selected' : ''; ?>>Stolen</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="categoryFilter">Category</label>
                <select id="categoryFilter" class="form-control">
                    <option value="">All Categories</option>
                    <?php
                    $categories_query = "SELECT * FROM categories ORDER BY category_name";
                    $categories_result = mysqli_query($conn, $categories_query);
                    while($category = mysqli_fetch_assoc($categories_result)) {
                        echo '<option value="' . $category['category_id'] . '">' . 
                            htmlspecialchars($category['category_name']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="locationFilter">Location</label>
                <select id="locationFilter" class="form-control">
                    <option value="">All Locations</option>
                    <?php
                    $locations_query = "SELECT * FROM locations ORDER BY building, room";
                    $locations_result = mysqli_query($conn, $locations_query);
                    while($location = mysqli_fetch_assoc($locations_result)) {
                        $location_name = htmlspecialchars($location['building']);
                        if(!empty($location['room'])) {
                            $location_name .= ' - ' . htmlspecialchars($location['room']);
                        }
                        echo '<option value="' . $location['location_id'] . '">' . $location_name . '</option>';
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

<!-- Assets List -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>
        Assets Inventory
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="assetsTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Purchase Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            echo '<tr>';
                            echo '<td>' . $row['asset_id'] . '</td>';
                            echo '<td>' . (!empty($row['asset_tag']) ? htmlspecialchars($row['asset_tag']) : '<span class="badge badge-warning">Not Tagged</span>') . '</td>';
                            echo '<td>' . htmlspecialchars($row['asset_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['category_name'] ?? 'Uncategorized') . '</td>';
                            
                            // Set badge color based on status
                            $badge_class = '';
                            switch($row['status']) {
                                case 'available': $badge_class = 'success'; break;
                                case 'assigned': $badge_class = 'primary'; break;
                                case 'under_repair': $badge_class = 'warning'; break;
                                case 'disposed': $badge_class = 'secondary'; break;
                                case 'lost': 
                                case 'stolen': $badge_class = 'danger'; break;
                                default: $badge_class = 'info';
                            }
                            
                            echo '<td><span class="badge badge-' . $badge_class . '">' . 
                                ucfirst(str_replace('_', ' ', $row['status'])) . '</span></td>';
                            
                            // Location display
                            $location = 'Not Assigned';
                            if(!empty($row['building'])) {
                                $location = htmlspecialchars($row['building']);
                                if(!empty($row['room'])) {
                                    $location .= ' - ' . htmlspecialchars($row['room']);
                                }
                            }
                            echo '<td>' . $location . '</td>';
                            
                            // Purchase date
                            echo '<td>' . (!empty($row['purchase_date']) ? date('M d, Y', strtotime($row['purchase_date'])) : 'N/A') . '</td>';
                            
                            // Actions
                            echo '<td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=' . $row['asset_id'] . '" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=' . $row['asset_id'] . '" class="btn btn-sm btn-primary" title="Edit Asset">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=' . $row['asset_id'] . '" class="btn btn-sm btn-danger confirm-delete" title="Delete Asset">
                                        <i class="fas fa-trash"></i>
                                    </a>';
                            
                            // If asset needs tagging, add tag button
                            if(empty($row['asset_tag']) || empty($row['qr_code'])) {
                                echo '<a href="../tagging/generate_tag.php?id=' . $row['asset_id'] . '" class="btn btn-sm btn-warning" title="Generate Tag">
                                    <i class="fas fa-tag"></i>
                                </a>';
                            }
                            
                            echo '</div>
                                </td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">No assets found</td></tr>';
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
    $('#assetsTable').DataTable({
        order: [[0, 'desc']], // Sort by ID descending
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
        
        // Get category filter
        const category = $('#categoryFilter').val();
        if(category) {
            params.push('category=' + category);
        }
        
        // Get location filter
        const location = $('#locationFilter').val();
        if(location) {
            params.push('location=' + location);
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