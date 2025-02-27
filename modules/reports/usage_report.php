<?php
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Check if user has permission to generate reports
require_permission('generate_reports', '../dashboard/index.php');

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $time_period = sanitize_input($conn, $_POST['time_period']);
    $category = isset($_POST['category']) ? intval($_POST['category']) : null;
    $department = sanitize_input($conn, $_POST['department']);
    
    // Calculate date ranges based on selected time period
    $end_date = date('Y-m-d');
    $start_date = '';
    
    switch($time_period) {
        case '30days':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90days':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            break;
        case '6months':
            $start_date = date('Y-m-d', strtotime('-6 months'));
            break;
        case '1year':
            $start_date = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            // Use custom date range if provided
            $start_date = isset($_POST['custom_start_date']) ? $_POST['custom_start_date'] : date('Y-m-d', strtotime('-30 days'));
            $end_date = isset($_POST['custom_end_date']) ? $_POST['custom_end_date'] : date('Y-m-d');
    }
    
    // Build query
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    // Base conditions - assignment period overlaps with our date range
    $where_clauses[] = "(aa.assignment_date BETWEEN ? AND ? OR
                       (aa.assignment_date <= ? AND 
                        (aa.actual_return_date >= ? OR aa.actual_return_date IS NULL)))";
    $params[] = $start_date;
    $params[] = $end_date . " 23:59:59";
    $params[] = $end_date . " 23:59:59";
    $params[] = $start_date;
    $param_types .= "ssss";
    
    if($category) {
        $where_clauses[] = "a.category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }
    
    if($department) {
        $where_clauses[] = "u.department = ?";
        $params[] = $department;
        $param_types .= "s";
    }
    
    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // First query - Assets with most assignments
    $sql1 = "SELECT a.asset_id, a.asset_tag, a.asset_name, c.category_name,
                   COUNT(aa.assignment_id) as assignment_count,
                   SUM(DATEDIFF(COALESCE(aa.actual_return_date, NOW()), aa.assignment_date)) as total_days
            FROM asset_assignments aa
            JOIN assets a ON aa.asset_id = a.asset_id
            LEFT JOIN categories c ON a.category_id = c.category_id
            JOIN users u ON aa.assigned_to = u.user_id
            $where_sql
            GROUP BY a.asset_id
            ORDER BY assignment_count DESC, total_days DESC
            LIMIT 10";
    
    // Second query - Users with most assignments
    $sql2 = "SELECT u.user_id, u.full_name, u.department, 
                   COUNT(aa.assignment_id) as assignment_count,
                   SUM(DATEDIFF(COALESCE(aa.actual_return_date, NOW()), aa.assignment_date)) as total_days
            FROM asset_assignments aa
            JOIN assets a ON aa.asset_id = a.asset_id
            JOIN users u ON aa.assigned_to = u.user_id
            $where_sql
            GROUP BY u.user_id
            ORDER BY assignment_count DESC, total_days DESC
            LIMIT 10";
    
    // Third query - Categories with most assignments
    $sql3 = "SELECT c.category_id, c.category_name,
                   COUNT(aa.assignment_id) as assignment_count,
                   COUNT(DISTINCT a.asset_id) as asset_count,
                   COUNT(DISTINCT aa.assigned_to) as user_count
            FROM asset_assignments aa
            JOIN assets a ON aa.asset_id = a.asset_id
            LEFT JOIN categories c ON a.category_id = c.category_id
            JOIN users u ON aa.assigned_to = u.user_id
            $where_sql
            GROUP BY c.category_id
            ORDER BY assignment_count DESC
            LIMIT 10";
    
    // Fourth query - Days of the week usage
    $sql4 = "SELECT DAYNAME(aa.assignment_date) as day_name,
                   COUNT(aa.assignment_id) as assignment_count
            FROM asset_assignments aa
            JOIN assets a ON aa.asset_id = a.asset_id
            JOIN users u ON aa.assigned_to = u.user_id
            $where_sql
            GROUP BY day_name
            ORDER BY FIELD(day_name, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
    
    // Prepare and execute the queries
    $stmt1 = mysqli_prepare($conn, $sql1);
    $stmt2 = mysqli_prepare($conn, $sql2);
    $stmt3 = mysqli_prepare($conn, $sql3);
    $stmt4 = mysqli_prepare($conn, $sql4);
    
    if($params) {
        mysqli_stmt_bind_param($stmt1, $param_types, ...$params);
        mysqli_stmt_bind_param($stmt2, $param_types, ...$params);
        mysqli_stmt_bind_param($stmt3, $param_types, ...$params);
        mysqli_stmt_bind_param($stmt4, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt1);
    $result1 = mysqli_stmt_get_result($stmt1);
    
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    
    mysqli_stmt_execute($stmt3);
    $result3 = mysqli_stmt_get_result($stmt3);
    
    mysqli_stmt_execute($stmt4);
    $result4 = mysqli_stmt_get_result($stmt4);
}

// Get categories for filters
$categories_result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");

// Get distinct departments
$departments_query = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-chart-line mr-2"></i>Asset Usage Analysis</h1>
        <p class="text-muted">Analyze asset usage patterns and statistics</p>
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
                        <label>Time Period</label>
                        <select class="form-control" name="time_period" id="time_period">
                            <option value="30days" <?php if(isset($_POST['time_period']) && $_POST['time_period'] == '30days') echo 'selected'; ?>>Last 30 Days</option>
                            <option value="90days" <?php if(isset($_POST['time_period']) && $_POST['time_period'] == '90days') echo 'selected'; ?>>Last 90 Days</option>
                            <option value="6months" <?php if(isset($_POST['time_period']) && $_POST['time_period'] == '6months') echo 'selected'; ?>>Last 6 Months</option>
                            <option value="1year" <?php if(isset($_POST['time_period']) && $_POST['time_period'] == '1year') echo 'selected'; ?>>Last Year</option>
                            <option value="custom" <?php if(isset($_POST['time_period']) && $_POST['time_period'] == 'custom') echo 'selected'; ?>>Custom Range</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 custom-dates" style="display: <?php echo (isset($_POST['time_period']) && $_POST['time_period'] == 'custom') ? 'block' : 'none'; ?>">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" class="form-control" name="custom_start_date" 
                               value="<?php echo isset($_POST['custom_start_date']) ? $_POST['custom_start_date'] : date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                </div>
                <div class="col-md-3 custom-dates" style="display: <?php echo (isset($_POST['time_period']) && $_POST['time_period'] == 'custom') ? 'block' : 'none'; ?>">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" class="form-control" name="custom_end_date"
                               value="<?php echo isset($_POST['custom_end_date']) ? $_POST['custom_end_date'] : date('Y-m-d'); ?>">
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
                        <label>Department</label>
                        <select class="form-control" name="department">
                            <option value="">All Departments</option>
                            <?php while($dept = mysqli_fetch_assoc($departments_result)): ?>
                                <option value="<?php echo $dept['department']; ?>"
                                    <?php if(isset($_POST['department']) && $_POST['department'] == $dept['department']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endwhile; ?>
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
                        <?php if(isset($result1) && mysqli_num_rows($result1) > 0): ?>
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
<?php if(isset($result1)): ?>
<div class="row">
    <div class="col-md-6">
        <!-- Most Used Assets -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-laptop mr-1"></i>Most Used Assets
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="topAssetsTable">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Category</th>
                                <th>Assignments</th>
                                <th>Total Days Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($result1)): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($row['asset_tag']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['asset_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                                <td class="text-center"><?php echo $row['assignment_count']; ?></td>
                                <td class="text-center"><?php echo $row['total_days']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- Top Users -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-users mr-1"></i>Top Users
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="topUsersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Department</th>
                                <th>Assignments</th>
                                <th>Total Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($result2)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                                <td class="text-center"><?php echo $row['assignment_count']; ?></td>
                                <td class="text-center"><?php echo $row['total_days']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <!-- Category Usage -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-tags mr-1"></i>Category Usage
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Assets</th>
                                <th>Total Users</th>
                                <th>Assignments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($result3, 0);
                            while($row = mysqli_fetch_assoc($result3)): 
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                                <td class="text-center"><?php echo $row['asset_count']; ?></td>
                                <td class="text-center"><?php echo $row['user_count']; ?></td>
                                <td class="text-center"><?php echo $row['assignment_count']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- Usage by Day of Week -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-calendar-alt mr-1"></i>Usage by Day of Week
            </div>
            <div class="card-body">
                <canvas id="dayOfWeekChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Add charts and export script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>

<script>
// Toggle custom date inputs when "Custom Range" is selected
$('#time_period').change(function() {
    if($(this).val() === 'custom') {
        $('.custom-dates').show();
    } else {
        $('.custom-dates').hide();
    }
});

// Create day of week chart
<?php if(isset($result4) && mysqli_num_rows($result4) > 0): ?>
$(document).ready(function() {
    var ctx = document.getElementById('dayOfWeekChart').getContext('2d');
    
    // Prepare data
    var labels = [];
    var data = [];
    var backgroundColors = [
        'rgba(75, 192, 192, 0.2)',
        'rgba(54, 162, 235, 0.2)',
        'rgba(255, 206, 86, 0.2)',
        'rgba(153, 102, 255, 0.2)',
        'rgba(255, 159, 64, 0.2)',
        'rgba(255, 99, 132, 0.2)',
        'rgba(201, 203, 207, 0.2)'
    ];
    
    var borderColors = [
        'rgb(75, 192, 192)',
        'rgb(54, 162, 235)',
        'rgb(255, 206, 86)',
        'rgb(153, 102, 255)',
        'rgb(255, 159, 64)',
        'rgb(255, 99, 132)',
        'rgb(201, 203, 207)'
    ];
    
    <?php 
    mysqli_data_seek($result4, 0);
    while($row = mysqli_fetch_assoc($result4)): 
    ?>
    labels.push('<?php echo $row['day_name']; ?>');
    data.push(<?php echo $row['assignment_count']; ?>);
    <?php endwhile; ?>
    
    var dayOfWeekChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Assignments',
                data: data,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            },
            legend: {
                display: false
            },
            title: {
                display: true,
                text: 'Asset Assignments by Day of Week'
            }
        }
    });
});
<?php endif; ?>

function exportToExcel() {
    // Create a workbook with multiple sheets
    let wb = XLSX.utils.book_new();
    
    // Add Top Assets sheet
    let table1 = document.getElementById("topAssetsTable");
    let ws1 = XLSX.utils.table_to_sheet(table1);
    XLSX.utils.book_append_sheet(wb, ws1, "Top Assets");
    
    // Add Top Users sheet
    let table2 = document.getElementById("topUsersTable");
    let ws2 = XLSX.utils.table_to_sheet(table2);
    XLSX.utils.book_append_sheet(wb, ws2, "Top Users");
    
    // Add Categories sheet
    let table3 = document.getElementById("categoriesTable");
    let ws3 = XLSX.utils.table_to_sheet(table3);
    XLSX.utils.book_append_sheet(wb, ws3, "Categories");
    
    // Write the file
    XLSX.writeFile(wb, 'asset_usage_report.xlsx');
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Title
    doc.text("Asset Usage Analysis Report", 14, 15);
    
    // Add Top Assets table
    doc.text("Most Used Assets", 14, 25);
    doc.autoTable({ 
        html: '#topAssetsTable',
        startY: 30
    });
    
    // Add Top Users table
    let finalY = doc.lastAutoTable.finalY || 30;
    doc.text("Top Users", 14, finalY + 10);
    doc.autoTable({
        html: '#topUsersTable',
        startY: finalY + 15
    });
    
    // Add a new page if needed
    finalY = doc.lastAutoTable.finalY || 30;
    if (finalY > 200) {
        doc.addPage();
        finalY = 20;
    }
    
    // Add Categories table
    doc.text("Category Usage", 14, finalY + 10);
    doc.autoTable({
        html: '#categoriesTable',
        startY: finalY + 15
    });
    
    doc.save("asset_usage_report.pdf");
}
</script>
<?php endif; ?>

<?php include_once "../../includes/footer.php"; ?>