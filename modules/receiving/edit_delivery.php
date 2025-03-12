<?php
// FILE PATH: asset_management_system/modules/receiving/edit_delivery.php
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
$delivery = null;
$delivery_items = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery'])) {
    // Get form data
    $delivery_date = sanitize_input($conn, $_POST['delivery_date']);
    $supplier = sanitize_input($conn, $_POST['supplier']);
    $reference_number = sanitize_input($conn, $_POST['reference_number']);
    $notes = sanitize_input($conn, $_POST['notes']);
    
    // Validate input
    if (empty($delivery_date) || empty($supplier)) {
        $error = "Delivery date and supplier are required fields.";
    } else {
        // Update delivery record
        $update_query = "UPDATE deliveries SET 
                        delivery_date = ?, 
                        supplier = ?, 
                        reference_number = ?, 
                        notes = ? 
                        WHERE delivery_id = ?";
        
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssssi", $delivery_date, $supplier, $reference_number, $notes, $delivery_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Log the activity
            $log_desc = "Updated delivery #$delivery_id";
            log_activity($_SESSION['user_id'], 'update_delivery', $log_desc);
            
            $success = "Delivery information updated successfully.";
            
            // Refresh delivery data
            $delivery = get_delivery_by_id($conn, $delivery_id);
        } else {
            $error = "Failed to update delivery information: " . mysqli_error($conn);
        }
    }
}

// Get delivery details if not already loaded
if ($delivery === null && !empty($delivery_id)) {
    $delivery = get_delivery_by_id($conn, $delivery_id);
    if (!$delivery) {
        $error = "Delivery not found.";
    }
    
    // Get delivery items
    $items_query = "SELECT di.*, c.category_name
                   FROM delivery_items di
                   LEFT JOIN categories c ON di.category_id = c.category_id
                   WHERE di.delivery_id = ?
                   ORDER BY di.item_name";
    
    $stmt = mysqli_prepare($conn, $items_query);
    mysqli_stmt_bind_param($stmt, "i", $delivery_id);
    mysqli_stmt_execute($stmt);
    $items_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($items_result) > 0) {
        while ($row = mysqli_fetch_assoc($items_result)) {
            $delivery_items[] = $row;
        }
    }
}

// Helper function to get delivery by ID
function get_delivery_by_id($conn, $delivery_id) {
    $query = "SELECT d.*, u.full_name as received_by_name 
              FROM deliveries d
              LEFT JOIN users u ON d.received_by = u.user_id
              WHERE d.delivery_id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $delivery_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Include header after potential redirects
include_once "../../includes/header.php";
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Edit Delivery</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="index.php">Deliveries</a></li>
        <li class="breadcrumb-item active">Edit Delivery</li>
    </ol>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($delivery): ?>
        <div class="row">
            <div class="col-lg-6">
                <!-- Edit Delivery Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i>
                        Edit Delivery Information
                    </div>
                    <div class="card-body">
                        <form action="edit_delivery.php?id=<?php echo $delivery_id; ?>" method="post">
                            <div class="mb-3">
                                <label for="delivery_date" class="form-label">Delivery Date</label>
                                <input type="date" class="form-control" id="delivery_date" name="delivery_date" 
                                       value="<?php echo htmlspecialchars($delivery['delivery_date']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="supplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="supplier" name="supplier"
                                       value="<?php echo htmlspecialchars($delivery['supplier']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reference_number" class="form-label">Reference Number</label>
                                <input type="text" class="form-control" id="reference_number" name="reference_number"
                                       value="<?php echo htmlspecialchars($delivery['reference_number'] ?? ''); ?>">
                                <div class="form-text">Invoice number, PO number, or other reference</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="received_by_name" class="form-label">Received By</label>
                                <input type="text" class="form-control" id="received_by_name" 
                                       value="<?php echo htmlspecialchars($delivery['received_by_name']); ?>" readonly>
                                <div class="form-text">This field cannot be changed</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($delivery['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="update_delivery" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                                <a href="view_delivery.php?id=<?php echo $delivery_id; ?>" class="btn btn-secondary ms-2">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Details
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- Delivery Items -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-list me-1"></i>
                        Delivery Items (<?php echo count($delivery_items); ?>)
                    </div>
                    <div class="card-body">
                        <?php if (!empty($delivery_items)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($delivery_items as $item): ?>
                                            <tr class="<?php echo ($item['status'] == 'pending') ? 'table-warning' : ''; ?>">
                                                <td>
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                    <?php if (!empty($item['description'])): ?>
                                                        <small class="d-block text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
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
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">To manage items, go to:</div>
                                    <a href="process_items.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-clipboard-check me-1"></i> Process Items
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-1"></i> No items found for this delivery.
                            </div>
                        <?php endif; ?>
                    </div>
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

<?php
// Include footer
include_once "../../includes/footer.php";
?>