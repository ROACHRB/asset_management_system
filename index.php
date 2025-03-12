<?php
// Include header
include_once "includes/header.php";

// Check if the user has a 'user' role
if ($_SESSION['role'] == 'user') {
    // ===============================================
    // USER DASHBOARD - Only for users with 'user' role
    // ===============================================
    
    // Get current user ID
    $user_id = $_SESSION["user_id"];
    
    // Count assigned assets
    $asset_query = "SELECT COUNT(*) as count FROM asset_assignments 
                    WHERE assigned_to = ? AND assignment_status = 'assigned'";
    $asset_stmt = mysqli_prepare($conn, $asset_query);
    mysqli_stmt_bind_param($asset_stmt, "i", $user_id);
    mysqli_stmt_execute($asset_stmt);
    $asset_result = mysqli_stmt_get_result($asset_stmt);
    $asset_row = mysqli_fetch_assoc($asset_result);
    $assigned_assets_count = $asset_row['count'];
    
    // Count upcoming returns (due within 7 days)
    $upcoming_query = "SELECT COUNT(*) as count FROM asset_assignments 
                       WHERE assigned_to = ? AND assignment_status = 'assigned' 
                       AND expected_return_date IS NOT NULL
                       AND expected_return_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                       AND expected_return_date >= CURDATE()";
    $upcoming_stmt = mysqli_prepare($conn, $upcoming_query);
    mysqli_stmt_bind_param($upcoming_stmt, "i", $user_id);
    mysqli_stmt_execute($upcoming_stmt);
    $upcoming_result = mysqli_stmt_get_result($upcoming_stmt);
    $upcoming_row = mysqli_fetch_assoc($upcoming_result);
    $upcoming_returns_count = $upcoming_row['count'];
    
    // Count overdue returns
    $overdue_query = "SELECT COUNT(*) as count FROM asset_assignments 
                      WHERE assigned_to = ? AND assignment_status = 'assigned' 
                      AND expected_return_date IS NOT NULL
                      AND expected_return_date < CURDATE()";
    $overdue_stmt = mysqli_prepare($conn, $overdue_query);
    mysqli_stmt_bind_param($overdue_stmt, "i", $user_id);
    mysqli_stmt_execute($overdue_stmt);
    $overdue_result = mysqli_stmt_get_result($overdue_stmt);
    $overdue_row = mysqli_fetch_assoc($overdue_result);
    $overdue_returns_count = $overdue_row['count'];
    
    // Get recent asset assignments
    // Get recent asset assignments
$recent_query = "SELECT a.asset_id, a.asset_name, a.asset_tag, a.model, 
c.category_name, aa.assignment_date, aa.expected_return_date
FROM asset_assignments aa
JOIN assets a ON aa.asset_id = a.asset_id
LEFT JOIN categories c ON a.category_id = c.category_id
WHERE aa.assigned_to = ?
AND aa.assignment_status = 'assigned'
AND a.status NOT IN ('pending_disposal', 'disposed')
ORDER BY aa.assignment_date DESC
LIMIT 5";
    $recent_stmt = mysqli_prepare($conn, $recent_query);
    mysqli_stmt_bind_param($recent_stmt, "i", $user_id);
    mysqli_stmt_execute($recent_stmt);
    $recent_result = mysqli_stmt_get_result($recent_stmt);
    ?>
    
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt mr-2"></i>My Dashboard</h1>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"]); ?>!</p>
        </div>
    </div>
    
    <!-- User Dashboard Cards -->
    <div class="row mb-4">
        <!-- My Assets Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card dashboard-card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-laptop dashboard-icon"></i>
                    <h5 class="card-title">My Assets</h5>
                    <h2 class="display-4"><?php echo $assigned_assets_count; ?></h2>
                    <div class="mt-2">
                        Currently Assigned
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="modules/assignments/my_assets.php" class="text-white">View Details</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <!-- Due Soon Card -->
        <?php if ($upcoming_returns_count > 0): ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card dashboard-card bg-warning text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-alt dashboard-icon"></i>
                    <h5 class="card-title">Due Soon</h5>
                    <h2 class="display-4"><?php echo $upcoming_returns_count; ?></h2>
                    <div class="mt-2">
                        Due within 7 days
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="modules/assignments/my_assets.php" class="text-white">View Details</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Overdue Card -->
        <?php if ($overdue_returns_count > 0): ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card dashboard-card bg-danger text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle dashboard-icon"></i>
                    <h5 class="card-title">Overdue</h5>
                    <h2 class="display-4"><?php echo $overdue_returns_count; ?></h2>
                    <div class="mt-2">
                        Past return date
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="modules/assignments/my_assets.php" class="text-white">View Details</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- My Assets & Quick Actions Row -->
    <div class="row">
        <!-- Recent Assets Card -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">My Recent Assets</h6>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($recent_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Category</th>
                                        <th>Assigned Date</th>
                                        <th>Return Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($asset = mysqli_fetch_assoc($recent_result)): ?>
                                        <?php
                                            // Determine if return date is upcoming or overdue
                                            $return_status = '';
                                            $return_class = '';
                                            
                                            if (!empty($asset['expected_return_date'])) {
                                                $return_date = new DateTime($asset['expected_return_date']);
                                                $today = new DateTime();
                                                
                                                if ($return_date < $today) {
                                                    $return_status = '<span class="badge badge-danger">Overdue</span>';
                                                    $return_class = 'text-danger';
                                                } elseif ($today->diff($return_date)->days <= 7) {
                                                    $return_status = '<span class="badge badge-warning">Due Soon</span>';
                                                    $return_class = 'text-warning';
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($asset['asset_tag']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($asset['category_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($asset['assignment_date'])); ?></td>
                                            <td class="<?php echo $return_class; ?>">
                                                <?php if (!empty($asset['expected_return_date'])): ?>
                                                    <?php echo date('M d, Y', strtotime($asset['expected_return_date'])); ?>
                                                    <?php echo $return_status; ?>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Permanent</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="modules/assignments/get_my_asset_details.php?id=<?php echo $asset['asset_id']; ?>" 
                                                   class="btn btn-sm btn-info view-details"
                                                   data-toggle="modal" data-target="#assetModal" data-id="<?php echo $asset['asset_id']; ?>">
                                                    <i class="fas fa-eye"></i> Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="modules/assignments/my_assets.php" class="btn btn-primary">
                                <i class="fas fa-list mr-1"></i> View All My Assets
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i> You currently do not have any assets assigned to you.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="modules/assignments/my_assets.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-laptop mr-2"></i> View My Assets
                        </a>
                        <a href="modules/users/profile.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user mr-2"></i> Update My Profile
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" 
                           data-toggle="modal" data-target="#contactSupportModal">
                            <i class="fas fa-headset mr-2"></i> Contact IT Support
                        </a>
                    </div>
                    
                    <div class="alert alert-info mt-4 mb-0">
                        <div class="d-flex align-items-center">
                            <div class="mr-3">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div>
                                <strong>Need help?</strong><br>
                                Contact the IT department for assistance with any asset-related issues.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Asset Details Modal -->
    <div class="modal fade" id="assetModal" tabindex="-1" role="dialog" aria-labelledby="assetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assetModalLabel">Asset Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="assetDetails">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contact Support Modal -->
    <div class="modal fade" id="contactSupportModal" tabindex="-1" role="dialog" aria-labelledby="contactSupportModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactSupportModalLabel">Contact IT Support</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Please contact IT support for any assistance with your assigned assets:</p>
                    
                    <div class="list-group mb-3">
                        <div class="list-group-item">
                            <i class="fas fa-envelope mr-2"></i> Email: 
                            <a href="mailto:it-support@example.com">it-support@example.com</a>
                        </div>
                        <div class="list-group-item">
                            <i class="fas fa-phone mr-2"></i> Phone: 
                            <a href="tel:+11234567890">(123) 456-7890</a>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        For urgent issues or equipment failures, please call the IT department directly.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        // Handle asset details modal
        $('.view-details').on('click', function() {
            const assetId = $(this).data('id');
            
            // Clear previous content and show loading spinner
            $('#assetDetails').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
            
            // Fetch asset details via AJAX
            $.ajax({
                url: 'modules/assignments/get_my_asset_details.php',
                type: 'GET',
                data: { id: assetId },
                success: function(response) {
                    $('#assetDetails').html(response);
                },
                error: function() {
                    $('#assetDetails').html('<div class="alert alert-danger">Error loading asset details.</div>');
                }
            });
        });
    });
    </script>

<?php
} else {
    // =================================================
    // ADMIN/STAFF DASHBOARD - For admin and staff roles
    // =================================================

    // Get counts for dashboard summary
    $total_assets_query = "SELECT COUNT(*) as total, COALESCE(SUM(purchase_cost), 0) as total_value FROM assets";
    $assigned_assets_query = "SELECT COUNT(*) as total FROM assets WHERE status = 'assigned'";
    $available_assets_query = "SELECT COUNT(*) as total FROM assets WHERE status = 'available'";
    $pending_deliveries_query = "SELECT COUNT(*) as total FROM delivery_items WHERE status = 'pending'";

    $total_assets_result = mysqli_query($conn, $total_assets_query);
    $assigned_assets_result = mysqli_query($conn, $assigned_assets_query);
    $available_assets_result = mysqli_query($conn, $available_assets_query);
    $pending_deliveries_result = mysqli_query($conn, $pending_deliveries_query);

    $total_assets_data = mysqli_fetch_assoc($total_assets_result);
    $total_assets = $total_assets_data['total'];
    $total_value = $total_assets_data['total_value'];
    $assigned_assets = mysqli_fetch_assoc($assigned_assets_result)['total'];
    $available_assets = mysqli_fetch_assoc($available_assets_result)['total'];
    $pending_deliveries = mysqli_fetch_assoc($pending_deliveries_result)['total'];

    // Get department asset values
    $dept_values_query = "SELECT 
                            COALESCE(d.department_name, 'Unassigned') as department, 
                            COUNT(a.asset_id) as asset_count, 
                            COALESCE(SUM(a.purchase_cost), 0) as total_value 
                          FROM assets a
                          LEFT JOIN locations l ON a.location_id = l.location_id
                          LEFT JOIN departments d ON l.department = d.department_id
                          GROUP BY COALESCE(d.department_name, 'Unassigned')
                          ORDER BY total_value DESC";
    $dept_values_result = mysqli_query($conn, $dept_values_query);
    
    // Get average cost per category
    $category_avg_query = "SELECT 
                            c.category_name, 
                            COUNT(a.asset_id) as asset_count,
                            AVG(a.purchase_cost) as avg_cost,
                            SUM(a.purchase_cost) as total_cost
                          FROM assets a
                          LEFT JOIN categories c ON a.category_id = c.category_id
                          GROUP BY a.category_id
                          HAVING COUNT(a.asset_id) > 0
                          ORDER BY avg_cost DESC";
    $category_avg_result = mysqli_query($conn, $category_avg_query);

    // Get yearly acquisition data for trend analysis
    $yearly_trend_query = "SELECT 
                            YEAR(purchase_date) as year, 
                            COUNT(*) as count, 
                            SUM(purchase_cost) as yearly_cost
                          FROM assets
                          WHERE purchase_date IS NOT NULL AND purchase_date != '0000-00-00'
                          GROUP BY YEAR(purchase_date)
                          ORDER BY year ASC";
    $yearly_trend_result = mysqli_query($conn, $yearly_trend_query);
    $trend_years = [];
    $trend_counts = [];
    $trend_costs = [];

    while($trend = mysqli_fetch_assoc($yearly_trend_result)) {
        $trend_years[] = $trend['year'];
        $trend_counts[] = $trend['count'];
        $trend_costs[] = $trend['yearly_cost'];
    }

    // Calculate depreciation (simplified linear depreciation)
    $depreciation_query = "SELECT 
                            a.asset_id, 
                            a.asset_name, 
                            a.purchase_date, 
                            a.purchase_cost,
                            c.category_name,
                            YEAR(CURDATE()) - YEAR(a.purchase_date) as age_years
                          FROM assets a
                          LEFT JOIN categories c ON a.category_id = c.category_id
                          WHERE a.purchase_date IS NOT NULL 
                            AND a.purchase_date != '0000-00-00'
                            AND a.purchase_cost > 0
                          ORDER BY age_years DESC
                          LIMIT 10";
    $depreciation_result = mysqli_query($conn, $depreciation_query);
    ?>

    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</h1>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-boxes dashboard-icon"></i>
                    <h5 class="card-title">Total Assets</h5>
                    <h2 class="display-4"><?php echo $total_assets; ?></h2>
                    <div class="mt-2">
                        Total Value: <?php echo format_currency($total_value); ?>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="modules/inventory/index.php" class="text-white">View Details</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card bg-success text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle dashboard-icon"></i>
                    <h5 class="card-title">Available Assets</h5>
                    <h2 class="display-4"><?php echo $available_assets; ?></h2>
                    <div class="mt-2">
                        <?php echo round(($available_assets / $total_assets) * 100, 1); ?>% of Inventory
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="modules/inventory/index.php?status=available" class="text-white">View Details</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card bg-info text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-user-check dashboard-icon"></i>
                    <h5 class="card-title">Assigned Assets</h5>
                    <h2 class="display-4"><?php echo $assigned_assets; ?></h2>
                    <div class="mt-2">
                        <?php echo round(($assigned_assets / $total_assets) * 100, 1); ?>% of Inventory
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="modules/inventory/index.php?status=assigned" class="text-white">View Details</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card dashboard-card bg-warning text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-truck-loading dashboard-icon"></i>
                    <h5 class="card-title">Pending Deliveries</h5>
                    <h2 class="display-4"><?php echo $pending_deliveries; ?></h2>
                    <div class="mt-2">
                        Awaiting Processing
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="modules/receiving/index.php?status=pending" class="text-white">View Details</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Asset Value Analytics -->
    <div class="row">
        <!-- Department Value Analysis -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-pie mr-1"></i>
                    Asset Value by Department
                </div>
                <div class="card-body">
                    <canvas id="departmentChart" height="250"></canvas>
                </div>
                <div class="card-footer">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th class="text-center">Assets</th>
                                    <th class="text-right">Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($dept_values_result, 0);
                                $dept_counter = 0;
                                while($dept = mysqli_fetch_assoc($dept_values_result)) {
                                    // Only show top 5 departments in table
                                    if($dept_counter++ < 5) {
                                        echo '<tr>';
                                        // Check if department is "Unassigned" and replace with a better name
                                        $display_department = ($dept['department'] == 'Unassigned') ? 'General Storage' : $dept['department'];
                                        echo '<td>' . htmlspecialchars($display_department) . '</td>';
                                        echo '<td class="text-center">' . $dept['asset_count'] . '</td>';
                                        echo '<td class="text-right">' . format_currency($dept['total_value']) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Cost by Category -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-bar mr-1"></i>
                    Average Cost per Asset Category
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
                <div class="card-footer">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-center">Count</th>
                                    <th class="text-right">Avg. Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($category_avg_result, 0);
                                $cat_counter = 0;
                                while($cat = mysqli_fetch_assoc($category_avg_result)) {
                                    // Only show top 5 categories in table
                                    if($cat_counter++ < 5) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($cat['category_name'] ?? 'Uncategorized') . '</td>';
                                        echo '<td class="text-center">' . $cat['asset_count'] . '</td>';
                                        echo '<td class="text-right">' . format_currency($cat['avg_cost']) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Depreciation Analysis -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-line mr-1"></i>
                    Depreciation Value Calculations
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Age (Years)</th>
                                    <th>Original Value</th>
                                    <th>Current Value</th>
                                    <th>Depreciation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                while($asset = mysqli_fetch_assoc($depreciation_result)) {
                                    // Simple straight-line depreciation calculation
                                    // Assuming 5-year useful life and 10% salvage value
                                    $useful_life = 5; // years
                                    $salvage_percent = 0.1; // 10%
                                    
                                    $age = $asset['age_years'];
                                    $original_value = $asset['purchase_cost'];
                                    $salvage_value = $original_value * $salvage_percent;
                                    
                                    // Calculate current value based on age
                                    if($age >= $useful_life) {
                                        $current_value = $salvage_value;
                                    } else {
                                        $annual_depreciation = ($original_value - $salvage_value) / $useful_life;
                                        $current_value = $original_value - ($annual_depreciation * $age);
                                    }
                                    
                                    $depreciation_amount = $original_value - $current_value;
                                    $depreciation_percent = ($depreciation_amount / $original_value) * 100;
                                    
                                    echo '<tr>';
                                    echo '<td title="' . htmlspecialchars($asset['asset_name']) . '">' . 
                                         htmlspecialchars(substr($asset['asset_name'], 0, 25)) . 
                                         (strlen($asset['asset_name']) > 25 ? '...' : '') . '</td>';
                                    echo '<td>' . $age . '</td>';
                                    echo '<td>' . format_currency($original_value) . '</td>';
                                    echo '<td>' . format_currency($current_value) . '</td>';
                                    echo '<td class="text-danger">' . round($depreciation_percent, 1) . '%</td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <small><i class="fas fa-info-circle mr-1"></i> Using straight-line depreciation with 5-year useful life and 10% salvage value</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Cost Trend Analysis -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-area mr-1"></i>
                    Asset Cost Trend Analysis
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="250"></canvas>
                </div>
                <div class="card-footer text-center">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-muted">Total Acquisition Cost</div>
                            <div class="h4"><?php echo format_currency($total_value); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted">Avg. Cost per Asset</div>
                            <div class="h4"><?php echo format_currency($total_assets > 0 ? $total_value / $total_assets : 0); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart JS for Data Visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Department Value Chart
        <?php
        mysqli_data_seek($dept_values_result, 0);
        $dept_labels = [];
        $dept_values = [];
        $dept_counts = [];
        $dept_counter = 0;
        
        while($dept = mysqli_fetch_assoc($dept_values_result)) {
            // Limit to top 6 departments for chart clarity
            if($dept_counter++ < 6) {
                // Replace "Unassigned" with your preferred department name
                $display_department = ($dept['department'] == 'Unassigned') ? 'General Storage' : $dept['department'];
                $dept_labels[] = $display_department;
                $dept_values[] = $dept['total_value'];
                $dept_counts[] = $dept['asset_count'];
            }
        }
        ?>
        
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        const departmentChart = new Chart(departmentCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($dept_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($dept_values); ?>,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', '#60616f'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const formattedValue = new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD'
                                }).format(value);
                                const count = <?php echo json_encode($dept_counts); ?>[context.dataIndex];
                                return `${label}: ${formattedValue} (${count} assets)`;
                            }
                        }
                    },
                    legend: {
                        position: 'right',
                    }
                },
            }
        });

        // Category Average Cost Chart
        <?php
        mysqli_data_seek($category_avg_result, 0);
        $cat_labels = [];
        $cat_avgs = [];
        $cat_counter = 0;
        
        while($cat = mysqli_fetch_assoc($category_avg_result)) {
            // Limit to top 6 categories for chart clarity
            if($cat_counter++ < 6) {
                $cat_labels[] = $cat['category_name'] ?? 'Uncategorized';
                $cat_avgs[] = $cat['avg_cost'];
            }
        }
        ?>
        
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($cat_labels); ?>,
                datasets: [{
                    label: 'Average Cost',
                    data: <?php echo json_encode($cat_avgs); ?>,
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderColor: '#4e73df',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw;
                                return `${label}: ${new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD'
                                }).format(value)}`;
                            }
                        }
                    }
                }
            }
        });

        // Cost Trend Analysis Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_years); ?>,
                datasets: [
                    {
                        label: 'Yearly Acquisition Cost',
                        data: <?php echo json_encode($trend_costs); ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointBorderColor: 'rgba(78, 115, 223, 1)',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Assets Acquired',
                        data: <?php echo json_encode($trend_counts); ?>,
                        backgroundColor: 'transparent',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                        pointBorderColor: 'rgba(28, 200, 138, 1)',
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderDash: [5, 5],
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Assets'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cost ($)'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw;
                                if (label === 'Yearly Acquisition Cost') {
                                    return `${label}: ${new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD'
                                    }).format(value)}`;
                                } else {
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    }
                }
            }
        });
    });
    </script>

<?php
} // End of admin/staff dashboard

// Include footer
include_once "includes/footer.php";
?>