<?php
// Start output buffering
ob_start();

include_once "../../includes/header.php";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $error = "";
    
    // Validate inputs
    if(empty(trim($_POST["building"]))) {
        $error = "Please enter building name.";
    }
    
    if(empty($error)) {
        // Prepare building and room variables
        $building = trim($_POST["building"]);
        $description = !empty($_POST["description"]) ? trim($_POST["description"]) : null;
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert location - set room to NULL
            $sql = "INSERT INTO locations (building, room, description) VALUES (?, NULL, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $building, $description);
            
            if(!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error creating location: " . mysqli_error($conn));
            }
            
            $location_id = mysqli_insert_id($conn);
            
            // Insert departments if provided
            if(isset($_POST["departments"]) && is_array($_POST["departments"])) {
                $dept_sql = "INSERT INTO location_departments (location_id, department) VALUES (?, ?)";
                $dept_stmt = mysqli_prepare($conn, $dept_sql);
                
                foreach($_POST["departments"] as $dept) {
                    if(!empty(trim($dept))) {
                        $department = trim($dept);
                        mysqli_stmt_bind_param($dept_stmt, "is", $location_id, $department);
                        mysqli_stmt_execute($dept_stmt);
                    }
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $_SESSION['success'] = "Location added successfully.";
            header("location: index.php");
            exit();
        }
        catch(Exception $e) {
            // Rollback on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-plus-circle mr-2"></i>Add New Location</h1>
        <p class="text-muted">Create a new storage location</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
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
                <div class="form-group col-md-12">
                    <label for="building" class="required-field">Building Name</label>
                    <input type="text" class="form-control" id="building" name="building" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="departments">Departments (Select Multiple)</label>
                    <select class="form-control selectpicker" id="departments" name="departments[]" multiple data-live-search="true">
                        <?php
                        // Get all departments for dropdown
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
                        
                        foreach($all_departments as $dept):
                        ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>">
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        Hold Ctrl (or Cmd on Mac) to select multiple departments or type to search.
                    </small>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addNewDept">
                            <i class="fas fa-plus"></i> Add New Department
                        </button>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Create Location
            </button>
        </form>
    </div>
</div>

<!-- Add New Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1" role="dialog" aria-labelledby="addDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDeptModalLabel">Add New Department</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="newDeptName">Department Name</label>
                    <input type="text" class="form-control" id="newDeptName">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveDeptBtn">Add Department</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Bootstrap-select for better multi-select experience -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.3/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize selectpicker
    $('.selectpicker').selectpicker({
        actionsBox: true,
        selectAllText: 'Select All',
        deselectAllText: 'Deselect All',
        selectedTextFormat: 'count > 2'
    });
    
    // Open department modal
    $('#addNewDept').click(function() {
        console.log("Add new department button clicked"); // Debug
        $('#newDeptName').val('');
        $('#addDeptModal').modal('show');
    });
    
    // Add new department
    $('#saveDeptBtn').click(function() {
        console.log("Save department button clicked"); // Debug
        var deptName = $('#newDeptName').val().trim();
        if(deptName) {
            // Check if it already exists
            if(!$('#departments option[value="' + deptName.replace(/"/g, '\\"') + '"]').length) {
                // Add to select
                var newOption = new Option(deptName, deptName, true, true);
                $('#departments').append(newOption);
                $('#departments').selectpicker('refresh');
                $('#addDeptModal').modal('hide');
            } else {
                alert('This department already exists.');
            }
        } else {
            alert('Please enter a department name.');
        }
    });

    // Form validation
    $("#locationForm").validate({
        rules: {
            building: "required"
        },
        messages: {
            building: "Please enter building name"
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

<?php 
include_once "../../includes/footer.php";
// Flush output buffer at the end
ob_end_flush();
?>