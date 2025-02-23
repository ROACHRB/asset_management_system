
<?php
include_once "../../includes/header.php";

// Get disposal requests
$query = "SELECT d.*, a.asset_name, a.asset_tag, 
          u1.full_name as requested_by_name,
          u2.full_name as approved_by_name
          FROM disposal_requests d
          JOIN assets a ON d.asset_id = a.asset_id
          JOIN users u1 ON d.requested_by = u1.user_id
          LEFT JOIN users u2 ON d.approved_by = u2.user_id
          ORDER BY d.request_date DESC";
$result = mysqli_query($conn, $query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-trash-alt mr-2"></i>Disposal Management</h1>
        <p class="text-muted">Track and manage asset disposal requests</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="request.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>New Disposal Request
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Disposal Requests
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table">
                <thead>
                    <tr>
                        <th>Request Date</th>
                        <th>Asset</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Approved By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['asset_name']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($row['asset_tag']); ?>)</small>
                        </td>
                        <td><?php echo htmlspecialchars($row['reason']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo ($row['status'] == 'approved' ? 'success' : 
                                    ($row['status'] == 'rejected' ? 'danger' : 
                                    ($row['status'] == 'completed' ? 'info' : 'warning'))); 
                            ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['requested_by_name']); ?></td>
                        <td><?php echo $row['approved_by_name'] ?? 'Pending'; ?></td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $row['disposal_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if($row['status'] == 'pending' && 
                                       ($_SESSION['role'] == 'admin' || 
                                        $_SESSION['user_id'] == $row['requested_by'])): ?>
                                <a href="edit.php?id=<?php echo $row['disposal_id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Edit Request">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if($row['status'] == 'pending' && $_SESSION['role'] == 'admin'): ?>
                                <a href="approve.php?id=<?php echo $row['disposal_id']; ?>" 
                                   class="btn btn-sm btn-success" title="Review Request">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if($row['status'] == 'approved' && $_SESSION['role'] == 'admin'): ?>
                                <a href="complete.php?id=<?php echo $row['disposal_id']; ?>" 
                                   class="btn btn-sm btn-warning" title="Mark as Disposed">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                                <?php endif; ?>
                            </div>
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
    $('.data-table').DataTable({
        order: [[0, 'desc']]
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>