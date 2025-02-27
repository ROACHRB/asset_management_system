<?php
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Check if user has permission to generate reports
require_permission('generate_reports', '../dashboard/index.php');

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $category = isset($_POST['category']) ? intval($_POST['category']) : null;
    $supplier = sanitize_input($conn, $_POST['supplier']);
    $min_cost = isset($_POST['min_cost']) && $_POST['min_cost'] !== '' ? floatval($_POST['min_cost']) : null;
    $max_cost = isset($_POST['max_cost']) && $_POST['max_cost'] !== '' ? floatval($_POST['max_cost']) : null;
    
    // Build query
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    if($year) {
        $where_clauses[] = "YEAR(a.purchase_date) = ?";
        $params[] = $year;
        $param_types .= "i";
    }
    
    if($category) {
        $where_clauses[] = "a.category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }
    
    if($supplier) {
        $where_clauses[] = "a.supplier = ?";
        $params[] = $supplier;
        $param_types .= "s";
    }
    
    if($min_cost !== null) {
        $where_clauses[] = "a.purchase_cost >= ?";
        $params[] = $min_cost;
        $param_types .= "d";
    }
    
    if($max_cost !== null) {
        $where_clauses[] = "a.purchase_cost <= ?";
        $params[] = $max_cost;
        $param_types .= "d";
    }
    
    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Main assets query
    $sql = "SELECT a.*, c.category_name
            FROM assets a
            LEFT JOIN categories c ON a.category_id = c.category_id
            $where_sql
            ORDER BY a.purchase_date DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if($params) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Get cost breakdown by category
    $cat_sql = "SELECT c.category_name, 
                      COUNT(a.asset_id) as asset_count,
                      SUM(a.purchase_cost) as total_cost,
                      AVG(a.purchase_cost) as avg_cost,
                      MIN(a.purchase_cost) as min_cost,
                      MAX(a.purchase_cost) as max_cost
               FROM assets a
               LEFT JOIN categories c ON a.category_id = c.category_id
               $where_sql
               GROUP BY a.category_id
               ORDER BY total_cost DESC";
    
    $cat_stmt = mysqli_prepare($conn, $cat_sql);
    if($params) {
        mysqli_stmt_bind_param($cat_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($cat_stmt);
    $cat_result = mysqli_stmt_get_result($cat_stmt);
    
    // Get cost breakdown by supplier
    $supplier_sql = "SELECT a.supplier, 
                          COUNT(a.asset_id) as asset_count,
                          SUM(a.purchase_cost) as total_cost,
                          AVG(a.purchase_cost) as avg_cost
                   FROM assets a
                   LEFT JOIN categories c ON a.category_id = c.category_id
                   $where_sql
                   GROUP BY a.supplier
                   ORDER BY total_cost DESC
                   LIMIT 10";
    
    $supplier_stmt = mysqli_prepare($conn, $supplier_sql);
    if($params) {
        mysqli_stmt_bind_param($supplier_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($supplier_stmt);
    $supplier_result = mysqli_stmt_get_result($supplier_stmt);
    
    // Get monthly spending
    $monthly_sql = "SELECT DATE_FORMAT(a.purchase_date, '%Y-%m') as month,
                          COUNT(a.asset_id) as asset_count,
                          SUM(a.purchase_cost) as total_cost
                   FROM assets a
                   LEFT JOIN categories c ON a.category_id = c.category_id
                   $where_sql
                   GROUP BY month
                   ORDER BY month ASC";
    
    $monthly_stmt = mysqli_prepare($conn, $monthly_sql);
    if($params) {
        mysqli_stmt_bind_param($monthly_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($monthly_stmt);
    $monthly_result = mysqli_stmt_get_result($monthly_stmt);
}

// Get categories for filters
$categories_result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");

// Get distinct suppliers
$suppliers_query = "SELECT DISTINCT supplier FROM assets WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier";
$suppliers_result = mysqli_query($conn, $suppliers_query);

// Get all years for purchase dates
$years_query = "SELECT DISTINCT YEAR(purchase_date) as year FROM assets WHERE purchase_date IS NOT NULL ORDER BY year DESC";
$years_result = mysqli_query($conn, $years_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-dollar-sign mr-2"></i>Cost Analysis Report</h1>
        <p class="text-muted">Financial metrics and cost tracking for assets</p>
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
                        <label>Purchase Year</label>
                        <select class="form-control" name="year">
                            <option value="">All Years</option>
                            <?php while($year = mysqli_fetch_assoc($years_result)): ?>
                                <option value="<?php echo $year['year']; ?>"
                                    <?php if(isset($_POST['year']) && $_POST['year'] == $year['year']) echo 'selected'; ?>>
                                    <?php echo $year['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
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
                        <label>Supplier</label>
                        <select class="form-control" name="supplier">
                            <option value="">All Suppliers</option>
                            <?php while($supplier = mysqli_fetch_assoc($suppliers_result)): ?>
                                <option value="<?php echo $supplier['supplier']; ?>"
                                    <?php if(isset($_POST['supplier']) && $_POST['supplier'] == $supplier['supplier']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($supplier['supplier']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Cost Range</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" class="form-control" name="min_cost" placeholder="Min" 
                                   value="<?php echo isset($_POST['min_cost']) ? $_POST['min_cost'] : ''; ?>">
                            <div class="input-group-prepend input-group-append">
                                <span class="input-group-text">-</span>
                            </div>
                            <input type="number" class="form-control" name="max_cost" placeholder="Max" 
                                   value="<?php echo isset($_POST['max_cost']) ? $_POST['max_cost'] : ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i>Generate Report
                        </button>
                        <?php if(isset($result) && mysqli_num_rows($result) > 0): ?>
                        <button type="button" class="btn btn-success ml-2" onclick="exportToExcel()">
                            <i class="fas fa-file-excel mr-1"></i>Export to Excel
                        </button>
                        <button type="button" class="btn btn-danger ml-2" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf mr-1"></i>Export to PDF
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Report Results -->
<?php if(isset($result)): ?>
<!-- Summary Stats -->
<div class="row mb-4">
    <?php
    $total_assets = mysqli_num_rows($result);
    $total_cost = 0;
    $avg_cost = 0;
    
    if($total_assets > 0) {
        mysqli_data_seek($result, 0);
        while($row = mysqli_fetch_assoc($result)) {
            $total_cost += $row['purchase_cost'];
        }
        $avg_cost = $total_cost / $total_assets;
        mysqli_data_seek($result, 0);
    }
    ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Assets</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_assets; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-laptop fa-2x text-gray-300"></i>
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
                            Total Cost</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_cost, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Average Cost</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($avg_cost, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calculator fa-2x text-gray-300"></i>
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
                <h6 class="m-0 font-weight-bold text-primary">Cost by Category</h6>
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
                <h6 class="m-0 font-weight-bold text-primary">Monthly Spending</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Breakdown -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-pie mr-1"></i>Cost Breakdown by Category
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="categoryTable">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Asset Count</th>
                        <th>Total Cost</th>
                        <th>Average Cost</th>
                        <th>Min Cost</th>
                        <th>Max Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($cat = mysqli_fetch_assoc($cat_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['category_name'] ?? 'Uncategorized'); ?></td>
                        <td class="text-center"><?php echo $cat['asset_count']; ?></td>
                        <td class="text-right">$<?php echo number_format($cat['total_cost'], 2); ?></td>
                        <td class="text-right">$<?php echo number_format($cat['avg_cost'], 2); ?></td>
                        <td class="text-right">$<?php echo number_format($cat['min_cost'], 2); ?></td>
                        <td class="text-right">$<?php echo number_format($cat['max_cost'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Supplier Breakdown -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-building mr-1"></i>Cost Breakdown by Supplier
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="supplierTable">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Asset Count</th>
                        <th>Total Cost</th>
                        <th>Average Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sup = mysqli_fetch_assoc($supplier_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sup['supplier'] ?? 'Unknown'); ?></td>
                        <td class="text-center"><?php echo $sup['asset_count']; ?></td>
                        <td class="text-right">$<?php echo number_format($sup['total_cost'], 2); ?></td>
                        <td class="text-right">$<?php echo number_format($sup['avg_cost'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Assets List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>Detailed Asset Cost List
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
                        <th>Supplier</th>
                        <th>Cost</th>
                        <th>Warranty Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($result, 0); ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['asset_tag']); ?></td>
                        <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo !empty($row['purchase_date']) ? date('M d, Y', strtotime($row['purchase_date'])) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($row['supplier'] ?? 'N/A'); ?></td>
                        <td class="text-right">$<?php echo number_format($row['purchase_cost'], 2); ?></td>
                        <td><?php echo !empty($row['warranty_expiry']) ? date('M d, Y', strtotime($row['warranty_expiry'])) : 'N/A'; ?></td>
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
    
    // Category Cost Chart
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
            label: 'Total Cost',
            data: [
                <?php 
                mysqli_data_seek($cat_result, 0);
                while($cat = mysqli_fetch_assoc($cat_result)) {
                    echo $cat['total_cost'] . ", ";
                } 
                ?>
            ],
            backgroundColor: [
                'rgba(255, 99, 132, 0.2)',
                'rgba(54, 162, 235, 0.2)',
                'rgba(255, 206, 86, 0.2)',
                'rgba(75, 192, 192, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(255, 159, 64, 0.2)',
                'rgba(201, 203, 207, 0.2)'
            ],
            borderColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 206, 86)',
                'rgb(75, 192, 192)',
                'rgb(153, 102, 255)',
                'rgb(255, 159, 64)',
                'rgb(201, 203, 207)'
            ],
            borderWidth: 1
        }]
    };
    
    var categoryChart = new Chart(ctxCategory, {
        type: 'pie',
        data: categoryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right'
            },
            title: {
                display: true,
                text: 'Cost Distribution by Category'
            }
        }
    });
    
    // Monthly Spending Chart
    var ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    var monthlyData = {
        labels: [
            <?php 
            mysqli_data_seek($monthly_result, 0);
            while($month = mysqli_fetch_assoc($monthly_result)) {
                echo "'" . date('M Y', strtotime($month['month'] . '-01')) . "', ";
            } 
            ?>
        ],
        datasets: [{
            label: 'Monthly Spending',
            data: [
                <?php 
                mysqli_data_seek($monthly_result, 0);
                while($month = mysqli_fetch_assoc($monthly_result)) {
                    echo $month['total_cost'] . ", ";
                } 
                ?>
            ],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }]
    };
    
    var monthlyChart = new Chart(ctxMonthly, {
        type: 'line',
        data: monthlyData,
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
                text: 'Monthly Spending Trend'
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
    XLSX.utils.book_append_sheet(wb, ws1, "Assets");
    
    // Add Category sheet
    let table2 = document.getElementById("categoryTable");
    let ws2 = XLSX.utils.table_to_sheet(table2);
    XLSX.utils.book_append_sheet(wb, ws2, "By Category");
    
    // Add Supplier sheet
    let table3 = document.getElementById("supplierTable");
    let ws3 = XLSX.utils.table_to_sheet(table3);
    XLSX.utils.book_append_sheet(wb, ws3, "By Supplier");
    
    // Write the file
    XLSX.writeFile(wb, 'cost_analysis_report.xlsx');
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Title
    doc.text("Cost Analysis Report", 14, 15);
    
    // Summary information
    doc.text("Summary", 14, 25);
    doc.text("Total Assets: <?php echo $total_assets; ?>", 14, 35);
    doc.text("Total Cost: $<?php echo number_format($total_cost, 2); ?>", 14, 42);
    doc.text("Average Cost: $<?php echo number_format($avg_cost, 2); ?>", 14, 49);
    
    // Add Category table
    doc.text("Cost Breakdown by Category", 14, 60);
    doc.autoTable({ 
        html: '#categoryTable',
        startY: 65
    });
    
    // Add a new page for Supplier table
    doc.addPage();
    doc.text("Cost Breakdown by Supplier", 14, 15);
    doc.autoTable({
        html: '#supplierTable',
        startY: 20
    });
    
    // Add a new page for Assets table
    doc.addPage();
    doc.text("Detailed Asset Cost List", 14, 15);
    doc.autoTable({
        html: '#assetsTable',
        startY: 20
    });
    
    doc.save("cost_analysis_report.pdf");
}
</script>
<?php endif; ?>

<?php include_once "../../includes/footer.php"; ?>