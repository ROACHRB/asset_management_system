<?php
// File: modules/storage/edit.php
include_once "../../includes/header.php";

// Check if ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid location ID.";
    header("Location: index.php");
    exit;
}

$location_id = $_GET['id'];

// Get existing location data
$location_query = "SELECT * FROM locations WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $location_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$location_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($location_result) == 0) {
    $_SESSION['error'] = "Location not found.";
    header("Location: index.php");
    exit;
}

$location = mysqli_fetch_assoc($location_result);

// Get current departments (if using multiple departments)
$departments = [];
$dept_query = "SELECT department FROM location_departments WHERE location_id = ?";
$stmt = mysqli_prepare($conn, $dept_query);
mysqli_stmt_bind_param($stmt, "i", $location_id);
mysqli_stmt_execute($stmt);
$dept_result = mysqli_stmt_get_result($stmt);

while($dept = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $dept['department'];
}

// If no departments found in the junction table, check the old department field
if(empty($departments) && !empty($location['department'])) {
    $departments[] = $location['department'];
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $error = "";
    
    // Validate inputs
    if(empty(trim($_POST["building"]))) {
        $error = "Please enter building name.";
    } elseif(empty(trim($_POST["room"]))) {
        $error = "Please enter room name.";
    }
    
    if(empty($error)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update location information
            $update_sql = "UPDATE locations SET building = ?, room = ?, description = ? WHERE location_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sssi", 
                trim($_POST["building"]),
                trim($_POST["room"]),
                !empty($_POST["description"]) ? trim($_POST["description"]) : null,
                $location_id
            );
            
            if(!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating location: " . mysqli_error($conn));
            }
            
            // Handle multiple departments
            // First, delete existing department connections
            $delete_depts = "DELETE FROM location_departments WHERE location_id = ?";
            $stmt = mysqli_prepare($conn, $delete_depts);
            mysqli_stmt_bind_param($stmt, "i", $location_id);
            
            if(!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error clearing departments: " . mysqli_error($conn));
            }
            
            // Insert new department connections if any departments were selected
            if(isset($_POST["departments"]) && !empty($_POST["departments"])) {
                $insert_dept = "INSERT INTO location_departments (location_id, department) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $insert_dept);
                
                foreach($_POST["departments"] as $dept) {
                    if(!empty(trim($dept))) {
                        mysqli_stmt_bind_param($stmt, "is", $location_id, trim($dept));
                        
                        if(!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Error adding department: " . mysqli_error($conn));
                        }
                    }
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Success message and redirect
            $_SESSION['success'] = "Location updated successfully.";
            header("Location: view.php?id=" . $location_id);
            exit();
            
        } catch(Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Get list of all departments for dropdown
$all_departments = [];
$all_depts_query = "SELECT DISTINCT department FROM location_departments 
                  UNION 
                  SELECT DISTINCT department FROM locations WHERE department IS NOT NULL";
$all_depts_result = mysqli_query($conn, $all_depts_query);

while($dept = mysqli_fetch_assoc($all_depts_result)) {
    if(!empty($dept['department'])) {
        $all_departments[] = $dept['department'];
    }
}
// Add some default departments if list is empty
if(empty($all_departments)) {
    $all_departments = ['IT', 'Finance', 'HR', 'Operations', 'Sales', 'Marketing', 'Research'];
}

sort($all_departments); // Sort alphabetically
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-edit mr-2"></i>Edit Location</h1>
        <p class="text-muted">Update storage location information</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary mr-2">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
        <a href="view.php?id=<?php echo $location_id; ?>" class="btn btn-info">
            <i class="fas fa-eye mr-2"></i>View Details
        </a>
    </div>
</div>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>Location Information
    </div>
    <div class="card-body">
        <form method="post" id="locationForm">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="building" class="required-field">Building</label>
                    <input type="text" class="form-control" id="building" name="building" 
                           value="<?php echo htmlspecialchars($location['building']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="room" class="required-field">Room</label>
                    <input type="text" class="form-control" id="room" name="room" 
                           value="<?php echo htmlspecialchars($location['room']); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="departments">Departments (Select Multiple)</label>
                    <select class="form-control selectpicker" id="departments" name="departments[]" multiple data-live-search="true">
                        <?php foreach($all_departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                <?php echo in_array($dept, $departments) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        Hold Ctrl (or Cmd on Mac) to select multiple departments or click to add new.
                    </small>
                </div>
                <div class="form-group col-md-6">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($location['description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Update Location
            </button>
        </form>
    </div>
</div>

<!-- Add Bootstrap-select for better multi-select experience -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize selectpicker
    $('.selectpicker').selectpicker({
        actionsBox: true,
        selectAllText: 'Select All',
        deselectAllText: 'Deselect All',
        selectedTextFormat: 'count > 2'
    });
    
    // Allow adding new options
    $('#departments').on('shown.bs.select', function() {
        var $searchbox = $('.bootstrap-select .bs-searchbox input');
        var $selectpicker = $(this);
        
        $searchbox.off('keydown').on('keydown', function(e) {
            if(e.keyCode === 13) { // Enter key
                e.preventDefault();
                var value = $(this).val().trim();
                
                if(value && !$selectpicker.find('option[value="' + value + '"]').length) {
                    // Add new option
                    var newOption = new Option(value, value, true, true);
                    $selectpicker.append(newOption);
                    $selectpicker.selectpicker('refresh');
                    $(this).val('');
                }
            }
        });
    });

    // Form validation
    $("#locationForm").validate({
        rules: {
            building: "required",
            room: "required"
        },
        messages: {
            building: "Please enter building name",
            room: "Please enter room name"
        },
        errorElement: "div",
        errorClass: "invalid-feedback",
        highlight: function(element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function(element) {
            $(element).removeClass("is-invalid");
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>