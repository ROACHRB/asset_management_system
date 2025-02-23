// FILE: D:\xampp\htdocs\asset_management_system\modules\audit\report.php
<?php
include_once "../../includes/header.php";

// Check if audit ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: index.php");
    exit();
}

$audit_id = $_GET['id'];

// Get audit details
$audit_sql = "SELECT a.*, u.full_name as auditor_name 
              FROM physical_audits a
              JOIN users u ON a.auditor_id = u.user_id
              WHERE a.audit_id = ?";
$stmt = mysqli_prepare($conn, $audit_sql);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$audit_result = mysqli_stmt_get_result($stmt);
$audit = mysqli_fetch_assoc($audit_result);

// Get audit items
$items_sql = "SELECT ai.*, a.asset_name, a.asset_tag, 
              l1.building as expected_building, l1.room as expected_room,
              l2.building as actual_building, l2.room as actual_room
              FROM audit_items ai
              JOIN assets a ON ai.asset_id = a.asset_id
              LEFT JOIN locations l1 ON ai.expected_location_id = l1.location_id
              LEFT JOIN locations l2 ON ai.actual_location_id = l2.location_id
              WHERE ai.audit_id = ?
              ORDER BY ai.status ASC, a.asset_name ASC";
$stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($stmt, "i", $audit_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

// Calculate statistics
$total = mysqli_num_rows($items_result);
$found = 0;
$missing = 0;
$wrong_location = 0;
$pending = 0;

mysqli_data_seek($items_result, 0);
while($item = mysqli_fetch_assoc($items_result)) {
    switch($item['status']) {
        case 'found': $found++; break;
        case 'missing': $missing++; break;
        case 'wrong_location': $wrong_location++; break;
        default: $pending++; break;
    }
}
mysqli_data_seek($items_result, 0);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-file-alt mr-2"></i>Audit Report</h1>
        <p class="text-muted">
            Location: <?php echo htmlspecialchars($audit['location']); ?><br>
            Date: <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>
        </p>
    </div>
    <div class="col-md-4 text-right">
        <div class="btn-group">
            <button onclick="exportToPDF()" class="btn btn-danger">
                <i class="fas fa-file-pdf mr-2"></i>Export PDF
            </button>
            <button onclick="exportToExcel()" class="btn btn-success">
                <i class="fas fa-file-excel mr-2"></i>Export Excel
            </button>
        </div>
    </div>
</div>

<!-- Report Content -->
<div id="reportContent">
    <!-- Audit Information -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle mr-1"></i>Audit Information
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Location:</strong><br> 
                        <?php echo htmlspecialchars($audit['location']); ?>
                    </p>
                    <p><strong>Audit Date:</strong><br>
                        <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <p><strong>Auditor:</strong><br>
                        <?php echo htmlspecialchars($audit['auditor_name']); ?>
                    </p>
                    <p><strong>Status:</strong><br>
                        <?php echo ucfirst(str_replace('_', ' ', $audit['status'])); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-pie mr-1"></i>Summary Statistics
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h3><?php echo $total; ?></h3>
                            <p class="mb-0">Total Items</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $found; ?></h3>
                            <p class="mb-0">Found</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $missing; ?></h3>
                            <p class="mb-0">Missing</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $wrong_location; ?></h3>
                            <p class="mb-0">Wrong Location</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Results -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list mr-1"></i>Detailed Results
        </div>
        <div class="card-body">
            <table class="table table-bordered" id="resultsTable">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Asset Name</th>
                        <th>Expected Location</th>
                        <th>Status</th>
                        <th>Actual Location</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = mysqli_fetch_assoc($items_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                        <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                        <td>
                            <?php 
                            echo htmlspecialchars($item['expected_building']);
                            if(!empty($item['expected_room'])) {
                                echo ' - ' . htmlspecialchars($item['expected_room']);
                            }
                            ?>
                        </td>
                        <td><?php echo ucfirst($item['status']); ?></td>
                        <td>
                            <?php 
                            if(!empty($item['actual_building'])) {
                                echo htmlspecialchars($item['actual_building']);
                                if(!empty($item['actual_room'])) {
                                    echo ' - ' . htmlspecialchars($item['actual_room']);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['notes'] ?? ''); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.20/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>

<script>
// Export to PDF
function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Add title
    doc.setFontSize(16);
    doc.text('Physical Inventory Audit Report', 14, 15);
    
    // Add audit info
    doc.setFontSize(12);
    doc.text('Location: <?php echo $audit['location']; ?>', 14, 25);
    doc.text('Date: <?php echo date('M d, Y', strtotime($audit['audit_date'])); ?>', 14, 32);
    doc.text('Auditor: <?php echo $audit['auditor_name']; ?>', 14, 39);
    
    // Add summary
    doc.text('Summary:', 14, 50);
    doc.text(`Total Items: <?php echo $total; ?>`, 20, 57);
    doc.text(`Found: <?php echo $found; ?>`, 20, 64);
    doc.text(`Missing: <?php echo $missing; ?>`, 20, 71);
    doc.text(`Wrong Location: <?php echo $wrong_location; ?>`, 20, 78);
    
    // Add table
    doc.autoTable({
        startY: 90,
        head: [['Asset Tag', 'Asset Name', 'Expected Location', 'Status', 'Actual Location', 'Notes']],
        html: '#resultsTable'
    });
    
    // Save PDF
    doc.save('audit_report_<?php echo date('Ymd'); ?>.pdf');
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('resultsTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Audit Results"});
    XLSX.writeFile(wb, 'audit_report_<?php echo date('Ymd'); ?>.xlsx');
}

$(document).ready(function() {
    $('#resultsTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>