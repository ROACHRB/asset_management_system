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

// Get existing buildings for dropdown
$buildings_query = "SELECT DISTINCT building FROM locations ORDER BY building";
$buildings_result = mysqli_query($conn, $buildings_query);
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
                <div class="form-group col-md-6">
                    <label for="building" class="required-field">Building</label>
                    <select class="form-control" id="building" name="building" required>
                        <option value="">-- Select Building --</option>
                        <?php
                        // Display existing buildings in dropdown
                        while($bldg = mysqli_fetch_assoc($buildings_result)) {
                            echo '<option value="' . htmlspecialchars($bldg['building']) . '">' . 
                                htmlspecialchars($bldg['building']) . '</option>';
                        }
                        ?>
                        <option value="new_building">-- Add New Building --</option>
                    </select>
                </div>
                <div class="form-group col-md-6" id="newBuildingGroup" style="display:none;">
                    <label for="new_building_name" class="required-field">New Building Name</label>
                    <input type="text" class="form-control" id="new_building_name" name="new_building_name">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-12">
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

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.3/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function() {
    // Show/hide new building input
    $('#building').change(function() {
        if($(this).val() === 'new_building') {
            $('#newBuildingGroup').show();
            $('#new_building_name').attr('required', true);
        } else {
            $('#newBuildingGroup').hide();
            $('#new_building_name').attr('required', false);
        }
    });

    // Form validation
    $("#locationForm").validate({
        rules: {
            building: "required",
            new_building_name: {
                required: function() {
                    return $('#building').val() === 'new_building';
                }
            }
        },
        messages: {
            building: "Please select a building",
            new_building_name: "Please enter a new building name"
        },
        errorElement: "div",
        errorClass: "invalid-feedback",
        highlight: function(element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function(element) {
            $(element).removeClass("is-invalid");
        },
        submitHandler: function(form) {
            // If "Add New Building" is selected, use the new name
            if($('#building').val() === 'new_building') {
                $('#building').val($('#new_building_name').val());
            }
            form.submit();
        }
    });
});
</script>

<?php 
include_once "../../includes/footer.php";
// Flush output buffer at the end
ob_end_flush();
?>