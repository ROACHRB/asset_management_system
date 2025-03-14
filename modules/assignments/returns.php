<?php
include_once "../../includes/header.php";

// Check if the asset_return_requests table exists, create it if it doesn't
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'asset_return_requests'");
if(mysqli_num_rows($table_check) == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE `asset_return_requests` (
                            `request_id` INT(11) NOT NULL AUTO_INCREMENT,
                            `asset_id` INT(11) NOT NULL,
                            `assignment_id` INT(11) NOT NULL,
                            `requested_by` INT(11) NOT NULL,
                            `request_date` DATETIME NOT NULL,
                            `processed_by` INT(11) NULL DEFAULT NULL,
                            `processed_date` DATETIME NULL DEFAULT NULL,
                            `return_condition` ENUM('excellent','good','fair','damaged','incomplete') NOT NULL,
                            `notes` TEXT NULL DEFAULT NULL,
                            `admin_notes` TEXT NULL DEFAULT NULL,
                            `status` ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
                            PRIMARY KEY (`request_id`),
                            FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`),
                            FOREIGN KEY (`assignment_id`) REFERENCES `asset_assignments`(`assignment_id`),
                            FOREIGN KEY (`requested_by`) REFERENCES `users`(`user_id`),
                            FOREIGN KEY (`processed_by`) REFERENCES `users`(`user_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($conn, $create_table_sql);
}

// Check if the return_notes column exists in asset_assignments table
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM asset_assignments LIKE 'return_notes'");
$notes_column_name = 'notes'; // Default column name

if(mysqli_num_rows($check_column) > 0) {
    $notes_column_name = 'return_notes';
}

// Initialize variables
$assignment = null;
$error = "";
$success = "";
$pending_requests = [];

// Check for return requests (new functionality)
$requests_query = "SELECT r.*, a.asset_name, a.asset_tag, u.full_name as user_name,
                          aa.assignment_date, aa.expected_return_date
                   FROM asset_return_requests r
                   JOIN assets a ON r.asset_id = a.asset_id
                   JOIN users u ON r.requested_by = u.user_id
                   JOIN asset_assignments aa ON r.assignment_id = aa.assignment_id
                   WHERE r.status = 'pending'
                   ORDER BY r.request_date DESC";
                   
$requests_result = mysqli_query($conn, $requests_query);
if ($requests_result) {
    while ($row = mysqli_fetch_assoc($requests_result)) {
        $pending_requests[] = $row;
    }
}

// Handle direct return processing (existing functionality)
if(isset($_GET['id']) && !empty($_GET['id'])) {
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
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['return_type'])) {
    if ($_POST['return_type'] == 'direct' && $assignment) {
        // Process direct return (existing functionality)
        $return_date = $_POST['return_date'];
        $notes = $_POST['notes'];
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update assignment status - use the correct column name for notes
            $update_assignment = "UPDATE asset_assignments 
                                SET assignment_status = 'returned',
                                    actual_return_date = ?,
                                    $notes_column_name = ?
                                WHERE assignment_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_assignment);
            mysqli_stmt_bind_param($stmt, "ssi", $return_date, $notes, $assignment_id);
            mysqli_stmt_execute($stmt);
            
            // Update asset status
            $update_asset = "UPDATE assets SET status = 'available' WHERE asset_id = ?";
            $stmt = mysqli_prepare($conn, $update_asset);
            mysqli_stmt_bind_param($stmt, "i", $assignment['asset_id']);
            mysqli_stmt_execute($stmt);
            
            // Add to asset history
            $history_sql = "INSERT INTO asset_history (
                           asset_id, action, action_date, performed_by, notes
                           ) VALUES (?, 'returned', NOW(), ?, ?)";
            
            $stmt = mysqli_prepare($conn, $history_sql);
            $history_notes = "Asset returned directly by administrator. Notes: $notes";
            mysqli_stmt_bind_param($stmt, "iis", $assignment['asset_id'], $_SESSION["user_id"], $history_notes);
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($conn);
            
            // Set success message
            $success = "Asset returned successfully!";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error processing return: " . $e->getMessage();
        }
    } 
    elseif ($_POST['return_type'] == 'request' && isset($_POST['request_id'])) {
        // Process return request (new functionality)
        $request_id = $_POST['request_id'];
        $status = $_POST['status'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Get request details
            $request_query = "SELECT r.*, a.asset_tag, a.asset_name 
                             FROM asset_return_requests r
                             JOIN assets a ON r.asset_id = a.asset_id
                             WHERE r.request_id = ?";
            $stmt = mysqli_prepare($conn, $request_query);
            mysqli_stmt_bind_param($stmt, "i", $request_id);
            mysqli_stmt_execute($stmt);
            $request_result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($request_result) == 0) {
                throw new Exception("Return request not found.");
            }
            
            $request = mysqli_fetch_assoc($request_result);
            
            // Update request status
            $update_sql = "UPDATE asset_return_requests SET 
                           status = ?, 
                           processed_by = ?, 
                           processed_date = NOW(), 
                           admin_notes = ?
                           WHERE request_id = ?";
            
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sisi", $status, $_SESSION["user_id"], $admin_notes, $request_id);
            mysqli_stmt_execute($stmt);
            
            // If completing the return, update asset status and assignment
            if ($status == 'completed') {
                // Update asset status
                $update_asset = "UPDATE assets SET 
                               status = 'available'
                               WHERE asset_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_asset);
                mysqli_stmt_bind_param($stmt, "i", $request['asset_id']);
                mysqli_stmt_execute($stmt);
                
                // Update assignment status
                $update_assignment = "UPDATE asset_assignments SET 
                                    assignment_status = 'returned',
                                    actual_return_date = NOW(),
                                    $notes_column_name = ?
                                    WHERE assignment_id = ?";
                
                $stmt = mysqli_prepare($conn, $update_assignment);
                $notes = "Return completed via user request. Admin notes: $admin_notes";
                mysqli_stmt_bind_param($stmt, "si", $notes, $request['assignment_id']);
                mysqli_stmt_execute($stmt);
                
                // Add to asset history
                $history_sql = "INSERT INTO asset_history (
                              asset_id, action, action_date, performed_by, notes
                              ) VALUES (?, 'returned', NOW(), ?, ?)";
                
                $stmt = mysqli_prepare($conn, $history_sql);
                $history_notes = "Asset returned by user request. Condition: {$request['return_condition']}. Admin notes: $admin_notes";
                mysqli_stmt_bind_param($stmt, "iis", $request['asset_id'], $_SESSION["user_id"], $history_notes);
                mysqli_stmt_execute($stmt);
            }
            
            mysqli_commit($conn);
            
            // Set success message
            $success = "Return request has been " . ($status == 'completed' ? "completed" : $status) . " successfully.";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error processing return request: " . $e->getMessage();
        }
    }
}

// If the form was submitted and successful, reset for a clean display
if ($success && !$error) {
    $assignment = null;
    
    // Refresh the pending requests list
    $pending_requests = [];
    $requests_result = mysqli_query($conn, $requests_query);
    if ($requests_result) {
        while ($row = mysqli_fetch_assoc($requests_result)) {
            $pending_requests[] = $row;
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-undo mr-2"></i>Process Returns</h1>
        <p class="text-muted">Process asset returns and user return requests</p>
    </div>
    <div class="col-md-4 text-right">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Assignments
        </a>
    </div>
</div>

<?php if($error): ?>
<div class="alert alert-danger">
    <?php echo $error; ?>
</div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success">
    <?php echo $success; ?>
</div>
<?php endif; ?>

<!-- Tabs for Return Options -->
<ul class="nav nav-tabs mb-4" id="returnTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?php echo (empty($assignment) && empty($_GET['requestId'])) ? 'active' : ''; ?>" 
           id="requests-tab" data-toggle="tab" href="#requests" role="tab">
            User Return Requests
            <?php if (count($pending_requests) > 0): ?>
                <span class="badge badge-warning"><?php echo count($pending_requests); ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo (!empty($assignment)) ? 'active' : ''; ?>" 
           id="direct-tab" data-toggle="tab" href="#direct" role="tab">
            Direct Return Processing
        </a>
    </li>
</ul>

<div class="tab-content" id="returnTabsContent">
    <!-- Return Requests Tab -->
    <div class="tab-pane fade <?php echo (empty($assignment) && empty($_GET['requestId'])) ? 'show active' : ''; ?>" 
         id="requests" role="tabpanel">
        
        <?php if (empty($pending_requests)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i> There are no pending return requests at this time.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list mr-1"></i>Pending Return Requests
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="requestsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Asset</th>
                                    <th>Requested By</th>
                                    <th>Request Date</th>
                                    <th>Condition</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td><?php echo $request['request_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['asset_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($request['asset_tag']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php
                                            echo ($request['return_condition'] == 'excellent' ? 'success' : 
                                                ($request['return_condition'] == 'good' ? 'primary' : 
                                                ($request['return_condition'] == 'fair' ? 'info' : 
                                                ($request['return_condition'] == 'damaged' ? 'warning' : 'danger'))));
                                        ?>">
                                            <?php echo ucfirst($request['return_condition']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($request['notes'])): ?>
                                            <?php echo nl2br(htmlspecialchars(substr($request['notes'], 0, 50))); ?>
                                            <?php if (strlen($request['notes']) > 50): ?>
                                                ...
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em class="text-muted">No notes</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm process-request" 
                                                data-toggle="modal" data-target="#requestModal" 
                                                data-id="<?php echo $request['request_id']; ?>"
                                                data-asset="<?php echo htmlspecialchars($request['asset_name']); ?>"
                                                data-tag="<?php echo htmlspecialchars($request['asset_tag']); ?>"
                                                data-user="<?php echo htmlspecialchars($request['user_name']); ?>"
                                                data-condition="<?php echo ucfirst($request['return_condition']); ?>"
                                                data-notes="<?php echo htmlspecialchars($request['notes']); ?>">
                                            <i class="fas fa-check"></i> Process
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Direct Return Tab -->
    <div class="tab-pane fade <?php echo (!empty($assignment)) ? 'show active' : ''; ?>" 
         id="direct" role="tabpanel">
        
        <?php if (!$assignment): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-1"></i> To process a direct return, please select an assignment from the 
                <a href="index.php">assignments list</a> and click the "Process Return" button.
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
                        <input type="hidden" name="return_type" value="direct">
                        
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
    </div>
</div>

<!-- Return Request Modal -->
<div class="modal fade" id="requestModal" tabindex="-1" role="dialog" aria-labelledby="requestModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestModalLabel">Process Return Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="return_type" value="request">
                    <input type="hidden" name="request_id" id="request_id">
                    
                    <div class="form-group">
                        <label>Asset:</label>
                        <div id="asset_info" class="form-control-plaintext"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Requested By:</label>
                        <div id="user_info" class="form-control-plaintext"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Reported Condition:</label>
                        <div id="condition_info" class="form-control-plaintext"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>User Notes:</label>
                        <div id="notes_info" class="form-control-plaintext"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status" class="required-field">Action</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="">-- Select Action --</option>
                            <option value="approved">Approve (Pending Physical Return)</option>
                            <option value="completed">Complete Return (Asset Received)</option>
                            <option value="rejected">Reject Request</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                            placeholder="Enter any notes about this return request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable for requests
    $('#requestsTable').DataTable({
        order: [[3, 'desc']] // Sort by request date by default
    });
    
    // Form validation for direct return
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
    
    // Handle request modal data
    $('.process-request').on('click', function() {
        var id = $(this).data('id');
        var asset = $(this).data('asset') + ' (' + $(this).data('tag') + ')';
        var user = $(this).data('user');
        var condition = $(this).data('condition');
        var notes = $(this).data('notes') || 'No notes provided';
        
        $('#request_id').val(id);
        $('#asset_info').text(asset);
        $('#user_info').text(user);
        $('#condition_info').html('<span class="badge badge-info">' + condition + '</span>');
        $('#notes_info').text(notes);
    });
    
    // Set active tab based on URL parameters
    var url = new URL(window.location.href);
    var tab = url.searchParams.get("tab");
    if (tab === "requests") {
        $('#requests-tab').tab('show');
    } else if (tab === "direct") {
        $('#direct-tab').tab('show');
    }
});
</script>

<?php include_once "../../includes/footer.php"; ?>