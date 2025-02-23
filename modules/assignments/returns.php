<?php
include_once "../../includes/header.php";

// Initialize $assignment variable
$assignment = null;
$error = "";

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "No assignment ID provided.";
} else {
    $assignment_id = $_GET['id'];
    
    // Fetch assignment details
    $sql = "SELECT aa.*, a.asset_name, a.asset_tag, a.condition_status, 
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
            } else {
                $error = "Assignment not found or already returned.";
            }
        } else {
            $error = "Error fetching assignment details.";
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Process return form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && $assignment) {
    $return_date = $_POST['return_date'];
    $notes = $_POST['notes'];
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update assignment status
        $update_assignment = "UPDATE asset_assignments 
                            SET assignment_status = 'returned',
                                actual_return_date = ?,
                                return_notes = ?
                            WHERE assignment_id = ?";
        
        $stmt = mysqli_prepare($conn, $update_assignment);
        mysqli_stmt_bind_param($stmt, "ssi", $return_date, $notes, $assignment_id);
        mysqli_stmt_execute($stmt);
        
        // Update asset status
        $update_asset = "UPDATE assets SET status = 'available' WHERE asset_id = ?";
        $stmt = mysqli_prepare($conn, $update_asset);
        mysqli_stmt_bind_param($stmt, "i", $assignment['asset_id']);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        
        // Redirect after successful return
        echo "<script>
            alert('Asset returned successfully!');
            window.location.href = 'index.php';
        </script>";
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Error processing return: " . $e->getMessage();
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-undo mr-2"></i>Process Return</h1>
        <p class="text-muted">Record the return of an assigned asset</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<?php if($error): ?>
<div class="alert alert-danger">
    <?php echo $error; ?>
    <br>
    <a href="index.php" class="btn btn-sm btn-secondary mt-2">Return to List</a>
</div>
<?php else: ?>

<!-- Assignment Details -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-info-circle mr-1"></i>Assignment Details
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Asset:</strong><br>
                    <?php echo htmlspecialchars($assignment['asset_name']); ?>
                    <small class="text-muted">(<?php echo htmlspecialchars($assignment['asset_tag']); ?>)</small>
                </p>
                <p><strong>Current Condition:</strong><br>
                    <?php echo ucfirst($assignment['condition_status']); ?>
                </p>
            </div>
            <div class="col-md-6">
                <p><strong>Assigned To:</strong><br>
                    <?php echo htmlspecialchars($assignment['assigned_to_name']); ?>
                </p>
                <p><strong>Assignment Date:</strong><br>
                    <?php echo date('M d, Y', strtotime($assignment['assignment_date'])); ?>
                </p>
                <?php if(!empty($assignment['expected_return_date'])): ?>
                <p><strong>Expected Return:</strong><br>
                    <?php 
                    echo date('M d, Y', strtotime($assignment['expected_return_date']));
                    if(strtotime($assignment['expected_return_date']) < time()) {
                        echo ' <span class="badge badge-danger">Overdue</span>';
                    }
                    ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Return Form -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>Return Details
    </div>
    <div class="card-body">
        <form method="post" id="returnForm">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="return_date" class="required-field">Return Date</label>
                    <input type="date" class="form-control" id="return_date" name="return_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Return Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" 
                    placeholder="Enter any notes about the condition of the asset or the return process"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check mr-1"></i>Process Return
            </button>
        </form>
    </div>
</div>

<?php endif; ?>

<script>
$(document).ready(function() {
    $("#returnForm").validate({
        rules: {
            return_date: {
                required: true,
                date: true
            }
        },
        messages: {
            return_date: {
                required: "Please enter the return date",
                date: "Please enter a valid date"
            }
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