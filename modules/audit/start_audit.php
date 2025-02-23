// FILE: D:\xampp\htdocs\asset_management_system\modules\audit\start_audit.php
<?php
include_once "../../includes/header.php";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $location = sanitize_input($conn, $_POST['location']);
    $notes = sanitize_input($conn, $_POST['notes']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Create audit record
        $sql = "INSERT INTO physical_audits (audit_date, location, auditor_id, status, notes) 
                VALUES (NOW(), ?, ?, 'in_progress', ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sis", $location, $_SESSION['user_id'], $notes);
        mysqli_stmt_execute($stmt);
        
        $audit_id = mysqli_insert_id($conn);
        
        // Get assets for this location
        $assets_sql = "SELECT asset_id, asset_name, asset_tag FROM assets 
                      WHERE location_id = ? AND status != 'disposed'";
        $assets_stmt = mysqli_prepare($conn, $assets_sql);
        mysqli_stmt_bind_param($assets_stmt, "i", $_POST['location_id']);
        mysqli_stmt_execute($assets_stmt);
        $assets_result = mysqli_stmt_get_result($assets_stmt);
        
        // Create audit items
        while($asset = mysqli_fetch_assoc($assets_result)) {
            $item_sql = "INSERT INTO audit_items (audit_id, asset_id, status) VALUES (?, ?, 'pending')";
            $item_stmt = mysqli_prepare($conn, $item_sql);
            mysqli_stmt_bind_param($item_stmt, "ii", $audit_id, $asset['asset_id']);
            mysqli_stmt_execute($item_stmt);
        }
        
        mysqli_commit($conn);
        header("Location: continue_audit.php?id=" . $audit_id);
        exit;
        
    } catch(Exception $e) {
        mysqli_rollback($conn);
        $error = "Error creating audit: " . $e->getMessage();
    }
}

// Get locations
$locations_query = "SELECT * FROM locations ORDER BY building, room";
$locations_result = mysqli_query($conn, $locations_query);
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-clipboard mr-2"></i>Start New Audit</h1>
        <p class="text-muted">Begin a new physical inventory count</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit mr-1"></i>Audit Details
    </div>
    <div class="card-body">
        <form method="post" id="auditForm">
            <div class="form-group">
                <label for="location_id">Select Location</label>
                <select class="form-control" id="location_id" name="location_id" required>
                    <option value="">-- Select Location --</option>
                    <?php while($loc = mysqli_fetch_assoc($locations_result)): ?>
                        <option value="<?php echo $loc['location_id']; ?>">
                            <?php echo htmlspecialchars($loc['building'] . ' - ' . $loc['room']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="location">Location Description</label>
                <input type="text" class="form-control" id="location" name="location" required>
            </div>
            
            <div class="form-group">
                <label for="notes">Audit Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-play mr-1"></i>Start Audit
            </button>
        </form>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>