
<?php
include_once "../../includes/header.php";

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $start_date = sanitize_input($conn, $_POST['start_date']);
    $end_date = sanitize_input($conn, $_POST['end_date']);
    $category = isset($_POST['category']) ? intval($_POST['category']) : null;
    $location = isset($_POST['location']) ? intval($_POST['location']) : null;
    $status = sanitize_input($conn, $_POST['status']);
    
    // Build query
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    if($start_date) {
        $where_clauses[] = "a.created_at >= ?";
        $params[] = $start_date;
        $param_types .= "s";
    }
    
    if($end_date) {
        $where_clauses[] = "a.created_at <= ?";
        $params[] = $end_date . " 23:59:59";
        $param_types .= "s";
    }
    
    if($category) {
        $where_clauses[] = "a.category_id = ?";
        $params[] = $category;
        $param_types .= "i";
    }
    
    if($location) {
        $where_clauses[] = "a.location_id = ?";
        $params[] = $location;
        $param_types .= "i";
    }
    
    if($status) {
        $where_clauses[] = "a.status = ?";
        $params[] = $status;
        $param_types .= "s";
    }
    
    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $sql = "SELECT a.*, c.category_name, l.building, l.room,
                   COALESCE(aa.assigned_to_name, 'Not Assigned') as assigned_to
            FROM assets a
            LEFT JOIN categories c ON a.category_id = c.category_id
            LEFT JOIN locations l ON a.location_id = l.location_id
            LEFT JOIN (
                SELECT asset_id, u.full_name as assigned_to_name
                FROM asset_assignments aa
                JOIN users u ON aa.assigned_to = u.user_id
                WHERE aa.assignment_status = 'assigned'
            ) aa ON a.asset_id = aa.asset_id
            $where_sql
            ORDER BY a.asset_id";
    
    $stmt = mysqli_prepare($conn, $sql);
    if($params) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

// Get categories and locations for filters
$categories_result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
$locations_result = mysqli_query($conn, "SELECT * FROM locations ORDER BY building, room");
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-boxes mr-2"></i>Inventory Report</h1>
        <p class="text-muted">Generate detailed inventory reports with filters</p>
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
                        <label>Location</label>
                        <select class="form-control" name="location">
                            <option value="">All Locations</option>
                            <?php while($loc = mysqli_fetch_assoc($locations_result)): ?>
                                <option value="<?php echo $loc['location_id']; ?>"
                                    <?php if(isset($_POST['location']) && $_POST['location'] == $loc['location_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($loc['building'] . ' - ' . $loc['room']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="">All Statuses</option>
                            <option value="available" <?php if(isset($_POST['status']) && $_POST['status'] == 'available') echo 'selected'; ?>>Available</option>
                            <option value="assigned" <?php if(isset($_POST['status']) && $_POST['status'] == 'assigned') echo 'selected'; ?>>Assigned</option>
                            <option value="under_repair" <?php if(isset($_POST['status']) && $_POST['status'] == 'under_repair') echo 'selected'; ?>>Under Repair</option>
                            <option value="disposed" <?php if(isset($_POST['status']) && $_POST['status'] == 'disposed') echo 'selected'; ?>>Disposed</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="form-group d-flex align-items-end h-100">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-search mr-1"></i>Generate Report
                        </button>
                        <?php if(isset($result) && mysqli_num_rows($result) > 0): ?>
                        <button type="button" class="btn btn-success mr-2" onclick="exportToExcel()">
                            <i class="fas fa-file-excel mr-1"></i>Export to Excel
                        </button>
                        <button type="button" class="btn btn-danger" onclick="exportToPDF()">
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
<div class="card">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>Report Results
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="reportTable">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Purchase Date</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_value = 0;
                    while($row = mysqli_fetch_assoc($result)): 
                        $total_value += $row['purchase_cost'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['asset_tag']); ?></td>
                        <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['building'] . ' - ' . $row['room']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($row['status'] == 'available' ? 'success' : 
                                    ($row['status'] == 'assigned' ? 'primary' : 
                                    ($row['status'] == 'under_repair' ? 'warning' : 'secondary')));
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['assigned_to']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['purchase_date'])); ?></td>
                        <td>₱<?php echo number_format($row['purchase_cost'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="7" class="text-right">Total Value:</th>
                        <th>₱<?php echo number_format($total_value, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add this script for export functionality -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>

<script>
function exportToExcel() {
    let table = document.getElementById("reportTable");
    let wb = XLSX.utils.table_to_book(table, {sheet: "Inventory Report"});
    XLSX.writeFile(wb, 'inventory_report.xlsx');
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.text("Inventory Report", 14, 15);
    doc.autoTable({ html: '#reportTable' });
    
    doc.save("inventory_report.pdf");
}

$(document).ready(function() {
    // Initialize DataTable with export buttons
    $('#reportTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>