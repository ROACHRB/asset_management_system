<?php
// File: modules/audits/index.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('conduct_audits')) {
    $_SESSION['error'] = "Access denied. You don't have permission to conduct audits.";
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
        <h1><i class="fas fa-clipboard-check mr-2"></i>Physical Audits</h1>
        <p class="text-muted">Manage and conduct physical inventory audits</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="new.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>New Audit
        </a>
    </div>
</div>

<!-- Audits List -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Recent Audits
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
                                    <a href="view.php?id=<?php echo $audit['audit_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if($audit['status'] == 'in_progress'): ?>
                                        <a href="conduct.php?id=<?php echo $audit['audit_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-tasks"></i> Conduct
                                        </a>
                                    <?php endif; ?>
                                    <?php if($audit['status'] == 'completed'): ?>
                                        <a href="report.php?id=<?php echo $audit['audit_id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-file-alt"></i> Report
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No audits found. <a href="new.php">Create a new audit</a>.</td>
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
        "order": [[1, "desc"]]
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>