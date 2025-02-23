
<?php
include_once "../../includes/header.php";

// Get audit list
$sql = "SELECT a.*, u.full_name as auditor_name,
        (SELECT COUNT(*) FROM audit_items ai WHERE ai.audit_id = a.audit_id) as total_items,
        (SELECT COUNT(*) FROM audit_items ai WHERE ai.audit_id = a.audit_id AND ai.status = 'missing') as missing_items
        FROM physical_audits a
        LEFT JOIN users u ON a.auditor_id = u.user_id
        ORDER BY a.audit_date DESC";
$result = mysqli_query($conn, $sql);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-clipboard-check mr-2"></i>Physical Inventory Audits</h1>
        <p class="text-muted">Track and manage physical inventory counts</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="start_audit.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Start New Audit
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Audit History
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table">
                <thead>
                    <tr>
                        <th>Audit ID</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Auditor</th>
                        <th>Items Checked</th>
                        <th>Discrepancies</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $row['audit_id']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['audit_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td><?php echo htmlspecialchars($row['auditor_name']); ?></td>
                        <td><?php echo $row['total_items']; ?></td>
                        <td>
                            <?php if($row['missing_items'] > 0): ?>
                                <span class="badge badge-danger"><?php echo $row['missing_items']; ?> missing</span>
                            <?php else: ?>
                                <span class="badge badge-success">No issues</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $row['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_audit.php?id=<?php echo $row['audit_id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if($row['status'] != 'completed'): ?>
                            <a href="continue_audit.php?id=<?php echo $row['audit_id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-play"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>