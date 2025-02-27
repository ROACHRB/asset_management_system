<?php
// FILE PATH: asset_management_system/modules/receiving/process_return.php
// Include necessary files
include_once "../../includes/header.php";
include_once "../../config/database.php";
include_once "../../includes/functions.php";

// Check for POST data or GET ID
$return_id = $_GET['id'] ?? null;
$item_id = $_GET['item_id'] ?? null;

// Initialize variables
$error = '';
$success = '';
$item = [];

// If return ID is provided, fetch the return data
if (!empty($return_id)) {
    $query = "SELECT r.*, a.asset_id, a.asset_tag, a.asset_name, a.serial_number, a.model,
              u.full_name as returned_by_name
              FROM asset_returns r
              LEFT JOIN assets a ON r.asset_id = a.asset_id
              LEFT JOIN users u ON r.returned_by = u.user_id
              WHERE r.return_id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $return_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $item = mysqli_fetch_assoc($result);
    } else {
        $error = "Return record not found.";
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $asset_id = sanitize_input($_POST['asset_id'] ?? '');
    $condition = sanitize_input($_POST['condition'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $status = sanitize_input($_POST['status'] ?? 'pending');
    
    // Basic validation
    if (empty($asset_id)) {
        $error = "Asset ID is required.";
    } elseif (empty($condition)) {
        $error = "Condition assessment is required.";
    } else {
        // Update the asset return record
        $query = "UPDATE asset_returns SET 
                 condition_on_return = ?,
                 notes = ?,
                 status = ?,
                 processed_date = NOW(),
                 processed_by = ?
                 WHERE return_id = ?";
                 
        $stmt = mysqli_prepare($conn, $query);
        $user_id = $_SESSION['user_id'] ?? 1; // Use logged-in user ID or default
        
        mysqli_stmt_bind_param($stmt, "sssii", $condition, $notes, $status, $user_id, $return_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // If approved, update the asset status
            if ($status === 'approved') {
                $update_asset = "UPDATE assets SET 
                                status = 'available',
                                condition_status = ?,
                                notes = CONCAT(notes, ' Return notes: ', ?)
                                WHERE asset_id = ?";
                                
                $stmt = mysqli_prepare($conn, $update_asset);
                mysqli_stmt_bind_param($stmt, "ssi", $condition, $notes, $asset_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Add to asset history
                    $history_query = "INSERT INTO asset_history 
                                     (asset_id, action, performed_by, notes) 
                                     VALUES (?, 'returned', ?, ?)";
                    
                    $stmt = mysqli_prepare($conn, $history_query);
                    $action_note = "Asset returned and processed. Condition: $condition";
                    
                    mysqli_stmt_bind_param($stmt, "iis", $asset_id, $user_id, $action_note);
                    mysqli_stmt_execute($stmt);
                }
            }
            
            $success = "Return has been processed successfully.";
            
            // Redirect after successful processing
            header("Location: returns.php?success=processed");
            exit;
        } else {
            $error = "Error processing return: " . mysqli_error($conn);
        }
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Process Asset Return</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="returns.php">Returns</a></li>
        <li class="breadcrumb-item active">Process Return</li>
    </ol>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-check me-1"></i>
            Process Return
        </div>
        <div class="card-body">
            <?php if (!empty($item)): ?>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="card-title">Asset Information</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Asset ID</th>
                                <td><?php echo htmlspecialchars($item['asset_tag']); ?></td>
                            </tr>
                            <tr>
                                <th>Asset Name</th>
                                <td><?php echo htmlspecialchars($item['asset_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Serial Number</th>
                                <td><?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Model</th>
                                <td><?php echo htmlspecialchars($item['model'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5 class="card-title">Return Details</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>Return Date</th>
                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($item['return_date']))); ?></td>
                            </tr>
                            <tr>
                                <th>Returned By</th>
                                <td><?php echo htmlspecialchars($item['returned_by_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Comments</th>
                                <td><?php echo htmlspecialchars($item['return_comments'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <form action="process_return.php?id=<?php echo $return_id; ?>" method="post">
                    <input type="hidden" name="asset_id" value="<?php echo $item['asset_id']; ?>">
                    
                    <div class="mb-3">
                        <label for="condition" class="form-label">Condition Assessment</label>
                        <select name="condition" id="condition" class="form-control" required>
                            <option value="">-- Select Condition --</option>
                            <option value="new">New / Like New</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                            <option value="unusable">Unusable</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Processing Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Processing Decision</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="approved" value="approved" checked>
                            <label class="form-check-label" for="approved">
                                Approve - Return to inventory
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="rejected" value="rejected">
                            <label class="form-check-label" for="rejected">
                                Reject - Request more information
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="repair" value="repair">
                            <label class="form-check-label" for="repair">
                                Needs Repair - Send to maintenance
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Process Return
                        </button>
                        <a href="returns.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Return record not found. <a href="returns.php" class="alert-link">Return to list</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>