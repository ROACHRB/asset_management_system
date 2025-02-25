<?php
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Check if user has permission to generate reports
enforce_permission('generate_reports', '../dashboard/index.php');

// Default depreciation rate (annual percentage)
$default_depreciation_rate = 20; // 20% per year

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $category = isset($_POST['category']) ? intval($_POST['category']) : null;
    $min_age = isset($_POST['min_age']) && $_POST['min_age'] !== '' ? intval($_POST['min_age']) : null;
    $max_age = isset($_POST['max_age']) && $_POST['max_age'] !== '' ? intval($_POST['max_age']) : null;
    $depreciation_rate = isset($_POST['depreciation_rate']) && $_POST['depreciation_rate'] !== '' ? 
                         floatval($_POST['depreciation_rate']) : $default_depreciation_rate;
    
    // Build query
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    $where_clauses[] = "a.purchase_date IS NOT NULL";
    $where_clauses[] = "a.purchase_cost > 0";
    
    if($category) {
        $where_clauses[] = "a.category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }
    
    // Calculate age in years based on purchase date
    $today = date('Y-m-d');
    
    if($min_age !== null) {
        $min_date = date('Y-m-d', strtotime("-$min_age years", strtotime($today)));
        $where_clauses[] = "a.purchase_date <= ?";
        $params[] = $min_date;
        $param_types .= "s";
    }
    
    if($max_age !== null) {
        $max_date = date('Y-m-d', strtotime("-$max_age years", strtotime($today)));
        $where_clauses[] = "a.purchase_date >= ?";
        $params[] = $max_date;
        $param_types .= "s";
    }
    
    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Main assets query
    $sql = "SELECT a.*, c.category_name,
                  DATEDIFF(CURRENT_DATE(), a.purchase_date) / 365.25 as age_years
            FROM assets a
            LEFT JOIN categories c ON a.category_id = c.category_id
            $where_sql
            ORDER BY a.purchase_date ASC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if($params) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Get total value by category
    $cat_sql = "SELECT c.category_id, c.category_name, 
                      COUNT(a.asset_id) as asset_count,
                      SUM(a.purchase_cost) as total_purchase_cost,
                      AVG(DATEDIFF(CURRENT_DATE(), a.purchase_date) / 365.25) as avg_age
                FROM assets a
                LEFT JOIN categories c ON a.category_id = c.category_id
                $where_sql
                GROUP BY c.category_id
                ORDER BY total_purchase_cost DESC";
    
    $cat_stmt = mysqli_prepare($conn, $cat_sql);
    if($params) {
        mysqli_stmt_bind_param($cat_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($cat_stmt);
    $cat_result = mysqli_stmt_get_result($cat_stmt);
    
    // Get value distribution by age
    $age_sql = "SELECT 
                    FLOOR(DATEDIFF(CURRENT_DATE(), a.purchase_date) / 365.25) as age_year,
                    COUNT(a.asset_id) as asset_count,
                    SUM(a.purchase_cost) as total_purchase_cost
                FROM assets a
                $where_sql
                GROUP BY age_year
                ORDER BY age_year ASC";
    
    $age_stmt = mysqli_prepare($conn, $age_sql);
    if($params) {
        mysqli_stmt_bind_param($age_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($age_stmt);
    $age_result = mysqli_stmt_get_result($age_stmt);
}

// Get categories for filters
$categories_result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-chart-area mr-2"></i>Asset Value Report</h1>
        <p class="text-muted">Track asset valuation and depreciation over time</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Reports
        </a>
    </div>
</div>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter mr-1"></i>Report Filters
    </div>
    <div class="card-body">
        <form method="post" id="reportForm">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category">
                            <option value="">All Categories</option>
                            <?php mysqli_data_seek($categories_result, 0); ?>
                            <?php while($cat = mysqli_fetch_assoc($categories_result)): ?>
                                <option value="<?php echo $cat['category_id']; ?>"
                                    <?php if(isset($_POST['category']) && $_POST['category'] == $cat['category_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Age Range (Years)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="min_age" placeholder="Min" min="0" step="1"
                                   value="<?php echo isset($_POST['min_age']) ? $_POST['min_age'] : ''; ?>">
                            <div class="input-group-prepend input-group-append">
                                <span class="input-group-text">-</span>
                            </div>
                            <input type="number" class="form-control" name="max_age" placeholder="Max" min="0" step="1"
                                   value="<?php echo isset($_POST['max_age']) ? $_POST['max_age'] : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Depreciation Rate (%/year)</label>
                        <input type="number" class="form-control" name="depreciation_rate" min="0" max="100" step="0.1"
                               value="<?php echo isset($_POST['depreciation_rate']) ? $_POST['depreciation_rate'] : $default_depreciation_rate; ?>">
                        <small class="form-text text-muted">Annual percentage rate</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group d-flex align-items-end h-100 mb-0">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i>Generate Report
                        </button>
                        <?php if(isset($result) && mysqli_num_rows($result) > 0): ?>
                        <button type="button" class="btn btn-success ml-2" onclick="exportToExcel()">
                            <i class="fas fa-file-excel mr-1"></i>Export
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Report Results -->
<?php if(isset($result) && mysqli_num_rows($result) > 0): ?>
<!-- Value Summary -->
<?php
$total_assets = mysqli_num_rows($result);
$total_purchase_value = 0;
$total_current_value = 0;
$total_depreciation = 0;
$depreciation_rate = isset($_POST['depreciation_rate']) ? floatval($_POST['depreciation_rate']) / 100 : $default_depreciation_rate / 100;

mysqli_data_seek($result, 0);
while($row = mysqli_fetch_assoc($result)) {
    $purchase_cost = floatval($row['purchase_cost']);
    $age_years = floatval($row['age_years']);
    
    // Calculate current value using straight-line depreciation
    $depreciation_factor = max(0, 1 - ($depreciation_rate * $age_years));
    $current_value = $purchase_cost * $depreciation_factor;
    
    // Add to totals
    $total_purchase_value += $purchase_cost;
    $total_current_value += $current_value;
    $total_depreciation += ($purchase_cost - $current_value);
}
mysqli_data_seek($result, 0);

$depreciation_percentage = $total_purchase_value > 0 ? 
    (($total_purchase_value - $total_current_value) / $total_purchase_value) * 100 : 0;
?>

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Purchase Value</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_purchase_value, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tags fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Current Value</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_current_value, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Depreciation</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_depreciation, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Depreciation Percentage</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($depreciation_percentage, 1); ?>%</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-percentage fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Value by Category</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Value Distribution by Age</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="ageChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Breakdown -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-pie mr-1"></i>Value Breakdown by Category
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="categoryTable">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Asset Count</th>
                        <th>Total Purchase Value</th>
                        <th>Estimated Current Value</th>
                        <th>Depreciation</th>
                        <th>Avg. Age (Years)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($cat_result, 0); ?>
                    <?php while($cat = mysqli_fetch_assoc($cat_result)): 
                        $cat_purchase_cost = floatval($cat['total_purchase_cost']);
                        $cat_avg_age = floatval($cat['avg_age']);
                        $cat_depreciation_factor = max(0, 1 - ($depreciation_rate * $cat_avg_age));
                        $cat_current_value = $cat_purchase_cost * $cat_depreciation_factor;
                        $cat_depreciation = $cat_purchase_cost - $cat_current_value;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['category_name'] ?? 'Uncategorized'); ?></td>
                        <td class="text-center"><?php echo $cat['asset_count']; ?></td>
                        <td class="text-right">$<?php echo number_format($cat_purchase_cost, 2); ?></td>
                        <td class="text-right">$<?php echo number_format($cat_current_value, 2); ?></td>
                        <td class="text-right">$<?php echo number_format($cat_depreciation, 2); ?></td>
                        <td class="text-center"><?php echo number_format($cat_avg_age, 1); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Asset Value List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>Detailed Asset Value List
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="assetsTable">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th>Age (Years)</th>
                        <th>Purchase Cost</th>
                        <th>Current Value</th>
                        <th>Depreciation</th>
                        <th>Value Retention</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($result, 0); ?>
                    <?php while($row = mysqli_fetch_assoc($result)): 
                        $purchase_cost = floatval($row['purchase_cost']);
                        $age_years = floatval($row['age_years']);
                        $depreciation_factor = max(0, 1 - ($depreciation_rate * $age_years));
                        $current_value = $purchase_cost * $depreciation_factor;
                        $depreciation = $purchase_cost - $current_value;
                        $value_retention = $purchase_cost > 0 ? ($current_value / $purchase_cost) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['asset_tag']); ?></td>
                        <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['purchase_date'])); ?></td>
                        <td class="text-center"><?php echo number_format($age_years, 1); ?></td>
                        <td class="text-right">$<?php echo number_format($purchase_cost, 2); ?></td>
                        <td class="text-right">$<?php echo number_format($current_value, 2); ?></td>
                        <td class="text-right">$<?php echo number_format($depreciation, 2); ?></td>
                        <td class="text-center"><?php echo number_format($value_retention, 1); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add charts and export script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#assetsTable').DataTable();
    
    // Category Value Chart
    var ctxCategory = document.getElementById('categoryChart').getContext('2d');
    var categoryData = {
        labels: [
            <?php 
            mysqli_data_seek($cat_result, 0);
            while($cat = mysqli_fetch_assoc($cat_result)) {
                echo "'" . ($cat['category_name'] ?? 'Uncategorized') . "', ";
            } 
            ?>
        ],
        datasets: [{
            label: 'Purchase Value',
            data: [
                <?php 
                mysqli_data_seek($cat_result, 0);
                while($cat = mysqli_fetch_assoc($cat_result)) {
                    echo floatval($cat['total_purchase_cost']) . ", ";
                } 
                ?>
            ],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }, {
            label: 'Current Value',
            data: [
                <?php 
                mysqli_data_seek($cat_result, 0);
                while($cat = mysqli_fetch_assoc($cat_result)) {
                    $cat_purchase_cost = floatval($cat['total_purchase_cost']);
                    $cat_avg_age = floatval($cat['avg_age']);
                    $cat_depreciation_factor = max(0, 1 - ($depreciation_rate * $cat_avg_age));
                    $cat_current_value = $cat_purchase_cost * $cat_depreciation_factor;
                    echo $cat_current_value . ", ";
                } 
                ?>
            ],
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgb(75, 192, 192)',
            borderWidth: 1
        }]
    };
    
    var categoryChart = new Chart(ctxCategory, {
        type: 'bar',
        data: categoryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value, index, values) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }]
            },
            title: {
                display: true,
                text: 'Value by Category'
            }
        }
    });
    
    // Age Distribution Chart
    var ctxAge = document.getElementById('ageChart').getContext('2d');
    var ageData = {
        labels: [
            <?php 
            mysqli_data_seek($age_result, 0);
            while($age = mysqli_fetch_assoc($age_result)) {
                echo "'" . $age['age_year'] . " yr', ";
            } 
            ?>
        ],
        datasets: [{
            label: 'Purchase Value',
            data: [
                <?php 
                mysqli_data_seek($age_result, 0);
                while($age = mysqli_fetch_assoc($age_result)) {
                    echo floatval($age['total_purchase_cost']) . ", ";
                } 
                ?>
            ],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }, {
            label: 'Current Value',
            data: [
                <?php 
                mysqli_data_seek($age_result, 0);
                while($age = mysqli_fetch_assoc($age_result)) {
                    $age_purchase_cost = floatval($age['total_purchase_cost']);
                    $age_years = floatval($age['age_year']);
                    $age_depreciation_factor = max(0, 1 - ($depreciation_rate * $age_years));
                    $age_current_value = $age_purchase_cost * $age_depreciation_factor;
                    echo $age_current_value . ", ";
                } 
                ?>
            ],
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgb(75, 192, 192)',
            borderWidth: 1
        }]
    };
    
    var ageChart = new Chart(ctxAge, {
        type: 'bar',
        data: ageData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value, index, values) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }]
            },
            title: {
                display: true,
                text: 'Value Distribution by Asset Age'
            }
        }
    });
});

function exportToExcel() {
    // Create a workbook with multiple sheets
    let wb = XLSX.utils.book_new();
    
    // Add Assets sheet
    let table1 = document.getElementById("assetsTable");
    let ws1 = XLSX.utils.table_to_sheet(table1);
    XLSX.utils.book_append_sheet(wb, ws1, "Asset Values");
    
    // Add Category sheet
    let table2 = document.getElementById("categoryTable");
    let ws2 = XLSX.utils.table_to_sheet(table2);
    XLSX.utils.book_append_sheet(wb, ws2, "By Category");
    
    // Write the file
    XLSX.writeFile(wb, 'asset_value_report.xlsx');
}
</script>
<?php endif; ?>

<?php include_once "../../includes/footer.php"; ?>