<?php
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Check if user has permission to generate reports
enforce_permission('generate_reports', '../dashboard/index.php');

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $start_date = sanitize_input($conn, $_POST['start_date']);
    $end_date = sanitize_input($conn, $_POST['end_date']);
    $asset_id = isset($_POST['asset_id']) ? intval($_POST['asset_id']) : null;
    $action = sanitize_input($conn, $_POST['action']);
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    // Build query
    $where_clauses = [];
    $params = [];
    $param_types = "";
    
    if($start_date) {
        $where_clauses[] = "ah.action_date >= ?";
        $params[] = $start_date;
        $param_types .= "s";
    }
    
    if($end_date) {
        $where_clauses[] = "ah.action_date <= ?";
        $params[] = $end_date . " 23:59:59";
        $param_types .= "s";
    }
    
    if($asset_id) {
        $where_clauses[] = "ah.asset_id = ?";
        $params[] = $asset_id;
        $param_types .= "i";
    }
    
    if($action && $action != 'all') {
        $where_clauses[] = "ah.action = ?";
        $params[] = $action;
        $param_types .= "s";
    }
    
    if($user_id) {
        $where_clauses[] = "ah.performed_by = ?";
        $params[] = $user_id;
        $param_types .= "i";
    }
    
    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $sql = "SELECT ah.*, a.asset_tag, a.asset_name, u.full_name as performed_by_name
            FROM asset_history ah
            JOIN assets a ON ah.asset_id = a.asset_id
            JOIN users u ON ah.performed_by = u.user_id
            $where_sql
            ORDER BY ah.action_date DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if($params) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

// Get assets for filters
$assets_query = "SELECT asset_id, asset_tag, asset_name FROM assets ORDER BY asset_tag";
$assets_result = mysqli_query($conn, $assets_query);

// Get users for filters
$users_query = "SELECT user_id, full_name FROM users ORDER BY full_name";
$users_result = mysqli_query($conn, $users_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-history mr-2"></i>Asset History Report</h1>
        <p class="text-muted">Track asset history, changes and actions over time</p>
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
                        <label>Asset</label>
                        <select class="form-control" name="asset_id">
                            <option value="">All Assets</option>
                            <?php mysqli_data_seek($assets_result, 0); ?>
                            <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                                <option value="<?php echo $asset['asset_id']; ?>"
                                    <?php if(isset($_POST['asset_id']) && $_POST['asset_id'] == $asset['asset_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($asset['asset_tag'] . ' - ' . $asset['asset_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Action Type</label>
                        <select class="form-control" name="action">
                            <option value="">All Actions</option>
                            <option value="created" <?php if(isset($_POST['action']) && $_POST['action'] == 'created') echo 'selected'; ?>>Created</option>
                            <option value="updated" <?php if(isset($_POST['action']) && $_POST['action'] == 'updated') echo 'selected'; ?>>Updated</option>
                            <option value="assigned" <?php if(isset($_POST['action']) && $_POST['action'] == 'assigned') echo 'selected'; ?>>Assigned</option>
                            <option value="returned" <?php if(isset($_POST['action']) && $_POST['action'] == 'returned') echo 'selected'; ?>>Returned</option>
                            <option value="transferred" <?php if(isset($_POST['action']) && $_POST['action'] == 'transferred') echo 'selected'; ?>>Transferred</option>
                            <option value="repaired" <?php if(isset($_POST['action']) && $_POST['action'] == 'repaired') echo 'selected'; ?>>Repaired</option>
                            <option value="disposed" <?php if(isset($_POST['action']) && $_POST['action'] == 'disposed') echo 'selected'; ?>>Disposed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Performed By</label>
                        <select class="form-control" name="user_id">
                            <option value="">All Users</option>
                            <?php mysqli_data_seek($users_result, 0); ?>
                            <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                                <option value="<?php echo $user['user_id']; ?>"
                                    <?php if(isset($_POST['user_id']) && $_POST['user_id'] == $user['user_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
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
        <i class="fas fa-table mr-1"></i>History Report Results
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="reportTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Asset Tag</th>
                        <th>Asset Name</th>
                        <th>Action</th>
                        <th>Performed By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 0;
                    while($row = mysqli_fetch_assoc($result)): 
                        $count++;
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($row['action_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['asset_tag']); ?></td>
                        <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($row['action'] == 'created' ? 'success' : 
                                    ($row['action'] == 'updated' ? 'info' : 
                                    ($row['action'] == 'assigned' ? 'primary' : 
                                    ($row['action'] == 'returned' ? 'secondary' :
                                    ($row['action'] == 'disposed' ? 'danger' : 'warning')))));
                            ?>">
                                <?php echo ucfirst($row['action']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['performed_by_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['notes']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6" class="text-right">Total Records: <?php echo $count; ?></th>
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
    let wb = XLSX.utils.table_to_book(table, {sheet: "Asset History Report"});
    XLSX.writeFile(wb, 'asset_history_report.xlsx');
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.text("Asset History Report", 14, 15);
    doc.autoTable({ html: '#reportTable' });
    
    doc.save("asset_history_report.pdf");
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