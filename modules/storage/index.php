<?php
include_once "../../includes/header.php";

// Get storage locations with asset counts
$query = "SELECT l.*, 
          (SELECT COUNT(*) FROM assets WHERE location_id = l.location_id) as total_assets,
          (SELECT COUNT(*) FROM assets WHERE location_id = l.location_id AND status = 'available') as available_assets
          FROM locations l 
          ORDER BY l.building, l.room";
$result = mysqli_query($conn, $query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-warehouse mr-2"></i>Storage Management</h1>
        <p class="text-muted">Manage storage locations and departments</p>
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
                        <th>Room</th>
                        <th>Department</th>
                        <th>Total Assets</th>
                        <th>Available Assets</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['building']); ?></td>
                        <td><?php echo htmlspecialchars($row['room']); ?></td>
                        <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                        <td class="text-center"><?php echo $row['total_assets']; ?></td>
                        <td class="text-center"><?php echo $row['available_assets']; ?></td>
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
                                <a href="delete.php?id=<?php echo $row['location_id']; ?>" 
                                   class="btn btn-sm btn-danger confirm-delete" 
                                   data-assets="<?php echo $row['total_assets']; ?>"
                                   title="Delete Location">
                                    <i class="fas fa-trash"></i>
                                </a>
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
        const assets = $(this).data('assets');
        if(assets > 0) {
            alert('Cannot delete location with existing assets. Please move assets first.');
            return;
        }
        if(confirm('Are you sure you want to delete this location?')) {
            window.location = $(this).attr('href');
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>