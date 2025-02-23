
<?php
include_once "../../includes/header.php";

// Get assignment statistics
$stats_query = "SELECT 
    COUNT(*) as total_assignments,
    SUM(CASE WHEN assignment_status = 'assigned' THEN 1 ELSE 0 END) as active_assignments,
    SUM(CASE WHEN assignment_status = 'returned' THEN 1 ELSE 0 END) as returned_assignments,
    COUNT(DISTINCT assigned_to) as total_users
FROM asset_assignments";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent assignments
$assignments_query = "SELECT aa.*, a.asset_name, a.asset_tag,
    u1.full_name as assigned_to_name,
    u2.full_name as assigned_by_name
FROM asset_assignments aa
JOIN assets a ON aa.asset_id = a.asset_id
JOIN users u1 ON aa.assigned_to = u1.user_id
JOIN users u2 ON aa.assigned_by = u2.user_id
ORDER BY aa.assignment_date DESC
LIMIT 10";
$assignments_result = mysqli_query($conn, $assignments_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-check mr-2"></i>Assignment Report</h1>
        <p class="text-muted">Track asset assignments and user history</p>
    </div>
    <div class="col-md-4 text-right">
        <button onclick="exportToPDF()" class="btn btn-danger">
            <i class="fas fa-file-pdf mr-1"></i>Export PDF
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Assignments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['total_assignments']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Active Assignments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['active_assignments']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Returned Assets</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['returned_assignments']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-undo-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['total_users']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Assignments Table -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>Recent Assignments
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="assignmentsTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Asset</th>
                        <th>Assigned To</th>
                        <th>Assigned By</th>
                        <th>Status</th>
                        <th>Return Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($assignments_result)): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['assignment_date'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['asset_name']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($row['asset_tag']); ?>)</small>
                        </td>
                        <td><?php echo htmlspecialchars($row['assigned_to_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['assigned_by_name']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $row['assignment_status'] == 'assigned' ? 'primary' : 'success'; ?>">
                                <?php echo ucfirst($row['assignment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            if($row['assignment_status'] == 'returned') {
                                echo date('M d, Y', strtotime($row['actual_return_date']));
                            } elseif(!empty($row['expected_return_date'])) {
                                echo 'Expected: ' . date('M d, Y', strtotime($row['expected_return_date']));
                            } else {
                                echo 'Permanent';
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

<script>
$(document).ready(function() {
    $('#assignmentsTable').DataTable();
});

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Add title
    doc.text("Asset Assignment Report", 14, 15);
    
    // Add statistics
    doc.text("Statistics", 14, 25);
    doc.text("Total Assignments: <?php echo $stats['total_assignments']; ?>", 14, 35);
    doc.text("Active Assignments: <?php echo $stats['active_assignments']; ?>", 14, 45);
    doc.text("Returned Assets: <?php echo $stats['returned_assignments']; ?>", 14, 55);
    
    // Add table
    doc.autoTable({ 
        html: '#assignmentsTable',
        startY: 70,
        theme: 'grid'
    });
    
    doc.save("assignment_report.pdf");
}
</script>

<?php include_once "../../includes/footer.php"; ?>