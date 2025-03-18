<?php
// File: modules/reports/audit_summary.php
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
        $date_condition = "WHERE a.audit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
        break;
    case 'quarter':
        $title = "Past Quarter";
        $date_condition = "WHERE a.audit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
        break;
    case 'year':
        $title = "Past Year";
        $date_condition = "WHERE a.audit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
        break;
}

// Get summary statistics for selected period
$audit_stats_query = "SELECT 
                        COUNT(DISTINCT a.audit_id) as total_audits,
                        COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.audit_id END) as completed_audits,
                        COUNT(DISTINCT CASE WHEN a.status = 'in_progress' THEN a.audit_id END) as pending_audits,
                        SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id AND status = 'missing')) as total_missing,
                        SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id AND status = 'found')) as total_found,
                        SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id)) as total_items
                      FROM physical_audits a
                      $date_condition";
                      
$stats_result = mysqli_query($conn, $audit_stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Calculate aggregate metrics
$completion_rate = ($stats['total_audits'] > 0) ? round(($stats['completed_audits'] / $stats['total_audits']) * 100) : 0;
$accuracy_rate = ($stats['total_items'] > 0) ? round(($stats['total_found'] / $stats['total_items']) * 100) : 0;
$discrepancy_rate = ($stats['total_items'] > 0) ? round(($stats['total_missing'] / $stats['total_items']) * 100) : 0;

// Get trend data by month for charts
$trend_query = "SELECT 
                  DATE_FORMAT(a.audit_date, '%Y-%m') AS month,
                  COUNT(DISTINCT a.audit_id) AS audit_count,
                  SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id AND status = 'found')) AS found_count,
                  SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id AND status = 'missing')) AS missing_count,
                  SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id)) AS total_items
                FROM physical_audits a
                WHERE a.audit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(a.audit_date, '%Y-%m')
                ORDER BY month ASC";
                
$trend_result = mysqli_query($conn, $trend_query);

// Prepare chart data
$months = [];
$audit_counts = [];
$accuracy_rates = [];

while ($row = mysqli_fetch_assoc($trend_result)) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $audit_counts[] = $row['audit_count'];
    
    // Calculate accuracy rate for this month
    $accuracy = ($row['total_items'] > 0) ? round(($row['found_count'] / $row['total_items']) * 100) : 0;
    $accuracy_rates[] = $accuracy;
}

// Get top 5 locations with highest missing rate
$location_query = "SELECT 
                    a.location,
                    COUNT(DISTINCT a.audit_id) as audit_count,
                    SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id)) as total_items,
                    SUM((SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id AND status = 'missing')) as missing_count
                  FROM physical_audits a
                  $date_condition
                  GROUP BY a.location
                  HAVING total_items > 0
                  ORDER BY (missing_count / total_items) DESC
                  LIMIT 5";
                  
$location_result = mysqli_query($conn, $location_query);

// Get recent missing assets
$missing_query = "SELECT i.*, a.audit_date, a.location, ast.asset_tag, ast.asset_name, ast.model, ast.serial_number, ast.purchase_cost
                 FROM audit_items i
                 JOIN physical_audits a ON i.audit_id = a.audit_id
                 JOIN assets ast ON i.asset_id = ast.asset_id
                 WHERE i.status = 'missing'
                 ORDER BY a.audit_date DESC
                 LIMIT 10";
                 
$missing_result = mysqli_query($conn, $missing_query);

// Get total financial impact of missing assets
$financial_query = "SELECT 
                    SUM(ast.purchase_cost) as total_missing_value
                   FROM audit_items i
                   JOIN physical_audits a ON i.audit_id = a.audit_id
                   JOIN assets ast ON i.asset_id = ast.asset_id
                   WHERE i.status = 'missing'
                   $date_condition";
                   
$financial_result = mysqli_query($conn, $financial_query);
$financial_data = mysqli_fetch_assoc($financial_result);
$total_missing_value = $financial_data['total_missing_value'] ?: 0;
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-chart-pie mr-2"></i>Audit Summary Report</h1>
        <p class="text-muted">Comprehensive overview of audit findings and statistics - <?php echo $title; ?></p>
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
            <button type="button" class="btn btn-success" onclick="window.print();">
                <i class="fas fa-print mr-2"></i>Print
            </button>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Audits</h5>
                <h1 class="display-4"><?php echo $stats['total_audits'] ?: 0; ?></h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="audit_list.php" class="text-white">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Completion Rate</h5>
                <h1 class="display-4"><?php echo $completion_rate; ?>%</h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="audit_list.php" class="text-white">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Accuracy Rate</h5>
                <h1 class="display-4"><?php echo $accuracy_rate; ?>%</h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="#" class="text-white">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Discrepancy Rate</h5>
                <h1 class="display-4"><?php echo $discrepancy_rate; ?>%</h1>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a href="audit_discrepancy.php" class="text-white">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Audit Trend Chart -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-bar mr-1"></i>
                Audit Performance Trend (Last 6 Months)
            </div>
            <div class="card-body">
                <canvas id="auditTrendChart" width="100%" height="40"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Financial Impact Card -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-dollar-sign mr-1"></i>
                Financial Impact
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-xl-12 text-center">
                        <h3>Total Missing Asset Value</h3>
                        <h1 class="text-danger"><?php echo format_currency($total_missing_value); ?></h1>
                        <div class="mt-4">
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Asset Recovery Rate</div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $accuracy_rate; ?>%">
                                    <?php echo $accuracy_rate; ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- High Risk Locations Table -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-map-marker-alt mr-1"></i>
                Locations with Highest Missing Rate
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="locationTable">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Audits</th>
                                <th>Items</th>
                                <th>Missing</th>
                                <th>Missing Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($location = mysqli_fetch_assoc($location_result)): ?>
                                <?php 
                                $missing_rate = ($location['total_items'] > 0) 
                                    ? round(($location['missing_count'] / $location['total_items']) * 100, 1) 
                                    : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($location['location']); ?></td>
                                    <td><?php echo $location['audit_count']; ?></td>
                                    <td><?php echo $location['total_items']; ?></td>
                                    <td><?php echo $location['missing_count']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="mr-2"><?php echo $missing_rate; ?>%</div>
                                            <div class="progress w-100">
                                                <div class="progress-bar bg-danger" role="progressbar" 
                                                     style="width: <?php echo $missing_rate; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($location_result) == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Missing Assets -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Recently Missing Assets
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="missingTable">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Tag</th>
                                <th>Serial</th>
                                <th>Location</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($missing = mysqli_fetch_assoc($missing_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($missing['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($missing['asset_tag']); ?></td>
                                    <td><?php echo htmlspecialchars($missing['serial_number']); ?></td>
                                    <td><?php echo htmlspecialchars($missing['location']); ?></td>
                                    <td><?php echo format_currency($missing['purchase_cost']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($missing_result) == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No missing assets found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style media="print">
    @page {
        size: A4;
        margin: 1cm;
    }
    body {
        font-size: 12pt;
    }
    .navbar, .btn, footer, .no-print {
        display: none !important;
    }
    .card {
        border: none !important;
        margin-bottom: 30px !important;
    }
    .card-header {
        background-color: #f1f1f1 !important;
        color: #000 !important;
    }
    .text-white {
        color: #000 !important;
    }
    .bg-primary, .bg-success, .bg-info, .bg-warning {
        background-color: #f1f1f1 !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#locationTable').DataTable({
        "pageLength": 5,
        "searching": false,
        "ordering": true,
        "order": [[4, "desc"]]
    });
    
    $('#missingTable').DataTable({
        "pageLength": 5,
        "searching": false,
        "ordering": true
    });
    
    // Line Chart - Audit Trend
    var ctx = document.getElementById("auditTrendChart");
    var months = <?php echo json_encode($months); ?>;
    var auditCounts = <?php echo json_encode($audit_counts); ?>;
    var accuracyRates = <?php echo json_encode($accuracy_rates); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: "Number of Audits",
                backgroundColor: "rgba(2,117,216,0.2)",
                borderColor: "rgba(2,117,216,1)",
                pointRadius: 5,
                pointBackgroundColor: "rgba(2,117,216,1)",
                pointBorderColor: "rgba(255,255,255,0.8)",
                pointHoverRadius: 5,
                pointHoverBackgroundColor: "rgba(2,117,216,1)",
                pointHitRadius: 50,
                pointBorderWidth: 2,
                data: auditCounts,
                yAxisID: 'y-axis-1'
            }, {
                label: "Accuracy Rate (%)",
                backgroundColor: "rgba(40,167,69,0.2)",
                borderColor: "rgba(40,167,69,1)",
                pointRadius: 5,
                pointBackgroundColor: "rgba(40,167,69,1)",
                pointBorderColor: "rgba(255,255,255,0.8)",
                pointHoverRadius: 5,
                pointHoverBackgroundColor: "rgba(40,167,69,1)",
                pointHitRadius: 50,
                pointBorderWidth: 2,
                data: accuracyRates,
                yAxisID: 'y-axis-2'
            }]
        },
        options: {
            scales: {
                xAxes: [{
                    time: {
                        unit: 'month'
                    },
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 6
                    }
                }],
                yAxes: [{
                    id: 'y-axis-1',
                    type: 'linear',
                    position: 'left',
                    ticks: {
                        min: 0,
                        suggestedMax: Math.max(...auditCounts) + 2,
                        maxTicksLimit: 5
                    },
                    gridLines: {
                        color: "rgba(0, 0, 0, .125)",
                    },
                    scaleLabel: {
                        display: true,
                        labelString: 'Number of Audits'
                    }
                }, {
                    id: 'y-axis-2',
                    type: 'linear',
                    position: 'right',
                    ticks: {
                        min: 0,
                        max: 100,
                        maxTicksLimit: 5
                    },
                    gridLines: {
                        display: false
                    },
                    scaleLabel: {
                        display: true,
                        labelString: 'Accuracy Rate (%)'
                    }
                }]
            },
            legend: {
                display: true
            }
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>