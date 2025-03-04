<?php
include_once "../../includes/header.php";

// Get storage locations with asset counts
$query = "SELECT l.*, 
          (SELECT COUNT(*) FROM assets WHERE location_id = l.location_id) as total_assets,
          (SELECT COUNT(*) FROM assets WHERE location_id = l.location_id AND status = 'available') as available_assets
          FROM locations l 
          GROUP BY l.location_id
          ORDER BY l.building, l.room";
$result = mysqli_query($conn, $query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-warehouse mr-2"></i>Storage Management</h1>
        <p class="text-muted">Manage storage locations</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Add New Location
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-list mr-1"></i>Storage Locations
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table">
                <thead>
                    <tr>
                        <th>Building</th>
                        <th>Total Assets</th>
                        <th>Available Assets</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr<?php echo isset($row['status']) && $row['status'] == 'inactive' ? ' class="table-secondary"' : ''; ?>>
                        <td>
                            <?php echo htmlspecialchars($row['building']); ?>
                            <?php if(!empty($row['room'])): ?>
                                <small class="text-muted d-block">Room: <?php echo htmlspecialchars($row['room']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $row['total_assets']; ?></td>
                        <td class="text-center"><?php echo $row['available_assets']; ?></td>
                        <td class="text-center">
                            <?php if(isset($row['status']) && $row['status'] == 'inactive'): ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php else: ?>
                                <span class="badge badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $row['location_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $row['location_id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Edit Location">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if($row['total_assets'] > 0): ?>
                                    <?php if(isset($row['status']) && $row['status'] == 'inactive'): ?>
                                        <a href="toggle_status.php?id=<?php echo $row['location_id']; ?>&status=active" 
                                           class="btn btn-sm btn-success" title="Activate Location">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="toggle_status.php?id=<?php echo $row['location_id']; ?>&status=inactive" 
                                           class="btn btn-sm btn-warning" title="Deactivate Location">
                                            <i class="fas fa-toggle-off"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="delete.php?id=<?php echo $row['location_id']; ?>" 
                                       class="btn btn-sm btn-danger confirm-delete" 
                                       title="Delete Location">
                                        <i class="fas fa-trash"></i>
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
    // Initialize DataTable
    $('.data-table').DataTable();

    // Handle delete confirmation
    $('.confirm-delete').click(function(e) {
        e.preventDefault();
        if(confirm('Are you sure you want to delete this location?')) {
            window.location = $(this).attr('href');
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>