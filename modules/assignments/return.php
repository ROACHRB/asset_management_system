<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\assignments\return.php
// Include header
include_once "../../includes/header.php";
include_once "../../includes/functions.php";

// Check if assignment ID is provided
if(!isset($_GET['id']) || empty(trim($_GET['id']))) {
    // Redirect to the assignments page
    header("location: index.php");
    exit;
}

// Get assignment ID
$assignment_id = trim($_GET['id']);

// Initialize variables
$return_date = date('Y-m-d');
$condition_status = "";
$notes = "";
$error = $success = "";

// Fetch assignment details
$sql = "SELECT aa.*, a.asset_id, a.asset_name, a.asset_tag, a.condition_status as original_condition,
        u.full_name as assigned_to_name
        FROM asset_assignments aa
        JOIN assets a ON aa.asset_id = a.asset_id
        JOIN users u ON aa.assigned_to = u.user_id
        WHERE aa.assignment_id = ? AND aa.assignment_status = 'assigned'";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $assignment_id);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) == 1) {
            $assignment = mysqli_fetch_assoc($result);
            $condition_status = $assignment['original_condition'];
        } else {
            // Assignment not found or already returned
            $_SESSION['error_message'] = "Assignment not found or already processed.";
            header("location: index.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo "Oops! Something went wrong. Please try again later.";
    exit;
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate return date
    if(empty(trim($_POST["return_date"]))) {
        $error = "Please enter a return date.";
    } else {
        $return_date = sanitize_input($conn, $_POST["return_date"]);
        
        // Check if return date is after assignment date
        if(strtotime($return_date) < strtotime($assignment['assignment_date'])) {
            $error = "Return date cannot be before the assignment date.";
        }
    }
    
    // Validate condition
    if(empty(trim($_POST["condition_status"]))) {
        $error = "Please select the asset condition.";
    } else {
        $condition_status = sanitize_input($conn, $_POST["condition_status"]);
    }
    
    // Get notes
    $notes = !empty($_POST["notes"]) ? sanitize_input($conn, $_POST["notes"]) : NULL;
    
    // Check for errors before proceeding
    if(empty($error)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update assignment status
            $update_assignment_sql = "UPDATE asset_assignments 
                                     SET assignment_status = 'returned', 
                                        actual_return_date = ? 
                                     WHERE assignment_id = ?";
            
            $update_assignment_stmt = mysqli_prepare($conn, $update_assignment_sql);
            mysqli_stmt_bind_param($update_assignment_stmt, "si", $return_date, $assignment_id);
            
            if(!mysqli_stmt_execute($update_assignment_stmt)) {
                throw new Exception("Error updating assignment: " . mysqli_stmt_error($update_assignment_stmt));
            }
            
            // Update asset status and condition
            $update_asset_sql = "UPDATE assets 
                               SET status = 'available', condition_status = ? 
                               WHERE asset_id = ?";
            
            $update_asset_stmt = mysqli_prepare($conn, $update_asset_sql);
            mysqli_stmt_bind_param($update_asset_stmt, "si", $condition_status, $assignment['asset_id']);
            
            if(!mysqli_stmt_execute($update_asset_stmt)) {
                throw new Exception("Error updating asset: " . mysqli_stmt_error($update_asset_stmt));
            }
            
            // Log the return action
            $log_notes = "Asset returned from User ID: " . $assignment['assigned_to'] . ". ";
            if(!empty($notes)) {
                $log_notes .= "Notes: " . $notes;
            }
            
            log_action($conn, $assignment['asset_id'], 'returned', $_SESSION['user_id'], $log_notes);
            
            // All operations successful, commit transaction
            mysqli_commit($conn);
            
            // Set success message
            $success = "Asset successfully returned. <a href='view.php?id=" . $assignment_id . "'>View Details</a>";
            
        } catch (Exception $e) {
            // An error occurred, rollback transaction
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-10">
        <h1><i class="fas fa-undo-alt mr-2"></i>Process Asset Return</h1>
        <p class="text-muted">Record the return of an assigned asset</p>
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

<!-- Assignment Summary -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-info-circle mr-1"></i>
        Assignment Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Asset:</strong><br> 
                   <?php echo htmlspecialchars($assignment['asset_name']); ?>
                   <?php if(!empty($assignment['asset_tag'])): ?>
                   <small class="text-muted">(<?php echo htmlspecialchars($assignment['asset_tag']); ?>)</small>
                   <?php endif; ?>
                </p>
                <p><strong>Assigned To:</strong><br> <?php echo htmlspecialchars($assignment['assigned_to_name']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Assignment Date:</strong><br> <?php echo date('M d, Y', strtotime($assignment['assignment_date'])); ?></p>
                <p><strong>Expected Return:</strong><br> 
                   <?php 
                   if(!empty($assignment['expected_return_date'])) {
                       echo date('M d, Y', strtotime($assignment['expected_return_date']));
                       
                       // Show if overdue
                       if(strtotime($assignment['expected_return_date']) < time()) {
                           $days_overdue = floor((time() - strtotime($assignment['expected_return_date'])) / 86400);
                           echo ' <span class="badge badge-danger">' . $days_overdue . ' days overdue</span>';
                       }
                   } else {
                       echo '<span class="text-muted">Permanent Assignment</span>';
                   }
                   ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Return Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>
        Return Details
    </div>
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $assignment_id); ?>" method="post" id="returnForm">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="return_date" class="required-field">Return Date</label>
                    <input type="date" class="form-control" id="return_date" name="return_date" 
                           value="<?php echo $return_date; ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="condition_status" class="required-field">Asset Condition</label>
                    <select class="form-control" id="condition_status" name="condition_status" required>
                        <option value="">-- Select Condition --</option>
                        <option value="new" <?php if($condition_status == 'new') echo 'selected'; ?>>New</option>
                        <option value="good" <?php if($condition_status == 'good') echo 'selected'; ?>>Good</option>
                        <option value="fair" <?php if($condition_status == 'fair') echo 'selected'; ?>>Fair</option>
                        <option value="poor" <?php if($condition_status == 'poor') echo 'selected'; ?>>Poor</option>
                        <option value="unusable" <?php if($condition_status == 'unusable') echo 'selected'; ?>>Unusable</option>
                    </select>
                    <small class="form-text text-muted">
                        Previous condition: <strong><?php echo ucfirst($assignment['original_condition']); ?></strong>
                    </small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Return Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"
                          placeholder="Note any damage, issues, or details about the return"><?php echo htmlspecialchars($notes); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check-circle mr-1"></i> Process Return
            </button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    $("#returnForm").validate({
        rules: {
            return_date: {
                required: true,
                date: true
            },
            condition_status: "required"
        },
        messages: {
            return_date: {
                required: "Please enter the return date",
                date: "Please enter a valid date"
            },
            condition_status: "Please select the asset condition"
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
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>