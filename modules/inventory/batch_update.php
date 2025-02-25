<?php
// FILE PATH: C:\xampp\htdocs\asset_management_system\modules\inventory\batch_update.php
// Include header
include_once "../../includes/header.php";

// Check user permissions
if (!in_array($_SESSION['user_role'], ['admin', 'staff'])) {
    echo "<div class='alert alert-danger'>You don't have permission to access this page.</div>";
    include_once "../../includes/footer.php";
    exit;
}

$success_msg = $error_msg = '';

// Handle batch update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_update'])) {
    $asset_ids = isset($_POST['asset_ids']) ? $_POST['asset_ids'] : [];
    $update_field = isset($_POST['update_field']) ? $_POST['update_field'] : '';
    $new_value = isset($_POST['new_value']) ? $_POST['new_value'] : '';
    
    if (empty($asset_ids)) {
        $error_msg = "Please select at least one asset to update.";
    } elseif (empty($update_field)) {
        $error_msg = "Please select a field to update.";
    } elseif ($update_field !== 'notes' && empty($new_value) && $new_value !== '0') {
        $error_msg = "Please provide a new value.";
    } else {
        // Validate the field
        $allowed_fields = ['status', 'condition_status', 'location_id', 'category_id', 'notes'];
        
        if (!in_array($update_field, $allowed_fields)) {
            $error_msg = "Invalid field selected for update.";
        } else {
            // Start a transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Prepare placeholders for the IN clause
                $placeholders = str_repeat('?,', count($asset_ids) - 1) . '?';
                
                // Prepare the update query
                $query = "UPDATE assets SET $update_field = ? WHERE asset_id IN ($placeholders)";
                
                $stmt = mysqli_prepare($conn, $query);
                
                // Create types string and params array
                $types = 's' . str_repeat('i', count($asset_ids));
                $params = array($types, $new_value);
                
                // Add asset IDs to params array
                foreach ($asset_ids as $id) {
                    $params[] = $id;
                }
                
                // Use call_user_func_array to bind parameters by reference
                $ref_params = array();
                foreach ($params as $key => $value) {
                    $ref_params[$key] = &$params[$key];
                }
                
                call_user_func_array('mysqli_stmt_bind_param', $ref_params);
                
                // Execute the update
                if (mysqli_stmt_execute($stmt)) {
                    // Add history records for each updated asset
                    $history_query = "INSERT INTO asset_history (asset_id, action, performed_by, notes) VALUES (?, 'updated', ?, ?)";
                    $history_stmt = mysqli_prepare($conn, $history_query);
                    
                    $user_id = $_SESSION['user_id'];
                    $field_label = '';
                    
                    // Get user-friendly field label
                    switch ($update_field) {
                        case 'status': $field_label = 'Status'; break;
                        case 'condition_status': $field_label = 'Condition'; break;
                        case 'location_id': $field_label = 'Location'; break;
                        case 'category_id': $field_label = 'Category'; break;
                        case 'notes': $field_label = 'Notes'; break;
                        default: $field_label = $update_field;
                    }
                    
                    // Get display value for certain fields
                    $display_value = $new_value;
                    
                    if ($update_field == 'location_id' && !empty($new_value)) {
                        $loc_query = "SELECT CONCAT(building, IF(room IS NOT NULL AND room != '', CONCAT(' - ', room), '')) as location_name FROM locations WHERE location_id = ?";
                        $loc_stmt = mysqli_prepare($conn, $loc_query);
                        mysqli_stmt_bind_param($loc_stmt, "i", $new_value);
                        mysqli_stmt_execute($loc_stmt);
                        $loc_result = mysqli_stmt_get_result($loc_stmt);
                        if ($loc_row = mysqli_fetch_assoc($loc_result)) {
                            $display_value = $loc_row['location_name'];
                        }
                    } elseif ($update_field == 'category_id' && !empty($new_value)) {
                        $cat_query = "SELECT category_name FROM categories WHERE category_id = ?";
                        $cat_stmt = mysqli_prepare($conn, $cat_query);
                        mysqli_stmt_bind_param($cat_stmt, "i", $new_value);
                        mysqli_stmt_execute($cat_stmt);
                        $cat_result = mysqli_stmt_get_result($cat_stmt);
                        if ($cat_row = mysqli_fetch_assoc($cat_result)) {
                            $display_value = $cat_row['category_name'];
                        }
                    }
                    
                    $history_note = "Batch Update: $field_label changed to " . htmlspecialchars($display_value);
                    
                    foreach ($asset_ids as $asset_id) {
                        mysqli_stmt_bind_param($history_stmt, "iis", $asset_id, $user_id, $history_note);
                        mysqli_stmt_execute($history_stmt);
                    }
                    
                    // Commit the transaction
                    mysqli_commit($conn);
                    
                    $success_msg = "Successfully updated " . count($asset_ids) . " assets.";
                } else {
                    throw new Exception(mysqli_error($conn));
                }
            } catch (Exception $e) {
                // Rollback the transaction on error
                mysqli_rollback($conn);
                $error_msg = "Error performing batch update: " . $e->getMessage();
            }
        }
    }
}

// Get all categories for dropdown
$categories_query = "SELECT category_id, category_name FROM categories ORDER BY category_name";
$categories_result = mysqli_query($conn, $categories_query);

// Get all locations for dropdown
$locations_query = "SELECT location_id, building, room FROM locations ORDER BY building, room";
$locations_result = mysqli_query($conn, $locations_query);

// Get assets for selection
$assets_query = "SELECT a.asset_id, a.asset_tag, a.asset_name, a.status, c.category_name 
                FROM assets a
                LEFT JOIN categories c ON a.category_id = c.category_id
                ORDER BY a.asset_id DESC";
$assets_result = mysqli_query($conn, $assets_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>
            <i class="fas fa-layer-group mr-2"></i>Batch Asset Update
        </h1>
        <p class="text-muted">Update multiple assets at once to save time</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Inventory
        </a>
    </div>
</div>

<!-- Display messages -->
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-1"></i> <?php echo $success_msg; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-1"></i> <?php echo $error_msg; ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Batch Update Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>
        Batch Update Assets
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-1"></i> Use this feature to update multiple assets at once. Select the assets, choose which field to update, and provide the new value.
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="asset_ids">Select Assets to Update*</label>
                <div class="alert alert-secondary">
                    <small><i class="fas fa-info-circle mr-1"></i> Hold Ctrl (or Cmd on Mac) to select multiple assets</small>
                </div>
                <select multiple class="form-control" id="asset_ids" name="asset_ids[]" size="10" required>
                    <?php 
                    if(mysqli_num_rows($assets_result) > 0) {
                        while($asset = mysqli_fetch_assoc($assets_result)) {
                            $asset_label = '';
                            if (!empty($asset['asset_tag'])) {
                                $asset_label .= $asset['asset_tag'] . ' - ';
                            }
                            $asset_label .= $asset['asset_name'];
                            
                            if (!empty($asset['category_name'])) {
                                $asset_label .= ' (' . $asset['category_name'] . ')';
                            }
                            
                            echo '<option value="' . $asset['asset_id'] . '">' . htmlspecialchars($asset_label) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="update_field">Field to Update*</label>
                <select class="form-control" id="update_field" name="update_field" required>
                    <option value="">-- Select Field --</option>
                    <option value="status">Status</option>
                    <option value="condition_status">Condition</option>
                    <option value="location_id">Location</option>
                    <option value="category_id">Category</option>
                    <option value="notes">Notes</option>
                </select>
            </div>
            
            <!-- Dynamic new value field - will be replaced based on selection -->
            <div class="form-group" id="new_value_container">
                <label for="new_value">New Value*</label>
                <input type="text" class="form-control" id="new_value" name="new_value">
            </div>
            
            <button type="submit" name="batch_update" class="btn btn-primary" id="updateButton">
                <i class="fas fa-save mr-1"></i>Update Assets
            </button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // When update field changes, update the input type
    $('#update_field').change(function() {
        const field = $(this).val();
        let html = '';
        
        switch(field) {
            case 'status':
                html = `
                    <label for="new_value">New Status*</label>
                    <select class="form-control" id="new_value" name="new_value" required>
                        <option value="">-- Select Status --</option>
                        <option value="available">Available</option>
                        <option value="assigned">Assigned</option>
                        <option value="under_repair">Under Repair</option>
                        <option value="disposed">Disposed</option>
                        <option value="lost">Lost</option>
                        <option value="stolen">Stolen</option>
                    </select>
                `;
                break;
                
            case 'condition_status':
                html = `
                    <label for="new_value">New Condition*</label>
                    <select class="form-control" id="new_value" name="new_value" required>
                        <option value="">-- Select Condition --</option>
                        <option value="new">New</option>
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                        <option value="unusable">Unusable</option>
                    </select>
                `;
                break;
                
            case 'location_id':
                html = `
                    <label for="new_value">New Location*</label>
                    <select class="form-control" id="new_value" name="new_value" required>
                        <option value="">-- Select Location --</option>
                        <?php 
                        mysqli_data_seek($locations_result, 0);
                        if(mysqli_num_rows($locations_result) > 0) {
                            while($location = mysqli_fetch_assoc($locations_result)) {
                                $location_name = htmlspecialchars($location['building']);
                                if (!empty($location['room'])) {
                                    $location_name .= ' - ' . htmlspecialchars($location['room']);
                                }
                                echo '<option value="' . $location['location_id'] . '">' . $location_name . '</option>';
                            }
                        }
                        ?>
                    </select>
                `;
                break;
                
            case 'category_id':
                html = `
                    <label for="new_value">New Category*</label>
                    <select class="form-control" id="new_value" name="new_value" required>
                        <option value="">-- Select Category --</option>
                        <?php 
                        mysqli_data_seek($categories_result, 0);
                        if(mysqli_num_rows($categories_result) > 0) {
                            while($category = mysqli_fetch_assoc($categories_result)) {
                                echo '<option value="' . $category['category_id'] . '">' . 
                                    htmlspecialchars($category['category_name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                `;
                break;
                
            case 'notes':
                html = `
                    <label for="new_value">New Notes</label>
                    <textarea class="form-control" id="new_value" name="new_value" rows="3"></textarea>
                `;
                break;
                
            default:
                html = `
                    <label for="new_value">New Value*</label>
                    <input type="text" class="form-control" id="new_value" name="new_value" required>
                `;
        }
        
        $('#new_value_container').html(html);
    });
    
    // Form validation
    $('form').submit(function(e) {
        const assetIds = $('#asset_ids').val();
        if (!assetIds || assetIds.length === 0) {
            alert('Please select at least one asset to update.');
            e.preventDefault();
            return false;
        }
        
        const updateField = $('#update_field').val();
        if (!updateField) {
            alert('Please select a field to update.');
            e.preventDefault();
            return false;
        }
        
        const newValue = $('#new_value').val();
        if (updateField !== 'notes' && (!newValue && newValue !== '0')) {
            alert('Please provide a new value.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>