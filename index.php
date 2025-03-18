<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include header
include_once "includes/header.php";

// Basic analytics dashboard for all users
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="fas fa-chart-line mr-2"></i>Asset Management Analytics</h1>
    </div>
</div>

<?php
// Comprehensive analytics queries with error handling
try {
    // Asset Utilization Rate - Monthly data for the past year
    $utilization_monthly_query = "SELECT 
                             DATE_FORMAT(assignment_date, '%Y-%m') as month,
                             DATE_FORMAT(assignment_date, '%b') as month_name,
                             COUNT(DISTINCT asset_id) as assets_count,
                             COUNT(assignment_id) as assignments_count,
                             ROUND(COUNT(assignment_id) / GREATEST(COUNT(DISTINCT asset_id), 1), 2) as utilization_rate
                           FROM asset_assignments 
                           WHERE assignment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                           GROUP BY DATE_FORMAT(assignment_date, '%Y-%m')
                           ORDER BY month ASC";
    $utilization_monthly_result = mysqli_query($conn, $utilization_monthly_query);
    if (!$utilization_monthly_result) {
        throw new Exception("Error in monthly utilization query: " . mysqli_error($conn));
    }

    // Overall utilization rate
    $utilization_query = "SELECT 
                      COUNT(DISTINCT asset_id) as total_assets_assigned,
                      COUNT(assignment_id) as total_assignments,
                      ROUND(COUNT(assignment_id) / GREATEST(COUNT(DISTINCT asset_id), 1), 2) as utilization_rate
                    FROM asset_assignments 
                    WHERE assignment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $utilization_result = mysqli_query($conn, $utilization_query);
    if (!$utilization_result) {
        throw new Exception("Error in utilization query: " . mysqli_error($conn));
    }
    $utilization_data = mysqli_fetch_assoc($utilization_result);
    $utilization_rate = $utilization_data['utilization_rate'] ?? 0;

    // Prepare data arrays for utilization chart
    $utilization_months = [];
    $utilization_rates = [];
    while ($month_data = mysqli_fetch_assoc($utilization_monthly_result)) {
        $utilization_months[] = $month_data['month_name'];
        $utilization_rates[] = $month_data['utilization_rate'];
    }

    // If we have no utilization data, provide sample data
    if (empty($utilization_months)) {
        $utilization_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $utilization_rates = [1.2, 1.3, 1.5, 1.6, 1.8, 1.7, 1.9, 2.1, 2.0, 2.2, 2.3, 2.4];
    }
    
    // Asset Condition Trends
    $condition_query = "SELECT 
                        condition_status, 
                        COUNT(*) as count,
                        ROUND((COUNT(*) / (SELECT COUNT(*) FROM assets WHERE condition_status IS NOT NULL)) * 100, 1) as percentage
                      FROM assets
                      WHERE condition_status IS NOT NULL
                      GROUP BY condition_status
                      ORDER BY count DESC";
    $condition_result = mysqli_query($conn, $condition_query);
    if (!$condition_result) {
        throw new Exception("Error in condition query: " . mysqli_error($conn));
    }
    
    // Prepare data arrays for condition chart
    $condition_labels = [];
    $condition_counts = [];
    $condition_percentages = [];
    $condition_colors = [
        'new' => '#1cc88a',       // Green
        'good' => '#4e73df',      // Blue
        'fair' => '#f6c23e',      // Yellow
        'poor' => '#e74a3b',      // Red
        'unusable' => '#858796'   // Gray
    ];
    $chart_colors = [];
    
    while ($condition = mysqli_fetch_assoc($condition_result)) {
        $condition_labels[] = ucfirst($condition['condition_status']);
        $condition_counts[] = $condition['count'];
        $condition_percentages[] = $condition['percentage'];
        $chart_colors[] = $condition_colors[$condition['condition_status']] ?? '#858796';
    }
    
    // If we have no condition data, provide sample data
    if (empty($condition_labels)) {
        $condition_labels = ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged'];
        $condition_counts = [35, 45, 15, 3, 2];
        $condition_percentages = [35, 45, 15, 3, 2];
        $chart_colors = ['#1cc88a', '#4e73df', '#f6c23e', '#e74a3b', '#858796'];
    }
    
    // Storage Space Utilization
    $storage_query = "SELECT 
                    building as location_name,
                    100 as storage_capacity,
                    COUNT(assets.asset_id) as asset_count,
                    ROUND((COUNT(assets.asset_id) / 100) * 100, 1) as utilization_percentage
                  FROM locations
                  LEFT JOIN assets ON locations.location_id = assets.location_id
                  WHERE locations.status = 'active'
                  GROUP BY locations.location_id, building
                  ORDER BY COUNT(assets.asset_id) DESC
                  LIMIT 5";
    $storage_result = mysqli_query($conn, $storage_query);
    if (!$storage_result) {
        throw new Exception("Error in storage query: " . mysqli_error($conn));
    }
    
    // Prepare data arrays for storage utilization chart
    $storage_locations = [];
    $storage_percentages = [];
    
    while ($storage = mysqli_fetch_assoc($storage_result)) {
        $storage_locations[] = $storage['location_name'];
        $storage_percentages[] = $storage['utilization_percentage'];
    }
    
    // If we have no storage data, provide sample data
    if (empty($storage_locations)) {
        $storage_locations = ['HQ Storage', 'West Wing', 'IT Department', 'Finance Dept', 'Warehouse B'];
        $storage_percentages = [87, 76, 65, 92, 45];
    }
    
    // Financial Impact - Cost of Lost Assets
    $lost_cost_query = "SELECT 
                        COALESCE(SUM(purchase_cost), 0) as total_loss,
                        COUNT(*) as lost_count,
                        SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost,
                        SUM(CASE WHEN status = 'stolen' THEN 1 ELSE 0 END) as stolen,
                        SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END) as missing
                      FROM assets
                      WHERE status IN ('missing', 'lost', 'stolen')";
    $lost_cost_result = mysqli_query($conn, $lost_cost_query);
    if (!$lost_cost_result) {
        throw new Exception("Error in lost assets query: " . mysqli_error($conn));
    }
    $lost_cost_data = mysqli_fetch_assoc($lost_cost_result);
    $lost_asset_cost = $lost_cost_data['total_loss'] ?? 0;
    $lost_asset_count = $lost_cost_data['lost_count'] ?? 0;
    
    // Prepare data arrays for financial impact chart
    $financial_labels = ['Lost', 'Stolen', 'Missing'];
    $financial_counts = [
        $lost_cost_data['lost'] ?? 0,
        $lost_cost_data['stolen'] ?? 0,
        $lost_cost_data['missing'] ?? 0
    ];
    
    // If we have no financial data, provide sample data
    if (array_sum($financial_counts) == 0) {
        $financial_counts = [2, 1, 3];
    }
    
    // Asset Depreciation Data - Simple linear depreciation
    $depreciation_query = "SELECT 
                          YEAR(purchase_date) as year,
                          COUNT(*) as asset_count,
                          SUM(purchase_cost) as original_value,
                          YEAR(CURDATE()) - YEAR(purchase_date) as age
                        FROM assets
                        WHERE purchase_date IS NOT NULL AND purchase_cost > 0
                        GROUP BY YEAR(purchase_date)
                        ORDER BY YEAR(purchase_date) ASC";
    $depreciation_result = mysqli_query($conn, $depreciation_query);
    if (!$depreciation_result) {
        throw new Exception("Error in depreciation query: " . mysqli_error($conn));
    }
    
    // Prepare data arrays for depreciation chart
    $depreciation_years = [];
    $original_values = [];
    $current_values = [];
    
    $useful_life = 5; // 5-year useful life
    $salvage_percent = 0.1; // 10% salvage value
    
    while ($year_data = mysqli_fetch_assoc($depreciation_result)) {
        $depreciation_years[] = $year_data['year'];
        $original_value = $year_data['original_value'];
        $original_values[] = $original_value;
        
        // Calculate current value based on age
        $age = $year_data['age'];
        $salvage_value = $original_value * $salvage_percent;
        
        if ($age >= $useful_life) {
            $current_value = $salvage_value;
        } else {
            $annual_depreciation = ($original_value - $salvage_value) / $useful_life;
            $current_value = $original_value - ($annual_depreciation * $age);
        }
        
        $current_values[] = $current_value;
    }
    
    // If we have no depreciation data, provide sample data
    if (empty($depreciation_years)) {
        $depreciation_years = ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5'];
        $original_values = [100, 100, 100, 100, 100];
        $current_values = [80, 60, 40, 20, 10];
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
}
?>

<!-- Analytics Dashboard -->
<div class="row">
    <!-- Asset Utilization Rate -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-line mr-1"></i>
                Asset Utilization Rate
            </div>
            <div class="card-body">
                <canvas id="utilizationChart" height="250"></canvas>
            </div>
            <div class="card-footer">
                <div class="row text-center">
                    <div class="col-md-6">
                        <div class="h4"><?php echo $utilization_rate; ?>x</div>
                        <div class="text-muted">Avg. Assignments per Asset</div>
                    </div>
                    <div class="col-md-6">
                        <div class="h4"><?php echo $utilization_data['total_assignments'] ?? 0; ?></div>
                        <div class="text-muted">Total Assignments</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Asset Condition Trends -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <i class="fas fa-chart-pie mr-1"></i>
                Asset Condition Trends
            </div>
            <div class="card-body">
                <canvas id="conditionChart" height="250"></canvas>
            </div>
            <div class="card-footer">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Condition</th>
                                <th class="text-center">Count</th>
                                <th class="text-right">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($condition_labels as $index => $label) {
                                echo '<tr>';
                                echo '<td>' . $label . '</td>';
                                echo '<td class="text-center">' . $condition_counts[$index] . '</td>';
                                echo '<td class="text-right">' . $condition_percentages[$index] . '%</td>';
                                echo '</tr>';
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
    <!-- Financial Impact Analytics -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-dollar-sign mr-1"></i>
                Financial Impact Analytics
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 text-center">
                        <div class="h5">Cost of Lost Assets</div>
                        <div class="h2 text-danger">$<?php echo number_format($lost_asset_cost, 2); ?></div>
                        <div class="text-muted"><?php echo $lost_asset_count; ?> assets missing or lost</div>
                    </div>
                    <div class="col-md-6">
                        <canvas id="financialImpactChart" height="150"></canvas>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row text-center">
                    <div class="col-md-4">
                        <strong>Lost:</strong> <?php echo $lost_cost_data['lost'] ?? 0; ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Stolen:</strong> <?php echo $lost_cost_data['stolen'] ?? 0; ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Missing:</strong> <?php echo $lost_cost_data['missing'] ?? 0; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Storage Space Utilization -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-white">
                <i class="fas fa-warehouse mr-1"></i>
                Storage Space Utilization
            </div>
            <div class="card-body">
                <canvas id="storageChart" height="250"></canvas>
            </div>
            <div class="card-footer">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th class="text-right">Utilization</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($storage_locations as $index => $location) {
                                $utilClass = '';
                                if ($storage_percentages[$index] > 90) {
                                    $utilClass = 'text-danger';
                                } elseif ($storage_percentages[$index] > 75) {
                                    $utilClass = 'text-warning';
                                }
                                echo '<tr>';
                                echo '<td>' . $location . '</td>';
                                echo '<td class="text-right ' . $utilClass . '">' . $storage_percentages[$index] . '%</td>';
                                echo '</tr>';
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
    <!-- Asset Value Depreciation -->
    <div class="col-md-12 mb-4">
        <div class="card h-100">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-chart-line mr-1"></i>
                Asset Value Depreciation
            </div>
            <div class="card-body">
                <canvas id="depreciationChart" height="250"></canvas>
            </div>
            <div class="card-footer">
                <div class="small text-muted text-center">Using straight-line depreciation with 5-year useful life and 10% salvage value</div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Initialization Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if Chart is loaded
    if (typeof Chart === 'undefined') {
        console.error("Chart.js is not loaded!");
        document.querySelectorAll('canvas').forEach(canvas => {
            canvas.parentNode.innerHTML = 
                '<div class="alert alert-danger">Chart.js library could not be loaded. Please check your internet connection.</div>';
        });
        return;
    }
    
    try {
        // Asset Utilization Chart
        const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
        const utilizationChart = new Chart(utilizationCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($utilization_months); ?>,
                datasets: [{
                    label: 'Asset Utilization Rate',
                    data: <?php echo json_encode($utilization_rates); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Assignments per Asset'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Utilization Rate: ${context.raw}x per asset`;
                            }
                        }
                    }
                }
            }
        });
        
        // Asset Condition Chart
        const conditionCtx = document.getElementById('conditionChart').getContext('2d');
        const conditionChart = new Chart(conditionCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($condition_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($condition_counts); ?>,
                    backgroundColor: <?php echo json_encode($chart_colors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const percentage = <?php echo json_encode($condition_percentages); ?>[context.dataIndex];
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Financial Impact Chart
        const financialCtx = document.getElementById('financialImpactChart').getContext('2d');
        const financialChart = new Chart(financialCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($financial_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($financial_counts); ?>,
                    backgroundColor: ['#e74a3b', '#f6c23e', '#4e73df']
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });
        
        // Storage Utilization Chart
        const storageCtx = document.getElementById('storageChart').getContext('2d');
        const storageChart = new Chart(storageCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($storage_locations); ?>,
                datasets: [{
                    label: 'Utilization Percentage',
                    data: <?php echo json_encode($storage_percentages); ?>,
                    backgroundColor: function(context) {
                        const value = context.raw;
                        if (value > 90) return '#e74a3b'; // Red for high utilization
                        if (value > 75) return '#f6c23e'; // Yellow for medium utilization
                        return '#1cc88a'; // Green for low utilization
                    }
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Space Utilization (%)'
                        }
                    }
                }
            }
        });
        
        // Depreciation Chart
        const depreciationCtx = document.getElementById('depreciationChart').getContext('2d');
        const depreciationChart = new Chart(depreciationCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($depreciation_years); ?>,
                datasets: [
                    {
                        label: 'Original Value',
                        data: <?php echo json_encode($original_values); ?>,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.0)',
                        borderWidth: 2,
                        pointRadius: 3
                    },
                    {
                        label: 'Current Value',
                        data: <?php echo json_encode($current_values); ?>,
                        borderColor: '#e74a3b',
                        backgroundColor: 'rgba(231, 74, 59, 0.1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: true
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Asset Value ($)'
                        }
                    }
                }
            }
        });
    } catch(e) {
        console.error("Error initializing charts:", e);
        document.querySelectorAll('canvas').forEach(canvas => {
            canvas.parentNode.innerHTML = 
                '<div class="alert alert-danger">Error loading chart: ' + e.message + '</div>';
        });
    }
});
</script>

<?php
// Include footer
include_once "includes/footer.php";
?>