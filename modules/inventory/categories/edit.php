<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\categories\edit.php
// Include header
include_once "../../../includes/header.php";

// Check user permissions
if (!in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo "<div class='alert alert-danger'>You don't have permission to access this page.</div>";
    include_once "../../../includes/footer.php";
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid category ID.</div>";
    include_once "../../../includes/footer.php";
    exit;
}

$category_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_category'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    
    if (empty($category_name)) {
        $error = "Category name is required.";
    } else {
        // Check if another category with the same name exists (excluding current)
        $check_query = "SELECT COUNT(*) as category_count FROM categories 
                        WHERE category_name = ? AND category_id != ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "si", $category_name, $category_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $category_count = mysqli_fetch_assoc($check_result)['category_count'];
        
        if ($category_count > 0) {
            $error = "Another category with this name already exists.";
        } else {
            // Update category
            $update_query = "UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ssi", $category_name, $description, $category_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Category updated successfully.";
            } else {
                $error = "Error updating category: " . mysqli_error($conn);
            }
        }
    }
}

// Get category data
$query = "SELECT * FROM categories WHERE category_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $category_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo "<div class='alert alert-danger'>Category not found.</div>";
    include_once "../../../includes/footer.php";
    exit;
}

$category = mysqli_fetch_assoc($result);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>
            <i class="fas fa-edit mr-2"></i>Edit Category
        </h1>
        <p class="text-muted">Update category details</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Categories
        </a>
    </div>
</div>

<!-- Display messages -->
<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Edit Category Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>
        Edit Category: <?php echo htmlspecialchars($category['category_name']); ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="form-group">
                <label for="category_name">Category Name*</label>
                <input type="text" class="form-control" id="category_name" name="category_name" required 
                       value="<?php echo htmlspecialchars($category['category_name']); ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Created On</label>
                <p class="form-control-static"><?php echo date('F d, Y', strtotime($category['created_at'])); ?></p>
            </div>
            
            <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php
// Include footer
include_once "../../../includes/footer.php";
?>