<?php
// FILE PATH: asset_management_system/modules/receiving/process_items.php
// Include header
include_once "../../includes/header.php";
include_once "../../config/database.php";
include_once "../../includes/functions.php";

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Get delivery ID
$delivery_id = $_GET['id'] ?? 0;

// Initialize variables
$error = '';
$success = '';
$items = [];
$pending_count = 0;
$total_count = 0;

// Get delivery details
if (!empty($delivery_id)) {
    $query = "SELECT d.*, u.full_name as received_by_name 
              FROM deliveries d
              LEFT JOIN users u ON d.received_by = u.user_id
              WHERE d.delivery_id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $delivery_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $delivery = mysqli_fetch_assoc($result);
        
        // Get delivery items
        $items_query = "SELECT di.*, c.category_name,
                        CASE WHEN di.status = 'pending' THEN 0 ELSE 1 END as is_processed
                        FROM delivery_items di
                        LEFT JOIN categories c ON di.category_id = c.category_id
                        WHERE di.delivery_id = ?
                        ORDER BY di.status = 'pending' DESC, di.item_name";
        
        $stmt = mysqli_prepare($conn, $items_query);
        mysqli_stmt_bind_param($stmt, "i", $delivery_id);
        mysqli_stmt_execute($stmt);
        $items_result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($items_result) > 0) {
            while ($row = mysqli_fetch_assoc($items_result)) {
                $items[] = $row;
                $total_count++;
                if ($row['status'] == 'pending') {
                    $pending_count++;
                }
            }
        } else {
            $error = "No items found for this delivery.";
        }
    } else {
        $error = "Delivery not found.";
    }
} else {
    $error = "No delivery ID provided.";
}

// Get success message if any
$success_message = '';
if(isset($_GET['success'])) {
    if($_GET['success'] == 'stored') {
        $success_message = 'Item has been stored successfully.';
    } elseif($_GET['success'] == 'tagged') {
        $success_message = 'Item has been tagged as an asset successfully.';
    }
}

// Get categories for bulk operations
$categories_query = "SELECT category_id, category_name FROM categories ORDER BY category_name";
$categories_result = mysqli_query($conn, $categories_query);

// Get locations for bulk operations
$locations_query = "SELECT location_id, building, room, department FROM locations ORDER BY building, room";
$locations_result = mysqli_query($conn, $locations_query);

// Process bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = sanitize_input($_POST['bulk_action']);
    $selected_items = $_POST['selected_items'] ?? [];
    
    if (empty($selected_items)) {
        $error = "No items selected for bulk action.";
    } else {
        if ($bulk_action === 'store') {
            $location_id = sanitize_input($_POST['bulk_location_id'] ?? '');
            $notes = sanitize_input($_POST['bulk_notes'] ?? '');
            
            if (empty($location_id)) {
                $error = "Storage location is required for bulk store action.";
            } else {
                $success_count = 0;
                
                foreach ($selected_items as $item_id) {
                    // Update delivery item status
                    $update_item = "UPDATE delivery_items SET status = 'stored', notes = ? WHERE item_id = ? AND status = 'pending'";
                    $stmt = mysqli_prepare($conn, $update_item);
                    mysqli_stmt_bind_param($stmt, "si", $notes, $item_id);
                    
                    if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
                        $success_count++;
                    }
                }
                
                if ($success_count > 0) {
                    $success_message = "$success_count items have been marked as stored successfully.";
                    
                    // Refresh the page to show updated status
                    header("Location: process_items.php?id=$delivery_id&success=bulk_stored&count=$success_count");
                    exit;
                } else {
                    $error = "No items were processed. They may have already been processed.";
                }
            }
        }
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Process Delivery Items</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="index.php">Deliveries</a></li>
        <li class="breadcrumb-item"><a href="view_delivery.php?id=<?php echo $delivery_id; ?>">Delivery Details</a></li>
        <li class="breadcrumb-item active">Process Items</li>
    </ol>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($delivery)): ?>
        <!-- Delivery Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-truck-loading me-1"></i>
                Delivery Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Delivery Date:</strong> <?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Supplier:</strong> <?php echo htmlspecialchars($delivery['supplier']); ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Reference Number:</strong> <?php echo htmlspecialchars($delivery['reference_number'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Received By:</strong> <?php echo htmlspecialchars($delivery['received_by_name']); ?></p>
                    </div>
                    <div class="col-md-8">
                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($delivery['notes'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($pending_count > 0): ?>
            <!-- Bulk Actions Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-tasks me-1"></i>
                    Bulk Actions
                </div>
                <div class="card-body">
                    <form id="bulkActionForm" action="process_items.php?id=<?php echo $delivery_id; ?>" method="post">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-3">
                                <label for="bulk_action" class="form-label">Action</label>
                                <select class="form-select" id="bulk_action" name="bulk_action" required>
                                    <option value="">-- Select Action --</option>
                                    <option value="store">Mark as Stored (Consumables)</option>
                                </select>
                            </div>
                            
                            <!-- Store Location (shown when 'store' action is selected) -->
                            <div class="col-md-3 mb-3" id="bulkLocationField" style="display: none;">
                                <label for="bulk_location_id" class="form-label">Storage Location</label>
                                <select class="form-select" id="bulk_location_id" name="bulk_location_id">
                                    <option value="">-- Select Location --</option>
                                    <?php 
                                    mysqli_data_seek($locations_result, 0);
                                    while($location = mysqli_fetch_assoc($locations_result)) {
                                        $location_name = $location['building'];
                                        if (!empty($location['room'])) {
                                            $location_name .= ' - ' . $location['room'];
                                        }
                                        if (!empty($location['department'])) {
                                            $location_name .= ' (' . $location['department'] . ')';
                                        }
                                        
                                        echo '<option value="' . $location['location_id'] . '">' . 
                                            htmlspecialchars($location_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Notes (shown when 'store' action is selected) -->
                            <div class="col-md-3 mb-3" id="bulkNotesField" style="display: none;">
                                <label for="bulk_notes" class="form-label">Notes</label>
                                <input type="text" class="form-control" id="bulk_notes" name="bulk_notes">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <button type="submit" class="btn btn-primary" id="applyBulkAction" disabled>
                                    <i class="fas fa-play me-1"></i> Apply to Selected Items
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-text mb-3">
                            * Select items from the list below and choose an action to process multiple items at once.
                        </div>
                    
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Items List Card -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-list me-1"></i>
                        Delivery Items (<?php echo $pending_count; ?> pending of <?php echo $total_count; ?> total)
                    </div>
                    <?php if ($pending_count > 0): ?>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllPending">
                            <i class="fas fa-check-square me-1"></i> Select All Pending
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">
                            <i class="fas fa-square me-1"></i> Deselect All
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="itemsTable">
                        <thead>
                            <tr>
                                <?php if ($pending_count > 0): ?>
                                <th style="width: 40px;"><input type="checkbox" id="checkAll"></th>
                                <?php endif; ?>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr class="<?php echo ($item['status'] == 'pending') ? 'table-warning' : ''; ?>">
                                        <?php if ($pending_count > 0): ?>
                                        <td class="text-center">
                                            <?php if ($item['status'] == 'pending'): ?>
                                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['item_id']; ?>" class="item-checkbox">
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                            <?php if (!empty($item['description'])): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td class="text-end">
                                            <?php echo !empty($item['unit_cost']) ? '$' . number_format($item['unit_cost'], 2) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php
                                            switch ($item['status']) {
                                                case 'pending':
                                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    break;
                                                case 'tagged':
                                                    echo '<span class="badge bg-info">Tagged</span>';
                                                    break;
                                                case 'stored':
                                                    echo '<span class="badge bg-success">Stored</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($item['status'] == 'pending'): ?>
                                                <a href="process_item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-clipboard-check"></i> Process
                                                </a>
                                            <?php elseif ($item['status'] == 'tagged'): ?>
                                                <a href="../inventory/index.php?tag=<?php echo $item['item_name']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-barcode"></i> View Asset
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-check"></i> Processed
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($pending_count > 0) ? '7' : '6'; ?>" class="text-center">No items found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($pending_count > 0): ?>
                    </form> <!-- Close the form started in bulk actions section -->
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="view_delivery.php?id=<?php echo $delivery_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Delivery Details
                    </a>
                    <?php if ($pending_count === 0 && $total_count > 0): ?>
                        <a href="index.php" class="btn btn-success ms-2">
                            <i class="fas fa-check-circle me-1"></i> All Items Processed
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Delivery not found. <a href="index.php" class="alert-link">Back to deliveries list</a>.
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#itemsTable').DataTable({
        order: [[5, 'asc']], // Sort by status (pending first)
        pageLength: 25,
        "columnDefs": [
            { "orderable": false, "targets": [0, 6] } // Disable sorting on checkbox and actions columns
        ]
    });
    
    // Show/hide bulk action fields based on selection
    $('#bulk_action').change(function() {
        const action = $(this).val();
        
        // Hide all conditional fields first
        $('#bulkLocationField, #bulkNotesField').hide();
        
        if (action === 'store') {
            $('#bulkLocationField, #bulkNotesField').show();
        }
        
        updateBulkActionButton();
    });
    
    // Check All checkbox functionality
    $('#checkAll').click(function() {
        $('.item-checkbox').prop('checked', this.checked);
        updateBulkActionButton();
    });
    
    // Select All Pending button
    $('#selectAllPending').click(function() {
        $('.item-checkbox').prop('checked', true);
        $('#checkAll').prop('checked', true);
        updateBulkActionButton();
    });
    
    // Deselect All button
    $('#deselectAll').click(function() {
        $('.item-checkbox').prop('checked', false);
        $('#checkAll').prop('checked', false);
        updateBulkActionButton();
    });
    
    // Individual checkbox change
    $('.item-checkbox').change(function() {
        updateBulkActionButton();
        
        // Update "Check All" checkbox state
        if ($('.item-checkbox:checked').length === $('.item-checkbox').length) {
            $('#checkAll').prop('checked', true);
        } else {
            $('#checkAll').prop('checked', false);
        }
    });
    
    // Function to enable/disable bulk action button
    function updateBulkActionButton() {
        const actionSelected = $('#bulk_action').val() !== '';
        const itemsSelected = $('.item-checkbox:checked').length > 0;
        
        // Additional validation for specific actions
        let additionalValidation = true;
        
        if ($('#bulk_action').val() === 'store') {
            additionalValidation = $('#bulk_location_id').val() !== '';
        }
        
        $('#applyBulkAction').prop('disabled', !(actionSelected && itemsSelected && additionalValidation));
    }
    
    // Validate required fields for bulk actions
    $('#bulk_location_id').change(function() {
        updateBulkActionButton();
    });
    
    // Form validation before submit
    $('#bulkActionForm').submit(function(e) {
        const actionSelected = $('#bulk_action').val() !== '';
        const itemsSelected = $('.item-checkbox:checked').length > 0;
        
        if (!actionSelected) {
            alert('Please select an action to perform.');
            e.preventDefault();
            return false;
        }
        
        if (!itemsSelected) {
            alert('Please select at least one item to process.');
            e.preventDefault();
            return false;
        }
        
        if ($('#bulk_action').val() === 'store' && $('#bulk_location_id').val() === '') {
            alert('Please select a storage location.');
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