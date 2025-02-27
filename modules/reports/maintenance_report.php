<?php
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Check if user has permission to generate reports
require_permission('generate_reports', '../dashboard/index.php');

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $start_date = sanitize_input($conn, $_POST['start_date']);
    $end_date = sanitize_input($conn, $_POST['end_date']);
    $category = isset($_POST['category']) ? intval($_POST['category']) : null;
    $status = sanitize_input($conn, $_POST['status']);
    
    // Build query
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    if($start_date) {
        $where_clauses[] = "ar.request_date >= ?";
        $params[] = $start_date;
        $param_types .= "s";
    }
    
    if($end_date) {
        $where_clauses[] = "ar.request_date <= ?";
        $params[] = $end_date . " 23:59:59";
        $param_types .= "s";
    }
    
    if($category) {
        $where_clauses[] = "a.category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }
    
    if($status && $status != 'all') {
        $where_clauses[] = "ar.status = ?";
        $params[] = $status;
        $param_types .= "s";
    }
    
    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Main repairs query
    $sql = "SELECT ar.*, a.asset_tag, a.asset_name, c.category_name,
                 u_req.full_name as requested_by_name,
                 u_ass.full_name as assigned_to_name
            FROM asset_repairs ar
            JOIN assets a ON ar.asset_id = a.asset_id
            LEFT JOIN categories c ON a.category_id = c.category_id
            LEFT JOIN users u_req ON ar.requested_by = u_req.user_id
            LEFT JOIN users u_ass ON ar.assigned_to = u_ass.user_id
            $where_sql
            ORDER BY ar.request_date DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if($params) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Get repair counts by category
    $cat_sql = "SELECT c.category_name, 
                       COUNT(ar.repair_id) as repair_count,
                       SUM(CASE WHEN ar.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                       AVG(CASE WHEN ar.status = 'completed' AND ar.completion_date IS NOT NULL THEN
                           DATEDIFF(ar.completion_date, ar.request_date)
                           ELSE NULL END) as avg_repair_days
                FROM asset_repairs ar
                JOIN assets a ON ar.asset_id = a.asset_id
                LEFT JOIN categories c ON a.category_id = c.category_id
                $where_sql
                GROUP BY c.category_id
                ORDER BY repair_count DESC";
    
    $cat_stmt = mysqli_prepare($conn, $cat_sql);
    if($params) {
        mysqli_stmt_bind_param($cat_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($cat_stmt);
    $cat_result = mysqli_stmt_get_result($cat_stmt);
    
    // Get average repair time trend by month
    $monthly_sql = "SELECT DATE_FORMAT(ar.request_date, '%Y-%m') as month,
                          COUNT(ar.repair_id) as repair_count,
                          AVG(CASE WHEN ar.status = 'completed' AND ar.completion_date IS NOT NULL THEN
                              DATEDIFF(ar.completion_date, ar.request_date)
                              ELSE NULL END) as avg_repair_days
                   FROM asset_repairs ar
                   JOIN assets a ON ar.asset_id = a.asset_id
                   $where_sql
                   GROUP BY month
                   ORDER BY month ASC";
    
    $monthly_stmt = mysqli_prepare($conn, $monthly_sql);
    if($params) {
        mysqli_stmt_bind_param($monthly_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($monthly_stmt);
    $monthly_result = mysqli_stmt_get_result($monthly_stmt);
    
    // Get top issues
    $issues_sql = "SELECT issue_type, 
                         COUNT(repair_id) as repair_count,
                         AVG(CASE WHEN status = 'completed' AND completion_date IS NOT NULL THEN
                             DATEDIFF(completion_date, request_date)
                             ELSE NULL END) as avg_repair_days
                  FROM asset_repairs ar
                  JOIN assets a ON ar.asset_id = a.asset_id
                  $where_sql
                  GROUP BY issue_type
                  ORDER BY repair_count DESC
                  LIMIT 10";
    
    $issues_stmt = mysqli_prepare($conn, $issues_sql);
    if($params) {
        mysqli_stmt_bind_param($issues_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($issues_stmt);
    $issues_result = mysqli_stmt_get_result($issues_stmt);
}

// Get categories for filters
$categories_result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-tools mr-2"></i>Maintenance History Report</h1>
        <p class="text-muted">Track asset repairs, maintenance history, and asset condition</p>
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
                        <label>Start Date</label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : ''; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" class="form-control" name="end_date"
                               value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category">
                            <option value="">All Categories</option>
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
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="all">All Statuses</option>
                            <option value="requested" <?php if(isset($_POST['status']) && $_POST['status'] == 'requested') echo 'selected'; ?>>Requested</option>
                            <option value="in_progress" <?php if(isset($_POST['status']) && $_POST['status'] == 'in_progress') echo 'selected'; ?>>In Progress</option>
                            <option value="completed" <?php if(isset($_POST['status']) && $_POST['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                            <option value="cancelled" <?php if(isset($_POST['status']) && $_POST['status'] == 'cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
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
    $total_repairs = mysqli_num_rows($result);
    $completed_repairs = 0;
    $in_progress_repairs = 0;
    $avg_repair_time = 0;
    $total_repair_days = 0;
    $repair_count_with_days = 0;
    
    mysqli_data_seek($result, 0);
    while($row = mysqli_fetch_assoc($result)) {
        if($row['status'] == 'completed') {
            $completed_repairs++;
            if(!empty($row['completion_date']) && !empty($row['request_date'])) {
                $days = round((strtotime($row['completion_date']) - strtotime($row['request_date'])) / (60 * 60 * 24));
                $total_repair_days += $days;
                $repair_count_with_days++;
            }
        } else if($row['status'] == 'in_progress') {
            $in_progress_repairs++;
        }
    }
    
    if($repair_count_with_days > 0) {
        $avg_repair_time = round($total_repair_days / $repair_count_with_days, 1);
    }
    
    mysqli_data_seek($result, 0);
    ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Repairs</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_repairs; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-gray-300"></i>
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
                            Completed Repairs</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completed_repairs; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                            In Progress</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $in_progress_repairs; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                            Avg. Repair Time</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $avg_repair_time; ?> days</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
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
                <h6 class="m-0 font-weight-bold text-primary">Repairs by Category</h6>
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
                <h6 class="m-0 font-weight-bold text-primary">Monthly Repair Time Trend</h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Common Issues -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-bug mr-1"></i>Top Issues
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="issuesTable">
                <thead>
                    <tr>
                        <th>Issue Type</th>
                        <th>Repair Count</th>
                        <th>Avg. Repair Time</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($issue = mysqli_fetch_assoc($issues_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($issue['issue_type']); ?></td>
                        <td class="text-center"><?php echo $issue['repair_count']; ?></td>
                        <td class="text-center"><?php echo !empty($issue['avg_repair_days']) ? round($issue['avg_repair_days'], 1) . ' days' : 'N/A'; ?></td>
                        <td class="text-center"><?php echo round(($issue['repair_count'] / $total_repairs) * 100, 1); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Breakdown -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-pie mr-1"></i>Repairs by Category
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="categoryTable">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Total Repairs</th>
                        <th>Completed Repairs</th>
                        <th>Avg. Repair Time</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($cat_result, 0); ?>
                    <?php while($cat = mysqli_fetch_assoc($cat_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['category_name'] ?? 'Uncategorized'); ?></td>
                        <td class="text-center"><?php echo $cat['repair_count']; ?></td>
                        <td class="text-center"><?php echo $cat['completed_count']; ?></td>
                        <td class="text-center"><?php echo !empty($cat['avg_repair_days']) ? round($cat['avg_repair_days'], 1) . ' days' : 'N/A'; ?></td>
                        <td class="text-center"><?php echo round(($cat['repair_count'] / $total_repairs) * 100, 1); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Repair List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>Detailed Repair List
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="repairsTable">
                <thead>
                    <tr>
                        <th>Repair ID</th>
                        <th>Asset</th>
                        <th>Issue Type</th>
                        <th>Requested By</th>
                        <th>Assigned To</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Completion Date</th>
                        <th>Days to Complete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($result, 0); ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $row['repair_id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['asset_tag']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($row['asset_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($row['issue_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['requested_by_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['assigned_to_name'] ?? 'Not Assigned'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($row['status'] == 'completed' ? 'success' : 
                                    ($row['status'] == 'in_progress' ? 'warning' : 
                                    ($row['status'] == 'requested' ? 'info' : 'secondary'))); 
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo !empty($row['completion_date']) ? date('M d, Y', strtotime($row['completion_date'])) : 'N/A'; ?></td>
                        <td>
                            <?php 
                            if(!empty($row['completion_date']) && !empty($row['request_date'])) {
                                $days = round((strtotime($row['completion_date']) - strtotime($row['request_date'])) / (60 * 60 * 24));
                                echo $days;
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
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
    $('#repairsTable').DataTable();
    
    // Category Repairs Chart
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
            label: 'Total Repairs',
            data: [
                <?php 
                mysqli_data_seek($cat_result, 0);
                while($cat = mysqli_fetch_assoc($cat_result)) {
                    echo $cat['repair_count'] . ", ";
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
        type: 'doughnut',
        data: categoryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right'
            },
            title: {
                display: true,
                text: 'Repairs by Category'
            }
        }
    });
    
    // Monthly Repair Time Chart
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
            label: 'Avg. Repair Time (Days)',
            data: [
                <?php 
                mysqli_data_seek($monthly_result, 0);
                while($month = mysqli_fetch_assoc($monthly_result)) {
                    echo (!empty($month['avg_repair_days']) ? round($month['avg_repair_days'], 1) : 0) . ", ";
                } 
                ?>
            ],
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgb(75, 192, 192)',
            borderWidth: 1,
            type: 'line',
            yAxisID: 'y-axis-1'
        }, {
            label: 'Number of Repairs',
            data: [
                <?php 
                mysqli_data_seek($monthly_result, 0);
                while($month = mysqli_fetch_assoc($monthly_result)) {
                    echo $month['repair_count'] . ", ";
                } 
                ?>
            ],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1,
            type: 'bar',
            yAxisID: 'y-axis-2'
        }]
    };
    
    var monthlyChart = new Chart(ctxMonthly, {
        type: 'bar',
        data: monthlyData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    type: 'linear',
                    display: true,
                    position: 'left',
                    id: 'y-axis-1',
                    ticks: {
                        beginAtZero: true
                    },
                    scaleLabel: {
                        display: true,
                        labelString: 'Avg. Repair Time (Days)'
                    }
                }, {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    id: 'y-axis-2',
                    ticks: {
                        beginAtZero: true
                    },
                    scaleLabel: {
                        display: true,
                        labelString: 'Number of Repairs'
                    },
                    gridLines: {
                        drawOnChartArea: false
                    }
                }]
            },
            title: {
                display: true,
                text: 'Monthly Repair Trend'
            }
        }
    });
});

function exportToExcel() {
    // Create a workbook with multiple sheets
    let wb = XLSX.utils.book_new();
    
    // Add Repairs sheet
    let table1 = document.getElementById("repairsTable");
    let ws1 = XLSX.utils.table_to_sheet(table1);
    XLSX.utils.book_append_sheet(wb, ws1, "Repairs");
    
    // Add Category sheet
    let table2 = document.getElementById("categoryTable");
    let ws2 = XLSX.utils.table_to_sheet(table2);
    XLSX.utils.book_append_sheet(wb, ws2, "By Category");
    
    // Add Issues sheet
    let table3 = document.getElementById("issuesTable");
    let ws3 = XLSX.utils.table_to_sheet(table3);
    XLSX.utils.book_append_sheet(wb, ws3, "Top Issues");
    
    // Write the file
    XLSX.writeFile(wb, 'maintenance_report.xlsx');
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Title
    doc.text("Maintenance History Report", 14, 15);
    
    // Summary information
    doc.text("Summary", 14, 25);
    doc.text("Total Repairs: <?php echo $total_repairs; ?>", 14, 35);
    doc.text("Completed Repairs: <?php echo $completed_repairs; ?>", 14, 42);
    doc.text("In Progress: <?php echo $in_progress_repairs; ?>", 14, 49);
    doc.text("Average Repair Time: <?php echo $avg_repair_time; ?> days", 14, 56);
    
    // Add Issues table
    doc.text("Top Issues", 14, 66);
    doc.autoTable({ 
        html: '#issuesTable',
        startY: 71
    });
    
    // Add a new page for Category table
    doc.addPage();
    doc.text("Repairs by Category", 14, 15);
    doc.autoTable({
        html: '#categoryTable',
        startY: 20
    });
    
    // Add a new page for Repairs table
    doc.addPage();
    doc.text("Detailed Repair List", 14, 15);
    doc.autoTable({
        html: '#repairsTable',
        startY: 20
    });
    
    doc.save("maintenance_report.pdf");
}
</script>
<?php endif; ?>

<?php include_once "../../includes/footer.php"; ?>