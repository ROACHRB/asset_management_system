<?php
// File: modules/audits/new.php
include_once "../../includes/header.php";

// Check permission
if(!has_permission('conduct_audits')) {
    $_SESSION['error'] = "Access denied. You don't have permission to conduct audits.";
    header("Location: ../../index.php");
    exit;
}

// Get locations for dropdown
$locations_query = "SELECT * FROM locations ORDER BY building, room";
$locations_result = mysqli_query($conn, $locations_query);

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $audit_date = trim($_POST['audit_date']);
    $location = trim($_POST['location']);
    $notes = trim($_POST['notes']);
    
    // Validate input
    $errors = [];
    
    if(empty($audit_date)) {
        $errors[] = "Audit date is required";
    }
    
    if(empty($location)) {
        $errors[] = "Location is required";
    }
    
    if(empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert new audit
            $insert_audit = "INSERT INTO physical_audits (audit_date, location, auditor_id, notes) 
                             VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_audit);
            mysqli_stmt_bind_param($stmt, "ssis", $audit_date, $location, $_SESSION['user_id'], $notes);
            $result = mysqli_stmt_execute($stmt);
            
            if(!$result) {
                throw new Exception("Error creating audit: " . mysqli_error($conn));
            }
            
            $audit_id = mysqli_insert_id($conn);
            
            // Get assets in the selected location
            if(is_numeric($location)) {
                // If location is a location_id from the database
                $assets_query = "SELECT * FROM assets WHERE location_id = ? AND status != 'disposed'";
                $stmt = mysqli_prepare($conn, $assets_query);
                mysqli_stmt_bind_param($stmt, "i", $location);
            } else {
                // If location is a custom text entry
                $assets_query = "SELECT * FROM assets WHERE status != 'disposed'";
                $stmt = mysqli_prepare($conn, $assets_query);
            }
            
            mysqli_stmt_execute($stmt);
            $assets_result = mysqli_stmt_get_result($stmt);
            
            // Add assets to audit items
            while($asset = mysqli_fetch_assoc($assets_result)) {
                $insert_item = "INSERT INTO audit_items (audit_id, asset_id, status) 
                                VALUES (?, ?, 'pending')";
                $stmt = mysqli_prepare($conn, $insert_item);
                mysqli_stmt_bind_param($stmt, "ii", $audit_id, $asset['asset_id']);
                $result = mysqli_stmt_execute($stmt);
                
                if(!$result) {
                    throw new Exception("Error adding audit item: " . mysqli_error($conn));
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Log activity
            log_activity('create_audit', "Created new audit #$audit_id for location: $location");
            
            // Set success message
            $_SESSION['success'] = "Audit created successfully. You can now begin scanning assets.";
            
            // Redirect to audit conduct page
            header("Location: conduct.php?id=$audit_id");
            exit;
            
        } catch(Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-plus-circle mr-2"></i>New Audit</h1>
        <p class="text-muted">Create a new physical inventory audit</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Audits
        </a>
    </div>
</div>

<?php if(!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>Audit Information
    </div>
    <div class="card-body">
        <form method="post">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="audit_date">Audit Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="audit_date" name="audit_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="location">Location <span class="text-danger">*</span></label>
                    <select class="form-control" id="location" name="location" required>
                        <option value="">-- Select Location --</option>
                        <?php while($location = mysqli_fetch_assoc($locations_result)): ?>
                            <?php 
                            $location_name = htmlspecialchars($location['building']);
                            if(!empty($location['room'])) {
                                $location_name .= ' - ' . htmlspecialchars($location['room']);
                            }
                            ?>
                            <option value="<?php echo $location['location_id']; ?>">
                                <?php echo $location_name; ?>
                            </option>
                        <?php endwhile; ?>
                        <option value="other">Other (Custom Location)</option>
                    </select>
                    <input type="text" class="form-control mt-2" id="custom_location" 
                           placeholder="Enter custom location" style="display: none;">
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                <small class="form-text text-muted">
                    Enter any additional information about this audit.
                </small>
            </div>
            
            <hr>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i>
                After creating the audit, you will be redirected to the audit page where you can scan asset tags.
            </div>
            
            <div class="form-group text-right">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-2"></i>Create Audit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle custom location input
    $('#location').change(function() {
        if($(this).val() === 'other') {
            $('#custom_location').show().attr('name', 'location');
            $(this).attr('name', '');
        } else {
            $('#custom_location').hide().attr('name', '');
            $(this).attr('name', 'location');
        }
    });
});
</script>

<?php include_once "../../includes/footer.php"; ?>