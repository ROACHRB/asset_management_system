<?php
// File: modules/reports/audit_list.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('generate_reports')) {
    $_SESSION['error'] = "Access denied. You don't have permission to access reports.";
    header("Location: ../../index.php");
    exit;
}

// Get list of audits
$audits_query = "SELECT a.*, u.full_name as auditor_name, 
                (SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id) as total_items,
                (SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id AND status = 'found') as found_items,
                (SELECT COUNT(*) FROM audit_items WHERE audit_id = a.audit_id AND status = 'missing') as missing_items
                FROM physical_audits a
                JOIN users u ON a.auditor_id = u.user_id
                ORDER BY a.audit_date DESC";
$audits_result = mysqli_query($conn, $audits_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-clipboard-check mr-2"></i>Audit History</h1>
        <p class="text-muted">View and analyze physical inventory audit records</p>
    </div>
    <div class="col-md-4 text-right">
        <div class="btn-group">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reports
            </a>
            <?php if(has_permission('conduct_audits')): ?>
            <a href="audit_conduct.php" class="btn btn-primary">
                <i class="fas fa-plus mr-2"></i>New Audit
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-success" onclick="exportTableToCSV('audit_history.csv')">
                <i class="fas fa-file-csv mr-2"></i>Export CSV
            </button>
        </div>
    </div>
</div>

<!-- Audits List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Audit History
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="auditsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Auditor</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($audits_result) > 0): ?>
                        <?php while($audit = mysqli_fetch_assoc($audits_result)): ?>
                            <tr>
                                <td><?php echo $audit['audit_id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($audit['audit_date'])); ?></td>
                                <td><?php echo htmlspecialchars($audit['location']); ?></td>
                                <td><?php echo htmlspecialchars($audit['auditor_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo ($audit['status'] == 'completed') ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($audit['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $total = $audit['total_items'];
                                    $found = $audit['found_items'];
                                    $missing = $audit['missing_items'];
                                    $percent = ($total > 0) ? round(($found + $missing) / $total * 100) : 0;
                                    ?>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%" 
                                             aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percent; ?>%
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $found; ?> found, 
                                        <?php echo $missing; ?> missing, 
                                        <?php echo $total - $found - $missing; ?> pending
                                    </small>
                                </td>
                                <td>
                                    <a href="audit_view.php?id=<?php echo $audit['audit_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if(has_permission('conduct_audits')): ?>
                                        <?php if($audit['status'] == 'in_progress'): ?>
                                            <a href="audit_conduct.php?id=<?php echo $audit['audit_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-tasks"></i> Conduct
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if($audit['status'] == 'completed'): ?>
                                        <a href="audit_report.php?id=<?php echo $audit['audit_id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-file-alt"></i> Report
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No audits found. <?php if(has_permission('conduct_audits')): ?><a href="audit_conduct.php">Create a new audit</a>.<?php endif; ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#auditsTable').DataTable({
        "order": [[1, "desc"]],
        "dom": 'Bfrtip',
        "buttons": [
            'copy', 'excel', 'pdf'
        ]
    });
});

function exportTableToCSV(filename) {
    // CSV export functionality
    let csv = [];
    const rows = document.querySelectorAll('table tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip the Actions column
            // Replace any commas in the cell text with a space to avoid CSV issues
            let text = cols[j].innerText.replace(/,/g, ' ');
            // Remove any special characters that might interfere with CSV format
            text = text.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    
    // Download CSV file
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: "text/csv"});
    const downloadLink = document.createElement("a");
    
    // File name
    downloadLink.download = filename;
    
    // Create a link to the file
    downloadLink.href = window.URL.createObjectURL(csvFile);
    
    // Hide download link
    downloadLink.style.display = "none";
    
    // Add the link to DOM
    document.body.appendChild(downloadLink);
    
    // Click download link
    downloadLink.click();
}
</script>

<?php include_once "../../includes/footer.php"; ?>