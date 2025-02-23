
<?php
include_once "../../includes/header.php";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $error = "";
    
    // Validate inputs
    if(empty(trim($_POST["building"]))) {
        $error = "Please enter building name.";
    }
    if(empty(trim($_POST["room"]))) {
        $error = "Please enter room name.";
    }
    
    if(empty($error)) {
        $sql = "INSERT INTO locations (building, room, department, description) VALUES (?, ?, ?, ?)";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssss", 
                trim($_POST["building"]),
                trim($_POST["room"]),
                !empty($_POST["department"]) ? trim($_POST["department"]) : null,
                !empty($_POST["description"]) ? trim($_POST["description"]) : null
            );
            
            if(mysqli_stmt_execute($stmt)) {
                header("location: index.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
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
                <div class="form-group col-md-6">
                    <label for="building" class="required-field">Building</label>
                    <input type="text" class="form-control" id="building" name="building" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="room" class="required-field">Room</label>
                    <input type="text" class="form-control" id="room" name="room" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="department">Department</label>
                    <input type="text" class="form-control" id="department" name="department">
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

<script>
$(document).ready(function() {
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