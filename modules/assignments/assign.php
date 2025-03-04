<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\assignments\assign.php
// Include header
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Initialize variables
$asset_id = $assigned_to = "";
$assignment_date = date('Y-m-d');
$expected_return_date = "";
$assignment_type = "temporary";
$notes = "";
$error = $success = "";

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate asset
    if(empty(trim($_POST["asset_id"]))) {
        $error = "Please select an asset.";
    } else {
        $asset_id = intval($_POST["asset_id"]);
        
        // Check if asset exists and is available
        $asset_check = "SELECT asset_id, status FROM assets WHERE asset_id = ?";
        $check_stmt = mysqli_prepare($conn, $asset_check);
        mysqli_stmt_bind_param($check_stmt, "i", $asset_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if(mysqli_num_rows($check_result) != 1) {
            $error = "Invalid asset selected.";
        } else {
            $asset = mysqli_fetch_assoc($check_result);
            if($asset['status'] != 'available') {
                $error = "This asset is not available for assignment.";
            }
        }
        
        mysqli_stmt_close($check_stmt);
    }
    
    // Validate user
    if(empty(trim($_POST["assigned_to"]))) {
        $error = "Please select a user.";
    } else {
        $assigned_to = intval($_POST["assigned_to"]);
        
        // Check if user exists
        $user_check = "SELECT user_id FROM users WHERE user_id = ?";
        $user_stmt = mysqli_prepare($conn, $user_check);
        mysqli_stmt_bind_param($user_stmt, "i", $assigned_to);
        mysqli_stmt_execute($user_stmt);
        mysqli_stmt_store_result($user_stmt);
        
        if(mysqli_stmt_num_rows($user_stmt) != 1) {
            $error = "Invalid user selected.";
        }
        
        mysqli_stmt_close($user_stmt);
    }
    
    // Validate assignment date
    if(empty(trim($_POST["assignment_date"]))) {
        $error = "Please enter an assignment date.";
    } else {
        $assignment_date = sanitize_input($conn, $_POST["assignment_date"]);
    }
    
    // Get assignment type
    $assignment_type = sanitize_input($conn, $_POST["assignment_type"]);
    
    // Validate expected return date for temporary assignments
    if($assignment_type == "temporary") {
        if(empty(trim($_POST["expected_return_date"]))) {
            $error = "Please enter an expected return date.";
        } else {
            $expected_return_date = sanitize_input($conn, $_POST["expected_return_date"]);
            
            // Check if return date is after assignment date
            if(strtotime($expected_return_date) <= strtotime($assignment_date)) {
                $error = "Expected return date must be after the assignment date.";
            }
        }
    } else {
        $expected_return_date = NULL;
    }
    
    // Get notes
    $notes = !empty($_POST["notes"]) ? sanitize_input($conn, $_POST["notes"]) : NULL;
    
    // Check for errors before proceeding
    if(empty($error)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Create assignment record
            $assign_sql = "INSERT INTO asset_assignments (asset_id, assigned_to, assigned_by, 
                                                        assignment_date, expected_return_date, 
                                                        assignment_status, notes) 
                          VALUES (?, ?, ?, ?, ?, 'assigned', ?)";
            
            $assign_stmt = mysqli_prepare($conn, $assign_sql);
            mysqli_stmt_bind_param($assign_stmt, "iiisss", 
                                  $asset_id, $assigned_to, $_SESSION['user_id'], 
                                  $assignment_date, $expected_return_date, $notes);
            
            if(!mysqli_stmt_execute($assign_stmt)) {
                throw new Exception("Error creating assignment: " . mysqli_stmt_error($assign_stmt));
            }
            
            $assignment_id = mysqli_insert_id($conn);
            
            // Update asset status
            $update_sql = "UPDATE assets SET status = 'assigned' WHERE asset_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "i", $asset_id);
            
            if(!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Error updating asset status: " . mysqli_stmt_error($update_stmt));
            }
            
            // Log the assignment
            log_action($conn, $asset_id, 'assigned', $_SESSION['user_id'], 
                      'Asset assigned to User ID: ' . $assigned_to . 
                      ($assignment_type == 'temporary' ? ' until ' . $expected_return_date : ' permanently'));
            
            // All operations successful, commit transaction
            mysqli_commit($conn);
            
            // Set success message
            $success = "Asset successfully assigned. <a href='view.php?id=" . $assignment_id . "'>View Assignment</a>";
            
            // Reset form
            $asset_id = $assigned_to = "";
            $assignment_date = date('Y-m-d');
            $expected_return_date = "";
            $assignment_type = "temporary";
            $notes = "";
            
        } catch (Exception $e) {
            // An error occurred, rollback transaction
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get available assets
$assets_query = "SELECT asset_id, asset_name, asset_tag, serial_number 
                FROM assets 
                WHERE status = 'available' 
                ORDER BY asset_name";
$assets_result = mysqli_query($conn, $assets_query);

// Get users
$users_query = "SELECT user_id, full_name, department 
               FROM users 
               ORDER BY full_name";
$users_result = mysqli_query($conn, $users_query);
?>

<div class="row mb-4">
    <div class="col-md-10">
        <h1><i class="fas fa-user-plus mr-2"></i>Assign Asset</h1>
        <p class="text-muted">Assign an asset to a user</p>
    </div>
    <div class="col-md-2 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<!-- Error/Success Messages -->
<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Assignment Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>
        Assignment Information
    </div>
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="assignmentForm">
            
            <!-- Asset Selection -->
            <h5 class="border-bottom pb-2 mb-3">Asset Information</h5>
            
            <div class="form-group">
                <label for="asset_id" class="required-field">Select Asset</label>
                <select class="form-control" id="asset_id" name="asset_id" required>
                    <option value="">-- Select Available Asset --</option>
                    <?php
                    if(mysqli_num_rows($assets_result) > 0) {
                        while($asset = mysqli_fetch_assoc($assets_result)) {
                            echo '<option value="' . $asset['asset_id'] . '"';
                            if($asset_id == $asset['asset_id']) echo ' selected';
                            echo '>' . htmlspecialchars($asset['asset_name']);
                            
                            if(!empty($asset['asset_tag'])) {
                                echo ' (' . htmlspecialchars($asset['asset_tag']) . ')';
                            } elseif(!empty($asset['serial_number'])) {
                                echo ' (SN: ' . htmlspecialchars($asset['serial_number']) . ')';
                            }
                            
                            echo '</option>';
                        }
                    }
                    ?>
                </select>
                <small class="form-text text-muted">
                    Only available assets are shown in this list
                </small>
            </div>
            
            <div id="assetDetails" class="mb-4 d-none">
                <!-- Asset details will be loaded here via AJAX -->
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Asset Details</h6>
                        <div id="assetDetailsContent"></div>
                    </div>
                </div>
            </div>
            
            <!-- Assignment Details -->
            <h5 class="border-bottom pb-2 mb-3 mt-4">Assignment Details</h5>
            
            <div class="form-group">
                <label for="assigned_to" class="required-field">Assign To</label>
                <select class="form-control" id="assigned_to" name="assigned_to" required>
                    <option value="">-- Select User --</option>
                    <?php
                    if(mysqli_num_rows($users_result) > 0) {
                        while($user = mysqli_fetch_assoc($users_result)) {
                            echo '<option value="' . $user['user_id'] . '"';
                            if($assigned_to == $user['user_id']) echo ' selected';
                            echo '>' . htmlspecialchars($user['full_name']);
                            
                            if(!empty($user['department'])) {
                                echo ' (' . htmlspecialchars($user['department']) . ')';
                            }
                            
                            echo '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="assignment_date" class="required-field">Assignment Date</label>
                    <input type="date" class="form-control" id="assignment_date" name="assignment_date" 
                           value="<?php echo $assignment_date; ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="assignment_type" class="required-field">Assignment Type</label>
                    <select class="form-control" id="assignment_type" name="assignment_type" required>
                        <option value="temporary" <?php if($assignment_type == 'temporary') echo 'selected'; ?>>Temporary</option>
                        <option value="permanent" <?php if($assignment_type == 'permanent') echo 'selected'; ?>>Permanent</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" id="returnDateGroup">
                <label for="expected_return_date" class="required-field">Expected Return Date</label>
                <input type="date" class="form-control" id="expected_return_date" name="expected_return_date" 
                       value="<?php echo $expected_return_date; ?>">
                <small class="form-text text-muted">
                    The date when the asset is expected to be returned
                </small>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"
                          placeholder="Any additional information about this assignment"><?php echo htmlspecialchars($notes); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-save mr-1"></i> Assign Asset
            </button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>
$(document).ready(function() {
    // Handle assignment type change
    $(document).ready(function() {
    // Handle assignment type change
    $('#assignment_type').change(function() {
        if ($(this).val() === 'temporary') {
            // Show return date field
            $('#returnDateGroup').show();
            $('#expected_return_date').prop('required', true);
        } else {
            // Hide return date field and reset value
            $('#returnDateGroup').hide();
            $('#expected_return_date').prop('required', false).val('');

            // Show confirmation popup
            Swal.fire({
                title: "Confirm Permanent Assignment",
                text: "This asset will be assigned permanently. No return date is required.",
                icon: "warning",
                confirmButtonText: "OK",
                allowOutsideClick: false
            });
        }
    });

    // Trigger change on page load to set initial state
    $('#assignment_type').trigger('change');
});

    
    // Trigger change on page load
    $('#assignment_type').trigger('change');
    
    // Load asset details when an asset is selected
    $('#asset_id').change(function() {
        const assetId = $(this).val();
        if(assetId) {
            $.ajax({
                url: 'get_asset_details.php',
                type: 'GET',
                data: { id: assetId },
                success: function(response) {
                    $('#assetDetailsContent').html(response);
                    $('#assetDetails').removeClass('d-none');
                },
                error: function() {
                    $('#assetDetails').addClass('d-none');
                    alert('Error loading asset details.');
                }
            });
        } else {
            $('#assetDetails').addClass('d-none');
        }
    });
    
    // Trigger change if asset is already selected
    if($('#asset_id').val()) {
        $('#asset_id').trigger('change');
    }
    
    // Form validation
    $("#assignmentForm").validate({
        rules: {
            asset_id: "required",
            assigned_to: "required",
            assignment_date: "required",
            expected_return_date: {
                required: function() {
                    return $("#assignment_type").val() === "temporary";
                },
                greaterThan: "#assignment_date"
            }
        },
        messages: {
            asset_id: "Please select an asset",
            assigned_to: "Please select a user",
            assignment_date: "Please enter assignment date",
            expected_return_date: {
                required: "Please enter expected return date",
                greaterThan: "Return date must be after assignment date"
            }
        },
        errorElement: "div",
        errorClass: "invalid-feedback",
        highlight: function(element) {
            $(element).addClass("is-invalid");
        },
        unhighlight: function(element) {
            $(element).removeClass("is-invalid");
        },
        errorPlacement: function(error, element) {
            error.insertAfter(element);
        }
    });
    
    // Custom validation method for date comparison
    $.validator.addMethod("greaterThan", function(value, element, param) {
        const startDate = $(param).val();
        if(!startDate || !value) {
            return true;
        }
        return new Date(value) > new Date(startDate);
    }, "End date must be after start date");
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>