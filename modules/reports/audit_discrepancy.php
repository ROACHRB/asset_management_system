<?php
// File: modules/reports/audit_discrepancy.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('generate_reports')) {
    $_SESSION['error'] = "Access denied. You don't have permission to access reports.";
    header("Location: ../../index.php");
    exit;
}

// Set time period for filtering
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$title = "All Time";
$date_condition = "";

switch ($period) {
    case 'month':
        $title = "Past Month";
        $date_condition = "AND a.audit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
        break;
    case 'quarter':
        $title = "Past Quarter";
        $date_condition = "AND a.audit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
        break;
    case 'year':
        $title = "Past Year";
        $date_condition = "AND a.audit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
        break;
}

// Get all missing assets
$missing_query = "SELECT i.*, a.audit_date, a.location, a.status as audit_status, a.completed_date,
                        ast.asset_id, ast.asset_tag, ast.asset_name, ast.serial_number, ast.model, 
                        ast.manufacturer, ast.purchase_date, ast.purchase_cost, ast.condition_status,
                        cat.category_name, u.full_name as assigned_to
                 FROM audit_items i
                 JOIN physical_audits a ON i.audit_id = a.audit_id
                 JOIN assets ast ON i.asset_id = ast.asset_id
                 LEFT JOIN categories cat ON ast.category_id = cat.category_id
                 LEFT JOIN asset_assignments aa ON ast.asset_id = aa.asset_id AND aa.actual_return_date IS NULL
                 LEFT JOIN users u ON aa.assigned_to = u.user_id
                 WHERE i.status = 'missing' $date_condition
                 ORDER BY a.audit_date DESC, ast.asset_name";
                 
$missing_result = mysqli_query($conn, $missing_query);

// Get wrong location assets
$wrong_location_query = "SELECT i.*, a.audit_date, a.location, a.status as audit_status, a.completed_date,
                              ast.asset_id, ast.asset_tag, ast.asset_name, ast.serial_number, ast.model, 
                              ast.manufacturer, ast.purchase_date, ast.purchase_cost,
                              l.building, l.room, l.location_id,
                              cat.category_name, u.full_name as assigned_to
                         FROM audit_items i
                         JOIN physical_audits a ON i.audit_id = a.audit_id
                         JOIN assets ast ON i.asset_id = ast.asset_id
                         LEFT JOIN locations l ON ast.location_id = l.location_id
                         LEFT JOIN categories cat ON ast.category_id = cat.category_id
                         LEFT JOIN asset_assignments aa ON ast.asset_id = aa.asset_id AND aa.actual_return_date IS NULL
                         LEFT JOIN users u ON aa.assigned_to = u.user_id
                         WHERE i.status = 'wrong_location' $date_condition
                         ORDER BY a.audit_date DESC, ast.asset_name";
                         
$wrong_location_result = mysqli_query($conn, $wrong_location_query);

// Get total counts and financial impact
$count_query = "SELECT 
                  COUNT(DISTINCT CASE WHEN i.status = 'missing' THEN i.item_id END) as missing_count,
                  COUNT(DISTINCT CASE WHEN i.status = 'wrong_location' THEN i.item_id END) as wrong_location_count,
                  SUM(CASE WHEN i.status = 'missing' THEN ast.purchase_cost ELSE 0 END) as missing_value,
                  COUNT(DISTINCT ast.category_id) as affected_categories,
                  COUNT(DISTINCT a.location) as affected_locations
                FROM audit_items i
                JOIN physical_audits a ON i.audit_id = a.audit_id
                JOIN assets ast ON i.asset_id = ast.asset_id
                WHERE (i.status = 'missing' OR i.status = 'wrong_location') $date_condition";
                
$count_result = mysqli_query($conn, $count_query);
$stats = mysqli_fetch_assoc($count_result);

// Get category breakdown for missing assets
$category_query = "SELECT 
                     cat.category_name,
                     COUNT(DISTINCT i.item_id) as missing_count,
                     SUM(ast.purchase_cost) as missing_value
                   FROM audit_items i
                   JOIN physical_audits a ON i.audit_id = a.audit_id
                   JOIN assets ast ON i.asset_id = ast.asset_id
                   LEFT JOIN categories cat ON ast.category_id = cat.category_id
                   WHERE i.status = 'missing' $date_condition
                   GROUP BY cat.category_id, cat.category_name
                   ORDER BY missing_count DESC";
                   
$category_result = mysqli_query($conn, $category_query);

// Get location breakdown for missing assets
$location_query = "SELECT 
                     a.location,
                     COUNT(DISTINCT i.item_id) as missing_count,
                     SUM(ast.purchase_cost) as missing_value
                   FROM audit_items i
                   JOIN physical_audits a ON i.audit_id = a.audit_id
                   JOIN assets ast ON i.asset_id = ast.asset_id
                   WHERE i.status = 'missing' $date_condition
                   GROUP BY a.location
                   ORDER BY missing_count DESC
                   LIMIT 10";
                   
$location_result = mysqli_query($conn, $location_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-exclamation-triangle mr-2"></i>Audit Discrepancy Analysis</h1>
        <p class="text-muted">Analysis of missing and misplaced assets - <?php echo $title; ?></p>
    </div>
    <div class="col-md-4 text-right">
        <div class="btn-group">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                <i class="fas fa-calendar mr-2"></i>Time Period
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item <?php echo ($period == 'all') ? 'active' : ''; ?>" href="?period=all">All Time</a>
                <a class="dropdown-item <?php echo ($period == 'year') ? 'active' : ''; ?>" href="?period=year">Past Year</a>
                <a class="dropdown-item <?php echo ($period == 'quarter') ? 'active' : ''; ?>" href="?period=quarter">Past Quarter</a>
                <a class="dropdown-item <?php echo ($period == 'month') ? 'active' : ''; ?>" href="?period=month">Past Month</a>
            </div>
            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel mr-2"></i>Export
            </button>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Missing Assets</h5>
                <h1 class="display-4"><?php echo $stats['missing_count'] ?: 0; ?></h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-white">Total count</span>
                <div class="small text-white"><i class="fas fa-calendar-alt"></i> <?php echo $title; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Misplaced Assets</h5>
                <h1 class="display-4"><?php echo $stats['wrong_location_count'] ?: 0; ?></h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-white">Wrong location</span>
                <div class="small text-white"><i class="fas fa-calendar-alt"></i> <?php echo $title; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Financial Impact</h5>
                <h1 class="display-4"><?php echo format_currency($stats['missing_value'] ?: 0); ?></h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-white">Missing asset value</span>
                <div class="small text-white"><i class="fas fa-calendar-alt"></i> <?php echo $title; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Affected Locations</h5>
                <h1 class="display-4"><?php echo $stats['affected_locations'] ?: 0; ?></h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-white">Locations with discrepancies</span>
                <div class="small text-white"><i class="fas fa-calendar-alt"></i> <?php echo $title; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Category Analysis -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-th-list mr-1"></i>
                Missing Assets by Category
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="categoryTable">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Missing Count</th>
                                <th>Value</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_missing = $stats['missing_count'] ?: 1; // Avoid division by zero
                            while($category = mysqli_fetch_assoc($category_result)): 
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category_name'] ?: 'Uncategorized'); ?></td>
                                    <td><?php echo $category['missing_count']; ?></td>
                                    <td><?php echo format_currency($category['missing_value']); ?></td>
                                    <td>
                                        <?php 
                                        $percentage = round(($category['missing_count'] / $total_missing) * 100, 1);
                                        echo $percentage . '%';
                                        ?>
                                        <div class="progress mt-1">
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($category_result) == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Location Analysis -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-map-marker-alt mr-1"></i>
                Top Locations with Missing Assets
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="locationTable">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Missing Count</th>
                                <th>Value</th>
                                <th>% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_missing = $stats['missing_count'] ?: 1; // Avoid division by zero
                            while($location = mysqli_fetch_assoc($location_result)): 
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($location['location']); ?></td>
                                    <td><?php echo $location['missing_count']; ?></td>
                                    <td><?php echo format_currency($location['missing_value']); ?></td>
                                    <td>
                                        <?php 
                                        $percentage = round(($location['missing_count'] / $total_missing) * 100, 1);
                                        echo $percentage . '%';
                                        ?>
                                        <div class="progress mt-1">
                                            <div class="progress-bar bg-danger" role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($location_result) == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Nav tabs for Missing and Misplaced assets -->
<div class="card mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="discrepancyTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="missing-tab" data-toggle="tab" href="#missing" role="tab">
                    Missing Assets (<?php echo $stats['missing_count'] ?: 0; ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="misplaced-tab" data-toggle="tab" href="#misplaced" role="tab">
                    Misplaced Assets (<?php echo $stats['wrong_location_count'] ?: 0; ?>)
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="discrepancyTabContent">
            <!-- Missing Assets Tab -->
            <div class="tab-pane fade show active" id="missing" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="missingTable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Serial Number</th>
                                <th>Value</th>
                                <th>Location</th>
                                <th>Assigned To</th>
                                <th>Audit Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($missing = mysqli_fetch_assoc($missing_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($missing['asset_tag']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($missing['asset_name']); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($missing['manufacturer'] . ' ' . $missing['model']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($missing['category_name'] ?: 'Uncategorized'); ?></td>
                                    <td><?php echo htmlspecialchars($missing['serial_number']); ?></td>
                                    <td><?php echo format_currency($missing['purchase_cost']); ?></td>
                                    <td><?php echo htmlspecialchars($missing['location']); ?></td>
                                    <td><?php echo htmlspecialchars($missing['assigned_to'] ?: 'Not Assigned'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($missing['audit_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($missing_result) == 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No missing assets found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Misplaced Assets Tab -->
            <div class="tab-pane fade" id="misplaced" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="misplacedTable">
                        <thead>
                            <tr>
                                <th>Asset Tag</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Serial Number</th>
                                <th>Expected Location</th>
                                <th>Found At</th>
                                <th>Assigned To</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($wrong = mysqli_fetch_assoc($wrong_location_result)): ?>
                                <?php 
                                $expected_location = '';
                                if(!empty($wrong['building'])) {
                                    $expected_location = htmlspecialchars($wrong['building']);
                                    if(!empty($wrong['room'])) {
                                        $expected_location .= ' - ' . htmlspecialchars($wrong['room']);
                                    }
                                } else {
                                    $expected_location = 'Unknown';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($wrong['asset_tag']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($wrong['asset_name']); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($wrong['manufacturer'] . ' ' . $wrong['model']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($wrong['category_name'] ?: 'Uncategorized'); ?></td>
                                    <td><?php echo htmlspecialchars($wrong['serial_number']); ?></td>
                                    <td><?php echo $expected_location; ?></td>
                                    <td><?php echo htmlspecialchars($wrong['location']); ?></td>
                                    <td><?php echo htmlspecialchars($wrong['assigned_to'] ?: 'Not Assigned'); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($wrong['notes'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($wrong_location_result) == 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No misplaced assets found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#categoryTable').DataTable({
        "pageLength": 10,
        "ordering": true,
        "order": [[1, "desc"]]
    });
    
    $('#locationTable').DataTable({
        "pageLength": 10,
        "ordering": true,
        "order": [[1, "desc"]]
    });
    
    $('#missingTable').DataTable({
        "pageLength": 25,
        "ordering": true,
        "order": [[7, "desc"]] // Order by audit date
    });
    
    $('#misplacedTable').DataTable({
        "pageLength": 25,
        "ordering": true
    });
});

// Function to export table data to Excel
function exportToExcel() {
    // Get active tab
    const activeTab = $('#discrepancyTabs .nav-link.active').attr('href');
    const tableId = (activeTab === '#missing') ? 'missingTable' : 'misplacedTable';
    const title = (activeTab === '#missing') ? 'Missing_Assets' : 'Misplaced_Assets';
    
    // Create a new workbook
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    
    // Add the worksheet to the workbook
    XLSX.utils.book_append_sheet(wb, ws, title);
    
    // Save the workbook
    XLSX.writeFile(wb, title + '_<?php echo str_replace(' ', '_', $title); ?>.xlsx');
}
</script>

<!-- Include SheetJS library for Excel export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<?php include_once "../../includes/footer.php"; ?>