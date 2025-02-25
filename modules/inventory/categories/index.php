<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\categories\index.php
// Include header
include_once "../../../includes/header.php";

// Check user permissions
if (!in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo "<div class='alert alert-danger'>You don't have permission to access this page.</div>";
    include_once "../../../includes/footer.php";
    exit;
}

// Handle category deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    // First check if any assets are using this category
    $check_query = "SELECT COUNT(*) as asset_count FROM assets WHERE category_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "i", $category_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $asset_count = mysqli_fetch_assoc($check_result)['asset_count'];
    
    if ($asset_count > 0) {
        $delete_error = "Cannot delete this category. It is assigned to $asset_count assets.";
    } else {
        // Safe to delete
        $delete_query = "DELETE FROM categories WHERE category_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, "i", $category_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $delete_success = "Category deleted successfully.";
        } else {
            $delete_error = "Error deleting category: " . mysqli_error($conn);
        }
    }
}

// Handle category addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    
    if (empty($category_name)) {
        $add_error = "Category name is required.";
    } else {
        // Check if category already exists
        $check_query = "SELECT COUNT(*) as category_count FROM categories WHERE category_name = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $category_name);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $category_count = mysqli_fetch_assoc($check_result)['category_count'];
        
        if ($category_count > 0) {
            $add_error = "A category with this name already exists.";
        } else {
            // Add category
            $add_query = "INSERT INTO categories (category_name, description) VALUES (?, ?)";
            $add_stmt = mysqli_prepare($conn, $add_query);
            mysqli_stmt_bind_param($add_stmt, "ss", $category_name, $description);
            
            if (mysqli_stmt_execute($add_stmt)) {
                $add_success = "Category added successfully.";
                // Clear form data
                $category_name = $description = "";
            } else {
                $add_error = "Error adding category: " . mysqli_error($conn);
            }
        }
    }
}

// Get categories list
$query = "SELECT c.*, COUNT(a.asset_id) as asset_count 
          FROM categories c
          LEFT JOIN assets a ON c.category_id = a.category_id
          GROUP BY c.category_id
          ORDER BY c.category_name";
$result = mysqli_query($conn, $query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>
            <i class="fas fa-tags mr-2"></i>Asset Categories
        </h1>
        <p class="text-muted">Manage asset categories to organize your inventory</p>
    </div>
    <div class="col-md-4 text-right">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
            <i class="fas fa-plus mr-2"></i>Add New Category
        </button>
    </div>
</div>

<!-- Display messages -->
<?php if (isset($delete_success)): ?>
    <div class="alert alert-success"><?php echo $delete_success; ?></div>
<?php endif; ?>

<?php if (isset($delete_error)): ?>
    <div class="alert alert-danger"><?php echo $delete_error; ?></div>
<?php endif; ?>

<?php if (isset($add_success)): ?>
    <div class="alert alert-success"><?php echo $add_success; ?></div>
<?php endif; ?>

<!-- Categories List -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-table mr-1"></i>
        Asset Categories
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered data-table" id="categoriesTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Assets Count</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            echo '<tr>';
                            echo '<td>' . $row['category_id'] . '</td>';
                            echo '<td>' . htmlspecialchars($row['category_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['description'] ?? 'No description') . '</td>';
                            echo '<td>' . $row['asset_count'] . '</td>';
                            echo '<td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
                            echo '<td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="edit.php?id=' . $row['category_id'] . '" class="btn btn-sm btn-primary" title="Edit Category">
                                        <i class="fas fa-edit"></i>
                                    </a>';
                            // Only show delete button if no assets are assigned
                            if ($row['asset_count'] == 0) {
                                echo '<a href="index.php?delete=' . $row['category_id'] . '" class="btn btn-sm btn-danger confirm-delete" title="Delete Category">
                                    <i class="fas fa-trash"></i>
                                </a>';
                            }
                            echo '</div>
                                </td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="6" class="text-center">No categories found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php if (isset($add_error)): ?>
                        <div class="alert alert-danger"><?php echo $add_error; ?></div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="category_name">Category Name*</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required 
                               value="<?php echo isset($category_name) ? htmlspecialchars($category_name) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#categoriesTable').DataTable({
        order: [[1, 'asc']], // Sort by category name
        pageLength: 10
    });
    
    // Confirm delete
    $('.confirm-delete').click(function(e) {
        if (!confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    <?php if (isset($add_error)): ?>
        $('#addCategoryModal').modal('show');
    <?php endif; ?>
});
</script>

<?php
// Include footer
include_once "../../../includes/footer.php";
?>